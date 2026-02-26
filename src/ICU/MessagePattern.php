<?php

declare(strict_types=1);

namespace Matecat\ICU;

use Iterator;
use Matecat\ICU\Exceptions\BadChoicePatternSyntaxException;
use Matecat\ICU\Exceptions\BadPluralSelectPatternSyntaxException;
use Matecat\ICU\Exceptions\InvalidArgumentException;
use Matecat\ICU\Exceptions\InvalidNumericValueException;
use Matecat\ICU\Exceptions\OutOfBoundsException;
use Matecat\ICU\Exceptions\UnmatchedBracesException;
use Matecat\ICU\Parsing\MessagePatternParser;
use Matecat\ICU\Parsing\ParseContext;
use Matecat\ICU\Parsing\PartAccessor;
use Matecat\ICU\Parsing\Style\NumericParser;
use Matecat\ICU\Parsing\Utils\CharUtils;
use Matecat\ICU\Tokens\ArgType;
use Matecat\ICU\Tokens\Part;
use Matecat\ICU\Tokens\TokenType;

/**
 * This is a porting of The ICU MessageFormat Parser by Markus Scherer:
 *
 * @link https://github.com/prepare/icu4j/blob/master/main/classes/core/src/com/ibm/icu/text/MessagePattern.java
 *
 * Parses and represents ICU MessageFormat patterns.
 * Also handles patterns for ChoiceFormat, PluralFormat, and SelectFormat.
 * Used in the implementations of those classes as well as in tools
 * for message validation, translation, and format conversion.
 * <p>
 * The parser handles all syntax relevant for identifying message arguments.
 * This includes "complex" arguments whose style strings contain
 * nested MessageFormat pattern substrings.
 * For "simple" arguments (with no nested MessageFormat pattern substrings),
 * the argument style is not parsed any further.
 * <p>
 * The parser handles named and numbered message arguments and allows both in one message.
 * <p>
 * Once a pattern has been parsed successfully, iterate through the parsed data
 * with countParts(), getPart() and related methods.
 * <p>
 * The data logically represents a parse tree but is stored and accessed
 * as a list of "parts" for fast and simple parsing and to minimize object allocations.
 * Arguments and nested messages are best handled via recursion.
 * For every _START "part", getLimitPartIndex(int) efficiently returns
 * the index of the corresponding _LIMIT "part".
 * <p>
 * List of "parts":
 * <pre>
 * message = MSG_START (SKIP_SYNTAX | INSERT_CHAR | REPLACE_NUMBER | argument)* MSG_LIMIT
 * argument = noneArg | simpleArg | complexArg
 * complexArg = choiceArg | pluralArg | selectArg
 *
 * noneArg = ARG_START.NONE (ARG_NAME | ARG_NUMBER) ARG_LIMIT.NONE
 * simpleArg = ARG_START.SIMPLE (ARG_NAME | ARG_NUMBER) ARG_TYPE [ARG_STYLE] ARG_LIMIT.SIMPLE
 * choiceArg = ARG_START.CHOICE (ARG_NAME | ARG_NUMBER) choiceStyle ARG_LIMIT.CHOICE
 * pluralArg = ARG_START.PLURAL (ARG_NAME | ARG_NUMBER) pluralStyle ARG_LIMIT.PLURAL
 * selectArg = ARG_START.SELECT (ARG_NAME | ARG_NUMBER) selectStyle ARG_LIMIT.SELECT
 *
 * choiceStyle = ((ARG_INT | ARG_DOUBLE) ARG_SELECTOR message)+
 * pluralStyle = [ARG_INT | ARG_DOUBLE] (ARG_SELECTOR [ARG_INT | ARG_DOUBLE] message)+
 * selectStyle = (ARG_SELECTOR message)+
 * </pre>
 * <ul>
 *   <li>Literal output text is not represented directly by "parts" but accessed
 *       between parts of a message, from one part's getLimit() to the next part's getIndex().
 *   <li>ARG_START.CHOICE stands for an ARG_START Part with ArgType CHOICE.
 *   <li>In the choiceStyle, the ARG_SELECTOR has the '<', the '#' or
 *       the less-than-or-equal-to sign (U+2264).
 *   <li>In the pluralStyle, the first, optional numeric Part has the "offset:" value.
 *       The optional numeric Part between each (ARG_SELECTOR, message) pair
 *       is the value of an explicit-number selector like "=2",
 *       otherwise the selector is a non-numeric identifier.
 *   <li>The REPLACE_NUMBER Part can occur only in an immediate sub-message of the pluralStyle.
 * </ul>
 * <p>
 * This class is not intended for public subclassing.
 *
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 *
 * @implements Iterator<Part>
 */
final class MessagePattern implements Iterator
{
    /**
     * Return value from validateArgumentName() for when
     * the string is a valid "pattern identifier" but not a number.
     */
    public const int ARG_NAME_NOT_NUMBER = -1;
    /**
     * Return value from validateArgumentName() for when
     * the string is invalid.
     * It might not be a valid "pattern identifier",
     * or it has only ASCII digits, but there is a leading zero or the number is too large.
     */
    public const int ARG_NAME_NOT_VALID = -2;
    /**
     * Return value indicating that the argument value exceeds
     * the allowed or expected range.
     */
    public const int ARG_VALUE_OVERFLOW = -3;

    /**
     * Special value that is returned by getNumericValue(Part) when no
     * numeric value is defined for a part.
     * @see getNumericValue()
     */
    public const float NO_NUMERIC_VALUE = -123456789.0;
    /**
     * A literal apostrophe is represented by either a single or a double apostrophe pattern character.
     * Within a MessageFormat pattern, a single apostrophe only starts quoted literal text
     * if it immediately precedes a curly brace {}, or a pipe symbol | if inside a choice format,
     * or a pound symbol # if inside a plural format.
     * <p>
     * This is the default behavior starting with ICU 4.8.
     */
    public const string APOSTROPHE_DOUBLE_OPTIONAL = 'DOUBLE_OPTIONAL';
    /**
     * A literal apostrophe must be represented by a double apostrophe pattern character.
     * A single apostrophe always starts quoted literal text.
     * <p>
     * This is the behavior of ICU 4.6 and earlier, and of java.text.MessageFormat.
     */
    public const string APOSTROPHE_DOUBLE_REQUIRED = 'DOUBLE_REQUIRED';

    private ParseContext $ctx;
    private MessagePatternParser $parser;
    private PartAccessor $partAccessor;

    /**
     * Tracks the current position for iteration.
     */
    private int $position = 0;

    /**
     * @throws InvalidArgumentException If the pattern syntax is invalid.
     * @throws UnmatchedBracesException If the pattern contains unmatched '{' or '}' braces.
     * @throws BadPluralSelectPatternSyntaxException If a plural/select pattern is malformed or missing the "other" case.
     * @throws BadChoicePatternSyntaxException If a choice pattern has invalid syntax (e.g., empty selector, bad nesting).
     * @throws InvalidNumericValueException If a numeric value in the pattern has bad syntax.
     * @throws OutOfBoundsException If certain limits are exceeded
     *         (e.g., argument number too high, argument name too long, nesting too deep, etc.)
     */
    public function __construct(?string $pattern = null, string $apostropheMode = self::APOSTROPHE_DOUBLE_OPTIONAL)
    {
        $this->ctx = new ParseContext($apostropheMode);
        $this->parser = new MessagePatternParser($this->ctx);
        $this->partAccessor = new PartAccessor($this->ctx);
        if (!empty($pattern)) {
            $this->parse($pattern);
        }
    }

    // ──────────────────────────────────────────────
    // Parsing entry points
    // ──────────────────────────────────────────────

    /**
     * Parses a MessageFormat pattern string.
     * @param string $pattern a MessageFormat pattern string
     * @return $this
     * @throws InvalidArgumentException If the pattern syntax is invalid.
     * @throws UnmatchedBracesException If the pattern contains unmatched '{' or '}' braces.
     * @throws BadPluralSelectPatternSyntaxException If a plural/select pattern is malformed or missing the "other" case.
     * @throws BadChoicePatternSyntaxException If a choice pattern has invalid syntax (e.g., empty selector, bad nesting).
     * @throws InvalidNumericValueException If a numeric value in the pattern has bad syntax.
     * @throws OutOfBoundsException If certain limits are exceeded
     *         (e.g., argument number too high, argument name too long, nesting too deep, etc.)
     */
    public function parse(string $pattern): self
    {
        $this->parser->parse($pattern);
        return $this;
    }

    /**
     * Parses a ChoiceFormat pattern string.
     * @param string $pattern a ChoiceFormat pattern string
     * @return $this
     * @throws InvalidArgumentException If the pattern syntax is invalid or has missing segments.
     * @throws UnmatchedBracesException If the pattern contains unmatched '{' or '}' braces.
     * @throws BadChoicePatternSyntaxException If the choice pattern has invalid syntax (e.g., empty selector, bad nesting).
     * @throws InvalidNumericValueException If a numeric selector has bad syntax.
     * @throws OutOfBoundsException If numeric selectors or other elements exceed allowed length limits.
     */
    public function parseChoiceStyle(string $pattern): self
    {
        $this->parser->parseChoiceStyle($pattern);
        return $this;
    }

    /**
     * Parses a PluralFormat pattern string.
     * @param string $pattern a PluralFormat pattern string
     * @return $this
     * @throws InvalidArgumentException If the pattern syntax is invalid.
     * @throws UnmatchedBracesException If the pattern contains unmatched '{' or '}' braces.
     * @throws BadPluralSelectPatternSyntaxException If the plural pattern is malformed or missing the "other" case.
     * @throws InvalidNumericValueException If a numeric value in the pattern has bad syntax.
     * @throws OutOfBoundsException If selectors or offset values exceed allowed length limits.
     */
    public function parsePluralStyle(string $pattern): self
    {
        $this->parser->parsePluralOrSelect(ArgType::PLURAL, $pattern);
        return $this;
    }

    /**
     * Parses a SelectFormat pattern string.
     * @param string $pattern a SelectFormat pattern string
     * @return $this
     * @throws InvalidArgumentException If the pattern syntax is invalid.
     * @throws UnmatchedBracesException If the pattern contains unmatched '{' or '}' braces.
     * @throws BadPluralSelectPatternSyntaxException If the select pattern is malformed or missing the "other" case.
     * @throws OutOfBoundsException If selectors exceed allowed length limits.
     */
    public function parseSelectStyle(string $pattern): self
    {
        $this->parser->parsePluralOrSelect(ArgType::SELECT, $pattern);
        return $this;
    }

    // ──────────────────────────────────────────────
    // State management
    // ──────────────────────────────────────────────

    /**
     * Clears this MessagePattern.
     * countParts() will return 0.
     */
    public function clear(): void
    {
        $this->ctx->clear();
    }

    /**
     * Clears this MessagePattern and sets the ApostropheMode.
     * countParts() will return 0.
     * @param string $mode The new ApostropheMode.
     */
    public function clearPatternAndSetApostropheMode(string $mode): void
    {
        $this->ctx->clear();
        $this->ctx->aposMode = $mode;
    }

    // ──────────────────────────────────────────────
    // Accessors
    // ──────────────────────────────────────────────

    /**
     * Returns the PartAccessor for querying parsed parts.
     *
     * Use this to access countParts(), getPart(), getSubstring(),
     * getNumericValue(), getLimitPartIndex() and other part-level queries.
     *
     * @return PartAccessor
     */
    public function parts(): PartAccessor
    {
        return $this->partAccessor;
    }

    /**
     * @return string this instance's ApostropheMode.
     */
    public function getApostropheMode(): string
    {
        return $this->ctx->aposMode;
    }

    /**
     * @return string the parsed pattern string (empty if none was parsed).
     */
    public function getPatternString(): string
    {
        return $this->ctx->msg;
    }

    /**
     * Does the parsed pattern have named arguments like {first_name}?
     * @return bool true if the parsed pattern has at least one named argument.
     */
    public function hasNamedArguments(): bool
    {
        return $this->ctx->hasArgNames;
    }

    /**
     * Does the parsed pattern have numbered arguments like {2}?
     * @return bool true if the parsed pattern has at least one numbered argument.
     */
    public function hasNumberedArguments(): bool
    {
        return $this->ctx->hasArgNumbers;
    }

    /**
     * Returns a version of the parsed pattern string where each ASCII apostrophe
     * is doubled (escaped) if it is not already, and if it is not interpreted as quoting syntax.
     * <p>
     *    For example, this turns "I don't '{know}' {gender,select,female{h''er}other{h'im}}."
     *    into "I don''t '{know}' {gender,select,female{h''er}other{h''im}}."
     * </p>
     * @return string the deep-auto-quoted version of the parsed pattern string.
     */
    public function autoQuoteApostropheDeep(): string
    {
        if (!$this->ctx->needsAutoQuoting) {
            return $this->ctx->msg;
        }

        $modified = $this->ctx->msg;

        foreach (array_reverse(iterator_to_array($this, false)) as $part) {
            if ($part->getType() === TokenType::INSERT_CHAR) {
                $index = $part->getIndex();
                $char = mb_chr($part->getValue());
                $modified = mb_substr($modified, 0, $index) . $char . mb_substr($modified, $index);
            }
        }

        return $modified;
    }

    // ──────────────────────────────────────────────
    // Static utilities
    // ──────────────────────────────────────────────

    /**
     * Validates and parses an argument name or argument number string.
     * An argument name must be a "pattern identifier", that is, it must contain
     * no Unicode Pattern_Syntax or Pattern_White_Space characters.
     * If it only contains ASCII digits, then it must be a small integer with no leading zero.
     * @param string $name Input string.
     * @return int >=0 if the name is a valid number,
     *         ARG_NAME_NOT_NUMBER (-1) if it is a "pattern identifier" but not all ASCII digits,
     *         ARG_NAME_NOT_VALID (-2) if it is neither.
     */
    public static function validateArgumentName(string $name): int
    {
        if (!CharUtils::isIdentifier($name)) {
            return self::ARG_NAME_NOT_VALID;
        }
        return NumericParser::parseArgNumberFromString($name, 0, mb_strlen($name));
    }

    /**
     * Appends a string segment to the output, reducing consecutive apostrophes.
     * Doubled apostrophes (escaped single quotes) are treated as a single apostrophe.
     *
     * @param string $s The input string containing apostrophes to process.
     * @param int $start The starting index of the segment within the input string.
     * @param int $limit The ending index (exclusive) of the segment within the input string.
     * @param string &$out A reference to the output string where the processed result will be appended.
     * @return void
     */
    public static function appendReducedApostrophes(string $s, int $start, int $limit, string &$out): void
    {
        CharUtils::appendReducedApostrophes($s, $start, $limit, $out);
    }

    // ──────────────────────────────────────────────
    // Iterator interface implementation
    // ──────────────────────────────────────────────

    /**
     * Returns the current Part in the iteration.
     *
     * @return Part The current Part.
     */
    public function current(): Part
    {
        return $this->ctx->parts[$this->position];
    }

    /**
     * Returns the key of the current Part in the iteration.
     *
     * @return int The key of the current Part.
     */
    public function key(): int
    {
        return $this->position;
    }

    /**
     * Moves the iterator to the next Part.
     *
     * @return void
     */
    public function next(): void
    {
        ++$this->position;
    }

    /**
     * Rewinds the iterator to the first Part.
     *
     * @return void
     */
    public function rewind(): void
    {
        $this->position = 0;
    }

    /**
     * Checks if the current position is valid in the iteration.
     *
     * @return bool True if the current position is valid, false otherwise.
     */
    public function valid(): bool
    {
        return isset($this->ctx->parts[$this->position]);
    }
}

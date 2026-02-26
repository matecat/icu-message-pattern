<?php

declare(strict_types=1);

/**
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 26/02/26
 *
 */

namespace Matecat\ICU\Parsing;

use Matecat\ICU\Exceptions\BadChoicePatternSyntaxException;
use Matecat\ICU\Exceptions\BadPluralSelectPatternSyntaxException;
use Matecat\ICU\Exceptions\InvalidArgumentException;
use Matecat\ICU\Exceptions\InvalidNumericValueException;
use Matecat\ICU\Exceptions\OutOfBoundsException;
use Matecat\ICU\Exceptions\UnmatchedBracesException;
use Matecat\ICU\MessagePattern;
use Matecat\ICU\Parsing\Style\ChoiceStyleParser;
use Matecat\ICU\Parsing\Style\NumericParser;
use Matecat\ICU\Parsing\Style\PluralSelectParser;
use Matecat\ICU\Parsing\Utils\CharUtils;
use Matecat\ICU\Tokens\ArgType;
use Matecat\ICU\Tokens\Part;
use Matecat\ICU\Tokens\TokenType;

/**
 * Internal parser for ICU MessageFormat pattern strings.
 *
 * Operates on a shared ParseContext instance. The public facade
 * (MessagePattern) delegates all the parsing to this class.
 *
 * @internal This class is not part of the public API and may change between versions.
 */
final class MessagePatternParser
{
    private ParseContext $ctx;
    private NumericParser $numericParser;
    private ChoiceStyleParser $choiceStyleParser;
    private PluralSelectParser $pluralSelectParser;

    /**
     * Initialises the parser with the given context and creates sub-parsers
     * for choice, plural, and select argument styles.
     */
    public function __construct(ParseContext $ctx)
    {
        $this->ctx = $ctx;
        $this->numericParser = new NumericParser($ctx);

        // Sub-parsers need a callback to parseMessage for recursive nesting
        // Create a first-class callable (Closure) referencing $this->parseMessage(),
        // so it can be passed to sub-parsers (ChoiceStyleParser, PluralSelectParser)
        // that need to call back into parseMessage() for recursively nested patterns.
        $parseMessageCb = $this->parseMessage(...);
        $this->choiceStyleParser = new ChoiceStyleParser($ctx, $this->numericParser, $parseMessageCb);
        $this->pluralSelectParser = new PluralSelectParser($ctx, $this->numericParser, $parseMessageCb);
    }

    // ──────────────────────────────────────────────
    // Entry points
    // ──────────────────────────────────────────────

    /**
     * Parses a full MessageFormat pattern.
     *
     * @throws InvalidArgumentException
     * @throws UnmatchedBracesException
     * @throws BadPluralSelectPatternSyntaxException
     * @throws BadChoicePatternSyntaxException
     * @throws InvalidNumericValueException
     * @throws OutOfBoundsException
     */
    public function parse(string $pattern): void
    {
        $this->ctx->preParse($pattern);
        $this->parseMessage(0, 0, 0, ArgType::NONE);
    }

    /**
     * Parses a ChoiceFormat pattern.
     *
     * @throws InvalidArgumentException
     * @throws UnmatchedBracesException
     * @throws BadChoicePatternSyntaxException
     * @throws InvalidNumericValueException
     * @throws OutOfBoundsException
     */
    public function parseChoiceStyle(string $pattern): void
    {
        $this->ctx->preParse($pattern);
        $this->choiceStyleParser->parse(0, 0, false);
    }

    /**
     * Parses a PluralFormat or SelectFormat pattern.
     *
     * @throws InvalidArgumentException
     * @throws UnmatchedBracesException
     * @throws BadPluralSelectPatternSyntaxException
     * @throws InvalidNumericValueException
     * @throws OutOfBoundsException
     */
    public function parsePluralOrSelect(ArgType $argType, string $pattern): void
    {
        $this->ctx->preParse($pattern);
        $this->pluralSelectParser->parse($argType, 0, 0, false);
    }

    // ──────────────────────────────────────────────
    // Core parsing methods
    // ──────────────────────────────────────────────

    /**
     * Parses a message fragment.
     *
     * @noinspection PhpSameParameterValueInspection Parameters vary when called via Closure from sub-parsers
     * @noinspection PhpReturnValueOfMethodIsNeverUsedInspection Sub-parsers use return value via Closure
     *
     * @throws InvalidArgumentException
     * @throws UnmatchedBracesException
     * @throws BadPluralSelectPatternSyntaxException
     * @throws BadChoicePatternSyntaxException
     * @throws InvalidNumericValueException
     * @throws OutOfBoundsException
     */
    private function parseMessage(int $index, int $msgStartLength, int $nestingLevel, ArgType $parentType): int
    {
        // Guard against excessive nesting that would overflow the call stack.
        // Each nesting level adds ~5 stack frames (parseMessage → parseArg → parseArgTypeAndStyle
        // → parseArgStyleBody → parsePluralOrSelectStyle → parseMessage). With Xdebug's default
        // max_nesting_level of 512 and ~25 frames of overhead (PHPUnit + entry), the absolute
        // max is ~97. We use 80 to leave a safe margin for varying environments.
        if ($nestingLevel > 80) {
            throw new OutOfBoundsException("Nesting level exceeds maximum value");
        }

        $msgStart = count($this->ctx->parts);
        $this->ctx->addPart(TokenType::MSG_START, $index, $msgStartLength, $nestingLevel);
        $index += $msgStartLength;

        while ($index < $this->ctx->msgLength) {
            $c = CharUtils::charAt($this->ctx->chars, $index++);

            if ($c === "'") {
                $index = $this->handleApostrophe($index, $parentType);
            } elseif ($parentType->hasPluralStyle() && $c === '#') {
                $this->ctx->addPart(TokenType::REPLACE_NUMBER, $index - 1, 1, 0);
            } elseif ($c === '{') {
                $index = $this->parseArg($index - 1, $nestingLevel);
            } elseif (($nestingLevel > 0 && $c === '}') || ($parentType === ArgType::CHOICE && $c === '|')) {
                return $this->closeMessageFragment($msgStart, $index, $nestingLevel, $parentType, $c);
            }
        }

        if ($nestingLevel > 0 && !$this->inTopLevelChoiceMessage($nestingLevel, $parentType)) {
            throw new UnmatchedBracesException(CharUtils::errorContext($this->ctx->msg));
        }

        $this->ctx->addLimitPart($msgStart, TokenType::MSG_LIMIT, $index, 0, $nestingLevel);
        return $index;
    }

    /**
     * Handles apostrophe quoting rules during message parsing.
     *
     * @return int The updated index after processing the apostrophe.
     */
    private function handleApostrophe(int $index, ArgType $parentType): int
    {
        if ($index === $this->ctx->msgLength) {
            $this->ctx->addPart(TokenType::INSERT_CHAR, $index, 0, 0x27);
            $this->ctx->needsAutoQuoting = true;
            return $index;
        }

        $c = CharUtils::charAt($this->ctx->chars, $index);
        if ($c === "'") {
            $this->ctx->addPart(TokenType::SKIP_SYNTAX, $index++, 1, 0);
            return $index;
        }

        if ($this->isQuoteTrigger($c, $parentType)) {
            return $this->parseQuotedLiteral($index);
        }

        $this->ctx->addPart(TokenType::INSERT_CHAR, $index, 0, 0x27);
        $this->ctx->needsAutoQuoting = true;
        return $index;
    }

    /**
     * Determines if the character following an apostrophe triggers the quoted literal mode.
     */
    private function isQuoteTrigger(string $c, ArgType $parentType): bool
    {
        return $this->ctx->aposMode === MessagePattern::APOSTROPHE_DOUBLE_REQUIRED
            || $c === '{' || $c === '}'
            || ($parentType === ArgType::CHOICE && $c === '|')
            || ($parentType->hasPluralStyle() && $c === '#');
    }

    /**
     * Parses a quoted literal section starting from the opening apostrophe.
     *
     * @return int The updated index after the quoted literal.
     */
    private function parseQuotedLiteral(int $index): int
    {
        $this->ctx->addPart(TokenType::SKIP_SYNTAX, $index - 1, 1, 0);
        while (true) {
            $index = CharUtils::indexOf($this->ctx->msg, "'", $index + 1);
            if ($index !== false) {
                if (($index + 1) < $this->ctx->msgLength && CharUtils::charAt($this->ctx->chars, $index + 1) === "'") {
                    $this->ctx->addPart(TokenType::SKIP_SYNTAX, ++$index, 1, 0);
                } else {
                    $this->ctx->addPart(TokenType::SKIP_SYNTAX, $index++, 1, 0);
                    break;
                }
            } else {
                $index = $this->ctx->msgLength;
                $this->ctx->addPart(TokenType::INSERT_CHAR, $index, 0, 0x27);
                $this->ctx->needsAutoQuoting = true;
                break;
            }
        }
        return $index;
    }

    /**
     * Closes a message fragment at '}' or choice separator '|'.
     *
     * @return int The index to resume parsing from.
     */
    private function closeMessageFragment(
        int $msgStart,
        int $index,
        int $nestingLevel,
        ArgType $parentType,
        string $c
    ): int {
        $limitLength = ($parentType === ArgType::CHOICE && $c === '}') ? 0 : 1;
        $this->ctx->addLimitPart($msgStart, TokenType::MSG_LIMIT, $index - 1, $limitLength, $nestingLevel);
        if ($parentType === ArgType::CHOICE) {
            return $index - 1;
        }
        return $index;
    }

    // ──────────────────────────────────────────────
    // Argument parsing
    // ──────────────────────────────────────────────

    /**
     * Parses an argument placeholder within a message pattern.
     *
     * @throws InvalidArgumentException
     * @throws UnmatchedBracesException
     * @throws BadPluralSelectPatternSyntaxException
     * @throws BadChoicePatternSyntaxException
     * @throws InvalidNumericValueException
     * @throws OutOfBoundsException
     */
    private function parseArg(int $index, int $nestingLevel): int
    {
        $argStart = count($this->ctx->parts);
        $argType = ArgType::NONE;
        $this->ctx->addPart(TokenType::ARG_START, $index, 1, $this->argTypeOrdinal($argType));

        $nameIndex = $index = CharUtils::skipWhiteSpace($this->ctx->msg, $index + 1);
        if ($index === $this->ctx->msgLength) {
            throw new UnmatchedBracesException(CharUtils::errorContext($this->ctx->msg));
        }

        $index = CharUtils::skipIdentifier($this->ctx->msg, $index);
        $number = $this->numericParser->parseArgNumber($nameIndex, $index);
        $length = $index - $nameIndex;

        $this->recordArgNameOrNumber($number, $nameIndex, $length);

        $index = CharUtils::skipWhiteSpace($this->ctx->msg, $index);
        if ($index === $this->ctx->msgLength) {
            throw new UnmatchedBracesException(CharUtils::errorContext($this->ctx->msg));
        }

        $c = CharUtils::charAt($this->ctx->chars, $index);
        if ($c !== '}') {
            [$argType, $index] = $this->parseArgTypeAndStyle($argStart, $nameIndex, $index, $nestingLevel);
        }

        $this->ctx->addLimitPart($argStart, TokenType::ARG_LIMIT, $index, 1, $this->argTypeOrdinal($argType));
        return $index + 1;
    }

    /**
     * Records an ARG_NUMBER or ARG_NAME part, validating the parsed argument number.
     *
     * @throws OutOfBoundsException
     * @throws InvalidArgumentException
     */
    private function recordArgNameOrNumber(int $number, int $nameIndex, int $length): void
    {
        if ($number >= 0) {
            $this->ctx->hasArgNumbers = true;
            $this->ctx->addPart(TokenType::ARG_NUMBER, $nameIndex, $length, $number);
            return;
        }

        if ($number === MessagePattern::ARG_NAME_NOT_NUMBER) {
            if ($length > Part::MAX_LENGTH) {
                throw new OutOfBoundsException(
                    "Argument name too long: " . CharUtils::errorContext($this->ctx->msg, $nameIndex)
                );
            }
            $this->ctx->hasArgNames = true;
            $this->ctx->addPart(TokenType::ARG_NAME, $nameIndex, $length, 0);
            return;
        }

        if ($number === MessagePattern::ARG_VALUE_OVERFLOW) {
            throw new OutOfBoundsException(
                "Argument number too large: " . CharUtils::errorContext($this->ctx->msg, $nameIndex)
            );
        }

        throw $this->badArgumentSyntax($nameIndex);
    }

    /**
     * Parses the argument type and style after the comma.
     *
     * @return array{0: ArgType, 1: int} The resolved ArgType and the updated index.
     * @throws InvalidArgumentException
     * @throws UnmatchedBracesException
     * @throws BadPluralSelectPatternSyntaxException
     * @throws BadChoicePatternSyntaxException
     * @throws InvalidNumericValueException
     * @throws OutOfBoundsException
     */
    private function parseArgTypeAndStyle(int $argStart, int $nameIndex, int $index, int $nestingLevel): array
    {
        $c = CharUtils::charAt($this->ctx->chars, $index);
        if ($c !== ',') {
            throw $this->badArgumentSyntax($nameIndex);
        }

        $typeIndex = $index = CharUtils::skipWhiteSpace($this->ctx->msg, $index + 1);
        while ($index < $this->ctx->msgLength && CharUtils::isArgTypeChar(
                CharUtils::charAt($this->ctx->chars, $index)
            )) {
            $index++;
        }
        $length = $index - $typeIndex;

        $index = CharUtils::skipWhiteSpace($this->ctx->msg, $index);
        if ($index === $this->ctx->msgLength) {
            throw new UnmatchedBracesException(CharUtils::errorContext($this->ctx->msg));
        }
        $c = CharUtils::charAt($this->ctx->chars, $index);
        if ($length === 0 || ($c !== ',' && $c !== '}')) {
            throw $this->badArgumentSyntax($nameIndex);
        }
        if ($length > Part::MAX_LENGTH) {
            throw new OutOfBoundsException(
                "Argument type name too long: " . CharUtils::errorContext($this->ctx->msg, $nameIndex)
            );
        }

        $argType = $this->resolveArgType($typeIndex, $length);

        $this->ctx->replacePartValue($argStart, $this->argTypeOrdinal($argType));
        if ($argType === ArgType::SIMPLE) {
            $this->ctx->addPart(TokenType::ARG_TYPE, $typeIndex, $length, 0);
        }

        $index = $this->parseArgStyleBody($argType, $nameIndex, $index, $nestingLevel, $c);

        return [$argType, $index];
    }

    /**
     * Resolves the ArgType from the type token at the given index.
     */
    private function resolveArgType(int $typeIndex, int $length): ArgType
    {
        $msg = $this->ctx->msg;
        $chars = $this->ctx->chars;
        $msgLen = $this->ctx->msgLength;

        if ($length === 6) {
            return match (true) {
                CharUtils::isChoice($msg, $chars, $msgLen, $typeIndex) => ArgType::CHOICE,
                CharUtils::isPlural($msg, $chars, $msgLen, $typeIndex) => ArgType::PLURAL,
                CharUtils::isSelect($msg, $chars, $msgLen, $typeIndex) => ArgType::SELECT,
                default => ArgType::SIMPLE,
            };
        }

        if (
            $length === 13
            && CharUtils::isSelect($msg, $chars, $msgLen, $typeIndex)
            && CharUtils::isOrdinal($msg, $chars, $msgLen, $typeIndex + 6)
        ) {
            return ArgType::SELECTORDINAL;
        }

        return ArgType::SIMPLE;
    }

    /**
     * Parses the style body of an argument (simple, choice, plural, or select).
     *
     * @throws InvalidArgumentException
     * @throws UnmatchedBracesException
     * @throws BadPluralSelectPatternSyntaxException
     * @throws BadChoicePatternSyntaxException
     * @throws InvalidNumericValueException
     * @throws OutOfBoundsException
     */
    private function parseArgStyleBody(ArgType $argType, int $nameIndex, int $index, int $nestingLevel, string $c): int
    {
        if ($c === '}') {
            if ($argType !== ArgType::SIMPLE) {
                throw new InvalidArgumentException(
                    "No style field for complex argument: " . CharUtils::errorContext($this->ctx->msg, $nameIndex)
                );
            }
            return $index;
        }

        $index++;
        if ($argType === ArgType::SIMPLE) {
            return $this->parseSimpleStyle($index);
        }
        if ($argType === ArgType::CHOICE) {
            return $this->choiceStyleParser->parse($index, $nestingLevel, $this->inMessageFormatPattern($nestingLevel));
        }
        return $this->pluralSelectParser->parse(
            $argType,
            $index,
            $nestingLevel,
            $this->inMessageFormatPattern($nestingLevel)
        );
    }

    // ──────────────────────────────────────────────
    // Simple style parsing
    // ──────────────────────────────────────────────

    /**
     * Parses a simple style format and records it as an argument style part.
     *
     * @throws InvalidArgumentException
     * @throws UnmatchedBracesException
     * @throws OutOfBoundsException
     */
    private function parseSimpleStyle(int $index): int
    {
        $start = $index;
        $nestedBraces = 0;
        $length = $this->ctx->msgLength;

        while ($index < $length) {
            $c = CharUtils::charAt($this->ctx->chars, $index++);
            if ($c === "'") {
                $index = $this->skipQuotedLiteralInStyle($index, $length, $start);
            } elseif ($c === '{') {
                $nestedBraces++;
            } elseif ($c === '}') {
                if ($nestedBraces > 0) {
                    $nestedBraces--;
                } else {
                    return $this->recordSimpleStyle($start, --$index);
                }
            }
        }
        throw new UnmatchedBracesException(CharUtils::errorContext($this->ctx->msg));
    }

    /**
     * Skips a quoted literal inside a simple style, returning the updated index.
     *
     * @throws InvalidArgumentException
     */
    private function skipQuotedLiteralInStyle(int $index, int $length, int $start): int
    {
        for ($i = $index; $i < $length; $i++) {
            if ($this->ctx->chars[$i] === "'") {
                return $i + 1;
            }
        }
        throw new InvalidArgumentException(
            "Quoted literal argument style text reaches to the end of the message: " . CharUtils::errorContext(
                $this->ctx->msg,
                $start
            )
        );
    }

    /**
     * Records the ARG_STYLE part for a simple style.
     *
     * @throws OutOfBoundsException
     */
    private function recordSimpleStyle(int $start, int $index): int
    {
        $len = $index - $start;
        if ($len > Part::MAX_LENGTH) {
            throw new OutOfBoundsException(
                "Argument style text too long: " . CharUtils::errorContext($this->ctx->msg, $start)
            );
        }
        $this->ctx->addPart(TokenType::ARG_STYLE, $start, $len, 0);
        return $index;
    }

    // ──────────────────────────────────────────────
    // Private helpers
    // ──────────────────────────────────────────────

    /**
     * Determines the ordinal position of the given ArgType.
     */
    private function argTypeOrdinal(ArgType $argType): int
    {
        foreach (ArgType::cases() as $i => $case) {
            if ($case === $argType) {
                return $i;
            }
        }
        return 0; // @codeCoverageIgnore
    }

    /**
     * Checks whether the parser is currently inside a MessageFormat pattern
     * (either nested or started with a MSG_START part).
     */
    private function inMessageFormatPattern(int $nestingLevel): bool
    {
        return $nestingLevel > 0
            || (isset($this->ctx->parts[0]) && $this->ctx->parts[0]->getType() === TokenType::MSG_START);
    }

    /**
     * Checks whether the parser is at the top-level message of a ChoiceFormat
     * pattern (nesting level 1, choice parent, no MSG_START).
     */
    private function inTopLevelChoiceMessage(int $nestingLevel, ArgType $parentType): bool
    {
        return $nestingLevel === 1
            && $parentType === ArgType::CHOICE
            && (isset($this->ctx->parts[0]) ? $this->ctx->parts[0]->getType() : null) !== TokenType::MSG_START;
    }

    /**
     * Creates an InvalidArgumentException for bad argument syntax at the given index.
     */
    private function badArgumentSyntax(int $nameIndex): InvalidArgumentException
    {
        return new InvalidArgumentException(
            "Bad argument syntax: " . CharUtils::errorContext($this->ctx->msg, $nameIndex)
        );
    }
}

<?php

declare(strict_types=1);

/**
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 26/02/26
 *
 */

namespace Matecat\ICU\Parsing\Style;

use Closure;
use Matecat\ICU\Exceptions\BadPluralSelectPatternSyntaxException;
use Matecat\ICU\Exceptions\InvalidArgumentException;
use Matecat\ICU\Exceptions\InvalidNumericValueException;
use Matecat\ICU\Exceptions\OutOfBoundsException;
use Matecat\ICU\Exceptions\UnmatchedBracesException;
use Matecat\ICU\Parsing\ParseContext;
use Matecat\ICU\Parsing\Utils\CharUtils;
use Matecat\ICU\Tokens\ArgType;
use Matecat\ICU\Tokens\Part;
use Matecat\ICU\Tokens\TokenType;

/**
 * Handles plural and select style parsing for the ICU MessagePattern parser.
 *
 * @internal This class is not part of the public API and may change between versions.
 */
final class PluralSelectParser
{
    private ParseContext $ctx;
    private NumericParser $numericParser;

    /**
     * Callback to parseMessage on the main parser.
     * @var Closure(int, int, int, ArgType): int
     */
    private Closure $parseMessage;

    public function __construct(ParseContext $ctx, NumericParser $numericParser, Closure $parseMessage)
    {
        $this->ctx = $ctx;
        $this->numericParser = $numericParser;
        $this->parseMessage = $parseMessage;
    }

    /**
     * Parses a plural or select style pattern.
     *
     * @throws InvalidArgumentException
     * @throws UnmatchedBracesException
     * @throws BadPluralSelectPatternSyntaxException
     * @throws InvalidNumericValueException
     * @throws OutOfBoundsException
     */
    public function parse(ArgType $argType, int $index, int $nestingLevel, bool $inMessageFormatPattern): int
    {
        $start = $index;
        $isEmpty = true;
        $hasOther = false;

        while (true) {
            $index = CharUtils::skipWhiteSpace($this->ctx->msg, $index);
            $eos = $index === $this->ctx->msgLength;

            if ($eos || CharUtils::charAt($this->ctx->chars, $index) === '}') {
                $this->validateEnd($eos, $hasOther, $argType, $start, $inMessageFormatPattern);
                return $index;
            }

            $selectorIndex = $index;

            if ($argType->hasPluralStyle() && CharUtils::charAt($this->ctx->chars, $selectorIndex) === '=') {
                $index = $this->parseExplicitValueSelector($selectorIndex, $index, $start, $argType);
            } else {
                [$index, $skipMessage] = $this->parseKeywordSelector($argType, $index, $selectorIndex, $start, $isEmpty);
                if ($skipMessage) {
                    $isEmpty = false;
                    continue;
                }
                $hasOther = $hasOther || $this->isOtherSelector($selectorIndex, $index - $selectorIndex);
            }

            $this->requireMessageFragment($argType, $index, $selectorIndex);
            $index = ($this->parseMessage)($index, 1, $nestingLevel + 1, $argType);
            $isEmpty = false;
        }
    }

    /**
     * Validates the end of a plural/select style (EOS or '}').
     *
     * @throws UnmatchedBracesException
     * @throws BadPluralSelectPatternSyntaxException
     */
    private function validateEnd(bool $eos, bool $hasOther, ArgType $argType, int $start, bool $inMessageFormatPattern): void
    {
        if ($eos) {
            $this->checkUnmatchedBraces();
        }

        if ($eos === $inMessageFormatPattern) {
            throw new BadPluralSelectPatternSyntaxException($argType->name, CharUtils::errorContext($this->ctx->msg, $start));
        }
        if (!$hasOther) {
            throw new BadPluralSelectPatternSyntaxException($argType->name, CharUtils::errorContext($this->ctx->msg, $start));
        }
    }

    /**
     * Checks for unmatched braces by counting MSG_START/MSG_LIMIT parts.
     *
     * @throws UnmatchedBracesException
     */
    private function checkUnmatchedBraces(): void
    {
        $curlyBraces = 0;
        foreach ($this->ctx->parts as $part) {
            match ($part->getType()) {
                TokenType::MSG_START => $curlyBraces++,
                TokenType::MSG_LIMIT => $curlyBraces--,
                default => null,
            };
        }
        if ($curlyBraces > 0) {
            throw new UnmatchedBracesException(CharUtils::errorContext($this->ctx->msg));
        }
    }

    /**
     * Parses an explicit-value selector (e.g., "=2") in a plural pattern.
     *
     * @throws BadPluralSelectPatternSyntaxException
     * @throws OutOfBoundsException
     * @throws InvalidNumericValueException
     */
    private function parseExplicitValueSelector(int $selectorIndex, int $index, int $start, ArgType $argType): int
    {
        $index = CharUtils::skipDouble($this->ctx->msg, $this->ctx->chars, $this->ctx->msgLength, $index + 1);
        $len = $index - $selectorIndex;
        if ($len === 1) {
            throw new BadPluralSelectPatternSyntaxException($argType->name, CharUtils::errorContext($this->ctx->msg, $start));
        }
        if ($len > Part::MAX_LENGTH) {
            throw new OutOfBoundsException("Argument selector too long: " . CharUtils::errorContext($this->ctx->msg, $selectorIndex));
        }
        $this->ctx->addPart(TokenType::ARG_SELECTOR, $selectorIndex, $len, 0);
        $this->numericParser->parseDoubleValue($selectorIndex + 1, $index, false);
        return $index;
    }

    /**
     * Checks if the current identifier is the plural "offset:" keyword.
     */
    private function isPluralOffset(ArgType $argType, int $len, int $index, int $selectorIndex): bool
    {
        return $argType->hasPluralStyle()
            && $len === 6
            && $index < $this->ctx->msgLength
            && CharUtils::startsWithAt($this->ctx->msg, $this->ctx->chars, $this->ctx->msgLength, 'offset:', $selectorIndex);
    }

    /**
     * Parses the "offset:" value in a plural pattern.
     *
     * @throws InvalidArgumentException
     * @throws InvalidNumericValueException
     * @throws OutOfBoundsException
     */
    private function parsePluralOffset(int $start, int $index, bool $isEmpty): int
    {
        if (!$isEmpty) {
            throw new InvalidArgumentException(
                "Plural argument 'offset:' (if present) must precede key-message pairs: " . CharUtils::errorContext($this->ctx->msg, $start)
            );
        }
        $valueIndex = CharUtils::skipWhiteSpace($this->ctx->msg, $index + 1);
        $index = CharUtils::skipDouble($this->ctx->msg, $this->ctx->chars, $this->ctx->msgLength, $valueIndex);
        if ($index === $valueIndex) {
            throw new InvalidArgumentException(
                "Missing value for plural 'offset:' " . CharUtils::errorContext($this->ctx->msg, $start)
            );
        }
        if (($index - $valueIndex) > Part::MAX_LENGTH) {
            throw new OutOfBoundsException("Plural offset value too long: " . CharUtils::errorContext($this->ctx->msg, $valueIndex));
        }
        $this->numericParser->parseDoubleValue($valueIndex, $index, false);
        return $index;
    }

    /**
     * Parses a keyword-based selector (non-explicit-value).
     * Returns the updated index and whether the loop should skip the message fragment
     * (true when the selector was "offset:").
     *
     * @return array{0: int, 1: bool}
     * @throws BadPluralSelectPatternSyntaxException
     * @throws OutOfBoundsException
     * @throws InvalidArgumentException
     * @throws InvalidNumericValueException
     */
    private function parseKeywordSelector(ArgType $argType, int $index, int $selectorIndex, int $start, bool $isEmpty): array
    {
        $index = CharUtils::skipIdentifier($this->ctx->msg, $index);
        $len = $index - $selectorIndex;

        if ($len === 0) {
            throw new BadPluralSelectPatternSyntaxException($argType->name, CharUtils::errorContext($this->ctx->msg, $start));
        }

        // Handle plural "offset:" — must be first, consumes no message fragment
        if ($this->isPluralOffset($argType, $len, $index, $selectorIndex)) {
            $index = $this->parsePluralOffset($start, $index, $isEmpty);
            return [$index, true];
        }

        if ($len > Part::MAX_LENGTH) {
            throw new OutOfBoundsException("Argument selector too long: " . CharUtils::errorContext($this->ctx->msg, $selectorIndex));
        }
        $this->ctx->addPart(TokenType::ARG_SELECTOR, $selectorIndex, $len, 0);

        return [$index, false];
    }

    /**
     * Checks whether the selector at the given position is "other".
     */
    private function isOtherSelector(int $selectorIndex, int $len): bool
    {
        return mb_substr($this->ctx->msg, $selectorIndex, $len) === 'other';
    }

    /**
     * Ensures that a "{message}" fragment follows the current selector.
     * Advances past whitespace and throws if '{' is not found.
     *
     * @throws InvalidArgumentException
     */
    private function requireMessageFragment(ArgType $argType, int &$index, int $selectorIndex): void
    {
        $index = CharUtils::skipWhiteSpace($this->ctx->msg, $index);
        if ($index === $this->ctx->msgLength || CharUtils::charAt($this->ctx->chars, $index) !== '{') {
            throw new InvalidArgumentException(
                "No message fragment after " . strtolower($argType->name) . " selector: " . CharUtils::errorContext($this->ctx->msg, $selectorIndex)
            );
        }
    }
}


<?php

declare(strict_types=1);

/**
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 26/02/26
 *
 */

namespace Matecat\ICU\Parsing\Style;

use Matecat\ICU\Exceptions\InvalidNumericValueException;
use Matecat\ICU\Exceptions\OutOfBoundsException;
use Matecat\ICU\MessagePattern;
use Matecat\ICU\Parsing\ParseContext;
use Matecat\ICU\Parsing\Utils\CharUtils;
use Matecat\ICU\Tokens\Part;
use Matecat\ICU\Tokens\TokenType;

/**
 * Handles numeric value parsing for the ICU MessagePattern parser.
 *
 * Parses integer and double values, including signed values and infinity,
 * from the pattern string and records them as Part tokens in the ParseContext.
 *
 * @internal This class is not part of the public API and may change between versions.
 */
final class NumericParser
{
    private ParseContext $ctx;

    public function __construct(ParseContext $ctx)
    {
        $this->ctx = $ctx;
    }

    /**
     * Validates and parses an argument number from this pattern.
     */
    public function parseArgNumber(int $start, int $limit): int
    {
        return self::parseArgNumberFromString($this->ctx->msg, $start, $limit);
    }

    /**
     * Parses a numeric value (integer or double) within the given range.
     *
     * @throws InvalidNumericValueException
     * @throws OutOfBoundsException
     */
    public function parseDoubleValue(int $start, int $limit, bool $allowInfinity): void
    {
        if ($start >= $limit) {
            throw new InvalidNumericValueException(); // @codeCoverageIgnore
        }

        $index = $start;
        $isNegative = 0;
        $c = CharUtils::charAt($this->ctx->chars, $index++);

        [$c, $index, $isNegative] = $this->parseLeadingSign($c, $index, $limit, $isNegative);

        if (CharUtils::startsWithAt($this->ctx->msg, $this->ctx->chars, $this->ctx->msgLength, "∞", $index - 1)) {
            $this->parseInfinity($allowInfinity, $index, $limit, $isNegative, $start);
            return;
        }

        $this->parseIntegerOrDouble($c, $index, $start, $limit, $isNegative);
    }

    /**
     * Parses a numeric argument from a substring and returns its integer value.
     *
     * @param string $s The string containing the numeric argument.
     * @param int $start The starting index.
     * @param int $limit The ending index (exclusive).
     * @return int >=0 if a valid number, ARG_NAME_NOT_NUMBER, ARG_NAME_NOT_VALID, or ARG_VALUE_OVERFLOW.
     */
    public static function parseArgNumberFromString(string $s, int $start, int $limit): int
    {
        if ($start >= $limit) {
            return MessagePattern::ARG_NAME_NOT_VALID; // @codeCoverageIgnore
        }

        $number = self::parseDigits($s, $start, $limit);

        // A leading '0' is only valid when it is the sole character
        if ($s[$start] === '0' && $limit - $start > 1) {
            $number = MessagePattern::ARG_NAME_NOT_VALID;
        }

        return $number;
    }

    /**
     * Parses a sequence of ASCII digits into an integer.
     *
     * @return int The parsed number (>=0), ARG_NAME_NOT_NUMBER, or ARG_VALUE_OVERFLOW.
     */
    private static function parseDigits(string $s, int $start, int $limit): int
    {
        $number = 0;
        for ($i = $start; $i < $limit; $i++) {
            $ord = mb_ord($s[$i]);
            if ($ord < 0x30 || $ord > 0x39) {
                return MessagePattern::ARG_NAME_NOT_NUMBER;
            }
            if ($number >= intdiv(Part::MAX_VALUE, 10) && $i > $start) {
                return MessagePattern::ARG_VALUE_OVERFLOW;
            }
            $number = $number * 10 + ($ord - 0x30);
        }

        return $number;
    }

    /**
     * Parses an optional leading sign (+/-) for a numeric value.
     *
     * @return array{0: string, 1: int, 2: int} The current char, updated index, and isNegative flag.
     * @throws InvalidNumericValueException
     */
    private function parseLeadingSign(string $c, int $index, int $limit, int $isNegative): array
    {
        if ($c === '-') {
            $isNegative = 1;
            if ($index === $limit) {
                throw new InvalidNumericValueException();
            }
            return [CharUtils::charAt($this->ctx->chars, $index++), $index, $isNegative];
        }

        if ($c === '+') {
            if ($index === $limit) {
                throw new InvalidNumericValueException();
            }
            return [CharUtils::charAt($this->ctx->chars, $index++), $index, $isNegative];
        }

        return [$c, $index, $isNegative];
    }

    /**
     * Handles the infinity symbol in a numeric value.
     *
     * @throws InvalidNumericValueException
     * @throws OutOfBoundsException
     */
    private function parseInfinity(bool $allowInfinity, int $index, int $limit, int $isNegative, int $start): void
    {
        if ($allowInfinity && $index === $limit) {
            $value = $isNegative ? -INF : INF;
            $this->ctx->addArgDoublePart($value, $start, $limit - $start);
            return;
        }
        throw new InvalidNumericValueException();
    }

    /**
     * Parses the integer/double body of a numeric value.
     *
     * @throws OutOfBoundsException
     */
    private function parseIntegerOrDouble(string $c, int $index, int $start, int $limit, int $isNegative): void
    {
        $value = 0;
        $ord = ord($c);
        while ($ord >= 0x30 && $ord <= 0x39) {
            $value = $value * 10 + ($ord - 0x30);
            if ($value > (Part::MAX_VALUE + $isNegative)) {
                break;
            }
            if ($index === $limit) {
                $this->ctx->addPart(TokenType::ARG_INT, $start, $limit - $start, $isNegative ? -$value : $value);
                return;
            }
            $ord = ord(CharUtils::charAt($this->ctx->chars, $index++));
        }

        $length = $limit - $start;
        $numericValue = (float)implode(array_slice($this->ctx->chars, $start, $length));
        $this->ctx->addArgDoublePart($numericValue, $start, $limit - $start);
    }
}


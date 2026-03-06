<?php

declare(strict_types=1);

namespace Matecat\ICU\Plurals;

/**
 * Extracts and holds all CLDR plural rule operand values from a numeric input.
 *
 * CLDR plural rules use these operands to determine which plural category applies:
 *
 * | CLDR | Property                       | Meaning                                              | "1.20" | 5  | "0.1" |
 * |------|--------------------------------|------------------------------------------------------|--------|----|-------|
 * | n    | absoluteValue                  | Absolute value of the source number                  | 1.2    | 5  | 0.1   |
 * | i    | integerPart                    | Integer digits of the absolute value                 | 1      | 5  | 0     |
 * | v    | fractionDigitCount             | Number of visible fraction digits (with trailing 0s) | 2      | 0  | 1     |
 * | w    | significantFractionDigitCount  | Number of visible fraction digits (no trailing 0s)   | 1      | 0  | 1     |
 * | f    | fractionDigits                 | Visible fraction digits as integer (with trailing 0s)| 20     | 0  | 1     |
 * | t    | significantFractionDigits      | Visible fraction digits as integer (no trailing 0s)  | 2      | 0  | 1     |
 * | e    | compactExponent                | Compact decimal exponent (reserved, always 0)        | 0      | 0  | 0     |
 *
 * ## Usage
 *
 * ```php
 * $op = PluralOperands::from("1.20");
 * // $op->absoluteValue === 1.2, $op->integerPart === 1
 * // $op->fractionDigitCount === 2, $op->fractionDigits === 20
 * // $op->significantFractionDigits === 2
 *
 * $op = PluralOperands::from(5);
 * // $op->absoluteValue === 5.0, $op->integerPart === 5
 * // $op->fractionDigitCount === 0, $op->fractionDigits === 0
 *
 * $op = PluralOperands::from("0.100");
 * // $op->absoluteValue === 0.1, $op->integerPart === 0
 * // $op->fractionDigitCount === 3, $op->fractionDigits === 100
 * // $op->significantFractionDigits === 1
 * ```
 *
 * @see https://unicode.org/reports/tr35/tr35-numbers.html#Plural_Operand_Meanings
 */
final readonly class PluralOperands
{
    /**
     * @param float $absoluteValue                  CLDR "n" — Absolute value of the source number (with decimals).
     * @param int   $integerPart                    CLDR "i" — Integer digits of the absolute value.
     * @param int   $fractionDigitCount             CLDR "v" — Number of visible fraction digits (with trailing zeros).
     * @param int   $significantFractionDigitCount  CLDR "w" — Number of visible fraction digits (without trailing zeros).
     * @param int   $fractionDigits                 CLDR "f" — Visible fraction digits as an integer (with trailing zeros).
     * @param int   $significantFractionDigits      CLDR "t" — Visible fraction digits as an integer (without trailing zeros).
     * @param int   $compactExponent                CLDR "e" — Compact decimal exponent value (reserved for future use, always 0).
     */
    public function __construct(
        public float $absoluteValue,
        public int   $integerPart,
        public int   $fractionDigitCount,
        public int   $significantFractionDigitCount,
        public int   $fractionDigits,
        public int   $significantFractionDigits,
        public int   $compactExponent = 0,
    ) {
    }

    /**
     * Create operands from any numeric representation.
     *
     * String input preserves the visible fraction digit count:
     * - `"1.20"` → fractionDigitCount=2, fractionDigits=20 (trailing zeros preserved)
     * - `"1.2"`  → fractionDigitCount=1, fractionDigits=2
     *
     * Integer input is a fast path with all fraction operands = 0.
     *
     * The integer part is normalized (leading zeros stripped).
     * The fractional part is preserved verbatim (trailing zeros kept).
     *
     * @param string|int|float $number The numeric value.
     * @return self
     */
    public static function from(string|int|float $number): self
    {
        // Fast path for integers
        if (is_int($number)) {
            $abs = abs($number);

            return new self(
                absoluteValue: (float) $abs,
                integerPart: $abs,
                fractionDigitCount: 0,
                significantFractionDigitCount: 0,
                fractionDigits: 0,
                significantFractionDigits: 0,
            );
        }

        // Convert float to string to capture its decimal representation.
        // Note: floats may lose trailing zeros (1.20 becomes "1.2").
        $str = is_float($number) ? self::floatToString($number) : (string) $number;

        return self::parseString($str);
    }

    /**
     * Create operands from an integer value (fast path used by legacy int-only API).
     *
     * This avoids any string parsing and ensures fractionDigitCount=fractionDigits=0.
     *
     * @param int $n The integer value.
     * @return self
     */
    public static function fromInt(int $n): self
    {
        $abs = abs($n);

        return new self(
            absoluteValue: (float) $abs,
            integerPart: $abs,
            fractionDigitCount: 0,
            significantFractionDigitCount: 0,
            fractionDigits: 0,
            significantFractionDigits: 0,
        );
    }

    /**
     * Parse a string representation into operands.
     *
     * Normalizes the integer part (strips leading zeros, handles sign),
     * preserves the fractional part verbatim (trailing zeros kept for v/f).
     */
    private static function parseString(string $str): self
    {
        // Strip leading/trailing whitespace and the sign
        $str = ltrim($str);
        if ($str !== '' && ($str[0] === '-' || $str[0] === '+')) {
            $str = substr($str, 1);
        }

        // Strip leading zeros from the integer part but keep at least one digit
        // e.g., "007.50" → "7.50", "0.5" → "0.5", "000" → "0"
        if (str_contains($str, '.')) {
            [$intPart, $fracPart] = explode('.', $str, 2);
            $intPart = ltrim($intPart, '0') ?: '0';
        } else {
            $intPart = ltrim($str, '0') ?: '0';
            $fracPart = '';
        }

        $i = (int) $intPart;

        if ($fracPart === '') {
            // No fraction → behave like integer
            return new self(
                absoluteValue: (float) $i,
                integerPart: $i,
                fractionDigitCount: 0,
                significantFractionDigitCount: 0,
                fractionDigits: 0,
                significantFractionDigits: 0,
            );
        }

        // v = number of visible fraction digits (with trailing zeros)
        $v = strlen($fracPart);

        // f = visible fraction digits as integer (with trailing zeros)
        // e.g., "20" → 20, "100" → 100, "001" → 1
        $f = (int) $fracPart;

        // t = visible fraction digits without trailing zeros, as integer
        // e.g., "20" → "2" → 2, "100" → "1" → 1, "50" → "5" → 5
        $trimmed = rtrim($fracPart, '0');
        $t = $trimmed === '' ? 0 : (int) $trimmed;

        // w = number of fraction digits without trailing zeros
        $w = $trimmed === '' ? 0 : strlen($trimmed);

        // n = absolute value as float
        $n = (float) ($intPart . '.' . $fracPart);

        return new self(
            absoluteValue: $n,
            integerPart: $i,
            fractionDigitCount: $v,
            significantFractionDigitCount: $w,
            fractionDigits: $f,
            significantFractionDigits: $t,
        );
    }

    /**
     * Convert a float to string without scientific notation.
     *
     * PHP may render very small/large floats in scientific notation (e.g., 1.0E-5).
     * This method forces a decimal representation.
     */
    private static function floatToString(float $value): string
    {
        $value = abs($value);

        // Use serialize precision to get the full representation
        $str = (string) $value;

        // If PHP used scientific notation, convert via number_format
        if (stripos($str, 'e') !== false) {
            // Determine the number of decimal places needed
            $parts = explode('e', strtolower($str));
            $mantissa = $parts[0];
            $exponent = (int) $parts[1];

            $decPos = strpos($mantissa, '.');
            $mantissaDecimals = $decPos !== false ? strlen($mantissa) - $decPos - 1 : 0;

            $decimals = max(0, $mantissaDecimals - $exponent);
            $str = number_format($value, $decimals, '.', '');
        }

        return $str;
    }
}


<?php

declare(strict_types=1);

namespace Matecat\ICU\Tests;

use Matecat\ICU\Plurals\PluralOperands;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PluralOperandsTest extends TestCase
{
    /**
     * @param string|int|float $input
     * @param array{n: float, i: int, v: int, w: int, f: int, t: int} $expected
     */
    #[Test]
    #[DataProvider('operandProvider')]
    public function testOperandExtraction(string|int|float $input, array $expected): void
    {
        $op = PluralOperands::from($input);

        self::assertSame($expected['n'], $op->absoluteValue, "absoluteValue (n) for input '$input'");
        self::assertSame($expected['i'], $op->integerPart, "integerPart (i) for input '$input'");
        self::assertSame($expected['v'], $op->fractionDigitCount, "fractionDigitCount (v) for input '$input'");
        self::assertSame($expected['w'], $op->significantFractionDigitCount, "significantFractionDigitCount (w) for input '$input'");
        self::assertSame($expected['f'], $op->fractionDigits, "fractionDigits (f) for input '$input'");
        self::assertSame($expected['t'], $op->significantFractionDigits, "significantFractionDigits (t) for input '$input'");
        self::assertSame(0, $op->compactExponent, "compactExponent (e) for input '$input'");
    }

    /**
     * @return array<string, array{0: string|int|float, 1: array{n: float, i: int, v: int, w: int, f: int, t: int}}>
     */
    public static function operandProvider(): array
    {
        return [
            // === Integers ===
            'int 0' => [0, ['n' => 0.0, 'i' => 0, 'v' => 0, 'w' => 0, 'f' => 0, 't' => 0]],
            'int 1' => [1, ['n' => 1.0, 'i' => 1, 'v' => 0, 'w' => 0, 'f' => 0, 't' => 0]],
            'int 5' => [5, ['n' => 5.0, 'i' => 5, 'v' => 0, 'w' => 0, 'f' => 0, 't' => 0]],
            'int 100' => [100, ['n' => 100.0, 'i' => 100, 'v' => 0, 'w' => 0, 'f' => 0, 't' => 0]],
            'int negative' => [-3, ['n' => 3.0, 'i' => 3, 'v' => 0, 'w' => 0, 'f' => 0, 't' => 0]],

            // === String integers ===
            'str "1"' => ['1', ['n' => 1.0, 'i' => 1, 'v' => 0, 'w' => 0, 'f' => 0, 't' => 0]],
            'str "0"' => ['0', ['n' => 0.0, 'i' => 0, 'v' => 0, 'w' => 0, 'f' => 0, 't' => 0]],
            'str "007"' => ['007', ['n' => 7.0, 'i' => 7, 'v' => 0, 'w' => 0, 'f' => 0, 't' => 0]],
            'str "-5"' => ['-5', ['n' => 5.0, 'i' => 5, 'v' => 0, 'w' => 0, 'f' => 0, 't' => 0]],

            // === Simple decimals ===
            'str "1.0"' => ['1.0', ['n' => 1.0, 'i' => 1, 'v' => 1, 'w' => 0, 'f' => 0, 't' => 0]],
            'str "1.2"' => ['1.2', ['n' => 1.2, 'i' => 1, 'v' => 1, 'w' => 1, 'f' => 2, 't' => 2]],
            'str "0.1"' => ['0.1', ['n' => 0.1, 'i' => 0, 'v' => 1, 'w' => 1, 'f' => 1, 't' => 1]],
            'str "0.5"' => ['0.5', ['n' => 0.5, 'i' => 0, 'v' => 1, 'w' => 1, 'f' => 5, 't' => 5]],

            // === Trailing zeros ===
            'str "1.20"' => ['1.20', ['n' => 1.2, 'i' => 1, 'v' => 2, 'w' => 1, 'f' => 20, 't' => 2]],
            'str "1.00"' => ['1.00', ['n' => 1.0, 'i' => 1, 'v' => 2, 'w' => 0, 'f' => 0, 't' => 0]],
            'str "0.100"' => ['0.100', ['n' => 0.1, 'i' => 0, 'v' => 3, 'w' => 1, 'f' => 100, 't' => 1]],
            'str "2.000"' => ['2.000', ['n' => 2.0, 'i' => 2, 'v' => 3, 'w' => 0, 'f' => 0, 't' => 0]],

            // === Multiple decimal digits ===
            'str "1.23"' => ['1.23', ['n' => 1.23, 'i' => 1, 'v' => 2, 'w' => 2, 'f' => 23, 't' => 23]],
            'str "0.04"' => ['0.04', ['n' => 0.04, 'i' => 0, 'v' => 2, 'w' => 2, 'f' => 4, 't' => 4]],
            'str "3.50"' => ['3.50', ['n' => 3.5, 'i' => 3, 'v' => 2, 'w' => 1, 'f' => 50, 't' => 5]],

            // === Floats (lose trailing zeros) ===
            'float 1.5' => [1.5, ['n' => 1.5, 'i' => 1, 'v' => 1, 'w' => 1, 'f' => 5, 't' => 5]],
            'float 0.1' => [0.1, ['n' => 0.1, 'i' => 0, 'v' => 1, 'w' => 1, 'f' => 1, 't' => 1]],
            'float 2.0' => [2.0, ['n' => 2.0, 'i' => 2, 'v' => 0, 'w' => 0, 'f' => 0, 't' => 0]],

            // === Leading zeros in integer part ===
            'str "007.50"' => ['007.50', ['n' => 7.5, 'i' => 7, 'v' => 2, 'w' => 1, 'f' => 50, 't' => 5]],
            'str "00.5"' => ['00.5', ['n' => 0.5, 'i' => 0, 'v' => 1, 'w' => 1, 'f' => 5, 't' => 5]],

            // === Edge: zero with decimals ===
            'str "0.0"' => ['0.0', ['n' => 0.0, 'i' => 0, 'v' => 1, 'w' => 0, 'f' => 0, 't' => 0]],
            'str "0.00"' => ['0.00', ['n' => 0.0, 'i' => 0, 'v' => 2, 'w' => 0, 'f' => 0, 't' => 0]],

            // === CLDR sample values ===
            'CLDR 1.000' => ['1.000', ['n' => 1.0, 'i' => 1, 'v' => 3, 'w' => 0, 'f' => 0, 't' => 0]],
            'CLDR 1.0000' => ['1.0000', ['n' => 1.0, 'i' => 1, 'v' => 4, 'w' => 0, 'f' => 0, 't' => 0]],
            'CLDR 21.0' => ['21.0', ['n' => 21.0, 'i' => 21, 'v' => 1, 'w' => 0, 'f' => 0, 't' => 0]],
        ];
    }

    #[Test]
    public function testFromIntFastPath(): void
    {
        $op = PluralOperands::fromInt(42);

        self::assertSame(42.0, $op->absoluteValue);
        self::assertSame(42, $op->integerPart);
        self::assertSame(0, $op->fractionDigitCount);
        self::assertSame(0, $op->significantFractionDigitCount);
        self::assertSame(0, $op->fractionDigits);
        self::assertSame(0, $op->significantFractionDigits);
        self::assertSame(0, $op->compactExponent);
    }

    #[Test]
    public function testFromIntNegative(): void
    {
        $op = PluralOperands::fromInt(-7);

        self::assertSame(7.0, $op->absoluteValue);
        self::assertSame(7, $op->integerPart);
    }

    #[Test]
    public function testFromIntZero(): void
    {
        $op = PluralOperands::fromInt(0);

        self::assertSame(0.0, $op->absoluteValue);
        self::assertSame(0, $op->integerPart);
        self::assertSame(0, $op->fractionDigitCount);
    }

    #[Test]
    public function testImmutability(): void
    {
        $op = PluralOperands::from('1.20');

        // readonly properties cannot be modified — just verify they exist
        self::assertSame(1.2, $op->absoluteValue);
        self::assertSame(1, $op->integerPart);
        self::assertSame(2, $op->fractionDigitCount);
        self::assertSame(1, $op->significantFractionDigitCount);
        self::assertSame(20, $op->fractionDigits);
        self::assertSame(2, $op->significantFractionDigits);
    }

    #[Test]
    public function testLargeIntegers(): void
    {
        $op = PluralOperands::from(1000000);

        self::assertSame(1000000.0, $op->absoluteValue);
        self::assertSame(1000000, $op->integerPart);
        self::assertSame(0, $op->fractionDigitCount);
    }


    #[Test]
    public function testSignStripping(): void
    {
        $op = PluralOperands::from('+3.5');

        self::assertSame(3.5, $op->absoluteValue);
        self::assertSame(3, $op->integerPart);
        self::assertSame(1, $op->fractionDigitCount);
        self::assertSame(5, $op->fractionDigits);
    }

    // =========================================================================
    // Scientific notation: floats that PHP renders with "E" notation
    // =========================================================================

    /**
     * @param float $input
     * @param array{n: float, i: int, v: int, w: int, f: int, t: int} $expected
     */
    #[Test]
    #[DataProvider('scientificNotationProvider')]
    public function testScientificNotationFloats(float $input, array $expected): void
    {
        // Ensure the input actually triggers scientific notation in PHP
        self::assertNotFalse(
            stripos((string) $input, 'e'),
            "Input $input should be rendered in scientific notation by PHP"
        );

        $op = PluralOperands::from($input);

        self::assertSame($expected['i'], $op->integerPart, "integerPart (i) for input $input");
        self::assertSame($expected['v'], $op->fractionDigitCount, "fractionDigitCount (v) for input $input");
        self::assertSame($expected['w'], $op->significantFractionDigitCount, "significantFractionDigitCount (w) for input $input");
        self::assertSame($expected['f'], $op->fractionDigits, "fractionDigits (f) for input $input");
        self::assertSame($expected['t'], $op->significantFractionDigits, "significantFractionDigits (t) for input $input");
        self::assertSame(0, $op->compactExponent, "compactExponent (e) for input $input");
    }

    /**
     * @return array<string, array{0: float, 1: array{n: float, i: int, v: int, w: int, f: int, t: int}}>
     */
    public static function scientificNotationProvider(): array
    {
        return [
            // 1.0E-5 → "0.00001" (mantissa has decimal, negative exponent)
            // v=5 (00001), f=1 (leading zeros stripped as int), t=1, w=1 (only "1" is significant)
            'float 1.0E-5' => [1.0E-5, ['n' => 1.0E-5, 'i' => 0, 'v' => 6, 'w' => 5, 'f' => 10, 't' => 1]],

            // 1.5E-6 → "0.0000015" (mantissa "1.5", exponent -6)
            // v=7, f=15, t=15, w=7
            'float 1.5E-6' => [1.5E-6, ['n' => 1.5E-6, 'i' => 0, 'v' => 7, 'w' => 7, 'f' => 15, 't' => 15]],

            // 1.23E-7 → "0.000000123" (mantissa "1.23", exponent -7)
            // v=9, f=123, t=123, w=9
            'float 1.23E-7' => [1.23E-7, ['n' => 1.23E-7, 'i' => 0, 'v' => 9, 'w' => 9, 'f' => 123, 't' => 123]],

            // 3.0E-5 → "0.000030" → number_format produces trailing zero
            // v=6, f=30, t=3, w=5
            'float 3.0E-5' => [3.0E-5, ['n' => 3.0E-5, 'i' => 0, 'v' => 6, 'w' => 5, 'f' => 30, 't' => 3]],
        ];
    }
}


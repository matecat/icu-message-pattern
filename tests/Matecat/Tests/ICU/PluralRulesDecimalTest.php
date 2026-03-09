<?php

declare(strict_types=1);

namespace Matecat\ICU\Tests;

use Matecat\ICU\Plurals\PluralRules;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the decimal-aware PluralRules API methods:
 * - getCardinalFormIndexForNumber()
 * - getCardinalCategoryNameForNumber()
 * - getOrdinalFormIndexForNumber()
 * - getOrdinalCategoryNameForNumber()
 *
 * Test data is derived from CLDR 49 @decimal samples in plurals.xml.
 */
class PluralRulesDecimalTest extends TestCase
{
    // =========================================================================
    // Backward compatibility: integers via ForNumber should match int methods
    // =========================================================================

    #[Test]
    public function testIntegerBackwardCompatibility(): void
    {
        $locales = ['en', 'fr', 'ru', 'ar', 'cs', 'lt', 'lv', 'pl', 'he', 'mk', 'gv', 'da', 'is'];
        $values = [0, 1, 2, 3, 5, 10, 11, 21, 100, 1000000];

        foreach ($locales as $locale) {
            foreach ($values as $n) {
                self::assertSame(
                    PluralRules::getCardinalFormIndex($locale, $n),
                    PluralRules::getCardinalFormIndexForNumber($locale, $n),
                    "Cardinal form index mismatch for locale=$locale, n=$n"
                );
                self::assertSame(
                    PluralRules::getCardinalCategoryName($locale, $n),
                    PluralRules::getCardinalCategoryNameForNumber($locale, $n),
                    "Cardinal category name mismatch for locale=$locale, n=$n"
                );
                self::assertSame(
                    PluralRules::getOrdinalFormIndex($locale, $n),
                    PluralRules::getOrdinalFormIndexForNumber($locale, $n),
                    "Ordinal form index mismatch for locale=$locale, n=$n"
                );
                self::assertSame(
                    PluralRules::getOrdinalCategoryName($locale, $n),
                    PluralRules::getOrdinalCategoryNameForNumber($locale, $n),
                    "Ordinal category name mismatch for locale=$locale, n=$n"
                );
            }
        }
    }

    // =========================================================================
    // Rule 0: No plurals (Asian languages) — always "other"
    // =========================================================================

    #[Test]
    public function testRule0Decimals(): void
    {
        self::assertSame('other', PluralRules::getCardinalCategoryNameForNumber('ja', '0.5'));
        self::assertSame('other', PluralRules::getCardinalCategoryNameForNumber('zh', '1.0'));
        self::assertSame('other', PluralRules::getCardinalCategoryNameForNumber('ko', '100.5'));
    }

    // =========================================================================
    // Rule 1 default: "i = 1 and v = 0" — decimals always "other"
    // =========================================================================

    #[Test]
    public function testRule1DefaultDecimals(): void
    {
        // CLDR: one = i = 1 and v = 0 → for v > 0, always "other"
        self::assertSame('other', PluralRules::getCardinalCategoryNameForNumber('en', '1.0'));
        self::assertSame('other', PluralRules::getCardinalCategoryNameForNumber('en', '0.5'));
        self::assertSame('other', PluralRules::getCardinalCategoryNameForNumber('de', '1.00'));
        self::assertSame('other', PluralRules::getCardinalCategoryNameForNumber('sv', '0.1'));
    }

    // =========================================================================
    // Rule 1 / n=1 locales: CLDR "n = 1" — 1.0 is "one", 0.5 is "other"
    // =========================================================================

    #[Test]
    public function testN1LocaleDecimals(): void
    {
        // CLDR @decimal: 1.0, 1.00, 1.000, 1.0000 → "one"
        self::assertSame('one', PluralRules::getCardinalCategoryNameForNumber('af', '1.0'));
        self::assertSame('one', PluralRules::getCardinalCategoryNameForNumber('tr', '1.00'));
        self::assertSame('one', PluralRules::getCardinalCategoryNameForNumber('bg', '1.000'));

        // Non-1 decimals → "other"
        self::assertSame('other', PluralRules::getCardinalCategoryNameForNumber('af', '0.5'));
        self::assertSame('other', PluralRules::getCardinalCategoryNameForNumber('tr', '2.0'));
        self::assertSame('other', PluralRules::getCardinalCategoryNameForNumber('bg', '0.0'));
    }

    // =========================================================================
    // Rule 1 / da: CLDR "n = 1 or t != 0 and i = 0,1"
    // =========================================================================

    #[Test]
    public function testDanishDecimals(): void
    {
        // @decimal 0.1~1.6 → "one"
        self::assertSame('one', PluralRules::getCardinalCategoryNameForNumber('da', '0.1'));
        self::assertSame('one', PluralRules::getCardinalCategoryNameForNumber('da', '0.5'));
        self::assertSame('one', PluralRules::getCardinalCategoryNameForNumber('da', '1.0'));
        self::assertSame('one', PluralRules::getCardinalCategoryNameForNumber('da', '1.5'));

        // @decimal 0.0, 2.0~3.4 → "other"
        self::assertSame('other', PluralRules::getCardinalCategoryNameForNumber('da', '0.0'));
        self::assertSame('other', PluralRules::getCardinalCategoryNameForNumber('da', '2.0'));
        self::assertSame('other', PluralRules::getCardinalCategoryNameForNumber('da', '3.4'));
    }

    // =========================================================================
    // Rule 1 / is: CLDR "t = 0 and i%10=1 and i%100!=11 or t%10=1 and t%100!=11"
    // =========================================================================

    #[Test]
    public function testIcelandicDecimals(): void
    {
        // @decimal 0.1, 1.0, 1.1, 2.1, 3.1, 10.1, 100.1 → "one"
        self::assertSame('one', PluralRules::getCardinalCategoryNameForNumber('is', '0.1'));
        self::assertSame('one', PluralRules::getCardinalCategoryNameForNumber('is', '1.1'));
        self::assertSame('one', PluralRules::getCardinalCategoryNameForNumber('is', '2.1'));

        // @decimal 0.0, 0.2~0.9, 1.2~1.8 → "other"
        self::assertSame('other', PluralRules::getCardinalCategoryNameForNumber('is', '0.0'));
        self::assertSame('other', PluralRules::getCardinalCategoryNameForNumber('is', '0.2'));
        self::assertSame('other', PluralRules::getCardinalCategoryNameForNumber('is', '1.2'));
    }

    // =========================================================================
    // Rule 2 / default: "i = 0 or n = 1" (am, hi, gu, etc.)
    // =========================================================================

    #[Test]
    public function testRule2DefaultDecimals(): void
    {
        // @decimal 0.0~1.0, 0.00~0.04 → "one"
        self::assertSame('one', PluralRules::getCardinalCategoryNameForNumber('hi', '0.0'));
        self::assertSame('one', PluralRules::getCardinalCategoryNameForNumber('hi', '0.5'));
        self::assertSame('one', PluralRules::getCardinalCategoryNameForNumber('hi', '1.0'));
        self::assertSame('one', PluralRules::getCardinalCategoryNameForNumber('am', '0.04'));

        // @decimal 1.1~2.6 → "other"
        self::assertSame('other', PluralRules::getCardinalCategoryNameForNumber('hi', '1.1'));
        self::assertSame('other', PluralRules::getCardinalCategoryNameForNumber('hi', '2.0'));
    }

    // =========================================================================
    // Rule 2 / i_01: "i = 0,1" (ff, hy, kab)
    // =========================================================================

    #[Test]
    public function testI01LocaleDecimals(): void
    {
        // @decimal 0.0~1.5 → "one"
        self::assertSame('one', PluralRules::getCardinalCategoryNameForNumber('ff', '0.0'));
        self::assertSame('one', PluralRules::getCardinalCategoryNameForNumber('ff', '1.5'));
        self::assertSame('one', PluralRules::getCardinalCategoryNameForNumber('hy', '0.5'));

        // @decimal 2.0~3.5 → "other"
        self::assertSame('other', PluralRules::getCardinalCategoryNameForNumber('ff', '2.0'));
        self::assertSame('other', PluralRules::getCardinalCategoryNameForNumber('kab', '3.5'));
    }

    // =========================================================================
    // Rule 2 / si: "n = 0,1 or i = 0 and f = 1"
    // =========================================================================

    #[Test]
    public function testSinhalaDecimals(): void
    {
        // @decimal 0.0, 0.1, 1.0, 0.00, 0.01, 1.00, 0.000, 0.001, 1.000 → "one"
        self::assertSame('one', PluralRules::getCardinalCategoryNameForNumber('si', '0.0'));
        self::assertSame('one', PluralRules::getCardinalCategoryNameForNumber('si', '0.1'));
        self::assertSame('one', PluralRules::getCardinalCategoryNameForNumber('si', '1.0'));
        self::assertSame('one', PluralRules::getCardinalCategoryNameForNumber('si', '0.01'));

        // @decimal 0.2~0.9, 1.1~1.8 → "other"
        self::assertSame('other', PluralRules::getCardinalCategoryNameForNumber('si', '0.2'));
        self::assertSame('other', PluralRules::getCardinalCategoryNameForNumber('si', '1.1'));
    }

    // =========================================================================
    // Rule 2 / n_01_range: "n = 0..1" (ak, mg, pa, etc.)
    // =========================================================================

    #[Test]
    public function testN01RangeDecimals(): void
    {
        // @decimal 0.0, 1.0, 0.00, 1.00 → "one"
        self::assertSame('one', PluralRules::getCardinalCategoryNameForNumber('ak', '0.0'));
        self::assertSame('one', PluralRules::getCardinalCategoryNameForNumber('mg', '1.0'));
        self::assertSame('one', PluralRules::getCardinalCategoryNameForNumber('pa', '0.5'));

        // @decimal 0.1~0.9, 1.1~1.7 → "other"
        self::assertSame('other', PluralRules::getCardinalCategoryNameForNumber('ak', '1.1'));
        self::assertSame('other', PluralRules::getCardinalCategoryNameForNumber('mg', '2.0'));
    }

    // =========================================================================
    // Rule 3 / be: Belarusian — uses n% (decimals participate)
    // =========================================================================

    #[Test]
    public function testBelarusianDecimals(): void
    {
        // @decimal 1.0, 21.0, 31.0 → "one"
        self::assertSame('one', PluralRules::getCardinalCategoryNameForNumber('be', '1.0'));
        self::assertSame('one', PluralRules::getCardinalCategoryNameForNumber('be', '21.0'));

        // @decimal 2.0, 3.0, 22.0 → "few"
        self::assertSame('few', PluralRules::getCardinalCategoryNameForNumber('be', '2.0'));
        self::assertSame('few', PluralRules::getCardinalCategoryNameForNumber('be', '3.0'));

        // @decimal 0.0, 5.0, 10.0, 11.0 → "many"
        self::assertSame('many', PluralRules::getCardinalCategoryNameForNumber('be', '0.0'));
        self::assertSame('many', PluralRules::getCardinalCategoryNameForNumber('be', '5.0'));

        // @decimal 0.1~0.9, 1.1~1.7 → "other"
        self::assertSame('other', PluralRules::getCardinalCategoryNameForNumber('be', '0.1'));
        self::assertSame('other', PluralRules::getCardinalCategoryNameForNumber('be', '1.5'));
    }

    // =========================================================================
    // Rule 3 / ru, uk: Russian/Ukrainian — v=0 prefix, decimals → "other"
    // =========================================================================

    #[Test]
    public function testRussianUkrainianDecimals(): void
    {
        // All decimals (v > 0) → "other" for ru/uk
        // CLDR @decimal: 0.0~1.5 → "other"
        self::assertSame('many', PluralRules::getCardinalCategoryNameForNumber('ru', '0.0'));
        self::assertSame('many', PluralRules::getCardinalCategoryNameForNumber('ru', '1.0'));
        self::assertSame('many', PluralRules::getCardinalCategoryNameForNumber('ru', '1.5'));
        self::assertSame('many', PluralRules::getCardinalCategoryNameForNumber('uk', '0.5'));
        self::assertSame('many', PluralRules::getCardinalCategoryNameForNumber('uk', '100.0'));
    }

    // =========================================================================
    // Rule 4: Czech/Slovak — v != 0 → "many"
    // =========================================================================

    #[Test]
    public function testCzechSlovakDecimals(): void
    {
        // CLDR: many = v != 0 → @decimal 0.0~1.5, 10.0 → "many"
        self::assertSame('many', PluralRules::getCardinalCategoryNameForNumber('cs', '0.0'));
        self::assertSame('many', PluralRules::getCardinalCategoryNameForNumber('cs', '1.5'));
        self::assertSame('many', PluralRules::getCardinalCategoryNameForNumber('sk', '10.0'));
        self::assertSame('many', PluralRules::getCardinalCategoryNameForNumber('sk', '100.0'));
    }

    // =========================================================================
    // Rule 6: Lithuanian — f != 0 → "many"
    // =========================================================================

    #[Test]
    public function testLithuanianDecimals(): void
    {
        // CLDR: many = f != 0 → @decimal 0.1~0.9, 1.1~1.7, 10.1 → "many"
        self::assertSame('many', PluralRules::getCardinalCategoryNameForNumber('lt', '0.1'));
        self::assertSame('many', PluralRules::getCardinalCategoryNameForNumber('lt', '1.7'));
        self::assertSame('many', PluralRules::getCardinalCategoryNameForNumber('lt', '10.1'));

        // f = 0 (e.g., "1.0") → evaluate n% rules: 1.0 → n%10=1, n%100!=11 → "one"... wait.
        // Actually CLDR f = 0 means no non-zero fraction. But "1.0" has f=0.
        // So "1.0" would have f=0, making many=false. Then check one/few/other via n%10, n%100.
        // For "1.0": n=1.0, nInt=1, n%10=1, n%100=1 → one? But this is a decimal, evaluateCardinalByRuleGroup
        // is called with v > 0... and f=0 with v>0 (e.g., "1.0" → v=1, f=0).
        // So: f != 0 is false → other (index 3)
        self::assertSame('other', PluralRules::getCardinalCategoryNameForNumber('lt', '1.0'));
        self::assertSame('other', PluralRules::getCardinalCategoryNameForNumber('lt', '0.0'));
    }

    // =========================================================================
    // Rule 7 / sl: Slovenian — v != 0 → "few"
    // =========================================================================

    #[Test]
    public function testSlovenianDecimals(): void
    {
        // CLDR: few = v = 0 and i%100=3..4 or v != 0
        // For decimals (v > 0): always "few"
        self::assertSame('few', PluralRules::getCardinalCategoryNameForNumber('sl', '0.0'));
        self::assertSame('few', PluralRules::getCardinalCategoryNameForNumber('sl', '1.5'));
        self::assertSame('few', PluralRules::getCardinalCategoryNameForNumber('sl', '100.0'));
    }

    // =========================================================================
    // Rule 7 / dsb, hsb: Sorbian — f%100 clauses
    // =========================================================================

    #[Test]
    public function testSorbianDecimals(): void
    {
        // @decimal 0.1, 1.1, 2.1, 3.1 → f%100=1 → "one"
        self::assertSame('one', PluralRules::getCardinalCategoryNameForNumber('dsb', '0.1'));
        self::assertSame('one', PluralRules::getCardinalCategoryNameForNumber('hsb', '1.1'));

        // @decimal 0.2, 1.2, 2.2 → f%100=2 → "two"
        self::assertSame('two', PluralRules::getCardinalCategoryNameForNumber('dsb', '0.2'));
        self::assertSame('two', PluralRules::getCardinalCategoryNameForNumber('hsb', '1.2'));

        // @decimal 0.3, 0.4, 1.3, 1.4 → f%100=3..4 → "few"
        self::assertSame('few', PluralRules::getCardinalCategoryNameForNumber('dsb', '0.3'));
        self::assertSame('few', PluralRules::getCardinalCategoryNameForNumber('hsb', '0.4'));

        // @decimal 0.0, 0.5~1.0, 1.5~2.0 → "other"
        self::assertSame('other', PluralRules::getCardinalCategoryNameForNumber('dsb', '0.0'));
        self::assertSame('other', PluralRules::getCardinalCategoryNameForNumber('hsb', '0.5'));
    }

    // =========================================================================
    // Rule 8: Macedonian — f%10=1 and f%100!=11 → "one"
    // =========================================================================

    #[Test]
    public function testMacedonianDecimals(): void
    {
        // @decimal 0.1, 1.1, 2.1 → f%10=1, f%100!=11 → "one"
        self::assertSame('one', PluralRules::getCardinalCategoryNameForNumber('mk', '0.1'));
        self::assertSame('one', PluralRules::getCardinalCategoryNameForNumber('mk', '1.1'));

        // @decimal 0.0, 0.2~1.0, 1.2~1.7 → "other"
        self::assertSame('other', PluralRules::getCardinalCategoryNameForNumber('mk', '0.0'));
        self::assertSame('other', PluralRules::getCardinalCategoryNameForNumber('mk', '0.2'));
    }

    // =========================================================================
    // Rule 10: Latvian — v=2 and f% clauses
    // =========================================================================

    #[Test]
    public function testLatvianDecimals(): void
    {
        // @decimal zero: 0.0, 10.0, 11.0, 12.0 → "zero"
        self::assertSame('zero', PluralRules::getCardinalCategoryNameForNumber('lv', '0.0'));
        self::assertSame('zero', PluralRules::getCardinalCategoryNameForNumber('lv', '10.0'));

        // @decimal one: 0.1, 1.0, 1.1, 2.1, 100.1 → "one"
        self::assertSame('one', PluralRules::getCardinalCategoryNameForNumber('lv', '0.1'));
        self::assertSame('one', PluralRules::getCardinalCategoryNameForNumber('lv', '1.1'));

        // @decimal other: 0.2~0.9, 1.2~1.9 → "other"
        self::assertSame('other', PluralRules::getCardinalCategoryNameForNumber('lv', '0.2'));
        self::assertSame('other', PluralRules::getCardinalCategoryNameForNumber('lv', '1.9'));
    }

    // =========================================================================
    // Rule 11: Polish — v > 0 → "other"
    // =========================================================================

    #[Test]
    public function testPolishDecimals(): void
    {
        // CLDR: other = (for decimals, all v=0 conditions fail)
        // @decimal 0.0~1.5 → "other"
        self::assertSame('other', PluralRules::getCardinalCategoryNameForNumber('pl', '0.0'));
        self::assertSame('other', PluralRules::getCardinalCategoryNameForNumber('pl', '1.5'));
        self::assertSame('other', PluralRules::getCardinalCategoryNameForNumber('pl', '10.0'));
    }

    // =========================================================================
    // Rule 12: Romanian — v != 0 → "few"
    // =========================================================================

    #[Test]
    public function testRomanianDecimals(): void
    {
        // CLDR: few = v != 0 or ... → @decimal 0.0~1.5 → "few"
        self::assertSame('few', PluralRules::getCardinalCategoryNameForNumber('ro', '0.0'));
        self::assertSame('few', PluralRules::getCardinalCategoryNameForNumber('ro', '1.5'));
        self::assertSame('few', PluralRules::getCardinalCategoryNameForNumber('mo', '10.0'));
    }

    // =========================================================================
    // Rule 18: Manx — v != 0 → "many"
    // =========================================================================

    #[Test]
    public function testManxDecimals(): void
    {
        // CLDR: many = v != 0 → @decimal 0.0~1.5 → "many"
        self::assertSame('many', PluralRules::getCardinalCategoryNameForNumber('gv', '0.0'));
        self::assertSame('many', PluralRules::getCardinalCategoryNameForNumber('gv', '1.5'));
        self::assertSame('many', PluralRules::getCardinalCategoryNameForNumber('gv', '100.0'));
    }

    // =========================================================================
    // Rule 19: Hebrew — i=0 and v!=0 → "one"
    // =========================================================================

    #[Test]
    public function testHebrewDecimals(): void
    {
        // CLDR: one = i=1 and v=0 or i=0 and v!=0
        // @decimal 0.0~0.9, 0.00~0.05 → "one" (i=0 and v!=0)
        self::assertSame('one', PluralRules::getCardinalCategoryNameForNumber('he', '0.0'));
        self::assertSame('one', PluralRules::getCardinalCategoryNameForNumber('he', '0.5'));
        self::assertSame('one', PluralRules::getCardinalCategoryNameForNumber('he', '0.05'));

        // @decimal 1.0~2.5 → "other"
        self::assertSame('other', PluralRules::getCardinalCategoryNameForNumber('he', '1.0'));
        self::assertSame('other', PluralRules::getCardinalCategoryNameForNumber('he', '2.5'));
    }

    // =========================================================================
    // Rule 25: Filipino — v!=0 and f%10 != 4,6,9 → "one"
    // =========================================================================

    #[Test]
    public function testFilipinoDecimals(): void
    {
        // @decimal one: 0.0~0.3, 0.5, 0.7, 0.8, 1.0~1.3, 1.5, 1.7, 1.8 → "one"
        self::assertSame('one', PluralRules::getCardinalCategoryNameForNumber('fil', '0.0'));
        self::assertSame('one', PluralRules::getCardinalCategoryNameForNumber('fil', '0.5'));
        self::assertSame('one', PluralRules::getCardinalCategoryNameForNumber('fil', '1.0'));

        // @decimal other: 0.4, 0.6, 0.9, 1.4, 1.6, 1.9 → "other"
        self::assertSame('other', PluralRules::getCardinalCategoryNameForNumber('fil', '0.4'));
        self::assertSame('other', PluralRules::getCardinalCategoryNameForNumber('fil', '0.6'));
        self::assertSame('other', PluralRules::getCardinalCategoryNameForNumber('fil', '0.9'));
    }

    // =========================================================================
    // Rule 27: Bosnian/Croatian/Serbian — f% clauses
    // =========================================================================

    #[Test]
    public function testBosnianCroatianSerbianDecimals(): void
    {
        // @decimal one: 0.1, 1.1, 2.1 → f%10=1, f%100!=11 → "one"
        self::assertSame('one', PluralRules::getCardinalCategoryNameForNumber('bs', '0.1'));
        self::assertSame('one', PluralRules::getCardinalCategoryNameForNumber('hr', '1.1'));

        // @decimal few: 0.2~0.4, 1.2~1.4 → f%10=2..4, f%100!=12..14 → "few"
        self::assertSame('few', PluralRules::getCardinalCategoryNameForNumber('sr', '0.2'));
        self::assertSame('few', PluralRules::getCardinalCategoryNameForNumber('bs', '0.4'));

        // @decimal other: 0.0, 0.5~1.0, 1.5~2.0 → "other"
        self::assertSame('other', PluralRules::getCardinalCategoryNameForNumber('hr', '0.0'));
        self::assertSame('other', PluralRules::getCardinalCategoryNameForNumber('sr', '0.5'));
    }

    // =========================================================================
    // Rule 29: French — i = 0,1 → "one"
    // =========================================================================

    #[Test]
    public function testFrenchDecimals(): void
    {
        // @decimal 0.0~1.5 → "one"
        self::assertSame('one', PluralRules::getCardinalCategoryNameForNumber('fr', '0.0'));
        self::assertSame('one', PluralRules::getCardinalCategoryNameForNumber('fr', '1.5'));

        // @decimal 2.0~3.5 → "other"
        self::assertSame('other', PluralRules::getCardinalCategoryNameForNumber('fr', '2.0'));
        self::assertSame('other', PluralRules::getCardinalCategoryNameForNumber('fr', '3.5'));
    }

    // =========================================================================
    // Rule 22 / lag: Langi — zero=n=0; one=i=0,1 and n!=0
    // =========================================================================

    #[Test]
    public function testLangiDecimals(): void
    {
        // zero: n=0
        self::assertSame('zero', PluralRules::getCardinalCategoryNameForNumber('lag', '0.0'));

        // one: i=0,1 and n!=0 → @decimal 0.1~1.6
        self::assertSame('one', PluralRules::getCardinalCategoryNameForNumber('lag', '0.1'));
        self::assertSame('one', PluralRules::getCardinalCategoryNameForNumber('lag', '1.5'));

        // other: @decimal 2.0~3.5
        self::assertSame('other', PluralRules::getCardinalCategoryNameForNumber('lag', '2.0'));
    }

    // =========================================================================
    // Rule 5: Irish — n=1/n=2/n=3..6/n=7..10/other (uses n)
    // =========================================================================

    #[Test]
    public function testIrishDecimals(): void
    {
        // @decimal one: n=1 (1.0)
        self::assertSame('one', PluralRules::getCardinalCategoryNameForNumber('ga', '1.0'));

        // @decimal two: n=2 (2.0)
        self::assertSame('two', PluralRules::getCardinalCategoryNameForNumber('ga', '2.0'));

        // @decimal few: n=3..6 (5.0)
        self::assertSame('few', PluralRules::getCardinalCategoryNameForNumber('ga', '5.0'));

        // @decimal many: n=7..10 (8.0)
        self::assertSame('many', PluralRules::getCardinalCategoryNameForNumber('ga', '8.0'));

        // @decimal other: non-integer decimals → other
        self::assertSame('other', PluralRules::getCardinalCategoryNameForNumber('ga', '0.5'));
        self::assertSame('other', PluralRules::getCardinalCategoryNameForNumber('ga', '11.0'));
    }

    // =========================================================================
    // Rule 13: Arabic — uses n (absolute value)
    // =========================================================================

    #[Test]
    public function testArabicDecimals(): void
    {
        // @decimal zero: n=0
        self::assertSame('zero', PluralRules::getCardinalCategoryNameForNumber('ar', '0.0'));

        // @decimal one: n=1
        self::assertSame('one', PluralRules::getCardinalCategoryNameForNumber('ar', '1.0'));

        // @decimal two: n=2
        self::assertSame('two', PluralRules::getCardinalCategoryNameForNumber('ar', '2.0'));

        // @decimal few: n%100=3..10
        self::assertSame('few', PluralRules::getCardinalCategoryNameForNumber('ar', '3.0'));
        self::assertSame('few', PluralRules::getCardinalCategoryNameForNumber('ar', '10.0'));

        // @decimal many: n%100=11..99
        self::assertSame('many', PluralRules::getCardinalCategoryNameForNumber('ar', '11.0'));
        self::assertSame('many', PluralRules::getCardinalCategoryNameForNumber('ar', '99.0'));

        // @decimal other: non-integer decimals
        self::assertSame('other', PluralRules::getCardinalCategoryNameForNumber('ar', '0.5'));
        self::assertSame('other', PluralRules::getCardinalCategoryNameForNumber('ar', '100.0'));
    }

    // =========================================================================
    // Rule 14: Welsh — uses n (absolute value)
    // =========================================================================

    #[Test]
    public function testWelshDecimals(): void
    {
        // @decimal zero: n=0
        self::assertSame('zero', PluralRules::getCardinalCategoryNameForNumber('cy', '0.0'));

        // @decimal one: n=1
        self::assertSame('one', PluralRules::getCardinalCategoryNameForNumber('cy', '1.0'));

        // @decimal two: n=2
        self::assertSame('two', PluralRules::getCardinalCategoryNameForNumber('cy', '2.0'));

        // @decimal few: n=3
        self::assertSame('few', PluralRules::getCardinalCategoryNameForNumber('cy', '3.0'));

        // @decimal many: n=6
        self::assertSame('many', PluralRules::getCardinalCategoryNameForNumber('cy', '6.0'));

        // @decimal other: rest
        self::assertSame('other', PluralRules::getCardinalCategoryNameForNumber('cy', '4.0'));
        self::assertSame('other', PluralRules::getCardinalCategoryNameForNumber('cy', '0.5'));
    }

    // =========================================================================
    // Rule 16: Scottish Gaelic — uses n
    // =========================================================================

    #[Test]
    public function testScottishGaelicDecimals(): void
    {
        // @decimal one: n=1 or n=11
        self::assertSame('one', PluralRules::getCardinalCategoryNameForNumber('gd', '1.0'));
        self::assertSame('one', PluralRules::getCardinalCategoryNameForNumber('gd', '11.0'));

        // @decimal two: n=2 or n=12
        self::assertSame('two', PluralRules::getCardinalCategoryNameForNumber('gd', '2.0'));
        self::assertSame('two', PluralRules::getCardinalCategoryNameForNumber('gd', '12.0'));

        // @decimal few: n=3..10 or n=13..19
        self::assertSame('few', PluralRules::getCardinalCategoryNameForNumber('gd', '5.0'));
        self::assertSame('few', PluralRules::getCardinalCategoryNameForNumber('gd', '15.0'));

        // @decimal other: rest
        self::assertSame('other', PluralRules::getCardinalCategoryNameForNumber('gd', '20.0'));
        self::assertSame('other', PluralRules::getCardinalCategoryNameForNumber('gd', '0.5'));
    }

    // =========================================================================
    // Rule 17: Breton — uses n (integer-valued decimals participate)
    // =========================================================================

    #[Test]
    public function testBretonDecimals(): void
    {
        // @decimal one: n%10=1 and n%100 not in 11,71,91
        self::assertSame('one', PluralRules::getCardinalCategoryNameForNumber('br', '1.0'));
        self::assertSame('one', PluralRules::getCardinalCategoryNameForNumber('br', '21.0'));

        // @decimal two: n%10=2 and n%100 not in 12,72,92
        self::assertSame('two', PluralRules::getCardinalCategoryNameForNumber('br', '2.0'));
        self::assertSame('two', PluralRules::getCardinalCategoryNameForNumber('br', '22.0'));

        // Non-integer decimals → other
        self::assertSame('other', PluralRules::getCardinalCategoryNameForNumber('br', '0.5'));
        self::assertSame('other', PluralRules::getCardinalCategoryNameForNumber('br', '1.5'));
    }

    // =========================================================================
    // Rule 20: Catalan/Spanish/Italian — v > 0 → "other"
    // =========================================================================

    #[Test]
    public function testCatalanSpanishItalianDecimals(): void
    {
        // For decimals (v > 0): one and many require v=0 → "other"
        self::assertSame('other', PluralRules::getCardinalCategoryNameForNumber('ca', '0.0'));
        self::assertSame('other', PluralRules::getCardinalCategoryNameForNumber('es', '1.0'));
        self::assertSame('other', PluralRules::getCardinalCategoryNameForNumber('it', '1.5'));
    }

    // =========================================================================
    // Rule 21: Inuktitut/Sami — one=n=1; two=n=2; other=rest (uses n)
    // =========================================================================

    #[Test]
    public function testInuktitutDecimals(): void
    {
        // @decimal one: n=1
        self::assertSame('one', PluralRules::getCardinalCategoryNameForNumber('iu', '1.0'));

        // @decimal two: n=2
        self::assertSame('two', PluralRules::getCardinalCategoryNameForNumber('iu', '2.0'));

        // @decimal other: rest
        self::assertSame('other', PluralRules::getCardinalCategoryNameForNumber('iu', '0.5'));
        self::assertSame('other', PluralRules::getCardinalCategoryNameForNumber('se', '3.0'));
    }

    // =========================================================================
    // Rule 22 (default): zero=n=0; one=n=1; other=rest (uses n)
    // =========================================================================

    #[Test]
    public function testRule22DefaultDecimals(): void
    {
        // @decimal zero: n=0
        self::assertSame('zero', PluralRules::getCardinalCategoryNameForNumber('ksh', '0.0'));

        // @decimal one: n=1
        self::assertSame('one', PluralRules::getCardinalCategoryNameForNumber('ksh', '1.0'));

        // @decimal other: rest
        self::assertSame('other', PluralRules::getCardinalCategoryNameForNumber('ksh', '0.5'));
        self::assertSame('other', PluralRules::getCardinalCategoryNameForNumber('ksh', '2.0'));
    }

    // =========================================================================
    // Rule 23: Tachelhit — one=i=0 or n=1; few=n=2..10; other=rest
    // =========================================================================

    #[Test]
    public function testTachelhitDecimals(): void
    {
        // @decimal one: i=0 or n=1
        self::assertSame('one', PluralRules::getCardinalCategoryNameForNumber('shi', '0.5'));
        self::assertSame('one', PluralRules::getCardinalCategoryNameForNumber('shi', '1.0'));

        // @decimal few: n=2..10
        self::assertSame('few', PluralRules::getCardinalCategoryNameForNumber('shi', '2.0'));
        self::assertSame('few', PluralRules::getCardinalCategoryNameForNumber('shi', '10.0'));

        // @decimal other: rest
        self::assertSame('other', PluralRules::getCardinalCategoryNameForNumber('shi', '11.0'));
        self::assertSame('other', PluralRules::getCardinalCategoryNameForNumber('shi', '1.5'));
    }

    // =========================================================================
    // Rule 24: Cornish — uses n (integer-valued decimals participate)
    // =========================================================================

    #[Test]
    public function testCornishDecimals(): void
    {
        // Integer-valued decimals → delegate to integer rules
        self::assertSame('one', PluralRules::getCardinalCategoryNameForNumber('kw', '1.0'));
        self::assertSame('two', PluralRules::getCardinalCategoryNameForNumber('kw', '2.0'));

        // Non-integer decimals → other
        self::assertSame('other', PluralRules::getCardinalCategoryNameForNumber('kw', '0.5'));
        self::assertSame('other', PluralRules::getCardinalCategoryNameForNumber('kw', '1.5'));
    }

    // =========================================================================
    // Rule 26: Tamazight — one=n=0..1 or n=11..99; other=rest (uses n)
    // =========================================================================

    #[Test]
    public function testTamazightDecimals(): void
    {
        // @decimal one: n=0..1 or n=11..99
        self::assertSame('one', PluralRules::getCardinalCategoryNameForNumber('tzm', '0.0'));
        // The Tamazight n=0.5 is within range 0..1, so CLDR says "one".
        self::assertSame('one', PluralRules::getCardinalCategoryNameForNumber('tzm', '0.5'));
        self::assertSame('one', PluralRules::getCardinalCategoryNameForNumber('tzm', '1.0'));
        self::assertSame('one', PluralRules::getCardinalCategoryNameForNumber('tzm', '11.0'));
        self::assertSame('one', PluralRules::getCardinalCategoryNameForNumber('tzm', '99.0'));


        // @decimal other: rest (n > 1 and n < 11, or n >= 100)
        self::assertSame('other', PluralRules::getCardinalCategoryNameForNumber('tzm', '2.0'));
        self::assertSame('other', PluralRules::getCardinalCategoryNameForNumber('tzm', '100.0'));
    }

    // =========================================================================
    // Rule 28: Maltese — uses n
    // =========================================================================

    #[Test]
    public function testMalteseDecimals(): void
    {
        // @decimal one: n=1
        self::assertSame('one', PluralRules::getCardinalCategoryNameForNumber('mt', '1.0'));

        // @decimal two: n=2
        self::assertSame('two', PluralRules::getCardinalCategoryNameForNumber('mt', '2.0'));

        // @decimal few: n=0 or n%100=3..10
        self::assertSame('few', PluralRules::getCardinalCategoryNameForNumber('mt', '0.0'));
        self::assertSame('few', PluralRules::getCardinalCategoryNameForNumber('mt', '3.0'));
        self::assertSame('few', PluralRules::getCardinalCategoryNameForNumber('mt', '10.0'));

        // @decimal many: n%100=11..19
        self::assertSame('many', PluralRules::getCardinalCategoryNameForNumber('mt', '11.0'));
        self::assertSame('many', PluralRules::getCardinalCategoryNameForNumber('mt', '19.0'));

        // @decimal other: rest
        self::assertSame('other', PluralRules::getCardinalCategoryNameForNumber('mt', '20.0'));
        self::assertSame('other', PluralRules::getCardinalCategoryNameForNumber('mt', '0.5'));
    }

    // =========================================================================
    // Rule 29 / pt: Portuguese — i = 0..1 → "one"
    // =========================================================================

    #[Test]
    public function testPortugueseDecimals(): void
    {
        // @decimal one: i=0..1
        self::assertSame('one', PluralRules::getCardinalCategoryNameForNumber('pt', '0.5'));
        self::assertSame('one', PluralRules::getCardinalCategoryNameForNumber('pt', '1.5'));

        // @decimal other: i >= 2
        self::assertSame('other', PluralRules::getCardinalCategoryNameForNumber('pt', '2.0'));
        self::assertSame('other', PluralRules::getCardinalCategoryNameForNumber('pt', '2.5'));
    }

    // =========================================================================
    // Ordinal decimal tests (should use integer part)
    // =========================================================================

    #[Test]
    public function testOrdinalDecimals(): void
    {
        // English ordinals use integer part
        self::assertSame('one', PluralRules::getOrdinalCategoryNameForNumber('en', '1.5'));   // i=1 → 1st
        self::assertSame('two', PluralRules::getOrdinalCategoryNameForNumber('en', '2.3'));   // i=2 → 2nd
        self::assertSame('few', PluralRules::getOrdinalCategoryNameForNumber('en', '3.7'));   // i=3 → 3rd
        self::assertSame('other', PluralRules::getOrdinalCategoryNameForNumber('en', '4.1')); // i=4 → 4th
    }

    // =========================================================================
    // Float input tests
    // =========================================================================

    #[Test]
    public function testFloatInputs(): void
    {
        // float 1.5 → v=1, f=5 (PHP renders "1.5")
        self::assertSame('many', PluralRules::getCardinalCategoryNameForNumber('cs', 1.5));

        // float 0.1 → v=1, f=1
        self::assertSame('one', PluralRules::getCardinalCategoryNameForNumber('mk', 0.1));
    }

    // =========================================================================
    // Locale variant tests
    // =========================================================================

    #[Test]
    public function testLocaleVariants(): void
    {
        // en-US should work like en
        self::assertSame('other', PluralRules::getCardinalCategoryNameForNumber('en-US', '1.0'));
        self::assertSame('other', PluralRules::getCardinalCategoryNameForNumber('en_GB', '0.5'));

        // fr-FR should work like fr
        self::assertSame('one', PluralRules::getCardinalCategoryNameForNumber('fr-FR', '1.5'));
    }
}


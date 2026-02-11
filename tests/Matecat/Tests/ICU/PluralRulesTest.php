<?php

declare(strict_types=1);

namespace Matecat\Tests\ICU;

use Matecat\ICU\Plurals\PluralRules;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests the functionality of the `PluralRules` class.
 *
 * This test suite validates the plural form calculation for various languages,
 * ensuring that the correct plural form index is returned for different numbers.
 *
 * The following key features are tested:
 *
 * - Rule 0: Languages with no plural forms (Asian languages like Japanese, Chinese, Korean)
 * - Rule 1: Languages with 2 forms using n != 1 (English, German, Spanish, etc.)
 * - Rule 2: Languages with 2 forms using n > 1 (French, Brazilian Portuguese)
 * - Rule 3: Slavic languages with 3 forms (Russian, Ukrainian, Serbian, Croatian)
 * - Rule 4: Czech and Slovak with 3 forms
 * - Rule 5: Irish with 5 forms
 * - Rule 6: Lithuanian with 3 forms
 * - Rule 7: Slovenian with 4 forms
 * - Rule 8: Macedonian with 2 forms (CLDR 48)
 * - Rule 9: Maltese with 4 forms
 * - Rule 10: Latvian with 3 forms (CLDR 48: zero/one/other)
 * - Rule 11: Polish with 3 forms
 * - Rule 12: Romanian with 3 forms
 * - Rule 13: Arabic with 6 forms
 * - Rule 14: Welsh with 6 forms (CLDR 48)
 * - Rule 15: Icelandic with 2 forms
 * - Rule 16: Scottish Gaelic with 4 forms
 * - Rule 17: Breton with 5 forms (CLDR 48)
 * - Rule 18: Manx with 4 forms (CLDR 48)
 * - Rule 19: Hebrew with 4 forms (CLDR 48)
 * - Locale fallback mechanism (handling locale variants like en-US, fr_FR)
 * - Unknown locale handling
 */
final class PluralRulesTest extends TestCase
{
    // =========================================================================
    // Rule 0: No plural forms (nplurals=1; plural=0)
    // Languages: Japanese, Chinese, Korean, Vietnamese, Thai, Indonesian, etc.
    // =========================================================================

    /**
     * Tests Rule 0: Languages with no plural forms.
     *
     * @param string $locale   The locale code.
     * @param int    $n        The number to test.
     * @param int    $expected The expected plural form index.
     *
     * @return void
     */
    #[DataProvider('rule0Provider')]
    public function testRule0NoPluralForms(string $locale, int $n, int $expected): void
    {
        self::assertSame($expected, PluralRules::getCardinalFormIndex($locale, $n));
    }

    /**
     * @return array<array<string|int>>
     */
    public static function rule0Provider(): array
    {
        return [
            // Japanese
            ['ja', 0, 0],
            ['ja', 1, 0],
            ['ja', 2, 0],
            ['ja', 100, 0],
            // Chinese
            ['zh', 0, 0],
            ['zh', 1, 0],
            ['zh', 5, 0],
            // Korean
            ['ko', 0, 0],
            ['ko', 1, 0],
            ['ko', 10, 0],
            // Vietnamese
            ['vi', 0, 0],
            ['vi', 1, 0],
            ['vi', 99, 0],
            // Thai
            ['th', 0, 0],
            ['th', 1, 0],
            ['th', 50, 0],
            // Indonesian
            ['id', 0, 0],
            ['id', 1, 0],
            ['id', 1000, 0],
        ];
    }

    // =========================================================================
    // Rule 1: Two forms, singular for n=1 (nplurals=2; plural=(n != 1))
    // Languages: English, German, Spanish, Italian, Dutch, etc.
    // =========================================================================

    /**
     * Tests Rule 1: Two forms, singular for n=1.
     *
     * @param string $locale   The locale code.
     * @param int    $n        The number to test.
     * @param int    $expected The expected plural form index.
     *
     * @return void
     */
    #[DataProvider('rule1Provider')]
    public function testRule1TwoFormsSingularOne(string $locale, int $n, int $expected): void
    {
        self::assertSame($expected, PluralRules::getCardinalFormIndex($locale, $n));
    }

    /**
     * @return array<array<string|int>>
     */
    public static function rule1Provider(): array
    {
        return [
            // English
            ['en', 0, 1],  // "0 items"
            ['en', 1, 0],  // "1 item"
            ['en', 2, 1],  // "2 items"
            ['en', 5, 1],  // "5 items"
            ['en', 21, 1], // "21 items"
            ['en', 100, 1],
            // German
            ['de', 0, 1],
            ['de', 1, 0],
            ['de', 2, 1],
            ['de', 11, 1],
            // Dutch
            ['nl', 0, 1],
            ['nl', 1, 0],
            ['nl', 2, 1],
            // Swedish
            ['sv', 0, 1],
            ['sv', 1, 0],
            ['sv', 2, 1],
            // Norwegian
            ['nb', 0, 1],
            ['nb', 1, 0],
            ['nb', 2, 1],
            // Danish
            ['da', 0, 1],
            ['da', 1, 0],
            ['da', 2, 1],
            // Greek
            ['el', 0, 1],
            ['el', 1, 0],
            ['el', 2, 1],
            // Hungarian
            ['hu', 0, 1],
            ['hu', 1, 0],
            ['hu', 2, 1],
            // Finnish
            ['fi', 0, 1],
            ['fi', 1, 0],
            ['fi', 2, 1],
            // Estonian
            ['et', 0, 1],
            ['et', 1, 0],
            ['et', 2, 1],
            // Bulgarian
            ['bg', 0, 1],
            ['bg', 1, 0],
            ['bg', 2, 1],
        ];
    }

    // =========================================================================
    // Rule 2: Two forms, singular for n=0 or n=1 (nplurals=2; plural=(n > 1))
    // Languages: French, Brazilian Portuguese, Occitan, etc.
    // =========================================================================

    /**
     * Tests Rule 2: Two forms, singular for n=0 or n=1.
     *
     * @param string $locale   The locale code.
     * @param int    $n        The number to test.
     * @param int    $expected The expected plural form index.
     *
     * @return void
     */
    #[DataProvider('rule2Provider')]
    public function testRule2TwoFormsSingularZeroOne(string $locale, int $n, int $expected): void
    {
        self::assertSame($expected, PluralRules::getCardinalFormIndex($locale, $n));
    }

    /**
     * @return array<array<string|int>>
     */
    public static function rule2Provider(): array
    {
        return [
            // Filipino
            ['fil', 0, 0],
            ['fil', 1, 0],
            ['fil', 2, 1],
            // Turkish
            ['tr', 0, 0],
            ['tr', 1, 0],
            ['tr', 2, 1],
            // Occitan
            ['oc', 0, 0],
            ['oc', 1, 0],
            ['oc', 2, 1],
            // Tigrinya
            ['ti', 0, 0],
            ['ti', 1, 0],
            ['ti', 2, 1],
            // Lingala
            ['ln', 0, 0],
            ['ln', 1, 0],
            ['ln', 2, 1],
        ];
    }

    // =========================================================================
    // Rule 3: Slavic languages (nplurals=3)
    // Languages: Russian, Ukrainian, Serbian, Croatian, Belarusian, Bosnian
    // =========================================================================

    /**
     * Tests Rule 3: Slavic languages with 3 forms.
     *
     * @param string $locale   The locale code.
     * @param int    $n        The number to test.
     * @param int    $expected The expected plural form index.
     *
     * @return void
     */
    #[DataProvider('rule3Provider')]
    public function testRule3Slavic(string $locale, int $n, int $expected): void
    {
        self::assertSame($expected, PluralRules::getCardinalFormIndex($locale, $n));
    }

    /**
     * @return array<array<string|int>>
     */
    public static function rule3Provider(): array
    {
        return [
            // Russian
            ['ru', 0, 2],   // "0 яблок" (many)
            ['ru', 1, 0],   // "1 яблоко" (one)
            ['ru', 2, 1],   // "2 яблока" (few)
            ['ru', 3, 1],   // "3 яблока" (few)
            ['ru', 4, 1],   // "4 яблока" (few)
            ['ru', 5, 2],   // "5 яблок" (many)
            ['ru', 10, 2],  // "10 яблок" (many)
            ['ru', 11, 2],  // "11 яблок" (many) - special case
            ['ru', 12, 2],  // "12 яблок" (many) - special case
            ['ru', 14, 2],  // "14 яблок" (many) - special case
            ['ru', 20, 2],  // "20 яблок" (many)
            ['ru', 21, 0],  // "21 яблоко" (one)
            ['ru', 22, 1],  // "22 яблока" (few)
            ['ru', 25, 2],  // "25 яблок" (many)
            ['ru', 100, 2], // "100 яблок" (many)
            ['ru', 101, 0], // "101 яблоко" (one)
            ['ru', 102, 1], // "102 яблока" (few)
            ['ru', 111, 2], // "111 яблок" (many) - special case
            ['ru', 112, 2], // "112 яблок" (many) - special case
            // Ukrainian
            ['uk', 1, 0],
            ['uk', 2, 1],
            ['uk', 5, 2],
            ['uk', 21, 0],
            ['uk', 22, 1],
            // Serbian
            ['sr', 1, 0],
            ['sr', 2, 1],
            ['sr', 5, 2],
            ['sr', 21, 0],
            // Croatian
            ['hr', 1, 0],
            ['hr', 2, 1],
            ['hr', 5, 2],
            // Belarusian
            ['be', 1, 0],
            ['be', 2, 1],
            ['be', 5, 2],
            // Bosnian
            ['bs', 1, 0],
            ['bs', 2, 1],
            ['bs', 5, 2],
        ];
    }

    // =========================================================================
    // Rule 4: Czech and Slovak (nplurals=3)
    // =========================================================================

    /**
     * Tests Rule 4: Czech and Slovak with 3 forms.
     *
     * @param string $locale   The locale code.
     * @param int    $n        The number to test.
     * @param int    $expected The expected plural form index.
     *
     * @return void
     */
    #[DataProvider('rule4Provider')]
    public function testRule4CzechSlovak(string $locale, int $n, int $expected): void
    {
        self::assertSame($expected, PluralRules::getCardinalFormIndex($locale, $n));
    }

    /**
     * @return array<array<string|int>>
     */
    public static function rule4Provider(): array
    {
        return [
            // Czech
            ['cs', 0, 2],   // "0 jablek" (other)
            ['cs', 1, 0],   // "1 jablko" (one)
            ['cs', 2, 1],   // "2 jablka" (few)
            ['cs', 3, 1],   // "3 jablka" (few)
            ['cs', 4, 1],   // "4 jablka" (few)
            ['cs', 5, 2],   // "5 jablek" (other)
            ['cs', 10, 2],
            ['cs', 21, 2],  // Different from Russian!
            ['cs', 100, 2],
            // Slovak
            ['sk', 0, 2],
            ['sk', 1, 0],
            ['sk', 2, 1],
            ['sk', 3, 1],
            ['sk', 4, 1],
            ['sk', 5, 2],
            ['sk', 10, 2],
        ];
    }

    // =========================================================================
    // Rule 5: Irish (nplurals=5)
    // =========================================================================

    /**
     * Tests Rule 5: Irish with 5 forms.
     *
     * @param string $locale   The locale code.
     * @param int    $n        The number to test.
     * @param int    $expected The expected plural form index.
     *
     * @return void
     */
    #[DataProvider('rule5Provider')]
    public function testRule5Irish(string $locale, int $n, int $expected): void
    {
        self::assertSame($expected, PluralRules::getCardinalFormIndex($locale, $n));
    }

    /**
     * @return array<array<string|int>>
     */
    public static function rule5Provider(): array
    {
        return [
            // Irish
            ['ga', 1, 0],   // one
            ['ga', 2, 1],   // two
            ['ga', 3, 2],   // few (3-6)
            ['ga', 4, 2],
            ['ga', 5, 2],
            ['ga', 6, 2],
            ['ga', 7, 3],   // many (7-10)
            ['ga', 8, 3],
            ['ga', 9, 3],
            ['ga', 10, 3],
            ['ga', 11, 4],  // other (11+)
            ['ga', 12, 4],
            ['ga', 100, 4],
        ];
    }


    // =========================================================================
    // Rule 6: Lithuanian (nplurals=3)
    // =========================================================================

    /**
     * Tests Rule 6: Lithuanian with 3 forms.
     *
     * @param string $locale   The locale code.
     * @param int    $n        The number to test.
     * @param int    $expected The expected plural form index.
     *
     * @return void
     */
    #[DataProvider('rule6Provider')]
    public function testRule6Lithuanian(string $locale, int $n, int $expected): void
    {
        self::assertSame($expected, PluralRules::getCardinalFormIndex($locale, $n));
    }

    /**
     * @return array<array<string|int>>
     */
    public static function rule6Provider(): array
    {
        return [
            ['lt', 0, 2],   // other
            ['lt', 1, 0],   // one
            ['lt', 2, 1],   // few
            ['lt', 9, 1],   // few
            ['lt', 10, 2],  // other
            ['lt', 11, 2],  // other (special case)
            ['lt', 12, 2],  // other
            ['lt', 19, 2],  // other
            ['lt', 20, 2],  // other
            ['lt', 21, 0],  // one
            ['lt', 22, 1],  // few
            ['lt', 29, 1],  // few
            ['lt', 100, 2], // other
            ['lt', 101, 0], // one
        ];
    }

    // =========================================================================
    // Rule 7: Slovenian (nplurals=4)
    // =========================================================================

    /**
     * Tests Rule 7: Slovenian with 4 forms.
     *
     * @param string $locale   The locale code.
     * @param int    $n        The number to test.
     * @param int    $expected The expected plural form index.
     *
     * @return void
     */
    #[DataProvider('rule7Provider')]
    public function testRule7Slovenian(string $locale, int $n, int $expected): void
    {
        self::assertSame($expected, PluralRules::getCardinalFormIndex($locale, $n));
    }

    /**
     * @return array<array<string|int>>
     */
    public static function rule7Provider(): array
    {
        return [
            ['sl', 0, 3],   // other
            ['sl', 1, 0],   // one (n%100 == 1)
            ['sl', 2, 1],   // two (n%100 == 2)
            ['sl', 3, 2],   // few (n%100 == 3 or 4)
            ['sl', 4, 2],   // few
            ['sl', 5, 3],   // other
            ['sl', 10, 3],  // other
            ['sl', 100, 3], // other
            ['sl', 101, 0], // one
            ['sl', 102, 1], // two
            ['sl', 103, 2], // few
            ['sl', 104, 2], // few
            ['sl', 105, 3], // other
            ['sl', 201, 0], // one
        ];
    }

    // =========================================================================
    // Rule 8: Macedonian (nplurals=2 - CLDR 48)
    // =========================================================================

    /**
     * Tests Rule 8: Macedonian with 2 forms (CLDR 48).
     *
     * @param string $locale   The locale code.
     * @param int    $n        The number to test.
     * @param int    $expected The expected plural form index.
     *
     * @return void
     */
    #[DataProvider('rule8Provider')]
    public function testRule8Macedonian(string $locale, int $n, int $expected): void
    {
        self::assertSame($expected, PluralRules::getCardinalFormIndex($locale, $n));
    }

    /**
     * @return array<array<string|int>>
     */
    public static function rule8Provider(): array
    {
        return [
            // CLDR 48: one = n % 10 = 1 and n % 100 != 11; other = everything else
            ['mk', 0, 1],   // other
            ['mk', 1, 0],   // one (n%10 == 1, n%100 != 11)
            ['mk', 2, 1],   // other
            ['mk', 3, 1],   // other
            ['mk', 10, 1],  // other
            ['mk', 11, 1],  // other (special case: n%100 == 11)
            ['mk', 12, 1],  // other
            ['mk', 21, 0],  // one
            ['mk', 22, 1],  // other
            ['mk', 31, 0],  // one
            ['mk', 32, 1],  // other
            ['mk', 111, 1], // other (special case: n%100 == 11)
            ['mk', 112, 1], // other
        ];
    }

    // =========================================================================
    // Rule 9: Maltese (nplurals=4)
    // =========================================================================

    /**
     * Tests Rule 9: Maltese with 4 forms.
     *
     * @param string $locale   The locale code.
     * @param int    $n        The number to test.
     * @param int    $expected The expected plural form index.
     *
     * @return void
     */
    #[DataProvider('rule9Provider')]
    public function testRule9Maltese(string $locale, int $n, int $expected): void
    {
        self::assertSame($expected, PluralRules::getCardinalFormIndex($locale, $n));
    }

    /**
     * @return array<array<string|int>>
     */
    public static function rule9Provider(): array
    {
        return [
            ['mt', 1, 0],   // one
            ['mt', 0, 1],   // few (n==0 or n%100 in 1..10)
            ['mt', 2, 1],   // few
            ['mt', 3, 1],   // few
            ['mt', 10, 1],  // few
            ['mt', 11, 2],  // many (n%100 in 11..19)
            ['mt', 15, 2],  // many
            ['mt', 19, 2],  // many
            ['mt', 20, 3],  // other
            ['mt', 21, 3],  // other
            ['mt', 100, 3], // other
            ['mt', 101, 1], // few
            ['mt', 102, 1], // few
            ['mt', 111, 2], // many
        ];
    }

    // =========================================================================
    // Rule 10: Latvian (nplurals=3 - CLDR 48)
    // Category order: zero, one, other
    // =========================================================================

    /**
     * Tests Rule 10: Latvian with 3 forms (CLDR 48).
     *
     * @param string $locale   The locale code.
     * @param int    $n        The number to test.
     * @param int    $expected The expected plural form index.
     *
     * @return void
     */
    #[DataProvider('rule10Provider')]
    public function testRule10Latvian(string $locale, int $n, int $expected): void
    {
        self::assertSame($expected, PluralRules::getCardinalFormIndex($locale, $n));
    }

    /**
     * @return array<array<string|int>>
     */
    public static function rule10Provider(): array
    {
        return [
            // CLDR 48: zero = n = 0; one = n % 10 = 1 and n % 100 != 11; other = everything else
            ['lv', 0, 0],   // zero (n == 0)
            ['lv', 1, 1],   // one (n%10 == 1, n%100 != 11)
            ['lv', 2, 2],   // other
            ['lv', 10, 2],  // other
            ['lv', 11, 2],  // other (special case: n%100 == 11)
            ['lv', 21, 1],  // one
            ['lv', 31, 1],  // one
            ['lv', 100, 2], // other
            ['lv', 101, 1], // one
            ['lv', 111, 2], // other (special case: n%100 == 11)
        ];
    }

    // =========================================================================
    // Rule 11: Polish (nplurals=3)
    // =========================================================================

    /**
     * Tests Rule 11: Polish with 3 forms.
     *
     * @param string $locale   The locale code.
     * @param int    $n        The number to test.
     * @param int    $expected The expected plural form index.
     *
     * @return void
     */
    #[DataProvider('rule11Provider')]
    public function testRule11Polish(string $locale, int $n, int $expected): void
    {
        self::assertSame($expected, PluralRules::getCardinalFormIndex($locale, $n));
    }

    /**
     * @return array<array<string|int>>
     */
    public static function rule11Provider(): array
    {
        return [
            ['pl', 0, 2],   // many
            ['pl', 1, 0],   // one
            ['pl', 2, 1],   // few
            ['pl', 3, 1],   // few
            ['pl', 4, 1],   // few
            ['pl', 5, 2],   // many
            ['pl', 10, 2],  // many
            ['pl', 11, 2],  // many
            ['pl', 12, 2],  // many
            ['pl', 14, 2],  // many
            ['pl', 20, 2],  // many
            ['pl', 21, 2],  // many (different from Russian!)
            ['pl', 22, 1],  // few
            ['pl', 23, 1],  // few
            ['pl', 24, 1],  // few
            ['pl', 25, 2],  // many
            ['pl', 100, 2], // many
            ['pl', 101, 2], // many (different from Russian!)
            ['pl', 102, 1], // few
            ['pl', 112, 2], // many
            ['pl', 122, 1], // few
        ];
    }

    // =========================================================================
    // Rule 12: Romanian (nplurals=3)
    // =========================================================================

    /**
     * Tests Rule 12: Romanian with 3 forms.
     *
     * @param string $locale   The locale code.
     * @param int    $n        The number to test.
     * @param int    $expected The expected plural form index.
     *
     * @return void
     */
    #[DataProvider('rule12Provider')]
    public function testRule12Romanian(string $locale, int $n, int $expected): void
    {
        self::assertSame($expected, PluralRules::getCardinalFormIndex($locale, $n));
    }

    /**
     * @return array<array<string|int>>
     */
    public static function rule12Provider(): array
    {
        return [
            ['ro', 0, 1],   // few
            ['ro', 1, 0],   // one
            ['ro', 2, 1],   // few (n==0 or n%100 in 1..19)
            ['ro', 10, 1],  // few
            ['ro', 19, 1],  // few
            ['ro', 20, 2],  // other
            ['ro', 21, 2],  // other
            ['ro', 100, 2], // other
            ['ro', 101, 1], // few
            ['ro', 119, 1], // few
            ['ro', 120, 2], // other
        ];
    }

    // =========================================================================
    // Rule 13: Arabic (nplurals=6)
    // =========================================================================

    /**
     * Tests Rule 13: Arabic with 6 forms.
     *
     * @param string $locale   The locale code.
     * @param int    $n        The number to test.
     * @param int    $expected The expected plural form index.
     *
     * @return void
     */
    #[DataProvider('rule13Provider')]
    public function testRule13Arabic(string $locale, int $n, int $expected): void
    {
        self::assertSame($expected, PluralRules::getCardinalFormIndex($locale, $n));
    }

    /**
     * @return array<array<string|int>>
     */
    public static function rule13Provider(): array
    {
        return [
            // Arabic has 6 forms: zero, one, two, few, many, other
            ['ar', 0, 0],   // zero
            ['ar', 1, 1],   // one
            ['ar', 2, 2],   // two
            ['ar', 3, 3],   // few (3-10)
            ['ar', 4, 3],
            ['ar', 5, 3],
            ['ar', 10, 3],
            ['ar', 11, 4],  // many (11-99)
            ['ar', 25, 4],
            ['ar', 99, 4],
            ['ar', 100, 5], // other (100, 1000, etc.)
            ['ar', 101, 5],
            ['ar', 102, 5],
            ['ar', 103, 3], // few (n%100 in 3-10)
            ['ar', 110, 3],
            ['ar', 111, 4], // many (n%100 in 11-99)
            ['ar', 199, 4],
            ['ar', 200, 5], // other
        ];
    }


    // =========================================================================
    // Rule 14: Welsh (nplurals=6 - CLDR 48)
    // Category order: zero, one, two, few, many, other
    // =========================================================================

    /**
     * Tests Rule 14: Welsh with 6 forms (CLDR 48).
     *
     * @param string $locale   The locale code.
     * @param int    $n        The number to test.
     * @param int    $expected The expected plural form index.
     *
     * @return void
     */
    #[DataProvider('rule14Provider')]
    public function testRule14Welsh(string $locale, int $n, int $expected): void
    {
        self::assertSame($expected, PluralRules::getCardinalFormIndex($locale, $n));
    }

    /**
     * @return array<array<string|int>>
     */
    public static function rule14Provider(): array
    {
        return [
            // CLDR 48: zero=0, one=1, two=2, few=3, many=6, other=everything else
            ['cy', 0, 0],   // zero
            ['cy', 1, 1],   // one
            ['cy', 2, 2],   // two
            ['cy', 3, 3],   // few
            ['cy', 4, 5],   // other
            ['cy', 5, 5],   // other
            ['cy', 6, 4],   // many
            ['cy', 7, 5],   // other
            ['cy', 8, 5],   // other
            ['cy', 9, 5],   // other
            ['cy', 10, 5],  // other
            ['cy', 11, 5],  // other
            ['cy', 12, 5],  // other
            ['cy', 100, 5], // other
        ];
    }

    /**
     * Test Welsh (cy) plural rules explicitly to ensure all match branches are covered.
     * Welsh has 6 plural forms: zero, one, two, few, many, other
     *
     * // CLDR 48: zero=0, one=1, two=2, few=3, many=6, other=everything else
     */
    public function testWelshAllBranches(): void
    {
        // zero: n = 0
        self::assertSame(0, PluralRules::getCardinalFormIndex('cy', 0));

        // one: n = 1
        self::assertSame(1, PluralRules::getCardinalFormIndex('cy', 1));

        // two: n = 2
        self::assertSame(2, PluralRules::getCardinalFormIndex('cy', 2));

        // few: n = 3
        self::assertSame(3, PluralRules::getCardinalFormIndex('cy', 3));

        // many: n = 6
        self::assertSame(4, PluralRules::getCardinalFormIndex('cy', 6));

        // other: everything else (default branch)
        self::assertSame(5, PluralRules::getCardinalFormIndex('cy', 4));
        self::assertSame(5, PluralRules::getCardinalFormIndex('cy', 5));
        self::assertSame(5, PluralRules::getCardinalFormIndex('cy', 7));
        self::assertSame(5, PluralRules::getCardinalFormIndex('cy', 10));
        self::assertSame(5, PluralRules::getCardinalFormIndex('cy', 100));
    }


    // =========================================================================
    // Rule 15: Icelandic (nplurals=2)
    // =========================================================================

    /**
     * Tests Rule 15: Icelandic with 2 forms.
     *
     * @param string $locale   The locale code.
     * @param int    $n        The number to test.
     * @param int    $expected The expected plural form index.
     *
     * @return void
     */
    #[DataProvider('rule15Provider')]
    public function testRule15Icelandic(string $locale, int $n, int $expected): void
    {
        self::assertSame($expected, PluralRules::getCardinalFormIndex($locale, $n));
    }

    /**
     * @return array<array<string|int>>
     */
    public static function rule15Provider(): array
    {
        return [
            ['is', 0, 1],   // other
            ['is', 1, 0],   // one (n%10 == 1, n%100 != 11)
            ['is', 2, 1],   // other
            ['is', 10, 1],  // other
            ['is', 11, 1],  // other (special case)
            ['is', 21, 0],  // one
            ['is', 31, 0],  // one
            ['is', 100, 1], // other
            ['is', 101, 0], // one
            ['is', 111, 1], // other (special case)
        ];
    }

    // =========================================================================
    // Rule 16: Scottish Gaelic (nplurals=4)
    // =========================================================================

    /**
     * Tests Rule 16: Scottish Gaelic with 4 forms.
     *
     * @param string $locale   The locale code.
     * @param int    $n        The number to test.
     * @param int    $expected The expected plural form index.
     *
     * @return void
     */
    #[DataProvider('rule16Provider')]
    public function testRule16ScottishGaelic(string $locale, int $n, int $expected): void
    {
        self::assertSame($expected, PluralRules::getCardinalFormIndex($locale, $n));
    }

    /**
     * @return array<array<string|int>>
     */
    public static function rule16Provider(): array
    {
        return [
            ['gd', 1, 0],   // one (n == 1 or n == 11)
            ['gd', 11, 0],  // one
            ['gd', 2, 1],   // two (n == 2 or n == 12)
            ['gd', 12, 1],  // two
            ['gd', 3, 2],   // few (n > 2 and n < 20)
            ['gd', 10, 2],  // few
            ['gd', 19, 2],  // few
            ['gd', 0, 3],   // other
            ['gd', 20, 3],  // other
            ['gd', 21, 3],  // other
            ['gd', 100, 3], // other
        ];
    }

    // =========================================================================
    // Rule 17: Breton (nplurals=5 - CLDR 48)
    // Category order: one, two, few, many, other
    // =========================================================================

    /**
     * Tests Rule 17: Breton with 5 forms (CLDR 48).
     *
     * @param string $locale   The locale code.
     * @param int    $n        The number to test.
     * @param int    $expected The expected plural form index.
     *
     * @return void
     */
    #[DataProvider('rule17Provider')]
    public function testRule17Breton(string $locale, int $n, int $expected): void
    {
        self::assertSame($expected, PluralRules::getCardinalFormIndex($locale, $n));
    }

    /**
     * @return array<array<string|int>>
     */
    public static function rule17Provider(): array
    {
        return [
            // CLDR 48 Breton rules:
            // one: n % 10 = 1 and n % 100 not in 11,71,91
            // two: n % 10 = 2 and n % 100 not in 12,72,92
            // few: n % 10 in 3..4,9 and n % 100 not in 10..19,70..79,90..99
            // many: n != 0 and n % 1000000 = 0
            // other: everything else
            ['br', 1, 0],   // one
            ['br', 21, 0],  // one
            ['br', 31, 0],  // one
            ['br', 11, 4],  // other (n%100 = 11)
            ['br', 71, 4],  // other (n%100 = 71)
            ['br', 91, 4],  // other (n%100 = 91)
            ['br', 2, 1],   // two
            ['br', 22, 1],  // two
            ['br', 12, 4],  // other (n%100 = 12)
            ['br', 72, 4],  // other (n%100 = 72)
            ['br', 3, 2],   // few
            ['br', 4, 2],   // few
            ['br', 9, 2],   // few
            ['br', 13, 4],  // other (n%100 in 10..19)
            ['br', 0, 4],   // other
            ['br', 5, 4],   // other
            ['br', 1000000, 3], // many
        ];
    }

    // =========================================================================
    // Rule 18: Manx (nplurals=4 - CLDR 48)
    // Category order: one, two, few, other
    // =========================================================================

    /**
     * Tests Rule 18: Manx with 4 forms (CLDR 48).
     *
     * @param string $locale   The locale code.
     * @param int    $n        The number to test.
     * @param int    $expected The expected plural form index.
     *
     * @return void
     */
    #[DataProvider('rule18Provider')]
    public function testRule18Manx(string $locale, int $n, int $expected): void
    {
        self::assertSame($expected, PluralRules::getCardinalFormIndex($locale, $n));
    }

    /**
     * @return array<array<string|int>>
     */
    public static function rule18Provider(): array
    {
        return [
            // CLDR 48 Manx rules:
            // one: v = 0 and i % 10 = 1
            // two: v = 0 and i % 10 = 2
            // few: v = 0 and i % 20 = 0
            // other: everything else
            ['gv', 1, 0],   // one (n%10 = 1)
            ['gv', 11, 0],  // one
            ['gv', 21, 0],  // one
            ['gv', 2, 1],   // two (n%10 = 2)
            ['gv', 12, 1],  // two
            ['gv', 22, 1],  // two
            ['gv', 0, 2],   // few (n%20 = 0)
            ['gv', 20, 2],  // few
            ['gv', 40, 2],  // few
            ['gv', 3, 3],   // other
            ['gv', 5, 3],   // other
            ['gv', 10, 3],  // other
            ['gv', 15, 3],  // other
        ];
    }

    // =========================================================================
    // Rule 19: Hebrew (nplurals=4 - CLDR 48)
    // Category order: one, two, many, other
    // =========================================================================

    /**
     * Tests Rule 19: Hebrew with 4 forms (CLDR 48).
     *
     * @param string $locale   The locale code.
     * @param int    $n        The number to test.
     * @param int    $expected The expected plural form index.
     *
     * @return void
     */
    #[DataProvider('rule19Provider')]
    public function testRule19Hebrew(string $locale, int $n, int $expected): void
    {
        self::assertSame($expected, PluralRules::getCardinalFormIndex($locale, $n));
    }

    /**
     * @return array<array<string|int>>
     */
    public static function rule19Provider(): array
    {
        return [
            // CLDR 48 Hebrew rules:
            // one: i = 1 and v = 0
            // two: i = 2 and v = 0
            // many: v = 0 and n != 0..10 and n % 10 = 0
            // other: everything else
            ['he', 1, 0],   // one
            ['he', 2, 1],   // two
            ['he', 20, 2],  // many (n > 10 and n%10 = 0)
            ['he', 30, 2],  // many
            ['he', 100, 2], // many
            ['he', 0, 3],   // other
            ['he', 3, 3],   // other
            ['he', 10, 3],  // other (n = 10 doesn't match n > 10)
            ['he', 11, 3],  // other
            ['he', 15, 3],  // other
            ['he', 21, 3],  // other
        ];
    }

    // =========================================================================
    // Rule 20: Italian, Spanish, French, Portuguese, Catalan (nplurals=3 - CLDR 49)
    // Category order: one, many, other
    // =========================================================================

    /**
     * Tests Rule 20: Italian, Spanish, French, Portuguese, Catalan with 3 forms (CLDR 49).
     *
     * @param string $locale   The locale code.
     * @param int    $n        The number to test.
     * @param int    $expected The expected plural form index.
     *
     * @return void
     */
    #[DataProvider('rule20Provider')]
    public function testRule20OneManyOther(string $locale, int $n, int $expected): void
    {
        self::assertSame($expected, PluralRules::getCardinalFormIndex($locale, $n));
    }

    /**
     * @return array<array<string|int>>
     */
    public static function rule20Provider(): array
    {
        return [
            // CLDR 49 rules for Italian, Spanish, French, Portuguese, Catalan:
            // one: i = 1 and v = 0
            // many: e = 0 and i != 0 and i % 1000000 = 0 and v = 0
            // other: everything else

            // Italian
            ['it', 1, 0],         // one
            ['it', 0, 2],         // other
            ['it', 2, 2],         // other
            ['it', 5, 2],         // other
            ['it', 10, 2],        // other
            ['it', 100, 2],       // other
            ['it', 1000, 2],      // other
            ['it', 1000000, 1],   // many (1 million)
            ['it', 2000000, 1],   // many (2 million)
            ['it', 3000000, 1],   // many (3 million)
            ['it', 1000001, 2],   // other

            // Spanish
            ['es', 1, 0],         // one
            ['es', 0, 2],         // other
            ['es', 2, 2],         // other
            ['es', 1000000, 1],   // many

            // French
            ['fr', 1, 0],         // one
            ['fr', 0, 2],         // other
            ['fr', 2, 2],         // other
            ['fr', 1000000, 1],   // many

            // Portuguese
            ['pt', 1, 0],         // one
            ['pt', 0, 2],         // other
            ['pt', 2, 2],         // other
            ['pt', 1000000, 1],   // many

            // Catalan
            ['ca', 1, 0],         // one
            ['ca', 0, 2],         // other
            ['ca', 2, 2],         // other
            ['ca', 1000000, 1],   // many
        ];
    }

    // =========================================================================
    // Locale Fallback Tests
    // =========================================================================

    public function testLocaleFallbackWithHyphen(): void
    {
        // Should extract 'en' from 'en-US' and use English rules
        self::assertSame(0, PluralRules::getCardinalFormIndex('en-US', 1));
        self::assertSame(1, PluralRules::getCardinalFormIndex('en-US', 2));

        // Should extract 'fr' from 'fr-CA' and use French rules (CLDR 49: one/many/other)
        self::assertSame(2, PluralRules::getCardinalFormIndex('fr-CA', 0));  // other
        self::assertSame(0, PluralRules::getCardinalFormIndex('fr-CA', 1));  // one
        self::assertSame(2, PluralRules::getCardinalFormIndex('fr-CA', 2));  // other

        // Should extract 'ar' from 'ar-SA' and use Arabic rules
        self::assertSame(0, PluralRules::getCardinalFormIndex('ar-SA', 0));
        self::assertSame(1, PluralRules::getCardinalFormIndex('ar-SA', 1));
        self::assertSame(2, PluralRules::getCardinalFormIndex('ar-SA', 2));
    }

    public function testLocaleFallbackWithUnderscore(): void
    {
        // Should extract 'en' from 'en_GB' and use English rules
        self::assertSame(0, PluralRules::getCardinalFormIndex('en_GB', 1));
        self::assertSame(1, PluralRules::getCardinalFormIndex('en_GB', 2));

        // Should extract 'de' from 'de_AT' and use German rules
        self::assertSame(0, PluralRules::getCardinalFormIndex('de_AT', 1));
        self::assertSame(1, PluralRules::getCardinalFormIndex('de_AT', 2));

        // Should extract 'ru' from 'ru_RU' and use Russian rules
        self::assertSame(0, PluralRules::getCardinalFormIndex('ru_RU', 1));
        self::assertSame(1, PluralRules::getCardinalFormIndex('ru_RU', 2));
        self::assertSame(2, PluralRules::getCardinalFormIndex('ru_RU', 5));
    }

    public function testLocaleIsCaseInsensitive(): void
    {
        // Uppercase should work
        self::assertSame(0, PluralRules::getCardinalFormIndex('EN', 1));
        self::assertSame(1, PluralRules::getCardinalFormIndex('EN', 2));

        // Mixed case should work
        self::assertSame(0, PluralRules::getCardinalFormIndex('En', 1));
        self::assertSame(0, PluralRules::getCardinalFormIndex('eN', 1));

        // Uppercase with region
        self::assertSame(0, PluralRules::getCardinalFormIndex('EN-US', 1));
        self::assertSame(0, PluralRules::getCardinalFormIndex('FR-FR', 1));
    }

    // =========================================================================
    // Unknown Locale Tests
    // =========================================================================

    public function testUnknownLocaleReturnsZero(): void
    {
        // Unknown locale should return 0 (no plural forms behavior)
        self::assertSame(0, PluralRules::getCardinalFormIndex('xyz', 1));
        self::assertSame(0, PluralRules::getCardinalFormIndex('xyz', 2));
        self::assertSame(0, PluralRules::getCardinalFormIndex('xyz', 100));

        // Unknown locale with region
        self::assertSame(0, PluralRules::getCardinalFormIndex('xyz-ZZ', 1));
        self::assertSame(0, PluralRules::getCardinalFormIndex('xyz_ZZ', 1));
    }

    // =========================================================================
    // Edge Cases and Boundary Tests
    // =========================================================================

    public function testLargeNumbers(): void
    {
        // English - large numbers
        self::assertSame(1, PluralRules::getCardinalFormIndex('en', 1000000));
        self::assertSame(1, PluralRules::getCardinalFormIndex('en', PHP_INT_MAX));

        // Russian - large numbers should still follow rules
        self::assertSame(0, PluralRules::getCardinalFormIndex('ru', 1000001)); // ends in 1, not 11
        self::assertSame(1, PluralRules::getCardinalFormIndex('ru', 1000002)); // ends in 2
        self::assertSame(2, PluralRules::getCardinalFormIndex('ru', 1000005)); // ends in 5
        self::assertSame(2, PluralRules::getCardinalFormIndex('ru', 1000011)); // ends in 11
    }

    public function testZero(): void
    {
        // Different languages handle zero differently
        self::assertSame(1, PluralRules::getCardinalFormIndex('en', 0));  // "0 items" (plural)
        self::assertSame(2, PluralRules::getCardinalFormIndex('fr', 0));  // "0 éléments" (other in French - CLDR 49)
        self::assertSame(0, PluralRules::getCardinalFormIndex('ar', 0));  // "zero" form
        self::assertSame(0, PluralRules::getCardinalFormIndex('ja', 0));  // no plural
    }

    // =========================================================================
    // Specific Language Code Tests (ISO 639-1 and ISO 639-3)
    // =========================================================================

    public function testThreeLetterLanguageCodes(): void
    {
        // Acehnese (ace) - rule 0
        self::assertSame(0, PluralRules::getCardinalFormIndex('ace', 1));
        self::assertSame(0, PluralRules::getCardinalFormIndex('ace', 2));

        // Asturian (ast) - rule 1
        self::assertSame(0, PluralRules::getCardinalFormIndex('ast', 1));
        self::assertSame(1, PluralRules::getCardinalFormIndex('ast', 2));

        // Filipino (fil) - rule 2
        self::assertSame(0, PluralRules::getCardinalFormIndex('fil', 0));
        self::assertSame(0, PluralRules::getCardinalFormIndex('fil', 1));
        self::assertSame(1, PluralRules::getCardinalFormIndex('fil', 2));
    }

    // =========================================================================
    // Comprehensive Comparison Tests
    // =========================================================================

    public function testRussianVsPolish(): void
    {
        // Russian and Polish have similar but different rules
        // For n=21: Russian returns 0 (one), Polish returns 2 (many)
        self::assertSame(0, PluralRules::getCardinalFormIndex('ru', 21));
        self::assertSame(2, PluralRules::getCardinalFormIndex('pl', 21));

        // For n=101: Russian returns 0 (one), Polish returns 2 (many)
        self::assertSame(0, PluralRules::getCardinalFormIndex('ru', 101));
        self::assertSame(2, PluralRules::getCardinalFormIndex('pl', 101));

        // Both agree on n=2, n=3, n=4
        self::assertSame(1, PluralRules::getCardinalFormIndex('ru', 2));
        self::assertSame(1, PluralRules::getCardinalFormIndex('pl', 2));
    }

    public function testCzechVsRussian(): void
    {
        // Czech and Russian differ significantly
        // For n=21: Russian returns 0 (one), Czech returns 2 (other)
        self::assertSame(0, PluralRules::getCardinalFormIndex('ru', 21));
        self::assertSame(2, PluralRules::getCardinalFormIndex('cs', 21));

        // Both use "few" for 2-4
        self::assertSame(1, PluralRules::getCardinalFormIndex('ru', 2));
        self::assertSame(1, PluralRules::getCardinalFormIndex('cs', 2));
    }

    public function testEnglishVsFrench(): void
    {
        // French (CLDR 49): Rule 20 - one (n=1), many (n=millions), other (everything else)
        // English: Rule 1 - one (n=1), other (n!=1)

        // Zero: English=other(1), French=other(2)
        self::assertSame(1, PluralRules::getCardinalFormIndex('en', 0));
        self::assertSame(2, PluralRules::getCardinalFormIndex('fr', 0));

        // Both treat 1 as 'one' (index 0)
        self::assertSame(0, PluralRules::getCardinalFormIndex('en', 1));
        self::assertSame(0, PluralRules::getCardinalFormIndex('fr', 1));

        // 2+: English=other(1), French=other(2)
        self::assertSame(1, PluralRules::getCardinalFormIndex('en', 2));
        self::assertSame(2, PluralRules::getCardinalFormIndex('fr', 2));

        // Millions: French has 'many' category
        self::assertSame(1, PluralRules::getCardinalFormIndex('en', 1000000));  // other
        self::assertSame(1, PluralRules::getCardinalFormIndex('fr', 1000000));  // many
    }

    // =========================================================================
    // getCategoryName() Tests
    // =========================================================================

    #[DataProvider('getCategoryNameProvider')]
    public function testGetCategoryName(string $locale, int $n, string $expected): void
    {
        self::assertSame($expected, PluralRules::getCardinalCategoryName($locale, $n));
    }

    /**
     * @return array<array<string|int>>
     */
    public static function getCategoryNameProvider(): array
    {
        return [
            // Rule 0: No plural forms - always "other"
            ['ja', 0, PluralRules::CATEGORY_OTHER],
            ['ja', 1, PluralRules::CATEGORY_OTHER],
            ['zh', 5, PluralRules::CATEGORY_OTHER],
            ['ko', 100, PluralRules::CATEGORY_OTHER],

            // Rule 1: Two forms (n != 1) - "one" or "other"
            ['en', 1, PluralRules::CATEGORY_ONE],
            ['en', 0, PluralRules::CATEGORY_OTHER],
            ['en', 2, PluralRules::CATEGORY_OTHER],
            ['de', 1, PluralRules::CATEGORY_ONE],
            ['de', 5, PluralRules::CATEGORY_OTHER],

            // Rule 20: Three forms (one, many, other) - CLDR 49
            ['fr', 0, PluralRules::CATEGORY_OTHER],
            ['fr', 1, PluralRules::CATEGORY_ONE],
            ['fr', 2, PluralRules::CATEGORY_OTHER],
            ['fr', 1000000, PluralRules::CATEGORY_MANY],
            ['pt', 1, PluralRules::CATEGORY_ONE],
            ['pt', 2, PluralRules::CATEGORY_OTHER],
            ['pt', 1000000, PluralRules::CATEGORY_MANY],

            // Rule 3: Slavic - "one", "few", "many"
            ['ru', 1, PluralRules::CATEGORY_ONE],
            ['ru', 21, PluralRules::CATEGORY_ONE],
            ['ru', 2, PluralRules::CATEGORY_FEW],
            ['ru', 3, PluralRules::CATEGORY_FEW],
            ['ru', 4, PluralRules::CATEGORY_FEW],
            ['ru', 5, PluralRules::CATEGORY_MANY],
            ['ru', 11, PluralRules::CATEGORY_MANY],
            ['ru', 20, PluralRules::CATEGORY_MANY],

            // Rule 4: Czech/Slovak - "one", "few", "other"
            ['cs', 1, PluralRules::CATEGORY_ONE],
            ['cs', 2, PluralRules::CATEGORY_FEW],
            ['cs', 4, PluralRules::CATEGORY_FEW],
            ['cs', 5, PluralRules::CATEGORY_OTHER],
            ['sk', 1, PluralRules::CATEGORY_ONE],

            // Rule 5: Irish - "one", "two", "few", "many", "other"
            ['ga', 1, PluralRules::CATEGORY_ONE],
            ['ga', 2, PluralRules::CATEGORY_TWO],
            ['ga', 3, PluralRules::CATEGORY_FEW],
            ['ga', 6, PluralRules::CATEGORY_FEW],
            ['ga', 7, PluralRules::CATEGORY_MANY],
            ['ga', 10, PluralRules::CATEGORY_MANY],
            ['ga', 11, PluralRules::CATEGORY_OTHER],

            // Rule 6: Lithuanian - "one", "few", "other"
            ['lt', 1, PluralRules::CATEGORY_ONE],
            ['lt', 21, PluralRules::CATEGORY_ONE],
            ['lt', 2, PluralRules::CATEGORY_FEW],
            ['lt', 9, PluralRules::CATEGORY_FEW],
            ['lt', 10, PluralRules::CATEGORY_OTHER],
            ['lt', 11, PluralRules::CATEGORY_OTHER],

            // Rule 7: Slovenian - "one", "two", "few", "other"
            ['sl', 1, PluralRules::CATEGORY_ONE],
            ['sl', 101, PluralRules::CATEGORY_ONE],
            ['sl', 2, PluralRules::CATEGORY_TWO],
            ['sl', 102, PluralRules::CATEGORY_TWO],
            ['sl', 3, PluralRules::CATEGORY_FEW],
            ['sl', 4, PluralRules::CATEGORY_FEW],
            ['sl', 5, PluralRules::CATEGORY_OTHER],

            // Rule 8: Macedonian - "one", "other" (CLDR 48)
            ['mk', 1, PluralRules::CATEGORY_ONE],
            ['mk', 21, PluralRules::CATEGORY_ONE],
            ['mk', 2, PluralRules::CATEGORY_OTHER],
            ['mk', 22, PluralRules::CATEGORY_OTHER],
            ['mk', 3, PluralRules::CATEGORY_OTHER],
            ['mk', 11, PluralRules::CATEGORY_OTHER],

            // Rule 9: Maltese - "one", "few", "many", "other"
            ['mt', 1, PluralRules::CATEGORY_ONE],
            ['mt', 0, PluralRules::CATEGORY_FEW],
            ['mt', 2, PluralRules::CATEGORY_FEW],
            ['mt', 10, PluralRules::CATEGORY_FEW],
            ['mt', 11, PluralRules::CATEGORY_MANY],
            ['mt', 19, PluralRules::CATEGORY_MANY],
            ['mt', 20, PluralRules::CATEGORY_OTHER],

            // Rule 10: Latvian - "zero", "one", "other" (CLDR 48)
            ['lv', 0, PluralRules::CATEGORY_ZERO],
            ['lv', 1, PluralRules::CATEGORY_ONE],
            ['lv', 21, PluralRules::CATEGORY_ONE],
            ['lv', 2, PluralRules::CATEGORY_OTHER],
            ['lv', 11, PluralRules::CATEGORY_OTHER],

            // Rule 11: Polish - "one", "few", "many"
            ['pl', 1, PluralRules::CATEGORY_ONE],
            ['pl', 2, PluralRules::CATEGORY_FEW],
            ['pl', 4, PluralRules::CATEGORY_FEW],
            ['pl', 5, PluralRules::CATEGORY_MANY],
            ['pl', 21, PluralRules::CATEGORY_MANY],

            // Rule 12: Romanian - "one", "few", "other"
            ['ro', 1, PluralRules::CATEGORY_ONE],
            ['ro', 0, PluralRules::CATEGORY_FEW],
            ['ro', 19, PluralRules::CATEGORY_FEW],
            ['ro', 20, PluralRules::CATEGORY_OTHER],

            // Rule 13: Arabic - "zero", "one", "two", "few", "many", "other"
            ['ar', 0, PluralRules::CATEGORY_ZERO],
            ['ar', 1, PluralRules::CATEGORY_ONE],
            ['ar', 2, PluralRules::CATEGORY_TWO],
            ['ar', 3, PluralRules::CATEGORY_FEW],
            ['ar', 10, PluralRules::CATEGORY_FEW],
            ['ar', 11, PluralRules::CATEGORY_MANY],
            ['ar', 99, PluralRules::CATEGORY_MANY],
            ['ar', 100, PluralRules::CATEGORY_OTHER],

            // Rule 14: Welsh - "zero", "one", "two", "few", "many", "other" (CLDR 48)
            ['cy', 0, PluralRules::CATEGORY_ZERO],
            ['cy', 1, PluralRules::CATEGORY_ONE],
            ['cy', 2, PluralRules::CATEGORY_TWO],
            ['cy', 3, PluralRules::CATEGORY_FEW],
            ['cy', 6, PluralRules::CATEGORY_MANY],
            ['cy', 8, PluralRules::CATEGORY_OTHER],
            ['cy', 11, PluralRules::CATEGORY_OTHER],

            // Rule 15: Icelandic - "one", "other"
            ['is', 1, PluralRules::CATEGORY_ONE],
            ['is', 21, PluralRules::CATEGORY_ONE],
            ['is', 2, PluralRules::CATEGORY_OTHER],
            ['is', 11, PluralRules::CATEGORY_OTHER],

            // Rule 16: Scottish Gaelic - "one", "two", "few", "other"
            ['gd', 1, PluralRules::CATEGORY_ONE],
            ['gd', 11, PluralRules::CATEGORY_ONE],
            ['gd', 2, PluralRules::CATEGORY_TWO],
            ['gd', 12, PluralRules::CATEGORY_TWO],
            ['gd', 3, PluralRules::CATEGORY_FEW],
            ['gd', 19, PluralRules::CATEGORY_FEW],
            ['gd', 20, PluralRules::CATEGORY_OTHER],

            // Unknown locale - defaults to rule 0, returns "other"
            ['xyz', 1, PluralRules::CATEGORY_OTHER],
            ['unknown', 5, PluralRules::CATEGORY_OTHER],
        ];
    }

    // =========================================================================
    // getCategories() Tests
    // =========================================================================

    /**
     * @param array<string> $expected
     */
    #[DataProvider('getCategoriesProvider')]
    public function testGetCategories(string $locale, array $expected): void
    {
        self::assertSame($expected, PluralRules::getCardinalCategories($locale));
    }

    /**
     * @return array<array{string, array<string>}>
     */
    public static function getCategoriesProvider(): array
    {
        return [
            // Rule 0: No plural forms
            ['ja', [PluralRules::CATEGORY_OTHER]],
            ['zh', [PluralRules::CATEGORY_OTHER]],
            ['ko', [PluralRules::CATEGORY_OTHER]],
            ['vi', [PluralRules::CATEGORY_OTHER]],

            // Rule 1: Two forms (n != 1)
            ['en', [PluralRules::CATEGORY_ONE, PluralRules::CATEGORY_OTHER]],
            ['de', [PluralRules::CATEGORY_ONE, PluralRules::CATEGORY_OTHER]],

            // Rule 20: Three forms (one, many, other) - CLDR 49
            ['es', [PluralRules::CATEGORY_ONE, PluralRules::CATEGORY_MANY, PluralRules::CATEGORY_OTHER]],
            ['it', [PluralRules::CATEGORY_ONE, PluralRules::CATEGORY_MANY, PluralRules::CATEGORY_OTHER]],
            ['fr', [PluralRules::CATEGORY_ONE, PluralRules::CATEGORY_MANY, PluralRules::CATEGORY_OTHER]],
            ['pt', [PluralRules::CATEGORY_ONE, PluralRules::CATEGORY_MANY, PluralRules::CATEGORY_OTHER]],
            ['ca', [PluralRules::CATEGORY_ONE, PluralRules::CATEGORY_MANY, PluralRules::CATEGORY_OTHER]],

            // Rule 3: Slavic
            ['ru', [PluralRules::CATEGORY_ONE, PluralRules::CATEGORY_FEW, PluralRules::CATEGORY_MANY, PluralRules::CATEGORY_OTHER]],
            ['uk', [PluralRules::CATEGORY_ONE, PluralRules::CATEGORY_FEW, PluralRules::CATEGORY_MANY, PluralRules::CATEGORY_OTHER]],
            ['sr', [PluralRules::CATEGORY_ONE, PluralRules::CATEGORY_FEW, PluralRules::CATEGORY_MANY, PluralRules::CATEGORY_OTHER]],

            // Rule 4: Czech/Slovak
            ['cs', [PluralRules::CATEGORY_ONE, PluralRules::CATEGORY_FEW, PluralRules::CATEGORY_OTHER]],
            ['sk', [PluralRules::CATEGORY_ONE, PluralRules::CATEGORY_FEW, PluralRules::CATEGORY_OTHER]],

            // Rule 5: Irish
            [
                'ga',
                [
                    PluralRules::CATEGORY_ONE,
                    PluralRules::CATEGORY_TWO,
                    PluralRules::CATEGORY_FEW,
                    PluralRules::CATEGORY_MANY,
                    PluralRules::CATEGORY_OTHER
                ]
            ],

            // Rule 6: Lithuanian
            ['lt', [PluralRules::CATEGORY_ONE, PluralRules::CATEGORY_FEW, PluralRules::CATEGORY_OTHER]],

            // Rule 7: Slovenian
            [
                'sl',
                [
                    PluralRules::CATEGORY_ONE,
                    PluralRules::CATEGORY_TWO,
                    PluralRules::CATEGORY_FEW,
                    PluralRules::CATEGORY_OTHER
                ]
            ],

            // Rule 8: Macedonian (CLDR 48)
            ['mk', [PluralRules::CATEGORY_ONE, PluralRules::CATEGORY_OTHER]],

            // Rule 9: Maltese
            [
                'mt',
                [
                    PluralRules::CATEGORY_ONE,
                    PluralRules::CATEGORY_FEW,
                    PluralRules::CATEGORY_MANY,
                    PluralRules::CATEGORY_OTHER
                ]
            ],

            // Rule 10: Latvian (CLDR 48)
            ['lv', [PluralRules::CATEGORY_ZERO, PluralRules::CATEGORY_ONE, PluralRules::CATEGORY_OTHER]],

            // Rule 11: Polish
            ['pl', [PluralRules::CATEGORY_ONE, PluralRules::CATEGORY_FEW, PluralRules::CATEGORY_MANY, PluralRules::CATEGORY_OTHER]],

            // Rule 12: Romanian
            ['ro', [PluralRules::CATEGORY_ONE, PluralRules::CATEGORY_FEW, PluralRules::CATEGORY_OTHER]],

            // Rule 13: Arabic
            [
                'ar',
                [
                    PluralRules::CATEGORY_ZERO,
                    PluralRules::CATEGORY_ONE,
                    PluralRules::CATEGORY_TWO,
                    PluralRules::CATEGORY_FEW,
                    PluralRules::CATEGORY_MANY,
                    PluralRules::CATEGORY_OTHER
                ]
            ],

            // Rule 14: Welsh (CLDR 48)
            [
                'cy',
                [
                    PluralRules::CATEGORY_ZERO,
                    PluralRules::CATEGORY_ONE,
                    PluralRules::CATEGORY_TWO,
                    PluralRules::CATEGORY_FEW,
                    PluralRules::CATEGORY_MANY,
                    PluralRules::CATEGORY_OTHER
                ]
            ],

            // Rule 15: Icelandic
            ['is', [PluralRules::CATEGORY_ONE, PluralRules::CATEGORY_OTHER]],

            // Rule 16: Scottish Gaelic
            [
                'gd',
                [
                    PluralRules::CATEGORY_ONE,
                    PluralRules::CATEGORY_TWO,
                    PluralRules::CATEGORY_FEW,
                    PluralRules::CATEGORY_OTHER
                ]
            ],

            // Rule 17: Breton (CLDR 48)
            [
                'br',
                [
                    PluralRules::CATEGORY_ONE,
                    PluralRules::CATEGORY_TWO,
                    PluralRules::CATEGORY_FEW,
                    PluralRules::CATEGORY_MANY,
                    PluralRules::CATEGORY_OTHER
                ]
            ],

            // Rule 18: Manx (CLDR 48)
            [
                'gv',
                [
                    PluralRules::CATEGORY_ONE,
                    PluralRules::CATEGORY_TWO,
                    PluralRules::CATEGORY_FEW,
                    PluralRules::CATEGORY_OTHER
                ]
            ],

            // Rule 19: Hebrew (CLDR 48)
            [
                'he',
                [
                    PluralRules::CATEGORY_ONE,
                    PluralRules::CATEGORY_TWO,
                    PluralRules::CATEGORY_MANY,
                    PluralRules::CATEGORY_OTHER
                ]
            ],

            // Unknown locale - defaults to rule 0
            ['xyz', [PluralRules::CATEGORY_OTHER]],
            ['unknown', [PluralRules::CATEGORY_OTHER]],
        ];
    }

    // =========================================================================
    // Locale Variant Tests for New Methods
    // =========================================================================

    public function testGetCategoryNameWithLocaleVariants(): void
    {
        // Test with underscore separator
        self::assertSame(PluralRules::CATEGORY_ONE, PluralRules::getCardinalCategoryName('en_US', 1));
        self::assertSame(PluralRules::CATEGORY_OTHER, PluralRules::getCardinalCategoryName('en_US', 2));
        self::assertSame(
            PluralRules::CATEGORY_OTHER,
            PluralRules::getCardinalCategoryName('fr_FR', 0)
        );  // CLDR 49: 0 is 'other'
        self::assertSame(PluralRules::CATEGORY_OTHER, PluralRules::getCardinalCategoryName('fr_FR', 2));

        // Test with hyphen separator
        self::assertSame(PluralRules::CATEGORY_ONE, PluralRules::getCardinalCategoryName('en-GB', 1));
        self::assertSame(PluralRules::CATEGORY_OTHER, PluralRules::getCardinalCategoryName('en-GB', 5));
        self::assertSame(PluralRules::CATEGORY_FEW, PluralRules::getCardinalCategoryName('ru-RU', 2));

        // Test case insensitivity
        self::assertSame(PluralRules::CATEGORY_ONE, PluralRules::getCardinalCategoryName('EN', 1));
        self::assertSame(PluralRules::CATEGORY_ONE, PluralRules::getCardinalCategoryName('En_Us', 1));
    }

    public function testGetCategoriesWithLocaleVariants(): void
    {
        // Test with underscore separator
        self::assertSame(
            [PluralRules::CATEGORY_ONE, PluralRules::CATEGORY_OTHER],
            PluralRules::getCardinalCategories('en_US')
        );
        self::assertSame(
            [PluralRules::CATEGORY_ONE, PluralRules::CATEGORY_FEW, PluralRules::CATEGORY_MANY, PluralRules::CATEGORY_OTHER],
            PluralRules::getCardinalCategories('ru_RU')
        );

        // Test with hyphen separator
        self::assertSame(
            [PluralRules::CATEGORY_ONE, PluralRules::CATEGORY_OTHER],
            PluralRules::getCardinalCategories('en-GB')
        );

        // Test case insensitivity
        self::assertSame(
            [PluralRules::CATEGORY_ONE, PluralRules::CATEGORY_OTHER],
            PluralRules::getCardinalCategories('EN')
        );
    }

    // =========================================================================
    // Constants Tests
    // =========================================================================

    public function testCategoryConstants(): void
    {
        self::assertSame('zero', PluralRules::CATEGORY_ZERO);
        self::assertSame('one', PluralRules::CATEGORY_ONE);
        self::assertSame('two', PluralRules::CATEGORY_TWO);
        self::assertSame('few', PluralRules::CATEGORY_FEW);
        self::assertSame('many', PluralRules::CATEGORY_MANY);
        self::assertSame('other', PluralRules::CATEGORY_OTHER);
    }

// =========================================================================
// getPluralCount() Tests
// =========================================================================

    #[DataProvider('getPluralCountProvider')]
    public function testGetPluralCount(string $locale, int $expected): void
    {
        self::assertSame($expected, PluralRules::getPluralCount($locale));
    }

    /**
     * @return array<array{string, int}>
     */
    public static function getPluralCountProvider(): array
    {
        return [
            // Rule 0: No plural forms (nplurals=1)
            ['ja', 1],
            ['zh', 1],
            ['ko', 1],
            ['vi', 1],
            ['th', 1],
            ['id', 1],

            // Rule 1: Two forms (nplurals=2)
            ['en', 2],
            ['de', 2],
            ['nl', 2],
            ['sv', 2],
            ['nb', 2],
            ['da', 2],
            ['el', 2],
            ['hu', 2],
            ['fi', 2],
            ['et', 2],
            ['bg', 2],

            // Rule 2: Two forms (nplurals=2)
            ['fil', 2],
            ['tr', 2],
            ['oc', 2],
            ['ti', 2],
            ['ln', 2],

            // Rule 20: Three forms - one, many, other (nplurals=3) - CLDR 49
            ['it', 3],
            ['es', 3],
            ['fr', 3],
            ['pt', 3],
            ['ca', 3],

            // Rule 3: Slavic (nplurals=4)
            ['ru', 4],
            ['uk', 4],
            ['sr', 4],
            ['hr', 4],
            ['be', 4],
            ['bs', 4],

            // Rule 4: Czech/Slovak (nplurals=3)
            ['cs', 3],
            ['sk', 3],

            // Rule 5: Irish (nplurals=5)
            ['ga', 5],

            // Rule 6: Lithuanian (nplurals=3)
            ['lt', 3],

            // Rule 7: Slovenian (nplurals=4)
            ['sl', 4],

            // Rule 8: Macedonian (nplurals=2)
            ['mk', 2],

            // Rule 9: Maltese (nplurals=4)
            ['mt', 4],

            // Rule 10: Latvian (nplurals=3)
            ['lv', 3],

            // Rule 11: Polish (nplurals=4)
            ['pl', 4],

            // Rule 12: Romanian (nplurals=3)
            ['ro', 3],

            // Rule 13: Arabic (nplurals=6)
            ['ar', 6],

            // Rule 14: Welsh (nplurals=6)
            ['cy', 6],

            // Rule 15: Icelandic (nplurals=2)
            ['is', 2],

            // Rule 16: Scottish Gaelic (nplurals=4)
            ['gd', 4],

            // Rule 17: Breton (nplurals=5)
            ['br', 5],

            // Rule 18: Manx (nplurals=4)
            ['gv', 4],

            // Rule 19: Hebrew (nplurals=4)
            ['he', 4],

            // Unknown locale - defaults to rule 0 (nplurals=1)
            ['xyz', 1],
            ['unknown', 1],
        ];
    }

    public function testGetPluralCountWithLocaleVariants(): void
    {
        // Test with underscore separator
        self::assertSame(2, PluralRules::getPluralCount('en_US'));
        self::assertSame(3, PluralRules::getPluralCount('fr_FR'));  // CLDR 49: one/many/other
        self::assertSame(4, PluralRules::getPluralCount('ru_RU'));

        // Test with hyphen separator
        self::assertSame(2, PluralRules::getPluralCount('en-GB'));
        self::assertSame(2, PluralRules::getPluralCount('de-AT'));
        self::assertSame(6, PluralRules::getPluralCount('ar-SA'));

        // Test case insensitivity
        self::assertSame(2, PluralRules::getPluralCount('EN'));
        self::assertSame(2, PluralRules::getPluralCount('En'));
        self::assertSame(2, PluralRules::getPluralCount('EN-US'));
    }

    public function testGetPluralCountConsistencyWithGetCategories(): void
    {
        $locales = ['en', 'fr', 'ru', 'pl', 'ar', 'ja', 'ga', 'cy', 'br', 'he'];

        foreach ($locales as $locale) {
            self::assertSame(
                count(PluralRules::getCardinalCategories($locale)),
                PluralRules::getPluralCount($locale),
                "getPluralCount should equal count of getCategories for locale: $locale"
            );
        }
    }

    public function testGetPluralCountBoundaries(): void
    {
        // Minimum: 1 (Asian languages)
        self::assertSame(1, PluralRules::getPluralCount('ja'));
        self::assertSame(1, PluralRules::getPluralCount('zh'));

        // Maximum: 6 (Arabic, Welsh)
        self::assertSame(6, PluralRules::getPluralCount('ar'));
        self::assertSame(6, PluralRules::getPluralCount('cy'));
    }

    // =========================================================================
    // Ordinal Categories Tests
    // =========================================================================

    /**
     * Test getOrdinalCategories for languages with only "other" ordinal form (Rule 0)
     */
    public function testOrdinalCategoriesRuleZeroOnlyOther(): void
    {
        // Asian languages - no ordinal distinction
        self::assertSame([PluralRules::CATEGORY_OTHER], PluralRules::getOrdinalCategories('ja'));
        self::assertSame([PluralRules::CATEGORY_OTHER], PluralRules::getOrdinalCategories('zh'));
        self::assertSame([PluralRules::CATEGORY_OTHER], PluralRules::getOrdinalCategories('ko'));
        self::assertSame([PluralRules::CATEGORY_OTHER], PluralRules::getOrdinalCategories('th'));

        // Slavic languages - most use only "other" for ordinals
        self::assertSame([PluralRules::CATEGORY_OTHER], PluralRules::getOrdinalCategories('ru'));
        self::assertSame([PluralRules::CATEGORY_OTHER], PluralRules::getOrdinalCategories('pl'));
        self::assertSame([PluralRules::CATEGORY_OTHER], PluralRules::getOrdinalCategories('cs'));
        self::assertSame([PluralRules::CATEGORY_OTHER], PluralRules::getOrdinalCategories('sk'));

        // Other languages with no ordinal distinction
        self::assertSame([PluralRules::CATEGORY_OTHER], PluralRules::getOrdinalCategories('de'));
        self::assertSame([PluralRules::CATEGORY_OTHER], PluralRules::getOrdinalCategories('nl'));
        self::assertSame([PluralRules::CATEGORY_OTHER], PluralRules::getOrdinalCategories('lt'));
        self::assertSame([PluralRules::CATEGORY_OTHER], PluralRules::getOrdinalCategories('lv'));
    }

    /**
     * Test getOrdinalCategories for English-like ordinals (Rule 1: one/two/few/other)
     * Pattern: 1st, 2nd, 3rd, 4th...
     */
    public function testOrdinalCategoriesRuleOneEnglishLike(): void
    {
        $expected = [
            PluralRules::CATEGORY_ONE,
            PluralRules::CATEGORY_TWO,
            PluralRules::CATEGORY_FEW,
            PluralRules::CATEGORY_OTHER
        ];

        self::assertSame($expected, PluralRules::getOrdinalCategories('en'));
        self::assertSame($expected, PluralRules::getOrdinalCategories('en-US'));
        self::assertSame($expected, PluralRules::getOrdinalCategories('en-GB'));
    }

    /**
     * Test getOrdinalCategories for French-like ordinals (Rule 2: one/other)
     * Pattern: 1er, 2e, 3e...
     */
    public function testOrdinalCategoriesRuleTwoFrenchLike(): void
    {
        $expected = [PluralRules::CATEGORY_ONE, PluralRules::CATEGORY_OTHER];

        // French
        self::assertSame($expected, PluralRules::getOrdinalCategories('fr'));
        self::assertSame($expected, PluralRules::getOrdinalCategories('fr-FR'));
        self::assertSame($expected, PluralRules::getOrdinalCategories('fr-CA'));

        // Catalan
        self::assertSame($expected, PluralRules::getOrdinalCategories('ca'));

        // Filipino
        self::assertSame($expected, PluralRules::getOrdinalCategories('fil'));

        // Swedish
        self::assertSame($expected, PluralRules::getOrdinalCategories('sv'));

        // Vietnamese
        self::assertSame($expected, PluralRules::getOrdinalCategories('vi'));

        // Romanian
        self::assertSame($expected, PluralRules::getOrdinalCategories('ro'));

        // Armenian
        self::assertSame($expected, PluralRules::getOrdinalCategories('hy'));

        // Irish
        self::assertSame($expected, PluralRules::getOrdinalCategories('ga'));

        // Malay
        self::assertSame($expected, PluralRules::getOrdinalCategories('ms'));
    }

    /**
     * Test getOrdinalCategories for Macedonian ordinals (Rule 8: one/two/many/other)
     */
    public function testOrdinalCategoriesRuleEightMacedonian(): void
    {
        $expected = [
            PluralRules::CATEGORY_ONE,
            PluralRules::CATEGORY_TWO,
            PluralRules::CATEGORY_MANY,
            PluralRules::CATEGORY_OTHER
        ];

        self::assertSame($expected, PluralRules::getOrdinalCategories('mk'));
    }

    /**
     * Test getOrdinalCategories for Welsh ordinals (Rule 14: zero/one/two/few/many/other)
     */
    public function testOrdinalCategoriesRuleFourteenWelsh(): void
    {
        $expected = [
            PluralRules::CATEGORY_ZERO,
            PluralRules::CATEGORY_ONE,
            PluralRules::CATEGORY_TWO,
            PluralRules::CATEGORY_FEW,
            PluralRules::CATEGORY_MANY,
            PluralRules::CATEGORY_OTHER
        ];

        self::assertSame($expected, PluralRules::getOrdinalCategories('cy'));
    }

    /**
     * Test getOrdinalCategories for Scottish Gaelic ordinals (Rule 16: one/two/few/other)
     */
    public function testOrdinalCategoriesRuleSixteenScottishGaelic(): void
    {
        $expected = [
            PluralRules::CATEGORY_ONE,
            PluralRules::CATEGORY_TWO,
            PluralRules::CATEGORY_FEW,
            PluralRules::CATEGORY_OTHER
        ];

        self::assertSame($expected, PluralRules::getOrdinalCategories('gd'));
    }

    /**
     * Test getOrdinalCategories for Italian ordinals (Rule 20: many/other)
     * Pattern: many for 8, 11, 80, 800; other for everything else
     */
    public function testOrdinalCategoriesRuleTwentyItalian(): void
    {
        $expected = [PluralRules::CATEGORY_MANY, PluralRules::CATEGORY_OTHER];

        self::assertSame($expected, PluralRules::getOrdinalCategories('it'));
        self::assertSame($expected, PluralRules::getOrdinalCategories('sc')); // Sardinian
    }

    /**
     * Test getOrdinalCategories for Kazakh/Azerbaijani ordinals (Rule 21: many/other)
     * Pattern: many for n%10=6,9 or n%10=0 && n!=0
     */
    public function testOrdinalCategoriesRuleTwentyOneKazakhAzerbaijani(): void
    {
        $expected = [PluralRules::CATEGORY_MANY, PluralRules::CATEGORY_OTHER];

        // Kazakh
        self::assertSame($expected, PluralRules::getOrdinalCategories('kk'));

        // Azerbaijani variants
        self::assertSame($expected, PluralRules::getOrdinalCategories('az'));
        self::assertSame($expected, PluralRules::getOrdinalCategories('azb'));
        self::assertSame($expected, PluralRules::getOrdinalCategories('azj'));

        // Georgian
        self::assertSame($expected, PluralRules::getOrdinalCategories('ka'));
    }

    /**
     * Test getOrdinalCategories for Hungarian/Ukrainian ordinals (Rule 22: few/other)
     * Pattern: few for n=1,5
     */
    public function testOrdinalCategoriesRuleTwentyTwoHungarianUkrainian(): void
    {
        $expected = [PluralRules::CATEGORY_FEW, PluralRules::CATEGORY_OTHER];

        // Hungarian
        self::assertSame($expected, PluralRules::getOrdinalCategories('hu'));

        // Ukrainian
        self::assertSame($expected, PluralRules::getOrdinalCategories('uk'));

        // Turkmen
        self::assertSame($expected, PluralRules::getOrdinalCategories('tk'));
    }

    /**
     * Test getOrdinalCategories for Bengali/Assamese/Hindi ordinals (Rule 23: one/other)
     * Pattern: one for n=1,5,7,8,9,10
     */
    public function testOrdinalCategoriesRuleTwentyThreeIndicLanguages(): void
    {
        $expected = [PluralRules::CATEGORY_ONE, PluralRules::CATEGORY_OTHER];

        // Bengali
        self::assertSame($expected, PluralRules::getOrdinalCategories('bn'));

        // Assamese
        self::assertSame($expected, PluralRules::getOrdinalCategories('as'));

        // Hindi
        self::assertSame($expected, PluralRules::getOrdinalCategories('hi'));
    }

    /**
     * Test getOrdinalCategories for Gujarati ordinals (Rule 24: one/two/few/many/other)
     */
    public function testOrdinalCategoriesRuleTwentyFourGujarati(): void
    {
        $expected = [
            PluralRules::CATEGORY_ONE,
            PluralRules::CATEGORY_TWO,
            PluralRules::CATEGORY_FEW,
            PluralRules::CATEGORY_MANY,
            PluralRules::CATEGORY_OTHER
        ];

        self::assertSame($expected, PluralRules::getOrdinalCategories('gu'));
    }

    /**
     * Test getOrdinalCategories for Kannada ordinals (Rule 25: one/two/few/other)
     */
    public function testOrdinalCategoriesRuleTwentyFiveKannada(): void
    {
        $expected = [
            PluralRules::CATEGORY_ONE,
            PluralRules::CATEGORY_TWO,
            PluralRules::CATEGORY_FEW,
            PluralRules::CATEGORY_OTHER
        ];

        self::assertSame($expected, PluralRules::getOrdinalCategories('kn'));
    }

    /**
     * Test getOrdinalCategories for Marathi ordinals (Rule 26: one/other)
     */
    public function testOrdinalCategoriesRuleTwentySixMarathi(): void
    {
        $expected = [PluralRules::CATEGORY_ONE, PluralRules::CATEGORY_OTHER];

        self::assertSame($expected, PluralRules::getOrdinalCategories('mr'));
    }

    /**
     * Test getOrdinalCategories for Odia ordinals (Rule 27: one/two/few/many/other)
     */
    public function testOrdinalCategoriesRuleTwentySevenOdia(): void
    {
        $expected = [
            PluralRules::CATEGORY_ONE,
            PluralRules::CATEGORY_TWO,
            PluralRules::CATEGORY_FEW,
            PluralRules::CATEGORY_MANY,
            PluralRules::CATEGORY_OTHER
        ];

        self::assertSame($expected, PluralRules::getOrdinalCategories('or'));
        self::assertSame($expected, PluralRules::getOrdinalCategories('ory'));
    }

    /**
     * Test getOrdinalCategories for Telugu ordinals (Rule 28: one/two/many/other)
     */
    public function testOrdinalCategoriesRuleTwentyEightTelugu(): void
    {
        $expected = [
            PluralRules::CATEGORY_ONE,
            PluralRules::CATEGORY_TWO,
            PluralRules::CATEGORY_MANY,
            PluralRules::CATEGORY_OTHER
        ];

        self::assertSame($expected, PluralRules::getOrdinalCategories('te'));
    }

    /**
     * Test getOrdinalCategories for Nepali ordinals (Rule 29: one/few/other)
     */
    public function testOrdinalCategoriesRuleTwentyNineNepali(): void
    {
        $expected = [
            PluralRules::CATEGORY_ONE,
            PluralRules::CATEGORY_FEW,
            PluralRules::CATEGORY_OTHER
        ];

        self::assertSame($expected, PluralRules::getOrdinalCategories('ne'));
    }

    /**
     * Test getOrdinalCategories for Albanian ordinals (Rule 30: one/two/few/other)
     */
    public function testOrdinalCategoriesRuleThirtyAlbanian(): void
    {
        $expected = [
            PluralRules::CATEGORY_ONE,
            PluralRules::CATEGORY_TWO,
            PluralRules::CATEGORY_FEW,
            PluralRules::CATEGORY_OTHER
        ];

        self::assertSame($expected, PluralRules::getOrdinalCategories('sq'));
    }

    /**
     * Test that cardinal and ordinal categories can differ for the same language
     */
    public function testCardinalAndOrdinalCategoriesDiffer(): void
    {
        // Kazakh: cardinal has one/other, ordinal has many/other
        $cardinalKk = PluralRules::getCardinalCategories('kk');
        $ordinalKk = PluralRules::getOrdinalCategories('kk');
        self::assertNotSame($cardinalKk, $ordinalKk);
        self::assertSame([PluralRules::CATEGORY_ONE, PluralRules::CATEGORY_OTHER], $cardinalKk);
        self::assertSame([PluralRules::CATEGORY_MANY, PluralRules::CATEGORY_OTHER], $ordinalKk);

        // English: cardinal has one/other, ordinal has one/two/few/other
        $cardinalEn = PluralRules::getCardinalCategories('en');
        $ordinalEn = PluralRules::getOrdinalCategories('en');
        self::assertNotSame($cardinalEn, $ordinalEn);
        self::assertCount(2, $cardinalEn);
        self::assertCount(4, $ordinalEn);

        // Hungarian: cardinal has one/other, ordinal has few/other
        $cardinalHu = PluralRules::getCardinalCategories('hu');
        $ordinalHu = PluralRules::getOrdinalCategories('hu');
        self::assertNotSame($cardinalHu, $ordinalHu);
        self::assertContains(PluralRules::CATEGORY_ONE, $cardinalHu);
        self::assertContains(PluralRules::CATEGORY_FEW, $ordinalHu);
        self::assertNotContains(PluralRules::CATEGORY_ONE, $ordinalHu);

        // Italian: cardinal has one/many/other, ordinal has many/other
        $cardinalIt = PluralRules::getCardinalCategories('it');
        $ordinalIt = PluralRules::getOrdinalCategories('it');
        self::assertNotSame($cardinalIt, $ordinalIt);
        self::assertCount(3, $cardinalIt);
        self::assertCount(2, $ordinalIt);
    }

    /**
     * Test ordinal categories with locale variants
     */
    public function testOrdinalCategoriesWithLocaleVariants(): void
    {
        // English variants should all have the same ordinal categories
        $enCategories = PluralRules::getOrdinalCategories('en');
        self::assertSame($enCategories, PluralRules::getOrdinalCategories('en-US'));
        self::assertSame($enCategories, PluralRules::getOrdinalCategories('en-GB'));
        self::assertSame($enCategories, PluralRules::getOrdinalCategories('en_AU'));

        // French variants
        $frCategories = PluralRules::getOrdinalCategories('fr');
        self::assertSame($frCategories, PluralRules::getOrdinalCategories('fr-FR'));
        self::assertSame($frCategories, PluralRules::getOrdinalCategories('fr-CA'));
        self::assertSame($frCategories, PluralRules::getOrdinalCategories('fr_BE'));

        // Azerbaijani variants
        $azCategories = PluralRules::getOrdinalCategories('az');
        self::assertSame($azCategories, PluralRules::getOrdinalCategories('azb'));
        self::assertSame($azCategories, PluralRules::getOrdinalCategories('azj'));
    }

    /**
     * Test ordinal categories count boundaries
     */
    public function testOrdinalCategoriesCountBoundaries(): void
    {
        // Minimum: 1 category (only "other")
        self::assertCount(1, PluralRules::getOrdinalCategories('ja'));
        self::assertCount(1, PluralRules::getOrdinalCategories('ru'));
        self::assertCount(1, PluralRules::getOrdinalCategories('de'));

        // 2 categories (one/other or many/other or few/other)
        self::assertCount(2, PluralRules::getOrdinalCategories('fr'));
        self::assertCount(2, PluralRules::getOrdinalCategories('it'));
        self::assertCount(2, PluralRules::getOrdinalCategories('kk'));
        self::assertCount(2, PluralRules::getOrdinalCategories('hu'));

        // 3 categories (one/few/other)
        self::assertCount(3, PluralRules::getOrdinalCategories('ne'));

        // 4 categories (one/two/few/other or one/two/many/other)
        self::assertCount(4, PluralRules::getOrdinalCategories('en'));
        self::assertCount(4, PluralRules::getOrdinalCategories('mk'));
        self::assertCount(4, PluralRules::getOrdinalCategories('gd'));
        self::assertCount(4, PluralRules::getOrdinalCategories('sq'));
        self::assertCount(4, PluralRules::getOrdinalCategories('te'));

        // 5 categories (one/two/few/many/other)
        self::assertCount(5, PluralRules::getOrdinalCategories('gu'));
        self::assertCount(5, PluralRules::getOrdinalCategories('or'));

        // Maximum: 6 categories (zero/one/two/few/many/other)
        self::assertCount(6, PluralRules::getOrdinalCategories('cy'));
    }

    /**
     * Test that unknown locales fall back to "other" only for ordinals
     */
    public function testOrdinalCategoriesUnknownLocaleFallback(): void
    {
        self::assertSame([PluralRules::CATEGORY_OTHER], PluralRules::getOrdinalCategories('unknown'));
        self::assertSame([PluralRules::CATEGORY_OTHER], PluralRules::getOrdinalCategories('xyz'));
        self::assertSame([PluralRules::CATEGORY_OTHER], PluralRules::getOrdinalCategories(''));
    }

    /**
     * @param array<string> $expected
     */
    #[DataProvider('ordinalCategoriesProvider')]
    public function testOrdinalCategoriesDataProvider(string $locale, array $expected): void
    {
        self::assertSame($expected, PluralRules::getOrdinalCategories($locale));
    }

    /**
     * @return array<string, array{string, array<string>}>
     */
    public static function ordinalCategoriesProvider(): array
    {
        return [
            // Rule 0: Only other
            'Japanese' => ['ja', [PluralRules::CATEGORY_OTHER]],
            'Chinese' => ['zh', [PluralRules::CATEGORY_OTHER]],
            'Russian' => ['ru', [PluralRules::CATEGORY_OTHER]],
            'German' => ['de', [PluralRules::CATEGORY_OTHER]],
            'Polish' => ['pl', [PluralRules::CATEGORY_OTHER]],
            'Arabic' => ['ar', [PluralRules::CATEGORY_OTHER]],

            // Rule 1: English-like (one/two/few/other)
            'English' => [
                'en',
                [
                    PluralRules::CATEGORY_ONE,
                    PluralRules::CATEGORY_TWO,
                    PluralRules::CATEGORY_FEW,
                    PluralRules::CATEGORY_OTHER
                ]
            ],

            // Rule 2: one/other
            'French' => ['fr', [PluralRules::CATEGORY_ONE, PluralRules::CATEGORY_OTHER]],
            'Swedish' => ['sv', [PluralRules::CATEGORY_ONE, PluralRules::CATEGORY_OTHER]],
            'Catalan' => ['ca', [PluralRules::CATEGORY_ONE, PluralRules::CATEGORY_OTHER]],

            // Rule 8: Macedonian (one/two/many/other)
            'Macedonian' => [
                'mk',
                [
                    PluralRules::CATEGORY_ONE,
                    PluralRules::CATEGORY_TWO,
                    PluralRules::CATEGORY_MANY,
                    PluralRules::CATEGORY_OTHER
                ]
            ],

            // Rule 14: Welsh (all 6 categories)
            'Welsh' => [
                'cy',
                [
                    PluralRules::CATEGORY_ZERO,
                    PluralRules::CATEGORY_ONE,
                    PluralRules::CATEGORY_TWO,
                    PluralRules::CATEGORY_FEW,
                    PluralRules::CATEGORY_MANY,
                    PluralRules::CATEGORY_OTHER
                ]
            ],

            // Rule 16: Scottish Gaelic (one/two/few/other)
            'Scottish Gaelic' => [
                'gd',
                [
                    PluralRules::CATEGORY_ONE,
                    PluralRules::CATEGORY_TWO,
                    PluralRules::CATEGORY_FEW,
                    PluralRules::CATEGORY_OTHER
                ]
            ],

            // Rule 20: Italian (many/other)
            'Italian' => ['it', [PluralRules::CATEGORY_MANY, PluralRules::CATEGORY_OTHER]],

            // Rule 21: Kazakh/Azerbaijani (many/other)
            'Kazakh' => ['kk', [PluralRules::CATEGORY_MANY, PluralRules::CATEGORY_OTHER]],
            'Azerbaijani' => ['az', [PluralRules::CATEGORY_MANY, PluralRules::CATEGORY_OTHER]],
            'Georgian' => ['ka', [PluralRules::CATEGORY_MANY, PluralRules::CATEGORY_OTHER]],

            // Rule 22: Hungarian/Ukrainian (few/other)
            'Hungarian' => ['hu', [PluralRules::CATEGORY_FEW, PluralRules::CATEGORY_OTHER]],
            'Ukrainian' => ['uk', [PluralRules::CATEGORY_FEW, PluralRules::CATEGORY_OTHER]],
            'Turkmen' => ['tk', [PluralRules::CATEGORY_FEW, PluralRules::CATEGORY_OTHER]],

            // Rule 23: Bengali/Assamese/Hindi (one/other)
            'Bengali' => ['bn', [PluralRules::CATEGORY_ONE, PluralRules::CATEGORY_OTHER]],
            'Assamese' => ['as', [PluralRules::CATEGORY_ONE, PluralRules::CATEGORY_OTHER]],
            'Hindi' => ['hi', [PluralRules::CATEGORY_ONE, PluralRules::CATEGORY_OTHER]],

            // Rule 24: Gujarati (one/two/few/many/other)
            'Gujarati' => [
                'gu',
                [
                    PluralRules::CATEGORY_ONE,
                    PluralRules::CATEGORY_TWO,
                    PluralRules::CATEGORY_FEW,
                    PluralRules::CATEGORY_MANY,
                    PluralRules::CATEGORY_OTHER
                ]
            ],

            // Rule 25: Kannada (one/two/few/other)
            'Kannada' => [
                'kn',
                [
                    PluralRules::CATEGORY_ONE,
                    PluralRules::CATEGORY_TWO,
                    PluralRules::CATEGORY_FEW,
                    PluralRules::CATEGORY_OTHER
                ]
            ],

            // Rule 26: Marathi (one/other)
            'Marathi' => ['mr', [PluralRules::CATEGORY_ONE, PluralRules::CATEGORY_OTHER]],

            // Rule 27: Odia (one/two/few/many/other)
            'Odia' => [
                'or',
                [
                    PluralRules::CATEGORY_ONE,
                    PluralRules::CATEGORY_TWO,
                    PluralRules::CATEGORY_FEW,
                    PluralRules::CATEGORY_MANY,
                    PluralRules::CATEGORY_OTHER
                ]
            ],

            // Rule 28: Telugu (one/two/many/other)
            'Telugu' => [
                'te',
                [
                    PluralRules::CATEGORY_ONE,
                    PluralRules::CATEGORY_TWO,
                    PluralRules::CATEGORY_MANY,
                    PluralRules::CATEGORY_OTHER
                ]
            ],

            // Rule 29: Nepali (one/few/other)
            'Nepali' => ['ne', [PluralRules::CATEGORY_ONE, PluralRules::CATEGORY_FEW, PluralRules::CATEGORY_OTHER]],

            // Rule 30: Albanian (one/two/few/other)
            'Albanian' => [
                'sq',
                [
                    PluralRules::CATEGORY_ONE,
                    PluralRules::CATEGORY_TWO,
                    PluralRules::CATEGORY_FEW,
                    PluralRules::CATEGORY_OTHER
                ]
            ],
        ];
    }

    // =========================================================================
    // getOrdinalFormIndex Tests
    // =========================================================================

    /**
     * Test getOrdinalFormIndex for Rule 0 (no ordinal distinction)
     */
    public function testGetOrdinalFormIndexRuleZero(): void
    {
        // Japanese, Chinese, Russian - always return 0 (other)
        self::assertSame(0, PluralRules::getOrdinalFormIndex('ja', 0));
        self::assertSame(0, PluralRules::getOrdinalFormIndex('ja', 1));
        self::assertSame(0, PluralRules::getOrdinalFormIndex('ja', 100));

        self::assertSame(0, PluralRules::getOrdinalFormIndex('zh', 1));
        self::assertSame(0, PluralRules::getOrdinalFormIndex('ru', 1));
        self::assertSame(0, PluralRules::getOrdinalFormIndex('de', 1));
    }

    /**
     * Test getOrdinalFormIndex for Rule 1 (English-like ordinals)
     * Pattern: 1st, 2nd, 3rd, 4th... 11th, 12th, 13th... 21st, 22nd, 23rd...
     */
    public function testGetOrdinalFormIndexRuleOneEnglish(): void
    {
        // one: n % 10 = 1 and n % 100 != 11
        self::assertSame(0, PluralRules::getOrdinalFormIndex('en', 1));   // 1st
        self::assertSame(0, PluralRules::getOrdinalFormIndex('en', 21));  // 21st
        self::assertSame(0, PluralRules::getOrdinalFormIndex('en', 31));  // 31st
        self::assertSame(0, PluralRules::getOrdinalFormIndex('en', 101)); // 101st

        // two: n % 10 = 2 and n % 100 != 12
        self::assertSame(1, PluralRules::getOrdinalFormIndex('en', 2));   // 2nd
        self::assertSame(1, PluralRules::getOrdinalFormIndex('en', 22));  // 22nd
        self::assertSame(1, PluralRules::getOrdinalFormIndex('en', 32));  // 32nd
        self::assertSame(1, PluralRules::getOrdinalFormIndex('en', 102)); // 102nd

        // few: n % 10 = 3 and n % 100 != 13
        self::assertSame(2, PluralRules::getOrdinalFormIndex('en', 3));   // 3rd
        self::assertSame(2, PluralRules::getOrdinalFormIndex('en', 23));  // 23rd
        self::assertSame(2, PluralRules::getOrdinalFormIndex('en', 33));  // 33rd
        self::assertSame(2, PluralRules::getOrdinalFormIndex('en', 103)); // 103rd

        // other: everything else (including 11, 12, 13)
        self::assertSame(3, PluralRules::getOrdinalFormIndex('en', 0));   // 0th
        self::assertSame(3, PluralRules::getOrdinalFormIndex('en', 4));   // 4th
        self::assertSame(3, PluralRules::getOrdinalFormIndex('en', 5));   // 5th
        self::assertSame(3, PluralRules::getOrdinalFormIndex('en', 10));  // 10th
        self::assertSame(3, PluralRules::getOrdinalFormIndex('en', 11));  // 11th (not 11st!)
        self::assertSame(3, PluralRules::getOrdinalFormIndex('en', 12));  // 12th (not 12nd!)
        self::assertSame(3, PluralRules::getOrdinalFormIndex('en', 13));  // 13th (not 13rd!)
        self::assertSame(3, PluralRules::getOrdinalFormIndex('en', 111)); // 111th
        self::assertSame(3, PluralRules::getOrdinalFormIndex('en', 112)); // 112th
        self::assertSame(3, PluralRules::getOrdinalFormIndex('en', 113)); // 113th
    }

    /**
     * Test getOrdinalFormIndex for Rule 2 (French-like ordinals)
     * Pattern: 1er, 2e, 3e...
     */
    public function testGetOrdinalFormIndexRuleTwoFrench(): void
    {
        // one: n = 1
        self::assertSame(0, PluralRules::getOrdinalFormIndex('fr', 1));  // 1er

        // other: everything else
        self::assertSame(1, PluralRules::getOrdinalFormIndex('fr', 0));
        self::assertSame(1, PluralRules::getOrdinalFormIndex('fr', 2));  // 2e
        self::assertSame(1, PluralRules::getOrdinalFormIndex('fr', 3));  // 3e
        self::assertSame(1, PluralRules::getOrdinalFormIndex('fr', 10));
        self::assertSame(1, PluralRules::getOrdinalFormIndex('fr', 21)); // 21e (unlike English)
        self::assertSame(1, PluralRules::getOrdinalFormIndex('fr', 100));
    }

    /**
     * Test getOrdinalFormIndex for Rule 8 (Macedonian ordinals)
     * Pattern: one/two/many/other
     */
    public function testGetOrdinalFormIndexRuleEightMacedonian(): void
    {
        // one: n % 10 = 1 and n % 100 != 11
        self::assertSame(0, PluralRules::getOrdinalFormIndex('mk', 1));
        self::assertSame(0, PluralRules::getOrdinalFormIndex('mk', 21));

        // two: n % 10 = 2 and n % 100 != 12
        self::assertSame(1, PluralRules::getOrdinalFormIndex('mk', 2));
        self::assertSame(1, PluralRules::getOrdinalFormIndex('mk', 22));

        // many: n % 10 = 7,8 and n % 100 != 17,18
        self::assertSame(2, PluralRules::getOrdinalFormIndex('mk', 7));
        self::assertSame(2, PluralRules::getOrdinalFormIndex('mk', 8));
        self::assertSame(2, PluralRules::getOrdinalFormIndex('mk', 27));
        self::assertSame(2, PluralRules::getOrdinalFormIndex('mk', 28));

        // other: everything else (including 11, 12, 17, 18)
        self::assertSame(3, PluralRules::getOrdinalFormIndex('mk', 0));
        self::assertSame(3, PluralRules::getOrdinalFormIndex('mk', 3));
        self::assertSame(3, PluralRules::getOrdinalFormIndex('mk', 11));
        self::assertSame(3, PluralRules::getOrdinalFormIndex('mk', 12));
        self::assertSame(3, PluralRules::getOrdinalFormIndex('mk', 17));
        self::assertSame(3, PluralRules::getOrdinalFormIndex('mk', 18));
    }

    /**
     * Test getOrdinalFormIndex for Rule 14 (Welsh ordinals)
     * Pattern: zero/one/two/few/many/other
     */
    public function testGetOrdinalFormIndexRuleFourteenWelsh(): void
    {
        // zero: n = 0,7,8,9
        self::assertSame(0, PluralRules::getOrdinalFormIndex('cy', 0));
        self::assertSame(0, PluralRules::getOrdinalFormIndex('cy', 7));
        self::assertSame(0, PluralRules::getOrdinalFormIndex('cy', 8));
        self::assertSame(0, PluralRules::getOrdinalFormIndex('cy', 9));

        // one: n = 1
        self::assertSame(1, PluralRules::getOrdinalFormIndex('cy', 1));

        // two: n = 2
        self::assertSame(2, PluralRules::getOrdinalFormIndex('cy', 2));

        // few: n = 3,4
        self::assertSame(3, PluralRules::getOrdinalFormIndex('cy', 3));
        self::assertSame(3, PluralRules::getOrdinalFormIndex('cy', 4));

        // many: n = 5,6
        self::assertSame(4, PluralRules::getOrdinalFormIndex('cy', 5));
        self::assertSame(4, PluralRules::getOrdinalFormIndex('cy', 6));

        // other: everything else
        self::assertSame(5, PluralRules::getOrdinalFormIndex('cy', 10));
        self::assertSame(5, PluralRules::getOrdinalFormIndex('cy', 15));
        self::assertSame(5, PluralRules::getOrdinalFormIndex('cy', 100));
    }

    /**
     * Test getOrdinalFormIndex for Rule 16 (Scottish Gaelic ordinals)
     */
    public function testGetOrdinalFormIndexRuleSixteenScottishGaelic(): void
    {
        // one: n = 1,11
        self::assertSame(0, PluralRules::getOrdinalFormIndex('gd', 1));
        self::assertSame(0, PluralRules::getOrdinalFormIndex('gd', 11));

        // two: n = 2,12
        self::assertSame(1, PluralRules::getOrdinalFormIndex('gd', 2));
        self::assertSame(1, PluralRules::getOrdinalFormIndex('gd', 12));

        // few: n = 3,13
        self::assertSame(2, PluralRules::getOrdinalFormIndex('gd', 3));
        self::assertSame(2, PluralRules::getOrdinalFormIndex('gd', 13));

        // other: everything else
        self::assertSame(3, PluralRules::getOrdinalFormIndex('gd', 0));
        self::assertSame(3, PluralRules::getOrdinalFormIndex('gd', 4));
        self::assertSame(3, PluralRules::getOrdinalFormIndex('gd', 14));
        self::assertSame(3, PluralRules::getOrdinalFormIndex('gd', 100));
    }

    /**
     * Test getOrdinalFormIndex for Rule 20 (Italian ordinals)
     * Pattern: many for 8,11,80,800; other for everything else
     */
    public function testGetOrdinalFormIndexRuleTwentyItalian(): void
    {
        // many: n = 8,11,80,800
        self::assertSame(0, PluralRules::getOrdinalFormIndex('it', 8));
        self::assertSame(0, PluralRules::getOrdinalFormIndex('it', 11));
        self::assertSame(0, PluralRules::getOrdinalFormIndex('it', 80));
        self::assertSame(0, PluralRules::getOrdinalFormIndex('it', 800));

        // other: everything else
        self::assertSame(1, PluralRules::getOrdinalFormIndex('it', 0));
        self::assertSame(1, PluralRules::getOrdinalFormIndex('it', 1));
        self::assertSame(1, PluralRules::getOrdinalFormIndex('it', 2));
        self::assertSame(1, PluralRules::getOrdinalFormIndex('it', 7));
        self::assertSame(1, PluralRules::getOrdinalFormIndex('it', 9));
        self::assertSame(1, PluralRules::getOrdinalFormIndex('it', 10));
        self::assertSame(1, PluralRules::getOrdinalFormIndex('it', 12));
        self::assertSame(1, PluralRules::getOrdinalFormIndex('it', 100));
    }

    /**
     * Test getOrdinalFormIndex for Rule 21 (Kazakh/Azerbaijani ordinals)
     * Pattern: many for n%10=6,9 or n%10=0 && n!=0; other for everything else
     */
    public function testGetOrdinalFormIndexRuleTwentyOneKazakh(): void
    {
        // many: n % 10 = 6,9 or n % 10 = 0 and n != 0
        self::assertSame(0, PluralRules::getOrdinalFormIndex('kk', 6));
        self::assertSame(0, PluralRules::getOrdinalFormIndex('kk', 9));
        self::assertSame(0, PluralRules::getOrdinalFormIndex('kk', 10));
        self::assertSame(0, PluralRules::getOrdinalFormIndex('kk', 16));
        self::assertSame(0, PluralRules::getOrdinalFormIndex('kk', 19));
        self::assertSame(0, PluralRules::getOrdinalFormIndex('kk', 20));
        self::assertSame(0, PluralRules::getOrdinalFormIndex('kk', 26));
        self::assertSame(0, PluralRules::getOrdinalFormIndex('kk', 100));

        // other: everything else (including 0)
        self::assertSame(1, PluralRules::getOrdinalFormIndex('kk', 0));
        self::assertSame(1, PluralRules::getOrdinalFormIndex('kk', 1));
        self::assertSame(1, PluralRules::getOrdinalFormIndex('kk', 2));
        self::assertSame(1, PluralRules::getOrdinalFormIndex('kk', 5));
        self::assertSame(1, PluralRules::getOrdinalFormIndex('kk', 7));
        self::assertSame(1, PluralRules::getOrdinalFormIndex('kk', 8));
        self::assertSame(1, PluralRules::getOrdinalFormIndex('kk', 11));
    }

    /**
     * Test getOrdinalFormIndex for Rule 22 (Hungarian ordinals)
     * Pattern: few for n=1,5; other for everything else
     */
    public function testGetOrdinalFormIndexRuleTwentyTwoHungarian(): void
    {
        // few: n = 1,5
        self::assertSame(0, PluralRules::getOrdinalFormIndex('hu', 1));
        self::assertSame(0, PluralRules::getOrdinalFormIndex('hu', 5));

        // other: everything else
        self::assertSame(1, PluralRules::getOrdinalFormIndex('hu', 0));
        self::assertSame(1, PluralRules::getOrdinalFormIndex('hu', 2));
        self::assertSame(1, PluralRules::getOrdinalFormIndex('hu', 3));
        self::assertSame(1, PluralRules::getOrdinalFormIndex('hu', 4));
        self::assertSame(1, PluralRules::getOrdinalFormIndex('hu', 6));
        self::assertSame(1, PluralRules::getOrdinalFormIndex('hu', 10));
        self::assertSame(1, PluralRules::getOrdinalFormIndex('hu', 100));
    }

    /**
     * Test getOrdinalFormIndex for Rule 23 (Bengali/Hindi ordinals)
     * Pattern: one for n=1,5,7,8,9,10; other for everything else
     */
    public function testGetOrdinalFormIndexRuleTwentyThreeBengali(): void
    {
        // one: n = 1,5,7,8,9,10
        self::assertSame(0, PluralRules::getOrdinalFormIndex('bn', 1));
        self::assertSame(0, PluralRules::getOrdinalFormIndex('bn', 5));
        self::assertSame(0, PluralRules::getOrdinalFormIndex('bn', 7));
        self::assertSame(0, PluralRules::getOrdinalFormIndex('bn', 8));
        self::assertSame(0, PluralRules::getOrdinalFormIndex('bn', 9));
        self::assertSame(0, PluralRules::getOrdinalFormIndex('bn', 10));

        // other: everything else
        self::assertSame(1, PluralRules::getOrdinalFormIndex('bn', 0));
        self::assertSame(1, PluralRules::getOrdinalFormIndex('bn', 2));
        self::assertSame(1, PluralRules::getOrdinalFormIndex('bn', 3));
        self::assertSame(1, PluralRules::getOrdinalFormIndex('bn', 4));
        self::assertSame(1, PluralRules::getOrdinalFormIndex('bn', 6));
        self::assertSame(1, PluralRules::getOrdinalFormIndex('bn', 11));
        self::assertSame(1, PluralRules::getOrdinalFormIndex('bn', 100));
    }

    /**
     * Test getOrdinalFormIndex for Rule 24 (Gujarati ordinals)
     * Pattern: one/two/few/many/other
     */
    public function testGetOrdinalFormIndexRuleTwentyFourGujarati(): void
    {
        // one: n = 1
        self::assertSame(0, PluralRules::getOrdinalFormIndex('gu', 1));

        // two: n = 2,3
        self::assertSame(1, PluralRules::getOrdinalFormIndex('gu', 2));
        self::assertSame(1, PluralRules::getOrdinalFormIndex('gu', 3));

        // few: n = 4
        self::assertSame(2, PluralRules::getOrdinalFormIndex('gu', 4));

        // many: n = 6
        self::assertSame(3, PluralRules::getOrdinalFormIndex('gu', 6));

        // other: everything else
        self::assertSame(4, PluralRules::getOrdinalFormIndex('gu', 0));
        self::assertSame(4, PluralRules::getOrdinalFormIndex('gu', 5));
        self::assertSame(4, PluralRules::getOrdinalFormIndex('gu', 7));
        self::assertSame(4, PluralRules::getOrdinalFormIndex('gu', 10));
        self::assertSame(4, PluralRules::getOrdinalFormIndex('gu', 100));
    }

    /**
     * Test getOrdinalFormIndex for Rule 25 (Kannada ordinals)
     */
    public function testGetOrdinalFormIndexRuleTwentyFiveKannada(): void
    {
        // one: n = 1
        self::assertSame(0, PluralRules::getOrdinalFormIndex('kn', 1));

        // two: n = 2,3
        self::assertSame(1, PluralRules::getOrdinalFormIndex('kn', 2));
        self::assertSame(1, PluralRules::getOrdinalFormIndex('kn', 3));

        // few: n = 4
        self::assertSame(2, PluralRules::getOrdinalFormIndex('kn', 4));

        // other: everything else
        self::assertSame(3, PluralRules::getOrdinalFormIndex('kn', 0));
        self::assertSame(3, PluralRules::getOrdinalFormIndex('kn', 5));
        self::assertSame(3, PluralRules::getOrdinalFormIndex('kn', 6));
        self::assertSame(3, PluralRules::getOrdinalFormIndex('kn', 100));
    }

    /**
     * Test getOrdinalFormIndex for Rule 26 (Marathi ordinals)
     */
    public function testGetOrdinalFormIndexRuleTwentySixMarathi(): void
    {
        // one: n = 1
        self::assertSame(0, PluralRules::getOrdinalFormIndex('mr', 1));

        // other: everything else
        self::assertSame(1, PluralRules::getOrdinalFormIndex('mr', 0));
        self::assertSame(1, PluralRules::getOrdinalFormIndex('mr', 2));
        self::assertSame(1, PluralRules::getOrdinalFormIndex('mr', 10));
        self::assertSame(1, PluralRules::getOrdinalFormIndex('mr', 100));
    }

    /**
     * Test getOrdinalFormIndex for Rule 27 (Odia ordinals)
     */
    public function testGetOrdinalFormIndexRuleTwentySevenOdia(): void
    {
        // one: n = 1,5,7..9
        self::assertSame(0, PluralRules::getOrdinalFormIndex('or', 1));
        self::assertSame(0, PluralRules::getOrdinalFormIndex('or', 5));
        self::assertSame(0, PluralRules::getOrdinalFormIndex('or', 7));
        self::assertSame(0, PluralRules::getOrdinalFormIndex('or', 8));
        self::assertSame(0, PluralRules::getOrdinalFormIndex('or', 9));

        // two: n = 2,3
        self::assertSame(1, PluralRules::getOrdinalFormIndex('or', 2));
        self::assertSame(1, PluralRules::getOrdinalFormIndex('or', 3));

        // few: n = 4
        self::assertSame(2, PluralRules::getOrdinalFormIndex('or', 4));

        // many: n = 6
        self::assertSame(3, PluralRules::getOrdinalFormIndex('or', 6));

        // other: everything else
        self::assertSame(4, PluralRules::getOrdinalFormIndex('or', 0));
        self::assertSame(4, PluralRules::getOrdinalFormIndex('or', 10));
        self::assertSame(4, PluralRules::getOrdinalFormIndex('or', 100));
    }

    /**
     * Test getOrdinalFormIndex for Rule 28 (Telugu ordinals)
     */
    public function testGetOrdinalFormIndexRuleTwentyEightTelugu(): void
    {
        // one: n = 1
        self::assertSame(0, PluralRules::getOrdinalFormIndex('te', 1));

        // two: n = 2,3
        self::assertSame(1, PluralRules::getOrdinalFormIndex('te', 2));
        self::assertSame(1, PluralRules::getOrdinalFormIndex('te', 3));

        // many: n = 4
        self::assertSame(2, PluralRules::getOrdinalFormIndex('te', 4));

        // other: everything else
        self::assertSame(3, PluralRules::getOrdinalFormIndex('te', 0));
        self::assertSame(3, PluralRules::getOrdinalFormIndex('te', 5));
        self::assertSame(3, PluralRules::getOrdinalFormIndex('te', 6));
        self::assertSame(3, PluralRules::getOrdinalFormIndex('te', 100));
    }

    /**
     * Test getOrdinalFormIndex for Rule 29 (Nepali ordinals)
     */
    public function testGetOrdinalFormIndexRuleTwentyNineNepali(): void
    {
        // one: n = 1..4
        self::assertSame(0, PluralRules::getOrdinalFormIndex('ne', 1));
        self::assertSame(0, PluralRules::getOrdinalFormIndex('ne', 2));
        self::assertSame(0, PluralRules::getOrdinalFormIndex('ne', 3));
        self::assertSame(0, PluralRules::getOrdinalFormIndex('ne', 4));

        // few: n = 5,6
        self::assertSame(1, PluralRules::getOrdinalFormIndex('ne', 5));
        self::assertSame(1, PluralRules::getOrdinalFormIndex('ne', 6));

        // other: everything else
        self::assertSame(2, PluralRules::getOrdinalFormIndex('ne', 0));
        self::assertSame(2, PluralRules::getOrdinalFormIndex('ne', 7));
        self::assertSame(2, PluralRules::getOrdinalFormIndex('ne', 10));
        self::assertSame(2, PluralRules::getOrdinalFormIndex('ne', 100));
    }

    /**
     * Test getOrdinalFormIndex for Rule 30 (Albanian ordinals)
     */
    public function testGetOrdinalFormIndexRuleThirtyAlbanian(): void
    {
        // one: n = 1
        self::assertSame(0, PluralRules::getOrdinalFormIndex('sq', 1));

        // two: n = 4
        self::assertSame(1, PluralRules::getOrdinalFormIndex('sq', 4));

        // few: n = 2..9 except 4
        self::assertSame(2, PluralRules::getOrdinalFormIndex('sq', 2));
        self::assertSame(2, PluralRules::getOrdinalFormIndex('sq', 3));
        self::assertSame(2, PluralRules::getOrdinalFormIndex('sq', 5));
        self::assertSame(2, PluralRules::getOrdinalFormIndex('sq', 6));
        self::assertSame(2, PluralRules::getOrdinalFormIndex('sq', 7));
        self::assertSame(2, PluralRules::getOrdinalFormIndex('sq', 8));
        self::assertSame(2, PluralRules::getOrdinalFormIndex('sq', 9));

        // other: everything else
        self::assertSame(3, PluralRules::getOrdinalFormIndex('sq', 0));
        self::assertSame(3, PluralRules::getOrdinalFormIndex('sq', 10));
        self::assertSame(3, PluralRules::getOrdinalFormIndex('sq', 11));
        self::assertSame(3, PluralRules::getOrdinalFormIndex('sq', 100));
    }

    /**
     * Test getOrdinalFormIndex with locale variants
     */
    public function testGetOrdinalFormIndexWithLocaleVariants(): void
    {
        // English variants
        self::assertSame(PluralRules::getOrdinalFormIndex('en', 1), PluralRules::getOrdinalFormIndex('en-US', 1));
        self::assertSame(PluralRules::getOrdinalFormIndex('en', 2), PluralRules::getOrdinalFormIndex('en-GB', 2));
        self::assertSame(PluralRules::getOrdinalFormIndex('en', 3), PluralRules::getOrdinalFormIndex('en_AU', 3));

        // French variants
        self::assertSame(PluralRules::getOrdinalFormIndex('fr', 1), PluralRules::getOrdinalFormIndex('fr-FR', 1));
        self::assertSame(PluralRules::getOrdinalFormIndex('fr', 2), PluralRules::getOrdinalFormIndex('fr-CA', 2));
    }

    // =========================================================================
    // getOrdinalCategoryName Tests
    // =========================================================================

    /**
     * Test getOrdinalCategoryName for English ordinals
     */
    public function testGetOrdinalCategoryNameEnglish(): void
    {
        // one: 1st, 21st, 31st...
        self::assertSame(PluralRules::CATEGORY_ONE, PluralRules::getOrdinalCategoryName('en', 1));
        self::assertSame(PluralRules::CATEGORY_ONE, PluralRules::getOrdinalCategoryName('en', 21));
        self::assertSame(PluralRules::CATEGORY_ONE, PluralRules::getOrdinalCategoryName('en', 31));

        // two: 2nd, 22nd, 32nd...
        self::assertSame(PluralRules::CATEGORY_TWO, PluralRules::getOrdinalCategoryName('en', 2));
        self::assertSame(PluralRules::CATEGORY_TWO, PluralRules::getOrdinalCategoryName('en', 22));
        self::assertSame(PluralRules::CATEGORY_TWO, PluralRules::getOrdinalCategoryName('en', 32));

        // few: 3rd, 23rd, 33rd...
        self::assertSame(PluralRules::CATEGORY_FEW, PluralRules::getOrdinalCategoryName('en', 3));
        self::assertSame(PluralRules::CATEGORY_FEW, PluralRules::getOrdinalCategoryName('en', 23));
        self::assertSame(PluralRules::CATEGORY_FEW, PluralRules::getOrdinalCategoryName('en', 33));

        // other: 4th, 5th, 11th, 12th, 13th...
        self::assertSame(PluralRules::CATEGORY_OTHER, PluralRules::getOrdinalCategoryName('en', 0));
        self::assertSame(PluralRules::CATEGORY_OTHER, PluralRules::getOrdinalCategoryName('en', 4));
        self::assertSame(PluralRules::CATEGORY_OTHER, PluralRules::getOrdinalCategoryName('en', 11));
        self::assertSame(PluralRules::CATEGORY_OTHER, PluralRules::getOrdinalCategoryName('en', 12));
        self::assertSame(PluralRules::CATEGORY_OTHER, PluralRules::getOrdinalCategoryName('en', 13));
    }

    /**
     * Test getOrdinalCategoryName for French ordinals
     */
    public function testGetOrdinalCategoryNameFrench(): void
    {
        // one: 1er
        self::assertSame(PluralRules::CATEGORY_ONE, PluralRules::getOrdinalCategoryName('fr', 1));

        // other: 2e, 3e, 4e...
        self::assertSame(PluralRules::CATEGORY_OTHER, PluralRules::getOrdinalCategoryName('fr', 0));
        self::assertSame(PluralRules::CATEGORY_OTHER, PluralRules::getOrdinalCategoryName('fr', 2));
        self::assertSame(PluralRules::CATEGORY_OTHER, PluralRules::getOrdinalCategoryName('fr', 21));
        self::assertSame(PluralRules::CATEGORY_OTHER, PluralRules::getOrdinalCategoryName('fr', 100));
    }

    /**
     * Test getOrdinalCategoryName for Italian ordinals
     */
    public function testGetOrdinalCategoryNameItalian(): void
    {
        // many: l'8°, l'11°, l'80°, l'800°
        self::assertSame(PluralRules::CATEGORY_MANY, PluralRules::getOrdinalCategoryName('it', 8));
        self::assertSame(PluralRules::CATEGORY_MANY, PluralRules::getOrdinalCategoryName('it', 11));
        self::assertSame(PluralRules::CATEGORY_MANY, PluralRules::getOrdinalCategoryName('it', 80));
        self::assertSame(PluralRules::CATEGORY_MANY, PluralRules::getOrdinalCategoryName('it', 800));

        // other: il 1°, il 2°, il 3°...
        self::assertSame(PluralRules::CATEGORY_OTHER, PluralRules::getOrdinalCategoryName('it', 1));
        self::assertSame(PluralRules::CATEGORY_OTHER, PluralRules::getOrdinalCategoryName('it', 2));
        self::assertSame(PluralRules::CATEGORY_OTHER, PluralRules::getOrdinalCategoryName('it', 7));
        self::assertSame(PluralRules::CATEGORY_OTHER, PluralRules::getOrdinalCategoryName('it', 100));
    }

    /**
     * Test getOrdinalCategoryName for Welsh ordinals (all 6 categories)
     */
    public function testGetOrdinalCategoryNameWelsh(): void
    {
        // zero: 0, 7, 8, 9
        self::assertSame(PluralRules::CATEGORY_ZERO, PluralRules::getOrdinalCategoryName('cy', 0));
        self::assertSame(PluralRules::CATEGORY_ZERO, PluralRules::getOrdinalCategoryName('cy', 7));
        self::assertSame(PluralRules::CATEGORY_ZERO, PluralRules::getOrdinalCategoryName('cy', 8));
        self::assertSame(PluralRules::CATEGORY_ZERO, PluralRules::getOrdinalCategoryName('cy', 9));

        // one: 1
        self::assertSame(PluralRules::CATEGORY_ONE, PluralRules::getOrdinalCategoryName('cy', 1));

        // two: 2
        self::assertSame(PluralRules::CATEGORY_TWO, PluralRules::getOrdinalCategoryName('cy', 2));

        // few: 3, 4
        self::assertSame(PluralRules::CATEGORY_FEW, PluralRules::getOrdinalCategoryName('cy', 3));
        self::assertSame(PluralRules::CATEGORY_FEW, PluralRules::getOrdinalCategoryName('cy', 4));

        // many: 5, 6
        self::assertSame(PluralRules::CATEGORY_MANY, PluralRules::getOrdinalCategoryName('cy', 5));
        self::assertSame(PluralRules::CATEGORY_MANY, PluralRules::getOrdinalCategoryName('cy', 6));

        // other: everything else
        self::assertSame(PluralRules::CATEGORY_OTHER, PluralRules::getOrdinalCategoryName('cy', 10));
        self::assertSame(PluralRules::CATEGORY_OTHER, PluralRules::getOrdinalCategoryName('cy', 100));
    }

    /**
     * Test getOrdinalCategoryName for Kazakh ordinals
     */
    public function testGetOrdinalCategoryNameKazakh(): void
    {
        // many: n % 10 = 6,9 or n % 10 = 0 and n != 0
        self::assertSame(PluralRules::CATEGORY_MANY, PluralRules::getOrdinalCategoryName('kk', 6));
        self::assertSame(PluralRules::CATEGORY_MANY, PluralRules::getOrdinalCategoryName('kk', 9));
        self::assertSame(PluralRules::CATEGORY_MANY, PluralRules::getOrdinalCategoryName('kk', 10));
        self::assertSame(PluralRules::CATEGORY_MANY, PluralRules::getOrdinalCategoryName('kk', 20));
        self::assertSame(PluralRules::CATEGORY_MANY, PluralRules::getOrdinalCategoryName('kk', 100));

        // other: everything else (including 0)
        self::assertSame(PluralRules::CATEGORY_OTHER, PluralRules::getOrdinalCategoryName('kk', 0));
        self::assertSame(PluralRules::CATEGORY_OTHER, PluralRules::getOrdinalCategoryName('kk', 1));
        self::assertSame(PluralRules::CATEGORY_OTHER, PluralRules::getOrdinalCategoryName('kk', 5));
        self::assertSame(PluralRules::CATEGORY_OTHER, PluralRules::getOrdinalCategoryName('kk', 7));
    }

    /**
     * Test getOrdinalCategoryName for Hungarian ordinals
     */
    public function testGetOrdinalCategoryNameHungarian(): void
    {
        // few: n = 1,5
        self::assertSame(PluralRules::CATEGORY_FEW, PluralRules::getOrdinalCategoryName('hu', 1));
        self::assertSame(PluralRules::CATEGORY_FEW, PluralRules::getOrdinalCategoryName('hu', 5));

        // other: everything else
        self::assertSame(PluralRules::CATEGORY_OTHER, PluralRules::getOrdinalCategoryName('hu', 0));
        self::assertSame(PluralRules::CATEGORY_OTHER, PluralRules::getOrdinalCategoryName('hu', 2));
        self::assertSame(PluralRules::CATEGORY_OTHER, PluralRules::getOrdinalCategoryName('hu', 10));
    }

    /**
     * Test getOrdinalCategoryName for languages with only "other"
     */
    public function testGetOrdinalCategoryNameOnlyOther(): void
    {
        // Japanese, Chinese, Russian, German, Polish - always return "other"
        self::assertSame(PluralRules::CATEGORY_OTHER, PluralRules::getOrdinalCategoryName('ja', 1));
        self::assertSame(PluralRules::CATEGORY_OTHER, PluralRules::getOrdinalCategoryName('ja', 100));

        self::assertSame(PluralRules::CATEGORY_OTHER, PluralRules::getOrdinalCategoryName('zh', 1));
        self::assertSame(PluralRules::CATEGORY_OTHER, PluralRules::getOrdinalCategoryName('ru', 1));
        self::assertSame(PluralRules::CATEGORY_OTHER, PluralRules::getOrdinalCategoryName('de', 1));
        self::assertSame(PluralRules::CATEGORY_OTHER, PluralRules::getOrdinalCategoryName('pl', 1));
    }

    /**
     * Test getOrdinalCategoryName with locale variants
     */
    public function testGetOrdinalCategoryNameWithLocaleVariants(): void
    {
        // English variants should behave the same
        self::assertSame(
            PluralRules::getOrdinalCategoryName('en', 1),
            PluralRules::getOrdinalCategoryName('en-US', 1)
        );
        self::assertSame(
            PluralRules::getOrdinalCategoryName('en', 2),
            PluralRules::getOrdinalCategoryName('en-GB', 2)
        );
        self::assertSame(
            PluralRules::getOrdinalCategoryName('en', 3),
            PluralRules::getOrdinalCategoryName('en_AU', 3)
        );
    }

    /**
     * Test getOrdinalCategoryName for unknown locale falls back to "other"
     */
    public function testGetOrdinalCategoryNameUnknownLocale(): void
    {
        self::assertSame(PluralRules::CATEGORY_OTHER, PluralRules::getOrdinalCategoryName('unknown', 1));
        self::assertSame(PluralRules::CATEGORY_OTHER, PluralRules::getOrdinalCategoryName('xyz', 100));
    }

    /**
     * @param array<int, int> $expectedResults Array of [number => expectedIndex]
     */
    #[DataProvider('ordinalFormIndexProvider')]
    public function testGetOrdinalFormIndexDataProvider(string $locale, array $expectedResults): void
    {
        foreach ($expectedResults as $number => $expectedIndex) {
            self::assertSame(
                $expectedIndex,
                PluralRules::getOrdinalFormIndex($locale, $number),
                "Failed for locale '$locale' with number $number"
            );
        }
    }

    /**
     * @return array<string, array{string, array<int, int>}>
     */
    public static function ordinalFormIndexProvider(): array
    {
        return [
            'English' => ['en', [1 => 0, 2 => 1, 3 => 2, 4 => 3, 11 => 3, 12 => 3, 13 => 3, 21 => 0, 22 => 1, 23 => 2]],
            'French' => ['fr', [1 => 0, 2 => 1, 3 => 1, 21 => 1]],
            'Italian' => ['it', [1 => 1, 8 => 0, 11 => 0, 80 => 0, 800 => 0]],
            'Kazakh' => ['kk', [1 => 1, 6 => 0, 9 => 0, 10 => 0, 20 => 0]],
            'Hungarian' => ['hu', [1 => 0, 5 => 0, 2 => 1, 10 => 1]],
            'Japanese' => ['ja', [1 => 0, 2 => 0, 100 => 0]],
        ];
    }

    #[DataProvider('ordinalCategoryNameProvider')]
    public function testGetOrdinalCategoryNameDataProvider(string $locale, int $number, string $expectedCategory): void
    {
        self::assertSame($expectedCategory, PluralRules::getOrdinalCategoryName($locale, $number));
    }

    /**
     * @return array<string, array{string, int, string}>
     */
    public static function ordinalCategoryNameProvider(): array
    {
        return [
            'English 1st' => ['en', 1, PluralRules::CATEGORY_ONE],
            'English 2nd' => ['en', 2, PluralRules::CATEGORY_TWO],
            'English 3rd' => ['en', 3, PluralRules::CATEGORY_FEW],
            'English 4th' => ['en', 4, PluralRules::CATEGORY_OTHER],
            'English 11th' => ['en', 11, PluralRules::CATEGORY_OTHER],
            'English 21st' => ['en', 21, PluralRules::CATEGORY_ONE],
            'French 1er' => ['fr', 1, PluralRules::CATEGORY_ONE],
            'French 2e' => ['fr', 2, PluralRules::CATEGORY_OTHER],
            'Italian 8°' => ['it', 8, PluralRules::CATEGORY_MANY],
            'Italian 1°' => ['it', 1, PluralRules::CATEGORY_OTHER],
            'Welsh 0' => ['cy', 0, PluralRules::CATEGORY_ZERO],
            'Welsh 1' => ['cy', 1, PluralRules::CATEGORY_ONE],
            'Welsh 2' => ['cy', 2, PluralRules::CATEGORY_TWO],
            'Welsh 3' => ['cy', 3, PluralRules::CATEGORY_FEW],
            'Welsh 5' => ['cy', 5, PluralRules::CATEGORY_MANY],
            'Welsh 10' => ['cy', 10, PluralRules::CATEGORY_OTHER],
            'Kazakh 6' => ['kk', 6, PluralRules::CATEGORY_MANY],
            'Kazakh 1' => ['kk', 1, PluralRules::CATEGORY_OTHER],
            'Hungarian 1' => ['hu', 1, PluralRules::CATEGORY_FEW],
            'Hungarian 2' => ['hu', 2, PluralRules::CATEGORY_OTHER],
            'Japanese 1' => ['ja', 1, PluralRules::CATEGORY_OTHER],
        ];
    }

    // =========================================================================
    // getPluralCount Tests (moved from LanguagesTest)
    // =========================================================================

    #[DataProvider('numberOfPluralsProvider')]
    public function testGetPluralCountReturnsCorrectCount(string $locale, int $expectedCount): void
    {
        self::assertSame($expectedCount, PluralRules::getPluralCount($locale));
    }

    /**
     * @return array<string, array{0: string, 1: int}>
     */
    public static function numberOfPluralsProvider(): array
    {
        return [
            // Languages with 1 plural form (no plural)
            'Japanese' => ['ja', 1],
            'Chinese' => ['zh', 1],
            'Korean' => ['ko', 1],
            'Vietnamese' => ['vi', 1],
            'Thai' => ['th', 1],
            'Indonesian' => ['id', 1],

            // Languages with 2 plural forms
            'English' => ['en', 2],
            'German' => ['de', 2],
            'Dutch' => ['nl', 2],
            'Swedish' => ['sv', 2],
            'Danish' => ['da', 2],
            'Norwegian' => ['nb', 2],
            'Icelandic' => ['is', 2],
            'Macedonian' => ['mk', 2],

            // Languages with 3 plural forms
            'Czech' => ['cs', 3],
            'Slovak' => ['sk', 3],
            'Romanian' => ['ro', 3],
            'Lithuanian' => ['lt', 3],
            'Latvian' => ['lv', 3],

            // Languages with 4 plural forms
            'Russian' => ['ru', 4],
            'Ukrainian' => ['uk', 4],
            'Polish' => ['pl', 4],
            'Croatian' => ['hr', 4],
            'Serbian' => ['sr', 4],
            'Italian' => ['it', 3],
            'Spanish' => ['es', 3],
            'French' => ['fr', 3],
            'Portuguese' => ['pt', 3],

            // Languages with 4 plural forms
            'Slovenian' => ['sl', 4],
            'Maltese' => ['mt', 4],
            'Scottish Gaelic' => ['gd', 4],
            'Hebrew' => ['he', 4],
            'Manx' => ['gv', 4],

            // Languages with 5 plural forms
            'Irish' => ['ga', 5],
            'Breton' => ['br', 5],

            // Languages with 6 plural forms
            'Arabic' => ['ar', 6],
            'Welsh' => ['cy', 6],
        ];
    }

    public function testGetPluralCountReturnsOneForUnknownLanguage(): void
    {
        // Unknown language should return 1 (default: no plural forms)
        self::assertSame(1, PluralRules::getPluralCount('xx'));
        self::assertSame(1, PluralRules::getPluralCount('unknown'));
    }

    public function testGetPluralCountWorksWithLocaleVariants(): void
    {
        // Should normalize locale variants and return the correct count
        self::assertSame(2, PluralRules::getPluralCount('en-US'));
        self::assertSame(2, PluralRules::getPluralCount('en-GB'));
        self::assertSame(2, PluralRules::getPluralCount('en_AU'));
        self::assertSame(3, PluralRules::getPluralCount('fr-FR'));
        self::assertSame(3, PluralRules::getPluralCount('fr_CA'));
        self::assertSame(3, PluralRules::getPluralCount('it-IT'));
        self::assertSame(4, PluralRules::getPluralCount('ru-RU'));
        self::assertSame(6, PluralRules::getPluralCount('ar-SA'));
    }

    public function testGetPluralCountIsCaseInsensitive(): void
    {
        // Should work with different case variations
        self::assertSame(2, PluralRules::getPluralCount('EN'));
        self::assertSame(2, PluralRules::getPluralCount('En'));
        self::assertSame(2, PluralRules::getPluralCount('EN-US'));
        self::assertSame(2, PluralRules::getPluralCount('en-us'));
    }

}



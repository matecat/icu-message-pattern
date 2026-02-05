<?php

declare(strict_types=1);

namespace Matecat\Tests\ICU;

use Matecat\ICU\PluralRules\PluralRules;
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
 * - Rule 8: Macedonian with 3 forms
 * - Rule 9: Maltese with 4 forms
 * - Rule 10: Latvian with 3 forms
 * - Rule 11: Polish with 3 forms
 * - Rule 12: Romanian with 3 forms
 * - Rule 13: Arabic with 6 forms
 * - Rule 14: Welsh with 4 forms
 * - Rule 15: Icelandic with 2 forms
 * - Rule 16: Scottish Gaelic with 4 forms
 * - Locale fallback mechanism (handling locale variants like en-US, fr_FR)
 * - Unknown locale handling
 */
final class PluralRulesTest extends TestCase
{
    // =========================================================================
    // Rule 0: No plural forms (nplurals=1; plural=0)
    // Languages: Japanese, Chinese, Korean, Vietnamese, Thai, Indonesian, etc.
    // =========================================================================

    #[DataProvider('rule0Provider')]
    public function testRule0NoPluralForms(string $locale, int $n, int $expected): void
    {
        self::assertSame($expected, PluralRules::calculate($locale, $n));
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

    #[DataProvider('rule1Provider')]
    public function testRule1TwoFormsSingularOne(string $locale, int $n, int $expected): void
    {
        self::assertSame($expected, PluralRules::calculate($locale, $n));
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
            // Spanish
            ['es', 0, 1],
            ['es', 1, 0],
            ['es', 2, 1],
            // Italian
            ['it', 0, 1],
            ['it', 1, 0],
            ['it', 2, 1],
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
            // Hebrew
            ['he', 0, 1],
            ['he', 1, 0],
            ['he', 2, 1],
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

    #[DataProvider('rule2Provider')]
    public function testRule2TwoFormsSingularZeroOne(string $locale, int $n, int $expected): void
    {
        self::assertSame($expected, PluralRules::calculate($locale, $n));
    }

    /**
     * @return array<array<string|int>>
     */
    public static function rule2Provider(): array
    {
        return [
            // French
            ['fr', 0, 0],  // "0 élément" (singular in French)
            ['fr', 1, 0],  // "1 élément"
            ['fr', 2, 1],  // "2 éléments"
            ['fr', 5, 1],
            ['fr', 100, 1],
            // Brazilian Portuguese (pt uses rule 1, but pt-BR behavior)
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

    #[DataProvider('rule3Provider')]
    public function testRule3Slavic(string $locale, int $n, int $expected): void
    {
        self::assertSame($expected, PluralRules::calculate($locale, $n));
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

    #[DataProvider('rule4Provider')]
    public function testRule4CzechSlovak(string $locale, int $n, int $expected): void
    {
        self::assertSame($expected, PluralRules::calculate($locale, $n));
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

    #[DataProvider('rule5Provider')]
    public function testRule5Irish(string $locale, int $n, int $expected): void
    {
        self::assertSame($expected, PluralRules::calculate($locale, $n));
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

    #[DataProvider('rule6Provider')]
    public function testRule6Lithuanian(string $locale, int $n, int $expected): void
    {
        self::assertSame($expected, PluralRules::calculate($locale, $n));
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

    #[DataProvider('rule7Provider')]
    public function testRule7Slovenian(string $locale, int $n, int $expected): void
    {
        self::assertSame($expected, PluralRules::calculate($locale, $n));
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
    // Rule 8: Macedonian (nplurals=3)
    // =========================================================================

    #[DataProvider('rule8Provider')]
    public function testRule8Macedonian(string $locale, int $n, int $expected): void
    {
        self::assertSame($expected, PluralRules::calculate($locale, $n));
    }

    /**
     * @return array<array<string|int>>
     */
    public static function rule8Provider(): array
    {
        return [
            ['mk', 0, 2],   // other
            ['mk', 1, 0],   // one (n%10 == 1, n%100 != 11)
            ['mk', 2, 1],   // two (n%10 == 2, n%100 != 12)
            ['mk', 3, 2],   // other
            ['mk', 10, 2],  // other
            ['mk', 11, 2],  // other (special case)
            ['mk', 12, 2],  // other (special case)
            ['mk', 21, 0],  // one
            ['mk', 22, 1],  // two
            ['mk', 31, 0],  // one
            ['mk', 32, 1],  // two
            ['mk', 111, 2], // other (special case)
            ['mk', 112, 2], // other (special case)
        ];
    }

    // =========================================================================
    // Rule 9: Maltese (nplurals=4)
    // =========================================================================

    #[DataProvider('rule9Provider')]
    public function testRule9Maltese(string $locale, int $n, int $expected): void
    {
        self::assertSame($expected, PluralRules::calculate($locale, $n));
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
    // Rule 10: Latvian (nplurals=3)
    // =========================================================================

    #[DataProvider('rule10Provider')]
    public function testRule10Latvian(string $locale, int $n, int $expected): void
    {
        self::assertSame($expected, PluralRules::calculate($locale, $n));
    }

    /**
     * @return array<array<string|int>>
     */
    public static function rule10Provider(): array
    {
        return [
            ['lv', 0, 2],   // zero
            ['lv', 1, 0],   // one (n%10 == 1, n%100 != 11)
            ['lv', 2, 1],   // other
            ['lv', 10, 1],  // other
            ['lv', 11, 1],  // other (special case)
            ['lv', 21, 0],  // one
            ['lv', 31, 0],  // one
            ['lv', 100, 1], // other
            ['lv', 101, 0], // one
            ['lv', 111, 1], // other (special case)
        ];
    }

    // =========================================================================
    // Rule 11: Polish (nplurals=3)
    // =========================================================================

    #[DataProvider('rule11Provider')]
    public function testRule11Polish(string $locale, int $n, int $expected): void
    {
        self::assertSame($expected, PluralRules::calculate($locale, $n));
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

    #[DataProvider('rule12Provider')]
    public function testRule12Romanian(string $locale, int $n, int $expected): void
    {
        self::assertSame($expected, PluralRules::calculate($locale, $n));
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

    #[DataProvider('rule13Provider')]
    public function testRule13Arabic(string $locale, int $n, int $expected): void
    {
        self::assertSame($expected, PluralRules::calculate($locale, $n));
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
    // Rule 14: Welsh (nplurals=4)
    // =========================================================================

    #[DataProvider('rule14Provider')]
    public function testRule14Welsh(string $locale, int $n, int $expected): void
    {
        self::assertSame($expected, PluralRules::calculate($locale, $n));
    }

    /**
     * @return array<array<string|int>>
     */
    public static function rule14Provider(): array
    {
        return [
            ['cy', 0, 2],   // other
            ['cy', 1, 0],   // one
            ['cy', 2, 1],   // two
            ['cy', 3, 2],   // other
            ['cy', 4, 2],   // other
            ['cy', 5, 2],   // other
            ['cy', 6, 2],   // other
            ['cy', 7, 2],   // other
            ['cy', 8, 3],   // few (n == 8 or n == 11)
            ['cy', 9, 2],   // other
            ['cy', 10, 2],  // other
            ['cy', 11, 3],  // few
            ['cy', 12, 2],  // other
            ['cy', 100, 2], // other
        ];
    }

    // =========================================================================
    // Rule 15: Icelandic (nplurals=2)
    // =========================================================================

    #[DataProvider('rule15Provider')]
    public function testRule15Icelandic(string $locale, int $n, int $expected): void
    {
        self::assertSame($expected, PluralRules::calculate($locale, $n));
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

    #[DataProvider('rule16Provider')]
    public function testRule16ScottishGaelic(string $locale, int $n, int $expected): void
    {
        self::assertSame($expected, PluralRules::calculate($locale, $n));
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
    // Locale Fallback Tests
    // =========================================================================

    public function testLocaleFallbackWithHyphen(): void
    {
        // Should extract 'en' from 'en-US' and use English rules
        self::assertSame(0, PluralRules::calculate('en-US', 1));
        self::assertSame(1, PluralRules::calculate('en-US', 2));

        // Should extract 'fr' from 'fr-CA' and use French rules
        self::assertSame(0, PluralRules::calculate('fr-CA', 0));
        self::assertSame(0, PluralRules::calculate('fr-CA', 1));
        self::assertSame(1, PluralRules::calculate('fr-CA', 2));

        // Should extract 'ar' from 'ar-SA' and use Arabic rules
        self::assertSame(0, PluralRules::calculate('ar-SA', 0));
        self::assertSame(1, PluralRules::calculate('ar-SA', 1));
        self::assertSame(2, PluralRules::calculate('ar-SA', 2));
    }

    public function testLocaleFallbackWithUnderscore(): void
    {
        // Should extract 'en' from 'en_GB' and use English rules
        self::assertSame(0, PluralRules::calculate('en_GB', 1));
        self::assertSame(1, PluralRules::calculate('en_GB', 2));

        // Should extract 'de' from 'de_AT' and use German rules
        self::assertSame(0, PluralRules::calculate('de_AT', 1));
        self::assertSame(1, PluralRules::calculate('de_AT', 2));

        // Should extract 'ru' from 'ru_RU' and use Russian rules
        self::assertSame(0, PluralRules::calculate('ru_RU', 1));
        self::assertSame(1, PluralRules::calculate('ru_RU', 2));
        self::assertSame(2, PluralRules::calculate('ru_RU', 5));
    }

    public function testLocaleIsCaseInsensitive(): void
    {
        // Uppercase should work
        self::assertSame(0, PluralRules::calculate('EN', 1));
        self::assertSame(1, PluralRules::calculate('EN', 2));

        // Mixed case should work
        self::assertSame(0, PluralRules::calculate('En', 1));
        self::assertSame(0, PluralRules::calculate('eN', 1));

        // Uppercase with region
        self::assertSame(0, PluralRules::calculate('EN-US', 1));
        self::assertSame(0, PluralRules::calculate('FR-FR', 1));
    }

    // =========================================================================
    // Unknown Locale Tests
    // =========================================================================

    public function testUnknownLocaleReturnsZero(): void
    {
        // Unknown locale should return 0 (no plural forms behavior)
        self::assertSame(0, PluralRules::calculate('xyz', 1));
        self::assertSame(0, PluralRules::calculate('xyz', 2));
        self::assertSame(0, PluralRules::calculate('xyz', 100));

        // Unknown locale with region
        self::assertSame(0, PluralRules::calculate('xyz-ZZ', 1));
        self::assertSame(0, PluralRules::calculate('xyz_ZZ', 1));
    }

    // =========================================================================
    // Edge Cases and Boundary Tests
    // =========================================================================

    public function testLargeNumbers(): void
    {
        // English - large numbers
        self::assertSame(1, PluralRules::calculate('en', 1000000));
        self::assertSame(1, PluralRules::calculate('en', PHP_INT_MAX));

        // Russian - large numbers should still follow rules
        self::assertSame(0, PluralRules::calculate('ru', 1000001)); // ends in 1, not 11
        self::assertSame(1, PluralRules::calculate('ru', 1000002)); // ends in 2
        self::assertSame(2, PluralRules::calculate('ru', 1000005)); // ends in 5
        self::assertSame(2, PluralRules::calculate('ru', 1000011)); // ends in 11
    }

    public function testZero(): void
    {
        // Different languages handle zero differently
        self::assertSame(1, PluralRules::calculate('en', 0));  // "0 items" (plural)
        self::assertSame(0, PluralRules::calculate('fr', 0));  // "0 élément" (singular in French)
        self::assertSame(0, PluralRules::calculate('ar', 0));  // "zero" form
        self::assertSame(0, PluralRules::calculate('ja', 0));  // no plural
    }

    // =========================================================================
    // Specific Language Code Tests (ISO 639-1 and ISO 639-3)
    // =========================================================================

    public function testThreeLetterLanguageCodes(): void
    {
        // Acehnese (ace) - rule 0
        self::assertSame(0, PluralRules::calculate('ace', 1));
        self::assertSame(0, PluralRules::calculate('ace', 2));

        // Asturian (ast) - rule 1
        self::assertSame(0, PluralRules::calculate('ast', 1));
        self::assertSame(1, PluralRules::calculate('ast', 2));

        // Filipino (fil) - rule 2
        self::assertSame(0, PluralRules::calculate('fil', 0));
        self::assertSame(0, PluralRules::calculate('fil', 1));
        self::assertSame(1, PluralRules::calculate('fil', 2));
    }

    // =========================================================================
    // Comprehensive Comparison Tests
    // =========================================================================

    public function testRussianVsPolish(): void
    {
        // Russian and Polish have similar but different rules
        // For n=21: Russian returns 0 (one), Polish returns 2 (many)
        self::assertSame(0, PluralRules::calculate('ru', 21));
        self::assertSame(2, PluralRules::calculate('pl', 21));

        // For n=101: Russian returns 0 (one), Polish returns 2 (many)
        self::assertSame(0, PluralRules::calculate('ru', 101));
        self::assertSame(2, PluralRules::calculate('pl', 101));

        // Both agree on n=2, n=3, n=4
        self::assertSame(1, PluralRules::calculate('ru', 2));
        self::assertSame(1, PluralRules::calculate('pl', 2));
    }

    public function testCzechVsRussian(): void
    {
        // Czech and Russian differ significantly
        // For n=21: Russian returns 0 (one), Czech returns 2 (other)
        self::assertSame(0, PluralRules::calculate('ru', 21));
        self::assertSame(2, PluralRules::calculate('cs', 21));

        // Both use "few" for 2-4
        self::assertSame(1, PluralRules::calculate('ru', 2));
        self::assertSame(1, PluralRules::calculate('cs', 2));
    }

    public function testEnglishVsFrench(): void
    {
        // French treats 0 as singular, English treats it as plural
        self::assertSame(1, PluralRules::calculate('en', 0));
        self::assertSame(0, PluralRules::calculate('fr', 0));

        // Both treat 1 as singular
        self::assertSame(0, PluralRules::calculate('en', 1));
        self::assertSame(0, PluralRules::calculate('fr', 1));

        // Both treat 2+ as plural
        self::assertSame(1, PluralRules::calculate('en', 2));
        self::assertSame(1, PluralRules::calculate('fr', 2));
    }

    // =========================================================================
    // getCategoryName() Tests
    // =========================================================================

    #[DataProvider('getCategoryNameProvider')]
    public function testGetCategoryName(string $locale, int $n, string $expected): void
    {
        self::assertSame($expected, PluralRules::getCategoryName($locale, $n));
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

            // Rule 2: Two forms (n > 1) - "one" or "other"
            ['fr', 0, PluralRules::CATEGORY_ONE],
            ['fr', 1, PluralRules::CATEGORY_ONE],
            ['fr', 2, PluralRules::CATEGORY_OTHER],
            ['pt', 1, PluralRules::CATEGORY_ONE],

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

            // Rule 8: Macedonian - "one", "two", "other"
            ['mk', 1, PluralRules::CATEGORY_ONE],
            ['mk', 21, PluralRules::CATEGORY_ONE],
            ['mk', 2, PluralRules::CATEGORY_TWO],
            ['mk', 22, PluralRules::CATEGORY_TWO],
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

            // Rule 10: Latvian - "one", "other", "zero"
            ['lv', 1, PluralRules::CATEGORY_ONE],
            ['lv', 21, PluralRules::CATEGORY_ONE],
            ['lv', 2, PluralRules::CATEGORY_OTHER],
            ['lv', 11, PluralRules::CATEGORY_OTHER],
            ['lv', 0, PluralRules::CATEGORY_ZERO],

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

            // Rule 14: Welsh - "one", "two", "few", "other"
            ['cy', 1, PluralRules::CATEGORY_ONE],
            ['cy', 2, PluralRules::CATEGORY_TWO],
            ['cy', 3, PluralRules::CATEGORY_FEW],
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
        self::assertSame($expected, PluralRules::getCategories($locale));
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
            ['es', [PluralRules::CATEGORY_ONE, PluralRules::CATEGORY_OTHER]],
            ['it', [PluralRules::CATEGORY_ONE, PluralRules::CATEGORY_OTHER]],

            // Rule 2: Two forms (n > 1)
            ['fr', [PluralRules::CATEGORY_ONE, PluralRules::CATEGORY_OTHER]],
            ['pt', [PluralRules::CATEGORY_ONE, PluralRules::CATEGORY_OTHER]],

            // Rule 3: Slavic
            ['ru', [PluralRules::CATEGORY_ONE, PluralRules::CATEGORY_FEW, PluralRules::CATEGORY_MANY]],
            ['uk', [PluralRules::CATEGORY_ONE, PluralRules::CATEGORY_FEW, PluralRules::CATEGORY_MANY]],
            ['sr', [PluralRules::CATEGORY_ONE, PluralRules::CATEGORY_FEW, PluralRules::CATEGORY_MANY]],

            // Rule 4: Czech/Slovak
            ['cs', [PluralRules::CATEGORY_ONE, PluralRules::CATEGORY_FEW, PluralRules::CATEGORY_OTHER]],
            ['sk', [PluralRules::CATEGORY_ONE, PluralRules::CATEGORY_FEW, PluralRules::CATEGORY_OTHER]],

            // Rule 5: Irish
            ['ga', [PluralRules::CATEGORY_ONE, PluralRules::CATEGORY_TWO, PluralRules::CATEGORY_FEW, PluralRules::CATEGORY_MANY, PluralRules::CATEGORY_OTHER]],

            // Rule 6: Lithuanian
            ['lt', [PluralRules::CATEGORY_ONE, PluralRules::CATEGORY_FEW, PluralRules::CATEGORY_OTHER]],

            // Rule 7: Slovenian
            ['sl', [PluralRules::CATEGORY_ONE, PluralRules::CATEGORY_TWO, PluralRules::CATEGORY_FEW, PluralRules::CATEGORY_OTHER]],

            // Rule 8: Macedonian
            ['mk', [PluralRules::CATEGORY_ONE, PluralRules::CATEGORY_TWO, PluralRules::CATEGORY_OTHER]],

            // Rule 9: Maltese
            ['mt', [PluralRules::CATEGORY_ONE, PluralRules::CATEGORY_FEW, PluralRules::CATEGORY_MANY, PluralRules::CATEGORY_OTHER]],

            // Rule 10: Latvian
            ['lv', [PluralRules::CATEGORY_ONE, PluralRules::CATEGORY_OTHER, PluralRules::CATEGORY_ZERO]],

            // Rule 11: Polish
            ['pl', [PluralRules::CATEGORY_ONE, PluralRules::CATEGORY_FEW, PluralRules::CATEGORY_MANY]],

            // Rule 12: Romanian
            ['ro', [PluralRules::CATEGORY_ONE, PluralRules::CATEGORY_FEW, PluralRules::CATEGORY_OTHER]],

            // Rule 13: Arabic
            ['ar', [PluralRules::CATEGORY_ZERO, PluralRules::CATEGORY_ONE, PluralRules::CATEGORY_TWO, PluralRules::CATEGORY_FEW, PluralRules::CATEGORY_MANY, PluralRules::CATEGORY_OTHER]],

            // Rule 14: Welsh
            ['cy', [PluralRules::CATEGORY_ONE, PluralRules::CATEGORY_TWO, PluralRules::CATEGORY_FEW, PluralRules::CATEGORY_OTHER]],

            // Rule 15: Icelandic
            ['is', [PluralRules::CATEGORY_ONE, PluralRules::CATEGORY_OTHER]],

            // Rule 16: Scottish Gaelic
            ['gd', [PluralRules::CATEGORY_ONE, PluralRules::CATEGORY_TWO, PluralRules::CATEGORY_FEW, PluralRules::CATEGORY_OTHER]],

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
        self::assertSame(PluralRules::CATEGORY_ONE, PluralRules::getCategoryName('en_US', 1));
        self::assertSame(PluralRules::CATEGORY_OTHER, PluralRules::getCategoryName('en_US', 2));
        self::assertSame(PluralRules::CATEGORY_ONE, PluralRules::getCategoryName('fr_FR', 0));
        self::assertSame(PluralRules::CATEGORY_OTHER, PluralRules::getCategoryName('fr_FR', 2));

        // Test with hyphen separator
        self::assertSame(PluralRules::CATEGORY_ONE, PluralRules::getCategoryName('en-GB', 1));
        self::assertSame(PluralRules::CATEGORY_OTHER, PluralRules::getCategoryName('en-GB', 5));
        self::assertSame(PluralRules::CATEGORY_FEW, PluralRules::getCategoryName('ru-RU', 2));

        // Test case insensitivity
        self::assertSame(PluralRules::CATEGORY_ONE, PluralRules::getCategoryName('EN', 1));
        self::assertSame(PluralRules::CATEGORY_ONE, PluralRules::getCategoryName('En_Us', 1));
    }

    public function testGetCategoriesWithLocaleVariants(): void
    {
        // Test with underscore separator
        self::assertSame(
            [PluralRules::CATEGORY_ONE, PluralRules::CATEGORY_OTHER],
            PluralRules::getCategories('en_US')
        );
        self::assertSame(
            [PluralRules::CATEGORY_ONE, PluralRules::CATEGORY_FEW, PluralRules::CATEGORY_MANY],
            PluralRules::getCategories('ru_RU')
        );

        // Test with hyphen separator
        self::assertSame(
            [PluralRules::CATEGORY_ONE, PluralRules::CATEGORY_OTHER],
            PluralRules::getCategories('en-GB')
        );

        // Test case insensitivity
        self::assertSame(
            [PluralRules::CATEGORY_ONE, PluralRules::CATEGORY_OTHER],
            PluralRules::getCategories('EN')
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
}

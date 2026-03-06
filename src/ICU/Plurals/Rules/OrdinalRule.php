<?php

declare(strict_types=1);

namespace Matecat\ICU\Plurals\Rules;

use Matecat\ICU\Plurals\PluralOperands;
use Matecat\ICU\Plurals\PluralRules;

/**
 * Ordinal plural rules for all inputs.
 *
 * CLDR ordinal rules are integer-only — the integer part of any decimal input
 * is used. This class handles all ordinal evaluation.
 *
 * @see PluralRules For the public facade API.
 */
class OrdinalRule
{

    /**
     * Mapping of the ordinal rule group => array of CLDR ordinal category names.
     *
     * CLDR ordinal rules are separate from cardinal rules. Many languages
     * that have simple cardinal rules (like English with one/other) have
     * more complex ordinal rules (English ordinal: one/two/few/other for 1st/2nd/3rd/4th).
     *
     * @see https://www.unicode.org/cldr/charts/49/supplemental/language_plural_rules.html
     * @var array<int, array<int, string>>
     */
    public static array $categoryMap = [
        // Only "other" (no ordinal distinction)
        // Locales: all locales with ordinal=0 (default)
        0  => PluralRules::CATEGORIES_OTHER,

        // one/other (French-like: n = 1)
        // Locales: bal, fil, fr, ga, ht, hy, lo, mo, ms, ro, tl, vi, zsm
        2  => PluralRules::CATEGORIES_ONE_OTHER,

        // one/two/few/other (English-like: 1st/2nd/3rd/4th)
        // Locales: en
        1  => PluralRules::CATEGORIES_ONE_TWO_FEW_OTHER,

        // one/two/few/other (Scottish Gaelic)
        // Locales: gd
        16 => PluralRules::CATEGORIES_ONE_TWO_FEW_OTHER,

        // one/two/many/other (Macedonian)
        // Locales: mk
        8  => PluralRules::CATEGORIES_ONE_TWO_MANY_OTHER,

        // one/two/few/many/other (Bengali/Assamese ordinals — CLDR 49)
        // Locales: as, asm, bn
        23 => PluralRules::CATEGORIES_ONE_TWO_FEW_MANY_OTHER,

        // one/two/few/many/other (Gujarati/Hindi ordinals — CLDR 49)
        // Locales: gu, hi
        24 => PluralRules::CATEGORIES_ONE_TWO_FEW_MANY_OTHER,

        // one/two/few/many/other (Odia ordinals)
        // Locales: or, ory
        27 => PluralRules::CATEGORIES_ONE_TWO_FEW_MANY_OTHER,

        // one/two/few/other (Marathi/Konkani)
        // Locales: kok, mr
        26 => PluralRules::CATEGORIES_ONE_TWO_FEW_OTHER,

        // many/other (Italian: n=8,11,80,800)
        // Locales: it, lld, sc, vec
        20 => PluralRules::CATEGORIES_MANY_OTHER,

        // many/other (Kazakh: n%10=6,9 or n%10=0 and n≠0)
        // Locales: kk
        21 => PluralRules::CATEGORIES_MANY_OTHER,

        // many/other (Ligurian/Sicilian: n=8,11,80..89,800..899)
        // Locales: lij, scn
        42 => PluralRules::CATEGORIES_MANY_OTHER,

        // one/many/other (Albanian: n=1 / n%10=4 except 14)
        // Locales: als, sq
        30 => PluralRules::CATEGORIES_ONE_MANY_OTHER,

        // one/many/other (Cornish)
        // Locales: kw
        32 => PluralRules::CATEGORIES_ONE_MANY_OTHER,

        // one/many/other (Georgian)
        // Locales: ka
        40 => PluralRules::CATEGORIES_ONE_MANY_OTHER,

        // few/other (Ukrainian: n%10=3, n%100≠13)
        // Locales: uk
        22 => PluralRules::CATEGORIES_FEW_OTHER,

        // few/other (Turkmen: n%10=6,9 or n=10)
        // Locales: tk
        43 => PluralRules::CATEGORIES_FEW_OTHER,

        // one/other (Nepali: n=1..4)
        // Locales: ne
        29 => PluralRules::CATEGORIES_ONE_OTHER,

        // one/other (Spanish: n%10=1,3 and n%100≠11)
        // Locales: es
        34 => PluralRules::CATEGORIES_ONE_OTHER,

        // one/other (Hungarian: n=1,5)
        // Locales: hu
        35 => PluralRules::CATEGORIES_ONE_OTHER,

        // one/other (Swedish: n%10=1,2 and n%100≠11,12)
        // Locales: sv
        41 => PluralRules::CATEGORIES_ONE_OTHER,

        // zero/one/few/other (Anii)
        // Locales: blo
        31 => PluralRules::CATEGORIES_ZERO_ONE_FEW_OTHER,

        // few/other (Afrikaans: i%100=2..19)
        // Locales: af
        33 => PluralRules::CATEGORIES_FEW_OTHER,

        // one/few/many/other (Azerbaijani)
        // Locales: az, azb, azj
        36 => PluralRules::CATEGORIES_ONE_FEW_MANY_OTHER,

        // few/other (Belarusian: n%10=2,3 and n%100≠12,13)
        // Locales: be
        37 => PluralRules::CATEGORIES_FEW_OTHER,

        // zero/one/two/few/many/other (Bulgarian — CLDR 49)
        // Locales: bg
        38 => PluralRules::CATEGORIES_ZERO_ONE_TWO_FEW_MANY_OTHER,

        // one/two/few/other (Catalan: n=1,3 / n=2 / n=4)
        // Locales: ca, cav
        39 => PluralRules::CATEGORIES_ONE_TWO_FEW_OTHER,

        // zero/one/two/few/many/other (Welsh)
        // Locales: cy
        14 => PluralRules::CATEGORIES_ZERO_ONE_TWO_FEW_MANY_OTHER,
    ];

    /**
     * Returns the ordinal plural form index for the given locale and number.
     *
     * @param string $locale The locale code.
     * @param int $n The ordinal number.
     * @return int The ordinal form index.
     */
    public static function getFormIndex(string $locale, int $n): int
    {
        $ruleGroup = PluralRules::getRuleGroup($locale, 'ordinal');

        return match ($ruleGroup) {

            // Rule 1: English ordinals (one/two/few/other)
            // Locales: en
            // one: n % 10 = 1 and n % 100 != 11 (1st, 21st, 31st...)
            // two: n % 10 = 2 and n % 100 != 12 (2nd, 22nd, 32nd...)
            // few: n % 10 = 3 and n % 100 != 13 (3rd, 23rd, 33rd...)
            // other: everything else (4th, 5th, 11th, 12th, 13th...)
            1 => match (true) {
                $n % 10 === 1 && $n % 100 !== 11 => 0,
                $n % 10 === 2 && $n % 100 !== 12 => 1,
                $n % 10 === 3 && $n % 100 !== 13 => 2,
                default => 3,
            },

            // Rule 2: French-like ordinals (one/other)
            // Locales: bal, fil, fr, ga, ht, hy, lo, mo, ms, ro, tl, vi, zsm
            // one: n = 1
            2 => $n === 1 ? 0 : 1,

            // Rule 8: Macedonian ordinals (one/two/many/other)
            // Locales: mk
            // one: i % 10 = 1 and i % 100 != 11
            // two: i % 10 = 2 and i % 100 != 12
            // many: i % 10 = 7,8 and i % 100 != 17,18
            8 => match (true) {
                $n % 10 === 1 && $n % 100 !== 11 => 0,
                $n % 10 === 2 && $n % 100 !== 12 => 1,
                in_array($n % 10, [7, 8], true) && !in_array($n % 100, [17, 18], true) => 2,
                default => 3,
            },

            // Rule 14: Welsh ordinals (zero/one/two/few/many/other)
            // Locales: cy
            // zero: n = 0,7,8,9
            // one: n = 1
            // two: n = 2
            // few: n = 3,4
            // many: n = 5,6
            14 => match ($n) { // @codeCoverageIgnore strange behavior of curly brackets and match in code coverage,
                0, 7, 8, 9 => 0,  // zero
                1 => 1,           // one
                2 => 2,           // two
                3, 4 => 3,        // few
                5, 6 => 4,        // many
                default => 5,     // other
            },

            // Rule 16: Scottish Gaelic ordinals (one/two/few/other)
            // Locales: gd
            // one: n = 1,11
            // two: n = 2,12
            // few: n = 3,13
            16 => match ($n) { // @codeCoverageIgnore strange behavior of curly brackets and match in code coverage,
                1, 11 => 0,       // one
                2, 12 => 1,       // two
                3, 13 => 2,       // few
                default => 3,     // other
            },

            // Rule 20: Italian ordinals (many/other)
            // Locales: it, lld, sc, vec
            // many: n = 8,11,80,800
            20 => in_array($n, [8, 11, 80, 800], true) ? 0 : 1,

            // Rule 21: Kazakh ordinals (many/other)
            // Locales: kk
            // many: n % 10 = 6 or n % 10 = 9 or n % 10 = 0 and n != 0
            21 => in_array($n % 10, [0, 6, 9], true) && $n !== 0 ? 0 : 1,

            // Rule 22: Ukrainian ordinals (few/other)
            // Locales: uk
            // few: n % 10 = 3 and n % 100 != 13
            22 => ($n % 10 === 3 && $n % 100 !== 13) ? 0 : 1,

            // Rule 23: Bengali/Assamese ordinals (one/two/few/many/other)
            // Locales: as, asm, bn
            // one: n = 1,5,7,8,9,10
            // two: n = 2,3
            // few: n = 4
            // many: n = 6
            23 => match (true) {
                in_array($n, [1, 5, 7, 8, 9, 10], true) => 0, // one
                in_array($n, [2, 3], true) => 1,               // two
                $n === 4 => 2,                                   // few
                $n === 6 => 3,                                   // many
                default => 4,                                    // other
            },

            // Rule 24: Gujarati/Hindi ordinals (one/two/few/many/other)
            // Locales: gu, hi
            // one: n = 1
            // two: n = 2,3
            // few: n = 4
            // many: n = 6
            24 => match ($n) { // @codeCoverageIgnore strange behavior of curly brackets and match in code coverage,
                1 => 0,           // one
                2, 3 => 1,        // two
                4 => 2,           // few
                6 => 3,           // many
                default => 4,     // other
            },

            // Rule 26: Marathi/Konkani ordinals (one/two/few/other)
            // Locales: kok, mr
            // one: n = 1
            // two: n = 2,3
            // few: n = 4
            26 => match ($n) { // @codeCoverageIgnore strange behavior of curly brackets and match in code coverage,
                1 => 0,           // one
                2, 3 => 1,        // two
                4 => 2,           // few
                default => 3,     // other
            },

            // Rule 27: Odia ordinals (one/two/few/many/other)
            // Locales: or, ory
            // one: n = 1,5,7..9
            // two: n = 2,3
            // few: n = 4
            // many: n = 6
            27 => match (true) {
                $n === 1 || $n === 5 || ($n >= 7 && $n <= 9) => 0,
                in_array($n, [2, 3], true) => 1,
                $n === 4 => 2,
                $n === 6 => 3,
                default => 4,
            },

            // Rule 29: Nepali ordinals (one/other)
            // Locales: ne
            // one: n = 1..4
            29 => $n >= 1 && $n <= 4 ? 0 : 1,

            // Rule 30: Albanian ordinals (one/many/other)
            // Locales: als, sq
            // one: n = 1
            // many: n % 10 = 4 and n % 100 != 14
            30 => match (true) {
                $n === 1 => 0,                                   // one
                $n % 10 === 4 && $n % 100 !== 14 => 1,          // many
                default => 2,                                     // other
            },

            // Rule 31: Anii ordinals (zero/one/few/other)
            // Locales: blo
            // zero: i = 0
            // one: i = 1
            // few: i = 2..6
            31 => match (true) {
                $n === 0 => 0,
                $n === 1 => 1,
                $n >= 2 && $n <= 6 => 2,
                default => 3,
            },

            // Rule 32: Cornish ordinals (one/many/other)
            // Locales: kw
            // one: n = 1..4 or n % 100 = 1..4,21..24,41..44,61..64,81..84
            // many: n = 5 or n % 100 = 5
            32 => match (true) {
                $n >= 1 && $n <= 4
                    || in_array($n % 100, [1, 2, 3, 4, 21, 22, 23, 24, 41, 42, 43, 44, 61, 62, 63, 64, 81, 82, 83, 84], true) => 0,
                $n === 5 || $n % 100 === 5 => 1,
                default => 2,
            },

            // Rule 33: Afrikaans ordinals (few/other)
            // Locales: af
            // few: i % 100 = 2..19
            33 => ($n % 100 >= 2 && $n % 100 <= 19) ? 0 : 1,

            // Rule 34: Spanish ordinals (one/other)
            // Locales: es
            // one: n % 10 = 1,3 and n % 100 != 11
            34 => (in_array($n % 10, [1, 3], true) && $n % 100 !== 11) ? 0 : 1,

            // Rule 35: Hungarian ordinals (one/other)
            // Locales: hu
            // one: n = 1,5
            35 => in_array($n, [1, 5], true) ? 0 : 1,

            // Rule 36: Azerbaijani ordinals (one/few/many/other)
            // Locales: az, azb, azj
            // one: i % 10 = 1,2,5,7,8 or i % 100 = 20,50,70,80
            // few: i % 10 = 3,4 or i % 1000 = 100,200,...,900
            // many: i = 0 or i % 10 = 6 or i % 100 = 40,60,90
            36 => match (true) {
                in_array($n % 10, [1, 2, 5, 7, 8], true)
                    || in_array($n % 100, [20, 50, 70, 80], true) => 0,  // one
                in_array($n % 10, [3, 4], true)
                    || in_array($n % 1000, [100, 200, 300, 400, 500, 600, 700, 800, 900], true) => 1,  // few
                $n === 0 || $n % 10 === 6
                    || in_array($n % 100, [40, 60, 90], true) => 2,      // many
                default => 3,                                              // other
            },

            // Rule 37: Belarusian ordinals (few/other)
            // Locales: be
            // few: n % 10 = 2,3 and n % 100 != 12,13
            37 => (in_array($n % 10, [2, 3], true) && !in_array($n % 100, [12, 13], true)) ? 0 : 1,

            // Rule 38: Bulgarian ordinals (zero/one/two/few/many/other)
            // Locales: bg
            38 => self::calculateBulgarianOrdinal($n),

            // Rule 39: Catalan ordinals (one/two/few/other)
            // Locales: ca, cav
            // one: n = 1,3
            // two: n = 2
            // few: n = 4
            39 => match ($n) { // @codeCoverageIgnore strange behavior of curly brackets and match in code coverage,
                1, 3 => 0,        // one
                2 => 1,           // two
                4 => 2,           // few
                default => 3,     // other
            },

            // Rule 40: Georgian ordinals (one/many/other)
            // Locales: ka
            // one: i = 1
            // many: i = 0 or i % 100 = 2..20,40,60,80
            40 => match (true) {
                $n === 1 => 0,    // one
                $n === 0 || ($n % 100 >= 2 && $n % 100 <= 20)
                    || in_array($n % 100, [40, 60, 80], true) => 1,  // many
                default => 2,     // other
            },

            // Rule 41: Swedish ordinals (one/other)
            // Locales: sv
            // one: n % 10 = 1,2 and n % 100 != 11,12
            41 => (in_array($n % 10, [1, 2], true) && !in_array($n % 100, [11, 12], true)) ? 0 : 1,

            // Rule 42: Ligurian/Sicilian ordinals (many/other)
            // Locales: lij, scn
            // many: n = 8,11,80..89,800..899
            42 => ($n === 8 || $n === 11 || ($n >= 80 && $n <= 89) || ($n >= 800 && $n <= 899)) ? 0 : 1,

            // Rule 43: Turkmen ordinals (few/other)
            // Locales: tk
            // few: n % 10 = 6,9 or n = 10
            43 => (in_array($n % 10, [6, 9], true) || $n === 10) ? 0 : 1,

            // Rule 0: Only "other" (no ordinal distinction)
            // All other locales — returns 0 (the only form index for "other")
            default => 0,

        };
    }

    /**
     * Returns the ordinal plural form index for the given locale and number,
     * supporting decimal input. CLDR ordinal rules are integer-only, so this
     * uses the integer part of the number.
     *
     * @param string $locale The locale code.
     * @param string|int|float $number The number to apply the rules to.
     * @return int The ordinal form index.
     */
    public static function getFormIndexForNumber(string $locale, string|int|float $number): int
    {
        $op = PluralOperands::from($number);

        // CLDR ordinal rules are all integer-based; use the integer part
        return self::getFormIndex($locale, $op->integerPart);
    }

    /**
     * Returns the CLDR ordinal category name for the given locale and integer number.
     *
     * @param string $locale The locale code.
     * @param int $n The ordinal number.
     * @return string The CLDR ordinal category name.
     */
    public static function getCategoryName(string $locale, int $n): string
    {
        $ordinalIndex = self::getFormIndex($locale, $n);
        $ruleGroup = PluralRules::getRuleGroup($locale, 'ordinal');

        return self::$categoryMap[$ruleGroup][$ordinalIndex] ?? PluralRules::CATEGORY_OTHER;
    }

    /**
     * Returns the CLDR ordinal category name for the given locale and number,
     * supporting decimal input.
     *
     * @param string $locale The locale code.
     * @param string|int|float $number The number to apply the rules to.
     * @return string The CLDR ordinal category name.
     */
    public static function getCategoryNameForNumber(string $locale, string|int|float $number): string
    {
        $ordinalIndex = self::getFormIndexForNumber($locale, $number);
        $ruleGroup = PluralRules::getRuleGroup($locale, 'ordinal');

        return self::$categoryMap[$ruleGroup][$ordinalIndex] ?? PluralRules::CATEGORY_OTHER;
    }

    /**
     * Returns all available CLDR ordinal categories for a given locale.
     *
     * @param string $locale The locale to get ordinal categories for.
     * @return array<string> Array of ordinal category names available for this locale.
     */
    public static function getCategories(string $locale): array
    {
        $ruleGroup = PluralRules::getRuleGroup($locale, 'ordinal');

        return self::$categoryMap[$ruleGroup] ?? [PluralRules::CATEGORY_OTHER];
    }

    /**
     * Calculate the ordinal plural form for Bulgarian (Rule 38 - CLDR 49)
     * zero: i % 100 = 0 and i != 0
     * one: i % 10 = 1 and i % 100 != 11
     * two: i % 10 = 2 and i % 100 != 12
     * few: i % 10 = 3,4 and i % 100 != 13,14
     * many: i % 10 = 7,8 and i % 100 != 17,18
     * other: everything else
     */
    private static function calculateBulgarianOrdinal(int $n): int
    {
        $n10  = $n % 10;
        $n100 = $n % 100;

        return match (true) {
            $n100 === 0 && $n !== 0 => 0,                                          // zero
            $n10 === 1 && $n100 !== 11 => 1,                                       // one
            $n10 === 2 && $n100 !== 12 => 2,                                       // two
            in_array($n10, [3, 4], true) && !in_array($n100, [13, 14], true) => 3, // few
            in_array($n10, [7, 8], true) && !in_array($n100, [17, 18], true) => 4, // many
            default => 5,                                                           // other
        };
    }
}


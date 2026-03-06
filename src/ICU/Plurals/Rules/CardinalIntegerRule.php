<?php

declare(strict_types=1);

namespace Matecat\ICU\Plurals\Rules;

use Matecat\ICU\Plurals\PluralRules;

/**
 * Cardinal plural rules for integer inputs.
 *
 * Evaluates which cardinal plural form index to use for a given integer number
 * in a specific locale. This class handles the integer-only fast path; for decimal
 * input with full CLDR operand evaluation, see {@see CardinalDecimalRule}.
 *
 * @see CardinalDecimalRule For decimal-aware cardinal rules.
 * @see PluralRules For the public facade API.
 */
class CardinalIntegerRule
{
    /**
     * Mapping of the plural rule group => array of category names indexed by plural form number.
     *
     * Each rule group returns indices 0, 1, 2, etc. from getFormIndex().
     * This map translates those indices to CLDR category names.
     *
     * Rules with identical category arrays share the same constant to reduce memory usage.
     * The rule number determines the calculation logic, not the category names.
     *
     * @var array<int, array<int, string>>
     */
    public static array $categoryMap = [
        // nplurals=1; only "other" (Asian languages, no plural forms)
        0  => PluralRules::CATEGORIES_OTHER,

        // nplurals=2; one/other (Germanic n!=1; French n>1; Macedonian; Icelandic; Filipino; Tamazight)
        1  => PluralRules::CATEGORIES_ONE_OTHER,
        2  => PluralRules::CATEGORIES_ONE_OTHER,
        8  => PluralRules::CATEGORIES_ONE_OTHER,
        15 => PluralRules::CATEGORIES_ONE_OTHER,
        25 => PluralRules::CATEGORIES_ONE_OTHER,
        26 => PluralRules::CATEGORIES_ONE_OTHER,

        // nplurals=4; one/few/many/other (Czech/Slovak — CLDR 49: "many" for decimals only)
        4  => PluralRules::CATEGORIES_ONE_FEW_MANY_OTHER,

        // nplurals=4; one/few/many/other (Lithuanian — CLDR 49: "many" for decimals only)
        6  => PluralRules::CATEGORIES_ONE_FEW_MANY_OTHER,

        // nplurals=3; one/few/other (Romanian; Moldavian; Tachelhit)
        12 => PluralRules::CATEGORIES_ONE_FEW_OTHER,
        23 => PluralRules::CATEGORIES_ONE_FEW_OTHER,

        // nplurals=3; one/many/other (Italian, Spanish, Catalan - CLDR 49: one = i = 1 and v = 0)
        20 => PluralRules::CATEGORIES_ONE_MANY_OTHER,

        // nplurals=3; one/many/other (French, Portuguese - CLDR 49: one = i = 0,1)
        29 => PluralRules::CATEGORIES_ONE_MANY_OTHER,

        // nplurals=3; one/two/other (Inuktitut, Sami, Nama, Swampy Cree)
        21 => PluralRules::CATEGORIES_ONE_TWO_OTHER,

        // nplurals=3; zero/one/other (Latvian; Colognian, Anii, Langi)
        10 => PluralRules::CATEGORIES_ZERO_ONE_OTHER,
        22 => PluralRules::CATEGORIES_ZERO_ONE_OTHER,

        // nplurals=4; one/few/many/other (Slavic; Polish)
        3  => PluralRules::CATEGORIES_ONE_FEW_MANY_OTHER,
        11 => PluralRules::CATEGORIES_ONE_FEW_MANY_OTHER,

        // nplurals=4; one/two/few/other (Slovenian; Scottish Gaelic)
        7  => PluralRules::CATEGORIES_ONE_TWO_FEW_OTHER,
        16 => PluralRules::CATEGORIES_ONE_TWO_FEW_OTHER,

        // nplurals=5; one/two/few/many/other (Manx — CLDR 49: "many" for decimals only)
        18 => PluralRules::CATEGORIES_ONE_TWO_FEW_MANY_OTHER,

        // nplurals=3; one/two/other (Hebrew — CLDR 49: removed "many")
        19 => PluralRules::CATEGORIES_ONE_TWO_OTHER,

        // nplurals=5; one/two/few/many/other (Irish; Breton)
        5  => PluralRules::CATEGORIES_ONE_TWO_FEW_MANY_OTHER,
        17 => PluralRules::CATEGORIES_ONE_TWO_FEW_MANY_OTHER,

        // nplurals=3; one/few/other (Slavic: Bosnian, Croatian, Serbian — CLDR 49)
        27 => PluralRules::CATEGORIES_ONE_FEW_OTHER,

        // nplurals=5; one/two/few/many/other (Maltese — CLDR 49)
        28 => PluralRules::CATEGORIES_ONE_TWO_FEW_MANY_OTHER,

        // nplurals=6; zero/one/two/few/many/other (Arabic; Welsh; Cornish)
        13 => PluralRules::CATEGORIES_ZERO_ONE_TWO_FEW_MANY_OTHER,
        14 => PluralRules::CATEGORIES_ZERO_ONE_TWO_FEW_MANY_OTHER,
        24 => PluralRules::CATEGORIES_ZERO_ONE_TWO_FEW_MANY_OTHER,
    ];

    /**
     * Returns the cardinal plural form index for the given locale corresponding
     * to the countable provided in $n.
     *
     * @param string $locale The locale to get the rule calculated for.
     * @param int $n The number to apply the rules to.
     * @param int|null $ruleGroup Optional pre-resolved rule group (used by PluralRules subclasses for late static binding).
     * @return int The plural rule number that should be used.
     */
    public static function getFormIndex(string $locale, int $n, ?int $ruleGroup = null): int
    {
        $ruleGroup ??= PluralRules::getRuleGroup($locale);

        return match ($ruleGroup) {

            // Rule 0 — nplurals=1; only "other" (no plural forms)
            // Locales: ace, ayr, ba, ban, bi, bjn, bm, bo, bod, bug, ch, chk, crh, dyu, dz,
            //   fj, fn, fon, gil, hmn, hnj, id, ig, ii, ja, jbo, jv, kac, kar, kbp, kde, kea,
            //   km, ko, kr, ksw, lkt, lo, mh, min, mos, ms, my, niu, nqo, osa, pau, pis, pon,
            //   ppk, sah, ses, sg, shn, sm, smo, su, sus, taq, th, tkl, tmh, to, ton, tpi, trv,
            //   tt, tvl, ty, vi, wls, wo, yo, yue, zh, zsm
            0 => 0,

            // Rule 2 — nplurals=2; plural=(n > 1)
            // CLDR: one = "i = 0 or n = 1" / "i = 0,1" / "n = 0..1" / "n = 0,1 or i = 0 and f = 1"
            // For integers: n = 0 or n = 1 → "one"; else "other"
            // Locales: acf, ak, am, bh, crs, csw, fa, ff, gcl, hi, hy, kab, ln, mfe, mg, mi,
            //   ns, nso, oc, pcm, plt, prs, si, tg, ti, tw, wa
            2 => $n > 1 ? 1 : 0,

            // Rules 3, 27 — Slavic one/few/many (or other)
            // CLDR: one = n%10=1 and n%100!=11; few = n%10=2..4 and n%100 not in 12..14; else many/other
            // Same integer computation; category arrays differ in $categoryMap.
            // Rule 3 locales (one/few/many/other): be, ru, uk
            // Rule 27 locales (one/few/other): bs, hr, me, rmn, sh, sr
            3, 27 => match (true) {
                $n % 10 === 1 && $n % 100 !== 11 => 0,
                $n % 10 >= 2 && $n % 10 <= 4 && ($n % 100 < 10 || $n % 100 >= 20) => 1,
                default => 2,
            },

            // Rule 4 — nplurals=4; one/few/many/other (Czech, Slovak)
            // CLDR: one = i=1 and v=0; few = i=2..4 and v=0; many = v!=0 (decimals only); other = rest
            // For integers: "many" (index 2) is unreachable; skip to "other" (index 3).
            // Locales: cs, sk
            4 => match (true) {
                $n === 1 => 0,
                $n >= 2 && $n <= 4 => 1,
                default => 3,   // index 3 = "other" (index 2 = "many" is decimal-only)
            },

            // Rule 5 — nplurals=5; one/two/few/many/other (Irish)
            // CLDR: one = n=1; two = n=2; few = n=3..6; many = n=7..10; other = everything else
            // Locales: ga
            5 => match (true) {
                $n === 1 => 0,
                $n === 2 => 1,
                $n >= 3 && $n <= 6 => 2,
                $n >= 7 && $n <= 10 => 3,
                default => 4,
            },

            // Rule 6 — nplurals=4; one/few/many/other (Lithuanian)
            // CLDR: one = n%10=1 and n%100 not in 11..19; few = n%10=2..9 and n%100 not in 11..19;
            //        many = f!=0 (decimals only); other = rest
            // For integers: "many" (index 2) is unreachable; skip to "other" (index 3).
            // Locales: lt
            6 => match (true) {
                $n % 10 === 1 && $n % 100 !== 11 => 0,
                $n % 10 >= 2 && ($n % 100 < 10 || $n % 100 >= 20) => 1,
                default => 3,   // index 3 = "other" (index 2 = "many" is decimal-only)
            },

            // Rule 7 — nplurals=4; one/two/few/other (Slovenian, Lower/Upper Sorbian)
            // CLDR: one = i%100=1; two = i%100=2; few = i%100=3..4 or v!=0; other = rest
            // For integers: v=0 always, so few = n%100 in {3,4} only.
            // Locales: dsb, hsb, sl
            7 => match (true) {
                $n % 100 === 1 => 0,
                $n % 100 === 2 => 1,
                in_array($n % 100, [3, 4], true) => 2,
                default => 3,
            },

            // Rule 8 — nplurals=2; one/other (Macedonian)
            // CLDR: one = v=0 and i%10=1 and i%100!=11 or f%10=1 and f%100!=11
            // For integers: n%10=1 and n%100!=11 → "one"; else "other"
            // Locales: mk
            8 => $n % 10 === 1 && $n % 100 !== 11 ? 0 : 1,

            // Rule 10 — nplurals=3; zero/one/other (Latvian)
            // CLDR: zero = n%10=0 or n%100=11..19; one = n%10=1 and n%100!=11; other = rest
            // For integers: zero catches 0, 10, 11–19, 20, 30, 40, ...
            // Locales: ltg, lv, lvs, prg
            10 => match (true) {
                $n % 10 === 0 || ($n % 100 >= 11 && $n % 100 <= 19) => 0,
                $n % 10 === 1 => 1,  // n%100!=11 is guaranteed (caught by the zero arm above)
                default => 2,
            },

            // Rule 11 — nplurals=3; one/few/many/other (Polish)
            // CLDR: one = i=1 and v=0; few = v=0 and i%10=2..4 and i%100 not in 12..14; else many
            // For integers: "other" (index 3) is decimal-only; index 2 = "many" is the integer default.
            // Locales: pl, szl
            11 => match (true) {
                $n === 1 => 0,
                $n % 10 >= 2 && $n % 10 <= 4 && ($n % 100 < 10 || $n % 100 >= 20) => 1,
                default => 2,
            },

            // Rule 12 — nplurals=3; one/few/other (Romanian, Moldavian)
            // CLDR: one = i=1 and v=0; few = v!=0 or n=0 or n!=1 and n%100=1..19; other = rest
            // For integers: n=1 → one; n=0 or n%100 in 1..19 (and n!=1) → few; else other
            // Locales: mo, ro
            12 => match (true) {
                $n === 1 => 0,
                $n === 0 || ($n % 100 > 0 && $n % 100 < 20) => 1,
                default => 2,
            },

            // Rule 13 — nplurals=6; zero/one/two/few/many/other (Arabic)
            // CLDR: zero = n=0; one = n=1; two = n=2; few = n%100=3..10; many = n%100=11..99; other = rest
            // Locales: ar, shu
            13 => match (true) {
                $n === 0 => 0,
                $n === 1 => 1,
                $n === 2 => 2,
                $n % 100 >= 3 && $n % 100 <= 10 => 3,
                $n % 100 >= 11 => 4,
                default => 5,
            },

            // Rule 14 — nplurals=6; zero/one/two/few/many/other (Welsh)
            // CLDR: zero = n=0; one = n=1; two = n=2; few = n=3; many = n=6; other = rest
            // Locales: cy
            14 => match ($n) { // @codeCoverageIgnore strange behavior of curly brackets and match in code coverage,
                0 => 0,
                1 => 1,
                2 => 2,
                3 => 3,
                6 => 4,
                default => 5,
            }, // @codeCoverageIgnore

            // Rule 15 — nplurals=2; one/other (Icelandic)
            // CLDR: one = t=0 and i%10=1 and i%100!=11 or t%10=1 and t%100!=11
            // For integers (t=0): n%10=1 and n%100!=11 → "one"; else "other"
            // Locales: is
            15 => $n % 10 !== 1 || $n % 100 === 11 ? 1 : 0,

            // Rule 16 — nplurals=4; one/two/few/other (Scottish Gaelic)
            // CLDR: one = n=1,11; two = n=2,12; few = n=3..10,13..19; other = rest
            // Locales: gd
            16 => match (true) {
                in_array($n, [1, 11], true) => 0,
                in_array($n, [2, 12], true) => 1,
                $n > 2 && $n < 20 => 2,
                default => 3,
            },

            // Rule 17 — nplurals=5; one/two/few/many/other (Breton)
            // CLDR: one = n%10=1 and n%100 not in 11,71,91; two = n%10=2 and n%100 not in 12,72,92;
            //        few = n%10 in 3,4,9 and n%100 not in 10..19,70..79,90..99; many = n!=0 and n%1M=0
            // Locales: br
            17 => self::calculateBreton($n),

            // Rule 18 — nplurals=5; one/two/few/many/other (Manx)
            // CLDR: one = v=0 and i%10=1; two = v=0 and i%10=2;
            //        few = v=0 and i%100 in 0,20,40,60,80; many = v!=0 (decimals only); other = rest
            // For integers: "many" (index 3) is unreachable; skip to "other" (index 4).
            // Locales: gv
            18 => match (true) {
                $n % 10 === 1 => 0,
                $n % 10 === 2 => 1,
                $n % 20 === 0 => 2,
                default => 4,   // index 4 = "other" (index 3 = "many" is decimal-only)
            },

            // Rule 19 — nplurals=3; one/two/other (Hebrew)
            // CLDR: one = i=1 and v=0 or i=0 and v!=0; two = i=2 and v=0; other = rest
            // For integers: n=1 → one; n=2 → two; else other
            // Locales: he
            19 => match (true) {
                $n === 1 => 0,
                $n === 2 => 1,
                default => 2,
            },

            // Rule 20 — nplurals=3; one/many/other (Italian, Spanish, Catalan)
            // CLDR: one = i=1 and v=0 (or n=1 for es); many = i!=0 and i%1000000=0 and v=0; other = rest
            // Locales: ca, cav, es, it, lld, pt_pt, scn, vec
            20 => match (true) {
                $n === 1 => 0,
                $n !== 0 && $n % 1000000 === 0 => 1,
                default => 2,
            },

            // Rule 21 — nplurals=3; one/two/other (Inuktitut, Sami, Nama, Swampy Cree)
            // CLDR: one = n=1; two = n=2; other = rest
            // Locales: iu, naq, sat, se, sma, smj, smn, sms
            21 => match ($n) { // @codeCoverageIgnore
                1 => 0,
                2 => 1,
                default => 2,
            }, // @codeCoverageIgnore

            // Rule 22 — nplurals=3; zero/one/other (Colognian, Anii, Langi)
            // CLDR: zero = n=0; one = n=1 (or i=0,1 and n!=0 for lag); other = rest
            // For integers: n=0 → zero; n=1 → one; else other
            // Locales: blo, ksh, lag
            22 => match ($n) { // @codeCoverageIgnore
                0 => 0,
                1 => 1,
                default => 2,
            }, // @codeCoverageIgnore

            // Rule 23 — nplurals=3; one/few/other (Tachelhit)
            // CLDR: one = i=0 or n=1; few = n=2..10; other = rest
            // For integers: n<=1 → one; n in 2..10 → few; else other
            // Locales: shi
            23 => match (true) {
                $n <= 1 => 0,
                $n <= 10 => 1,
                default => 2,
            },

            // Rule 24 — nplurals=6; zero/one/two/few/many/other (Cornish)
            // CLDR: complex rules for n%100 and n%1000000
            // Locales: kw
            24 => self::calculateCornish($n),

            // Rule 25 — nplurals=2; one/other (Filipino, Tagalog)
            // CLDR: one = v=0 and i=1,2,3 or v=0 and i%10!=4,6,9 or v!=0 and f%10!=4,6,9
            // For integers (v=0): "other" when n%10 in {4,6,9}; "one" otherwise
            // Locales: fil, tl
            25 => in_array($n % 10, [4, 6, 9], true) ? 1 : 0,

            // Rule 26 — nplurals=2; one/other (Central Atlas Tamazight)
            // CLDR: one = n=0..1 or n=11..99; other = rest
            // Locales: tzm
            26 => ($n <= 1 || ($n >= 11 && $n <= 99)) ? 0 : 1,

            // Rule 28 — nplurals=5; one/two/few/many/other (Maltese)
            // CLDR: one = n=1; two = n=2; few = n=0 or n%100=3..10; many = n%100=11..19; other = rest
            // Locales: mt
            28 => match (true) {
                $n === 1 => 0,
                $n === 2 => 1,
                $n === 0 || ($n % 100 >= 3 && $n % 100 <= 10) => 2,
                $n % 100 >= 11 && $n % 100 <= 19 => 3,
                default => 4,
            },

            // Rule 29 — nplurals=3; one/many/other (French, Portuguese)
            // CLDR: one = i=0,1 (or i=0..1); many = i!=0 and i%1000000=0 and v=0; other = rest
            // Differs from Rule 20: n=0 is "one" here (not "other")
            // Locales: fr, ht, pt
            29 => match (true) {
                $n <= 1 => 0,
                $n % 1000000 === 0 => 1,
                default => 2,
            },

            // Default — Rule 1 — nplurals=2; plural=(n != 1)
            // CLDR: one = n=1 (or i=1 and v=0, or n=1 or t!=0 and i=0,1 — all reduce to n=1 for integers)
            // Fallback for ~222 locales (Germanic, most European languages).
            default => $n === 1 ? 0 : 1,
        };
    }

    /**
     * Returns the CLDR plural category name for the given locale and integer number.
     *
     * @param string $locale The locale to get the category for.
     * @param int $n The number to apply the rules to.
     * @return string The CLDR plural category name.
     */
    public static function getCategoryName(string $locale, int $n): string
    {
        $pluralIndex = self::getFormIndex($locale, $n);
        $ruleGroup = PluralRules::getRuleGroup($locale);

        return self::$categoryMap[$ruleGroup][$pluralIndex] ?? PluralRules::CATEGORY_OTHER;
    }

    /**
     * Returns all available CLDR cardinal categories for a given locale.
     *
     * @param string $locale The locale to get categories for.
     * @return array<string> Array of category names available for this locale.
     */
    public static function getCategories(string $locale): array
    {
        $ruleGroup = PluralRules::getRuleGroup($locale);

        return self::$categoryMap[$ruleGroup] ?? [PluralRules::CATEGORY_OTHER];
    }

    /**
     * Returns the number of cardinal plural forms (nplurals) for a given locale.
     *
     * @param string $locale The locale code.
     * @return int The number of cardinal plural forms (1-6).
     */
    public static function getPluralCount(string $locale): int
    {
        return count(self::getCategories($locale));
    }

    /**
     * Calculate the plural form for the Breton language (Rule 17 - CLDR 48)
     * one: n%10=1 and n%100 not in 11,71,91
     * two: n%10=2 and n%100 not in 12,72,92
     * few: n%10 in 3,4,9 and n%100 not in 10..19,70..79,90..99
     * many: n!=0 and n%1000000=0
     * other: everything else
     */
    public static function calculateBreton(int $n): int
    {
        $n10 = $n % 10;
        $n100 = $n % 100;
        $inTeens = $n100 >= 10 && $n100 < 20;
        $inSeventies = $n100 >= 70 && $n100 < 80;
        $inNineties = $n100 >= 90;

        return match (true) {
            $n10 === 1 && !in_array($n100, [11, 71, 91], true) => 0,
            $n10 === 2 && !in_array($n100, [12, 72, 92], true) => 1,
            in_array($n10, [3, 4, 9], true) && !$inTeens && !$inSeventies && !$inNineties => 2,
            $n !== 0 && $n % 1000000 === 0 => 3,
            default => 4,
        };
    }

    /**
     * Calculate the plural form for the Cornish language (Rule 24 - CLDR 49)
     * zero: n = 0
     * one: n = 1
     * two: n%100 in {2,22,42,62,82} or (n%1000=0 and n%100000 in 1000..20000,40000,60000,80000) or (n!=0 and n%1000000=100000)
     * few: n%100 in {3,23,43,63,83}
     * many: n!=1 and n%100 in {1,21,41,61,81}
     * other: everything else
     */
    public static function calculateCornish(int $n): int
    {
        $n100 = $n % 100;

        return match (true) {
            $n === 0 => 0,
            $n === 1 => 1,
            in_array($n100, [2, 22, 42, 62, 82], true)
                || ($n % 1000 === 0 && (($n100k = $n % 100000) >= 1000 && $n100k <= 20000 || in_array($n100k, [40000, 60000, 80000], true)))
                || $n % 1000000 === 100000 => 2,
            in_array($n100, [3, 23, 43, 63, 83], true) => 3,
            in_array($n100, [1, 21, 41, 61, 81], true) => 4,
            default => 5,
        };
    }
}


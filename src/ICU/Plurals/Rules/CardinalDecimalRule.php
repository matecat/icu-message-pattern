<?php

declare(strict_types=1);

namespace Matecat\ICU\Plurals\Rules;

use Matecat\ICU\Plurals\PluralOperands;
use Matecat\ICU\Plurals\PluralRules;

/**
 * Cardinal plural rules for decimal inputs with full CLDR operand evaluation.
 *
 * This class handles the decimal-aware path using all CLDR operands (n, i, v, w, f, t).
 * For integer-only cardinal rules, see {@see CardinalIntegerRule}.
 *
 * @see CardinalIntegerRule For integer-only cardinal rules (fast path).
 * @see PluralRules For the public facade API.
 */
class CardinalDecimalRule
{
    /**
     * Locale → CLDR cardinal rule set identifier.
     *
     * When a rule group contains locales with different CLDR expressions
     * (same integer behavior but different decimal behavior), this map
     * distinguishes them for the decimal-aware evaluator.
     *
     * Locales not listed here use their rule group number directly.
     *
     * @var array<string, string>
     */
    private static array $cldrCardinalRuleSet = [
        // Rule group 1 splits:
        // CLDR "n = 1" locales (differs from "i = 1 and v = 0" for decimals like 1.0)
        'af' => 'n_eq_1', 'an' => 'n_eq_1', 'asa' => 'n_eq_1', 'az' => 'n_eq_1',
        'bal' => 'n_eq_1', 'bem' => 'n_eq_1', 'bez' => 'n_eq_1', 'bg' => 'n_eq_1',
        'brx' => 'n_eq_1', 'ce' => 'n_eq_1', 'cgg' => 'n_eq_1', 'chr' => 'n_eq_1',
        'ckb' => 'n_eq_1', 'dv' => 'n_eq_1', 'ee' => 'n_eq_1', 'el' => 'n_eq_1',
        'eo' => 'n_eq_1', 'eu' => 'n_eq_1', 'fo' => 'n_eq_1', 'fur' => 'n_eq_1',
        'gsw' => 'n_eq_1', 'ha' => 'n_eq_1', 'haw' => 'n_eq_1', 'hu' => 'n_eq_1',
        'jgo' => 'n_eq_1', 'jmc' => 'n_eq_1', 'ka' => 'n_eq_1', 'kaj' => 'n_eq_1',
        'kcg' => 'n_eq_1', 'kk' => 'n_eq_1', 'kkj' => 'n_eq_1', 'kl' => 'n_eq_1',
        'ks' => 'n_eq_1', 'ksb' => 'n_eq_1', 'ku' => 'n_eq_1', 'ky' => 'n_eq_1',
        'lb' => 'n_eq_1', 'lg' => 'n_eq_1', 'mas' => 'n_eq_1', 'mgo' => 'n_eq_1',
        'ml' => 'n_eq_1', 'mn' => 'n_eq_1', 'mr' => 'n_eq_1', 'nah' => 'n_eq_1',
        'nb' => 'n_eq_1', 'nd' => 'n_eq_1', 'ne' => 'n_eq_1', 'nn' => 'n_eq_1',
        'nnh' => 'n_eq_1', 'no' => 'n_eq_1', 'nr' => 'n_eq_1', 'ny' => 'n_eq_1',
        'nyn' => 'n_eq_1', 'om' => 'n_eq_1', 'or' => 'n_eq_1', 'os' => 'n_eq_1',
        'pap' => 'n_eq_1', 'ps' => 'n_eq_1', 'rm' => 'n_eq_1', 'rof' => 'n_eq_1',
        'rwk' => 'n_eq_1', 'saq' => 'n_eq_1', 'sd' => 'n_eq_1', 'sdh' => 'n_eq_1',
        'seh' => 'n_eq_1', 'sn' => 'n_eq_1', 'so' => 'n_eq_1', 'sq' => 'n_eq_1',
        'ss' => 'n_eq_1', 'ssy' => 'n_eq_1', 'st' => 'n_eq_1', 'syr' => 'n_eq_1',
        'ta' => 'n_eq_1', 'te' => 'n_eq_1', 'teo' => 'n_eq_1', 'tig' => 'n_eq_1',
        'tk' => 'n_eq_1', 'tn' => 'n_eq_1', 'tr' => 'n_eq_1', 'ts' => 'n_eq_1',
        'ug' => 'n_eq_1', 'uz' => 'n_eq_1', 've' => 'n_eq_1', 'vo' => 'n_eq_1',
        'vun' => 'n_eq_1', 'wae' => 'n_eq_1', 'xh' => 'n_eq_1', 'xog' => 'n_eq_1',

        // CLDR "n = 1 or t != 0 and i = 0,1" (Danish)
        'da' => 'da',

        // CLDR "t = 0 and i % 10 = 1 and i % 100 != 11 or t % 10 = 1 and t % 100 != 11" (Icelandic)
        'is' => 'is',

        // Rule group 2 splits:
        // CLDR "i = 0,1" (ff, hy, kab)
        'ff' => 'i_01', 'hy' => 'i_01', 'kab' => 'i_01',

        // CLDR "n = 0,1 or i = 0 and f = 1" (Sinhala)
        'si' => 'si',

        // CLDR "n = 0..1" (ak, bho, csw, guw, ln, mg, nso, pa, ti, wa)
        'ak' => 'n_01_range', 'bho' => 'n_01_range', 'csw' => 'n_01_range',
        'guw' => 'n_01_range', 'ln' => 'n_01_range', 'mg' => 'n_01_range',
        'nso' => 'n_01_range', 'pa' => 'n_01_range', 'ti' => 'n_01_range',
        'wa' => 'n_01_range',

        // Rule group 3 splits:
        // CLDR "v = 0 and i % ..." (ru, uk — decimals always → "other")
        'ru' => 'ru_uk', 'uk' => 'ru_uk',
        // be stays as rule group 3 default (n% form — decimals participate)

        // Rule group 7 splits:
        // CLDR "v = 0 and i % 100 = ... or f % 100 = ..." (dsb, hsb — f% clauses)
        'dsb' => 'dsb_hsb', 'hsb' => 'dsb_hsb',
        // sl stays as rule group 7 default

        // Rule group 22 splits:
        // CLDR "i = 0,1 and n != 0" for one (Langi)
        'lag' => 'lag',

        // Rule group 29 splits:
        // CLDR "i = 0..1" (pt — differs from fr "i = 0,1" for decimals)
        'pt' => 'pt',
    ];

    /**
     * Returns the cardinal plural form index for the given locale and number,
     * supporting decimal input with full CLDR operand evaluation.
     *
     * String input preserves visible fraction digits: "1.20" has v=2, f=20.
     *
     * @param string $locale The locale code.
     * @param string|int|float $number The number to apply the rules to.
     * @return int The plural form index.
     */
    public static function getFormIndexForNumber(string $locale, string|int|float $number): int
    {
        $op = PluralOperands::from($number);

        // Fast path: integer operands (v=0) → delegate to existing integer logic
        if ($op->fractionDigitCount === 0) {
            return CardinalIntegerRule::getFormIndex($locale, $op->integerPart);
        }

        return self::evaluateWithOperands($locale, $op);
    }

    /**
     * Returns the CLDR cardinal category name for the given locale and number,
     * supporting decimal input with full CLDR operand evaluation.
     *
     * @param string $locale The locale code.
     * @param string|int|float $number The number to apply the rules to.
     * @return string The CLDR plural category name.
     */
    public static function getCategoryNameForNumber(string $locale, string|int|float $number): string
    {
        $pluralIndex = self::getFormIndexForNumber($locale, $number);
        $ruleGroup = PluralRules::getRuleGroup($locale);

        return CardinalIntegerRule::$categoryMap[$ruleGroup][$pluralIndex] ?? PluralRules::CATEGORY_OTHER;
    }

    /**
     * Evaluate cardinal plural rules using full CLDR operands.
     *
     * This method is only called for decimal inputs (v > 0).
     * Integer inputs are handled by CardinalIntegerRule::getFormIndex().
     *
     * This match dispatches on the locale-specific CLDR rule set identifier ($ruleSet)
     * instead of the numeric rule group ($ruleGroup).
     *
     * Why? Several locales share the same rule group number because their INTEGER
     * plural behavior is identical (e.g., "af" and "da" both return one/other for
     * n=1/n≠1). However, their CLDR DECIMAL rules differ:
     *   - "af" uses "n = 1"        → 1.0 is "one", 0.5 is "other"
     *   - "da" uses "n=1 or t!=0 and i=0,1" → 0.5 is "one"
     *   - "en" uses "i=1 and v=0"  → 1.0 is "other" (v > 0)
     *
     * The rule group alone cannot distinguish these cases. This match resolves
     * the ambiguity by routing each locale to its exact CLDR expression via the
     * $cldrCardinalRuleSet map. Locales not in that map fall through to the
     * default arm, which delegates to evaluateByRuleGroup().
     *
     * @param string $locale The locale code.
     * @param PluralOperands $op The operands extracted from the number.
     * @return int The plural form index.
     */
    private static function evaluateWithOperands(string $locale, PluralOperands $op): int
    {
        $locale = strtolower($locale);

        // Normalize locale: try full code first, then base language
        if (!isset(self::$cldrCardinalRuleSet[$locale])) {
            $base = explode('_', $locale)[0];
            if ($base !== $locale && !isset(self::$cldrCardinalRuleSet[$base])) {
                $base = explode('-', $base)[0];
            }
            $locale = $base;
        }

        $ruleSet = self::$cldrCardinalRuleSet[$locale] ?? null;
        $ruleGroup = PluralRules::getRuleGroup($locale);

        $i = $op->integerPart;
        $f = $op->fractionDigits;
        $t = $op->significantFractionDigits;
        $n = $op->absoluteValue;

        // Note: fractionDigitCount (CLDR "v") is not needed here. This method is only called when v > 0
        // (the caller fast-paths integers). Rules that depend on v either:
        //   - return a constant (e.g., ru_uk → 2, because all conditions require v=0)
        //   - are delegated to evaluateByRuleGroup() which extracts $v itself

        return match ($ruleSet) {

            // ----------------------------------------------------------------
            // Rule group 1 / n_eq_1: CLDR "n = 1"
            // one: n = 1 (matches 1.0, 1.00, etc.)
            // ----------------------------------------------------------------
            'n_eq_1' => $n == 1 ? 0 : 1,

            // ----------------------------------------------------------------
            // Rule group 1 / da: CLDR "n = 1 or t != 0 and i = 0,1"
            // one: n=1, or any non-zero-trailing decimal with integer part 0 or 1
            // ----------------------------------------------------------------
            'da' => ($n == 1 || ($t !== 0 && ($i === 0 || $i === 1))) ? 0 : 1,

            // ----------------------------------------------------------------
            // Rule group 1 (is): CLDR "t = 0 and i % 10 = 1 and i % 100 != 11
            //                          or t % 10 = 1 and t % 100 != 11"
            // ----------------------------------------------------------------
            'is' => (
                ($t === 0 && $i % 10 === 1 && $i % 100 !== 11)
                || ($t % 10 === 1 && $t % 100 !== 11)
            ) ? 0 : 1,

            // ----------------------------------------------------------------
            // Rule group 2 / i_01: CLDR "i = 0,1"
            // ----------------------------------------------------------------
            'i_01' => ($i === 0 || $i === 1) ? 0 : 1,

            // ----------------------------------------------------------------
            // Rule group 2 / si: CLDR "n = 0,1 or i = 0 and f = 1"
            // ----------------------------------------------------------------
            'si' => ($n == 0 || $n == 1 || ($i === 0 && $f === 1)) ? 0 : 1,

            // ----------------------------------------------------------------
            // Rule group 2 / n_01_range: CLDR "n = 0..1"
            // ----------------------------------------------------------------
            'n_01_range' => ($n >= 0 && $n <= 1) ? 0 : 1,

            // ----------------------------------------------------------------
            // Rule group 3 / ru_uk: CLDR "v = 0 and i % 10 = ..."
            // For decimals (v > 0): always "other" (index 2 maps to "many" in
            // categoryMap[3], but semantically this is the "rest" bucket).
            // For ru/uk, decimals go to the last category.
            // ----------------------------------------------------------------
            'ru_uk' => 2, // v > 0 → all decimal conditions have "v = 0" → none match → "other" (index 2 = "many")

            // ----------------------------------------------------------------
            // Rule group 7 / dsb_hsb: CLDR with f%100 clauses
            // one: v = 0 and i % 100 = 1 or f % 100 = 1
            // two: v = 0 and i % 100 = 2 or f % 100 = 2
            // few: v = 0 and i % 100 = 3..4 or f % 100 = 3..4
            // other: rest
            // ----------------------------------------------------------------
            'dsb_hsb' => match (true) {
                $f % 100 === 1 => 0,   // one (v>0, so "v=0 and i%100=1" is false, but "f%100=1" applies)
                $f % 100 === 2 => 1,   // two
                $f % 100 >= 3 && $f % 100 <= 4 => 2, // few
                default => 3,          // other
            },

            // ----------------------------------------------------------------
            // Rule group 22 / lag: CLDR zero=n=0; one=i=0,1 and n!=0
            // ----------------------------------------------------------------
            'lag' => match (true) {
                $n == 0 => 0,               // zero
                $i === 0 || $i === 1 => 1,  // one (n != 0 guaranteed by first arm)
                default => 2,               // other
            },

            // ----------------------------------------------------------------
            // Rule group 29 / pt: CLDR "i = 0..1"
            // (same as fr for integers, but textually "i = 0..1" vs "i = 0,1")
            // Actually identical behavior — "0..1" means 0 or 1 (integer range).
            // ----------------------------------------------------------------
            'pt' => match (true) {
                $i === 0 || $i === 1 => 0,  // one
                default => 2,               // other (many is unreachable: requires v=0 but we are in v>0 path)
            },

            // ----------------------------------------------------------------
            // No special rule set — use rule group for remaining decimal cases
            // ----------------------------------------------------------------
            default => self::evaluateByRuleGroup($ruleGroup, $op),
        };
    }

    /**
     * Evaluate cardinal plural rules by rule group for decimal operands.
     *
     * This is the fallback when no locale-specific rule set is defined in
     * $cldrCardinalRuleSet. It handles rules that either use $n directly
     * (and thus work the same for decimals) or that collapse to a constant
     * because all conditions require v=0.
     *
     * @param int $ruleGroup The cardinal rule group number.
     * @param PluralOperands $op The operands.
     * @return int The plural form index.
     */
    private static function evaluateByRuleGroup(int $ruleGroup, PluralOperands $op): int
    {
        $i = $op->integerPart;
        $v = $op->fractionDigitCount;
        $f = $op->fractionDigits;
        $n = $op->absoluteValue;

        return match ($ruleGroup) {

            // Rule 0 — nplurals=1; only "other"
            0 => 0,

            // Rule 1 — default "i = 1 and v = 0"
            // For decimals (v > 0): always "other"
            1 => 1,

            // Rule 2 — "i = 0 or n = 1" (am, as, bn, doi, fa, gu, hi, kn, kok, pcm, zu)
            2 => ($i === 0 || $n == 1) ? 0 : 1,

            // Rule 3 — be: "n % 10 = 1 and n % 100 != 11" (uses n, so decimals participate)
            // one: n%10=1 and n%100!=11; few: n%10=2..4 and n%100 not 12..14;
            // many: n%10=0 or n%10=5..9 or n%100=11..14; other: rest (decimals that don't match)
            3 => self::evaluateBelarusianCardinal($op),

            // Rule 4 — cs, sk: one=i=1 and v=0; few=i=2..4 and v=0; many=v!=0; other=rest
            // For decimals (v > 0): "many" (index 2)
            4 => 2, // many

            // Rule 5 — ga: n=1/n=2/n=3..6/n=7..10/other (uses n, decimals participate)
            5 => match (true) {
                $n == 1 => 0,
                $n == 2 => 1,
                $n >= 3 && $n <= 6 => 2,
                $n >= 7 && $n <= 10 => 3,
                default => 4,
            },

            // Rule 6 — lt: one=n%10=1 not 11..19; few=n%10=2..9 not 11..19; many=f!=0; other=rest
            // For decimals (v > 0): f != 0 is always true → "many" (index 2)
            6 => ($f !== 0) ? 2 : 3, // f is always != 0 when v > 0, but being explicit

            // Rule 7 — sl: one=v=0 and i%100=1; two=v=0 and i%100=2;
            //              few=v=0 and i%100=3..4 or v!=0; other=rest
            // For decimals (v > 0): "few" (index 2, because v != 0)
            7 => 2, // few

            // Rule 8 — mk: one=v=0 and i%10=1 and i%100!=11 or f%10=1 and f%100!=11
            8 => (($v === 0 && $i % 10 === 1 && $i % 100 !== 11) || ($f % 10 === 1 && $f % 100 !== 11)) ? 0 : 1,

            // Rule 10 — lv, prg: zero=n%10=0 or n%100=11..19 or v=2 and f%100=11..19;
            //                    one=n%10=1 and n%100!=11 or v=2 and f%10=1 and f%100!=11 or v!=2 and f%10=1;
            //                    other=rest
            10 => self::evaluateLatvianCardinal($op),

            // Rule 11 — pl: one=i=1 and v=0; few=v=0 and i%10=2..4 and i%100!=12..14;
            //               many=v=0 and ...; other=rest
            // For decimals (v > 0): none of the v=0 conditions match → "other" (index 3)
            11 => 3, // other

            // Rule 12 — ro, mo: one=i=1 and v=0; few=v!=0 or n=0 or n!=1 and n%100=1..19; other=rest
            // For decimals (v > 0): "few" (index 1, because v != 0)
            12 => 1, // few

            // Rule 13 — ar: uses n = absolute value
            13 => match (true) {
                $n == 0 => 0,
                $n == 1 => 1,
                $n == 2 => 2,
                (int) $n % 100 >= 3 && (int) $n % 100 <= 10 => 3,
                (int) $n % 100 >= 11 => 4,
                default => 5,
            },

            // Rule 14 — cy: uses n = absolute value
            14 => match (true) {
                $n == 0 => 0,
                $n == 1 => 1,
                $n == 2 => 2,
                $n == 3 => 3,
                $n == 6 => 4,
                default => 5,
            },

            // Rule 15 — is (Icelandic): intentionally omitted.
            // Icelandic is the only locale with rule group 15, and it is always
            // intercepted by the $cldrCardinalRuleSet map ('is' => 'is') in
            // evaluateWithOperands(), so this arm is unreachable. Keeping it
            // would be dead code.

            // Rule 16 — gd: uses n
            16 => match (true) {
                $n == 1 || $n == 11 => 0,
                $n == 2 || $n == 12 => 1,
                ($n >= 3 && $n <= 10) || ($n >= 13 && $n <= 19) => 2,
                default => 3,
            },

            // Rule 17 — br: uses n (decimals participate)
            17 => self::evaluateBretonCardinal($op),

            // Rule 18 — gv: one=v=0 and i%10=1; two=v=0 and i%10=2;
            //               few=v=0 and i%100=0,20,40,60,80; many=v!=0; other=rest
            // For decimals (v > 0): "many" (index 3)
            18 => 3, // many

            // Rule 19 — he: one=i=1 and v=0 or i=0 and v!=0; two=i=2 and v=0; other=rest
            // For decimals (v > 0): one if i=0; otherwise other
            19 => ($i === 0 && $v !== 0) ? 0 : 2,

            // Rule 20 — ca, cav, es, it, lld, pt_pt, scn, vec:
            //           one=i=1 and v=0; many=e=0 and i!=0 and i%1M=0 and v=0 or e!=0..5; other=rest
            // For decimals (v > 0): "other" (index 2) — one and many both require v=0 (ignoring e)
            20 => 2,

            // Rule 21 — iu, naq, sat, se, ...: one=n=1; two=n=2; other=rest (uses n)
            21 => match (true) {
                $n == 1 => 0,
                $n == 2 => 1,
                default => 2,
            },

            // Rule 22 — blo, cv, ksh: zero=n=0; one=n=1; other=rest (uses n)
            // Note: lag is handled separately via cldrCardinalRuleSet
            22 => match (true) {
                $n == 0 => 0,
                $n == 1 => 1,
                default => 2,
            },

            // Rule 23 — shi: one=i=0 or n=1; few=n=2..10; other=rest
            23 => match (true) {
                $i === 0 || $n == 1 => 0,
                $n >= 2 && $n <= 10 => 1,
                default => 2,
            },

            // Rule 24 — kw: uses n (all rules use n, decimals participate)
            24 => self::evaluateCornishCardinal($op),

            // Rule 25 — fil, tl: one=v=0 and i=1,2,3 or v=0 and i%10!=4,6,9 or v!=0 and f%10!=4,6,9
            // For decimals (v > 0): "one" if f%10 is not 4, 6, or 9; else "other"
            25 => ($v !== 0 && !in_array($f % 10, [4, 6, 9], true)) ? 0 : 1,

            // Rule 26 — tzm: one=n=0..1 or n=11..99; other=rest (uses n)
            26 => ($n >= 0 && $n <= 1 || $n >= 11 && $n <= 99) ? 0 : 1,

            // Rule 27 — bs, hr, sh, sr:
            //   one=v=0 and i%10=1 and i%100!=11 or f%10=1 and f%100!=11
            //   few=v=0 and i%10=2..4 and i%100!=12..14 or f%10=2..4 and f%100!=12..14
            //   other=rest
            27 => match (true) {
                ($v === 0 && $i % 10 === 1 && $i % 100 !== 11) || ($f % 10 === 1 && $f % 100 !== 11) => 0,
                ($v === 0 && $i % 10 >= 2 && $i % 10 <= 4 && ($i % 100 < 12 || $i % 100 > 14))
                    || ($f % 10 >= 2 && $f % 10 <= 4 && ($f % 100 < 12 || $f % 100 > 14)) => 1,
                default => 2,
            },

            // Rule 28 — mt: uses n
            28 => match (true) {
                $n == 1 => 0,
                $n == 2 => 1,
                $n == 0 || ((int) $n % 100 >= 3 && (int) $n % 100 <= 10) => 2,
                (int) $n % 100 >= 11 && (int) $n % 100 <= 19 => 3,
                default => 4,
            },

            // Rule 29 — fr, ht: one=i=0,1; many=e=0 and i!=0 and i%1M=0 and v=0 or e!=0..5; other=rest
            // For decimals (v > 0): one if i=0 or i=1; many is unreachable (v > 0 blocks it); else other
            29 => ($i === 0 || $i === 1) ? 0 : 2,

            // Rule 0 — nplurals=1; only "other"
            // Default: "other" (only form)
            default => 0,
        };
    }

    /**
     * Evaluate Belarusian cardinal rules with full operands.
     * CLDR: one=n%10=1 and n%100!=11; few=n%10=2..4 and n%100!=12..14;
     *       many=n%10=0 or n%10=5..9 or n%100=11..14; other=rest
     * Uses n (absolute value), so decimals like 1.0, 21.0 participate.
     */
    private static function evaluateBelarusianCardinal(PluralOperands $op): int
    {
        // For Belarusian, CLDR uses "n" operand. For integer-valued decimals
        // (like 21.0), n=21.0, but n%10 and n%100 operate on the absolute value.
        // The CLDR "@decimal" samples show 1.0, 21.0 → one; 2.0, 3.0 → few; etc.
        // Noninteger decimals (like 0.1) go to "other" (they match none of the n% conditions).
        $n = $op->absoluteValue;
        $nInt = (int) $n;

        // If n is not an integer value, it goes to "other"
        if ($n != $nInt) {
            return 3; // other
        }

        // n is integer-valued (e.g., 21.0): apply standard integer Slavic rules
        return match (true) {
            $nInt % 10 === 1 && $nInt % 100 !== 11 => 0,        // one
            $nInt % 10 >= 2 && $nInt % 10 <= 4 && ($nInt % 100 < 10 || $nInt % 100 >= 20) => 1, // few
            default => 2,                                         // many
        };
    }

    /**
     * Evaluate Latvian cardinal rules with full operands.
     * CLDR: zero = n%10=0 or n%100=11..19 or v=2 and f%100=11..19
     *       one  = n%10=1 and n%100!=11 or v=2 and f%10=1 and f%100!=11 or v!=2 and f%10=1
     *       other = rest
     */
    private static function evaluateLatvianCardinal(PluralOperands $op): int
    {
        $n = $op->absoluteValue;
        $v = $op->fractionDigitCount;
        $f = $op->fractionDigits;

        // CLDR uses n% (float modulo). For noninteger n, n%10 is not an integer.
        $n10 = fmod($n, 10);
        $n100 = fmod($n, 100);
        $f10 = $f % 10;
        $f100 = $f % 100;

        // zero: n%10=0 or n%100=11..19 or v=2 and f%100=11..19
        if ($n10 == 0 || ($n100 >= 11 && $n100 <= 19) || ($v === 2 && $f100 >= 11 && $f100 <= 19)) {
            return 0;
        }

        // one: n%10=1 and n%100!=11 or v=2 and f%10=1 and f%100!=11 or v!=2 and f%10=1
        //
        // The "$n100 != 11" sub-expression is logically redundant at this point:
        // the zero guard above already returned for any n%100 in 11..19, so n%100
        // can never be 11 here. The check is kept intentionally to match the CLDR
        // rule text character-by-character, so that future maintainers can verify
        // this code against the CLDR specification (plurals.xml) without mental
        // translation. The same reasoning applies to "$f100 !== 11".
        if (($n10 == 1 && $n100 != 11) || ($v === 2 && $f10 === 1 && $f100 !== 11) || ($v !== 2 && $f10 === 1)) {
            return 1;
        }

        // other
        return 2;
    }

    /**
     * Evaluate Breton cardinal rules with full operands.
     * CLDR: one=n%10=1 and n%100 not in 11,71,91; two=n%10=2 and n%100 not in 12,72,92;
     *       few=n%10 in 3..4,9 and n%100 not in 10..19,70..79,90..99;
     *       many=n!=0 and n%1000000=0; other=rest
     * Uses n, so only integer-valued decimals participate.
     */
    private static function evaluateBretonCardinal(PluralOperands $op): int
    {
        $n = $op->absoluteValue;
        $nInt = (int) $n;

        // Non-integer-valued decimals → other
        if ($n != $nInt) {
            return 4; // other
        }

        return CardinalIntegerRule::calculateBreton($nInt);
    }

    /**
     * Evaluate Cornish cardinal rules with full operands.
     * Uses n, so only integer-valued decimals participate.
     */
    private static function evaluateCornishCardinal(PluralOperands $op): int
    {
        $n = $op->absoluteValue;
        $nInt = (int) $n;

        // Non-integer-valued decimals → other
        if ($n != $nInt) {
            return 5; // other
        }

        return CardinalIntegerRule::calculateCornish($nInt);
    }
}


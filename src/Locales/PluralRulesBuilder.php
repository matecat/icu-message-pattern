<?php
/** @noinspection PhpMultipleClassDeclarationsInspection */

declare(strict_types=1);

namespace Matecat\Locales;

use JsonException;
use Matecat\ICU\Plurals\PluralRules;
use Matecat\Locales\DTO\CategoryFragment;
use Matecat\Locales\DTO\LanguageRulesFragment;
use RuntimeException;

/**
 * Builds the pluralRules.json file using PluralRules definitions and the supported languages list.
 *
 * This class iterates over all languages from Languages (supported_langs.json),
 * resolves their plural rule groups via PluralRules, and constructs a JSON structure
 * containing cardinal and ordinal plural categories with human-readable descriptions.
 *
 * Category names are resolved at runtime from PluralRules, not duplicated here.
 *
 * On first access, the builder checks for a cached pluralRules.json file.
 * If the file exists, it reads from it (cache read-through).
 * If not, it builds the rules and writes them to disk for future use.
 *
 * ## Usage Example
 *
 * ```php
 * use Matecat\ICU\Plurals\PluralRulesBuilder;
 *
 * // Get the singleton instance (reads from cache or builds + writes)
 * $builder = PluralRulesBuilder::getInstance();
 *
 * // Access the built rules
 * $rules = $builder->getRules();
 *
 * // Force rebuild (useful for tests)
 * PluralRulesBuilder::destroyInstance();
 * $builder = PluralRulesBuilder::getInstance(forceRebuild: true);
 * ```
 */
class PluralRulesBuilder
{
    private static ?self $instance = null;

    /**
     * The default file path for the cached pluralRules.json.
     */
    private const string DEFAULT_FILE_PATH = __DIR__ . '/pluralRules.json';

    /**
     * The file path for per-language overrides of human_rule and example.
     *
     * This file contains only the deltas: fields that differ from the
     * rule-group defaults in CARDINAL_HUMAN_RULES / ORDINAL_HUMAN_RULES.
     * Overrides are keyed by category name (e.g. "one", "other") rather
     * than positional index, making them resilient to category reordering.
     */
    private const string OVERRIDES_FILE_PATH = __DIR__ . '/pluralRulesOverrides.json';

    // ─── Array key constants ─────────────────────────────────────────────
    private const string K_RULE       = 'rule';
    private const string K_HUMAN_RULE = 'human_rule';
    private const string K_EXAMPLE    = 'example';
    private const string K_CARDINAL   = 'cardinal';
    private const string K_ORDINAL    = 'ordinal';

    // ─── Rule string constants ───────────────────────────────────────────
    private const string R_EMPTY                     = '';
    private const string R_N_EQ_0                    = 'n = 0';
    private const string R_N_EQ_1                    = 'n = 1';
    private const string R_N_EQ_2                    = 'n = 2';
    private const string R_N_EQ_4                    = 'n = 4';
    private const string R_N_EQ_6                    = 'n = 6';
    private const string R_N_EQ_2_3                  = 'n = 2, 3';
    private const string R_N_EQ_1_5                  = 'n = 1, 5';
    private const string R_I_EQ_0                    = 'i = 0';
    private const string R_I_EQ_1                    = 'i = 1';
    private const string R_I1_V0                     = 'i = 1 and v = 0';
    private const string R_V_NE_0                    = 'v != 0';
    private const string R_ENDS_1_NOT_11             = 'n % 10 = 1 and n % 100 != 11';
    private const string R_ENDS_2_NOT_12             = 'n % 10 = 2 and n % 100 != 12';

    // ─── Human rule string constants ─────────────────────────────────────
    private const string H_ANY_NUMBER                = 'Any number';
    private const string H_ANY_OTHER                 = 'Any other number';
    private const string H_EXACTLY_0                 = 'Exactly 0';
    private const string H_EXACTLY_1                 = 'Exactly 1';
    private const string H_EXACTLY_1_NO_DEC          = 'Exactly 1 (no decimals)';
    private const string H_EXACTLY_2                 = 'Exactly 2';
    private const string H_EXACTLY_4                 = 'Exactly 4';
    private const string H_EXACTLY_6                 = 'Exactly 6';
    private const string H_EXACTLY_2_OR_3            = 'Exactly 2 or 3';
    private const string H_ENDS_1_EXCEPT_11          = 'Ends in 1 (except 11)';
    private const string H_ENDS_2_EXCEPT_12          = 'Ends in 2 (except 12)';
    private const string H_DECIMAL_ONLY              = 'Any number with decimals (decimal-only category)';

    // ─── Example string constants ────────────────────────────────────────
    private const string E_EMPTY                     = '';
    private const string E_ALL_NUMBERS               = '0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, …';
    private const string E_ALL_NUMBERS_LONG          = '0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 100, 1000, …';
    private const string E_0                         = '0';
    private const string E_1                         = '1';
    private const string E_2                         = '2';
    private const string E_3                         = '3';
    private const string E_4                         = '4';
    private const string E_6                         = '6';
    private const string E_0_1                       = '0, 1';
    private const string E_1_5                       = '1, 5';
    private const string E_2_3                       = '2, 3';
    private const string E_ENDS_1_SHORT              = '1, 21, 31, 41, 51, …';
    private const string E_ENDS_1_LONG               = '1, 21, 31, 41, 51, 61, 71, 81, 101, …';
    private const string E_ENDS_2_4                  = '2~4, 22~24, 32~34, …';
    private const string E_OTHER_0_2_16              = '0, 2~16, 100, 1000, …';
    private const string E_OTHER_2_17                = '2~17, 100, 1000, …';
    private const string E_OTHER_0_5_20              = '0, 5~20, 100, 1000, …';
    private const string E_OTHER_0_3_17              = '0, 3~17, 100, 1000, …';
    private const string E_DECIMAL_01_15             = '0.0~1.5, 10.0, …';

    /**
     * The built plural rules, keyed by ISO code.
     *
     * @var array<string, LanguageRulesFragment>
     */
    private array $rules;

    /**
     * Human-readable descriptions for cardinal plural rules.
     *
     * Maps a rule group number to a positional array of {rule, human_rule, example}.
     * The position corresponds to the category index returned by PluralRules::getCardinalCategories().
     *
     * @var array<int, array<int, array{rule: string, human_rule: string, example: string}>>
     */
    private const array CARDINAL_HUMAN_RULES = [
        // Rule 0: nplurals=1; only "other" (Asian languages, no plural forms)
        // Locales (76): ace, ayr, ba, ban, bi, bjn, bm, bo, bod, bug, ch, chk, crh, dyu, dz,
        //   fj, fn, fon, gil, hmn, hnj, id, ig, ii, ja, jbo, jv, kac, kar, kbp, kde, kea, km,
        //   ko, kr, ksw, lkt, lo, mh, min, mos, ms, my, niu, nqo, osa, pau, pis, pon, ppk,
        //   sah, ses, sg, shn, sm, smo, su, sus, taq, th, tkl, tmh, to, ton, tpi, trv, tt,
        //   tvl, ty, vi, wls, wo, yo, yue, zh, zsm
        0 => [
            [
                self::K_RULE => self::R_EMPTY,
                self::K_HUMAN_RULE => self::H_ANY_NUMBER,
                self::K_EXAMPLE => self::E_ALL_NUMBERS_LONG,
            ],
        ],
        // Rule 1: nplurals=2; plural=(n != 1); (Germanic, most European)
        // Locales (222, default): aa, af, aig, als, an, as, asa, asm, ast, awa, az, azb, azj,
        //   bah, bal, bem, bez, bg, bho, bjs, bn, brx, cac, cb, ce, ceb, cgg, chr, cjk, ckb,
        //   cop, ctg, da, de, dik, diq, div, doi, dv, ee, el, en, eo, et, eu, fi, fo, fuc,
        //   fur, fuv, fy, gax, gaz, gl, glw, gn, grc, grt, gsw, gu, guz, gyn, ha, haw, hig,
        //   hil, hne, hoc, hu, ia, ilo, io, jam, jgo, ji, jmc, ka, kaj, kal, kam, kas, kcg,
        //   kg, kha, khk, ki, kjb, kk, kkj, kl, kln, kmb, kmr, kn, knc, kok, ks, ksb, ku,
        //   ky, la, lb, lg, li, lij, lmo, lua, lug, luo, lus, luy, mag, mai, mam, mas, men,
        //   mer, mfi, mfv, mgo, mhr, ml, mn, mni, mnk, mr, mrj, mrt, nb, nd, ndc, ne, nl,
        //   nn, nnh, no, nr, nup, nus, ny, nyf, nyn, om, or, ory, os, pa, pag, pap, pbt, pi,
        //   pko, pot, pov, ps, qnt, qu, quc, quy, rhg, rhl, rm, rmo, rn, rof, roh, run, rw,
        //   rwk, sa, saq, sc, sd, sdh, seh, sn, sna, snk, so, sq, srn, ss, ssy, st, sv, svc,
        //   sw, syc, syr, ta, te, teo, tet, tig, tiv, tk, tn, tr, ts, tsc, tum, udm, ug, umb,
        //   ur, uz, uzn, ve, vic, vls, vmw, vo, vun, wae, war, xh, xog, ydd, yi, ymm, zdj, zu
        1 => [
            [self::K_RULE => self::R_I1_V0, self::K_HUMAN_RULE => self::H_EXACTLY_1_NO_DEC, self::K_EXAMPLE => self::E_1],
            [self::K_RULE => self::R_EMPTY, self::K_HUMAN_RULE => self::H_ANY_OTHER, self::K_EXAMPLE => self::E_OTHER_0_2_16],
        ],
        // Rule 2: nplurals=2; (Amharic, Persian, Hindi, etc. — CLDR: i = 0 or n = 1; integer approximation: n > 1)
        // Locales (27): acf, ak, am, bh, crs, csw, fa, ff, gcl, hi, hy, kab, ln, mfe, mg, mi,
        //   ns, nso, oc, pcm, plt, prs, si, tg, ti, tw, wa
        2 => [
            [self::K_RULE => 'i = 0 or n = 1', self::K_HUMAN_RULE => '0 or 1', self::K_EXAMPLE => self::E_0_1],
            [self::K_RULE => self::R_EMPTY, self::K_HUMAN_RULE => self::H_ANY_OTHER, self::K_EXAMPLE => self::E_OTHER_2_17],
        ],
        // Rule 3: nplurals=4; Slavic (Russian, Ukrainian, Belarusian, Serbian, Croatian)
        // Locales (3): be, ru, uk
        3 => [
            [
                self::K_RULE => self::R_ENDS_1_NOT_11,
                self::K_HUMAN_RULE => self::H_ENDS_1_EXCEPT_11,
                self::K_EXAMPLE => self::E_ENDS_1_LONG,
            ],
            [
                self::K_RULE => 'n % 10 = 2–4 and n % 100 != 12–14',
                self::K_HUMAN_RULE => 'Ends in 2-4 (except 12–14)',
                self::K_EXAMPLE => self::E_ENDS_2_4,
            ],
            [
                self::K_RULE => 'n % 10 = 0 or n % 10 = 5–9 or n % 100 = 11–14',
                self::K_HUMAN_RULE => 'Ends in 0, 5–9, or 11–14',
                self::K_EXAMPLE => '0, 5~20, 25~30, 35~40, …',
            ],
            [self::K_RULE => self::R_EMPTY, self::K_HUMAN_RULE => self::H_ANY_OTHER, self::K_EXAMPLE => self::E_EMPTY],
        ],
        // Rule 4: nplurals=4; (Czech, Slovak — CLDR 49: "many" for decimals only)
        // Locales (2): cs, sk
        4 => [
            [self::K_RULE => self::R_I1_V0, self::K_HUMAN_RULE => self::H_EXACTLY_1_NO_DEC, self::K_EXAMPLE => self::E_1],
            [self::K_RULE => 'i = 2–4 and v = 0', self::K_HUMAN_RULE => 'Exactly 2, 3, or 4 (no decimals)', self::K_EXAMPLE => '2~4'],
            [self::K_RULE => self::R_V_NE_0, self::K_HUMAN_RULE => self::H_DECIMAL_ONLY, self::K_EXAMPLE => self::E_DECIMAL_01_15],
            [self::K_RULE => self::R_EMPTY, self::K_HUMAN_RULE => self::H_ANY_OTHER, self::K_EXAMPLE => self::E_OTHER_0_5_20],
        ],
        // Rule 5: nplurals=5; (Irish)
        // Locales (1): ga
        5 => [
            [self::K_RULE => self::R_N_EQ_1, self::K_HUMAN_RULE => self::H_EXACTLY_1, self::K_EXAMPLE => self::E_1],
            [self::K_RULE => self::R_N_EQ_2, self::K_HUMAN_RULE => self::H_EXACTLY_2, self::K_EXAMPLE => self::E_2],
            [self::K_RULE => 'n = 3–6', self::K_HUMAN_RULE => 'Exactly 3, 4, 5, or 6', self::K_EXAMPLE => '3~6'],
            [self::K_RULE => 'n = 7–10', self::K_HUMAN_RULE => 'Exactly 7, 8, 9, or 10', self::K_EXAMPLE => '7~10'],
            [self::K_RULE => self::R_EMPTY, self::K_HUMAN_RULE => self::H_ANY_OTHER, self::K_EXAMPLE => '0, 11~25, 100, 1000, …'],
        ],
        // Rule 6: nplurals=4; (Lithuanian — CLDR 49: "many" for decimals only)
        // Locales (1): lt
        6 => [
            [
                self::K_RULE => self::R_ENDS_1_NOT_11,
                self::K_HUMAN_RULE => self::H_ENDS_1_EXCEPT_11,
                self::K_EXAMPLE => self::E_ENDS_1_LONG,
            ],
            [
                self::K_RULE => 'n % 10 = 2–9 and n % 100 != 12–19',
                self::K_HUMAN_RULE => 'Ends in 2–9 (except 12–19)',
                self::K_EXAMPLE => '2~9, 22~29, 32~39, …',
            ],
            [self::K_RULE => 'f != 0', self::K_HUMAN_RULE => self::H_DECIMAL_ONLY, self::K_EXAMPLE => '0.1~0.9, 1.1~1.7, …'],
            [self::K_RULE => self::R_EMPTY, self::K_HUMAN_RULE => self::H_ANY_OTHER, self::K_EXAMPLE => '0, 10~20, 30, 40, 50, 60, 100, …'],
        ],
        // Rule 7: nplurals=4; (Slovenian, Lower/Upper Sorbian)
        // Locales (3): dsb, hsb, sl
        7 => [
            [
                self::K_RULE => 'v = 0 and i % 100 = 1',
                self::K_HUMAN_RULE => 'Ends in 01 (including the decimal part)',
                self::K_EXAMPLE => '1, 101, 201, …',
            ],
            [
                self::K_RULE => 'v = 0 and i % 100 = 2',
                self::K_HUMAN_RULE => 'Ends in 02 (including after the decimal point)',
                self::K_EXAMPLE => '2, 102, 202, …',
            ],
            [
                self::K_RULE => 'v = 0 and i % 100 = 3–4',
                self::K_HUMAN_RULE => 'Ends in 03 or 04 (including after the decimal point)',
                self::K_EXAMPLE => '3, 4, 103, 104, 203, 204, …',
            ],
            [self::K_RULE => self::R_EMPTY, self::K_HUMAN_RULE => self::H_ANY_OTHER, self::K_EXAMPLE => '0, 5~19, 100, 105~119, …'],
        ],
        // Rule 8: nplurals=2; (Macedonian)
        // Locales (1): mk
        8 => [
            [
                self::K_RULE => self::R_ENDS_1_NOT_11,
                self::K_HUMAN_RULE => self::H_ENDS_1_EXCEPT_11,
                self::K_EXAMPLE => self::E_ENDS_1_SHORT,
            ],
            [self::K_RULE => self::R_EMPTY, self::K_HUMAN_RULE => self::H_ANY_OTHER, self::K_EXAMPLE => '0, 2~20, 22~30, …'],
        ],
        // Rule 10: nplurals=3; (Latvian)
        // Locales (4): ltg, lv, lvs, prg
        10 => [
            [
                self::K_RULE => 'n % 10 = 0 or n % 100 = 11–19',
                self::K_HUMAN_RULE => 'Ends in 0 or 11–19',
                self::K_EXAMPLE => '0, 10~20, 30, 40, …'
            ],
            [
                self::K_RULE => self::R_ENDS_1_NOT_11,
                self::K_HUMAN_RULE => self::H_ENDS_1_EXCEPT_11,
                self::K_EXAMPLE => '1, 21, 31, 41, …'
            ],
            [self::K_RULE => self::R_EMPTY, self::K_HUMAN_RULE => self::H_ANY_OTHER, self::K_EXAMPLE => '2~9, 22~29, 32~39, …'],
        ],
        // Rule 11: nplurals=3; (Polish)
        // Locales (2): pl, szl
        11 => [
            [self::K_RULE => self::R_I1_V0, self::K_HUMAN_RULE => self::H_EXACTLY_1_NO_DEC, self::K_EXAMPLE => self::E_1],
            [
                self::K_RULE => 'v = 0 and i % 10 = 2–4 and i % 100 != 12–14',
                self::K_HUMAN_RULE => 'Ends in 2–4 (except 12–14, no decimals)',
                self::K_EXAMPLE => self::E_ENDS_2_4
            ],
            [
                self::K_RULE => 'v = 0 and i != 1 and i % 10 = 0–1 or v = 0 and i % 10 = 5–9 or v = 0 and i % 100 = 12–14',
                self::K_HUMAN_RULE => 'Ends in 0, 1, 5–9, or 12–14 (except 1, no decimals)',
                self::K_EXAMPLE => '0, 5~19, 100, …'
            ],
            [self::K_RULE => self::R_EMPTY, self::K_HUMAN_RULE => self::H_ANY_OTHER, self::K_EXAMPLE => self::E_DECIMAL_01_15],
        ],
        // Rule 12: nplurals=3; (Romanian, Moldavian)
        // Locales (2): mo, ro
        12 => [
            [self::K_RULE => self::R_I1_V0, self::K_HUMAN_RULE => self::H_EXACTLY_1_NO_DEC, self::K_EXAMPLE => self::E_1],
            [
                self::K_RULE => 'v != 0 or n = 0 or n % 100 = 2–19',
                self::K_HUMAN_RULE => 'Exactly 0 or ends in 02–19',
                self::K_EXAMPLE => '0, 2~19, 102~119, …'
            ],
            [self::K_RULE => self::R_EMPTY, self::K_HUMAN_RULE => self::H_ANY_OTHER, self::K_EXAMPLE => '20~35, 100, 1000, …'],
        ],
        // Rule 13: nplurals=6; (Arabic)
        // Locales (2): ar, shu
        13 => [
            [self::K_RULE => self::R_N_EQ_0, self::K_HUMAN_RULE => self::H_EXACTLY_0, self::K_EXAMPLE => self::E_0],
            [self::K_RULE => self::R_N_EQ_1, self::K_HUMAN_RULE => self::H_EXACTLY_1, self::K_EXAMPLE => self::E_1],
            [self::K_RULE => self::R_N_EQ_2, self::K_HUMAN_RULE => self::H_EXACTLY_2, self::K_EXAMPLE => self::E_2],
            [self::K_RULE => 'n % 100 = 3–10', self::K_HUMAN_RULE => 'Ends in 03–10', self::K_EXAMPLE => '3~10, 103~110, …'],
            [self::K_RULE => 'n % 100 = 11–99', self::K_HUMAN_RULE => 'Ends in 11-99', self::K_EXAMPLE => '11~26, 111~126, …'],
            [self::K_RULE => self::R_EMPTY, self::K_HUMAN_RULE => self::H_ANY_OTHER, self::K_EXAMPLE => '100~102, 200~202, …'],
        ],
        // Rule 14: nplurals=6; (Welsh)
        // Locales (1): cy
        14 => [
            [self::K_RULE => self::R_N_EQ_0, self::K_HUMAN_RULE => self::H_EXACTLY_0, self::K_EXAMPLE => self::E_0],
            [self::K_RULE => self::R_N_EQ_1, self::K_HUMAN_RULE => self::H_EXACTLY_1, self::K_EXAMPLE => self::E_1],
            [self::K_RULE => self::R_N_EQ_2, self::K_HUMAN_RULE => self::H_EXACTLY_2, self::K_EXAMPLE => self::E_2],
            [self::K_RULE => 'n = 3', self::K_HUMAN_RULE => 'Exactly 3', self::K_EXAMPLE => self::E_3],
            [self::K_RULE => self::R_N_EQ_6, self::K_HUMAN_RULE => self::H_EXACTLY_6, self::K_EXAMPLE => self::E_6],
            [self::K_RULE => self::R_EMPTY, self::K_HUMAN_RULE => self::H_ANY_OTHER, self::K_EXAMPLE => '4, 5, 7~20, 100, 1000, …'],
        ],
        // Rule 15: nplurals=2; (Icelandic)
        // Locales (1): is
        15 => [
            [
                self::K_RULE => self::R_ENDS_1_NOT_11,
                self::K_HUMAN_RULE => self::H_ENDS_1_EXCEPT_11,
                self::K_EXAMPLE => self::E_ENDS_1_SHORT
            ],
            [self::K_RULE => self::R_EMPTY, self::K_HUMAN_RULE => self::H_ANY_OTHER, self::K_EXAMPLE => self::E_OTHER_0_2_16],
        ],
        // Rule 16: nplurals=4; (Scottish Gaelic)
        // Locales (1): gd
        16 => [
            [self::K_RULE => 'n = 1, 11', self::K_HUMAN_RULE => 'Exactly 1 or 11', self::K_EXAMPLE => '1, 11'],
            [self::K_RULE => 'n = 2, 12', self::K_HUMAN_RULE => 'Exactly 2 or 12', self::K_EXAMPLE => '2, 12'],
            [self::K_RULE => 'n = 3–10, 13–19', self::K_HUMAN_RULE => 'Exactly 3–10 or 13–19', self::K_EXAMPLE => '3~10, 13~19'],
            [self::K_RULE => self::R_EMPTY, self::K_HUMAN_RULE => self::H_ANY_OTHER, self::K_EXAMPLE => '0, 20~34, 100, 1000, …'],
        ],
        // Rule 17: nplurals=5; (Breton)
        // Locales (1): br
        17 => [
            [
                self::K_RULE => 'n % 10 = 1 and n % 100 != 11, 71, 91',
                self::K_HUMAN_RULE => 'Ends in 1 (except 11, 71 or 91)',
                self::K_EXAMPLE => '1, 21, 31, 41, 51, 61, 81, 101, …'
            ],
            [
                self::K_RULE => 'n % 10 = 2 and n % 100 != 12, 72, 92',
                self::K_HUMAN_RULE => 'Ends in 2 (except 12, 72 or 92)',
                self::K_EXAMPLE => '2, 22, 32, 42, 52, 62, 82, 102, …'
            ],
            [
                self::K_RULE => 'n % 10 = 3–4, 9 and n % 100 != 10–19, 70–79, 90–99',
                self::K_HUMAN_RULE => 'Ends in 3, 4, or 9 (except 10–19, 70–79, 90–99)',
                self::K_EXAMPLE => '3, 4, 9, 23, 24, 29, …'
            ],
            [
                self::K_RULE => 'n != 0 and n % 1000000 = 0',
                self::K_HUMAN_RULE => 'Non-zero multiple of 1,000,000',
                self::K_EXAMPLE => '1000000, 2000000, …'
            ],
            [self::K_RULE => self::R_EMPTY, self::K_HUMAN_RULE => self::H_ANY_OTHER, self::K_EXAMPLE => '0, 5~8, 10~20, 100, 1000, …'],
        ],
        // Rule 18: nplurals=5; (Manx — CLDR 49: "many" for decimals only)
        // Locales (1): gv
        18 => [
            [self::K_RULE => 'n % 10 = 1', self::K_HUMAN_RULE => 'Ends in 1', self::K_EXAMPLE => '1, 11, 21, 31, …'],
            [self::K_RULE => 'n % 10 = 2', self::K_HUMAN_RULE => 'Ends in 2', self::K_EXAMPLE => '2, 12, 22, 32, …'],
            [self::K_RULE => 'n % 20 = 0', self::K_HUMAN_RULE => 'Multiple of 20', self::K_EXAMPLE => '0, 20, 40, 60, 80, 100, …'],
            [self::K_RULE => self::R_V_NE_0, self::K_HUMAN_RULE => self::H_DECIMAL_ONLY, self::K_EXAMPLE => self::E_DECIMAL_01_15],
            [self::K_RULE => self::R_EMPTY, self::K_HUMAN_RULE => self::H_ANY_OTHER, self::K_EXAMPLE => '3~9, 13~19, 23~29, …'],
        ],
        // Rule 19: nplurals=3; (Hebrew — CLDR 49: removed "many")
        // Locales (1): he
        19 => [
            [self::K_RULE => self::R_I1_V0, self::K_HUMAN_RULE => self::H_EXACTLY_1_NO_DEC, self::K_EXAMPLE => self::E_1],
            [self::K_RULE => 'i = 2 and v = 0', self::K_HUMAN_RULE => 'Exactly 2 (no decimals)', self::K_EXAMPLE => self::E_2],
            [self::K_RULE => self::R_EMPTY, self::K_HUMAN_RULE => self::H_ANY_OTHER, self::K_EXAMPLE => self::E_OTHER_0_3_17],
        ],
        // Rule 20: nplurals=3; (Italian, Spanish, Catalan - CLDR 49: one = i = 1 and v = 0)
        // Locales (8): ca, cav, es, it, lld, pt_pt, scn, vec
        20 => [
            [self::K_RULE => self::R_I1_V0, self::K_HUMAN_RULE => self::H_EXACTLY_1_NO_DEC, self::K_EXAMPLE => self::E_1],
            [
                self::K_RULE => 'e = 0 and i != 0 and i % 1000000 = 0 and v = 0 or e != 0–5',
                self::K_HUMAN_RULE => 'Multiple of 1,000,000 (no decimals)',
                self::K_EXAMPLE => '1000000, 2000000, …'
            ],
            [self::K_RULE => self::R_EMPTY, self::K_HUMAN_RULE => self::H_ANY_OTHER, self::K_EXAMPLE => self::E_OTHER_0_2_16],
        ],
        // Rule 21: nplurals=3; (Inuktitut, Sami, Nama, Swampy Cree)
        // Locales (8): iu, naq, sat, se, sma, smj, smn, sms
        21 => [
            [self::K_RULE => self::R_N_EQ_1, self::K_HUMAN_RULE => self::H_EXACTLY_1, self::K_EXAMPLE => self::E_1],
            [self::K_RULE => self::R_N_EQ_2, self::K_HUMAN_RULE => self::H_EXACTLY_2, self::K_EXAMPLE => self::E_2],
            [self::K_RULE => self::R_EMPTY, self::K_HUMAN_RULE => self::H_ANY_OTHER, self::K_EXAMPLE => self::E_OTHER_0_3_17],
        ],
        // Rule 22: nplurals=3; (Colognian, Anii, Langi)
        // Locales (3): blo, ksh, lag
        22 => [
            [self::K_RULE => self::R_N_EQ_0, self::K_HUMAN_RULE => self::H_EXACTLY_0, self::K_EXAMPLE => self::E_0],
            [self::K_RULE => self::R_N_EQ_1, self::K_HUMAN_RULE => self::H_EXACTLY_1, self::K_EXAMPLE => self::E_1],
            [self::K_RULE => self::R_EMPTY, self::K_HUMAN_RULE => self::H_ANY_OTHER, self::K_EXAMPLE => self::E_OTHER_2_17],
        ],
        // Rule 23: nplurals=3; (Tachelhit)
        // Locales (1): shi
        23 => [
            [self::K_RULE => 'n = 0–1', self::K_HUMAN_RULE => 'Exactly 0 or 1', self::K_EXAMPLE => self::E_0_1],
            [self::K_RULE => 'n = 2–10', self::K_HUMAN_RULE => 'Exactly 2 through 10', self::K_EXAMPLE => '2~10'],
            [self::K_RULE => self::R_EMPTY, self::K_HUMAN_RULE => self::H_ANY_OTHER, self::K_EXAMPLE => '11~26, 100, 1000, …'],
        ],
        // Rule 24: nplurals=6; (Cornish)
        // Locales (1): kw
        24 => [
            [self::K_RULE => self::R_N_EQ_0, self::K_HUMAN_RULE => self::H_EXACTLY_0, self::K_EXAMPLE => self::E_0],
            [self::K_RULE => self::R_N_EQ_1, self::K_HUMAN_RULE => self::H_EXACTLY_1, self::K_EXAMPLE => self::E_1],
            [
                self::K_RULE => 'n % 100 = 2, 22, 42, 62, 82 or n % 1000 = 0 ...',
                self::K_HUMAN_RULE => 'Ends in 02, 22, 42, 62, 82 (or special multiples of 1000)',
                self::K_EXAMPLE => '2, 22, 42, 62, 82, 102, …'
            ],
            [
                self::K_RULE => 'n % 100 = 3, 23, 43, 63, 83',
                self::K_HUMAN_RULE => 'Ends in 03, 23, 43, 63, 83',
                self::K_EXAMPLE => '3, 23, 43, 63, 83, 103, …'
            ],
            [
                self::K_RULE => 'n != 1 and n % 100 = 1, 21, 41, 61, 81',
                self::K_HUMAN_RULE => 'Ends in 01, 21, 41, 61, 81 (except 1)',
                self::K_EXAMPLE => '21, 41, 61, 81, 101, …'
            ],
            [self::K_RULE => self::R_EMPTY, self::K_HUMAN_RULE => self::H_ANY_OTHER, self::K_EXAMPLE => '4~20, 24~40, 44~60, …'],
        ],
        // Rule 25: nplurals=2; (Filipino, Tagalog - CLDR 49)
        // one: v = 0 and i = 1,2,3 or v = 0 and i % 10 != 4,6,9 or v != 0 and f % 10 != 4,6,9
        // Locales (2): fil, tl
        25 => [
            [
                self::K_RULE => 'v = 0 and i = 1, 2, 3 or v = 0 and i % 10 != 4, 6, 9 or v != 0 and f % 10 != 4, 6, 9',
                self::K_HUMAN_RULE => 'Does not end in 4, 6, 9 (including the part after the decimal point)',
                self::K_EXAMPLE => '0, 1, 2, 3, 5, 7, 8, 10, 11, 12, 13, 15, …'
            ],
            [self::K_RULE => self::R_EMPTY, self::K_HUMAN_RULE => self::H_ANY_OTHER, self::K_EXAMPLE => '4, 6, 9, 14, 16, 19, 24, …'],
        ],
        // Rule 26: nplurals=2; (Central Atlas Tamazight - CLDR 49)
        // one: n = 0..1 or n = 11..99
        // Locales (1): tzm
        26 => [
            [
                self::K_RULE => 'n = 0–1 or n = 11–99',
                self::K_HUMAN_RULE => 'Exactly 0–1 or 11–99',
                self::K_EXAMPLE => '0, 1, 11~99'
            ],
            [self::K_RULE => self::R_EMPTY, self::K_HUMAN_RULE => self::H_ANY_OTHER, self::K_EXAMPLE => '2~10, 100~110, 1000, …'],
        ],
        // Rule 27: nplurals=3; (Bosnian, Croatian, Serbian — CLDR 49: one/few/other)
        // Locales (6): bs, hr, me, rmn, sh, sr
        27 => [
            [
                self::K_RULE => 'v = 0 and i % 10 = 1 and i % 100 != 11 or f % 10 = 1 and f % 100 != 11',
                self::K_HUMAN_RULE => self::H_ENDS_1_EXCEPT_11,
                self::K_EXAMPLE => self::E_ENDS_1_LONG
            ],
            [
                self::K_RULE => 'v = 0 and i % 10 = 2–4 and i % 100 != 12–14 or f % 10 = 2–4 and f % 100 != 12–14',
                self::K_HUMAN_RULE => 'Ends in 2-4 (except 12–14)',
                self::K_EXAMPLE => self::E_ENDS_2_4
            ],
            [self::K_RULE => self::R_EMPTY, self::K_HUMAN_RULE => self::H_ANY_OTHER, self::K_EXAMPLE => '0, 5~20, 25~30, 35~40, …'],
        ],
        // Rule 28: nplurals=5; (Maltese — CLDR 49: one/two/few/many/other)
        // Locales (1): mt
        28 => [
            [self::K_RULE => self::R_N_EQ_1, self::K_HUMAN_RULE => self::H_EXACTLY_1, self::K_EXAMPLE => self::E_1],
            [self::K_RULE => self::R_N_EQ_2, self::K_HUMAN_RULE => self::H_EXACTLY_2, self::K_EXAMPLE => self::E_2],
            [
                self::K_RULE => 'n = 0 or n % 100 = 3–10',
                self::K_HUMAN_RULE => 'Exactly 0 or ends in 03–10',
                self::K_EXAMPLE => '0, 3~10, 103~110, …'
            ],
            [self::K_RULE => 'n % 100 = 11–19', self::K_HUMAN_RULE => 'Ends in 11–19', self::K_EXAMPLE => '11~19, 111~119, …'],
            [self::K_RULE => self::R_EMPTY, self::K_HUMAN_RULE => self::H_ANY_OTHER, self::K_EXAMPLE => '20~35, 100~102, 200~202, 1000, …'],
        ],
        // Rule 29: nplurals=3; (French, Portuguese - CLDR 49: one = i = 0,1)
        // Locales (3): fr, ht, pt
        29 => [
            [self::K_RULE => 'i = 0,1', self::K_HUMAN_RULE => '0 or 1', self::K_EXAMPLE => self::E_0_1],
            [
                self::K_RULE => 'e = 0 and i != 0 and i % 1000000 = 0 and v = 0 or e != 0–5',
                self::K_HUMAN_RULE => 'Multiple of 1,000,000 (no decimals)',
                self::K_EXAMPLE => '1000000, 2000000, …'
            ],
            [self::K_RULE => self::R_EMPTY, self::K_HUMAN_RULE => self::H_ANY_OTHER, self::K_EXAMPLE => self::E_OTHER_2_17],
        ],
    ];

    /**
     * Human-readable descriptions for ordinal plural rules.
     *
     * Maps a rule group number to a positional array of {rule, human_rule, example}.
     * The position corresponds to the category index returned by PluralRules::getOrdinalCategories().
     *
     * @var array<int, array<int, array{rule: string, human_rule: string, example: string}>>
     */
    private const array ORDINAL_HUMAN_RULES = [
        // Rule 0: Only "other"
        // Locales (328, default): All locales not listed in other ordinal rules, including:
        //   aa, ace, acf, ak, am, an, ar, ast, awa, ayr, ba, bah, ban, bh, bho, bi, bjn,
        //   bjs, bm, bo, bod, br, brx, bs, bug, cb, ce, ceb, ch, chk, chr, cjk, ckb, cop,
        //   crh, crs, cs, csw, ctg, da, de, dik, diq, div, doi, dsb, dv, dyu, dz, ee, el,
        //   eo, et, eu, fa, ff, fi, fj, fn, fo, fon, fuc, fur, fuv, fy, gax, gaz, gcl, gil,
        //   gl, glw, gn, grc, grt, gsw, guz, gv, gyn, ha, haw, he, hig, hil, hmn, hne, hnj,
        //   hoc, hr, hsb, ia, id, ig, ii, ilo, io, is, iu, ja, jam, jbo, jgo, ji, jmc, jv,
        //   kab, kac, kaj, kal, kam, kar, kas, kbp, kcg, kde, kea, kg, kha, khk, ki, kjb,
        //   kkj, kl, kln, km, kmb, kmr, kn, knc, ko, kr, ks, ksb, ksh, ksw, ku, ky, la,
        //   lag, lb, lg, li, lkt, lmo, ln, lt, ltg, lua, lug, luo, lus, luy, lv, lvs, mag,
        //   mai, mam, mas, me, men, mer, mfe, mfi, mfv, mg, mgo, mh, mhr, mi, min, ml, mn,
        //   mni, mnk, mos, mrj, mrt, mt, my, nb, naq, nd, ndc, niu, nl, nn, nnh, no, nqo,
        //   nr, ns, nso, nup, nus, ny, nyf, nyn, oc, om, os, osa, pa, pag, pap, pau, pbt,
        //   pcm, pi, pis, pko, pl, plt, pon, pot, pov, ppk, prg, prs, ps, pt, pt_pt, qnt,
        //   qu, quc, quy, rhg, rhl, rm, rmn, rmo, rn, rof, roh, ru, run, rw, rwk, sa, sah,
        //   saq, sat, sd, sdh, se, seh, ses, sg, sh, shi, shn, shu, si, sk, sl, sm, sma,
        //   smj, smn, smo, sms, sn, sna, snk, so, sr, srn, ss, ssy, st, su, sus, svc, sw,
        //   syc, syr, szl, ta, taq, te, teo, tet, tg, th, ti, tig, tiv, tkl, tmh, tn, to,
        //   ton, tpi, tr, trv, ts, tsc, tt, tum, tvl, tw, ty, tzm, udm, ug, umb, ur, uz,
        //   uzn, ve, vic, vls, vmw, vo, vun, wa, wae, war, wls, wo, xh, xog, ydd, yi, ymm,
        //   yo, yue, zdj, zh, zu
        0 => [
            [
                self::K_RULE => self::R_EMPTY,
                self::K_HUMAN_RULE => self::H_ANY_NUMBER,
                self::K_EXAMPLE => self::E_ALL_NUMBERS
            ],
        ],
        // Rule 1: English-like ordinals (one/two/few/other)
        // Locales (1): en
        1 => [
            [
                self::K_RULE => self::R_ENDS_1_NOT_11,
                self::K_HUMAN_RULE => self::H_ENDS_1_EXCEPT_11,
                self::K_EXAMPLE => self::E_ENDS_1_SHORT
            ],
            [
                self::K_RULE => self::R_ENDS_2_NOT_12,
                self::K_HUMAN_RULE => self::H_ENDS_2_EXCEPT_12,
                self::K_EXAMPLE => '2, 22, 32, 42, 52, …'
            ],
            [
                self::K_RULE => 'n % 10 = 3 and n % 100 != 13',
                self::K_HUMAN_RULE => 'Ends in 3 (except 13)',
                self::K_EXAMPLE => '3, 23, 33, 43, 53, …'
            ],
            [self::K_RULE => self::R_EMPTY, self::K_HUMAN_RULE => self::H_ANY_OTHER, self::K_EXAMPLE => '0, 4~18, 100, 1000, …'],
        ],
        // Rule 2: French-like ordinals (one/other)
        // Locales (14): bal, fil, fr, ga, ht, hy, lo, mo, ms, ro, sv, tl, vi, zsm
        2 => [
            [self::K_RULE => self::R_N_EQ_1, self::K_HUMAN_RULE => self::H_EXACTLY_1, self::K_EXAMPLE => self::E_1],
            [self::K_RULE => self::R_EMPTY, self::K_HUMAN_RULE => self::H_ANY_OTHER, self::K_EXAMPLE => self::E_OTHER_0_2_16],
        ],
        // Rule 3: Only "other" (Slavic) — no locales directly mapped (kept for safety)
        // Locales: (included in Rule 0 default pool)
        3 => [
            [
                self::K_RULE => self::R_EMPTY,
                self::K_HUMAN_RULE => self::H_ANY_NUMBER,
                self::K_EXAMPLE => self::E_ALL_NUMBERS
            ],
        ],
        // Rule 4: Only "other" (Czech/Slovak) — no locales directly mapped (kept for safety)
        4 => [
            [
                self::K_RULE => self::R_EMPTY,
                self::K_HUMAN_RULE => self::H_ANY_NUMBER,
                self::K_EXAMPLE => self::E_ALL_NUMBERS
            ],
        ],
        // Rule 5: Irish ordinals (one/other) — no locales directly mapped (kept for safety)
        5 => [
            [self::K_RULE => self::R_N_EQ_1, self::K_HUMAN_RULE => self::H_EXACTLY_1, self::K_EXAMPLE => self::E_1],
            [self::K_RULE => self::R_EMPTY, self::K_HUMAN_RULE => self::H_ANY_OTHER, self::K_EXAMPLE => self::E_OTHER_0_2_16],
        ],
        // Rule 6: Only "other" (Lithuanian) — no locales directly mapped (kept for safety)
        6 => [
            [
                self::K_RULE => self::R_EMPTY,
                self::K_HUMAN_RULE => self::H_ANY_NUMBER,
                self::K_EXAMPLE => self::E_ALL_NUMBERS
            ],
        ],
        // Rule 7: Only "other" (Slovenian) — no locales directly mapped (kept for safety)
        7 => [
            [
                self::K_RULE => self::R_EMPTY,
                self::K_HUMAN_RULE => self::H_ANY_NUMBER,
                self::K_EXAMPLE => self::E_ALL_NUMBERS
            ],
        ],
        // Rule 8: Macedonian ordinals (one/two/many/other)
        // Locales (1): mk
        8 => [
            [
                self::K_RULE => self::R_ENDS_1_NOT_11,
                self::K_HUMAN_RULE => self::H_ENDS_1_EXCEPT_11,
                self::K_EXAMPLE => self::E_ENDS_1_SHORT
            ],
            [
                self::K_RULE => self::R_ENDS_2_NOT_12,
                self::K_HUMAN_RULE => self::H_ENDS_2_EXCEPT_12,
                self::K_EXAMPLE => '2, 22, 32, 42, 52, …'
            ],
            [
                self::K_RULE => 'n % 10 = 7, 8 and n % 100 != 17, 18',
                self::K_HUMAN_RULE => 'Ends in 7 or 8 (except 17, 18)',
                self::K_EXAMPLE => '7, 8, 27, 28, 37, 38, …'
            ],
            [self::K_RULE => self::R_EMPTY, self::K_HUMAN_RULE => self::H_ANY_OTHER, self::K_EXAMPLE => '0, 3~6, 9~19, 23~26, …'],
        ],
        // Rule 10: Only "other" (Latvian) — no locales directly mapped (kept for safety)
        10 => [
            [
                self::K_RULE => self::R_EMPTY,
                self::K_HUMAN_RULE => self::H_ANY_NUMBER,
                self::K_EXAMPLE => self::E_ALL_NUMBERS
            ],
        ],
        // Rule 11: Only "other" (Polish) — no locales directly mapped (kept for safety)
        11 => [
            [
                self::K_RULE => self::R_EMPTY,
                self::K_HUMAN_RULE => self::H_ANY_NUMBER,
                self::K_EXAMPLE => self::E_ALL_NUMBERS
            ],
        ],
        // Rule 12: Romanian ordinals (one/other) — no locales directly mapped (ro/mo use ordinal=2)
        12 => [
            [self::K_RULE => self::R_N_EQ_1, self::K_HUMAN_RULE => self::H_EXACTLY_1, self::K_EXAMPLE => self::E_1],
            [self::K_RULE => self::R_EMPTY, self::K_HUMAN_RULE => self::H_ANY_OTHER, self::K_EXAMPLE => self::E_OTHER_0_2_16],
        ],
        // Rule 13: Only "other" (Arabic) — no locales directly mapped (kept for safety)
        13 => [
            [
                self::K_RULE => self::R_EMPTY,
                self::K_HUMAN_RULE => self::H_ANY_NUMBER,
                self::K_EXAMPLE => self::E_ALL_NUMBERS
            ],
        ],
        // Rule 14: Welsh ordinals (zero/one/two/few/many/other)
        // Locales (1): cy
        14 => [
            [self::K_RULE => 'n = 0, 7, 8, 9', self::K_HUMAN_RULE => 'Exactly 0, 7, 8 ,9', self::K_EXAMPLE => '0, 7, 8, 9'],
            [self::K_RULE => self::R_N_EQ_1, self::K_HUMAN_RULE => self::H_EXACTLY_1, self::K_EXAMPLE => self::E_1],
            [self::K_RULE => self::R_N_EQ_2, self::K_HUMAN_RULE => self::H_EXACTLY_2, self::K_EXAMPLE => self::E_2],
            [self::K_RULE => 'n = 3, 4', self::K_HUMAN_RULE => 'Exactly 3 or 4', self::K_EXAMPLE => '3, 4'],
            [self::K_RULE => 'n = 5, 6', self::K_HUMAN_RULE => 'Exactly 5 or 6', self::K_EXAMPLE => '5, 6'],
            [self::K_RULE => self::R_EMPTY, self::K_HUMAN_RULE => self::H_ANY_OTHER, self::K_EXAMPLE => '10~25, 100, 1000, …'],
        ],
        // Rule 15: Only "other" (Icelandic) — no locales directly mapped (kept for safety)
        15 => [
            [
                self::K_RULE => self::R_EMPTY,
                self::K_HUMAN_RULE => self::H_ANY_NUMBER,
                self::K_EXAMPLE => self::E_ALL_NUMBERS
            ],
        ],
        // Rule 16: Scottish Gaelic ordinals (one/two/few/other)
        // Locales (1): gd
        16 => [
            [self::K_RULE => 'n = 1, 11', self::K_HUMAN_RULE => 'Exactly 1 or 11', self::K_EXAMPLE => '1, 11'],
            [self::K_RULE => 'n = 2, 12', self::K_HUMAN_RULE => 'Exactly 2 or 12', self::K_EXAMPLE => '2, 12'],
            [self::K_RULE => 'n = 3, 13', self::K_HUMAN_RULE => 'Exactly 3 or 13', self::K_EXAMPLE => '3, 13'],
            [self::K_RULE => self::R_EMPTY, self::K_HUMAN_RULE => self::H_ANY_OTHER, self::K_EXAMPLE => '0, 4~10, 14~21, 100, …'],
        ],
        // Rule 17: Only "other" (Breton) — no locales directly mapped (kept for safety)
        17 => [
            [
                self::K_RULE => self::R_EMPTY,
                self::K_HUMAN_RULE => self::H_ANY_NUMBER,
                self::K_EXAMPLE => self::E_ALL_NUMBERS
            ],
        ],
        // Rule 18: Only "other" (Manx) — no locales directly mapped (kept for safety)
        18 => [
            [
                self::K_RULE => self::R_EMPTY,
                self::K_HUMAN_RULE => self::H_ANY_NUMBER,
                self::K_EXAMPLE => self::E_ALL_NUMBERS
            ],
        ],
        // Rule 19: Only "other" (Hebrew) — no locales directly mapped (kept for safety)
        19 => [
            [
                self::K_RULE => self::R_EMPTY,
                self::K_HUMAN_RULE => self::H_ANY_NUMBER,
                self::K_EXAMPLE => self::E_ALL_NUMBERS
            ],
        ],
        // Rule 20: Italian ordinals (many/other)
        // Locales (6): it, lij, lld, sc, scn, vec
        20 => [
            [self::K_RULE => 'n = 8, 11, 80, 800', self::K_HUMAN_RULE => 'Exactly 8, 11, 80 or 800', self::K_EXAMPLE => '8, 11, 80, 800'],
            [self::K_RULE => self::R_EMPTY, self::K_HUMAN_RULE => self::H_ANY_OTHER, self::K_EXAMPLE => '0~7, 9, 10, 12~79, 81~99, 100, …'],
        ],
        // Rule 21: Kazakh, Azerbaijani ordinals (many/other)
        // Locales (1): kk
        21 => [
            [
                self::K_RULE => 'n % 10 = 6,9 or n % 10 = 0 and n != 0',
                self::K_HUMAN_RULE => 'Ends in 0 (except 0 itself), 6 or 9',
                self::K_EXAMPLE => '6, 9, 10, 16, 19, 20, 26, 29, 30, …'
            ],
            [self::K_RULE => self::R_EMPTY, self::K_HUMAN_RULE => self::H_ANY_OTHER, self::K_EXAMPLE => '0~5, 7, 8, 11~15, 17, 18, 21, …'],
        ],
        // Rule 22: Ukrainian, Turkmen ordinals (few/other)
        // Locales (2): tk, uk
        22 => [
            [self::K_RULE => self::R_N_EQ_1_5, self::K_HUMAN_RULE => 'Exactly 1 or 5', self::K_EXAMPLE => self::E_1_5],
            [self::K_RULE => self::R_EMPTY, self::K_HUMAN_RULE => self::H_ANY_OTHER, self::K_EXAMPLE => '0, 2~4, 6~17, 100, 1000, …'],
        ],
        // Rule 23: Bengali, Assamese, Hindi ordinals (one/two/few/many/other) — CLDR 49
        // Locales (4): as, asm, bn, hi
        23 => [
            [
                self::K_RULE => 'n = 1, 5, 7, 8, 9, 10',
                self::K_HUMAN_RULE => 'Exactly 1, 5, 7, 8, 9 or 10',
                self::K_EXAMPLE => '1, 5, 7, 8, 9, 10'
            ],
            [self::K_RULE => self::R_N_EQ_2_3, self::K_HUMAN_RULE => self::H_EXACTLY_2_OR_3, self::K_EXAMPLE => self::E_2_3],
            [self::K_RULE => self::R_N_EQ_4, self::K_HUMAN_RULE => self::H_EXACTLY_4, self::K_EXAMPLE => self::E_4],
            [self::K_RULE => self::R_N_EQ_6, self::K_HUMAN_RULE => self::H_EXACTLY_6, self::K_EXAMPLE => self::E_6],
            [self::K_RULE => self::R_EMPTY, self::K_HUMAN_RULE => self::H_ANY_OTHER, self::K_EXAMPLE => '0, 11~25, 100, 1000, …'],
        ],
        // Rule 24: Gujarati ordinals (one/two/few/many/other)
        // Locales (1): gu
        24 => [
            [self::K_RULE => self::R_N_EQ_1, self::K_HUMAN_RULE => self::H_EXACTLY_1, self::K_EXAMPLE => self::E_1],
            [self::K_RULE => self::R_N_EQ_2_3, self::K_HUMAN_RULE => self::H_EXACTLY_2_OR_3, self::K_EXAMPLE => self::E_2_3],
            [self::K_RULE => self::R_N_EQ_4, self::K_HUMAN_RULE => self::H_EXACTLY_4, self::K_EXAMPLE => self::E_4],
            [self::K_RULE => self::R_N_EQ_6, self::K_HUMAN_RULE => self::H_EXACTLY_6, self::K_EXAMPLE => self::E_6],
            [self::K_RULE => self::R_EMPTY, self::K_HUMAN_RULE => self::H_ANY_OTHER, self::K_EXAMPLE => '0, 5, 7~20, 100, 1000, …'],
        ],
        // Rule 26: Marathi/Konkani ordinals (one/two/few/other) — CLDR 49
        // Locales (2): kok, mr
        26 => [
            [self::K_RULE => self::R_N_EQ_1, self::K_HUMAN_RULE => self::H_EXACTLY_1, self::K_EXAMPLE => self::E_1],
            [self::K_RULE => self::R_N_EQ_2_3, self::K_HUMAN_RULE => self::H_EXACTLY_2_OR_3, self::K_EXAMPLE => self::E_2_3],
            [self::K_RULE => self::R_N_EQ_4, self::K_HUMAN_RULE => self::H_EXACTLY_4, self::K_EXAMPLE => self::E_4],
            [self::K_RULE => self::R_EMPTY, self::K_HUMAN_RULE => self::H_ANY_OTHER, self::K_EXAMPLE => self::E_OTHER_0_5_20],
        ],
        // Rule 27: Odia ordinals (one/two/few/many/other)
        // Locales (2): or, ory
        27 => [
            [self::K_RULE => 'n = 1, 5, 7–9', self::K_HUMAN_RULE => 'Exactly 1, 5, 7, 8, or 9', self::K_EXAMPLE => '1, 5, 7, 8, 9'],
            [self::K_RULE => self::R_N_EQ_2_3, self::K_HUMAN_RULE => self::H_EXACTLY_2_OR_3, self::K_EXAMPLE => self::E_2_3],
            [self::K_RULE => self::R_N_EQ_4, self::K_HUMAN_RULE => self::H_EXACTLY_4, self::K_EXAMPLE => self::E_4],
            [self::K_RULE => self::R_N_EQ_6, self::K_HUMAN_RULE => self::H_EXACTLY_6, self::K_EXAMPLE => self::E_6],
            [self::K_RULE => self::R_EMPTY, self::K_HUMAN_RULE => self::H_ANY_OTHER, self::K_EXAMPLE => '0, 10~25, 100, 1000, …'],
        ],
        // Rule 29: Nepali ordinals (one/other) — CLDR 49: simplified
        // Locales (1): ne
        29 => [
            [self::K_RULE => 'n = 1–4', self::K_HUMAN_RULE => 'Exactly 1, 2, 3 or 4', self::K_EXAMPLE => '1~4'],
            [self::K_RULE => self::R_EMPTY, self::K_HUMAN_RULE => self::H_ANY_OTHER, self::K_EXAMPLE => self::E_OTHER_0_5_20],
        ],
        // Rule 30: Albanian ordinals (one/many/other)
        // Locales (2): als, sq
        30 => [
            [self::K_RULE => self::R_N_EQ_1, self::K_HUMAN_RULE => self::H_EXACTLY_1, self::K_EXAMPLE => self::E_1],
            [self::K_RULE => self::R_N_EQ_4, self::K_HUMAN_RULE => self::H_EXACTLY_4, self::K_EXAMPLE => self::E_4],
            [self::K_RULE => self::R_EMPTY, self::K_HUMAN_RULE => self::H_ANY_OTHER, self::K_EXAMPLE => '0, 2, 3, 5~16, 100, 1000, …'],
        ],
        // Rule 31: Anii ordinals (zero/one/few/other)
        // Locales (1): blo
        31 => [
            [self::K_RULE => self::R_I_EQ_0, self::K_HUMAN_RULE => self::H_EXACTLY_0, self::K_EXAMPLE => self::E_0],
            [self::K_RULE => self::R_I_EQ_1, self::K_HUMAN_RULE => self::H_EXACTLY_1, self::K_EXAMPLE => self::E_1],
            [self::K_RULE => 'i = 2, 3, 4, 5, 6', self::K_HUMAN_RULE => 'Exactly 2, 3, 4, 5 or 6', self::K_EXAMPLE => '2~6'],
            [self::K_RULE => self::R_EMPTY, self::K_HUMAN_RULE => self::H_ANY_OTHER, self::K_EXAMPLE => '7~20, 100, 1000, …'],
        ],
        // Rule 32: Cornish ordinals (one/many/other)
        // Locales (1): kw
        32 => [
            [
                self::K_RULE => 'n = 1–4 or n % 100 = 1–4, 21–24, 41–44, 61–64, 81–84',
                self::K_HUMAN_RULE => 'Exactly 1–4 or ends in 01–04, 21–24, 41–44, 61–64, 81–84',
                self::K_EXAMPLE => '1~4, 21~24, 41~44, 61~64, 81~84, …'
            ],
            [
                self::K_RULE => 'n = 5 or n % 100 = 5',
                self::K_HUMAN_RULE => 'Exactly 5 or ends in 05',
                self::K_EXAMPLE => '5, 105, 205, …'
            ],
            [self::K_RULE => self::R_EMPTY, self::K_HUMAN_RULE => self::H_ANY_OTHER, self::K_EXAMPLE => '0, 6~20, 25~40, 45~60, …'],
        ],
        // Rule 33: Afrikaans ordinals (few/other) — CLDR 49
        // Locales (1): af
        33 => [
            [
                self::K_RULE => 'i % 100 = 2–19',
                self::K_HUMAN_RULE => 'Ends in 02–19',
                self::K_EXAMPLE => '2~19, 102~119, 202~219, …'
            ],
            [self::K_RULE => self::R_EMPTY, self::K_HUMAN_RULE => self::H_ANY_OTHER, self::K_EXAMPLE => '0, 1, 20~101, 120~201, …'],
        ],
        // Rule 34: Spanish ordinals (one/other) — CLDR 49
        // Locales (1): es
        34 => [
            [self::K_RULE => self::R_N_EQ_1, self::K_HUMAN_RULE => self::H_EXACTLY_1, self::K_EXAMPLE => self::E_1],
            [self::K_RULE => self::R_EMPTY, self::K_HUMAN_RULE => self::H_ANY_OTHER, self::K_EXAMPLE => self::E_OTHER_0_2_16],
        ],
        // Rule 35: Hungarian ordinals (one/other) — CLDR 49
        // Locales (1): hu
        35 => [
            [self::K_RULE => self::R_N_EQ_1_5, self::K_HUMAN_RULE => 'Exactly 1 or 5', self::K_EXAMPLE => self::E_1_5],
            [self::K_RULE => self::R_EMPTY, self::K_HUMAN_RULE => self::H_ANY_OTHER, self::K_EXAMPLE => '0, 2~4, 6~17, 100, 1000, …'],
        ],
        // Rule 36: Azerbaijani ordinals (one/few/many/other) — CLDR 49
        // Locales (3): az, azb, azj
        36 => [
            [
                self::K_RULE => 'i % 10 = 1, 2, 5, 7, 8 or i % 100 = 20, 50, 70, 80',
                self::K_HUMAN_RULE => 'Ends in 1, 2, 5, 7, 8 or ends in 20, 50, 70, 80',
                self::K_EXAMPLE => '1, 2, 5, 7, 8, 11, 12, 15, 17, 18, 20, …'
            ],
            [
                self::K_RULE => 'i % 10 = 3, 4 or i % 1000 = 100, 200, 300, 400, 500, 600, 700, 800, 900',
                self::K_HUMAN_RULE => 'Ends in 3, 4 or multiples of 100',
                self::K_EXAMPLE => '3, 4, 13, 14, 23, 24, 100, 200, …'
            ],
            [
                self::K_RULE => 'i = 0 or i % 10 = 6 or i % 100 = 40, 60, 90',
                self::K_HUMAN_RULE => 'Exactly 0, or ends in 6, 40, 60, or 90',
                self::K_EXAMPLE => '0, 6, 16, 26, 36, 40, 46, 56, 60, …'
            ],
            [self::K_RULE => self::R_EMPTY, self::K_HUMAN_RULE => self::H_ANY_OTHER, self::K_EXAMPLE => '9, 10, 19, 29, 30, 39, 49, 59, …'],
        ],
        // Rule 37: Belarusian ordinals (few/other) — CLDR 49
        // Locales (1): be
        37 => [
            [
                self::K_RULE => 'n % 10 = 2, 3 and n % 100 != 12, 13',
                self::K_HUMAN_RULE => 'Ends in 2 or 3 (except 12, 13)',
                self::K_EXAMPLE => '2, 3, 22, 23, 32, 33, 42, 43, …'
            ],
            [self::K_RULE => self::R_EMPTY, self::K_HUMAN_RULE => self::H_ANY_OTHER, self::K_EXAMPLE => '0, 1, 4~21, 24~31, 34~41, …'],
        ],
        // Rule 38: Bulgarian ordinals (zero/one/two/few/many/other) — CLDR 49
        // Locales (1): bg
        38 => [
            [self::K_RULE => 'i % 100 = 11', self::K_HUMAN_RULE => 'Ends in 11', self::K_EXAMPLE => '11, 111, 211, …'],
            [self::K_RULE => 'i % 100 = 1', self::K_HUMAN_RULE => self::H_ENDS_1_EXCEPT_11, self::K_EXAMPLE => '1, 21, 31, 41, …'],
            [self::K_RULE => 'i % 100 = 2', self::K_HUMAN_RULE => self::H_ENDS_2_EXCEPT_12, self::K_EXAMPLE => '2, 22, 32, 42, …'],
            [self::K_RULE => 'i % 100 = 7, 8', self::K_HUMAN_RULE => 'Ends in 7 or 8', self::K_EXAMPLE => '7, 8, 27, 28, …'],
            [self::K_RULE => 'i % 100 = 3–6', self::K_HUMAN_RULE => 'Ends in 3–6', self::K_EXAMPLE => '3~6, 23~26, 43~46, …'],
            [self::K_RULE => self::R_EMPTY, self::K_HUMAN_RULE => self::H_ANY_OTHER, self::K_EXAMPLE => '0, 9, 10, 12~20, 29, 30, …'],
        ],
        // Rule 39: Catalan ordinals (one/two/few/other) — CLDR 49
        // Locales (2): ca, cav
        39 => [
            [self::K_RULE => 'n = 1, 3', self::K_HUMAN_RULE => 'Exactly 1 or 3', self::K_EXAMPLE => '1, 3'],
            [self::K_RULE => self::R_N_EQ_2, self::K_HUMAN_RULE => self::H_EXACTLY_2, self::K_EXAMPLE => self::E_2],
            [self::K_RULE => self::R_N_EQ_4, self::K_HUMAN_RULE => self::H_EXACTLY_4, self::K_EXAMPLE => self::E_4],
            [self::K_RULE => self::R_EMPTY, self::K_HUMAN_RULE => self::H_ANY_OTHER, self::K_EXAMPLE => self::E_OTHER_0_5_20],
        ],
        // Rule 40: Georgian ordinals (one/many/other) — CLDR 49
        // Locales (1): ka
        40 => [
            [self::K_RULE => self::R_I_EQ_1, self::K_HUMAN_RULE => self::H_EXACTLY_1, self::K_EXAMPLE => self::E_1],
            [
                self::K_RULE => 'i = 0 or i % 100 = 2–20, 40, 60, 80',
                self::K_HUMAN_RULE => 'Exactly 0, or ends in 02–20, 40, 60, or 80',
                self::K_EXAMPLE => '0, 2~20, 40, 60, 80, 102~120, …'
            ],
            [self::K_RULE => self::R_EMPTY, self::K_HUMAN_RULE => self::H_ANY_OTHER, self::K_EXAMPLE => '21~39, 41~59, 61~79, 81~101, …'],
        ],
    ];

    /**
     * @param bool $forceRebuild When true, skip the cache and rebuild from PluralRules.
     * @param string|null $filePath Custom file path for the cache file. Defaults to the Locales directory.
     * @throws RuntimeException
     */
    private function __construct(bool $forceRebuild = false, ?string $filePath = null)
    {
        $filePath ??= self::DEFAULT_FILE_PATH;

        if (!$forceRebuild && file_exists($filePath)) {
            $this->rules = $this->readFromFile($filePath);
        } else {
            $this->rules = $this->build();
            $this->writeToFile($filePath);
        }
    }

    /**
     * Get the singleton instance.
     *
     * On the first call, initializes the builder with cache read-through:
     * - If the cache file exists and forceRebuild is false, reads from it.
     * - Otherwise, builds the rules from PluralRules and writes the cache file.
     *
     * @param bool $forceRebuild When true, skip the cache and rebuild from PluralRules.
     *                           Useful for tests or after updating PluralRules data.
     * @param string|null $filePath Custom file path for the cache file.
     * @return self
     * @throws RuntimeException
     */
    public static function getInstance(bool $forceRebuild = false, ?string $filePath = null): self
    {
        if (self::$instance === null) {
            self::$instance = new self($forceRebuild, $filePath);
        }

        return self::$instance;
    }

    /**
     * Destroy the singleton instance.
     *
     * This allows re-initialization with different parameters (e.g., forceRebuild for tests).
     */
    public static function destroyInstance(): void
    {
        self::$instance = null;
    }

    /**
     * Get the built plural rules.
     *
     * @return array<string, LanguageRulesFragment> Keyed by ISO code.
     */
    public function getRules(): array
    {
        return $this->rules;
    }

    /**
     * Get the plural rules for a specific language by its ISO code.
     *
     * @param string $isoCode ISO 639 language code (e.g. "en", "ar", "pt").
     * @return LanguageRulesFragment|null The language's plural rules, or null if not found.
     */
    public function getLanguageRules(string $isoCode): ?LanguageRulesFragment
    {
        return $this->rules[$isoCode] ?? null;
    }

    /**
     * Build the plural rules map for all supported languages.
     *
     * @return array<string, LanguageRulesFragment> Keyed by ISO code.
     */
    private function build(): array
    {
        $languages = Languages::getInstance();
        $enabledLanguages = $languages->getEnabledLanguages();
        $overrides = $this->loadOverrides();

        $result = [];

        foreach ($enabledLanguages as $rfc => $langInfo) {
            $isoCode = Languages::convertLanguageToIsoCode($rfc);
            // @codeCoverageIgnoreStart
            if ($isoCode === null) {
                continue;
            }
            // @codeCoverageIgnoreEnd

            $name = $langInfo['name'];

            // Build locale-specific entry (e.g. "pt-PT" → "pt_pt") when the
            // variant has its own entry in the PluralRules rulesMap.
            // This allows pt_PT to carry different overrides/examples than pt.
            $localeCode = strtolower(str_replace('-', '_', $rfc));
            if ($localeCode !== $isoCode && PluralRules::hasRulesFor($localeCode)) {
                $result[$localeCode] = $this->buildLanguageRulesFragment(
                    $name,
                    $localeCode,
                    $overrides
                );
            }

            // Skip duplicates (same iso code already processed)
            if (isset($result[$isoCode])) {
                continue;
            }

            $result[$isoCode] = $this->buildLanguageRulesFragment(
                $name,
                $isoCode,
                $overrides
            );
        }

        ksort($result);

        return $result;
    }

    /**
     * Build a LanguageRulesFragment for a given code.
     *
     * @param string $name     The display name.
     * @param string $code     The ISO or locale code (e.g. "pt" or "pt_PT").
     * @param array<string, array{cardinal?: array<string, array{human_rule?: string, example?: string}>, ordinal?: array<string, array{human_rule?: string, example?: string}>}> $overrides Per-language overrides.
     */
    private function buildLanguageRulesFragment(string $name, string $code, array $overrides): LanguageRulesFragment
    {
        $cardinalFragments = $this->buildCategoryFragments(
            PluralRules::getCardinalCategories($code),
            self::CARDINAL_HUMAN_RULES[PluralRules::getRuleGroup($code)] ?? [],
            $overrides[$code][self::K_CARDINAL] ?? []
        );

        $ordinalFragments = $this->buildCategoryFragments(
            PluralRules::getOrdinalCategories($code),
            self::ORDINAL_HUMAN_RULES[PluralRules::getRuleGroup($code, self::K_ORDINAL)] ?? [],
            $overrides[$code][self::K_ORDINAL] ?? []
        );

        return new LanguageRulesFragment($name, $code, $cardinalFragments, $ordinalFragments);
    }

    /**
     * Build CategoryFragment objects by zipping category names from PluralRules
     * with the positional human rule data, applying per-language overrides.
     *
     * @param array<int, string> $categories Category names from PluralRules (e.g. ['one', 'other']).
     * @param array<int, array{rule: string, human_rule: string, example: string}> $humanRules Positional human rule data.
     * @param array<string, array{human_rule?: string, example?: string}> $overrides Per-language overrides keyed by category name (e.g. 'one', 'other').
     * @return CategoryFragment[]
     */
    private function buildCategoryFragments(array $categories, array $humanRules, array $overrides = []): array
    {
        $fragments = [];

        foreach ($categories as $index => $category) {
            $ruleData = $humanRules[$index] ?? [
                self::K_RULE => self::R_EMPTY,
                self::K_HUMAN_RULE => self::H_ANY_OTHER,
                self::K_EXAMPLE => self::E_EMPTY,
            ];

            // Apply per-language overrides if present (keyed by category name)
            $humanRule = $overrides[$category][self::K_HUMAN_RULE] ?? $ruleData[self::K_HUMAN_RULE];
            $example   = $overrides[$category][self::K_EXAMPLE]    ?? $ruleData[self::K_EXAMPLE];

            $fragments[] = new CategoryFragment(
                category: $category,
                rule: $ruleData[self::K_RULE],
                human_rule: $humanRule,
                example: $example,
            );
        }

        return $fragments;
    }

    /**
     * Load per-language overrides for human_rule and example.
     *
     * Returns an array keyed by ISO code, then 'cardinal'/'ordinal',
     * then category name (e.g. 'one', 'other'), containing only the fields
     * that differ from the rule-group defaults.
     *
     * @return array<string, array{cardinal?: array<string, array{human_rule?: string, example?: string}>, ordinal?: array<string, array{human_rule?: string, example?: string}>}>
     */
    private function loadOverrides(): array
    {
        if (!file_exists(self::OVERRIDES_FILE_PATH)) {
            // @codeCoverageIgnoreStart
            return [];
            // @codeCoverageIgnoreEnd
        }

        return $this->readJsonFile(self::OVERRIDES_FILE_PATH);
    }

    /**
     * Read plural rules from a cached JSON file.
     *
     * @param string $filePath Path to the JSON file.
     * @return array<string, LanguageRulesFragment>
     * @throws RuntimeException
     */
    private function readFromFile(string $filePath): array
    {
        $data = $this->readJsonFile($filePath);

        $result = [];

        foreach ($data as $isoCode => $langData) {
            $cardinalFragments = [];
            foreach ($langData[self::K_CARDINAL] ?? [] as $cat) {
                $cardinalFragments[] = new CategoryFragment(
                    category: $cat['category'],
                    rule: $cat[self::K_RULE],
                    human_rule: $cat[self::K_HUMAN_RULE],
                    example: $cat[self::K_EXAMPLE],
                );
            }

            $ordinalFragments = [];
            foreach ($langData[self::K_ORDINAL] ?? [] as $cat) {
                $ordinalFragments[] = new CategoryFragment(
                    category: $cat['category'],
                    rule: $cat[self::K_RULE],
                    human_rule: $cat[self::K_HUMAN_RULE],
                    example: $cat[self::K_EXAMPLE],
                );
            }

            $result[$isoCode] = new LanguageRulesFragment(
                $langData['name'],
                $langData['isoCode'] ?? $isoCode,
                $cardinalFragments,
                $ordinalFragments,
            );
        }

        return $result;
    }

    /**
     * Read and decode a JSON file from disk.
     *
     * @param string $filePath Path to the JSON file.
     * @return array<string, mixed>
     * @throws RuntimeException If the file cannot be read or decoded.
     */
    private function readJsonFile(string $filePath): array
    {
        $json = file_get_contents($filePath);

        // @codeCoverageIgnoreStart
        if ($json === false) {
            throw new RuntimeException("Failed to read JSON file: $filePath");
        }
        // @codeCoverageIgnoreEnd

        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            // @codeCoverageIgnoreStart
        } catch (JsonException $e) {
            throw new RuntimeException("Failed to decode JSON file: $filePath", 0, $e);
            // @codeCoverageIgnoreEnd
        }

        return is_array($data) ? $data : [];
    }

    /**
     * Write the plural rules to a JSON file.
     *
     * @param string|null $filePath Path to write the JSON file.
     *                              If null, writes to the default location in the Locales directory.
     */
    private function writeToFile(?string $filePath = null): void
    {
        $filePath ??= self::DEFAULT_FILE_PATH;

        $json = json_encode($this->rules, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        file_put_contents($filePath, $json . "\n");
    }
}


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
     * When present, these values replace the defaults during build().
     */
    private const string OVERRIDES_FILE_PATH = __DIR__ . '/pluralRulesOverrides.json';

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
        0 => [
            [
                'rule' => '',
                'human_rule' => 'Any number',
                'example' => '0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 100, 1000, …'
            ],
        ],
        // Rule 1: nplurals=2; plural=(n != 1); (Germanic, most European)
        1 => [
            ['rule' => 'i = 1 and v = 0', 'human_rule' => 'Exactly 1 (no decimals)', 'example' => '1'],
            ['rule' => '', 'human_rule' => 'Any other number', 'example' => '0, 2~16, 100, 1000, …'],
        ],
        // Rule 2: nplurals=2; (Amharic, Persian, Hindi, etc. — CLDR: i = 0 or n = 1; integer approximation: n > 1)
        2 => [
            ['rule' => 'i = 0 or n = 1', 'human_rule' => '0 or 1', 'example' => '0, 1'],
            ['rule' => '', 'human_rule' => 'Any other number', 'example' => '2~17, 100, 1000, …'],
        ],
        // Rule 3: nplurals=4; Slavic (Russian, Ukrainian, Belarusian, Serbian, Croatian)
        3 => [
            [
                'rule' => 'n % 10 = 1 and n % 100 != 11',
                'human_rule' => 'Ends in 1 (except 11)',
                'example' => '1, 21, 31, 41, 51, 61, 71, 81, 101, …'
            ],
            [
                'rule' => 'n % 10 = 2–4 and n % 100 != 12–14',
                'human_rule' => 'Ends in 2-4 (except 12–14)',
                'example' => '2~4, 22~24, 32~34, …'
            ],
            [
                'rule' => 'n % 10 = 0 or n % 10 = 5–9 or n % 100 = 11–14',
                'human_rule' => 'Ends in 0, 5–9, or 11–14',
                'example' => '0, 5~20, 25~30, 35~40, …'
            ],
            ['rule' => '', 'human_rule' => 'Any other number', 'example' => ''],
        ],
        // Rule 4: nplurals=3; (Czech, Slovak)
        4 => [
            ['rule' => 'i = 1 and v = 0', 'human_rule' => 'Exactly 1 (no decimals)', 'example' => '1'],
            ['rule' => 'i = 2–4 and v = 0', 'human_rule' => 'Exactly 2, 3, or 4 (no decimals)', 'example' => '2~4'],
            ['rule' => '', 'human_rule' => 'Any other number', 'example' => '0, 5~20, 100, 1000, …'],
        ],
        // Rule 5: nplurals=5; (Irish)
        5 => [
            ['rule' => 'n = 1', 'human_rule' => 'Exactly 1', 'example' => '1'],
            ['rule' => 'n = 2', 'human_rule' => 'Exactly 2', 'example' => '2'],
            ['rule' => 'n = 3–6', 'human_rule' => 'Exactly 3, 4, 5, or 6', 'example' => '3~6'],
            ['rule' => 'n = 7–10', 'human_rule' => 'Exactly 7, 8, 9, or 10', 'example' => '7~10'],
            ['rule' => '', 'human_rule' => 'Any other number', 'example' => '0, 11~25, 100, 1000, …'],
        ],
        // Rule 6: nplurals=3; (Lithuanian)
        6 => [
            [
                'rule' => 'n % 10 = 1 and n % 100 != 11',
                'human_rule' => 'Ends in 1 (except 11)',
                'example' => '1, 21, 31, 41, 51, 61, 71, 81, 101, …'
            ],
            [
                'rule' => 'n % 10 = 2–9 and n % 100 != 12–19',
                'human_rule' => 'Ends in 2–9 (except 12–19)',
                'example' => '2~9, 22~29, 32~39, …'
            ],
            ['rule' => '', 'human_rule' => 'Any other number', 'example' => '0, 10~20, 30, 40, 50, 60, 100, …'],
        ],
        // Rule 7: nplurals=4; (Slovenian)
        7 => [
            [
                'rule' => 'v = 0 and i % 100 = 1',
                'human_rule' => 'Ends in 01 (including the decimal part)',
                'example' => '1, 101, 201, …'
            ],
            [
                'rule' => 'v = 0 and i % 100 = 2',
                'human_rule' => 'Ends in 02 (including after the decimal point)',
                'example' => '2, 102, 202, …'
            ],
            [
                'rule' => 'v = 0 and i % 100 = 3–4',
                'human_rule' => 'Ends in 03 or 04 (including after the decimal point)',
                'example' => '3, 4, 103, 104, 203, 204, …'
            ],
            ['rule' => '', 'human_rule' => 'Any other number', 'example' => '0, 5~19, 100, 105~119, …'],
        ],
        // Rule 8: nplurals=2; (Macedonian)
        8 => [
            [
                'rule' => 'n % 10 = 1 and n % 100 != 11',
                'human_rule' => 'Ends in 1 (except 11)',
                'example' => '1, 21, 31, 41, 51, …'
            ],
            ['rule' => '', 'human_rule' => 'Any other number', 'example' => '0, 2~20, 22~30, …'],
        ],
        // Rule 9: nplurals=4; (Maltese)
        9 => [
            ['rule' => 'n = 1', 'human_rule' => 'Exactly 1', 'example' => '1'],
            [
                'rule' => 'n = 0 or n % 100 = 2–10',
                'human_rule' => 'Exactly 0 or ends in 02–10',
                'example' => '0, 2~10, 102~110, …'
            ],
            ['rule' => 'n % 100 = 11–19', 'human_rule' => 'Ends in 11–19', 'example' => '11~19, 111~119, …'],
            ['rule' => '', 'human_rule' => 'Any other number', 'example' => '20~35, 100, 1000, …'],
        ],
        // Rule 10: nplurals=3; (Latvian)
        10 => [
            [
                'rule' => 'n % 10 = 0 or n % 100 = 11–19',
                'human_rule' => 'Ends in 0 or 11–19',
                'example' => '0, 10~20, 30, 40, …'
            ],
            [
                'rule' => 'n % 10 = 1 and n % 100 != 11',
                'human_rule' => 'Ends in 1 (except 11)',
                'example' => '1, 21, 31, 41, …'
            ],
            ['rule' => '', 'human_rule' => 'Any other number', 'example' => '2~9, 22~29, 32~39, …'],
        ],
        // Rule 11: nplurals=3; (Polish)
        11 => [
            ['rule' => 'i = 1 and v = 0', 'human_rule' => 'Exactly 1 (no decimals)', 'example' => '1'],
            [
                'rule' => 'v = 0 and i % 10 = 2–4 and i % 100 != 12–14',
                'human_rule' => 'Ends in 2–4 (except 12–14, no decimals)',
                'example' => '2~4, 22~24, 32~34, …'
            ],
            [
                'rule' => 'v = 0 and i != 1 and i % 10 = 0–1 or v = 0 and i % 10 = 5–9 or v = 0 and i % 100 = 12–14',
                'human_rule' => 'Ends in 0, 1, 5–9, or 12–14 (except 1, no decimals)',
                'example' => '0, 5~19, 100, …'
            ],
            ['rule' => '', 'human_rule' => 'Any other number', 'example' => '0.0~1.5, 10.0, …'],
        ],
        // Rule 12: nplurals=3; (Romanian)
        12 => [
            ['rule' => 'i = 1 and v = 0', 'human_rule' => 'Exactly 1 (no decimals)', 'example' => '1'],
            [
                'rule' => 'v != 0 or n = 0 or n % 100 = 2–19',
                'human_rule' => 'Exactly 0 or ends in 02–19',
                'example' => '0, 2~19, 102~119, …'
            ],
            ['rule' => '', 'human_rule' => 'Any other number', 'example' => '20~35, 100, 1000, …'],
        ],
        // Rule 13: nplurals=6; (Arabic)
        13 => [
            ['rule' => 'n = 0', 'human_rule' => 'Exactly 0', 'example' => '0'],
            ['rule' => 'n = 1', 'human_rule' => 'Exactly 1', 'example' => '1'],
            ['rule' => 'n = 2', 'human_rule' => 'Exactly 2', 'example' => '2'],
            ['rule' => 'n % 100 = 3–10', 'human_rule' => 'Ends in 03–10', 'example' => '3~10, 103~110, …'],
            ['rule' => 'n % 100 = 11–99', 'human_rule' => 'Ends in 11-99', 'example' => '11~26, 111~126, …'],
            ['rule' => '', 'human_rule' => 'Any other number', 'example' => '100~102, 200~202, …'],
        ],
        // Rule 14: nplurals=6; (Welsh)
        14 => [
            ['rule' => 'n = 0', 'human_rule' => 'Exactly 0', 'example' => '0'],
            ['rule' => 'n = 1', 'human_rule' => 'Exactly 1', 'example' => '1'],
            ['rule' => 'n = 2', 'human_rule' => 'Exactly 2', 'example' => '2'],
            ['rule' => 'n = 3', 'human_rule' => 'Exactly 3', 'example' => '3'],
            ['rule' => 'n = 6', 'human_rule' => 'Exactly 6', 'example' => '6'],
            ['rule' => '', 'human_rule' => 'Any other number', 'example' => '4, 5, 7~20, 100, 1000, …'],
        ],
        // Rule 15: nplurals=2; (Icelandic)
        15 => [
            [
                'rule' => 'n % 10 = 1 and n % 100 != 11',
                'human_rule' => 'Ends in 1 (except 11)',
                'example' => '1, 21, 31, 41, 51, …'
            ],
            ['rule' => '', 'human_rule' => 'Any other number', 'example' => '0, 2~16, 100, 1000, …'],
        ],
        // Rule 16: nplurals=4; (Scottish Gaelic)
        16 => [
            ['rule' => 'n = 1, 11', 'human_rule' => 'Exactly 1 or 11', 'example' => '1, 11'],
            ['rule' => 'n = 2, 12', 'human_rule' => 'Exactly 2 or 12', 'example' => '2, 12'],
            ['rule' => 'n = 3–10, 13–19', 'human_rule' => 'Exactly 3–10 or 13–19', 'example' => '3~10, 13~19'],
            ['rule' => '', 'human_rule' => 'Any other number', 'example' => '0, 20~34, 100, 1000, …'],
        ],
        // Rule 17: nplurals=5; (Breton)
        17 => [
            [
                'rule' => 'n % 10 = 1 and n % 100 != 11, 71, 91',
                'human_rule' => 'Ends in 1 (except 11, 71 or 91)',
                'example' => '1, 21, 31, 41, 51, 61, 81, 101, …'
            ],
            [
                'rule' => 'n % 10 = 2 and n % 100 != 12, 72, 92',
                'human_rule' => 'Ends in 2 (except 12, 72 or 92)',
                'example' => '2, 22, 32, 42, 52, 62, 82, 102, …'
            ],
            [
                'rule' => 'n % 10 = 3–4, 9 and n % 100 != 10–19, 70–79, 90–99',
                'human_rule' => 'Ends in 3, 4, or 9 (except 10–19, 70–79, 90–99)',
                'example' => '3, 4, 9, 23, 24, 29, …'
            ],
            [
                'rule' => 'n != 0 and n % 1000000 = 0',
                'human_rule' => 'Non-zero multiple of 1,000,000',
                'example' => '1000000, 2000000, …'
            ],
            ['rule' => '', 'human_rule' => 'Any other number', 'example' => '0, 5~8, 10~20, 100, 1000, …'],
        ],
        // Rule 18: nplurals=4; (Manx)
        18 => [
            ['rule' => 'n % 10 = 1', 'human_rule' => 'Ends in 1', 'example' => '1, 11, 21, 31, …'],
            ['rule' => 'n % 10 = 2', 'human_rule' => 'Ends in 2', 'example' => '2, 12, 22, 32, …'],
            ['rule' => 'n % 20 = 0', 'human_rule' => 'Multiple of 20', 'example' => '0, 20, 40, 60, 80, 100, …'],
            ['rule' => '', 'human_rule' => 'Any other number', 'example' => '3~9, 13~19, 23~29, …'],
        ],
        // Rule 19: nplurals=4; (Hebrew)
        19 => [
            ['rule' => 'n = 1', 'human_rule' => 'Exactly 1', 'example' => '1'],
            ['rule' => 'n = 2', 'human_rule' => 'Exactly 2', 'example' => '2'],
            [
                'rule' => 'n > 10 and n % 10 = 0',
                'human_rule' => 'Multiple of 10 (above 10)',
                'example' => '20, 30, 40, 50, 60, …'
            ],
            ['rule' => '', 'human_rule' => 'Any other number', 'example' => '0, 3~9, 11~19, 21~29, …'],
        ],
        // Rule 20: nplurals=3; (Italian, Spanish, French, Portuguese, Catalan - CLDR 49)
        20 => [
            ['rule' => 'i = 1 and v = 0', 'human_rule' => 'Exactly 1 (no decimals)', 'example' => '1'],
            [
                'rule' => 'e = 0 and i != 0 and i % 1000000 = 0 and v = 0 or e != 0–5',
                'human_rule' => 'Multiple of 1,000,000 (no decimals)',
                'example' => '1000000, 2000000, …'
            ],
            ['rule' => '', 'human_rule' => 'Any other number', 'example' => '0, 2~16, 100, 1000, …'],
        ],
        // Rule 21: nplurals=3; (Inuktitut, Sami, Nama, Swampy Cree)
        21 => [
            ['rule' => 'n = 1', 'human_rule' => 'Exactly 1', 'example' => '1'],
            ['rule' => 'n = 2', 'human_rule' => 'Exactly 2', 'example' => '2'],
            ['rule' => '', 'human_rule' => 'Any other number', 'example' => '0, 3~17, 100, 1000, …'],
        ],
        // Rule 22: nplurals=3; (Colognian, Anii, Langi)
        22 => [
            ['rule' => 'n = 0', 'human_rule' => 'Exactly 0', 'example' => '0'],
            ['rule' => 'n = 1', 'human_rule' => 'Exactly 1', 'example' => '1'],
            ['rule' => '', 'human_rule' => 'Any other number', 'example' => '2~17, 100, 1000, …'],
        ],
        // Rule 23: nplurals=3; (Tachelhit)
        23 => [
            ['rule' => 'n = 0–1', 'human_rule' => 'Exactly 0 or 1', 'example' => '0, 1'],
            ['rule' => 'n = 2–10', 'human_rule' => 'Exactly 2 through 10', 'example' => '2~10'],
            ['rule' => '', 'human_rule' => 'Any other number', 'example' => '11~26, 100, 1000, …'],
        ],
        // Rule 24: nplurals=6; (Cornish)
        24 => [
            ['rule' => 'n = 0', 'human_rule' => 'Exactly 0', 'example' => '0'],
            ['rule' => 'n = 1', 'human_rule' => 'Exactly 1', 'example' => '1'],
            [
                'rule' => 'n % 100 = 2, 22, 42, 62, 82 or n % 1000 = 0 ...',
                'human_rule' => 'Ends in 02, 22, 42, 62, 82 (or special multiples of 1000)',
                'example' => '2, 22, 42, 62, 82, 102, …'
            ],
            [
                'rule' => 'n % 100 = 3, 23, 43, 63, 83',
                'human_rule' => 'Ends in 03, 23, 43, 63, 83',
                'example' => '3, 23, 43, 63, 83, 103, …'
            ],
            [
                'rule' => 'n != 1 and n % 100 = 1, 21, 41, 61, 81',
                'human_rule' => 'Ends in 01, 21, 41, 61, 81 (except 1)',
                'example' => '21, 41, 61, 81, 101, …'
            ],
            ['rule' => '', 'human_rule' => 'Any other number', 'example' => '4~20, 24~40, 44~60, …'],
        ],
        // Rule 25: nplurals=2; (Filipino, Tagalog - CLDR 49)
        // one: v = 0 and i = 1,2,3 or v = 0 and i % 10 != 4,6,9 or v != 0 and f % 10 != 4,6,9
        25 => [
            [
                'rule' => 'v = 0 and i = 1, 2, 3 or v = 0 and i % 10 != 4, 6, 9 or v != 0 and f % 10 != 4, 6, 9',
                'human_rule' => 'Does not end in 4, 6, 9 (including the part after the decimal point)',
                'example' => '0, 1, 2, 3, 5, 7, 8, 10, 11, 12, 13, 15, …'
            ],
            ['rule' => '', 'human_rule' => 'Any other number', 'example' => '4, 6, 9, 14, 16, 19, 24, …'],
        ],
        // Rule 26: nplurals=2; (Central Atlas Tamazight - CLDR 49)
        // one: n = 0..1 or n = 11..99
        26 => [
            [
                'rule' => 'n = 0–1 or n = 11–99',
                'human_rule' => 'Exactly 0–1 or 11–99',
                'example' => '0, 1, 11~99'
            ],
            ['rule' => '', 'human_rule' => 'Any other number', 'example' => '2~10, 100~110, 1000, …'],
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
        0 => [
            [
                'rule' => '',
                'human_rule' => 'Any number',
                'example' => '0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, …'
            ],
        ],
        // Rule 1: English-like ordinals (one/two/few/other)
        1 => [
            [
                'rule' => 'n % 10 = 1 and n % 100 != 11',
                'human_rule' => 'Ends in 1 (except 11)',
                'example' => '1, 21, 31, 41, 51, …'
            ],
            [
                'rule' => 'n % 10 = 2 and n % 100 != 12',
                'human_rule' => 'Ends in 2 (except 12)',
                'example' => '2, 22, 32, 42, 52, …'
            ],
            [
                'rule' => 'n % 10 = 3 and n % 100 != 13',
                'human_rule' => 'Ends in 3 (except 13)',
                'example' => '3, 23, 33, 43, 53, …'
            ],
            ['rule' => '', 'human_rule' => 'Any other number', 'example' => '0, 4~18, 100, 1000, …'],
        ],
        // Rule 2: French-like ordinals (one/other)
        2 => [
            ['rule' => 'n = 1', 'human_rule' => 'Exactly 1', 'example' => '1'],
            ['rule' => '', 'human_rule' => 'Any other number', 'example' => '0, 2~16, 100, 1000, …'],
        ],
        // Rule 3: Only "other" (Slavic)
        3 => [
            [
                'rule' => '',
                'human_rule' => 'Any number',
                'example' => '0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, …'
            ],
        ],
        // Rule 4: Only "other" (Czech/Slovak)
        4 => [
            [
                'rule' => '',
                'human_rule' => 'Any number',
                'example' => '0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, …'
            ],
        ],
        // Rule 5: Irish ordinals (one/other)
        5 => [
            ['rule' => 'n = 1', 'human_rule' => 'Exactly 1', 'example' => '1'],
            ['rule' => '', 'human_rule' => 'Any other number', 'example' => '0, 2~16, 100, 1000, …'],
        ],
        // Rule 6: Only "other" (Lithuanian)
        6 => [
            [
                'rule' => '',
                'human_rule' => 'Any number',
                'example' => '0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, …'
            ],
        ],
        // Rule 7: Only "other" (Slovenian)
        7 => [
            [
                'rule' => '',
                'human_rule' => 'Any number',
                'example' => '0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, …'
            ],
        ],
        // Rule 8: Macedonian ordinals (one/two/many/other)
        8 => [
            [
                'rule' => 'n % 10 = 1 and n % 100 != 11',
                'human_rule' => 'Ends in 1 (except 11)',
                'example' => '1, 21, 31, 41, 51, …'
            ],
            [
                'rule' => 'n % 10 = 2 and n % 100 != 12',
                'human_rule' => 'Ends in 2 (except 12)',
                'example' => '2, 22, 32, 42, 52, …'
            ],
            [
                'rule' => 'n % 10 = 7, 8 and n % 100 != 17, 18',
                'human_rule' => 'Ends in 7 or 8 (except 17, 18)',
                'example' => '7, 8, 27, 28, 37, 38, …'
            ],
            ['rule' => '', 'human_rule' => 'Any other number', 'example' => '0, 3~6, 9~19, 23~26, …'],
        ],
        // Rule 9: Only "other" (Maltese)
        9 => [
            [
                'rule' => '',
                'human_rule' => 'Any number',
                'example' => '0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, …'
            ],
        ],
        // Rule 10: Only "other" (Latvian)
        10 => [
            [
                'rule' => '',
                'human_rule' => 'Any number',
                'example' => '0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, …'
            ],
        ],
        // Rule 11: Only "other" (Polish)
        11 => [
            [
                'rule' => '',
                'human_rule' => 'Any number',
                'example' => '0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, …'
            ],
        ],
        // Rule 12: Romanian ordinals (one/other)
        12 => [
            ['rule' => 'n = 1', 'human_rule' => 'Exactly 1', 'example' => '1'],
            ['rule' => '', 'human_rule' => 'Any other number', 'example' => '0, 2~16, 100, 1000, …'],
        ],
        // Rule 13: Only "other" (Arabic)
        13 => [
            [
                'rule' => '',
                'human_rule' => 'Any number',
                'example' => '0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, …'
            ],
        ],
        // Rule 14: Welsh ordinals (zero/one/two/few/many/other)
        14 => [
            ['rule' => 'n = 0, 7, 8, 9', 'human_rule' => 'Exactly 0, 7, 8 ,9', 'example' => '0, 7, 8, 9'],
            ['rule' => 'n = 1', 'human_rule' => 'Exactly 1', 'example' => '1'],
            ['rule' => 'n = 2', 'human_rule' => 'Exactly 2', 'example' => '2'],
            ['rule' => 'n = 3, 4', 'human_rule' => 'Exactly 3 or 4', 'example' => '3, 4'],
            ['rule' => 'n = 5, 6', 'human_rule' => 'Exactly 5 or 6', 'example' => '5, 6'],
            ['rule' => '', 'human_rule' => 'Any other number', 'example' => '10~25, 100, 1000, …'],
        ],
        // Rule 15: Only "other" (Icelandic)
        15 => [
            [
                'rule' => '',
                'human_rule' => 'Any number',
                'example' => '0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, …'
            ],
        ],
        // Rule 16: Scottish Gaelic ordinals (one/two/few/other)
        16 => [
            ['rule' => 'n = 1, 11', 'human_rule' => 'Exactly 1 or 11', 'example' => '1, 11'],
            ['rule' => 'n = 2, 12', 'human_rule' => 'Exactly 2 or 12', 'example' => '2, 12'],
            ['rule' => 'n = 3, 13', 'human_rule' => 'Exactly 3 or 13', 'example' => '3, 13'],
            ['rule' => '', 'human_rule' => 'Any other number', 'example' => '0, 4~10, 14~21, 100, …'],
        ],
        // Rule 17: Only "other" (Breton)
        17 => [
            [
                'rule' => '',
                'human_rule' => 'Any number',
                'example' => '0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, …'
            ],
        ],
        // Rule 18: Only "other" (Manx)
        18 => [
            [
                'rule' => '',
                'human_rule' => 'Any number',
                'example' => '0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, …'
            ],
        ],
        // Rule 19: Only "other" (Hebrew)
        19 => [
            [
                'rule' => '',
                'human_rule' => 'Any number',
                'example' => '0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, …'
            ],
        ],
        // Rule 20: Italian ordinals (many/other)
        20 => [
            ['rule' => 'n = 8, 11, 80, 800', 'human_rule' => 'Exactly 8, 11, 80 or 800', 'example' => '8, 11, 80, 800'],
            ['rule' => '', 'human_rule' => 'Any other number', 'example' => '0~7, 9, 10, 12~79, 81~99, 100, …'],
        ],
        // Rule 21: Kazakh, Azerbaijani ordinals (many/other)
        21 => [
            [
                'rule' => 'n % 10 = 6,9 or n % 10 = 0 and n != 0',
                'human_rule' => 'Ends in 0 (except 0 itself), 6 or 9',
                'example' => '6, 9, 10, 16, 19, 20, 26, 29, 30, …'
            ],
            ['rule' => '', 'human_rule' => 'Any other number', 'example' => '0~5, 7, 8, 11~15, 17, 18, 21, …'],
        ],
        // Rule 22: Hungarian ordinals (few/other)
        22 => [
            ['rule' => 'n = 1, 5', 'human_rule' => 'Exactly 1 or 5', 'example' => '1, 5'],
            ['rule' => '', 'human_rule' => 'Any other number', 'example' => '0, 2~4, 6~17, 100, 1000, …'],
        ],
        // Rule 23: Bengali, Assamese, Hindi ordinals (one/other)
        23 => [
            [
                'rule' => 'n = 1, 5, 7, 8, 9, 10',
                'human_rule' => 'Exactly 1, 5, 7, 8, 9 or 10',
                'example' => '1, 5, 7, 8, 9, 10'
            ],
            ['rule' => '', 'human_rule' => 'Any other number', 'example' => '0, 2~4, 6, 11~25, 100, 1000, …'],
        ],
        // Rule 24: Gujarati ordinals (one/two/few/many/other)
        24 => [
            ['rule' => 'n = 1', 'human_rule' => 'Exactly 1', 'example' => '1'],
            ['rule' => 'n = 2, 3', 'human_rule' => 'Exactly 2 or 3', 'example' => '2, 3'],
            ['rule' => 'n = 4', 'human_rule' => 'Exactly 4', 'example' => '4'],
            ['rule' => 'n = 6', 'human_rule' => 'Exactly 6', 'example' => '6'],
            ['rule' => '', 'human_rule' => 'Any other number', 'example' => '0, 5, 7~20, 100, 1000, …'],
        ],
        // Rule 25: Kannada ordinals (one/two/few/other)
        25 => [
            ['rule' => 'n = 1', 'human_rule' => 'Exactly 1', 'example' => '1'],
            ['rule' => 'n = 2, 3', 'human_rule' => 'Exactly 2 or 3', 'example' => '2, 3'],
            ['rule' => 'n = 4', 'human_rule' => 'Exactly 4', 'example' => '4'],
            ['rule' => '', 'human_rule' => 'Any other number', 'example' => '0, 5~20, 100, 1000, …'],
        ],
        // Rule 26: Marathi ordinals (one/other)
        26 => [
            ['rule' => 'n = 1', 'human_rule' => 'Exactly 1', 'example' => '1'],
            ['rule' => '', 'human_rule' => 'Any other number', 'example' => '0, 2~16, 100, 1000, …'],
        ],
        // Rule 27: Odia ordinals (one/two/few/many/other)
        27 => [
            ['rule' => 'n = 1, 5, 7–9', 'human_rule' => 'Exactly 1, 5, 7, 8, or 9', 'example' => '1, 5, 7, 8, 9'],
            ['rule' => 'n = 2, 3', 'human_rule' => 'Exactly 2 or 3', 'example' => '2, 3'],
            ['rule' => 'n = 4', 'human_rule' => 'Exactly 4', 'example' => '4'],
            ['rule' => 'n = 6', 'human_rule' => 'Exactly 6', 'example' => '6'],
            ['rule' => '', 'human_rule' => 'Any other number', 'example' => '0, 10~25, 100, 1000, …'],
        ],
        // Rule 28: Telugu ordinals (one/two/many/other)
        28 => [
            ['rule' => 'n = 1', 'human_rule' => 'Exactly 1', 'example' => '1'],
            ['rule' => 'n = 2, 3', 'human_rule' => 'Exactly 2 or 3', 'example' => '2, 3'],
            ['rule' => 'n = 4', 'human_rule' => 'Exactly 4', 'example' => '4'],
            ['rule' => '', 'human_rule' => 'Any other number', 'example' => '0, 5~20, 100, 1000, …'],
        ],
        // Rule 29: Nepali ordinals (one/few/other)
        29 => [
            ['rule' => 'n = 1–4', 'human_rule' => 'Exactly 1, 2, 3 or 4', 'example' => '1~4'],
            ['rule' => 'n = 5, 6', 'human_rule' => 'Exactly 5 or 6', 'example' => '5, 6'],
            ['rule' => '', 'human_rule' => 'Any other number', 'example' => '0, 7~20, 100, 1000, …'],
        ],
        // Rule 30: Albanian ordinals (one/many/other)
        30 => [
            ['rule' => 'n = 1', 'human_rule' => 'Exactly 1', 'example' => '1'],
            ['rule' => 'n = 4', 'human_rule' => 'Exactly 4', 'example' => '4'],
            ['rule' => '', 'human_rule' => 'Any other number', 'example' => '0, 2, 3, 5~16, 100, 1000, …'],
        ],
        // Rule 31: Anii ordinals (zero/one/few/other)
        31 => [
            ['rule' => 'i = 0', 'human_rule' => 'Exactly 0', 'example' => '0'],
            ['rule' => 'i = 1', 'human_rule' => 'Exactly 1', 'example' => '1'],
            ['rule' => 'i = 2, 3, 4, 5, 6', 'human_rule' => 'Exactly 2, 3, 4, 5 or 6', 'example' => '2~6'],
            ['rule' => '', 'human_rule' => 'Any other number', 'example' => '7~20, 100, 1000, …'],
        ],
        // Rule 32: Cornish ordinals (one/many/other)
        32 => [
            [
                'rule' => 'n = 1–4 or n % 100 = 1–4, 21–24, 41–44, 61–64, 81–84',
                'human_rule' => 'Exactly 1–4 or ends in 01–04, 21–24, 41–44, 61–64, 81–84',
                'example' => '1~4, 21~24, 41~44, 61~64, 81~84, …'
            ],
            [
                'rule' => 'n = 5 or n % 100 = 5',
                'human_rule' => 'Exactly 5 or ends in 05',
                'example' => '5, 105, 205, …'
            ],
            ['rule' => '', 'human_rule' => 'Any other number', 'example' => '0, 6~20, 25~40, 45~60, …'],
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

            // Skip duplicates (same iso code already processed)
            if (isset($result[$isoCode])) {
                continue;
            }

            $name = $langInfo['name'];

            $cardinalFragments = $this->buildCategoryFragments(
                PluralRules::getCardinalCategories($isoCode),
                self::CARDINAL_HUMAN_RULES[PluralRules::getRuleGroup($isoCode)] ?? [],
                $overrides[$isoCode]['cardinal'] ?? []
            );

            $ordinalFragments = $this->buildCategoryFragments(
                PluralRules::getOrdinalCategories($isoCode),
                self::ORDINAL_HUMAN_RULES[PluralRules::getRuleGroup($isoCode, 'ordinal')] ?? [],
                $overrides[$isoCode]['ordinal'] ?? []
            );

            $result[$isoCode] = new LanguageRulesFragment($name, $isoCode, $cardinalFragments, $ordinalFragments);
        }

        ksort($result);

        return $result;
    }

    /**
     * Build CategoryFragment objects by zipping category names from PluralRules
     * with the positional human rule data, applying per-language overrides.
     *
     * @param array<int, string> $categories Category names from PluralRules (e.g. ['one', 'other']).
     * @param array<int, array{rule: string, human_rule: string, example: string}> $humanRules Positional human rule data.
     * @param array<int, array{human_rule?: string, example?: string}> $overrides Per-language overrides (sparse, positional).
     * @return CategoryFragment[]
     */
    private function buildCategoryFragments(array $categories, array $humanRules, array $overrides = []): array
    {
        $fragments = [];

        foreach ($categories as $index => $category) {
            $ruleData = $humanRules[$index] ?? [
                'rule' => '',
                'human_rule' => 'Any other number',
                'example' => '',
            ];

            // Apply per-language overrides if present
            $humanRule = $overrides[$index]['human_rule'] ?? $ruleData['human_rule'];
            $example   = $overrides[$index]['example']    ?? $ruleData['example'];

            $fragments[] = new CategoryFragment(
                category: $category,
                rule: $ruleData['rule'],
                human_rule: $humanRule,
                example: $example,
            );
        }

        return $fragments;
    }

    /**
     * Load per-language overrides for human_rule and example.
     *
     * Returns a sparse array keyed by ISO code, then 'cardinal'/'ordinal',
     * then positional index, containing only the fields that differ from defaults.
     *
     * @return array<string, array{cardinal?: array<int, array{human_rule?: string, example?: string}>, ordinal?: array<int, array{human_rule?: string, example?: string}>}>
     */
    private function loadOverrides(): array
    {
        if (!file_exists(self::OVERRIDES_FILE_PATH)) {
            return [];
        }

        $json = file_get_contents(self::OVERRIDES_FILE_PATH);

        // @codeCoverageIgnoreStart
        if ($json === false) {
            return [];
        }
        // @codeCoverageIgnoreEnd

        $data = json_decode($json, true);

        return is_array($data) ? $data : [];
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
        $json = file_get_contents($filePath);

        // @codeCoverageIgnoreStart
        if ($json === false) {
            throw new RuntimeException("Failed to read plural rules cache file: $filePath");
        }
        // @codeCoverageIgnoreEnd

        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            // @codeCoverageIgnoreStart
            throw new RuntimeException("Failed to decode plural rules cache file: $filePath", 0, $e);
            // @codeCoverageIgnoreEnd
        }

        $result = [];

        foreach ($data as $isoCode => $langData) {
            $cardinalFragments = [];
            foreach ($langData['cardinal'] ?? [] as $cat) {
                $cardinalFragments[] = new CategoryFragment(
                    category: $cat['category'],
                    rule: $cat['rule'],
                    human_rule: $cat['human_rule'],
                    example: $cat['example'],
                );
            }

            $ordinalFragments = [];
            foreach ($langData['ordinal'] ?? [] as $cat) {
                $ordinalFragments[] = new CategoryFragment(
                    category: $cat['category'],
                    rule: $cat['rule'],
                    human_rule: $cat['human_rule'],
                    example: $cat['example'],
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


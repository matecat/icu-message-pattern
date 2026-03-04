<?php

declare(strict_types=1);

namespace Matecat\ICU\Plurals;


/**
 * Utility class to calculate which plural form index to use for a given number in a specific locale.
 *
 * This class is used when processing ICU MessageFormat plural patterns. Given a locale and a number,
 * it returns the index (0, 1, 2, etc.) of the plural form that should be used.
 *
 * ## Purpose
 *
 * Different languages have different plural rules. For example,
 * - English has 2 forms: "1 item" (singular) vs. "2 items" (plural)
 * - Russian has 3 forms: "1 яблоко", "2 яблока", "5 яблок"
 * - Arabic has 6 forms: zero, one, two, few, many, other
 *
 * This class determines which form index to use based on the count value.
 *
 * ## Usage Example
 *
 * ```php
 * use Matecat\ICU\PluralRules\PluralRules;
 *
 * // English: "1 item" vs. "2 items"
 * PluralRules::calculate('en', 1); // Returns 0 → use "one" form (singular)
 * PluralRules::calculate('en', 2); // Returns 1 → use "other" form (plural)
 * PluralRules::calculate('en', 0); // Returns 1 → use "other" form (plural)
 *
 * // French: "0 item", "1 item" vs. "2 items" (0 and 1 are singular)
 * PluralRules::calculate('fr', 0); // Returns 0 → use "one" form
 * PluralRules::calculate('fr', 1); // Returns 0 → use "one" form
 * PluralRules::calculate('fr', 2); // Returns 1 → use "other" form
 *
 * // Russian: "1 яблоко" (one), "2 яблока" (few), "5 яблок" (many)
 * PluralRules::calculate('ru', 1);  // Returns 0 → use "one" form
 * PluralRules::calculate('ru', 2);  // Returns 1 → use "few" form
 * PluralRules::calculate('ru', 5);  // Returns 2 → use "many" form
 * PluralRules::calculate('ru', 21); // Returns 0 → use "one" form (21, 31, 41...)
 *
 * // Arabic: has 6 different plural forms
 * PluralRules::calculate('ar', 0);  // Returns 0 → "zero"
 * PluralRules::calculate('ar', 1);  // Returns 1 → "one"
 * PluralRules::calculate('ar', 2);  // Returns 2 → "two"
 * PluralRules::calculate('ar', 5);  // Returns 3 → "few" (3-10)
 * PluralRules::calculate('ar', 11); // Returns 4 → "many" (11-99)
 * PluralRules::calculate('ar', 100);// Returns 5 → "other"
 * ```
 *
 * ## Plural Form Index Mapping
 *
 * The returned index corresponds to CLDR plural categories in this order:
 * - 0: one (singular)
 * - 1: other (or "few" for languages with 3+ forms)
 * - 2: many (for languages with 3+ forms)
 * - 3+: additional forms for complex languages (Arabic, Welsh, etc.)
 *
 * ## Note on nplurals
 *
 * To get the total number of plural forms for a language (nplurals), use the `nplurals` field
 * in the supported_langs.json file instead of this class.
 *
 * @see https://docs.translatehouse.org/projects/localization-guide/en/latest/l10n/pluralforms.html
 * @see https://github.com/cakephp/i18n/blob/master/PluralRules.php
 * @see https://unicode-org.github.io/cldr-staging/charts/latest/supplemental/language_plural_rules.html
 */
class PluralRules
{
    /**
     * CLDR plural category names
     */
    public const string CATEGORY_ZERO = 'zero';
    public const string CATEGORY_ONE = 'one';
    public const string CATEGORY_TWO = 'two';
    public const string CATEGORY_FEW = 'few';
    public const string CATEGORY_MANY = 'many';
    public const string CATEGORY_OTHER = 'other';

    /**
     * All valid CLDR plural category names.
     */
    public const array VALID_CATEGORIES = [
        self::CATEGORY_ZERO,
        self::CATEGORY_ONE,
        self::CATEGORY_TWO,
        self::CATEGORY_FEW,
        self::CATEGORY_MANY,
        self::CATEGORY_OTHER,
    ];

    /**
     * Checks if a selector is a valid CLDR category name.
     *
     * @param string $selector The selector to check.
     * @return bool True if it's a valid CLDR category, false otherwise.
     */
    public static function isValidCategory(string $selector): bool
    {
        return in_array($selector, self::VALID_CATEGORIES, true);
    }

    /**
     * Common category arrays shared by multiple rules.
     * Using constants to avoid duplication in the categoryMap.
     */
    private const array CATEGORIES_OTHER = [self::CATEGORY_OTHER];
    private const array CATEGORIES_ONE_OTHER = [self::CATEGORY_ONE, self::CATEGORY_OTHER];
    private const array CATEGORIES_ONE_FEW_OTHER = [self::CATEGORY_ONE, self::CATEGORY_FEW, self::CATEGORY_OTHER];
    private const array CATEGORIES_ONE_MANY_OTHER = [self::CATEGORY_ONE, self::CATEGORY_MANY, self::CATEGORY_OTHER];
    private const array CATEGORIES_ONE_TWO_FEW_OTHER = [
        self::CATEGORY_ONE,
        self::CATEGORY_TWO,
        self::CATEGORY_FEW,
        self::CATEGORY_OTHER
    ];
    private const array CATEGORIES_ONE_TWO_MANY_OTHER = [
        self::CATEGORY_ONE,
        self::CATEGORY_TWO,
        self::CATEGORY_MANY,
        self::CATEGORY_OTHER
    ];
    private const array CATEGORIES_ONE_FEW_MANY_OTHER = [
        self::CATEGORY_ONE,
        self::CATEGORY_FEW,
        self::CATEGORY_MANY,
        self::CATEGORY_OTHER
    ];
    private const array CATEGORIES_ONE_TWO_OTHER = [self::CATEGORY_ONE, self::CATEGORY_TWO, self::CATEGORY_OTHER];
    private const array CATEGORIES_ONE_TWO_FEW_MANY_OTHER = [
        self::CATEGORY_ONE,
        self::CATEGORY_TWO,
        self::CATEGORY_FEW,
        self::CATEGORY_MANY,
        self::CATEGORY_OTHER
    ];
    private const array CATEGORIES_ZERO_ONE_OTHER = [self::CATEGORY_ZERO, self::CATEGORY_ONE, self::CATEGORY_OTHER];
    private const array CATEGORIES_ZERO_ONE_TWO_FEW_MANY_OTHER = [
        self::CATEGORY_ZERO,
        self::CATEGORY_ONE,
        self::CATEGORY_TWO,
        self::CATEGORY_FEW,
        self::CATEGORY_MANY,
        self::CATEGORY_OTHER
    ];

    /**
     * Mapping of the plural rule group => array of category names indexed by plural form number.
     *
     * Each rule group returns indices 0, 1, 2, etc. from calculate().
     * This map translates those indices to CLDR category names.
     *
     * Rules with identical category arrays share the same constant to reduce memory usage.
     * The rule number determines the calculation logic, not the category names.
     *
     * @var array<int, array<int, string>>
     */
    private static array $cardinalCategoryMap = [
        // nplurals=1; only "other" (Asian languages, no plural forms)
        0  => self::CATEGORIES_OTHER,

        // nplurals=2; one/other (Germanic n!=1; French n>1; Macedonian; Icelandic; Filipino; Tamazight)
        1  => self::CATEGORIES_ONE_OTHER,
        2  => self::CATEGORIES_ONE_OTHER,
        8  => self::CATEGORIES_ONE_OTHER,
        15 => self::CATEGORIES_ONE_OTHER,
        25 => self::CATEGORIES_ONE_OTHER,
        26 => self::CATEGORIES_ONE_OTHER,

        // nplurals=4; one/few/many/other (Czech/Slovak — CLDR 49: "many" for decimals only)
        4  => self::CATEGORIES_ONE_FEW_MANY_OTHER,

        // nplurals=4; one/few/many/other (Lithuanian — CLDR 49: "many" for decimals only)
        6  => self::CATEGORIES_ONE_FEW_MANY_OTHER,

        // nplurals=3; one/few/other (Romanian; Moldavian; Tachelhit)
        12 => self::CATEGORIES_ONE_FEW_OTHER,
        23 => self::CATEGORIES_ONE_FEW_OTHER,

        // nplurals=3; one/many/other (Italian, Spanish, French, Portuguese, Catalan - CLDR 49)
        20 => self::CATEGORIES_ONE_MANY_OTHER,

        // nplurals=3; one/two/other (Inuktitut, Sami, Nama, Swampy Cree)
        21 => self::CATEGORIES_ONE_TWO_OTHER,

        // nplurals=3; zero/one/other (Latvian; Colognian, Anii, Langi)
        10 => self::CATEGORIES_ZERO_ONE_OTHER,
        22 => self::CATEGORIES_ZERO_ONE_OTHER,

        // nplurals=4; one/few/many/other (Slavic; Polish)
        3  => self::CATEGORIES_ONE_FEW_MANY_OTHER,
        11 => self::CATEGORIES_ONE_FEW_MANY_OTHER,

        // nplurals=4; one/two/few/other (Slovenian; Scottish Gaelic)
        7  => self::CATEGORIES_ONE_TWO_FEW_OTHER,
        16 => self::CATEGORIES_ONE_TWO_FEW_OTHER,

        // nplurals=5; one/two/few/many/other (Manx — CLDR 49: "many" for decimals only)
        18 => self::CATEGORIES_ONE_TWO_FEW_MANY_OTHER,

        // nplurals=3; one/two/other (Hebrew — CLDR 49: removed "many")
        19 => self::CATEGORIES_ONE_TWO_OTHER,

        // nplurals=5; one/two/few/many/other (Irish; Breton)
        5  => self::CATEGORIES_ONE_TWO_FEW_MANY_OTHER,
        17 => self::CATEGORIES_ONE_TWO_FEW_MANY_OTHER,

        // nplurals=3; one/few/other (Slavic: Bosnian, Croatian, Serbian — CLDR 49)
        27 => self::CATEGORIES_ONE_FEW_OTHER,

        // nplurals=5; one/two/few/many/other (Maltese — CLDR 49)
        28 => self::CATEGORIES_ONE_TWO_FEW_MANY_OTHER,

        // nplurals=6; zero/one/two/few/many/other (Arabic; Welsh; Cornish)
        13 => self::CATEGORIES_ZERO_ONE_TWO_FEW_MANY_OTHER,
        14 => self::CATEGORIES_ZERO_ONE_TWO_FEW_MANY_OTHER,
        24 => self::CATEGORIES_ZERO_ONE_TWO_FEW_MANY_OTHER,
    ];

    /**
     * Additional category arrays for ordinal rules not covered by cardinal constants.
     */
    private const array CATEGORIES_MANY_OTHER = [self::CATEGORY_MANY, self::CATEGORY_OTHER];
    private const array CATEGORIES_FEW_OTHER = [self::CATEGORY_FEW, self::CATEGORY_OTHER];
    private const array CATEGORIES_ZERO_ONE_FEW_OTHER = [
        self::CATEGORY_ZERO,
        self::CATEGORY_ONE,
        self::CATEGORY_FEW,
        self::CATEGORY_OTHER,
    ];

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
    private static array $ordinalCategoryMap = [
        // Only "other" (no ordinal distinction)
        // Slavic, Czech/Slovak, Lithuanian, Slovenian, Latvian, Polish,
        // Arabic, Icelandic, Breton, Manx, Hebrew, and many others
        0  => self::CATEGORIES_OTHER,
        3  => self::CATEGORIES_OTHER,
        4  => self::CATEGORIES_OTHER,
        6  => self::CATEGORIES_OTHER,
        7  => self::CATEGORIES_OTHER,
        10 => self::CATEGORIES_OTHER,
        11 => self::CATEGORIES_OTHER,
        13 => self::CATEGORIES_OTHER,
        15 => self::CATEGORIES_OTHER,
        17 => self::CATEGORIES_OTHER,
        18 => self::CATEGORIES_OTHER,
        19 => self::CATEGORIES_OTHER,

        // one/other (French-like; Irish; Romanian; Moldavian)
        2  => self::CATEGORIES_ONE_OTHER,
        5  => self::CATEGORIES_ONE_OTHER,
        12 => self::CATEGORIES_ONE_OTHER,

        // one/two/few/other (English-like 1st/2nd/3rd/4th; Scottish Gaelic)
        1  => self::CATEGORIES_ONE_TWO_FEW_OTHER,
        16 => self::CATEGORIES_ONE_TWO_FEW_OTHER,

        // one/two/many/other (Macedonian)
        8  => self::CATEGORIES_ONE_TWO_MANY_OTHER,

        // one/two/few/many/other (Bengali/Assamese/Hindi; Gujarati; Odia)
        23 => self::CATEGORIES_ONE_TWO_FEW_MANY_OTHER,
        24 => self::CATEGORIES_ONE_TWO_FEW_MANY_OTHER,
        27 => self::CATEGORIES_ONE_TWO_FEW_MANY_OTHER,

        // one/two/few/other (Marathi/Konkani)
        26 => self::CATEGORIES_ONE_TWO_FEW_OTHER,

        // many/other (Italian-like; Kazakh)
        20 => self::CATEGORIES_MANY_OTHER,
        21 => self::CATEGORIES_MANY_OTHER,

        // one/many/other (Albanian; Cornish; Georgian)
        30 => self::CATEGORIES_ONE_MANY_OTHER,
        32 => self::CATEGORIES_ONE_MANY_OTHER,
        40 => self::CATEGORIES_ONE_MANY_OTHER,

        // few/other (Ukrainian/Turkmen)
        22 => self::CATEGORIES_FEW_OTHER,

        // one/other (Nepali — CLDR 49)
        29 => self::CATEGORIES_ONE_OTHER,

        // zero/one/few/other (Anii)
        31 => self::CATEGORIES_ZERO_ONE_FEW_OTHER,

        // zero/one/two/few/many/other (Welsh)
        14 => self::CATEGORIES_ZERO_ONE_TWO_FEW_MANY_OTHER,

        // --- New ordinal groups (CLDR 49) ---

        // Rule 33: Afrikaans ordinals (few/other)
        // few: i % 100 = 2..19
        33 => self::CATEGORIES_FEW_OTHER,

        // Rule 34: Spanish ordinals (one/other)
        // one: n = 1
        34 => self::CATEGORIES_ONE_OTHER,

        // Rule 35: Hungarian ordinals (one/other)
        // one: n = 1, 5
        35 => self::CATEGORIES_ONE_OTHER,

        // Rule 36: Azerbaijani ordinals (one/few/many/other)
        36 => self::CATEGORIES_ONE_FEW_MANY_OTHER,

        // Rule 37: Belarusian ordinals (few/other)
        // few: n % 10 = 2, 3 and n % 100 != 12, 13
        37 => self::CATEGORIES_FEW_OTHER,

        // Rule 38: Bulgarian ordinals (zero/one/two/few/many/other)
        38 => self::CATEGORIES_ZERO_ONE_TWO_FEW_MANY_OTHER,

        // Rule 39: Catalan ordinals (one/two/few/other)
        39 => self::CATEGORIES_ONE_TWO_FEW_OTHER,

        // Rule 40: Georgian ordinals (one/many/other)
        // (already declared above in the one/many/other block)
    ];

    /**
     * A map of the locale => plurals group used to determine
     * which plural rules apply to the language
     *
     * Plural Rules (Cardinal):
     * 0  - nplurals=1; plural=0; (Asian, no plural forms)
     * 1  - nplurals=2; plural=(n != 1); (Germanic, most European)
     * 2  - nplurals=2; plural=(n > 1); (French, Brazilian Portuguese)
     * 3  - nplurals=4; plural=(n%10==1 && n%100!=11 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2); (Slavic: Russian, Ukrainian, Belarusian)
     * 4  - nplurals=4; plural=(n==1) ? 0 : (n>=2 && n<=4) ? 1 : 3; (Czech, Slovak — CLDR 49: "many" for decimals at index 2)
     * 5  - nplurals=5; plural=n==1 ? 0 : n==2 ? 1 : (n>2 && n<7) ? 2 :(n>6 && n<11) ? 3 : 4; (Irish)
     * 6  - nplurals=4; plural=(n%10==1 && n%100!=11 ? 0 : n%10>=2 && (n%100<10 || n%100>=20) ? 1 : 3); (Lithuanian — CLDR 49: "many" for decimals at index 2)
     * 7  - nplurals=4; plural=(n%100==1 ? 0 : n%100==2 ? 1 : n%100==3 || n%100==4 ? 2 : 3); (Slovenian)
     * 8  - nplurals=2; plural=(n%10==1 && n%100!=11) ? 0 : 1; (Macedonian - CLDR 48)
     * 10 - nplurals=3; plural=(n%10==1 && n%100!=11 ? 0 : n != 0 ? 1 : 2); (Latvian)
     * 11 - nplurals=3; plural=(n==1 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2); (Polish)
     * 12 - nplurals=3; plural=(n==1 ? 0 : n==0 || n%100>0 && n%100<20 ? 1 : 2); (Romanian; Moldavian)
     * 13 - nplurals=6; plural=(n==0 ? 0 : n==1 ? 1 : n==2 ? 2 : n%100>=3 && n%100<=10 ? 3 : n%100>=11 ? 4 : 5); (Arabic)
     * 14 - nplurals=6; plural=(n==0) ? 0 : (n==1) ? 1 : (n==2) ? 2 : (n==3) ? 3 : (n==6) ? 4 : 5; (Welsh - CLDR 48)
     * 15 - nplurals=2; plural=(n%10!=1 || n%100==11); (Icelandic)
     * 16 - nplurals=4; plural=(n==1 || n==11) ? 0 : (n==2 || n==12) ? 1 : (n>2 && n<20) ? 2 : 3; (Scottish Gaelic)
     * 17 - nplurals=5; plural=(n==1) ? 0 : (n==2) ? 1 : (n==3) ? 2 : 3; (Breton - CLDR 48)
     * 18 - nplurals=5; plural=(n%10==1) ? 0 : (n%10==2) ? 1 : (n%20==0) ? 2 : 4; (Manx — CLDR 49: "many" for decimals at index 3)
     * 19 - nplurals=3; plural=(n==1) ? 0 : (n==2) ? 1 : 2; (Hebrew — CLDR 49: removed "many")
     * 20 - nplurals=3; plural=(n==1) ? 0 : (n!=0 && n%1000000==0) ? 1 : 2; (Italian, Spanish, French, Portuguese, Catalan - CLDR 49)
     * 21 - nplurals=3; plural=(n==1) ? 0 : (n==2) ? 1 : 2; (Inuktitut, Sami, Nama)
     * 22 - nplurals=3; plural=(n==0) ? 0 : (n==1) ? 1 : 2; (Colognian, Anii, Langi)
     * 23 - nplurals=3; plural=(n<=1) ? 0 : (n>=2 && n<=10) ? 1 : 2; (Tachelhit)
     * 24 - nplurals=6; (Cornish - complex CLDR 49 rules)
     * 25 - nplurals=2; (Filipino/Tagalog - CLDR 49)
     * 26 - nplurals=2; (Central Atlas Tamazight - CLDR 49)
     * 27 - nplurals=3; (Bosnian, Croatian, Serbian — CLDR 49: one/few/other)
     * 28 - nplurals=5; (Maltese — CLDR 49: one/two/few/many/other)
     *
     * Ordinal Rules:
     * 0  - Only "other" (no ordinal distinction)
     * 1  - one/two/few/other (English-like: 1st, 2nd, 3rd, 4th)
     * 2  - one/other (French-like: 1er, 2e)
     * 3  - Only "other" (Slavic)
     * 4  - Only "other" (Czech/Slovak)
     * 5  - one/other (Irish)
     * 6  - Only "other" (Lithuanian)
     * 7  - Only "other" (Slovenian)
     * 8  - one/two/many/other (Macedonian)
     * 10 - Only "other" (Latvian)
     * 11 - Only "other" (Polish)
     * 12 - one/other (Romanian, Moldavian)
     * 13 - Only "other" (Arabic)
     * 14 - zero/one/two/few/many/other (Welsh)
     * 15 - Only "other" (Icelandic)
     * 16 - one/two/few/other (Scottish Gaelic)
     * 17 - Only "other" (Breton)
     * 18 - Only "other" (Manx)
     * 19 - Only "other" (Hebrew)
     * 20 - many/other (Italian-like ordinals)
     * 21 - many/other (Kazakh ordinals: n%10=6,9 or n%10=0 && n!=0)
     * 22 - few/other (Ukrainian, Turkmen ordinals)
     * 23 - one/two/few/many/other (Bengali, Assamese, Hindi ordinals — CLDR 49)
     * 24 - one/two/few/many/other (Gujarati ordinals)
     * 26 - one/two/few/other (Marathi, Konkani ordinals — CLDR 49)
     * 27 - one/two/few/many/other (Odia ordinals)
     * 29 - one/other (Nepali ordinals — CLDR 49: simplified)
     * 30 - one/many/other (Albanian ordinals - CLDR 49)
     * 31 - zero/one/few/other (Anii ordinals)
     * 32 - one/many/other (Cornish ordinals)
     * 33 - few/other (Afrikaans ordinals — CLDR 49)
     * 34 - one/other (Spanish ordinals — CLDR 49)
     * 35 - one/other (Hungarian ordinals — CLDR 49)
     * 36 - one/few/many/other (Azerbaijani ordinals — CLDR 49)
     * 37 - few/other (Belarusian ordinals — CLDR 49)
     * 38 - zero/one/two/few/many/other (Bulgarian ordinals — CLDR 49)
     * 39 - one/two/few/other (Catalan ordinals — CLDR 49)
     * 40 - one/many/other (Georgian ordinals — CLDR 49)
     *
     * @var array<string, array{cardinal: int, ordinal: int}>
     */
    protected static array $rulesMap = [
        // A
        'aa' => ['cardinal' => 1, 'ordinal' => 0],    // Afar
        'ace' => ['cardinal' => 0, 'ordinal' => 0],   // Acehnese - no plural
        'acf' => ['cardinal' => 2, 'ordinal' => 0],   // Saint Lucian Creole French
        'af' => ['cardinal' => 1, 'ordinal' => 33],    // Afrikaans - CLDR 49 ordinal: few/other
        'aig' => ['cardinal' => 1, 'ordinal' => 0],   // Antigua and Barbuda Creole English
        'ak' => ['cardinal' => 2, 'ordinal' => 0],    // Akan
        'als' => ['cardinal' => 1, 'ordinal' => 30],  // Albanian (Tosk) - inherits from 'sq'
        'am' => ['cardinal' => 2, 'ordinal' => 0],    // Amharic
        'an' => ['cardinal' => 1, 'ordinal' => 0],    // Aragonese
        'ar' => ['cardinal' => 13, 'ordinal' => 0],   // Arabic
        'as' => ['cardinal' => 1, 'ordinal' => 23],   // Assamese - CLDR 49 ordinal: one/two/few/many/other
        'asa' => ['cardinal' => 1, 'ordinal' => 0],   // Asu - CLDR 49
        'asm' => ['cardinal' => 1, 'ordinal' => 23],  // Assamese (alternate code) - same as 'as'
        'ast' => ['cardinal' => 1, 'ordinal' => 0],   // Asturian
        'awa' => ['cardinal' => 1, 'ordinal' => 0],   // Awadhi
        'ayr' => ['cardinal' => 0, 'ordinal' => 0],   // Central Aymara
        'az' => ['cardinal' => 1, 'ordinal' => 36],   // Azerbaijani - CLDR 49 ordinal: one/few/many/other
        'azb' => ['cardinal' => 1, 'ordinal' => 36],  // South Azerbaijani
        'azj' => ['cardinal' => 1, 'ordinal' => 36],  // North Azerbaijani

        // B
        'ba' => ['cardinal' => 0, 'ordinal' => 0],    // Bashkir
        'bah' => ['cardinal' => 1, 'ordinal' => 0],   // Bahamas Creole English
        'bal' => ['cardinal' => 1, 'ordinal' => 2],   // Baluchi - CLDR 49 ordinal: one/other
        'ban' => ['cardinal' => 0, 'ordinal' => 0],   // Balinese
        'be' => ['cardinal' => 3, 'ordinal' => 37],    // Belarusian - CLDR 49 ordinal: few/other
        'bem' => ['cardinal' => 1, 'ordinal' => 0],   // Bemba
        'bez' => ['cardinal' => 1, 'ordinal' => 0],   // Bena - CLDR 49
        'bg' => ['cardinal' => 1, 'ordinal' => 38],    // Bulgarian - CLDR 49 ordinal: zero/one/two/few/many/other
        'bh' => ['cardinal' => 2, 'ordinal' => 0],    // Bihari
        'bho' => ['cardinal' => 1, 'ordinal' => 0],   // Bhojpuri
        'bi' => ['cardinal' => 0, 'ordinal' => 0],    // Bislama
        'bjn' => ['cardinal' => 0, 'ordinal' => 0],   // Banjar
        'bjs' => ['cardinal' => 1, 'ordinal' => 0],   // Bajan
        'blo' => ['cardinal' => 22, 'ordinal' => 31], // Anii - CLDR 49
        'bm' => ['cardinal' => 0, 'ordinal' => 0],    // Bambara
        'bn' => ['cardinal' => 1, 'ordinal' => 23],   // Bengali - CLDR 49 ordinal: one/two/few/many/other
        'bo' => ['cardinal' => 0, 'ordinal' => 0],    // Tibetan
        'bod' => ['cardinal' => 0, 'ordinal' => 0],   // Tibetan (alternate code) - same as 'bo'
        'br' => ['cardinal' => 17, 'ordinal' => 0],   // Breton
        'brx' => ['cardinal' => 1, 'ordinal' => 0],   // Bodo
        'bs' => ['cardinal' => 27, 'ordinal' => 0],    // Bosnian - CLDR 49: one/few/other
        'bug' => ['cardinal' => 0, 'ordinal' => 0],   // Buginese

        // C
        'ca' => ['cardinal' => 20, 'ordinal' => 39],   // Catalan - CLDR 49 ordinal: one/two/few/other
        'cac' => ['cardinal' => 1, 'ordinal' => 0],   // Chuj
        'cav' => ['cardinal' => 20, 'ordinal' => 39],  // Catalan (Valencia) - CLDR 49
        'cb' => ['cardinal' => 1, 'ordinal' => 0],    // Cebuano (alternate code) - same as 'ceb'
        'ce' => ['cardinal' => 1, 'ordinal' => 0],    // Chechen
        'ceb' => ['cardinal' => 1, 'ordinal' => 0],   // Cebuano
        'cgg' => ['cardinal' => 1, 'ordinal' => 0],   // Chiga - CLDR 49
        'ch' => ['cardinal' => 0, 'ordinal' => 0],    // Chamorro
        'chk' => ['cardinal' => 0, 'ordinal' => 0],   // Chuukese
        'chr' => ['cardinal' => 1, 'ordinal' => 0],   // Cherokee
        'cjk' => ['cardinal' => 1, 'ordinal' => 0],   // Chokwe
        'ckb' => ['cardinal' => 1, 'ordinal' => 0],   // Central Kurdish
        'cop' => ['cardinal' => 1, 'ordinal' => 0],   // Coptic
        'crh' => ['cardinal' => 0, 'ordinal' => 0],   // Crimean Tatar
        'crs' => ['cardinal' => 2, 'ordinal' => 0],   // Seselwa Creole French
        'cs' => ['cardinal' => 4, 'ordinal' => 0],    // Czech
        'csw' => ['cardinal' => 2, 'ordinal' => 0],  // Swampy Cree - CLDR 49: one/other
        'ctg' => ['cardinal' => 1, 'ordinal' => 0],   // Chittagonian
        'cy' => ['cardinal' => 14, 'ordinal' => 14],  // Welsh - CLDR 49 ordinal: zero/one/two/few/many/other

        // D
        'da' => ['cardinal' => 1, 'ordinal' => 0],    // Danish
        'de' => ['cardinal' => 1, 'ordinal' => 0],    // German
        'dik' => ['cardinal' => 1, 'ordinal' => 0],   // Southwestern Dinka
        'diq' => ['cardinal' => 1, 'ordinal' => 0],   // Dimli
        'div' => ['cardinal' => 1, 'ordinal' => 0],   // Divehi (alternate code) - same as 'dv'
        'doi' => ['cardinal' => 1, 'ordinal' => 0],   // Dogri
        'dsb' => ['cardinal' => 7, 'ordinal' => 0],   // Lower Sorbian - CLDR 49
        'dv' => ['cardinal' => 1, 'ordinal' => 0],    // Divehi
        'dyu' => ['cardinal' => 0, 'ordinal' => 0],   // Dyula
        'dz' => ['cardinal' => 0, 'ordinal' => 0],    // Dzongkha

        // E
        'ee' => ['cardinal' => 1, 'ordinal' => 0],    // Ewe
        'el' => ['cardinal' => 1, 'ordinal' => 0],    // Greek
        'en' => ['cardinal' => 1, 'ordinal' => 1],    // English - CLDR 49 ordinal: one/two/few/other
        'eo' => ['cardinal' => 1, 'ordinal' => 0],    // Esperanto
        'es' => ['cardinal' => 20, 'ordinal' => 34],   // Spanish - CLDR 49 ordinal: one/other
        'et' => ['cardinal' => 1, 'ordinal' => 0],    // Estonian
        'eu' => ['cardinal' => 1, 'ordinal' => 0],    // Basque

        // F
        'fa' => ['cardinal' => 2, 'ordinal' => 0],    // Persian
        'ff' => ['cardinal' => 1, 'ordinal' => 0],    // Fulah
        'fi' => ['cardinal' => 1, 'ordinal' => 0],    // Finnish
        'fil' => ['cardinal' => 25, 'ordinal' => 2],   // Filipino - CLDR 49 cardinal: one/other (does not end in 4,6,9)
        'fj' => ['cardinal' => 0, 'ordinal' => 0],    // Fijian
        'fn' => ['cardinal' => 0, 'ordinal' => 0],    // Fanagalo
        'fo' => ['cardinal' => 1, 'ordinal' => 0],    // Faroese
        'fon' => ['cardinal' => 0, 'ordinal' => 0],   // Fon
        'fr' => ['cardinal' => 20, 'ordinal' => 2],   // French - CLDR 49 ordinal: one/other
        'fuc' => ['cardinal' => 1, 'ordinal' => 0],   // Pulaar
        'fur' => ['cardinal' => 1, 'ordinal' => 0],   // Friulian
        'fuv' => ['cardinal' => 1, 'ordinal' => 0],   // Nigerian Fulfulde
        'fy' => ['cardinal' => 1, 'ordinal' => 0],    // Western Frisian - CLDR 49

        // G
        'ga' => ['cardinal' => 5, 'ordinal' => 2],    // Irish - CLDR 49 ordinal: one/other
        'gax' => ['cardinal' => 1, 'ordinal' => 0],   // Borana-Arsi-Guji Oromo
        'gaz' => ['cardinal' => 1, 'ordinal' => 0],   // West Central Oromo
        'gcl' => ['cardinal' => 2, 'ordinal' => 0],   // Grenadian Creole English
        'gd' => ['cardinal' => 16, 'ordinal' => 16],  // Scottish Gaelic - CLDR 49 ordinal: one/two/few/other
        'gil' => ['cardinal' => 0, 'ordinal' => 0],   // Gilbertese
        'gl' => ['cardinal' => 1, 'ordinal' => 0],    // Galician
        'glw' => ['cardinal' => 1, 'ordinal' => 0],   // Glaro-Twabo
        'gn' => ['cardinal' => 1, 'ordinal' => 0],    // Guarani
        'grc' => ['cardinal' => 1, 'ordinal' => 0],   // Ancient Greek
        'grt' => ['cardinal' => 1, 'ordinal' => 0],   // Garo
        'gsw' => ['cardinal' => 1, 'ordinal' => 0],   // Swiss German - CLDR 49
        'gu' => ['cardinal' => 1, 'ordinal' => 24],   // Gujarati - CLDR 49 ordinal: one/two/few/many/other
        'guz' => ['cardinal' => 1, 'ordinal' => 0],   // Gusii
        'gv' => ['cardinal' => 18, 'ordinal' => 0],   // Manx
        'gyn' => ['cardinal' => 1, 'ordinal' => 0],   // Guyanese Creole English

        // H
        'ha' => ['cardinal' => 1, 'ordinal' => 0],    // Hausa
        'haw' => ['cardinal' => 1, 'ordinal' => 0],   // Hawaiian
        'he' => ['cardinal' => 19, 'ordinal' => 0],   // Hebrew
        'hi' => ['cardinal' => 2, 'ordinal' => 23],   // Hindi - CLDR 49 ordinal: one/two/few/many/other
        'hig' => ['cardinal' => 1, 'ordinal' => 0],   // Kamwe
        'hil' => ['cardinal' => 1, 'ordinal' => 0],   // Hiligaynon
        'hmn' => ['cardinal' => 0, 'ordinal' => 0],   // Hmong
        'hne' => ['cardinal' => 1, 'ordinal' => 0],   // Chhattisgarhi
        'hnj' => ['cardinal' => 0, 'ordinal' => 0],   // Hmong Njua - CLDR 49
        'hoc' => ['cardinal' => 1, 'ordinal' => 0],   // Ho
        'hr' => ['cardinal' => 27, 'ordinal' => 0],    // Croatian - CLDR 49: one/few/other
        'hsb' => ['cardinal' => 7, 'ordinal' => 0],   // Upper Sorbian - CLDR 49
        'ht' => ['cardinal' => 20, 'ordinal' => 2],   // Haitian Creole - CLDR 49: one/many/other
        'hu' => ['cardinal' => 1, 'ordinal' => 35],   // Hungarian - CLDR 49 ordinal: one/other
        'hy' => ['cardinal' => 1, 'ordinal' => 2],    // Armenian - CLDR 49 ordinal: one/other

        // I
        'ia' => ['cardinal' => 1, 'ordinal' => 0],    // Interlingua - CLDR 49
        'id' => ['cardinal' => 0, 'ordinal' => 0],    // Indonesian
        'ig' => ['cardinal' => 0, 'ordinal' => 0],    // Igbo
        'ii' => ['cardinal' => 0, 'ordinal' => 0],    // Sichuan Yi - CLDR 49
        'ilo' => ['cardinal' => 1, 'ordinal' => 0],   // Ilocano
        'io' => ['cardinal' => 1, 'ordinal' => 0],    // Ido - CLDR 49
        'is' => ['cardinal' => 15, 'ordinal' => 0],   // Icelandic
        'it' => ['cardinal' => 20, 'ordinal' => 20],  // Italian - CLDR 49 ordinal: many/other
        'iu' => ['cardinal' => 21, 'ordinal' => 0],   // Inuktitut - CLDR 49

        // J
        'ja' => ['cardinal' => 0, 'ordinal' => 0],    // Japanese
        'jam' => ['cardinal' => 1, 'ordinal' => 0],   // Jamaican Creole English
        'jbo' => ['cardinal' => 0, 'ordinal' => 0],   // Lojban - CLDR 49
        'jgo' => ['cardinal' => 1, 'ordinal' => 0],   // Ngomba - CLDR 49
        'ji' => ['cardinal' => 1, 'ordinal' => 0],    // Yiddish (alternate code)
        'jmc' => ['cardinal' => 1, 'ordinal' => 0],   // Machame - CLDR 49
        'jv' => ['cardinal' => 0, 'ordinal' => 0],    // Javanese

        // K
        'ka' => ['cardinal' => 1, 'ordinal' => 40],   // Georgian - CLDR 49 ordinal: one/many/other
        'kab' => ['cardinal' => 2, 'ordinal' => 0],   // Kabyle
        'kac' => ['cardinal' => 0, 'ordinal' => 0],   // Kachin
        'kaj' => ['cardinal' => 1, 'ordinal' => 0],   // Jju - CLDR 49
        'kal' => ['cardinal' => 1, 'ordinal' => 0],   // Kalaallisut (alternate code) - same as 'kl'
        'kam' => ['cardinal' => 1, 'ordinal' => 0],   // Kamba
        'kar' => ['cardinal' => 0, 'ordinal' => 0],   // Karen
        'kas' => ['cardinal' => 1, 'ordinal' => 0],   // Kashmiri
        'kbp' => ['cardinal' => 0, 'ordinal' => 0],   // Kabiyè
        'kcg' => ['cardinal' => 1, 'ordinal' => 0],   // Tyap - CLDR 49
        'kde' => ['cardinal' => 0, 'ordinal' => 0],   // Makonde - CLDR 49
        'kea' => ['cardinal' => 0, 'ordinal' => 0],   // Kabuverdianu
        'kg' => ['cardinal' => 1, 'ordinal' => 0],    // Kongo
        'kha' => ['cardinal' => 1, 'ordinal' => 0],   // Khasi
        'khk' => ['cardinal' => 1, 'ordinal' => 0],   // Halh Mongolian
        'ki' => ['cardinal' => 1, 'ordinal' => 0],    // Kikuyu
        'kjb' => ['cardinal' => 1, 'ordinal' => 0],   // Q'anjob'al
        'kk' => ['cardinal' => 1, 'ordinal' => 21],   // Kazakh - CLDR 49 ordinal: many/other
        'kkj' => ['cardinal' => 1, 'ordinal' => 0],   // Kako - CLDR 49
        'kl' => ['cardinal' => 1, 'ordinal' => 0],    // Greenlandic
        'kln' => ['cardinal' => 1, 'ordinal' => 0],   // Kalenjin
        'km' => ['cardinal' => 0, 'ordinal' => 0],    // Khmer
        'kmb' => ['cardinal' => 1, 'ordinal' => 0],   // Kimbundu
        'kmr' => ['cardinal' => 1, 'ordinal' => 0],   // Northern Kurdish
        'kn' => ['cardinal' => 1, 'ordinal' => 0],   // Kannada - CLDR 49 ordinal: other only
        'knc' => ['cardinal' => 1, 'ordinal' => 0],   // Central Kanuri
        'ko' => ['cardinal' => 0, 'ordinal' => 0],    // Korean
        'kok' => ['cardinal' => 1, 'ordinal' => 26],   // Konkani - CLDR 49 ordinal: one/two/few/other
        'kr' => ['cardinal' => 0, 'ordinal' => 0],    // Kanuri
        'ks' => ['cardinal' => 1, 'ordinal' => 0],    // Kashmiri
        'ksb' => ['cardinal' => 1, 'ordinal' => 0],   // Shambala - CLDR 49
        'ksh' => ['cardinal' => 22, 'ordinal' => 0],  // Colognian - CLDR 49
        'ksw' => ['cardinal' => 0, 'ordinal' => 0],   // S'gaw Karen
        'ku' => ['cardinal' => 1, 'ordinal' => 0],    // Kurdish - CLDR 49
        'kw' => ['cardinal' => 24, 'ordinal' => 32],  // Cornish - CLDR 49
        'ky' => ['cardinal' => 1, 'ordinal' => 0],    // Kyrgyz

        // L
        'la' => ['cardinal' => 1, 'ordinal' => 0],    // Latin
        'lag' => ['cardinal' => 22, 'ordinal' => 0],  // Langi - CLDR 49
        'lb' => ['cardinal' => 1, 'ordinal' => 0],    // Luxembourgish
        'lg' => ['cardinal' => 1, 'ordinal' => 0],    // Ganda
        'li' => ['cardinal' => 1, 'ordinal' => 0],    // Limburgish
        'lij' => ['cardinal' => 1, 'ordinal' => 20],   // Ligurian - CLDR 49 ordinal: many/other
        'lkt' => ['cardinal' => 0, 'ordinal' => 0],   // Lakota - CLDR 49
        'lld' => ['cardinal' => 20, 'ordinal' => 20], // Ladin - CLDR 49
        'lmo' => ['cardinal' => 1, 'ordinal' => 0],   // Lombard
        'ln' => ['cardinal' => 2, 'ordinal' => 0],    // Lingala
        'lo' => ['cardinal' => 0, 'ordinal' => 2],    // Lao - CLDR 49 ordinal: one/other
        'lt' => ['cardinal' => 6, 'ordinal' => 0],    // Lithuanian
        'ltg' => ['cardinal' => 10, 'ordinal' => 0],  // Latgalian
        'lua' => ['cardinal' => 1, 'ordinal' => 0],   // Luba-Lulua
        'lug' => ['cardinal' => 1, 'ordinal' => 0],   // Luganda (alternate code) - same as 'lg'
        'luo' => ['cardinal' => 1, 'ordinal' => 0],   // Luo
        'lus' => ['cardinal' => 1, 'ordinal' => 0],   // Mizo
        'luy' => ['cardinal' => 1, 'ordinal' => 0],   // Luyia
        'lv' => ['cardinal' => 10, 'ordinal' => 0],   // Latvian
        'lvs' => ['cardinal' => 10, 'ordinal' => 0],  // Standard Latvian

        // M
        'mag' => ['cardinal' => 1, 'ordinal' => 0],   // Magahi
        'mai' => ['cardinal' => 1, 'ordinal' => 0],   // Maithili
        'mam' => ['cardinal' => 1, 'ordinal' => 0],   // Mam
        'mas' => ['cardinal' => 1, 'ordinal' => 0],   // Maasai
        'me' => ['cardinal' => 27, 'ordinal' => 0],    // Montenegrin - same as Serbian (CLDR 49)
        'men' => ['cardinal' => 1, 'ordinal' => 0],   // Mende
        'mer' => ['cardinal' => 1, 'ordinal' => 0],   // Meru
        'mfe' => ['cardinal' => 2, 'ordinal' => 0],   // Mauritian Creole
        'mfi' => ['cardinal' => 1, 'ordinal' => 0],   // Wandala
        'mfv' => ['cardinal' => 1, 'ordinal' => 0],   // Mandjak
        'mg' => ['cardinal' => 2, 'ordinal' => 0],    // Malagasy
        'mgo' => ['cardinal' => 1, 'ordinal' => 0],   // Metaʼ - CLDR 49
        'mh' => ['cardinal' => 0, 'ordinal' => 0],    // Marshallese
        'mhr' => ['cardinal' => 1, 'ordinal' => 0],   // Eastern Mari
        'mi' => ['cardinal' => 2, 'ordinal' => 0],    // Maori
        'min' => ['cardinal' => 0, 'ordinal' => 0],   // Minangkabau
        'mk' => ['cardinal' => 8, 'ordinal' => 8],    // Macedonian - CLDR 49 ordinal: one/two/many/other
        'ml' => ['cardinal' => 1, 'ordinal' => 0],    // Malayalam
        'mn' => ['cardinal' => 1, 'ordinal' => 0],    // Mongolian
        'mni' => ['cardinal' => 1, 'ordinal' => 0],   // Manipuri
        'mnk' => ['cardinal' => 1, 'ordinal' => 0],   // Mandinka
        'mo' => ['cardinal' => 12, 'ordinal' => 2],   // Moldavian (same as Romanian)
        'mos' => ['cardinal' => 0, 'ordinal' => 0],   // Mossi
        'mr' => ['cardinal' => 1, 'ordinal' => 26],   // Marathi - CLDR 49 ordinal: one/two/few/other
        'mrj' => ['cardinal' => 1, 'ordinal' => 0],   // Western Mari
        'mrt' => ['cardinal' => 1, 'ordinal' => 0],   // Marghi Central
        'ms' => ['cardinal' => 0, 'ordinal' => 2],    // Malay - CLDR 49 ordinal: one/other
        'mt' => ['cardinal' => 28, 'ordinal' => 0],    // Maltese - CLDR 49: one/two/few/many/other
        'my' => ['cardinal' => 0, 'ordinal' => 0],    // Burmese

        // N
        'nb' => ['cardinal' => 1, 'ordinal' => 0],    // Norwegian Bokmål
        'nd' => ['cardinal' => 1, 'ordinal' => 0],    // North Ndebele
        'ndc' => ['cardinal' => 1, 'ordinal' => 0],   // Ndau
        'ne' => ['cardinal' => 1, 'ordinal' => 29],   // Nepali - CLDR 49 ordinal: one/other
        'naq' => ['cardinal' => 21, 'ordinal' => 0],  // Nama - CLDR 49
        'niu' => ['cardinal' => 0, 'ordinal' => 0],   // Niuean
        'nl' => ['cardinal' => 1, 'ordinal' => 0],    // Dutch
        'nn' => ['cardinal' => 1, 'ordinal' => 0],    // Norwegian Nynorsk
        'nnh' => ['cardinal' => 1, 'ordinal' => 0],   // Ngiemboon - CLDR 49
        'no' => ['cardinal' => 1, 'ordinal' => 0],    // Norwegian - CLDR 49
        'nqo' => ['cardinal' => 0, 'ordinal' => 0],   // N'Ko - CLDR 49
        'nr' => ['cardinal' => 1, 'ordinal' => 0],    // South Ndebele
        'ns' => ['cardinal' => 2, 'ordinal' => 0],    // Sesotho/Northern Sotho (alternate code) - same as 'nso'
        'nso' => ['cardinal' => 2, 'ordinal' => 0],   // Northern Sotho
        'nup' => ['cardinal' => 1, 'ordinal' => 0],   // Nupe
        'nus' => ['cardinal' => 1, 'ordinal' => 0],   // Nuer
        'ny' => ['cardinal' => 1, 'ordinal' => 0],    // Nyanja (Chichewa)
        'nyf' => ['cardinal' => 1, 'ordinal' => 0],   // Giryama
        'nyn' => ['cardinal' => 1, 'ordinal' => 0],   // Nyankole - CLDR 49

        // O
        'oc' => ['cardinal' => 2, 'ordinal' => 0],    // Occitan
        'om' => ['cardinal' => 1, 'ordinal' => 0],    // Oromo
        'or' => ['cardinal' => 1, 'ordinal' => 27],   // Odia - CLDR 49 ordinal: one/two/few/many/other
        'ory' => ['cardinal' => 1, 'ordinal' => 27],  // Odia (Oriya)
        'os' => ['cardinal' => 1, 'ordinal' => 0],    // Ossetic - CLDR 49
        'osa' => ['cardinal' => 0, 'ordinal' => 0],   // Osage - CLDR 49

        // P
        'pa' => ['cardinal' => 1, 'ordinal' => 0],    // Punjabi
        'pag' => ['cardinal' => 1, 'ordinal' => 0],   // Pangasinan
        'pap' => ['cardinal' => 1, 'ordinal' => 0],   // Papiamento
        'pau' => ['cardinal' => 0, 'ordinal' => 0],   // Palauan
        'pbt' => ['cardinal' => 1, 'ordinal' => 0],   // Southern Pashto
        'pcm' => ['cardinal' => 2, 'ordinal' => 0],   // Nigerian Pidgin - CLDR 49
        'pi' => ['cardinal' => 1, 'ordinal' => 0],    // Pali
        'pis' => ['cardinal' => 0, 'ordinal' => 0],   // Pijin
        'pko' => ['cardinal' => 1, 'ordinal' => 0],   // Pökoot
        'pl' => ['cardinal' => 11, 'ordinal' => 0],   // Polish
        'plt' => ['cardinal' => 2, 'ordinal' => 0],   // Plateau Malagasy
        'pon' => ['cardinal' => 0, 'ordinal' => 0],   // Pohnpeian
        'pot' => ['cardinal' => 1, 'ordinal' => 0],   // Potawatomi
        'pov' => ['cardinal' => 1, 'ordinal' => 0],   // Guinea-Bissau Creole
        'ppk' => ['cardinal' => 0, 'ordinal' => 0],   // Uma
        'prg' => ['cardinal' => 10, 'ordinal' => 0],  // Prussian - CLDR 49
        'prs' => ['cardinal' => 2, 'ordinal' => 0],   // Dari
        'ps' => ['cardinal' => 1, 'ordinal' => 0],    // Pashto
        'pt' => ['cardinal' => 20, 'ordinal' => 0],   // Portuguese - CLDR 49
        'pt_pt' => ['cardinal' => 20, 'ordinal' => 0], // European Portuguese - CLDR 49

        // Q
        'qu' => ['cardinal' => 1, 'ordinal' => 0],    // Quechua
        'quc' => ['cardinal' => 1, 'ordinal' => 0],   // K'iche'
        'quy' => ['cardinal' => 1, 'ordinal' => 0],   // Ayacucho Quechua
        'qnt' => ['cardinal' => 1, 'ordinal' => 0],   // Testing pseudo-locale

        // R
        'rhg' => ['cardinal' => 1, 'ordinal' => 0],   // Rohingya
        'rhl' => ['cardinal' => 1, 'ordinal' => 0],   // Rohingya (alternate)
        'rmn' => ['cardinal' => 27, 'ordinal' => 0],   // Balkan Romani - same as Serbian (CLDR 49)
        'rmo' => ['cardinal' => 1, 'ordinal' => 0],   // Sinte Romani
        'rn' => ['cardinal' => 1, 'ordinal' => 0],    // Rundi
        'rm' => ['cardinal' => 1, 'ordinal' => 0],    // Romansh - CLDR 49
        'ro' => ['cardinal' => 12, 'ordinal' => 2],   // Romanian - CLDR 49 ordinal: one/other
        'rof' => ['cardinal' => 1, 'ordinal' => 0],   // Rombo - CLDR 49
        'roh' => ['cardinal' => 1, 'ordinal' => 0],   // Romansh
        'ru' => ['cardinal' => 3, 'ordinal' => 0],    // Russian
        'run' => ['cardinal' => 1, 'ordinal' => 0],   // Rundi (alternate)
        'rw' => ['cardinal' => 1, 'ordinal' => 0],    // Kinyarwanda
        'rwk' => ['cardinal' => 1, 'ordinal' => 0],   // Rwa - CLDR 49

        // S
        'sa' => ['cardinal' => 1, 'ordinal' => 0],    // Sanskrit
        'sah' => ['cardinal' => 0, 'ordinal' => 0],   // Yakut - CLDR 49
        'saq' => ['cardinal' => 1, 'ordinal' => 0],   // Samburu - CLDR 49
        'sat' => ['cardinal' => 21, 'ordinal' => 0],   // Santali - CLDR 49: one/two/other
        'sc' => ['cardinal' => 1, 'ordinal' => 20],   // Sardinian - CLDR 49 ordinal: many/other
        'scn' => ['cardinal' => 20, 'ordinal' => 20],   // Sicilian - CLDR 49: one/many/other, ordinal: many/other
        'sd' => ['cardinal' => 1, 'ordinal' => 0],    // Sindhi
        'sdh' => ['cardinal' => 1, 'ordinal' => 0],   // Southern Kurdish - CLDR 49
        'se' => ['cardinal' => 21, 'ordinal' => 0],   // Northern Sami - CLDR 49
        'seh' => ['cardinal' => 1, 'ordinal' => 0],   // Sena
        'ses' => ['cardinal' => 0, 'ordinal' => 0],   // Koyraboro Senni - CLDR 49
        'sg' => ['cardinal' => 0, 'ordinal' => 0],    // Sango
        'sh' => ['cardinal' => 27, 'ordinal' => 0],    // Serbo-Croatian - CLDR 49: one/few/other
        'shi' => ['cardinal' => 23, 'ordinal' => 0],  // Tachelhit - CLDR 49
        'shn' => ['cardinal' => 0, 'ordinal' => 0],   // Shan
        'shu' => ['cardinal' => 13, 'ordinal' => 0],  // Chadian Arabic
        'si' => ['cardinal' => 1, 'ordinal' => 0],    // Sinhala
        'sk' => ['cardinal' => 4, 'ordinal' => 0],    // Slovak
        'sl' => ['cardinal' => 7, 'ordinal' => 0],    // Slovenian
        'sm' => ['cardinal' => 0, 'ordinal' => 0],    // Samoan
        'sma' => ['cardinal' => 21, 'ordinal' => 0],  // Southern Sami - CLDR 49
        'smj' => ['cardinal' => 21, 'ordinal' => 0],  // Lule Sami - CLDR 49
        'smn' => ['cardinal' => 21, 'ordinal' => 0],  // Inari Sami - CLDR 49
        'smo' => ['cardinal' => 0, 'ordinal' => 0],   // Samoan (alternate code) - same as 'sm'
        'sms' => ['cardinal' => 21, 'ordinal' => 0],  // Skolt Sami - CLDR 49
        'sn' => ['cardinal' => 1, 'ordinal' => 0],    // Shona
        'sna' => ['cardinal' => 1, 'ordinal' => 0],   // Shona (alternate code) - same as 'sn'
        'snk' => ['cardinal' => 1, 'ordinal' => 0],   // Soninke
        'so' => ['cardinal' => 1, 'ordinal' => 0],    // Somali
        'sq' => ['cardinal' => 1, 'ordinal' => 30],   // Albanian - CLDR 49 ordinal: one/many/other
        'sr' => ['cardinal' => 27, 'ordinal' => 0],    // Serbian - CLDR 49: one/few/other
        'srn' => ['cardinal' => 1, 'ordinal' => 0],   // Sranan Tongo
        'ss' => ['cardinal' => 1, 'ordinal' => 0],    // Swati
        'ssy' => ['cardinal' => 1, 'ordinal' => 0],   // Saho
        'st' => ['cardinal' => 1, 'ordinal' => 0],    // Southern Sotho
        'su' => ['cardinal' => 0, 'ordinal' => 0],    // Sundanese
        'sus' => ['cardinal' => 0, 'ordinal' => 0],   // Susu
        'sv' => ['cardinal' => 1, 'ordinal' => 2],    // Swedish - CLDR 49 ordinal: one/other
        'svc' => ['cardinal' => 1, 'ordinal' => 0],   // Vincentian Creole English
        'sw' => ['cardinal' => 1, 'ordinal' => 0],    // Swahili
        'syc' => ['cardinal' => 1, 'ordinal' => 0],   // Classical Syriac
        'syr' => ['cardinal' => 1, 'ordinal' => 0],   // Syriac - CLDR 49
        'szl' => ['cardinal' => 11, 'ordinal' => 0],  // Silesian

        // T
        'ta' => ['cardinal' => 1, 'ordinal' => 0],    // Tamil
        'taq' => ['cardinal' => 0, 'ordinal' => 0],   // Tamasheq
        'te' => ['cardinal' => 1, 'ordinal' => 0],   // Telugu - CLDR 49 ordinal: other only
        'teo' => ['cardinal' => 1, 'ordinal' => 0],   // Teso - CLDR 49
        'tet' => ['cardinal' => 1, 'ordinal' => 0],   // Tetum
        'tg' => ['cardinal' => 2, 'ordinal' => 0],    // Tajik
        'th' => ['cardinal' => 0, 'ordinal' => 0],    // Thai
        'ti' => ['cardinal' => 2, 'ordinal' => 0],    // Tigrinya
        'tig' => ['cardinal' => 1, 'ordinal' => 0],   // Tigre - CLDR 49
        'tiv' => ['cardinal' => 1, 'ordinal' => 0],   // Tiv
        'tk' => ['cardinal' => 1, 'ordinal' => 22],   // Turkmen - CLDR 49 ordinal: few/other
        'tkl' => ['cardinal' => 0, 'ordinal' => 0],   // Tokelau
        'tl' => ['cardinal' => 25, 'ordinal' => 2],    // Tagalog - same as Filipino (CLDR 49)
        'tmh' => ['cardinal' => 0, 'ordinal' => 0],   // Tamashek
        'tn' => ['cardinal' => 1, 'ordinal' => 0],    // Tswana
        'to' => ['cardinal' => 0, 'ordinal' => 0],    // Tongan
        'ton' => ['cardinal' => 0, 'ordinal' => 0],   // Tongan (alternate code) - same as 'to'
        'tpi' => ['cardinal' => 0, 'ordinal' => 0],   // Tok Pisin
        'tr' => ['cardinal' => 1, 'ordinal' => 0],    // Turkish - CLDR 49: n = 1
        'trv' => ['cardinal' => 0, 'ordinal' => 0],   // Taroko
        'ts' => ['cardinal' => 1, 'ordinal' => 0],    // Tsonga
        'tsc' => ['cardinal' => 1, 'ordinal' => 0],   // Tswa
        'tt' => ['cardinal' => 0, 'ordinal' => 0],    // Tatar
        'tum' => ['cardinal' => 1, 'ordinal' => 0],   // Tumbuka
        'tvl' => ['cardinal' => 0, 'ordinal' => 0],   // Tuvalu
        'tw' => ['cardinal' => 2, 'ordinal' => 0],    // Twi
        'ty' => ['cardinal' => 0, 'ordinal' => 0],    // Tahitian
        'tzm' => ['cardinal' => 26, 'ordinal' => 0],   // Central Atlas Tamazight - CLDR 49: n = 0–1 or n = 11–99

        // U
        'udm' => ['cardinal' => 1, 'ordinal' => 0],   // Udmurt
        'ug' => ['cardinal' => 1, 'ordinal' => 0],    // Uyghur - CLDR 49: one/other
        'uk' => ['cardinal' => 3, 'ordinal' => 22],   // Ukrainian - CLDR 49 ordinal: few/other
        'umb' => ['cardinal' => 1, 'ordinal' => 0],   // Umbundu
        'ur' => ['cardinal' => 1, 'ordinal' => 0],    // Urdu
        'uz' => ['cardinal' => 1, 'ordinal' => 0],    // Uzbek - CLDR 49: n = 1
        'uzn' => ['cardinal' => 1, 'ordinal' => 0],   // Northern Uzbek - CLDR 49: n = 1

        // V
        've' => ['cardinal' => 1, 'ordinal' => 0],    // Venda - CLDR 49
        'vec' => ['cardinal' => 20, 'ordinal' => 20],   // Venetian - CLDR 49: one/many/other, ordinal: many/other
        'vi' => ['cardinal' => 0, 'ordinal' => 2],    // Vietnamese - CLDR 49 ordinal: one/other
        'vic' => ['cardinal' => 1, 'ordinal' => 0],   // Virgin Islands Creole English
        'vls' => ['cardinal' => 1, 'ordinal' => 0],   // Vlaams (West Flemish)
        'vmw' => ['cardinal' => 1, 'ordinal' => 0],   // Makhuwa
        'vo' => ['cardinal' => 1, 'ordinal' => 0],    // Volapük - CLDR 49
        'vun' => ['cardinal' => 1, 'ordinal' => 0],   // Vunjo - CLDR 49

        // W
        'wa' => ['cardinal' => 2, 'ordinal' => 0],    // Walloon - CLDR 49
        'wae' => ['cardinal' => 1, 'ordinal' => 0],   // Walser - CLDR 49
        'war' => ['cardinal' => 1, 'ordinal' => 0],   // Waray
        'wls' => ['cardinal' => 0, 'ordinal' => 0],   // Wallisian
        'wo' => ['cardinal' => 0, 'ordinal' => 0],    // Wolof

        // X
        'xh' => ['cardinal' => 1, 'ordinal' => 0],    // Xhosa
        'xog' => ['cardinal' => 1, 'ordinal' => 0],   // Soga - CLDR 49

        // Y
        'ydd' => ['cardinal' => 1, 'ordinal' => 0],   // Eastern Yiddish
        'yi' => ['cardinal' => 1, 'ordinal' => 0],    // Yiddish
        'ymm' => ['cardinal' => 1, 'ordinal' => 0],   // Maay Maay
        'yo' => ['cardinal' => 0, 'ordinal' => 0],    // Yoruba - CLDR 49: other only
        'yue' => ['cardinal' => 0, 'ordinal' => 0],   // Cantonese - CLDR 49

        // Z
        'zdj' => ['cardinal' => 1, 'ordinal' => 0],   // Ngazidja Comorian
        'zh' => ['cardinal' => 0, 'ordinal' => 0],    // Chinese
        'zsm' => ['cardinal' => 0, 'ordinal' => 2],   // Standard Malay - CLDR 49 ordinal: one/other
        'zu' => ['cardinal' => 1, 'ordinal' => 0],    // Zulu
    ];

    /**
     * Returns the cardinal plural form index for the given locale corresponding
     * to the countable provided in $n.
     *
     * @param string $locale The locale to get the rule calculated for.
     * @param int $n The number to apply the rules to.
     * @return int The plural rule number that should be used.
     * @link https://docs.translatehouse.org/projects/localization-guide/en/latest/l10n/pluralforms.html
     * @link https://developer.mozilla.org/en-US/docs/Mozilla/Localization/Localization_and_Plurals#List_of_Plural_Rules
     */
    public static function getCardinalFormIndex(string $locale, int $n): int
    {
        $ruleGroup = self::getRuleGroup($locale);

        return match ($ruleGroup) {
            // nplurals=1; plural=0; (Asian, no plural forms)
            0 => 0,
            // nplurals=2; plural=(n > 1); (Amharic, Persian, Hindi, etc. — integer approximation of CLDR "i = 0 or n = 1")
            2 => $n > 1 ? 1 : 0,
            // nplurals=3/4; Slavic (Rule 3: ru/uk/be → one/few/many/other; Rule 27: bs/hr/sr → one/few/other)
            // Same integer computation; category arrays differ in $cardinalCategoryMap.
            3, 27 => match (true) {
                $n % 10 === 1 && $n % 100 !== 11 => 0,
                $n % 10 >= 2 && $n % 10 <= 4 && ($n % 100 < 10 || $n % 100 >= 20) => 1,
                default => 2,
            },
            // nplurals=4; (Czech, Slovak — CLDR 49: "many" at index 2 is for decimals v!=0, unreachable for int)
            4 => match (true) {
                $n === 1 => 0,
                $n >= 2 && $n <= 4 => 1,
                default => 3,   // index 3 = "other" (index 2 = "many" is decimal-only)
            },
            // nplurals=5; (Irish)
            5 => match (true) {
                $n === 1 => 0,
                $n === 2 => 1,
                $n < 7 => 2,
                $n < 11 => 3,
                default => 4,
            },
            // nplurals=4; (Lithuanian — CLDR 49: "many" at index 2 is for decimals f!=0, unreachable for int)
            6 => match (true) {
                $n % 10 === 1 && $n % 100 !== 11 => 0,
                $n % 10 >= 2 && ($n % 100 < 10 || $n % 100 >= 20) => 1,
                default => 3,   // index 3 = "other" (index 2 = "many" is decimal-only)
            },
            // nplurals=4; (Slovenian)
            7 => match (true) {
                $n % 100 === 1 => 0,
                $n % 100 === 2 => 1,
                in_array($n % 100, [3, 4], true) => 2,
                default => 3,
            },
            // nplurals=2; (Macedonian - CLDR 48)
            8 => $n % 10 === 1 && $n % 100 !== 11 ? 0 : 1,
            // nplurals=3; (Latvian - CLDR 48)
            10 => match (true) {
                $n === 0 => 0,
                $n % 10 === 1 && $n % 100 !== 11 => 1,
                default => 2,
            },
            // nplurals=3; (Polish)
            11 => match (true) {
                $n === 1 => 0,
                $n % 10 >= 2 && $n % 10 <= 4 && ($n % 100 < 10 || $n % 100 >= 20) => 1,
                default => 2,
            },
            // nplurals=3; (Romanian, Moldavian)
            12 => match (true) {
                $n === 1 => 0,
                $n === 0 || ($n % 100 > 0 && $n % 100 < 20) => 1,
                default => 2,
            },
            // nplurals=6; (Arabic)
            13 => match (true) {
                $n === 0 => 0,
                $n === 1 => 1,
                $n === 2 => 2,
                $n % 100 >= 3 && $n % 100 <= 10 => 3,
                $n % 100 >= 11 => 4,
                default => 5,
            },
            // nplurals=6; (Welsh - CLDR 48)
            14 => match ($n) { // @codeCoverageIgnore strange behavior of curly brackets and match in code coverage,
                0 => 0,
                1 => 1,
                2 => 2,
                3 => 3,
                6 => 4,
                default => 5,
            }, // @codeCoverageIgnore
            // nplurals=2; (Icelandic)
            15 => $n % 10 !== 1 || $n % 100 === 11 ? 1 : 0,
            // nplurals=4; (Scottish Gaelic)
            16 => match (true) {
                in_array($n, [1, 11], true) => 0,
                in_array($n, [2, 12], true) => 1,
                $n > 2 && $n < 20 => 2,
                default => 3,
            },
            // nplurals=5; (Breton - CLDR 48)
            17 => self::calculateBreton($n),
            // nplurals=5; (Manx — CLDR 49: "many" at index 3 is for decimals v!=0, unreachable for int)
            18 => match (true) {
                $n % 10 === 1 => 0,
                $n % 10 === 2 => 1,
                $n % 20 === 0 => 2,
                default => 4,   // index 4 = "other" (index 3 = "many" is decimal-only)
            },
            // nplurals=3; (Hebrew — CLDR 49: one/two/other, removed "many")
            19 => match (true) {
                $n === 1 => 0,
                $n === 2 => 1,
                default => 2,
            },
            // nplurals=3; (Italian, Spanish, Portuguese, French - CLDR 49)
            // one: i = 1 and v = 0
            // many: e = 0 and i != 0 and i % 1000000 = 0 and v = 0
            // other: everything else
            20 => match (true) {
                $n === 1 => 0,
                $n !== 0 && $n % 1000000 === 0 => 1,
                default => 2,
            },
            // nplurals=3; (Inuktitut, Sami, Nama, Swampy Cree - one/two/other)
            21 => match ($n) { // @codeCoverageIgnore
                1 => 0,
                2 => 1,
                default => 2,
            }, // @codeCoverageIgnore
            // nplurals=3; (Colognian, Anii, Langi - zero/one/other)
            22 => match ($n) { // @codeCoverageIgnore
                0 => 0,
                1 => 1,
                default => 2,
            }, // @codeCoverageIgnore
            // nplurals=3; (Tachelhit - one/few/other)
            23 => match (true) {
                $n <= 1 => 0,
                $n <= 10 => 1,
                default => 2,
            },
            // nplurals=6; (Cornish)
            24 => self::calculateCornish($n),
            // nplurals=2; (Filipino, Tagalog - CLDR 49)
            // one: v = 0 and i = 1,2,3 or v = 0 and i % 10 != 4,6,9 or v != 0 and f % 10 != 4,6,9
            // For integers: "other" when n ends in 4, 6, or 9; "one" otherwise
            25 => in_array($n % 10, [4, 6, 9], true) ? 1 : 0,
            // nplurals=2; (Central Atlas Tamazight - CLDR 49)
            // one: n = 0..1 or n = 11..99
            26 => ($n <= 1 || ($n >= 11 && $n <= 99)) ? 0 : 1,
            // nplurals=5; (Maltese — CLDR 49: one/two/few/many/other)
            28 => match (true) {
                $n === 1 => 0,
                $n === 2 => 1,
                $n === 0 || ($n % 100 >= 2 && $n % 100 <= 10) => 2,
                $n % 100 > 10 && $n % 100 < 20 => 3,
                default => 4,
            },
            // nplurals=2; plural=(n != 1); (Germanic, most European)
            // Fallback: Rule 1 (n != 1) is the most common CLDR cardinal rule,
            // covering ~170+ locales (Germanic, most European languages).
            default => $n === 1 ? 0 : 1,
        };
    }

    /**
     * Calculate the plural form for the Breton language (Rule 17 - CLDR 48)
     * one: n%10=1 and n%100 not in 11,71,91
     * two: n%10=2 and n%100 not in 12,72,92
     * few: n%10 in 3,4,9 and n%100 not in 10..19,70..79,90..99
     * many: n!=0 and n%1000000=0
     * other: everything else
     */
    private static function calculateBreton(int $n): int
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
    private static function calculateCornish(int $n): int
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

    /**
     * Calculate the ordinal plural form for Bulgarian (Rule 38 - CLDR 49)
     * zero: i % 100 = 11
     * one: i % 100 = 1
     * two: i % 100 = 2
     * few: i % 100 = 7,8
     * many: i % 100 = 3..6
     * other: everything else
     */
    private static function calculateBulgarianOrdinal(int $n): int
    {
        $n100 = $n % 100;

        return match (true) {
            $n100 === 11 => 0,                   // zero
            $n100 === 1 => 1,                     // one
            $n100 === 2 => 2,                     // two
            in_array($n100, [7, 8], true) => 3,  // few
            $n100 >= 3 && $n100 <= 6 => 4,       // many
            default => 5,                         // other
        };
    }

    /**
     * Returns the CLDR plural category name for the given locale and number.
     *
     * This method combines calculate() with the category mapping to return
     * the actual category name ('zero', 'one', 'two', 'few', 'many', 'other').
     *
     * ## Usage Example
     *
     * ```php
     * use Matecat\ICU\PluralRules\PluralRules;
     *
     * // English
     * PluralRules::getCategoryName('en', 1); // Returns "one"
     * PluralRules::getCategoryName('en', 2); // Returns "other"
     *
     * // Arabic
     * PluralRules::getCategoryName('ar', 0); // Returns "zero"
     * PluralRules::getCategoryName('ar', 1); // Returns "one"
     * PluralRules::getCategoryName('ar', 2); // Returns "two"
     * PluralRules::getCategoryName('ar', 5); // Returns "few"
     * PluralRules::getCategoryName('ar', 11); // Returns "many"
     * PluralRules::getCategoryName('ar', 100);// Returns "other"
     *
     * // Russian
     * PluralRules::getCategoryName('ru', 1); // Returns "one"
     * PluralRules::getCategoryName('ru', 2); // Returns "few"
     * PluralRules::getCategoryName('ru', 5); // Returns "many"
     * ```
     *
     * @param string $locale The locale to get the category for.
     * @param int $n The number to apply the rules to.
     * @return string The CLDR plural category name.
     */
    public static function getCardinalCategoryName(string $locale, int $n): string
    {
        $pluralIndex = self::getCardinalFormIndex($locale, $n);
        $ruleGroup = self::getRuleGroup($locale);

        return self::$cardinalCategoryMap[$ruleGroup][$pluralIndex] ?? self::CATEGORY_OTHER;
    }

    /**
     * Returns all available CLDR plural categories for a given locale.
     *
     * This is useful to know which plural forms are available for a language
     * when building ICU MessageFormat patterns.
     *
     * ## Usage Example
     *
     * ```php
     * use Matecat\ICU\PluralRules\PluralRules;
     *
     * PluralRules::getCategories('en');
     * // Returns ['one', 'other']
     *
     * PluralRules::getCategories('ru');
     * // Returns ['one', 'few', 'many']
     *
     * PluralRules::getCategories('ar');
     * // Returns ['zero', 'one', 'two', 'few', 'many', 'other']
     * ```
     *
     * @param string $locale The locale to get categories for.
     * @return array<string> Array of category names available for this locale.
     */
    public static function getCardinalCategories(string $locale): array
    {
        $ruleGroup = self::getRuleGroup($locale);

        return self::$cardinalCategoryMap[$ruleGroup] ?? [self::CATEGORY_OTHER];
    }

    /**
     * Returns all available CLDR ordinal categories for a given locale.
     *
     * Ordinal categories are used for selectordinal patterns (1st, 2nd, 3rd, etc.).
     * These are different from cardinal categories used in plural patterns.
     *
     * ## Usage Example
     *
     * ```php
     * use Matecat\ICU\PluralRules\PluralRules;
     *
     * PluralRules::getOrdinalCategories('en');
     * // Returns ['one', 'two', 'few', 'other'] for 1st, 2nd, 3rd, 4th
     *
     * PluralRules::getOrdinalCategories('ru');
     * // Returns ['other'] - Russian uses the same form for all ordinals
     *
     * PluralRules::getOrdinalCategories('cy');
     * // Returns ['zero', 'one', 'two', 'few', 'many', 'other'] - Welsh has complex ordinals
     * ```
     *
     * @param string $locale The locale to get ordinal categories for.
     * @return array<string> Array of ordinal category names available for this locale.
     * @see https://www.unicode.org/cldr/charts/48/supplemental/language_plural_rules.html
     */
    public static function getOrdinalCategories(string $locale): array
    {
        $ruleGroup = self::getRuleGroup($locale, 'ordinal');

        return self::$ordinalCategoryMap[$ruleGroup] ?? [self::CATEGORY_OTHER];
    }

    /**
     * Returns the ordinal plural form index for the given locale and number.
     *
     * This method calculates which ordinal plural form should be used for a given
     * number in a specific locale. Ordinal numbers are used for ranking or ordering
     * (1st, 2nd, 3rd, etc.) and have different rules than cardinal numbers.
     *
     * The returned index corresponds to the position in the ordinal category array
     * returned by {@see getOrdinalCategories()}. Use {@see getOrdinalCategoryName()}
     * to get the CLDR category name directly.
     *
     * ## How Ordinal Rules Work
     *
     * Different languages have different patterns for ordinal suffixes:
     * - **English**: 1st, 2nd, 3rd, 4th... (one/two/few/other)
     * - **French**: 1er, 2e, 3e... (one/other - only 1 is special)
     * - **Welsh**: Complex system with 6 categories
     * - **Japanese**: No distinction (only "other")
     *
     * ## Usage Example
     *
     * ```php
     * use Matecat\ICU\Plurals\PluralRules;
     *
     * // English ordinals: 1st, 2nd, 3rd, 4th
     * PluralRules::getOrdinalFormIndex('en', 1);  // Returns 0 (one - for "1st")
     * PluralRules::getOrdinalFormIndex('en', 2);  // Returns 1 (two - for "2nd")
     * PluralRules::getOrdinalFormIndex('en', 3);  // Returns 2 (few - for "3rd")
     * PluralRules::getOrdinalFormIndex('en', 4);  // Returns 3 (other - for "4th")
     * PluralRules::getOrdinalFormIndex('en', 21); // Returns 0 (one - for "21st")
     * PluralRules::getOrdinalFormIndex('en', 22); // Returns 1 (two - for "22nd")
     *
     * // French ordinals: 1er, 2e, 3e...
     * PluralRules::getOrdinalFormIndex('fr', 1);  // Returns 0 (one - for "1er")
     * PluralRules::getOrdinalFormIndex('fr', 2);  // Returns 1 (other - for "2e")
     *
     * // Japanese: no ordinal distinction
     * PluralRules::getOrdinalFormIndex('ja', 1);  // Returns 0 (other)
     * PluralRules::getOrdinalFormIndex('ja', 100);// Returns 0 (other)
     * ```
     *
     * ## Relationship with Other Methods
     *
     * - Use {@see getOrdinalCategories()} to get all available ordinal categories for a locale
     * - Use {@see getOrdinalCategoryName()} to get the CLDR category name for a number
     * - For cardinal (counting) numbers, use {@see getCardinalFormIndex()} instead
     *
     * @param string $locale The locale code (e.g., 'en', 'fr', 'de', 'en-US', 'fr_FR')
     * @param int $n The ordinal number to get the form index for (must be non-negative)
     * @return int The ordinal form index (0-based), corresponding to the position in the
     *             ordinal categories array for this locale
     *
     * @see getOrdinalCategoryName() To get the CLDR category name directly
     * @see getOrdinalCategories() To get all available ordinal categories
     * @see getCardinalFormIndex() For cardinal (counting) numbers
     * @see https://www.unicode.org/cldr/charts/49/supplemental/language_plural_rules.html
     */
    public static function getOrdinalFormIndex(string $locale, int $n): int
    {
        $ruleGroup = self::getRuleGroup($locale, 'ordinal');

        return match ($ruleGroup) {

            // Rules with a simple "one: n=1, other: everything else" pattern
            2, 5, 12 => $n === 1 ? 0 : 1,
            // Rule 1: English-like ordinals (one/two/few/other)
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
            // Rule 8: Macedonian ordinals (one/two/many/other)
            8 => match (true) {
                $n % 10 === 1 && $n % 100 !== 11 => 0,
                $n % 10 === 2 && $n % 100 !== 12 => 1,
                in_array($n % 10, [7, 8], true) && !in_array($n % 100, [17, 18], true) => 2,
                default => 3,
            },
            // Rule 14: Welsh ordinals (zero/one/two/few/many/other) - 6 categories
            14 => match ($n) { // @codeCoverageIgnore strange behavior of curly brackets and match in code coverage,
                0, 7, 8, 9 => 0,  // zero
                1 => 1,           // one
                2 => 2,           // two
                3, 4 => 3,        // few
                5, 6 => 4,        // many
                default => 5,     // other
            },
            // Rule 16: Scottish Gaelic ordinals (one/two/few/other)
            16 => match ($n) { // @codeCoverageIgnore strange behavior of curly brackets and match in code coverage,
                1, 11 => 0,       // one
                2, 12 => 1,       // two
                3, 13 => 2,       // few
                default => 3,     // other
            },
            // Rule 20: Italian ordinals (many/other) - special for 8, 11, 80, 800
            20 => in_array($n, [8, 11, 80, 800], true) ? 0 : 1,
            // Rule 21: Kazakh ordinals (many/other)
            // many: n % 10 = 6,9 or n % 10 = 0 and n != 0
            21 => in_array($n % 10, [0, 6, 9], true) && $n !== 0 ? 0 : 1,
            // Rule 22/35: Ukrainian/Turkmen ordinals (few/other); Hungarian ordinals (one/other)
            // Same integer computation; category arrays differ in $ordinalCategoryMap.
            22, 35 => in_array($n, [1, 5], true) ? 0 : 1,
            // Rule 23: Bengali, Assamese, Hindi ordinals (one/two/few/many/other) - CLDR 49
            23 => match (true) {
                in_array($n, [1, 5, 7, 8, 9, 10], true) => 0, // one
                in_array($n, [2, 3], true) => 1,               // two
                $n === 4 => 2,                                   // few
                $n === 6 => 3,                                   // many
                default => 4,                                    // other
            },
            // Rule 24: Gujarati ordinals (one/two/few/many/other)
            24 => match ($n) { // @codeCoverageIgnore strange behavior of curly brackets and match in code coverage,
                1 => 0,           // one
                2, 3 => 1,        // two
                4 => 2,           // few
                6 => 3,           // many
                default => 4,     // other
            },

            // Rule 26: Marathi/Konkani ordinals (one/two/few/other) — CLDR 49
            26 => match ($n) { // @codeCoverageIgnore strange behavior of curly brackets and match in code coverage,
                1 => 0,           // one
                2, 3 => 1,        // two
                4 => 2,           // few
                default => 3,     // other
            },
            // Rule 27: Odia ordinals (one/two/few/many/other)
            27 => match (true) {
                $n === 1 || $n === 5 || ($n >= 7 && $n <= 9) => 0,
                in_array($n, [2, 3], true) => 1,
                $n === 4 => 2,
                $n === 6 => 3,
                default => 4,
            },
            // Rule 29: Nepali ordinals (one/other) — CLDR 49
            29 => $n >= 1 && $n <= 4 ? 0 : 1,
            // Rule 30: Albanian ordinals (one/many/other) - CLDR 49
            // one: n = 1
            // many: n = 4
            // other: everything else
            30 => match ($n) {
                1 => 0,
                4 => 1,
                default => 2,
            },
            // Rule 31: Anii ordinals (zero/one/few/other)
            31 => match (true) {
                $n === 0 => 0,
                $n === 1 => 1,
                $n >= 2 && $n <= 6 => 2,
                default => 3,
            },
            // Rule 32: Cornish ordinals (one/many/other)
            // one: n = 1..4, or n%100 in 1..4,21..24,41..44,61..64,81..84
            // many: n = 5, or n%100 = 5
            32 => match (true) {
                $n >= 1 && $n <= 4
                    || in_array($n % 100, [1, 2, 3, 4, 21, 22, 23, 24, 41, 42, 43, 44, 61, 62, 63, 64, 81, 82, 83, 84], true) => 0,
                $n === 5 || $n % 100 === 5 => 1,
                default => 2,
            },
            // Rule 33: Afrikaans ordinals (few/other) — CLDR 49
            // few: i % 100 = 2..19
            33 => ($n % 100 >= 2 && $n % 100 <= 19) ? 0 : 1,
            // Rule 36: Azerbaijani ordinals (one/few/many/other) — CLDR 49
            // one: i % 10 = 1,2,5,7,8 or i % 100 = 20,50,70,80
            // few: i % 10 = 3,4 or i % 1000 = 100,200,300,400,500,600,700,800,900
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
            // Rule 37: Belarusian ordinals (few/other) — CLDR 49
            // few: n % 10 = 2,3 and n % 100 != 12,13
            37 => (in_array($n % 10, [2, 3], true) && !in_array($n % 100, [12, 13], true)) ? 0 : 1,
            // Rule 38: Bulgarian ordinals (zero/one/two/few/many/other) — CLDR 49
            38 => self::calculateBulgarianOrdinal($n),
            // Rule 39: Catalan ordinals (one/two/few/other) — CLDR 49
            // one: n = 1,3
            // two: n = 2
            // few: n = 4
            39 => match ($n) { // @codeCoverageIgnore strange behavior of curly brackets and match in code coverage,
                1, 3 => 0,        // one
                2 => 1,           // two
                4 => 2,           // few
                default => 3,     // other
            },
            // Rule 40: Georgian ordinals (one/many/other) — CLDR 49
            // one: i = 1
            // many: i = 0 or i % 100 = 2..20,40,60,80
            40 => match (true) {
                $n === 1 => 0,    // one
                $n === 0 || ($n % 100 >= 2 && $n % 100 <= 20)
                    || in_array($n % 100, [40, 60, 80], true) => 1,  // many
                default => 2,     // other
            },
            //IMPORTANT
            // Rules with no ordinal distinction - only "other" (returns 0)
            // merge all these into a single default case
            // 0, 3, 4, 6, 7, 9, 10, 11, 13, 15, 17, 18, 19 => 0,
            default => 0,

        };
    }

    /**
     * Returns the CLDR ordinal category name for the given locale and number.
     *
     * This method determines which ordinal plural category applies to a given number
     * in a specific locale. Ordinal categories are used for ranking or ordering
     * (1st, 2nd, 3rd, etc.) and follow different rules than cardinal categories.
     *
     * The method combines {@see getOrdinalFormIndex()} with the ordinal category mapping
     * to return the actual CLDR category name ('zero', 'one', 'two', 'few', 'many', 'other').
     *
     * ## Ordinal vs Cardinal
     *
     * - **Cardinal**: Used for counting quantities ("1 item", "2 items")
     *   → Use {@see getCardinalCategoryName()}
     * - **Ordinal**: Used for ranking/ordering ("1st place", "2nd floor")
     *   → Use this method
     *
     * ## Usage Example
     *
     * ```php
     * use Matecat\ICU\Plurals\PluralRules;
     *
     * // English ordinals: 1st, 2nd, 3rd, 4th...
     * PluralRules::getOrdinalCategoryName('en', 1);  // Returns "one"   → "1st"
     * PluralRules::getOrdinalCategoryName('en', 2);  // Returns "two"   → "2nd"
     * PluralRules::getOrdinalCategoryName('en', 3);  // Returns "few"   → "3rd"
     * PluralRules::getOrdinalCategoryName('en', 4);  // Returns "other" → "4th"
     * PluralRules::getOrdinalCategoryName('en', 11); // Returns "other" → "11th"
     * PluralRules::getOrdinalCategoryName('en', 21); // Returns "one"   → "21st"
     * PluralRules::getOrdinalCategoryName('en', 22); // Returns "two"   → "22nd"
     * PluralRules::getOrdinalCategoryName('en', 23); // Returns "few"   → "23rd"
     *
     * // French ordinals: 1er, 2e, 3e...
     * PluralRules::getOrdinalCategoryName('fr', 1);  // Returns "one"   → "1er"
     * PluralRules::getOrdinalCategoryName('fr', 2);  // Returns "other" → "2e"
     * PluralRules::getOrdinalCategoryName('fr', 3);  // Returns "other" → "3e"
     *
     * // Italian ordinals: special for 8, 11, 80, 800
     * PluralRules::getOrdinalCategoryName('it', 8);  // Returns "many"  → "l'8°"
     * PluralRules::getOrdinalCategoryName('it', 11); // Returns "many"  → "l'11°"
     * PluralRules::getOrdinalCategoryName('it', 5);  // Returns "other" → "il 5°"
     *
     * // Welsh ordinals: complex system with 6 categories
     * PluralRules::getOrdinalCategoryName('cy', 0);  // Returns "zero"
     * PluralRules::getOrdinalCategoryName('cy', 1);  // Returns "one"
     * PluralRules::getOrdinalCategoryName('cy', 2);  // Returns "two"
     * PluralRules::getOrdinalCategoryName('cy', 3);  // Returns "few"
     * PluralRules::getOrdinalCategoryName('cy', 5);  // Returns "many"
     * PluralRules::getOrdinalCategoryName('cy', 10); // Returns "other"
     *
     * // Japanese: no ordinal distinction
     * PluralRules::getOrdinalCategoryName('ja', 1);  // Returns "other"
     * PluralRules::getOrdinalCategoryName('ja', 100);// Returns "other"
     * ```
     *
     * ## ICU MessageFormat Integration
     *
     * This method is useful when working with ICU selectordinal patterns:
     *
     * ```
     * {count, selectordinal,
     *     one {#st}
     *     two {#nd}
     *     few {#rd}
     *     other {#th}
     * }
     * ```
     *
     * @param string $locale The locale code (e.g., 'en', 'fr', 'it', 'en-US', 'fr_FR')
     * @param int $n The ordinal number to categorize (must be non-negative)
     * @return string The CLDR ordinal category name: 'zero', 'one', 'two', 'few', 'many', or 'other'
     *
     * @see getOrdinalFormIndex() To get the numeric index instead of the category name
     * @see getOrdinalCategories() To get all available ordinal categories for a locale
     * @see getCardinalCategoryName() For cardinal (counting) numbers
     * @see https://www.unicode.org/cldr/charts/49/supplemental/language_plural_rules.html
     */
    public static function getOrdinalCategoryName(string $locale, int $n): string
    {
        $ordinalIndex = self::getOrdinalFormIndex($locale, $n);
        $ruleGroup = self::getRuleGroup($locale, 'ordinal');

        return self::$ordinalCategoryMap[$ruleGroup][$ordinalIndex] ?? self::CATEGORY_OTHER;
    }

    /**
     * Check if a locale code has a direct entry in the rules map.
     *
     * Unlike getRuleGroup(), this does NOT fall back to the base language code.
     * It checks for an exact match only (case-insensitive).
     *
     * @param string $locale The locale code to check (e.g. "pt_PT", "pt").
     * @return bool True if the locale has its own rules entry.
     */
    public static function hasRulesFor(string $locale): bool
    {
        return isset(static::$rulesMap[strtolower($locale)]);
    }

    /**
     * Returns the plural rule group number for a given locale.
     *
     * @param string $locale The locale to get the rule group for.
     * @param string $type The type of rule to get: 'cardinal' or 'ordinal'. Default is 'cardinal'.
     * @return int The rule group number.
     */
    public static function getRuleGroup(string $locale, string $type = 'cardinal'): int
    {
        $locale = strtolower($locale);

        if (!isset(static::$rulesMap[$locale])) {
            $locale = explode('_', $locale)[0];
        }

        if (!isset(static::$rulesMap[$locale])) {
            $locale = explode('-', $locale)[0];
        }

        $rules = static::$rulesMap[$locale] ?? ['cardinal' => 0, 'ordinal' => 0];

        return $rules[$type] ?? 0;
    }

    /**
     * Returns the number of cardinal plural forms (nplurals) for a given locale.
     *
     * The nplurals value represents the total count of distinct **cardinal** plural
     * categories that a language uses to express grammatical number for quantities.
     * This value is essential for translation systems and internationalization
     * frameworks because it determines how many different translation strings are
     * needed for each pluralizable message.
     *
     * ## Cardinal vs Ordinal
     *
     * This method returns the count of **cardinal** categories (used for counting:
     * "1 item", "2 items", "5 items"), NOT ordinal categories (used for ranking:
     * "1st", "2nd", "3rd"). For ordinal categories, use {@see getOrdinalCategories()}.
     *
     * ## What nplurals means
     *
     * Different languages categorize quantities differently:
     * - Japanese (nplurals=1): Uses only one form for all quantities ("1本", "5本", "100本")
     * - English (nplurals=2): Uses two forms - singular and plural ("1 item", "2 items")
     * - Russian (nplurals=3): Uses three forms - one, few, many ("1 яблоко", "2 яблока", "5 яблок")
     * - Welsh (nplurals=6): Uses six forms - zero, one, two, few, many, other
     * - Arabic (nplurals=6): Uses six forms - zero, one, two, few, many, other
     *
     * ## Relationship with other methods
     *
     * - The nplurals value equals the count of categories returned by {@see getCardinalCategories()}
     * - The {@see getCardinalFormIndex()} method returns indices from 0 to (nplurals - 1)
     * - The {@see getCardinalCategoryName()} method maps these indices to CLDR category names
     *
     * ## Common use cases
     *
     * This value is commonly used in:
     * - GNU gettext PO/MO file headers (e.g., "Plural-Forms: nplurals=2; plural=(n != 1);")
     * - ICU MessageFormat plural rules validation
     * - Translation management systems to ensure all plural forms are provided
     * - XLIFF and other translation file formats
     *
     * ## Usage Example
     *
     * ```php
     * use Matecat\ICU\Plurals\PluralRules;
     *
     * // Check how many translations are needed for each language
     * PluralRules::getPluralCount('en'); // Returns 2 (one, other)
     * PluralRules::getPluralCount('ru'); // Returns 3 (one, few, many)
     * PluralRules::getPluralCount('ar'); // Returns 6 (zero, one, two, few, many, other)
     * PluralRules::getPluralCount('ja'); // Returns 1 (other - no plural distinction)
     *
     * // Works with locale variants
     * PluralRules::getPluralCount('en-US'); // Returns 2
     * PluralRules::getPluralCount('fr_FR'); // Returns 2
     * ```
     *
     * @param string $locale The locale code (e.g., 'en', 'ru', 'ar', 'en-US', 'fr_FR')
     * @return int The number of cardinal plural forms (1-6 depending on the language)
     *
     * @see getCardinalCategories() To get the actual cardinal category names
     * @see getOrdinalCategories() To get ordinal category names (1st, 2nd, 3rd)
     * @see getCardinalFormIndex() To determine which plural form index to use for a specific number
     * @see https://www.unicode.org/cldr/charts/48/supplemental/language_plural_rules.html
     */
    public static function getPluralCount(string $locale): int
    {
        return count(self::getCardinalCategories($locale));
    }

}

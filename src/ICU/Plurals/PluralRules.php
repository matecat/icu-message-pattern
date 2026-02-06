<?php

declare(strict_types=1);

namespace Matecat\ICU\Plurals;

use RuntimeException;

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
     * Common category arrays shared by multiple rules.
     * Using constants to avoid duplication in the categoryMap.
     */
    private const array CATEGORIES_OTHER = [self::CATEGORY_OTHER];
    private const array CATEGORIES_ONE_OTHER = [self::CATEGORY_ONE, self::CATEGORY_OTHER];
    private const array CATEGORIES_ONE_FEW_OTHER = [self::CATEGORY_ONE, self::CATEGORY_FEW, self::CATEGORY_OTHER];
    private const array CATEGORIES_ONE_FEW_MANY = [self::CATEGORY_ONE, self::CATEGORY_FEW, self::CATEGORY_MANY];
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
    protected static array $cardinalCategoryMap = [
        // Rule 0: nplurals=1; (Asian, no plural forms)
        0 => self::CATEGORIES_OTHER,

        // Rule 1: nplurals=2; plural=(n != 1); (Germanic, most European)
        1 => self::CATEGORIES_ONE_OTHER,

        // Rule 2: nplurals=2; plural=(n > 1); (Filipino, Turkish, etc.)
        2 => self::CATEGORIES_ONE_OTHER,

        // Rule 3: nplurals=3; (Slavic - Russian, Ukrainian, etc.)
        3 => self::CATEGORIES_ONE_FEW_MANY,

        // Rule 4: nplurals=3; (Czech, Slovak)
        4 => self::CATEGORIES_ONE_FEW_OTHER,

        // Rule 5: nplurals=5; (Irish)
        5 => self::CATEGORIES_ONE_TWO_FEW_MANY_OTHER,

        // Rule 6: nplurals=3; (Lithuanian)
        6 => self::CATEGORIES_ONE_FEW_OTHER,

        // Rule 7: nplurals=4; (Slovenian)
        7 => self::CATEGORIES_ONE_TWO_FEW_OTHER,

        // Rule 8: nplurals=2; (Macedonian - CLDR 48)
        8 => self::CATEGORIES_ONE_OTHER,

        // Rule 9: nplurals=4; (Maltese)
        9 => self::CATEGORIES_ONE_FEW_MANY_OTHER,

        // Rule 10: nplurals=3; (Latvian - CLDR 48)
        10 => self::CATEGORIES_ZERO_ONE_OTHER,

        // Rule 11: nplurals=3; (Polish)
        11 => self::CATEGORIES_ONE_FEW_MANY,

        // Rule 12: nplurals=3; (Romanian)
        12 => self::CATEGORIES_ONE_FEW_OTHER,

        // Rule 13: nplurals=6; (Arabic)
        13 => self::CATEGORIES_ZERO_ONE_TWO_FEW_MANY_OTHER,

        // Rule 14: nplurals=6; (Welsh)
        14 => self::CATEGORIES_ZERO_ONE_TWO_FEW_MANY_OTHER,

        // Rule 15: nplurals=2; (Icelandic)
        15 => self::CATEGORIES_ONE_OTHER,

        // Rule 16: nplurals=4; (Scottish Gaelic)
        16 => self::CATEGORIES_ONE_TWO_FEW_OTHER,

        // Rule 17: nplurals=5; (Breton - CLDR 48)
        17 => self::CATEGORIES_ONE_TWO_FEW_MANY_OTHER,

        // Rule 18: nplurals=4; (Manx - CLDR 48)
        18 => self::CATEGORIES_ONE_TWO_FEW_OTHER,

        // Rule 19: nplurals=4; (Hebrew - CLDR 48)
        19 => self::CATEGORIES_ONE_TWO_MANY_OTHER,

        // Rule 20: nplurals=3; (Italian, Spanish, French, Portuguese, Catalan - CLDR 49)
        20 => self::CATEGORIES_ONE_MANY_OTHER,
    ];

    /**
     * Additional category arrays for ordinal rules not covered by cardinal constants.
     */
    private const array CATEGORIES_MANY_OTHER = [self::CATEGORY_MANY, self::CATEGORY_OTHER];
    private const array CATEGORIES_FEW_OTHER = [self::CATEGORY_FEW, self::CATEGORY_OTHER];

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
    protected static array $ordinalCategoryMap = [
        // Rule 0: No ordinal distinction - only "other"
        0 => self::CATEGORIES_OTHER,

        // Rule 1: English-like ordinals (one/two/few/other for 1st/2nd/3rd/4th)
        1 => self::CATEGORIES_ONE_TWO_FEW_OTHER,

        // Rule 2: French-like ordinals (one/other)
        2 => self::CATEGORIES_ONE_OTHER,

        // Rules 3, 4, 6, 7, 9, 10, 11, 13, 15, 17, 18, 19: Only "other"
        3 => self::CATEGORIES_OTHER,
        4 => self::CATEGORIES_OTHER,
        5 => self::CATEGORIES_ONE_OTHER,  // Irish ordinals
        6 => self::CATEGORIES_OTHER,
        7 => self::CATEGORIES_OTHER,
        8 => self::CATEGORIES_ONE_TWO_MANY_OTHER,  // Macedonian ordinals
        9 => self::CATEGORIES_OTHER,
        10 => self::CATEGORIES_OTHER,
        11 => self::CATEGORIES_OTHER,
        12 => self::CATEGORIES_ONE_OTHER,  // Romanian ordinals
        13 => self::CATEGORIES_OTHER,
        14 => self::CATEGORIES_ZERO_ONE_TWO_FEW_MANY_OTHER,  // Welsh ordinals
        15 => self::CATEGORIES_OTHER,
        16 => self::CATEGORIES_ONE_TWO_FEW_OTHER,  // Scottish Gaelic ordinals
        17 => self::CATEGORIES_OTHER,
        18 => self::CATEGORIES_OTHER,
        19 => self::CATEGORIES_OTHER,
        20 => self::CATEGORIES_MANY_OTHER,  // Italian ordinals (many/other)

        // Rule 21: Kazakh, Azerbaijani ordinals (many/other)
        // many: n % 10 = 6 or n % 10 = 9 or n % 10 = 0 and n != 0
        21 => self::CATEGORIES_MANY_OTHER,

        // Rule 22: Hungarian, Ukrainian, Turkmen ordinals (few/other)
        // few: n = 1 or n = 5
        22 => self::CATEGORIES_FEW_OTHER,

        // Rule 23: Bengali, Assamese, Hindi ordinals (one/other)
        // one: n = 1,5,7,8,9,10
        23 => self::CATEGORIES_ONE_OTHER,

        // Rule 24: Gujarati ordinals (one/two/few/many/other)
        24 => self::CATEGORIES_ONE_TWO_FEW_MANY_OTHER,

        // Rule 25: Kannada ordinals (one/two/few/other)
        25 => self::CATEGORIES_ONE_TWO_FEW_OTHER,

        // Rule 26: Marathi ordinals (one/other)
        26 => self::CATEGORIES_ONE_OTHER,

        // Rule 27: Odia ordinals (one/two/few/many/other)
        27 => self::CATEGORIES_ONE_TWO_FEW_MANY_OTHER,

        // Rule 28: Telugu ordinals (one/two/many/other)
        // not exactly same as 8 but close
        28 => self::CATEGORIES_ONE_TWO_MANY_OTHER,

        // Rule 29: Nepali ordinals (one/few/other)
        29 => self::CATEGORIES_ONE_FEW_OTHER,

        // Rule 30: Albanian ordinals (one/two/few/other)
        30 => self::CATEGORIES_ONE_TWO_FEW_OTHER,
    ];

    /**
     * A map of the locale => plurals group used to determine
     * which plural rules apply to the language
     *
     * Plural Rules (Cardinal):
     * 0  - nplurals=1; plural=0; (Asian, no plural forms)
     * 1  - nplurals=2; plural=(n != 1); (Germanic, most European)
     * 2  - nplurals=2; plural=(n > 1); (French, Brazilian Portuguese)
     * 3  - nplurals=3; plural=(n%10==1 && n%100!=11 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2); (Slavic)
     * 4  - nplurals=3; plural=(n==1) ? 0 : (n>=2 && n<=4) ? 1 : 2; (Czech, Slovak)
     * 5  - nplurals=5; plural=n==1 ? 0 : n==2 ? 1 : (n>2 && n<7) ? 2 :(n>6 && n<11) ? 3 : 4; (Irish)
     * 6  - nplurals=3; plural=(n%10==1 && n%100!=11 ? 0 : n%10>=2 && (n%100<10 || n%100>=20) ? 1 : 2); (Lithuanian)
     * 7  - nplurals=4; plural=(n%100==1 ? 0 : n%100==2 ? 1 : n%100==3 || n%100==4 ? 2 : 3); (Slovenian)
     * 8  - nplurals=2; plural=(n%10==1 && n%100!=11) ? 0 : 1; (Macedonian - CLDR 48)
     * 9  - nplurals=4; plural=(n==1 ? 0 : n==0 || (n%100>0 && n%100<=10) ? 1 : (n%100>10 && n%100<20) ? 2 : 3); (Maltese)
     * 10 - nplurals=3; plural=(n%10==1 && n%100!=11 ? 0 : n != 0 ? 1 : 2); (Latvian)
     * 11 - nplurals=3; plural=(n==1 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2); (Polish)
     * 12 - nplurals=3; plural=(n==1 ? 0 : n==0 || n%100>0 && n%100<20 ? 1 : 2); (Romanian)
     * 13 - nplurals=6; plural=(n==0 ? 0 : n==1 ? 1 : n==2 ? 2 : n%100>=3 && n%100<=10 ? 3 : n%100>=11 ? 4 : 5); (Arabic)
     * 14 - nplurals=6; plural=(n==0) ? 0 : (n==1) ? 1 : (n==2) ? 2 : (n==3) ? 3 : (n==6) ? 4 : 5; (Welsh - CLDR 48)
     * 15 - nplurals=2; plural=(n%10!=1 || n%100==11); (Icelandic)
     * 16 - nplurals=4; plural=(n==1 || n==11) ? 0 : (n==2 || n==12) ? 1 : (n>2 && n<20) ? 2 : 3; (Scottish Gaelic)
     * 17 - nplurals=4; plural=(n==1) ? 0 : (n==2) ? 1 : (n==3) ? 2 : 3; (Breton - CLDR 48)
     * 18 - nplurals=4; plural=(n%10==1) ? 0 : (n%10==2) ? 1 : (n%20==0) ? 2 : 3; (Manx)
     * 19 - nplurals=4; plural=(n==1) ? 0 : (n==2) ? 1 : (n>10 && n%10==0) ? 2 : 3; (Hebrew - CLDR 48)
     * 20 - nplurals=3; plural=(n==1) ? 0 : (n!=0 && n%1000000==0) ? 1 : 2; (Italian, Spanish, French, Portuguese, Catalan - CLDR 49)
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
     * 9  - Only "other" (Maltese)
     * 10 - Only "other" (Latvian)
     * 11 - Only "other" (Polish)
     * 12 - one/other (Romanian)
     * 13 - Only "other" (Arabic)
     * 14 - zero/one/two/few/many/other (Welsh)
     * 15 - Only "other" (Icelandic)
     * 16 - one/two/few/other (Scottish Gaelic)
     * 17 - Only "other" (Breton)
     * 18 - Only "other" (Manx)
     * 19 - Only "other" (Hebrew)
     * 20 - many/other (Italian-like ordinals)
     * 21 - many/other (Kazakh, Azerbaijani ordinals: n%10=6,9 or n%10=0 && n!=0)
     * 22 - few/other (Hungarian ordinals: n=1,5)
     * 23 - one/other (Bengali, Assamese ordinals: n=1,5,7,8,9,10)
     * 24 - one/two/few/many/other (Gujarati ordinals)
     * 25 - one/two/few/other (Kannada ordinals)
     * 26 - one/other (Marathi ordinals: n=1)
     * 27 - one/two/few/many/other (Odia ordinals)
     * 28 - one/two/many/other (Telugu ordinals)
     * 29 - one/few/other (Nepali ordinals)
     * 30 - one/two/few/other (Albanian ordinals)
     *
     * @var array<string, array{cardinal: int, ordinal: int}>
     */
    protected static array $rulesMap = [
        // A
        'ace' => ['cardinal' => 0, 'ordinal' => 0],   // Acehnese - no plural
        'acf' => ['cardinal' => 2, 'ordinal' => 0],   // Saint Lucian Creole French
        'af' => ['cardinal' => 1, 'ordinal' => 0],    // Afrikaans
        'aig' => ['cardinal' => 1, 'ordinal' => 0],   // Antigua and Barbuda Creole English
        'ak' => ['cardinal' => 2, 'ordinal' => 0],    // Akan
        'als' => ['cardinal' => 1, 'ordinal' => 0],   // Albanian (Tosk)
        'am' => ['cardinal' => 2, 'ordinal' => 0],    // Amharic
        'an' => ['cardinal' => 1, 'ordinal' => 0],    // Aragonese
        'ar' => ['cardinal' => 13, 'ordinal' => 0],   // Arabic
        'as' => ['cardinal' => 1, 'ordinal' => 23],   // Assamese - CLDR 49 ordinal: one/other
        'ast' => ['cardinal' => 1, 'ordinal' => 0],   // Asturian
        'awa' => ['cardinal' => 1, 'ordinal' => 0],   // Awadhi
        'ayr' => ['cardinal' => 0, 'ordinal' => 0],   // Central Aymara
        'az' => ['cardinal' => 1, 'ordinal' => 21],   // Azerbaijani - CLDR 49 ordinal: many/other
        'azb' => ['cardinal' => 1, 'ordinal' => 21],  // South Azerbaijani
        'azj' => ['cardinal' => 1, 'ordinal' => 21],  // North Azerbaijani

        // B
        'ba' => ['cardinal' => 0, 'ordinal' => 0],    // Bashkir
        'bah' => ['cardinal' => 1, 'ordinal' => 0],   // Bahamas Creole English
        'bal' => ['cardinal' => 1, 'ordinal' => 0],   // Baluchi
        'ban' => ['cardinal' => 0, 'ordinal' => 0],   // Balinese
        'be' => ['cardinal' => 3, 'ordinal' => 0],    // Belarusian
        'bem' => ['cardinal' => 1, 'ordinal' => 0],   // Bemba
        'bg' => ['cardinal' => 1, 'ordinal' => 0],    // Bulgarian
        'bh' => ['cardinal' => 2, 'ordinal' => 0],    // Bihari
        'bho' => ['cardinal' => 1, 'ordinal' => 0],   // Bhojpuri
        'bi' => ['cardinal' => 0, 'ordinal' => 0],    // Bislama
        'bjn' => ['cardinal' => 0, 'ordinal' => 0],   // Banjar
        'bjs' => ['cardinal' => 1, 'ordinal' => 0],   // Bajan
        'bm' => ['cardinal' => 0, 'ordinal' => 0],    // Bambara
        'bn' => ['cardinal' => 1, 'ordinal' => 23],   // Bengali - CLDR 49 ordinal: one/other
        'bo' => ['cardinal' => 0, 'ordinal' => 0],    // Tibetan
        'br' => ['cardinal' => 17, 'ordinal' => 0],   // Breton
        'brx' => ['cardinal' => 1, 'ordinal' => 0],   // Bodo
        'bs' => ['cardinal' => 3, 'ordinal' => 0],    // Bosnian
        'bug' => ['cardinal' => 0, 'ordinal' => 0],   // Buginese

        // C
        'ca' => ['cardinal' => 20, 'ordinal' => 2],   // Catalan - CLDR 49
        'cac' => ['cardinal' => 1, 'ordinal' => 0],   // Chuj
        'cav' => ['cardinal' => 20, 'ordinal' => 2],  // Catalan (Valencia) - CLDR 49
        'ce' => ['cardinal' => 1, 'ordinal' => 0],    // Chechen
        'ceb' => ['cardinal' => 1, 'ordinal' => 0],   // Cebuano
        'ch' => ['cardinal' => 0, 'ordinal' => 0],    // Chamorro
        'chk' => ['cardinal' => 0, 'ordinal' => 0],   // Chuukese
        'chr' => ['cardinal' => 1, 'ordinal' => 0],   // Cherokee
        'cjk' => ['cardinal' => 1, 'ordinal' => 0],   // Chokwe
        'ckb' => ['cardinal' => 1, 'ordinal' => 0],   // Central Kurdish
        'cop' => ['cardinal' => 1, 'ordinal' => 0],   // Coptic
        'crh' => ['cardinal' => 0, 'ordinal' => 0],   // Crimean Tatar
        'crs' => ['cardinal' => 2, 'ordinal' => 0],   // Seselwa Creole French
        'cs' => ['cardinal' => 4, 'ordinal' => 0],    // Czech
        'ctg' => ['cardinal' => 1, 'ordinal' => 0],   // Chittagonian
        'cy' => ['cardinal' => 14, 'ordinal' => 14],  // Welsh - CLDR 49 ordinal: zero/one/two/few/many/other

        // D
        'da' => ['cardinal' => 1, 'ordinal' => 0],    // Danish
        'de' => ['cardinal' => 1, 'ordinal' => 0],    // German
        'dik' => ['cardinal' => 1, 'ordinal' => 0],   // Southwestern Dinka
        'diq' => ['cardinal' => 1, 'ordinal' => 0],   // Dimli
        'doi' => ['cardinal' => 1, 'ordinal' => 0],   // Dogri
        'dv' => ['cardinal' => 1, 'ordinal' => 0],    // Divehi
        'dyu' => ['cardinal' => 0, 'ordinal' => 0],   // Dyula
        'dz' => ['cardinal' => 0, 'ordinal' => 0],    // Dzongkha

        // E
        'ee' => ['cardinal' => 1, 'ordinal' => 0],    // Ewe
        'el' => ['cardinal' => 1, 'ordinal' => 0],    // Greek
        'en' => ['cardinal' => 1, 'ordinal' => 1],    // English - CLDR 49 ordinal: one/two/few/other
        'eo' => ['cardinal' => 1, 'ordinal' => 0],    // Esperanto
        'es' => ['cardinal' => 20, 'ordinal' => 0],   // Spanish - CLDR 49
        'et' => ['cardinal' => 1, 'ordinal' => 0],    // Estonian
        'eu' => ['cardinal' => 1, 'ordinal' => 0],    // Basque

        // F
        'fa' => ['cardinal' => 2, 'ordinal' => 0],    // Persian
        'ff' => ['cardinal' => 1, 'ordinal' => 0],    // Fulah
        'fi' => ['cardinal' => 1, 'ordinal' => 0],    // Finnish
        'fil' => ['cardinal' => 2, 'ordinal' => 2],   // Filipino - CLDR 49 ordinal: one/other
        'fj' => ['cardinal' => 0, 'ordinal' => 0],    // Fijian
        'fn' => ['cardinal' => 0, 'ordinal' => 0],    // Fanagalo
        'fo' => ['cardinal' => 1, 'ordinal' => 0],    // Faroese
        'fon' => ['cardinal' => 0, 'ordinal' => 0],   // Fon
        'fr' => ['cardinal' => 20, 'ordinal' => 2],   // French - CLDR 49 ordinal: one/other
        'fuc' => ['cardinal' => 1, 'ordinal' => 0],   // Pulaar
        'fur' => ['cardinal' => 1, 'ordinal' => 0],   // Friulian
        'fuv' => ['cardinal' => 1, 'ordinal' => 0],   // Nigerian Fulfulde

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
        'gu' => ['cardinal' => 1, 'ordinal' => 24],   // Gujarati - CLDR 49 ordinal: one/two/few/many/other
        'guz' => ['cardinal' => 1, 'ordinal' => 0],   // Gusii
        'gv' => ['cardinal' => 18, 'ordinal' => 0],   // Manx
        'gyn' => ['cardinal' => 1, 'ordinal' => 0],   // Guyanese Creole English

        // H
        'ha' => ['cardinal' => 1, 'ordinal' => 0],    // Hausa
        'haw' => ['cardinal' => 1, 'ordinal' => 0],   // Hawaiian
        'he' => ['cardinal' => 19, 'ordinal' => 0],   // Hebrew
        'hi' => ['cardinal' => 2, 'ordinal' => 23],   // Hindi - CLDR 49 ordinal: one/other
        'hig' => ['cardinal' => 1, 'ordinal' => 0],   // Kamwe
        'hil' => ['cardinal' => 1, 'ordinal' => 0],   // Hiligaynon
        'hmn' => ['cardinal' => 0, 'ordinal' => 0],   // Hmong
        'hne' => ['cardinal' => 1, 'ordinal' => 0],   // Chhattisgarhi
        'hoc' => ['cardinal' => 1, 'ordinal' => 0],   // Ho
        'hr' => ['cardinal' => 3, 'ordinal' => 0],    // Croatian
        'ht' => ['cardinal' => 1, 'ordinal' => 0],    // Haitian Creole
        'hu' => ['cardinal' => 1, 'ordinal' => 22],   // Hungarian - CLDR 49 ordinal: few/other
        'hy' => ['cardinal' => 1, 'ordinal' => 2],    // Armenian - CLDR 49 ordinal: one/other

        // I
        'id' => ['cardinal' => 0, 'ordinal' => 0],    // Indonesian
        'ig' => ['cardinal' => 0, 'ordinal' => 0],    // Igbo
        'ilo' => ['cardinal' => 1, 'ordinal' => 0],   // Ilocano
        'is' => ['cardinal' => 15, 'ordinal' => 0],   // Icelandic
        'it' => ['cardinal' => 20, 'ordinal' => 20],  // Italian - CLDR 49 ordinal: many/other

        // J
        'ja' => ['cardinal' => 0, 'ordinal' => 0],    // Japanese
        'jam' => ['cardinal' => 1, 'ordinal' => 0],   // Jamaican Creole English
        'jv' => ['cardinal' => 0, 'ordinal' => 0],    // Javanese

        // K
        'ka' => ['cardinal' => 0, 'ordinal' => 21],   // Georgian - CLDR 49 ordinal: many/other
        'kab' => ['cardinal' => 2, 'ordinal' => 0],   // Kabyle
        'kac' => ['cardinal' => 0, 'ordinal' => 0],   // Kachin
        'kam' => ['cardinal' => 1, 'ordinal' => 0],   // Kamba
        'kar' => ['cardinal' => 0, 'ordinal' => 0],   // Karen
        'kas' => ['cardinal' => 1, 'ordinal' => 0],   // Kashmiri
        'kbp' => ['cardinal' => 0, 'ordinal' => 0],   // Kabiyè
        'kea' => ['cardinal' => 0, 'ordinal' => 0],   // Kabuverdianu
        'kg' => ['cardinal' => 1, 'ordinal' => 0],    // Kongo
        'kha' => ['cardinal' => 1, 'ordinal' => 0],   // Khasi
        'khk' => ['cardinal' => 1, 'ordinal' => 0],   // Halh Mongolian
        'ki' => ['cardinal' => 1, 'ordinal' => 0],    // Kikuyu
        'kjb' => ['cardinal' => 1, 'ordinal' => 0],   // Q'anjob'al
        'kk' => ['cardinal' => 1, 'ordinal' => 21],   // Kazakh - CLDR 49 ordinal: many/other
        'kl' => ['cardinal' => 1, 'ordinal' => 0],    // Greenlandic
        'kln' => ['cardinal' => 1, 'ordinal' => 0],   // Kalenjin
        'km' => ['cardinal' => 0, 'ordinal' => 0],    // Khmer
        'kmb' => ['cardinal' => 1, 'ordinal' => 0],   // Kimbundu
        'kmr' => ['cardinal' => 1, 'ordinal' => 0],   // Northern Kurdish
        'kn' => ['cardinal' => 1, 'ordinal' => 25],   // Kannada - CLDR 49 ordinal: one/two/few/other
        'knc' => ['cardinal' => 1, 'ordinal' => 0],   // Central Kanuri
        'ko' => ['cardinal' => 0, 'ordinal' => 0],    // Korean
        'kok' => ['cardinal' => 1, 'ordinal' => 0],   // Konkani
        'kr' => ['cardinal' => 0, 'ordinal' => 0],    // Kanuri
        'ks' => ['cardinal' => 1, 'ordinal' => 0],    // Kashmiri
        'ksw' => ['cardinal' => 0, 'ordinal' => 0],   // S'gaw Karen
        'ky' => ['cardinal' => 1, 'ordinal' => 0],    // Kyrgyz

        // L
        'la' => ['cardinal' => 1, 'ordinal' => 0],    // Latin
        'lb' => ['cardinal' => 1, 'ordinal' => 0],    // Luxembourgish
        'lg' => ['cardinal' => 1, 'ordinal' => 0],    // Ganda
        'li' => ['cardinal' => 1, 'ordinal' => 0],    // Limburgish
        'lij' => ['cardinal' => 1, 'ordinal' => 0],   // Ligurian
        'lmo' => ['cardinal' => 1, 'ordinal' => 0],   // Lombard
        'ln' => ['cardinal' => 2, 'ordinal' => 0],    // Lingala
        'lo' => ['cardinal' => 0, 'ordinal' => 2],    // Lao - CLDR 49 ordinal: one/other
        'lt' => ['cardinal' => 6, 'ordinal' => 0],    // Lithuanian
        'ltg' => ['cardinal' => 10, 'ordinal' => 0],  // Latgalian
        'lua' => ['cardinal' => 1, 'ordinal' => 0],   // Luba-Lulua
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
        'men' => ['cardinal' => 1, 'ordinal' => 0],   // Mende
        'mer' => ['cardinal' => 1, 'ordinal' => 0],   // Meru
        'mfe' => ['cardinal' => 2, 'ordinal' => 0],   // Mauritian Creole
        'mfi' => ['cardinal' => 1, 'ordinal' => 0],   // Wandala
        'mfv' => ['cardinal' => 1, 'ordinal' => 0],   // Mandjak
        'mg' => ['cardinal' => 2, 'ordinal' => 0],    // Malagasy
        'mh' => ['cardinal' => 0, 'ordinal' => 0],    // Marshallese
        'mhr' => ['cardinal' => 1, 'ordinal' => 0],   // Eastern Mari
        'mi' => ['cardinal' => 2, 'ordinal' => 0],    // Maori
        'min' => ['cardinal' => 0, 'ordinal' => 0],   // Minangkabau
        'mk' => ['cardinal' => 8, 'ordinal' => 8],    // Macedonian - CLDR 49 ordinal: one/two/many/other
        'ml' => ['cardinal' => 1, 'ordinal' => 0],    // Malayalam
        'mn' => ['cardinal' => 1, 'ordinal' => 0],    // Mongolian
        'mni' => ['cardinal' => 1, 'ordinal' => 0],   // Manipuri
        'mnk' => ['cardinal' => 1, 'ordinal' => 0],   // Mandinka
        'mos' => ['cardinal' => 0, 'ordinal' => 0],   // Mossi
        'mr' => ['cardinal' => 1, 'ordinal' => 26],   // Marathi - CLDR 49 ordinal: one/other
        'mrj' => ['cardinal' => 1, 'ordinal' => 0],   // Western Mari
        'mrt' => ['cardinal' => 1, 'ordinal' => 0],   // Marghi Central
        'ms' => ['cardinal' => 0, 'ordinal' => 2],    // Malay - CLDR 49 ordinal: one/other
        'mt' => ['cardinal' => 9, 'ordinal' => 0],    // Maltese
        'my' => ['cardinal' => 0, 'ordinal' => 0],    // Burmese

        // N
        'nb' => ['cardinal' => 1, 'ordinal' => 0],    // Norwegian Bokmål
        'nd' => ['cardinal' => 1, 'ordinal' => 0],    // North Ndebele
        'ndc' => ['cardinal' => 1, 'ordinal' => 0],   // Ndau
        'ne' => ['cardinal' => 1, 'ordinal' => 29],   // Nepali - CLDR 49 ordinal: one/few/other
        'niu' => ['cardinal' => 0, 'ordinal' => 0],   // Niuean
        'nl' => ['cardinal' => 1, 'ordinal' => 0],    // Dutch
        'nn' => ['cardinal' => 1, 'ordinal' => 0],    // Norwegian Nynorsk
        'nr' => ['cardinal' => 1, 'ordinal' => 0],    // South Ndebele
        'nso' => ['cardinal' => 2, 'ordinal' => 0],   // Northern Sotho
        'nup' => ['cardinal' => 1, 'ordinal' => 0],   // Nupe
        'nus' => ['cardinal' => 1, 'ordinal' => 0],   // Nuer
        'ny' => ['cardinal' => 1, 'ordinal' => 0],    // Nyanja (Chichewa)
        'nyf' => ['cardinal' => 1, 'ordinal' => 0],   // Giryama

        // O
        'oc' => ['cardinal' => 2, 'ordinal' => 0],    // Occitan
        'om' => ['cardinal' => 1, 'ordinal' => 0],    // Oromo
        'or' => ['cardinal' => 1, 'ordinal' => 27],   // Odia - CLDR 49 ordinal: one/two/few/many/other
        'ory' => ['cardinal' => 1, 'ordinal' => 27],  // Odia (Oriya)

        // P
        'pa' => ['cardinal' => 1, 'ordinal' => 0],    // Punjabi
        'pag' => ['cardinal' => 1, 'ordinal' => 0],   // Pangasinan
        'pap' => ['cardinal' => 1, 'ordinal' => 0],   // Papiamento
        'pau' => ['cardinal' => 0, 'ordinal' => 0],   // Palauan
        'pbt' => ['cardinal' => 1, 'ordinal' => 0],   // Southern Pashto
        'pi' => ['cardinal' => 1, 'ordinal' => 0],    // Pali
        'pis' => ['cardinal' => 0, 'ordinal' => 0],   // Pijin
        'pko' => ['cardinal' => 1, 'ordinal' => 0],   // Pökoot
        'pl' => ['cardinal' => 11, 'ordinal' => 0],   // Polish
        'plt' => ['cardinal' => 2, 'ordinal' => 0],   // Plateau Malagasy
        'pon' => ['cardinal' => 0, 'ordinal' => 0],   // Pohnpeian
        'pot' => ['cardinal' => 1, 'ordinal' => 0],   // Potawatomi
        'pov' => ['cardinal' => 1, 'ordinal' => 0],   // Guinea-Bissau Creole
        'ppk' => ['cardinal' => 0, 'ordinal' => 0],   // Uma
        'prs' => ['cardinal' => 2, 'ordinal' => 0],   // Dari
        'ps' => ['cardinal' => 1, 'ordinal' => 0],    // Pashto
        'pt' => ['cardinal' => 20, 'ordinal' => 0],   // Portuguese - CLDR 49

        // Q
        'qu' => ['cardinal' => 1, 'ordinal' => 0],    // Quechua
        'quc' => ['cardinal' => 1, 'ordinal' => 0],   // K'iche'
        'quy' => ['cardinal' => 1, 'ordinal' => 0],   // Ayacucho Quechua

        // R
        'rhg' => ['cardinal' => 1, 'ordinal' => 0],   // Rohingya
        'rhl' => ['cardinal' => 1, 'ordinal' => 0],   // Rohingya (alternate)
        'rmn' => ['cardinal' => 3, 'ordinal' => 0],   // Balkan Romani
        'rmo' => ['cardinal' => 1, 'ordinal' => 0],   // Sinte Romani
        'rn' => ['cardinal' => 1, 'ordinal' => 0],    // Rundi
        'ro' => ['cardinal' => 12, 'ordinal' => 2],   // Romanian - CLDR 49 ordinal: one/other
        'roh' => ['cardinal' => 1, 'ordinal' => 0],   // Romansh
        'ru' => ['cardinal' => 3, 'ordinal' => 0],    // Russian
        'run' => ['cardinal' => 1, 'ordinal' => 0],   // Rundi (alternate)
        'rw' => ['cardinal' => 1, 'ordinal' => 0],    // Kinyarwanda

        // S
        'sa' => ['cardinal' => 1, 'ordinal' => 0],    // Sanskrit
        'sat' => ['cardinal' => 1, 'ordinal' => 0],   // Santali
        'sc' => ['cardinal' => 1, 'ordinal' => 20],   // Sardinian - CLDR 49 ordinal: many/other
        'scn' => ['cardinal' => 1, 'ordinal' => 0],   // Sicilian
        'sd' => ['cardinal' => 1, 'ordinal' => 0],    // Sindhi
        'seh' => ['cardinal' => 1, 'ordinal' => 0],   // Sena
        'sg' => ['cardinal' => 0, 'ordinal' => 0],    // Sango
        'sh' => ['cardinal' => 3, 'ordinal' => 0],    // Serbo-Croatian
        'shn' => ['cardinal' => 0, 'ordinal' => 0],   // Shan
        'shu' => ['cardinal' => 13, 'ordinal' => 0],  // Chadian Arabic
        'si' => ['cardinal' => 1, 'ordinal' => 0],    // Sinhala
        'sk' => ['cardinal' => 4, 'ordinal' => 0],    // Slovak
        'sl' => ['cardinal' => 7, 'ordinal' => 0],    // Slovenian
        'sm' => ['cardinal' => 0, 'ordinal' => 0],    // Samoan
        'sn' => ['cardinal' => 1, 'ordinal' => 0],    // Shona
        'snk' => ['cardinal' => 1, 'ordinal' => 0],   // Soninke
        'so' => ['cardinal' => 1, 'ordinal' => 0],    // Somali
        'sq' => ['cardinal' => 1, 'ordinal' => 30],   // Albanian - CLDR 49 ordinal: one/two/few/other
        'sr' => ['cardinal' => 3, 'ordinal' => 0],    // Serbian
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
        'szl' => ['cardinal' => 11, 'ordinal' => 0],  // Silesian

        // T
        'ta' => ['cardinal' => 1, 'ordinal' => 0],    // Tamil
        'taq' => ['cardinal' => 0, 'ordinal' => 0],   // Tamasheq
        'te' => ['cardinal' => 1, 'ordinal' => 28],   // Telugu - CLDR 49 ordinal: one/two/many/other
        'tet' => ['cardinal' => 1, 'ordinal' => 0],   // Tetum
        'tg' => ['cardinal' => 2, 'ordinal' => 0],    // Tajik
        'th' => ['cardinal' => 0, 'ordinal' => 0],    // Thai
        'ti' => ['cardinal' => 2, 'ordinal' => 0],    // Tigrinya
        'tiv' => ['cardinal' => 1, 'ordinal' => 0],   // Tiv
        'tk' => ['cardinal' => 1, 'ordinal' => 22],   // Turkmen - CLDR 49 ordinal: few/other
        'tkl' => ['cardinal' => 0, 'ordinal' => 0],   // Tokelau
        'tl' => ['cardinal' => 2, 'ordinal' => 2],    // Tagalog - same as Filipino
        'tmh' => ['cardinal' => 0, 'ordinal' => 0],   // Tamashek
        'tn' => ['cardinal' => 1, 'ordinal' => 0],    // Tswana
        'to' => ['cardinal' => 0, 'ordinal' => 0],    // Tongan
        'tpi' => ['cardinal' => 0, 'ordinal' => 0],   // Tok Pisin
        'tr' => ['cardinal' => 2, 'ordinal' => 0],    // Turkish
        'trv' => ['cardinal' => 0, 'ordinal' => 0],   // Taroko
        'ts' => ['cardinal' => 1, 'ordinal' => 0],    // Tsonga
        'tsc' => ['cardinal' => 1, 'ordinal' => 0],   // Tswa
        'tt' => ['cardinal' => 0, 'ordinal' => 0],    // Tatar
        'tum' => ['cardinal' => 1, 'ordinal' => 0],   // Tumbuka
        'tvl' => ['cardinal' => 0, 'ordinal' => 0],   // Tuvalu
        'tw' => ['cardinal' => 2, 'ordinal' => 0],    // Twi
        'ty' => ['cardinal' => 0, 'ordinal' => 0],    // Tahitian
        'tzm' => ['cardinal' => 2, 'ordinal' => 0],   // Central Atlas Tamazight

        // U
        'udm' => ['cardinal' => 1, 'ordinal' => 0],   // Udmurt
        'ug' => ['cardinal' => 0, 'ordinal' => 0],    // Uyghur
        'uk' => ['cardinal' => 3, 'ordinal' => 22],   // Ukrainian - CLDR 49 ordinal: few/other
        'umb' => ['cardinal' => 1, 'ordinal' => 0],   // Umbundu
        'ur' => ['cardinal' => 1, 'ordinal' => 0],    // Urdu
        'uz' => ['cardinal' => 2, 'ordinal' => 0],    // Uzbek
        'uzn' => ['cardinal' => 2, 'ordinal' => 0],   // Northern Uzbek

        // V
        'vec' => ['cardinal' => 1, 'ordinal' => 0],   // Venetian
        'vi' => ['cardinal' => 0, 'ordinal' => 2],    // Vietnamese - CLDR 49 ordinal: one/other
        'vic' => ['cardinal' => 1, 'ordinal' => 0],   // Virgin Islands Creole English
        'vls' => ['cardinal' => 1, 'ordinal' => 0],   // Vlaams (West Flemish)
        'vmw' => ['cardinal' => 1, 'ordinal' => 0],   // Makhuwa

        // W
        'war' => ['cardinal' => 1, 'ordinal' => 0],   // Waray
        'wls' => ['cardinal' => 0, 'ordinal' => 0],   // Wallisian
        'wo' => ['cardinal' => 0, 'ordinal' => 0],    // Wolof

        // X
        'xh' => ['cardinal' => 1, 'ordinal' => 0],    // Xhosa

        // Y
        'ydd' => ['cardinal' => 1, 'ordinal' => 0],   // Eastern Yiddish
        'yi' => ['cardinal' => 1, 'ordinal' => 0],    // Yiddish
        'yo' => ['cardinal' => 1, 'ordinal' => 0],    // Yoruba

        // Z
        'zdj' => ['cardinal' => 1, 'ordinal' => 0],   // Ngazidja Comorian
        'zh' => ['cardinal' => 0, 'ordinal' => 0],    // Chinese
        'zsm' => ['cardinal' => 0, 'ordinal' => 2],   // Standard Malay - CLDR 49 ordinal: one/other
        'zu' => ['cardinal' => 1, 'ordinal' => 0],    // Zulu
    ];

    /**
     * Returns the plural form number for the passed locale corresponding
     * to the countable provided in $n.
     *
     * @param string $locale The locale to get the rule calculated for.
     * @param int $n The number to apply the rules to.
     * @return int The plural rule number that should be used.
     * @link https://docs.translatehouse.org/projects/localization-guide/en/latest/l10n/pluralforms.html
     * @link https://developer.mozilla.org/en-US/docs/Mozilla/Localization/Localization_and_Plurals#List_of_Plural_Rules
     */
    public static function calculate(string $locale, int $n): int
    {
        $ruleGroup = self::getRuleGroup($locale, 'cardinal');

        return match ($ruleGroup) {
            // nplurals=1; plural=0; (Asian, no plural forms)
            0 => 0,

            // nplurals=2; plural=(n != 1); (Germanic, most European)
            1 => $n === 1 ? 0 : 1,

            // nplurals=2; plural=(n > 1); (French, Brazilian Portuguese)
            2 => $n > 1 ? 1 : 0,

            // nplurals=3; Slavic (Russian, Ukrainian, Belarusian, Serbian, Croatian)
            3 => $n % 10 === 1 && $n % 100 !== 11 ? 0
                : (($n % 10 >= 2 && $n % 10 <= 4) && ($n % 100 < 10 || $n % 100 >= 20) ? 1 : 2),

            // nplurals=3; (Czech, Slovak)
            4 => $n === 1 ? 0 : ($n >= 2 && $n <= 4 ? 1 : 2),

            // nplurals=5; (Irish)
            5 => $n === 1 ? 0 : ($n === 2 ? 1 : ($n < 7 ? 2 : ($n < 11 ? 3 : 4))),

            // nplurals=3; (Lithuanian)
            6 => $n % 10 === 1 && $n % 100 !== 11 ? 0
                : ($n % 10 >= 2 && ($n % 100 < 10 || $n % 100 >= 20) ? 1 : 2),

            // nplurals=4; (Slovenian)
            7 => $n % 100 === 1 ? 0 : ($n % 100 === 2 ? 1 : ($n % 100 === 3 || $n % 100 === 4 ? 2 : 3)),

            // nplurals=2; (Macedonian - CLDR 48)
            8 => ($n % 10 === 1 && $n % 100 !== 11) ? 0 : 1,

            // nplurals=4; (Maltese)
            9 => $n === 1 ? 0 : ($n === 0 || ($n % 100 > 0 && $n % 100 <= 10) ? 1 : ($n % 100 > 10 && $n % 100 < 20 ? 2 : 3)),

            // nplurals=3; (Latvian - CLDR 48)
            10 => $n === 0 ? 0 : (($n % 10 === 1 && $n % 100 !== 11) ? 1 : 2),

            // nplurals=3; (Polish)
            11 => $n === 1 ? 0 : ($n % 10 >= 2 && $n % 10 <= 4 && ($n % 100 < 10 || $n % 100 >= 20) ? 1 : 2),

            // nplurals=3; (Romanian)
            12 => $n === 1 ? 0 : ($n === 0 || ($n % 100 > 0 && $n % 100 < 20) ? 1 : 2),

            // nplurals=6; (Arabic)
            13 => $n === 0 ? 0 : ($n === 1 ? 1 : ($n === 2 ? 2 : ($n % 100 >= 3 && $n % 100 <= 10 ? 3 : ($n % 100 >= 11 ? 4 : 5)))),

            // nplurals=6; (Welsh - CLDR 48)
            14 => match ($n) {
                0 => 0,
                1 => 1,
                2 => 2,
                3 => 3,
                6 => 4,
                default => 5,
            },

            // nplurals=2; (Icelandic)
            15 => $n % 10 !== 1 || $n % 100 === 11 ? 1 : 0,

            // nplurals=4; (Scottish Gaelic)
            16 => ($n === 1 || $n === 11) ? 0 : (($n === 2 || $n === 12) ? 1 : (($n > 2 && $n < 20) ? 2 : 3)),

            // nplurals=5; (Breton - CLDR 48)
            17 => self::calculateBreton($n),

            // nplurals=4; (Manx - CLDR 48)
            18 => match (true) {
                $n % 10 === 1 => 0,
                $n % 10 === 2 => 1,
                $n % 20 === 0 => 2,
                default => 3,
            },

            // nplurals=4; (Hebrew - CLDR 48)
            19 => match (true) {
                $n === 1 => 0,
                $n === 2 => 1,
                $n > 10 && $n % 10 === 0 => 2,
                default => 3,
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

            // @codeCoverageIgnoreStart
            default => throw new RuntimeException('Unable to find plural rule number.'),
            // @codeCoverageIgnoreEnd
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
     * PluralRules::getCategoryName('en', 1);  // Returns "one"
     * PluralRules::getCategoryName('en', 2);  // Returns "other"
     *
     * // Arabic
     * PluralRules::getCategoryName('ar', 0);  // Returns "zero"
     * PluralRules::getCategoryName('ar', 1);  // Returns "one"
     * PluralRules::getCategoryName('ar', 2);  // Returns "two"
     * PluralRules::getCategoryName('ar', 5);  // Returns "few"
     * PluralRules::getCategoryName('ar', 11); // Returns "many"
     * PluralRules::getCategoryName('ar', 100);// Returns "other"
     *
     * // Russian
     * PluralRules::getCategoryName('ru', 1);  // Returns "one"
     * PluralRules::getCategoryName('ru', 2);  // Returns "few"
     * PluralRules::getCategoryName('ru', 5);  // Returns "many"
     * ```
     *
     * @param string $locale The locale to get the category for.
     * @param int $n The number to apply the rules to.
     * @return string The CLDR plural category name.
     */
    public static function getCardinalCategoryName(string $locale, int $n): string
    {
        $pluralIndex = self::calculate($locale, $n);
        $ruleGroup = self::getRuleGroup($locale, 'cardinal');

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
        $ruleGroup = self::getRuleGroup($locale, 'cardinal');

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
     * // Returns ['other'] - Russian uses same form for all ordinals
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
     * Returns the plural rule group number for a given locale.
     *
     * @param string $locale The locale to get the rule group for.
     * @param string $type The type of rule to get: 'cardinal' or 'ordinal'. Default is 'cardinal'.
     * @return int The rule group number.
     */
    protected static function getRuleGroup(string $locale, string $type = 'cardinal'): int
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
     * - The {@see calculate()} method returns indices from 0 to (nplurals - 1)
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
     * @see calculate() To determine which plural form index to use for a specific number
     * @see https://www.unicode.org/cldr/charts/48/supplemental/language_plural_rules.html
     */
    public static function getPluralCount(string $locale): int
    {
        return count(self::getCardinalCategories($locale));
    }

}

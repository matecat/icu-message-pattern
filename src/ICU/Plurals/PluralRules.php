<?php

declare(strict_types=1);

namespace Matecat\ICU\Plurals;

use Matecat\ICU\Plurals\Rules\CardinalDecimalRule;
use Matecat\ICU\Plurals\Rules\CardinalIntegerRule;
use Matecat\ICU\Plurals\Rules\OrdinalRule;

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

    // =========================================================================
    // Common category arrays shared by cardinal and ordinal rule classes.
    // Public so that CardinalIntegerRule, CardinalDecimalRule, and OrdinalRule
    // can reference them without duplicating the definitions.
    // =========================================================================

    public const array CATEGORIES_OTHER = [self::CATEGORY_OTHER];
    public const array CATEGORIES_ONE_OTHER = [self::CATEGORY_ONE, self::CATEGORY_OTHER];
    public const array CATEGORIES_ONE_FEW_OTHER = [self::CATEGORY_ONE, self::CATEGORY_FEW, self::CATEGORY_OTHER];
    public const array CATEGORIES_ONE_MANY_OTHER = [self::CATEGORY_ONE, self::CATEGORY_MANY, self::CATEGORY_OTHER];
    public const array CATEGORIES_ONE_TWO_FEW_OTHER = [
        self::CATEGORY_ONE,
        self::CATEGORY_TWO,
        self::CATEGORY_FEW,
        self::CATEGORY_OTHER,
    ];
    public const array CATEGORIES_ONE_TWO_MANY_OTHER = [
        self::CATEGORY_ONE,
        self::CATEGORY_TWO,
        self::CATEGORY_MANY,
        self::CATEGORY_OTHER,
    ];
    public const array CATEGORIES_ONE_FEW_MANY_OTHER = [
        self::CATEGORY_ONE,
        self::CATEGORY_FEW,
        self::CATEGORY_MANY,
        self::CATEGORY_OTHER,
    ];
    public const array CATEGORIES_ONE_TWO_OTHER = [self::CATEGORY_ONE, self::CATEGORY_TWO, self::CATEGORY_OTHER];
    public const array CATEGORIES_ONE_TWO_FEW_MANY_OTHER = [
        self::CATEGORY_ONE,
        self::CATEGORY_TWO,
        self::CATEGORY_FEW,
        self::CATEGORY_MANY,
        self::CATEGORY_OTHER,
    ];
    public const array CATEGORIES_ZERO_ONE_OTHER = [self::CATEGORY_ZERO, self::CATEGORY_ONE, self::CATEGORY_OTHER];
    public const array CATEGORIES_ZERO_ONE_TWO_FEW_MANY_OTHER = [
        self::CATEGORY_ZERO,
        self::CATEGORY_ONE,
        self::CATEGORY_TWO,
        self::CATEGORY_FEW,
        self::CATEGORY_MANY,
        self::CATEGORY_OTHER,
    ];

    /**
     * Additional category arrays used only by ordinal rules.
     */
    public const array CATEGORIES_MANY_OTHER = [PluralRules::CATEGORY_MANY, PluralRules::CATEGORY_OTHER];
    public const array CATEGORIES_FEW_OTHER = [PluralRules::CATEGORY_FEW, PluralRules::CATEGORY_OTHER];
    public const array CATEGORIES_ZERO_ONE_FEW_OTHER = [
        PluralRules::CATEGORY_ZERO,
        PluralRules::CATEGORY_ONE,
        PluralRules::CATEGORY_FEW,
        PluralRules::CATEGORY_OTHER,
    ];

    // =========================================================================
    // Locale → rule group mapping
    // =========================================================================

    /**
     * A map of the locale => plurals group used to determine
     * which plural rules apply to the language
     *
     * Plural Rules (Cardinal):
     * 0  - nplurals=1; plural=0; (Asian, no plural forms)
     * 1  - nplurals=2; plural=(n != 1); (Germanic, most European)
     * 2  - nplurals=2; plural=(n > 1); (Amharic, Persian, Hindi, Fulah, Armenian, Sinhala, etc.)
     * 3  - nplurals=4; plural=(n%10==1 && n%100!=11 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2); (Slavic: Russian, Ukrainian, Belarusian)
     * 4  - nplurals=4; plural=(n==1) ? 0 : (n>=2 && n<=4) ? 1 : 3; (Czech, Slovak — CLDR 49: "many" for decimals at index 2)
     * 5  - nplurals=5; plural=n==1 ? 0 : n==2 ? 1 : (n>=3 && n<=6) ? 2 : (n>=7 && n<=10) ? 3 : 4; (Irish)
     * 6  - nplurals=4; plural=(n%10==1 && n%100!=11 ? 0 : n%10>=2 && (n%100<10 || n%100>=20) ? 1 : 3); (Lithuanian — CLDR 49: "many" for decimals at index 2)
     * 7  - nplurals=4; plural=(n%100==1 ? 0 : n%100==2 ? 1 : n%100==3 || n%100==4 ? 2 : 3); (Slovenian, Lower/Upper Sorbian)
     * 8  - nplurals=2; plural=(n%10==1 && n%100!=11) ? 0 : 1; (Macedonian - CLDR 48)
     * 10 - nplurals=3; plural=(n%10==0 || n%100 in 11..19) ? 0 : (n%10==1 && n%100!=11) ? 1 : 2; (Latvian)
     * 11 - nplurals=3; plural=(n==1 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2); (Polish)
     * 12 - nplurals=3; plural=(n==1 ? 0 : n==0 || n%100>0 && n%100<20 ? 1 : 2); (Romanian; Moldavian)
     * 13 - nplurals=6; plural=(n==0 ? 0 : n==1 ? 1 : n==2 ? 2 : n%100>=3 && n%100<=10 ? 3 : n%100>=11 ? 4 : 5); (Arabic)
     * 14 - nplurals=6; plural=(n==0) ? 0 : (n==1) ? 1 : (n==2) ? 2 : (n==3) ? 3 : (n==6) ? 4 : 5; (Welsh - CLDR 48)
     * 15 - nplurals=2; plural=(n%10!=1 || n%100==11); (Icelandic)
     * 16 - nplurals=4; plural=(n==1 || n==11) ? 0 : (n==2 || n==12) ? 1 : (n>2 && n<20) ? 2 : 3; (Scottish Gaelic)
     * 17 - nplurals=5; plural=(n==1) ? 0 : (n==2) ? 1 : (n==3) ? 2 : 3; (Breton - CLDR 48)
     * 18 - nplurals=5; plural=(n%10==1) ? 0 : (n%10==2) ? 1 : (n%20==0) ? 2 : 4; (Manx — CLDR 49: "many" for decimals at index 3)
     * 19 - nplurals=3; plural=(n==1) ? 0 : (n==2) ? 1 : 2; (Hebrew — CLDR 49: removed "many")
     * 20 - nplurals=3; plural=(n==1) ? 0 : (n!=0 && n%1000000==0) ? 1 : 2; (Italian, Spanish, Catalan - CLDR 49: one = i = 1)
     * 21 - nplurals=3; plural=(n==1) ? 0 : (n==2) ? 1 : 2; (Inuktitut, Sami, Nama)
     * 22 - nplurals=3; plural=(n==0) ? 0 : (n==1) ? 1 : 2; (Colognian, Anii, Langi)
     * 23 - nplurals=3; plural=(n<=1) ? 0 : (n>=2 && n<=10) ? 1 : 2; (Tachelhit)
     * 24 - nplurals=6; (Cornish - complex CLDR 49 rules)
     * 25 - nplurals=2; (Filipino/Tagalog - CLDR 49)
     * 26 - nplurals=2; (Central Atlas Tamazight - CLDR 49)
     * 27 - nplurals=3; (Bosnian, Croatian, Serbian — CLDR 49: one/few/other)
     * 28 - nplurals=5; plural=(n==1) ? 0 : (n==2) ? 1 : (n==0 || n%100>=3 && n%100<=10) ? 2 : (n%100>=11 && n%100<=19) ? 3 : 4; (Maltese — CLDR 49)
     * 29 - nplurals=3; plural=(n<=1) ? 0 : (n!=0 && n%1000000==0) ? 1 : 2; (French, Portuguese - CLDR 49: one = i = 0,1)
     *
     * Ordinal Rules:
     * 0  - Only "other" (no ordinal distinction) — default for all unlisted locales
     * 1  - one/two/few/other (English: n%10=1 except 11 / n%10=2 except 12 / n%10=3 except 13)
     * 2  - one/other (French-like: n = 1)
     * 8  - one/two/many/other (Macedonian)
     * 14 - zero/one/two/few/many/other (Welsh)
     * 16 - one/two/few/other (Scottish Gaelic)
     * 20 - many/other (Italian: n=8,11,80,800)
     * 21 - many/other (Kazakh: n%10=6,9 or n%10=0 && n!=0)
     * 22 - few/other (Ukrainian: n%10=3 and n%100!=13)
     * 23 - one/two/few/many/other (Bengali, Assamese: one=1,5,7,8,9,10)
     * 24 - one/two/few/many/other (Gujarati, Hindi: one=1)
     * 26 - one/two/few/other (Marathi, Konkani)
     * 27 - one/two/few/many/other (Odia: one=1,5,7..9)
     * 29 - one/other (Nepali: n=1..4)
     * 30 - one/many/other (Albanian: n=1 / n%10=4 except 14)
     * 31 - zero/one/few/other (Anii)
     * 32 - one/many/other (Cornish)
     * 33 - few/other (Afrikaans: i%100=2..19)
     * 34 - one/other (Spanish: n%10=1,3 and n%100!=11)
     * 35 - one/other (Hungarian: n=1,5)
     * 36 - one/few/many/other (Azerbaijani)
     * 37 - few/other (Belarusian: n%10=2,3 and n%100!=12,13)
     * 38 - zero/one/two/few/many/other (Bulgarian)
     * 39 - one/two/few/other (Catalan: n=1,3 / n=2 / n=4)
     * 40 - one/many/other (Georgian)
     * 41 - one/other (Swedish: n%10=1,2 and n%100!=11,12)
     * 42 - many/other (Ligurian/Sicilian: n=8,11,80..89,800..899)
     * 43 - few/other (Turkmen: n%10=6,9 or n=10)
     *
     * @var array<string, array{cardinal: int, ordinal: int}>
     */
    protected static array $rulesMap = [
        // A
        'aa' => ['cardinal' => 0, 'ordinal' => 0],      // Afar
        'ace' => ['cardinal' => 0, 'ordinal' => 2],    // Acehnese - inherits from 'ms'
        'acf' => ['cardinal' => 29, 'ordinal' => 2],   // Saint Lucian Creole French - inherits from 'fr'
        'af' => ['cardinal' => 1, 'ordinal' => 33],    // Afrikaans - CLDR 49 ordinal: few/other
        'aig' => ['cardinal' => 1, 'ordinal' => 1],    // Antigua and Barbuda Creole English - inherits from 'en'
        'ak' => ['cardinal' => 2, 'ordinal' => 0],    // Akan
        'als' => ['cardinal' => 1, 'ordinal' => 30],   // Albanian (Tosk) - inherits from 'sq'
        'am' => ['cardinal' => 2, 'ordinal' => 0],    // Amharic
        'an' => ['cardinal' => 1, 'ordinal' => 0],    // Aragonese
        'ar' => ['cardinal' => 13, 'ordinal' => 0],   // Arabic
        'as' => ['cardinal' => 2, 'ordinal' => 23],   // Assamese - CLDR 49: one = i = 0 or n = 1; ordinal: one/two/few/many/other
        'asa' => ['cardinal' => 1, 'ordinal' => 0],   // Asu - CLDR 49
        'asm' => ['cardinal' => 2, 'ordinal' => 23],   // Assamese (alternate code) - inherits from 'as'
        'ast' => ['cardinal' => 1, 'ordinal' => 0],   // Asturian
        'awa' => ['cardinal' => 2, 'ordinal' => 24],   // Awadhi - inherits from 'hi'
        'ayr' => ['cardinal' => 0, 'ordinal' => 0],   // Central Aymara
        'az' => ['cardinal' => 1, 'ordinal' => 36],   // Azerbaijani - CLDR 49 ordinal: one/few/many/other
        'azb' => ['cardinal' => 1, 'ordinal' => 36],  // South Azerbaijani
        'azj' => ['cardinal' => 1, 'ordinal' => 36],  // North Azerbaijani

        // B
        'ba' => ['cardinal' => 0, 'ordinal' => 0],    // Bashkir
        'bah' => ['cardinal' => 1, 'ordinal' => 1],    // Bahamas Creole English - inherits from 'en'
        'bal' => ['cardinal' => 1, 'ordinal' => 2],   // Baluchi - CLDR 49 ordinal: one/other
        'ban' => ['cardinal' => 0, 'ordinal' => 0],   // Balinese
        'be' => ['cardinal' => 3, 'ordinal' => 37],    // Belarusian - CLDR 49 ordinal: few/other
        'bem' => ['cardinal' => 1, 'ordinal' => 0],   // Bemba
        'bez' => ['cardinal' => 1, 'ordinal' => 0],   // Bena - CLDR 49
        'bg' => ['cardinal' => 1, 'ordinal' => 38],    // Bulgarian - CLDR 49 ordinal: zero/one/two/few/many/other
        'bh' => ['cardinal' => 2, 'ordinal' => 24],    // Bihari - inherits from 'hi'
        'bho' => ['cardinal' => 2, 'ordinal' => 0],   // Bhojpuri - CLDR 49: one = n = 0..1
        'bi' => ['cardinal' => 1, 'ordinal' => 1],    // Bislama - inherits from 'en'
        'bjn' => ['cardinal' => 0, 'ordinal' => 2],   // Banjar - inherits from 'ms'
        'bjs' => ['cardinal' => 1, 'ordinal' => 1],    // Bajan - inherits from 'en'
        'blo' => ['cardinal' => 22, 'ordinal' => 31], // Anii - CLDR 49
        'bm' => ['cardinal' => 0, 'ordinal' => 0],    // Bambara
        'bn' => ['cardinal' => 2, 'ordinal' => 23],   // Bengali - CLDR 49: one = i = 0 or n = 1; ordinal: one/two/few/many/other
        'bo' => ['cardinal' => 0, 'ordinal' => 0],    // Tibetan
        'bod' => ['cardinal' => 0, 'ordinal' => 0],   // Tibetan (alternate code) - inherits from 'bo'
        'br' => ['cardinal' => 17, 'ordinal' => 0],   // Breton
        'brx' => ['cardinal' => 1, 'ordinal' => 0],   // Bodo
        'bs' => ['cardinal' => 27, 'ordinal' => 0],    // Bosnian - CLDR 49: one/few/other
        'bug' => ['cardinal' => 0, 'ordinal' => 0],   // Buginese - inherits from 'id'

        // C
        'ca' => ['cardinal' => 20, 'ordinal' => 39],   // Catalan - CLDR 49 ordinal: one/two/few/other
        'cac' => ['cardinal' => 0, 'ordinal' => 0],     // Chuj
        'cav' => ['cardinal' => 20, 'ordinal' => 39],   // Catalan (Valencia) - inherits from 'ca'
        'cb' => ['cardinal' => 1, 'ordinal' => 0],     // Cebuano (alternate code) - inherits from 'ceb'
        'ce' => ['cardinal' => 1, 'ordinal' => 0],    // Chechen
        'ceb' => ['cardinal' => 1, 'ordinal' => 0],   // Cebuano
        'cgg' => ['cardinal' => 1, 'ordinal' => 0],   // Chiga - CLDR 49
        'ch' => ['cardinal' => 0, 'ordinal' => 0],    // Chamorro
        'chk' => ['cardinal' => 0, 'ordinal' => 0],   // Chuukese
        'chr' => ['cardinal' => 1, 'ordinal' => 0],   // Cherokee
        'cjk' => ['cardinal' => 0, 'ordinal' => 0],     // Chokwe
        'ckb' => ['cardinal' => 1, 'ordinal' => 0],   // Central Kurdish
        'cop' => ['cardinal' => 0, 'ordinal' => 0],     // Coptic
        'crh' => ['cardinal' => 1, 'ordinal' => 0],   // Crimean Tatar - inherits from 'tr'
        'crs' => ['cardinal' => 29, 'ordinal' => 2],   // Seselwa Creole French - inherits from 'fr'
        'cs' => ['cardinal' => 4, 'ordinal' => 0],    // Czech
        'csw' => ['cardinal' => 2, 'ordinal' => 0],  // Swampy Cree - CLDR 49: one/other
        'ctg' => ['cardinal' => 2, 'ordinal' => 23],   // Chittagonian - inherits from 'bn'
        'cy' => ['cardinal' => 14, 'ordinal' => 14],  // Welsh - CLDR 49 ordinal: zero/one/two/few/many/other

        // D
        'da' => ['cardinal' => 1, 'ordinal' => 0],    // Danish
        'de' => ['cardinal' => 1, 'ordinal' => 0],    // German
        'dik' => ['cardinal' => 0, 'ordinal' => 0],     // Southwestern Dinka
        'diq' => ['cardinal' => 0, 'ordinal' => 0],     // Dimli
        'div' => ['cardinal' => 1, 'ordinal' => 0],    // Divehi (alternate code) - inherits from 'dv'
        'doi' => ['cardinal' => 2, 'ordinal' => 0],   // Dogri - CLDR 49: one = i = 0 or n = 1
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
        'ff' => ['cardinal' => 2, 'ordinal' => 0],    // Fulah - CLDR 49: one = i = 0,1
        'fi' => ['cardinal' => 1, 'ordinal' => 0],    // Finnish
        'fil' => ['cardinal' => 25, 'ordinal' => 2],   // Filipino - CLDR 49 cardinal: one/other (does not end in 4,6,9)
        'fj' => ['cardinal' => 0, 'ordinal' => 0],    // Fijian
        'fn' => ['cardinal' => 2, 'ordinal' => 0],    // Fanagalo - inherits from 'zu'
        'fo' => ['cardinal' => 1, 'ordinal' => 0],    // Faroese
        'fon' => ['cardinal' => 0, 'ordinal' => 0],   // Fon
        'fr' => ['cardinal' => 29, 'ordinal' => 2],   // French - CLDR 49: one = i = 0,1; ordinal: one/other
        'fuc' => ['cardinal' => 2, 'ordinal' => 0],    // Pulaar - inherits from 'ff'
        'fur' => ['cardinal' => 1, 'ordinal' => 0],   // Friulian
        'fuv' => ['cardinal' => 2, 'ordinal' => 0],    // Nigerian Fulfulde - inherits from 'ff'
        'fy' => ['cardinal' => 1, 'ordinal' => 0],    // Western Frisian - CLDR 49

        // G
        'ga' => ['cardinal' => 5, 'ordinal' => 2],    // Irish - CLDR 49 ordinal: one/other
        'gax' => ['cardinal' => 1, 'ordinal' => 0],    // Borana-Arsi-Guji Oromo - inherits from 'om'
        'gaz' => ['cardinal' => 1, 'ordinal' => 0],    // West Central Oromo - inherits from 'om'
        'gcl' => ['cardinal' => 1, 'ordinal' => 1],    // Grenadian Creole English - inherits from 'en'
        'gd' => ['cardinal' => 16, 'ordinal' => 16],  // Scottish Gaelic - CLDR 49 ordinal: one/two/few/other
        'gil' => ['cardinal' => 0, 'ordinal' => 0],   // Gilbertese
        'gl' => ['cardinal' => 1, 'ordinal' => 0],    // Galician
        'glw' => ['cardinal' => 0, 'ordinal' => 0],     // Glaro-Twabo
        'gn' => ['cardinal' => 0, 'ordinal' => 0],      // Guarani
        'grc' => ['cardinal' => 1, 'ordinal' => 0],    // Ancient Greek - inherits from 'el'
        'grt' => ['cardinal' => 0, 'ordinal' => 0],     // Garo
        'gsw' => ['cardinal' => 1, 'ordinal' => 0],   // Swiss German - CLDR 49
        'gu' => ['cardinal' => 2, 'ordinal' => 24],   // Gujarati - CLDR 49: one = i = 0 or n = 1; ordinal: one/two/few/many/other
        'guz' => ['cardinal' => 0, 'ordinal' => 0],     // Gusii
        'gv' => ['cardinal' => 18, 'ordinal' => 0],   // Manx
        'gyn' => ['cardinal' => 1, 'ordinal' => 1],    // Guyanese Creole English - inherits from 'en'

        // H
        'ha' => ['cardinal' => 1, 'ordinal' => 0],    // Hausa
        'haw' => ['cardinal' => 1, 'ordinal' => 0],   // Hawaiian
        'he' => ['cardinal' => 19, 'ordinal' => 0],   // Hebrew
        'hi' => ['cardinal' => 2, 'ordinal' => 24],   // Hindi - CLDR 49 ordinal: one/two/few/many/other (same as gu)
        'hig' => ['cardinal' => 0, 'ordinal' => 0],     // Kamwe
        'hil' => ['cardinal' => 25, 'ordinal' => 2],   // Hiligaynon - inherits from 'fil'
        'hmn' => ['cardinal' => 0, 'ordinal' => 0],   // Hmong
        'hne' => ['cardinal' => 2, 'ordinal' => 24],   // Chhattisgarhi - inherits from 'hi'
        'hnj' => ['cardinal' => 0, 'ordinal' => 0],   // Hmong Njua - CLDR 49
        'hoc' => ['cardinal' => 0, 'ordinal' => 0],     // Ho
        'hr' => ['cardinal' => 27, 'ordinal' => 0],    // Croatian - CLDR 49: one/few/other
        'hsb' => ['cardinal' => 7, 'ordinal' => 0],   // Upper Sorbian - CLDR 49
        'ht' => ['cardinal' => 29, 'ordinal' => 2],    // Haitian Creole - follows French: one = i = 0,1
        'hu' => ['cardinal' => 1, 'ordinal' => 35],   // Hungarian - CLDR 49 ordinal: one/other
        'hy' => ['cardinal' => 2, 'ordinal' => 2],    // Armenian - CLDR 49: one = i = 0,1; ordinal: one/other

        // I
        'ia' => ['cardinal' => 1, 'ordinal' => 0],    // Interlingua - CLDR 49
        'id' => ['cardinal' => 0, 'ordinal' => 0],    // Indonesian
        'ig' => ['cardinal' => 0, 'ordinal' => 0],    // Igbo
        'ii' => ['cardinal' => 0, 'ordinal' => 0],    // Sichuan Yi - CLDR 49
        'ilo' => ['cardinal' => 25, 'ordinal' => 2],   // Ilocano - inherits from 'fil'
        'io' => ['cardinal' => 1, 'ordinal' => 0],    // Ido - CLDR 49
        'is' => ['cardinal' => 15, 'ordinal' => 0],   // Icelandic
        'it' => ['cardinal' => 20, 'ordinal' => 20],  // Italian - CLDR 49 ordinal: many/other
        'iu' => ['cardinal' => 21, 'ordinal' => 0],   // Inuktitut - CLDR 49

        // J
        'ja' => ['cardinal' => 0, 'ordinal' => 0],    // Japanese
        'jam' => ['cardinal' => 1, 'ordinal' => 1],    // Jamaican Creole English - inherits from 'en'
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
        'kal' => ['cardinal' => 1, 'ordinal' => 0],    // Kalaallisut (alternate code) - inherits from 'kl'
        'kam' => ['cardinal' => 0, 'ordinal' => 0],     // Kamba
        'kar' => ['cardinal' => 0, 'ordinal' => 0],   // Karen
        'kas' => ['cardinal' => 1, 'ordinal' => 0],    // Kashmiri (alternate code) - inherits from 'ks'
        'kbp' => ['cardinal' => 0, 'ordinal' => 0],   // Kabiyè
        'kcg' => ['cardinal' => 1, 'ordinal' => 0],   // Tyap - CLDR 49
        'kde' => ['cardinal' => 0, 'ordinal' => 0],   // Makonde - CLDR 49
        'kea' => ['cardinal' => 0, 'ordinal' => 0],   // Kabuverdianu
        'kg' => ['cardinal' => 0, 'ordinal' => 0],      // Kongo
        'kha' => ['cardinal' => 0, 'ordinal' => 0],     // Khasi
        'khk' => ['cardinal' => 1, 'ordinal' => 0],    // Halh Mongolian - inherits from 'mn'
        'ki' => ['cardinal' => 0, 'ordinal' => 0],      // Kikuyu
        'kjb' => ['cardinal' => 0, 'ordinal' => 0],     // Q'anjob'al
        'kk' => ['cardinal' => 1, 'ordinal' => 21],   // Kazakh - CLDR 49 ordinal: many/other
        'kkj' => ['cardinal' => 1, 'ordinal' => 0],   // Kako - CLDR 49
        'kl' => ['cardinal' => 1, 'ordinal' => 0],    // Greenlandic
        'kln' => ['cardinal' => 0, 'ordinal' => 0],     // Kalenjin
        'km' => ['cardinal' => 0, 'ordinal' => 0],    // Khmer
        'kmb' => ['cardinal' => 0, 'ordinal' => 0],     // Kimbundu
        'kmr' => ['cardinal' => 1, 'ordinal' => 0],    // Northern Kurdish - inherits from 'ku'
        'kn' => ['cardinal' => 2, 'ordinal' => 0],   // Kannada - CLDR 49: one = i = 0 or n = 1
        'knc' => ['cardinal' => 0, 'ordinal' => 0],     // Central Kanuri
        'ko' => ['cardinal' => 0, 'ordinal' => 0],    // Korean
        'kok' => ['cardinal' => 2, 'ordinal' => 26],   // Konkani - CLDR 49: one = i = 0 or n = 1; ordinal: one/two/few/other
        'kr' => ['cardinal' => 0, 'ordinal' => 0],    // Kanuri
        'ks' => ['cardinal' => 1, 'ordinal' => 0],    // Kashmiri
        'ksb' => ['cardinal' => 1, 'ordinal' => 0],   // Shambala - CLDR 49
        'ksh' => ['cardinal' => 22, 'ordinal' => 0],  // Colognian - CLDR 49
        'ksw' => ['cardinal' => 0, 'ordinal' => 0],   // S'gaw Karen
        'ku' => ['cardinal' => 1, 'ordinal' => 0],    // Kurdish - CLDR 49
        'kw' => ['cardinal' => 24, 'ordinal' => 32],  // Cornish - CLDR 49
        'ky' => ['cardinal' => 1, 'ordinal' => 0],    // Kyrgyz

        // L
        'la' => ['cardinal' => 20, 'ordinal' => 20],   // Latin - inherits from 'it'
        'lag' => ['cardinal' => 22, 'ordinal' => 0],  // Langi - CLDR 49
        'lb' => ['cardinal' => 1, 'ordinal' => 0],    // Luxembourgish
        'lg' => ['cardinal' => 1, 'ordinal' => 0],    // Ganda
        'li' => ['cardinal' => 1, 'ordinal' => 0],     // Limburgish - inherits from 'nl'
        'lij' => ['cardinal' => 1, 'ordinal' => 42],   // Ligurian - CLDR 49 ordinal: many/other (n=8,11,80..89,800..899)
        'lkt' => ['cardinal' => 0, 'ordinal' => 0],   // Lakota - CLDR 49
        'lld' => ['cardinal' => 20, 'ordinal' => 20], // Ladin - CLDR 49
        'lmo' => ['cardinal' => 20, 'ordinal' => 20],  // Lombard - inherits from 'it'
        'ln' => ['cardinal' => 2, 'ordinal' => 0],    // Lingala
        'lo' => ['cardinal' => 0, 'ordinal' => 2],    // Lao - CLDR 49 ordinal: one/other
        'lt' => ['cardinal' => 6, 'ordinal' => 0],    // Lithuanian
        'ltg' => ['cardinal' => 10, 'ordinal' => 0],   // Latgalian - inherits from 'lv'
        'lua' => ['cardinal' => 0, 'ordinal' => 0],     // Luba-Lulua
        'lug' => ['cardinal' => 1, 'ordinal' => 0],    // Luganda (alternate code) - inherits from 'lg'
        'luo' => ['cardinal' => 0, 'ordinal' => 0],     // Luo
        'lus' => ['cardinal' => 0, 'ordinal' => 0],     // Mizo
        'luy' => ['cardinal' => 0, 'ordinal' => 0],     // Luyia
        'lv' => ['cardinal' => 10, 'ordinal' => 0],   // Latvian
        'lvs' => ['cardinal' => 10, 'ordinal' => 0],   // Standard Latvian - inherits from 'lv'

        // M
        'mag' => ['cardinal' => 2, 'ordinal' => 24],   // Magahi - inherits from 'hi'
        'mai' => ['cardinal' => 2, 'ordinal' => 24],   // Maithili - inherits from 'hi'
        'mam' => ['cardinal' => 0, 'ordinal' => 0],     // Mam
        'mas' => ['cardinal' => 1, 'ordinal' => 0],   // Maasai
        'me' => ['cardinal' => 27, 'ordinal' => 0],    // Montenegrin - inherits from 'sr'
        'men' => ['cardinal' => 0, 'ordinal' => 0],     // Mende
        'mer' => ['cardinal' => 0, 'ordinal' => 0],     // Meru
        'mfe' => ['cardinal' => 29, 'ordinal' => 2],   // Mauritian Creole - inherits from 'fr'
        'mfi' => ['cardinal' => 0, 'ordinal' => 0],     // Wandala
        'mfv' => ['cardinal' => 0, 'ordinal' => 0],     // Mandjak
        'mg' => ['cardinal' => 2, 'ordinal' => 0],    // Malagasy
        'mgo' => ['cardinal' => 1, 'ordinal' => 0],   // Metaʼ - CLDR 49
        'mh' => ['cardinal' => 0, 'ordinal' => 0],    // Marshallese
        'mhr' => ['cardinal' => 0, 'ordinal' => 0],     // Eastern Mari
        'mi' => ['cardinal' => 0, 'ordinal' => 0],      // Maori
        'min' => ['cardinal' => 0, 'ordinal' => 2],   // Minangkabau - inherits from 'ms'
        'mk' => ['cardinal' => 8, 'ordinal' => 8],    // Macedonian - CLDR 49 ordinal: one/two/many/other
        'ml' => ['cardinal' => 1, 'ordinal' => 0],    // Malayalam
        'mn' => ['cardinal' => 1, 'ordinal' => 0],    // Mongolian
        'mni' => ['cardinal' => 0, 'ordinal' => 0],     // Manipuri
        'mnk' => ['cardinal' => 0, 'ordinal' => 0],     // Mandinka
        'mo' => ['cardinal' => 12, 'ordinal' => 2],   // Moldavian (same as Romanian)
        'mos' => ['cardinal' => 0, 'ordinal' => 0],   // Mossi
        'mr' => ['cardinal' => 1, 'ordinal' => 26],   // Marathi - CLDR 49 ordinal: one/two/few/other
        'mrj' => ['cardinal' => 0, 'ordinal' => 0],     // Western Mari
        'mrt' => ['cardinal' => 0, 'ordinal' => 0],     // Marghi Central
        'ms' => ['cardinal' => 0, 'ordinal' => 2],    // Malay - CLDR 49 ordinal: one/other
        'mt' => ['cardinal' => 28, 'ordinal' => 0],    // Maltese - CLDR 49: one/two/few/many/other
        'my' => ['cardinal' => 0, 'ordinal' => 0],    // Burmese

        // N
        'nb' => ['cardinal' => 1, 'ordinal' => 0],    // Norwegian Bokmål
        'nd' => ['cardinal' => 1, 'ordinal' => 0],    // North Ndebele
        'ndc' => ['cardinal' => 0, 'ordinal' => 0],     // Ndau
        'ne' => ['cardinal' => 1, 'ordinal' => 29],   // Nepali - CLDR 49 ordinal: one/other
        'naq' => ['cardinal' => 21, 'ordinal' => 0],  // Nama - CLDR 49
        'niu' => ['cardinal' => 0, 'ordinal' => 0],   // Niuean
        'nl' => ['cardinal' => 1, 'ordinal' => 0],    // Dutch
        'nn' => ['cardinal' => 1, 'ordinal' => 0],    // Norwegian Nynorsk
        'nnh' => ['cardinal' => 1, 'ordinal' => 0],   // Ngiemboon - CLDR 49
        'no' => ['cardinal' => 1, 'ordinal' => 0],    // Norwegian - CLDR 49
        'nqo' => ['cardinal' => 0, 'ordinal' => 0],   // N'Ko - CLDR 49
        'nr' => ['cardinal' => 1, 'ordinal' => 0],    // South Ndebele
        'ns' => ['cardinal' => 2, 'ordinal' => 0],     // Sesotho/Northern Sotho (alternate code) - inherits from 'nso'
        'nso' => ['cardinal' => 2, 'ordinal' => 0],   // Northern Sotho
        'nup' => ['cardinal' => 0, 'ordinal' => 0],     // Nupe
        'nus' => ['cardinal' => 0, 'ordinal' => 0],     // Nuer
        'ny' => ['cardinal' => 1, 'ordinal' => 0],    // Nyanja (Chichewa)
        'nyf' => ['cardinal' => 0, 'ordinal' => 0],     // Giryama
        'nyn' => ['cardinal' => 1, 'ordinal' => 0],   // Nyankole - CLDR 49

        // O
        'oc' => ['cardinal' => 1, 'ordinal' => 0],      // Occitan - one = i = 1 and v = 0; other
        'om' => ['cardinal' => 1, 'ordinal' => 0],    // Oromo
        'or' => ['cardinal' => 1, 'ordinal' => 27],   // Odia - CLDR 49 ordinal: one/two/few/many/other
        'ory' => ['cardinal' => 1, 'ordinal' => 27],   // Odia (Oriya) - inherits from 'or'
        'os' => ['cardinal' => 1, 'ordinal' => 0],    // Ossetic - CLDR 49
        'osa' => ['cardinal' => 0, 'ordinal' => 0],   // Osage - CLDR 49

        // P
        'pa' => ['cardinal' => 2, 'ordinal' => 0],    // Punjabi - CLDR 49: one = n = 0..1
        'pag' => ['cardinal' => 25, 'ordinal' => 2],   // Pangasinan - inherits from 'fil'
        'pap' => ['cardinal' => 1, 'ordinal' => 0],   // Papiamento
        'pau' => ['cardinal' => 0, 'ordinal' => 0],   // Palauan
        'pbt' => ['cardinal' => 1, 'ordinal' => 0],    // Southern Pashto - inherits from 'ps'
        'pcm' => ['cardinal' => 2, 'ordinal' => 0],   // Nigerian Pidgin - CLDR 49
        'pi' => ['cardinal' => 0, 'ordinal' => 0],      // Pali
        'pis' => ['cardinal' => 1, 'ordinal' => 1],   // Pijin - inherits from 'en'
        'pko' => ['cardinal' => 0, 'ordinal' => 0],     // Pökoot
        'pl' => ['cardinal' => 11, 'ordinal' => 0],   // Polish
        'plt' => ['cardinal' => 2, 'ordinal' => 0],    // Plateau Malagasy - inherits from 'mg'
        'pon' => ['cardinal' => 0, 'ordinal' => 0],   // Pohnpeian
        'pot' => ['cardinal' => 0, 'ordinal' => 0],     // Potawatomi
        'pov' => ['cardinal' => 29, 'ordinal' => 0],   // Guinea-Bissau Creole - inherits from 'pt'
        'ppk' => ['cardinal' => 0, 'ordinal' => 0],   // Uma
        'prg' => ['cardinal' => 10, 'ordinal' => 0],  // Prussian - CLDR 49
        'prs' => ['cardinal' => 2, 'ordinal' => 0],    // Dari - inherits from 'fa'
        'ps' => ['cardinal' => 1, 'ordinal' => 0],    // Pashto
        'pt' => ['cardinal' => 29, 'ordinal' => 0],   // Portuguese - CLDR 49: one = i = 0..1
        'pt_pt' => ['cardinal' => 20, 'ordinal' => 0], // European Portuguese - CLDR 49

        // Q
        'qu' => ['cardinal' => 0, 'ordinal' => 0],      // Quechua
        'quc' => ['cardinal' => 0, 'ordinal' => 0],     // K'iche'
        'quy' => ['cardinal' => 0, 'ordinal' => 0],     // Ayacucho Quechua
        'qnt' => ['cardinal' => 0, 'ordinal' => 0],     // Testing pseudo-locale

        // R
        'rhg' => ['cardinal' => 0, 'ordinal' => 0],     // Rohingya
        'rhl' => ['cardinal' => 0, 'ordinal' => 0],     // Rohingya (alternate)
        'rmn' => ['cardinal' => 27, 'ordinal' => 0],   // Balkan Romani - inherits from 'sr'
        'rmo' => ['cardinal' => 0, 'ordinal' => 0],     // Sinte Romani
        'rn' => ['cardinal' => 0, 'ordinal' => 0],      // Rundi
        'rm' => ['cardinal' => 1, 'ordinal' => 0],    // Romansh - CLDR 49
        'ro' => ['cardinal' => 12, 'ordinal' => 2],   // Romanian - CLDR 49 ordinal: one/other
        'rof' => ['cardinal' => 1, 'ordinal' => 0],   // Rombo - CLDR 49
        'roh' => ['cardinal' => 1, 'ordinal' => 0],    // Romansh (alternate code) - inherits from 'rm'
        'ru' => ['cardinal' => 3, 'ordinal' => 0],    // Russian
        'run' => ['cardinal' => 0, 'ordinal' => 0],     // Rundi (alternate)
        'rw' => ['cardinal' => 0, 'ordinal' => 0],      // Kinyarwanda
        'rwk' => ['cardinal' => 1, 'ordinal' => 0],   // Rwa - CLDR 49

        // S
        'sa' => ['cardinal' => 2, 'ordinal' => 24],    // Sanskrit - inherits from 'hi'
        'sah' => ['cardinal' => 0, 'ordinal' => 0],   // Yakut - CLDR 49
        'saq' => ['cardinal' => 1, 'ordinal' => 0],   // Samburu - CLDR 49
        'sat' => ['cardinal' => 21, 'ordinal' => 0],   // Santali - CLDR 49: one/two/other
        'sc' => ['cardinal' => 1, 'ordinal' => 20],   // Sardinian - CLDR 49 ordinal: many/other
        'scn' => ['cardinal' => 20, 'ordinal' => 42],   // Sicilian - CLDR 49: one/many/other, ordinal: many/other (n=8,11,80..89,800..899)
        'sd' => ['cardinal' => 1, 'ordinal' => 0],    // Sindhi
        'sdh' => ['cardinal' => 1, 'ordinal' => 0],   // Southern Kurdish - CLDR 49
        'se' => ['cardinal' => 21, 'ordinal' => 0],   // Northern Sami - CLDR 49
        'seh' => ['cardinal' => 1, 'ordinal' => 0],   // Sena
        'ses' => ['cardinal' => 0, 'ordinal' => 0],   // Koyraboro Senni - CLDR 49
        'sg' => ['cardinal' => 0, 'ordinal' => 0],    // Sango
        'sh' => ['cardinal' => 27, 'ordinal' => 0],    // Serbo-Croatian - CLDR 49: one/few/other
        'shi' => ['cardinal' => 23, 'ordinal' => 0],  // Tachelhit - CLDR 49
        'shn' => ['cardinal' => 0, 'ordinal' => 0],   // Shan
        'shu' => ['cardinal' => 13, 'ordinal' => 0],   // Chadian Arabic - inherits from 'ar'
        'si' => ['cardinal' => 2, 'ordinal' => 0],    // Sinhala - CLDR 49: one = n = 0,1
        'sk' => ['cardinal' => 4, 'ordinal' => 0],    // Slovak
        'sl' => ['cardinal' => 7, 'ordinal' => 0],    // Slovenian
        'sm' => ['cardinal' => 0, 'ordinal' => 0],    // Samoan
        'sma' => ['cardinal' => 21, 'ordinal' => 0],  // Southern Sami - CLDR 49
        'smj' => ['cardinal' => 21, 'ordinal' => 0],  // Lule Sami - CLDR 49
        'smn' => ['cardinal' => 21, 'ordinal' => 0],  // Inari Sami - CLDR 49
        'smo' => ['cardinal' => 0, 'ordinal' => 0],   // Samoan (alternate code) - same as 'sm'
        'sms' => ['cardinal' => 21, 'ordinal' => 0],  // Skolt Sami - CLDR 49
        'sn' => ['cardinal' => 1, 'ordinal' => 0],    // Shona
        'sna' => ['cardinal' => 1, 'ordinal' => 0],    // Shona (alternate code) - inherits from 'sn'
        'snk' => ['cardinal' => 0, 'ordinal' => 0],     // Soninke
        'so' => ['cardinal' => 1, 'ordinal' => 0],    // Somali
        'sq' => ['cardinal' => 1, 'ordinal' => 30],   // Albanian - CLDR 49 ordinal: one/many/other
        'sr' => ['cardinal' => 27, 'ordinal' => 0],    // Serbian - CLDR 49: one/few/other
        'srn' => ['cardinal' => 1, 'ordinal' => 0],    // Sranan Tongo - inherits from 'nl'
        'ss' => ['cardinal' => 1, 'ordinal' => 0],    // Swati
        'ssy' => ['cardinal' => 1, 'ordinal' => 0],   // Saho
        'st' => ['cardinal' => 1, 'ordinal' => 0],    // Southern Sotho
        'su' => ['cardinal' => 0, 'ordinal' => 0],    // Sundanese
        'sus' => ['cardinal' => 0, 'ordinal' => 0],   // Susu
        'sv' => ['cardinal' => 1, 'ordinal' => 41],   // Swedish - CLDR 49 ordinal: one/other (n%10=1,2 except 11,12)
        'svc' => ['cardinal' => 1, 'ordinal' => 1],    // Vincentian Creole English - inherits from 'en'
        'sw' => ['cardinal' => 1, 'ordinal' => 0],    // Swahili
        'syc' => ['cardinal' => 0, 'ordinal' => 0],     // Classical Syriac
        'syr' => ['cardinal' => 1, 'ordinal' => 0],   // Syriac - CLDR 49
        'szl' => ['cardinal' => 11, 'ordinal' => 0],   // Silesian - inherits from 'pl'

        // T
        'ta' => ['cardinal' => 1, 'ordinal' => 0],    // Tamil
        'taq' => ['cardinal' => 0, 'ordinal' => 0],   // Tamasheq
        'te' => ['cardinal' => 1, 'ordinal' => 0],   // Telugu - CLDR 49 ordinal: other only
        'teo' => ['cardinal' => 1, 'ordinal' => 0],   // Teso - CLDR 49
        'tet' => ['cardinal' => 29, 'ordinal' => 0],   // Tetum - inherits from 'pt'
        'tg' => ['cardinal' => 2, 'ordinal' => 0],     // Tajik - inherits from 'fa'
        'th' => ['cardinal' => 0, 'ordinal' => 0],    // Thai
        'ti' => ['cardinal' => 2, 'ordinal' => 0],    // Tigrinya
        'tig' => ['cardinal' => 1, 'ordinal' => 0],   // Tigre - CLDR 49
        'tiv' => ['cardinal' => 0, 'ordinal' => 0],     // Tiv
        'tk' => ['cardinal' => 1, 'ordinal' => 43],   // Turkmen - CLDR 49 ordinal: few/other (n%10=6,9 or n=10)
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
        'tsc' => ['cardinal' => 0, 'ordinal' => 0],     // Tswa
        'tt' => ['cardinal' => 0, 'ordinal' => 0],    // Tatar
        'tum' => ['cardinal' => 0, 'ordinal' => 0],     // Tumbuka
        'tvl' => ['cardinal' => 0, 'ordinal' => 0],   // Tuvalu
        'tw' => ['cardinal' => 2, 'ordinal' => 0],     // Twi - inherits from 'ak'
        'ty' => ['cardinal' => 0, 'ordinal' => 0],    // Tahitian
        'tzm' => ['cardinal' => 26, 'ordinal' => 0],   // Central Atlas Tamazight - CLDR 49: n = 0–1 or n = 11–99

        // U
        'udm' => ['cardinal' => 0, 'ordinal' => 0],     // Udmurt
        'ug' => ['cardinal' => 1, 'ordinal' => 0],    // Uyghur - CLDR 49: one/other
        'uk' => ['cardinal' => 3, 'ordinal' => 22],   // Ukrainian - CLDR 49 ordinal: few/other (n%10=3, n%100≠13)
        'umb' => ['cardinal' => 0, 'ordinal' => 0],     // Umbundu
        'ur' => ['cardinal' => 1, 'ordinal' => 0],    // Urdu
        'uz' => ['cardinal' => 1, 'ordinal' => 0],    // Uzbek - CLDR 49: n = 1
        'uzn' => ['cardinal' => 1, 'ordinal' => 0],     // Northern Uzbek - follows Uzbek (CLDR 49: n = 1)

        // V
        've' => ['cardinal' => 1, 'ordinal' => 0],    // Venda - CLDR 49
        'vec' => ['cardinal' => 20, 'ordinal' => 20],   // Venetian - CLDR 49: one/many/other, ordinal: many/other
        'vi' => ['cardinal' => 0, 'ordinal' => 2],    // Vietnamese - CLDR 49 ordinal: one/other
        'vic' => ['cardinal' => 1, 'ordinal' => 1],    // Virgin Islands Creole English - inherits from 'en'
        'vls' => ['cardinal' => 1, 'ordinal' => 0],    // Vlaams (West Flemish) - inherits from 'nl'
        'vmw' => ['cardinal' => 0, 'ordinal' => 0],     // Makhuwa
        'vo' => ['cardinal' => 1, 'ordinal' => 0],    // Volapük - CLDR 49
        'vun' => ['cardinal' => 1, 'ordinal' => 0],   // Vunjo - CLDR 49

        // W
        'wa' => ['cardinal' => 2, 'ordinal' => 0],    // Walloon - CLDR 49
        'wae' => ['cardinal' => 1, 'ordinal' => 0],   // Walser - CLDR 49
        'war' => ['cardinal' => 25, 'ordinal' => 2],   // Waray - inherits from 'fil'
        'wls' => ['cardinal' => 0, 'ordinal' => 0],   // Wallisian
        'wo' => ['cardinal' => 0, 'ordinal' => 0],    // Wolof

        // X
        'xh' => ['cardinal' => 1, 'ordinal' => 0],    // Xhosa
        'xog' => ['cardinal' => 1, 'ordinal' => 0],   // Soga - CLDR 49

        // Y
        'ydd' => ['cardinal' => 1, 'ordinal' => 0],    // Eastern Yiddish - inherits from 'yi'
        'yi' => ['cardinal' => 1, 'ordinal' => 0],    // Yiddish
        'ymm' => ['cardinal' => 1, 'ordinal' => 0],    // Maay Maay - inherits from 'so'
        'yo' => ['cardinal' => 0, 'ordinal' => 0],    // Yoruba - CLDR 49: other only
        'yue' => ['cardinal' => 0, 'ordinal' => 0],   // Cantonese - CLDR 49

        // Z
        'zdj' => ['cardinal' => 13, 'ordinal' => 0],   // Ngazidja Comorian - inherits from 'ar'
        'zh' => ['cardinal' => 0, 'ordinal' => 0],    // Chinese
        'zsm' => ['cardinal' => 0, 'ordinal' => 2],    // Standard Malay - inherits from 'ms'
        'zu' => ['cardinal' => 2, 'ordinal' => 0],    // Zulu - CLDR 49: one = i = 0 or n = 1
    ];

    // =========================================================================
    // Cardinal integer API — delegates to CardinalIntegerRule
    // =========================================================================

    /** @see CardinalIntegerRule::getFormIndex() */
    public static function getCardinalFormIndex(string $locale, int $n): int
    {
        return CardinalIntegerRule::getFormIndex($locale, $n, static::getRuleGroup($locale));
    }

    /** @see CardinalIntegerRule::getCategoryName() */
    public static function getCardinalCategoryName(string $locale, int $n): string
    {
        return CardinalIntegerRule::getCategoryName($locale, $n);
    }

    /** @return array<string>
     * @see CardinalIntegerRule::getCategories() */
    public static function getCardinalCategories(string $locale): array
    {
        return CardinalIntegerRule::getCategories($locale);
    }

    /** @see CardinalIntegerRule::getPluralCount() */
    public static function getPluralCount(string $locale): int
    {
        return CardinalIntegerRule::getPluralCount($locale);
    }

    // =========================================================================
    // Cardinal decimal API — delegates to CardinalDecimalRule
    // =========================================================================

    /** @see CardinalDecimalRule::getFormIndexForNumber() */
    public static function getCardinalFormIndexForNumber(string $locale, string|int|float $number): int
    {
        return CardinalDecimalRule::getFormIndexForNumber($locale, $number);
    }

    /** @see CardinalDecimalRule::getCategoryNameForNumber() */
    public static function getCardinalCategoryNameForNumber(string $locale, string|int|float $number): string
    {
        return CardinalDecimalRule::getCategoryNameForNumber($locale, $number);
    }

    // =========================================================================
    // Ordinal API — delegates to OrdinalRule
    // =========================================================================

    /** @see OrdinalRule::getFormIndex() */
    public static function getOrdinalFormIndex(string $locale, int $n): int
    {
        return OrdinalRule::getFormIndex($locale, $n);
    }

    /** @see OrdinalRule::getFormIndexForNumber() */
    public static function getOrdinalFormIndexForNumber(string $locale, string|int|float $number): int
    {
        return OrdinalRule::getFormIndexForNumber($locale, $number);
    }

    /** @see OrdinalRule::getCategoryName() */
    public static function getOrdinalCategoryName(string $locale, int $n): string
    {
        return OrdinalRule::getCategoryName($locale, $n);
    }

    /** @see OrdinalRule::getCategoryNameForNumber() */
    public static function getOrdinalCategoryNameForNumber(string $locale, string|int|float $number): string
    {
        return OrdinalRule::getCategoryNameForNumber($locale, $number);
    }

    /** @return array<string>
     * @see OrdinalRule::getCategories() */
    public static function getOrdinalCategories(string $locale): array
    {
        return OrdinalRule::getCategories($locale);
    }

    // =========================================================================
    // Shared utilities
    // =========================================================================

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

}

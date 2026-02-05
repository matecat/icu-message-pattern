<?php

declare(strict_types=1);

namespace Matecat\ICU\PluralRules;

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
     * Mapping of plural rule group => array of category names indexed by plural form number.
     *
     * Each rule group returns indices 0, 1, 2, etc. from calculate().
     * This map translates those indices to CLDR category names.
     *
     * @var array<int, array<int, string>>
     */
    protected static array $_categoryMap = [
        // Rule 0: nplurals=1; (Asian, no plural forms)
        // Only "other" form exists
        0 => [self::CATEGORY_OTHER],

        // Rule 1: nplurals=2; plural=(n != 1); (Germanic, most European)
        // 0=one (n==1), 1=other (n!=1)
        1 => [self::CATEGORY_ONE, self::CATEGORY_OTHER],

        // Rule 2: nplurals=2; plural=(n > 1); (French, Brazilian Portuguese)
        // 0=one (n<=1), 1=other (n>1)
        2 => [self::CATEGORY_ONE, self::CATEGORY_OTHER],

        // Rule 3: nplurals=3; (Slavic - Russian, Ukrainian, etc.)
        // 0=one, 1=few, 2=many
        3 => [self::CATEGORY_ONE, self::CATEGORY_FEW, self::CATEGORY_MANY],

        // Rule 4: nplurals=3; (Czech, Slovak)
        // 0=one, 1=few, 2=other
        4 => [self::CATEGORY_ONE, self::CATEGORY_FEW, self::CATEGORY_OTHER],

        // Rule 5: nplurals=5; (Irish)
        // 0=one, 1=two, 2=few, 3=many, 4=other
        5 => [self::CATEGORY_ONE, self::CATEGORY_TWO, self::CATEGORY_FEW, self::CATEGORY_MANY, self::CATEGORY_OTHER],

        // Rule 6: nplurals=3; (Lithuanian)
        // 0=one, 1=few, 2=other
        6 => [self::CATEGORY_ONE, self::CATEGORY_FEW, self::CATEGORY_OTHER],

        // Rule 7: nplurals=4; (Slovenian)
        // 0=one, 1=two, 2=few, 3=other
        7 => [self::CATEGORY_ONE, self::CATEGORY_TWO, self::CATEGORY_FEW, self::CATEGORY_OTHER],

        // Rule 8: nplurals=3; (Macedonian)
        // 0=one, 1=two, 2=other
        8 => [self::CATEGORY_ONE, self::CATEGORY_TWO, self::CATEGORY_OTHER],

        // Rule 9: nplurals=4; (Maltese)
        // 0=one, 1=few, 2=many, 3=other
        9 => [self::CATEGORY_ONE, self::CATEGORY_FEW, self::CATEGORY_MANY, self::CATEGORY_OTHER],

        // Rule 10: nplurals=3; (Latvian)
        // 0=one, 1=other, 2=zero
        10 => [self::CATEGORY_ONE, self::CATEGORY_OTHER, self::CATEGORY_ZERO],

        // Rule 11: nplurals=3; (Polish)
        // 0=one, 1=few, 2=many
        11 => [self::CATEGORY_ONE, self::CATEGORY_FEW, self::CATEGORY_MANY],

        // Rule 12: nplurals=3; (Romanian)
        // 0=one, 1=few, 2=other
        12 => [self::CATEGORY_ONE, self::CATEGORY_FEW, self::CATEGORY_OTHER],

        // Rule 13: nplurals=6; (Arabic)
        // 0=zero, 1=one, 2=two, 3=few, 4=many, 5=other
        13 => [
            self::CATEGORY_ZERO,
            self::CATEGORY_ONE,
            self::CATEGORY_TWO,
            self::CATEGORY_FEW,
            self::CATEGORY_MANY,
            self::CATEGORY_OTHER
        ],

        // Rule 14: nplurals=4; (Welsh)
        // 0=one, 1=two, 2=few, 3=other
        14 => [self::CATEGORY_ONE, self::CATEGORY_TWO, self::CATEGORY_FEW, self::CATEGORY_OTHER],

        // Rule 15: nplurals=2; (Icelandic)
        // 0=one, 1=other
        15 => [self::CATEGORY_ONE, self::CATEGORY_OTHER],

        // Rule 16: nplurals=4; (Scottish Gaelic)
        // 0=one, 1=two, 2=few, 3=other
        16 => [self::CATEGORY_ONE, self::CATEGORY_TWO, self::CATEGORY_FEW, self::CATEGORY_OTHER],
    ];

    /**
     * A map of locale => plurals group used to determine
     * which plural rules apply to the language
     *
     * Plural Rules:
     * 0  - nplurals=1; plural=0; (Asian, no plural forms)
     * 1  - nplurals=2; plural=(n != 1); (Germanic, most European)
     * 2  - nplurals=2; plural=(n > 1); (French, Brazilian Portuguese)
     * 3  - nplurals=3; plural=(n%10==1 && n%100!=11 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2); (Slavic)
     * 4  - nplurals=3; plural=(n==1) ? 0 : (n>=2 && n<=4) ? 1 : 2; (Czech, Slovak)
     * 5  - nplurals=5; plural=n==1 ? 0 : n==2 ? 1 : (n>2 && n<7) ? 2 :(n>6 && n<11) ? 3 : 4; (Irish)
     * 6  - nplurals=3; plural=(n%10==1 && n%100!=11 ? 0 : n%10>=2 && (n%100<10 || n%100>=20) ? 1 : 2); (Lithuanian)
     * 7  - nplurals=4; plural=(n%100==1 ? 0 : n%100==2 ? 1 : n%100==3 || n%100==4 ? 2 : 3); (Slovenian)
     * 8  - nplurals=3; plural=(n%10==1 ? 0 : n%10==2 ? 1 : 2); (Macedonian - simplified)
     * 9  - nplurals=4; plural=(n==1 ? 0 : n==0 || (n%100>0 && n%100<=10) ? 1 : (n%100>10 && n%100<20) ? 2 : 3); (Maltese)
     * 10 - nplurals=3; plural=(n%10==1 && n%100!=11 ? 0 : n != 0 ? 1 : 2); (Latvian)
     * 11 - nplurals=3; plural=(n==1 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2); (Polish)
     * 12 - nplurals=3; plural=(n==1 ? 0 : n==0 || n%100>0 && n%100<20 ? 1 : 2); (Romanian)
     * 13 - nplurals=6; plural=(n==0 ? 0 : n==1 ? 1 : n==2 ? 2 : n%100>=3 && n%100<=10 ? 3 : n%100>=11 ? 4 : 5); (Arabic)
     * 14 - nplurals=4; plural=(n==1) ? 0 : (n==2) ? 1 : (n != 8 && n != 11) ? 2 : 3; (Welsh)
     * 15 - nplurals=2; plural=(n%10!=1 || n%100==11); (Icelandic)
     * 16 - nplurals=4; plural=(n==1 || n==11) ? 0 : (n==2 || n==12) ? 1 : (n>2 && n<20) ? 2 : 3; (Scottish Gaelic)
     *
     * @var array<string, int>
     */
    protected static array $_rulesMap = [
        'ace' => 0,  // Acehnese - no plural
        'acf' => 2,  // Saint Lucian Creole French - French-based creole
        'af' => 1,   // Afrikaans
        'aig' => 1,  // Antigua and Barbuda Creole English - English-based creole
        'ak' => 2,   // Akan
        'als' => 1,  // Albanian (Tosk) - same as Albanian
        'am' => 2,   // Amharic
        'an' => 1,   // Aragonese
        'ar' => 13,  // Arabic
        'as' => 1,   // Assamese
        'ast' => 1,  // Asturian
        'awa' => 1,  // Awadhi - Indo-Aryan
        'ayr' => 0,  // Central Aymara - no plural
        'az' => 1,   // Azerbaijani
        'azb' => 1,  // South Azerbaijani
        'azj' => 1,  // North Azerbaijani
        'ba' => 0,   // Bashkir - Turkic, no plural
        'bah' => 1,  // Bahamas Creole English - English-based creole
        'bal' => 1,  // Baluchi
        'ban' => 0,  // Balinese - no plural
        'be' => 3,   // Belarusian
        'bem' => 1,  // Bemba
        'bg' => 1,   // Bulgarian
        'bh' => 2,   // Bihari
        'bho' => 1,  // Bhojpuri
        'bi' => 0,   // Bislama - Creole, no plural
        'bjn' => 0,  // Banjar - no plural
        'bjs' => 1,  // Bajan - English-based creole
        'bm' => 0,   // Bambara - no plural
        'bn' => 1,   // Bengali
        'bo' => 0,   // Tibetan
        'br' => 2,   // Breton
        'brx' => 1,  // Bodo
        'bs' => 3,   // Bosnian
        'bug' => 0,  // Buginese - no plural
        'ca' => 1,   // Catalan
        'cac' => 1,  // Chuj - Mayan
        'cav' => 1,  // Cavineña
        'ce' => 1,   // Chechen
        'ceb' => 1,  // Cebuano
        'ch' => 0,   // Chamorro - no plural
        'chk' => 0,  // Chuukese - no plural
        'chr' => 1,  // Cherokee
        'cjk' => 1,  // Chokwe
        'ckb' => 1,  // Central Kurdish
        'cop' => 1,  // Coptic
        'crh' => 0,  // Crimean Tatar - Turkic, no plural
        'crs' => 2,  // Seselwa Creole French - French-based creole
        'cs' => 4,   // Czech
        'ctg' => 1,  // Chittagonian - Indo-Aryan
        'cy' => 14,  // Welsh
        'da' => 1,   // Danish
        'de' => 1,   // German
        'dik' => 1,  // Southwestern Dinka
        'diq' => 1,  // Dimli
        'doi' => 1,  // Dogri
        'dv' => 1,   // Divehi
        'dyu' => 0,  // Dyula - no plural
        'dz' => 0,   // Dzongkha
        'ee' => 1,   // Ewe
        'el' => 1,   // Greek
        'en' => 1,   // English
        'eo' => 1,   // Esperanto
        'es' => 1,   // Spanish
        'et' => 1,   // Estonian
        'eu' => 1,   // Basque
        'fa' => 2,   // Persian
        'ff' => 1,   // Fulah
        'fi' => 1,   // Finnish
        'fil' => 2,  // Filipino
        'fj' => 0,   // Fijian - no plural
        'fn' => 0,   // Fanagalo - no plural
        'fo' => 1,   // Faroese
        'fon' => 0,  // Fon - no plural
        'fr' => 2,   // French
        'fuc' => 1,  // Pulaar - Fulah dialect
        'fur' => 1,  // Friulian
        'fuv' => 1,  // Nigerian Fulfulde
        'ga' => 5,   // Irish
        'gax' => 1,  // Borana-Arsi-Guji Oromo
        'gaz' => 1,  // West Central Oromo
        'gcl' => 2,  // Grenadian Creole English
        'gd' => 16,  // Scottish Gaelic
        'gil' => 0,  // Gilbertese - no plural
        'gl' => 1,   // Galician
        'glw' => 1,  // Glaro-Twabo
        'gn' => 1,   // Guarani
        'grc' => 1,  // Ancient Greek
        'grt' => 1,  // Garo
        'gu' => 1,   // Gujarati
        'guz' => 1,  // Gusii
        'gv' => 1,   // Manx
        'gyn' => 1,  // Guyanese Creole English
        'ha' => 1,   // Hausa
        'haw' => 1,  // Hawaiian
        'he' => 1,   // Hebrew
        'hi' => 2,   // Hindi
        'hig' => 1,  // Kamwe
        'hil' => 1,  // Hiligaynon
        'hmn' => 0,  // Hmong - no plural
        'hne' => 1,  // Chhattisgarhi
        'hoc' => 1,  // Ho
        'hr' => 3,   // Croatian
        'ht' => 1,   // Haitian Creole
        'hu' => 1,   // Hungarian
        'hy' => 1,   // Armenian
        'id' => 0,   // Indonesian
        'ig' => 0,   // Igbo - no plural
        'ilo' => 1,  // Ilocano
        'is' => 15,  // Icelandic
        'it' => 1,   // Italian
        'ja' => 0,   // Japanese
        'jam' => 1,  // Jamaican Creole English
        'jv' => 0,   // Javanese
        'ka' => 0,   // Georgian
        'kab' => 2,  // Kabyle
        'kac' => 0,  // Kachin - no plural
        'kam' => 1,  // Kamba
        'kar' => 0,  // Karen - no plural
        'kas' => 1,  // Kashmiri
        'kbp' => 0,  // Kabiyè - no plural
        'kea' => 0,  // Kabuverdianu - no plural
        'kg' => 1,   // Kongo
        'kha' => 1,  // Khasi
        'khk' => 1,  // Halh Mongolian
        'ki' => 1,   // Kikuyu
        'kjb' => 1,  // Q'anjob'al - Mayan
        'kk' => 1,   // Kazakh
        'kl' => 1,   // Greenlandic
        'kln' => 1,  // Kalenjin
        'km' => 0,   // Khmer
        'kmb' => 1,  // Kimbundu
        'kmr' => 1,  // Northern Kurdish
        'kn' => 1,   // Kannada
        'knc' => 1,  // Central Kanuri
        'ko' => 0,   // Korean
        'kok' => 1,  // Konkani
        'kr' => 0,   // Kanuri - no plural
        'ks' => 1,   // Kashmiri
        'ksw' => 0,  // S'gaw Karen - no plural
        'ky' => 1,   // Kyrgyz
        'la' => 1,   // Latin
        'lb' => 1,   // Luxembourgish
        'lg' => 1,   // Ganda
        'li' => 1,   // Limburgish
        'lij' => 1,  // Ligurian
        'lmo' => 1,  // Lombard
        'ln' => 2,   // Lingala
        'lo' => 0,   // Lao
        'lt' => 6,   // Lithuanian
        'ltg' => 10, // Latgalian - same as Latvian
        'lua' => 1,  // Luba-Lulua
        'luo' => 1,  // Luo
        'lus' => 1,  // Mizo
        'luy' => 1,  // Luyia
        'lv' => 10,  // Latvian
        'lvs' => 10, // Standard Latvian
        'mag' => 1,  // Magahi
        'mai' => 1,  // Maithili
        'mam' => 1,  // Mam - Mayan
        'mas' => 1,  // Maasai
        'men' => 1,  // Mende
        'mer' => 1,  // Meru
        'mfe' => 2,  // Mauritian Creole
        'mfi' => 1,  // Wandala
        'mfv' => 1,  // Mandjak
        'mg' => 2,   // Malagasy
        'mh' => 0,   // Marshallese - no plural
        'mhr' => 1,  // Eastern Mari
        'mi' => 2,   // Maori
        'min' => 0,  // Minangkabau - no plural
        'mk' => 8,   // Macedonian
        'ml' => 1,   // Malayalam
        'mn' => 1,   // Mongolian
        'mni' => 1,  // Manipuri
        'mnk' => 1,  // Mandinka
        'mos' => 0,  // Mossi - no plural
        'mr' => 1,   // Marathi
        'mrj' => 1,  // Western Mari
        'mrt' => 1,  // Marghi Central
        'ms' => 0,   // Malay
        'mt' => 9,   // Maltese
        'my' => 0,   // Burmese
        'nb' => 1,   // Norwegian Bokmål
        'nd' => 1,   // North Ndebele
        'ndc' => 1,  // Ndau
        'ne' => 1,   // Nepali
        'niu' => 0,  // Niuean - no plural
        'nl' => 1,   // Dutch
        'nn' => 1,   // Norwegian Nynorsk
        'nr' => 1,   // South Ndebele
        'nso' => 2,  // Northern Sotho
        'nup' => 1,  // Nupe
        'nus' => 1,  // Nuer
        'ny' => 1,   // Nyanja (Chichewa)
        'nyf' => 1,  // Giryama
        'oc' => 2,   // Occitan
        'om' => 1,   // Oromo
        'or' => 1,   // Oriya
        'ory' => 1,  // Odia (Oriya)
        'pa' => 1,   // Punjabi
        'pag' => 1,  // Pangasinan
        'pap' => 1,  // Papiamento
        'pau' => 0,  // Palauan - no plural
        'pbt' => 1,  // Southern Pashto
        'pi' => 1,   // Pali
        'pis' => 0,  // Pijin - no plural
        'pko' => 1,  // Pökoot
        'pl' => 11,  // Polish
        'plt' => 2,  // Plateau Malagasy
        'pon' => 0,  // Pohnpeian - no plural
        'pot' => 1,  // Potawatomi
        'pov' => 1,  // Guinea-Bissau Creole
        'ppk' => 0,  // Uma - no plural
        'prs' => 2,  // Dari - same as Persian
        'ps' => 1,   // Pashto
        'pt' => 1,   // Portuguese
        'qu' => 1,   // Quechua
        'quc' => 1,  // K'iche' - Mayan
        'quy' => 1,  // Ayacucho Quechua
        'rhg' => 1,  // Rohingya
        'rhl' => 1,  // Rohingya (alternate)
        'rmn' => 3,  // Balkan Romani - Slavic influence
        'rmo' => 1,  // Sinte Romani
        'rn' => 1,   // Rundi
        'ro' => 12,  // Romanian
        'roh' => 1,  // Romansh
        'ru' => 3,   // Russian
        'run' => 1,  // Rundi (alternate)
        'rw' => 1,   // Kinyarwanda
        'sa' => 1,   // Sanskrit
        'sat' => 1,  // Santali
        'sc' => 1,   // Sardinian
        'scn' => 1,  // Sicilian
        'sd' => 1,   // Sindhi
        'seh' => 1,  // Sena
        'sg' => 0,   // Sango - no plural
        'sh' => 3,   // Serbo-Croatian
        'shn' => 0,  // Shan - no plural
        'shu' => 13, // Chadian Arabic - Arabic
        'si' => 1,   // Sinhala
        'sk' => 4,   // Slovak
        'sl' => 7,   // Slovenian
        'sm' => 0,   // Samoan - no plural
        'sn' => 1,   // Shona
        'snk' => 1,  // Soninke
        'so' => 1,   // Somali
        'sq' => 1,   // Albanian
        'sr' => 3,   // Serbian
        'srn' => 1,  // Sranan Tongo
        'ss' => 1,   // Swati
        'ssy' => 1,  // Saho
        'st' => 1,   // Southern Sotho
        'su' => 0,   // Sundanese
        'sus' => 0,  // Susu - no plural
        'sv' => 1,   // Swedish
        'svc' => 1,  // Vincentian Creole English
        'sw' => 1,   // Swahili
        'syc' => 1,  // Classical Syriac
        'szl' => 11, // Silesian - Polish-like
        'ta' => 1,   // Tamil
        'taq' => 0,  // Tamasheq - no plural
        'te' => 1,   // Telugu
        'tet' => 1,  // Tetum
        'tg' => 2,   // Tajik
        'th' => 0,   // Thai
        'ti' => 2,   // Tigrinya
        'tiv' => 1,  // Tiv
        'tk' => 1,   // Turkmen
        'tkl' => 0,  // Tokelau - no plural
        'tl' => 2,   // Tagalog - same as Filipino
        'tmh' => 0,  // Tamashek - no plural
        'tn' => 1,   // Tswana
        'to' => 0,   // Tongan - no plural
        'tpi' => 0,  // Tok Pisin - no plural
        'tr' => 2,   // Turkish
        'trv' => 0,  // Taroko - no plural
        'ts' => 1,   // Tsonga
        'tsc' => 1,  // Tswa
        'tt' => 0,   // Tatar
        'tum' => 1,  // Tumbuka
        'tvl' => 0,  // Tuvalu - no plural
        'tw' => 2,   // Twi - Akan
        'ty' => 0,   // Tahitian - no plural
        'tzm' => 2,  // Central Atlas Tamazight
        'udm' => 1,  // Udmurt
        'ug' => 0,   // Uyghur
        'uk' => 3,   // Ukrainian
        'umb' => 1,  // Umbundu
        'ur' => 1,   // Urdu
        'uz' => 2,   // Uzbek
        'uzn' => 2,  // Northern Uzbek
        'vec' => 1,  // Venetian
        'vi' => 0,   // Vietnamese
        'vic' => 1,  // Virgin Islands Creole English
        'vls' => 1,  // Vlaams (West Flemish)
        'vmw' => 1,  // Makhuwa
        'war' => 1,  // Waray
        'wls' => 0,  // Wallisian - no plural
        'wo' => 0,   // Wolof
        'xh' => 1,   // Xhosa
        'ydd' => 1,  // Eastern Yiddish
        'yi' => 1,   // Yiddish
        'yo' => 1,   // Yoruba
        'zdj' => 1,  // Ngazidja Comorian
        'zh' => 0,   // Chinese
        'zsm' => 0,  // Standard Malay - same as Malay
        'zu' => 1,   // Zulu
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
        $ruleGroup = self::getRuleGroup($locale);

        switch ($ruleGroup) {
            case 0:
                // nplurals=1; plural=0; (Asian, no plural forms)
                return 0;
            case 1:
                // nplurals=2; plural=(n != 1); (Germanic, most European)
                return $n === 1 ? 0 : 1;
            case 2:
                // nplurals=2; plural=(n > 1); (French, Brazilian Portuguese)
                return $n > 1 ? 1 : 0;
            case 3:
                // nplurals=3; Slavic (Russian, Ukrainian, Belarusian, Serbian, Croatian)
                return $n % 10 === 1 && $n % 100 !== 11 ? 0 :
                    (($n % 10 >= 2 && $n % 10 <= 4) && ($n % 100 < 10 || $n % 100 >= 20) ? 1 : 2);
            case 4:
                // nplurals=3; (Czech, Slovak)
                return $n === 1 ? 0 :
                    ($n >= 2 && $n <= 4 ? 1 : 2);
            case 5:
                // nplurals=5; (Irish)
                return $n === 1 ? 0 :
                    ($n === 2 ? 1 : ($n < 7 ? 2 : ($n < 11 ? 3 : 4)));
            case 6:
                // nplurals=3; (Lithuanian)
                return $n % 10 === 1 && $n % 100 !== 11 ? 0 :
                    ($n % 10 >= 2 && ($n % 100 < 10 || $n % 100 >= 20) ? 1 : 2);
            case 7:
                // nplurals=4; (Slovenian)
                return $n % 100 === 1 ? 0 :
                    ($n % 100 === 2 ? 1 : ($n % 100 === 3 || $n % 100 === 4 ? 2 : 3));
            case 8:
                // nplurals=3; (Macedonian)
                return $n % 10 === 1 && $n % 100 !== 11 ? 0 : ($n % 10 === 2 && $n % 100 !== 12 ? 1 : 2);
            case 9:
                // nplurals=4; (Maltese)
                return $n === 1 ? 0 :
                    ($n === 0 || ($n % 100 > 0 && $n % 100 <= 10) ? 1 :
                        ($n % 100 > 10 && $n % 100 < 20 ? 2 : 3));
            case 10:
                // nplurals=3; (Latvian)
                return $n % 10 === 1 && $n % 100 !== 11 ? 0 : ($n !== 0 ? 1 : 2);
            case 11:
                // nplurals=3; (Polish)
                return $n === 1 ? 0 :
                    ($n % 10 >= 2 && $n % 10 <= 4 && ($n % 100 < 10 || $n % 100 >= 20) ? 1 : 2);
            case 12:
                // nplurals=3; (Romanian)
                return $n === 1 ? 0 :
                    ($n === 0 || ($n % 100 > 0 && $n % 100 < 20) ? 1 : 2);
            case 13:
                // nplurals=6; (Arabic)
                return $n === 0 ? 0 :
                    ($n === 1 ? 1 :
                        ($n === 2 ? 2 :
                            ($n % 100 >= 3 && $n % 100 <= 10 ? 3 :
                                ($n % 100 >= 11 ? 4 : 5))));
            case 14:
                // nplurals=4; (Welsh)
                return $n === 1 ? 0 :
                    ($n === 2 ? 1 :
                        ($n !== 8 && $n !== 11 ? 2 : 3));
            case 15:
                // nplurals=2; (Icelandic)
                return $n % 10 !== 1 || $n % 100 === 11 ? 1 : 0;
            case 16:
                // nplurals=4; (Scottish Gaelic)
                return ($n === 1 || $n === 11) ? 0 :
                    (($n === 2 || $n === 12) ? 1 :
                        (($n > 2 && $n < 20) ? 2 : 3));
        }

        // @codeCoverageIgnoreStart
        throw new RuntimeException('Unable to find plural rule number.');
        // @codeCoverageIgnoreEnd
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
    public static function getCategoryName(string $locale, int $n): string
    {
        $pluralIndex = self::calculate($locale, $n);
        $ruleGroup = self::getRuleGroup($locale);

        return self::$_categoryMap[$ruleGroup][$pluralIndex] ?? self::CATEGORY_OTHER;
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
    public static function getCategories(string $locale): array
    {
        $ruleGroup = self::getRuleGroup($locale);

        return self::$_categoryMap[$ruleGroup] ?? [self::CATEGORY_OTHER];
    }

    /**
     * Returns the plural rule group number for a given locale.
     *
     * @param string $locale The locale to get the rule group for.
     * @return int The rule group number (0-16).
     */
    protected static function getRuleGroup(string $locale): int
    {
        $locale = strtolower($locale);

        if (!isset(static::$_rulesMap[$locale])) {
            $locale = explode('_', $locale)[0];
        }

        if (!isset(static::$_rulesMap[$locale])) {
            $locale = explode('-', $locale)[0];
        }

        return static::$_rulesMap[$locale] ?? 0;
    }
}

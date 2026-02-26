<?php

namespace Matecat\Locales;

use RuntimeException;

class Languages
{
    private static ?Languages $instance = null;

    /** @var array<string, string> associative map on language names -> codes */
    private static array $mapString2rfc = [];

    /** @var array<string, array<string, mixed>> internal support map rfc -> language data */
    private static array $mapRfc2obj = [];

    /*
     * Associative map iso → rfc codes.
     *
     * IMPORTANT:
     *
     * This map is an approximation and should not be used to resolve specific language variants.
     * For example, the English has more than 6 variants (ex: en -> en-??)
     *
     * This map should be used only to allow internal code to work even for general language codes.
     *
     */
    /** @var array<string, string> */
    private static array $mapIso2rfc = [];

    /** @var array<int|string, array<string, mixed>> the complete JSON struct */
    private static array $languagesDefinition = [];

    /** @var array<string, string> */
    private static array $ocrSupported = [];

    /** @var array<string, string> */
    private static array $ocrNotSupported = [];

    /** @var array<string, array{code: string, name: string, direction: string}> */
    private static array $enabledLanguageList = [];

    /**
     * Languages constructor.
     */
    private function __construct()
    {
        self::$languagesDefinition = $this->loadLanguageDefinitions();
        $this->buildLocalizationMaps();
        $this->buildSupportMaps();
        $this->buildEnabledLanguageList();
    }

    /**
     * Load and parse the language definitions JSON file.
     *
     * @return array<int, array<string, mixed>>
     */
    private function loadLanguageDefinitions(): array
    {
        $file = __DIR__ . '/supported_langs.json';
        // @codeCoverageIgnoreStart
        if (!file_exists($file)) {
            throw new RuntimeException("no language defs found in $file");
        }
        // @codeCoverageIgnoreEnd
        $string = file_get_contents($file);
        // @codeCoverageIgnoreStart
        if ($string === false) {
            throw new RuntimeException("Failed to read language defs from $file");
        }
        // @codeCoverageIgnoreEnd

        /** @var array{langs: array<int, array<string, mixed>>} $langs */
        $langs = json_decode($string, true);

        return $langs['langs'];
    }

    /**
     * Build internal localization maps (string->rfc, OCR support) from language definitions.
     */
    private function buildLocalizationMaps(): void
    {
        foreach (self::$languagesDefinition as $k1 => $lang) {
            foreach ($lang['localized'] as $k2 => $localizedTagPair) {
                $this->processLocalizedTagPair($k1, $lang, $localizedTagPair);
                unset(self::$languagesDefinition[$k1]['localized'][$k2]);
            }
        }
    }

    /**
     * Process a single localized tag pair, updating maps and OCR support.
     *
     * @param int|string $langIndex
     * @param array<string, mixed> $lang
     * @param array<string, string> $localizedTagPair
     */
    private function processLocalizedTagPair(int|string $langIndex, array $lang, array $localizedTagPair): void
    {
        foreach ($localizedTagPair as $isocode => $localizedTag) {
            self::$mapString2rfc[$localizedTag] = $lang['rfc3066code'];
            self::$languagesDefinition[$langIndex]['localized'][$isocode] = $localizedTag;

            // ocr support
            if ($lang['ocr']['supported'] === true) {
                self::$ocrSupported[$localizedTag] = $lang['rfc3066code'];
            }

            // @codeCoverageIgnoreStart
            if ($lang['ocr']['not_supported_or_rtl'] === true) {
                self::$ocrNotSupported[$localizedTag] = $lang['rfc3066code'];
            }
            // @codeCoverageIgnoreEnd
        }
    }

    /**
     * Build internal support maps (rfc->obj, iso->rfc, string->rfc).
     *
     * The iso->rfc map is inherently ambiguous because a single ISO 639-1 code
     * (e.g. "en") can correspond to multiple RFC 3066 regional variants
     * (e.g. "en-US", "en-GB", "en-AU", etc.). During the loop, each language
     * definition overwrites the previous mapping for the same ISO code, so the
     * final value is non-deterministic and depends on the order of entries in the
     * JSON file.
     *
     * To resolve this, we apply a set of hardcoded overrides after the loop that
     * pin each ambiguous ISO code to a single "default" regional variant. This is
     * an approximation: it allows internal code to work with bare ISO codes, but
     * it does NOT represent a linguistically correct or exhaustive mapping. Any
     * caller that needs a specific regional variant should use the full RFC code.
     */
    private function buildSupportMaps(): void
    {
        foreach (self::$languagesDefinition as $lang) {
            // map the optional language-region code (e.g. "it_IT") to its RFC code
            if (isset($lang['languageRegionCode'])) {
                self::$mapString2rfc[$lang['languageRegionCode']] = $lang['rfc3066code'];
            }

            // identity mapping: RFC code resolves to itself
            self::$mapString2rfc[$lang['rfc3066code']] = $lang['rfc3066code'];
            // primary lookup: RFC code -> full language data object
            self::$mapRfc2obj[$lang['rfc3066code']] = $lang;
            // ISO -> RFC (last write wins; overridden below for ambiguous codes)
            self::$mapIso2rfc[$lang['isocode']] = $lang['rfc3066code'];
        }

        // Hardcoded overrides for ambiguous ISO codes.
        // Each bare ISO code is pinned to a single default regional variant.
        // For example, "en" could be en-US, en-GB, en-AU, etc.; we pick en-US
        // as a reasonable default so that callers passing just "en" get a valid
        // RFC code rather than whichever variant happened to be loaded last.
        self::$mapIso2rfc['en'] = 'en-US';
        self::$mapIso2rfc['sp'] = 'sp-SP';
        self::$mapIso2rfc['pt'] = 'pt-PT';
        self::$mapIso2rfc['fr'] = 'fr-FR';
        self::$mapIso2rfc['ar'] = 'ar-SA';
        self::$mapIso2rfc['zh'] = 'zh-CN';
        self::$mapIso2rfc['it'] = 'it-IT';
    }

    /**
     * Build the list of enabled languages, sorted alphabetically by name.
     */
    private function buildEnabledLanguageList(): void
    {
        foreach (self::$mapRfc2obj as $rfc => $lang) {
            if ($lang['enabled']) {
                self::$enabledLanguageList[$rfc] = [
                    'code' => $rfc,
                    'name' => $lang['localized']['en'],
                    'direction' => ($lang['rtl']) ? 'rtl' : 'ltr'
                ];
            }
        }

        uasort(self::$enabledLanguageList, function ($a, $b) {
            return strcmp($a['name'], $b['name']);
        });
    }

    /**
     * @return Languages
     */
    public static function getInstance(): Languages
    {
        if (!self::$instance) {
            self::$instance = new Languages();
        }

        return self::$instance;
    }

    /**
     * Check if a language is RTL
     *
     * @param string $code
     *
     * @return bool
     */
    public static function isRTL(string $code): bool
    {
        //convert ISO code in RFC
        $code = self::getInstance()->normalizeLanguageCode($code);

        return self::$mapRfc2obj[$code]['rtl'];
    }

    /**
     * Check if the language is enabled
     *
     * @param string $code
     *
     * @return bool
     */
    public function isEnabled(string $code): bool
    {
        //convert ISO code in RFC
        $code = $this->normalizeLanguageCode($code);
        return self::$mapRfc2obj[$code]['enabled'] ?? false;
    }

    /**
     * get the corresponding Language-Region code given the localized name
     * http://www.rfc-editor.org/rfc/rfc5646.txt
     * http://www.w3.org/International/articles/language-tags/
     */
    public function getLangRegionCode(string $localizedName): string
    {
        $value = self::$mapRfc2obj[self::$mapString2rfc[$localizedName]]['languageRegionCode'] ?? null;
        if (empty($value)) {
            $value = $this->get3066Code($localizedName);
        }

        return $value;
    }

    /**
     * get a list of languages, as RFC3066
     *
     * @param string $localizedName
     *
     * @return string
     */
    public function get3066Code(string $localizedName): string
    {
        return self::$mapString2rfc[$localizedName];
    }

    /**
     * get a list of languages, as ISO Code
     *
     * @param string $localizedName
     *
     * @return string
     */
    public function getIsoCode(string $localizedName): string
    {
        return self::$mapRfc2obj[self::$mapString2rfc[$localizedName]]['isocode'];
    }

    /**
     * get a list of enabled languages ordered by name alphabetically, the keys are the language rfc3066code codes
     *
     * @return array<string, array{code: string, name: string, direction: string}>
     */
    public function getEnabledLanguages(): array
    {
        return self::$enabledLanguageList;
    }

    /**
     *
     * Get the corresponding ISO 639-1 code given a localized name
     *
     * @param string $code
     * @param string|null $lang
     *
     * @return string|null
     */
    public function getLocalizedName(string $code, ?string $lang = 'en'): ?string
    {
        $code = $this->normalizeLanguageCode($code);

        return self::$mapRfc2obj[$code]['localized'][$lang];
    }

    /**
     *
     * Be strict when and only find a localized name with an RFC expected input
     *
     * @param string|null $code
     * @param string|null $lang
     *
     * @return string|null
     * @throws InvalidLanguageException
     */
    public function getLocalizedNameRFC(?string $code = null, ?string $lang = 'en'): ?string
    {
        if ($code === null || !array_key_exists($code, self::$mapRfc2obj)) {
            throw new InvalidLanguageException('Invalid language code: ' . $code);
        }

        return self::$mapRfc2obj[$code]['localized'][$lang] ?? null;
    }

    /**
     * Returns a list of RTL language codes
     *
     * @return array<int, string>
     */
    public function getRTLLangs(): array
    {
        $acc = [];
        foreach (self::$mapRfc2obj as $code => $value) {
            if ($value['rtl'] && $value['enabled']) {
                $acc[] = $code;
            }
        }

        return $acc;
    }

    /**
     * @throws InvalidLanguageException
     */
    public function validateLanguage(?string $code = null): string
    {
        if (empty($code)) {
            throw new InvalidLanguageException("Missing language.", -3);
        }

        $code = $this->normalizeLanguageCode($code);

        if ($code === null || !array_key_exists($code, self::$mapRfc2obj)) {
            throw new InvalidLanguageException('Invalid language code: ' . $code);
        }

        if (!$this->isEnabled($code)) {
            throw new InvalidLanguageException('Language not enabled: ' . $code);
        }

        return $code;
    }

    /**
     * @param array<int, string> $languageList
     * @return array<int, string>
     * @throws InvalidLanguageException
     */
    public function validateLanguageList(array $languageList): array
    {
        if (empty($languageList)) {
            throw new InvalidLanguageException("Empty language list.", -3);
        }

        $langList = [];
        foreach ($languageList as $language) {
            $langList[] = $this->validateLanguage($language);
        }

        return $langList;
    }

    /**
     * @param non-empty-string $separator
     * @throws InvalidLanguageException
     */
    public function validateLanguageListAsString(string $languageList, string $separator = ','): string
    {
        $targets = explode($separator, $languageList);
        $targets = array_map('trim', $targets);
        $targets = array_unique($targets);

        return implode(',', $this->validateLanguageList($targets));
    }

    /**
     * @param string $languageCode
     *
     * @return string|null
     */
    protected function normalizeLanguageCode(string $languageCode): ?string
    {
        $langParts = explode('-', $languageCode);

        $langParts[0] = trim(strtolower($langParts[0]));

        if (sizeof($langParts) == 1) {
            /*
             *  IMPORTANT: Pick the first language region. This is an approximation. Use this only to normalize the language code.
             */
            return self::$mapIso2rfc[$langParts[0]] ?? null;
        } elseif (sizeof($langParts) == 2) {
            $langParts[1] = trim(strtoupper($langParts[1]));
        } elseif (sizeof($langParts) == 3) {
            $langParts[1] = ucfirst(trim(strtolower($langParts[1])));
            $langParts[2] = trim(strtoupper($langParts[2]));
        } else {
            return null;
        }

        return implode("-", $langParts);
    }

    /**
     * @param string $language
     *
     * @return bool
     */
    public static function isValidLanguage(string $language): bool
    {
        $language = self::getInstance()->normalizeLanguageCode($language);

        if ($language === null) {
            return false;
        }

        return array_key_exists($language, self::$mapRfc2obj);
    }

    /**
     * @param string $rfc3066code
     *
     * @return string|null
     */
    public static function getLocalizedLanguage(string $rfc3066code): ?string
    {
        foreach (self::$languagesDefinition as $lang) {
            if ($lang['rfc3066code'] === $rfc3066code) {
                return $lang['localized']['en'] ?? null;
            }
        }

        return null;
    }

    /**
     * Examples:
     *
     * it-IT ---> it
     * es-419 ---> es
     * to-TO ----> ton
     *
     * @param string $code
     *
     * @return string|null
     */
    public static function convertLanguageToIsoCode(string $code): ?string
    {
        $code = self::getInstance()->normalizeLanguageCode($code);

        return self::$mapRfc2obj[$code]['isocode'] ?? null;
    }

    /**
     * @return array<string, string>
     */
    public static function getLanguagesWithOcrSupported(): array
    {
        return self::$ocrSupported;
    }

    /**
     * @return array<string, string>
     */
    public static function getLanguagesWithOcrNotSupported(): array
    {
        return self::$ocrNotSupported;
    }
}


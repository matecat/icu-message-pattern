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
     * Base directory for runtime JSON resource files.
     */
    private const string RESOURCES_DIR = __DIR__ . '/../resources';

    /**
     * Base directory for build-time JSON inputs (CLDR data, lookups, overrides).
     */
    private const string BUILD_DIR = self::RESOURCES_DIR . '/build';

    /**
     * The default file path for the cached pluralRules.json.
     */
    private const string DEFAULT_FILE_PATH = self::RESOURCES_DIR . '/pluralRules.json';

    /**
     * The file path for per-language overrides of example strings.
     *
     * This file contains only the deltas: human_rule and example fields that differ
     * from the CLDR defaults resolved via the lookup tables.
     * Overrides are keyed by category name (e.g. "one", "other") rather
     * than positional index, making them resilient to category reordering.
     */
    private const string OVERRIDES_FILE_PATH = self::BUILD_DIR . '/pluralRulesOverrides.json';

    /**
     * The CLDR 49 per-locale plural rules (source of truth for rule expressions,
     * human-readable descriptions, and examples).
     *
     * Used at build time to resolve the exact CLDR rule text, integer examples,
     * and — via the human-rule lookup tables — human-readable descriptions.
     */
    private const string CLDR_RULES_FILE_PATH = self::BUILD_DIR . '/cldr49_plural_rules.json';

    /**
     * Lookup table: CLDR cardinal rule expression → human-readable description.
     */
    private const string CARDINAL_HUMAN_LOOKUP_PATH = self::BUILD_DIR . '/cardinal_rules_human.json';

    /**
     * Lookup table: CLDR ordinal rule expression → human-readable description.
     */
    private const string ORDINAL_HUMAN_LOOKUP_PATH = self::BUILD_DIR . '/ordinal_rules_human.json';

    /**
     * Parent-locale mapping for locales not in CLDR.
     *
     * Maps child locale codes to their closest CLDR parent (e.g. "acf" → "fr").
     * Used to inherit CLDR rule text, human-readable descriptions, and examples
     * from the parent when the child has no direct CLDR entry.
     */
    private const string PARENT_MAP_FILE_PATH = self::BUILD_DIR . '/nonCldrParentMap.json';

    // ─── Array key constants ─────────────────────────────────────────────
    private const string K_RULE       = 'rule';
    private const string K_HUMAN_RULE = 'human_rule';
    private const string K_EXAMPLE    = 'example';
    private const string K_CARDINAL   = 'cardinal';
    private const string K_ORDINAL    = 'ordinal';

    // ─── Default fallback values ─────────────────────────────────────────
    // Used only for locales not present in CLDR (the sole source of truth).
    private const string R_EMPTY      = '';
    private const string H_ANY_NUMBER = 'Any number';
    private const string H_ANY_OTHER  = 'Any other number';

    /**
     * The built plural rules, keyed by ISO code.
     *
     * @var array<string, LanguageRulesFragment>
     */
    private array $rules;


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
        $overrides       = $this->loadOverrides();
        $cldrRules       = $this->loadCldrRules();
        $humanRuleLookup = $this->loadHumanRuleLookup();
        $parentMap       = $this->loadParentMap();

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
                    $overrides,
                    $cldrRules,
                    $humanRuleLookup,
                    $parentMap
                );
            }

            // Skip duplicates (same iso code already processed)
            if (isset($result[$isoCode])) {
                continue;
            }

            $result[$isoCode] = $this->buildLanguageRulesFragment(
                $name,
                $isoCode,
                $overrides,
                $cldrRules,
                $humanRuleLookup,
                $parentMap
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
     * @param array<string, array{cardinal?: array<string, array{example?: string}>, ordinal?: array<string, array{example?: string}>}> $overrides Per-language overrides.
     * @param array<string, array{cardinal?: list<array{category: string, rule: string}>, ordinal?: list<array{category: string, rule: string}>}> $cldrRules Per-locale CLDR rules.
     * @param array{cardinal: array<string, string>, ordinal: array<string, string>} $humanRuleLookup Rule expression → human-readable text lookups.
     * @param array<string, string> $parentMap Child locale → CLDR parent locale mapping.
     */
    private function buildLanguageRulesFragment(
        string $name,
        string $code,
        array $overrides,
        array $cldrRules,
        array $humanRuleLookup,
        array $parentMap = []
    ): LanguageRulesFragment {
        // Resolve CLDR rules for this locale using the following chain:
        // 1. Exact match in CLDR
        // 2. Case-insensitive match (e.g. "pt_pt" → "pt_PT")
        // 3. Parent locale from nonCldrParentMap.json (e.g. "acf" → "fr")
        // 4. No match → empty fallback
        $cldrCode = $this->resolveCldrCode($code, $cldrRules, $parentMap);
        $cldrCardinal = $this->buildCldrCategoryMap($cldrRules[$cldrCode][self::K_CARDINAL] ?? []);
        $cldrOrdinal  = $this->buildCldrCategoryMap($cldrRules[$cldrCode][self::K_ORDINAL] ?? []);

        $cardinalFragments = $this->buildCategoryFragments(
            PluralRules::getCardinalCategories($code),
            $overrides[$code][self::K_CARDINAL] ?? [],
            $cldrCardinal,
            $humanRuleLookup[self::K_CARDINAL]
        );

        $ordinalFragments = $this->buildCategoryFragments(
            PluralRules::getOrdinalCategories($code),
            $overrides[$code][self::K_ORDINAL] ?? [],
            $cldrOrdinal,
            $humanRuleLookup[self::K_ORDINAL]
        );

        return new LanguageRulesFragment($name, $code, $cardinalFragments, $ordinalFragments);
    }

    /**
     * Build CategoryFragment objects by zipping category names from PluralRules
     * with CLDR data and per-language overrides.
     *
     * Priority chain for each field:
     *   rule       → CLDR > fallback (empty)
     *   human_rule → override > CLDR lookup > fallback ("Any other number")
     *   example    → override > CLDR integer_examples > fallback (empty)
     *
     * @param array<int, string> $categories Category names from PluralRules (e.g. ['one', 'other']).
     * @param array<string, array{human_rule?: string, example?: string}> $overrides Per-language overrides keyed by category name.
     * @param array<string, array{rule: string, example: string}> $cldrCategoryData CLDR {rule, example} keyed by category name.
     * @param array<string, string> $humanRuleLookup Rule expression → human-readable text lookup.
     * @return CategoryFragment[]
     */
    private function buildCategoryFragments(
        array $categories,
        array $overrides = [],
        array $cldrCategoryData = [],
        array $humanRuleLookup = []
    ): array {
        $fragments    = [];
        $isSingleForm = count($categories) === 1;

        foreach ($categories as $category) {
            $cldrData = $cldrCategoryData[$category] ?? null;

            // ── rule ──
            $rule = $cldrData[self::K_RULE] ?? self::R_EMPTY;

            // ── human_rule ──
            // When there is only one category ("other" with no rule), use
            // "Any number" instead of "Any other number" — there is nothing
            // to contrast the catch-all against.
            if ($isSingleForm && $rule === self::R_EMPTY) {
                $defaultHumanRule = self::H_ANY_NUMBER;
            } else {
                $defaultHumanRule = $humanRuleLookup[$rule] ?? self::H_ANY_OTHER;
            }
            $humanRule = $overrides[$category][self::K_HUMAN_RULE] ?? $defaultHumanRule;

            // ── example ──
            // Try: override → CLDR integer_examples → empty
            $defaultExample = $cldrData[self::K_EXAMPLE] ?? '';
            $example = $overrides[$category][self::K_EXAMPLE] ?? $defaultExample;

            $fragments[] = new CategoryFragment(
                category: $category,
                rule: $rule,
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
     * Load per-locale CLDR plural rules from the CLDR 49 JSON file.
     *
     * Returns an array keyed by locale code (e.g. "af", "pt_PT"), then
     * 'cardinal'/'ordinal', each containing a list of {category, rule, ...}.
     *
     * @return array<string, array{cardinal?: list<array{category: string, rule: string}>, ordinal?: list<array{category: string, rule: string}>}>
     */
    private function loadCldrRules(): array
    {
        if (!file_exists(self::CLDR_RULES_FILE_PATH)) {
            // @codeCoverageIgnoreStart
            return [];
            // @codeCoverageIgnoreEnd
        }

        return $this->readJsonFile(self::CLDR_RULES_FILE_PATH);
    }

    /**
     * Load both cardinal and ordinal human-rule lookup tables.
     *
     * @return array{cardinal: array<string, string>, ordinal: array<string, string>}
     */
    private function loadHumanRuleLookup(): array
    {
        $cardinal = file_exists(self::CARDINAL_HUMAN_LOOKUP_PATH)
            ? $this->readJsonFile(self::CARDINAL_HUMAN_LOOKUP_PATH)
            : [];
        $ordinal = file_exists(self::ORDINAL_HUMAN_LOOKUP_PATH)
            ? $this->readJsonFile(self::ORDINAL_HUMAN_LOOKUP_PATH)
            : [];

        return [
            self::K_CARDINAL => $cardinal,
            self::K_ORDINAL  => $ordinal,
        ];
    }

    /**
     * Load the child → parent locale mapping for non-CLDR locales.
     *
     * @return array<string, string> Child locale code → CLDR parent locale code.
     */
    private function loadParentMap(): array
    {
        if (!file_exists(self::PARENT_MAP_FILE_PATH)) {
            // @codeCoverageIgnoreStart
            return [];
            // @codeCoverageIgnoreEnd
        }

        return $this->readJsonFile(self::PARENT_MAP_FILE_PATH);
    }

    /**
     * Resolve the CLDR key for a locale using the following chain:
     *
     * 1. Exact match in CLDR
     * 2. Case-insensitive match (e.g. "pt_pt" → "pt_PT")
     * 3. Parent locale from nonCldrParentMap.json (e.g. "acf" → "fr")
     * 4. No match → returns the original code (will produce empty CLDR data)
     *
     * @param string $code The locale code to resolve.
     * @param array<string, mixed> $cldrRules The full CLDR rules array.
     * @param array<string, string> $parentMap Child → parent mapping.
     * @return string The resolved CLDR key.
     */
    private function resolveCldrCode(string $code, array $cldrRules, array $parentMap): string
    {
        // 1. Exact match
        if (isset($cldrRules[$code])) {
            return $code;
        }

        // 2. Case-insensitive match (e.g. "pt_pt" → "pt_PT")
        foreach ($cldrRules as $cldrKey => $v) {
            if (strcasecmp($cldrKey, $code) === 0) {
                return $cldrKey;
            }
        }

        // 3. Parent locale fallback (e.g. "acf" → "fr")
        if (isset($parentMap[$code], $cldrRules[$parentMap[$code]])) {
            return $parentMap[$code];
        }

        // 4. No match
        return $code;
    }

    /**
     * Build a category → {rule, example} map from a CLDR categories array.
     *
     * @param list<array{category: string, rule: string, integer_examples?: string}> $cldrCategories
     * @return array<string, array{rule: string, example: string}> Category name → CLDR rule expression and integer examples.
     */
    private function buildCldrCategoryMap(array $cldrCategories): array
    {
        $map = [];
        foreach ($cldrCategories as $cat) {
            $map[$cat['category']] = [
                self::K_RULE    => $cat[self::K_RULE],
                self::K_EXAMPLE => $cat['integer_examples'] ?? '',
            ];
        }

        return $map;
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


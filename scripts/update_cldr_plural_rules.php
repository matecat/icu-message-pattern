#!/usr/bin/env php
<?php
/**
 * Unified CLDR plural rules pipeline.
 *
 * This script performs the full update workflow in one run:
 *
 *   1. Downloads CLDR source files from the Unicode CLDR GitHub repository
 *   2. Parses the XML files and builds cldr49_plural_rules.json
 *   3. Validates PluralRules.php categories against the new CLDR data
 *   4. Generates pluralRulesOverrides.json (category-keyed, only real deltas)
 *
 * Source URLs:
 *   - plurals.xml   → https://raw.githubusercontent.com/unicode-org/cldr/refs/heads/main/common/supplemental/plurals.xml
 *   - ordinals.xml  → https://raw.githubusercontent.com/unicode-org/cldr/refs/heads/main/common/supplemental/ordinals.xml
 *   - en.xml        → https://raw.githubusercontent.com/unicode-org/cldr/refs/heads/main/common/main/en.xml
 *
 * Usage: php scripts/update_cldr_plural_rules.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Matecat\ICU\Plurals\PluralRules;
use Matecat\Locales\Languages;

// ─────────────────────────────────────────────────────────────────────────────
// Constants
// ─────────────────────────────────────────────────────────────────────────────

const CLDR_SOURCES = [
    'plurals.xml'  => 'https://raw.githubusercontent.com/unicode-org/cldr/refs/heads/main/common/supplemental/plurals.xml',
    'ordinals.xml' => 'https://raw.githubusercontent.com/unicode-org/cldr/refs/heads/main/common/supplemental/ordinals.xml',
    'en.xml'       => 'https://raw.githubusercontent.com/unicode-org/cldr/refs/heads/main/common/main/en.xml',
];

/**
 * Legacy/deprecated ISO 639 codes mapped to their modern equivalents.
 */
const LEGACY_CODE_MAP = [
    'in'  => 'id',  // Indonesian
    'iw'  => 'he',  // Hebrew
    'ji'  => 'yi',  // Yiddish
    'jw'  => 'jv',  // Javanese
    'mo'  => 'ro',  // Moldavian → Romanian
];

$sourcesDir    = __DIR__ . '/cldr_sources';
$cldrJsonPath  = __DIR__ . '/../src/resources/build/cldr49_plural_rules.json';
$overridesPath = __DIR__ . '/../src/resources/build/pluralRulesOverrides.json';

// ─────────────────────────────────────────────────────────────────────────────
// Helper functions
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Print a section header.
 */
function section(int $step, string $title): void
{
    echo "\n┌─────────────────────────────────────────────────────────────\n";
    echo "│ Step $step: $title\n";
    echo "└─────────────────────────────────────────────────────────────\n\n";
}

/**
 * Download a file from a URL.
 *
 * @return string The downloaded content.
 */
function download(string $url, string $label): string
{
    echo "  Downloading $label ... ";

    $context = stream_context_create([
        'http' => [
            'timeout' => 30,
            'user_agent' => 'matecat-icu-intl/cldr-parser',
        ],
    ]);

    $content = file_get_contents($url, false, $context);
    if ($content === false) {
        fwrite(STDERR, "\n  ✗ Failed to download $url\n");
        exit(1);
    }

    echo "OK (" . number_format(strlen($content)) . " bytes)\n";

    return $content;
}

/**
 * Read a JSON file and decode it.
 *
 * @return array<string, mixed>
 */
function readJson(string $path): array
{
    if (!file_exists($path)) {
        return [];
    }

    $json = file_get_contents($path);

    return ($json !== false) ? json_decode($json, true, 512, JSON_THROW_ON_ERROR) : [];
}

/**
 * Parse the CLDR English locale file (en.xml) to extract language display names.
 *
 * Only entries without an "alt" attribute are used (to avoid alternate display
 * forms like "menu" or "long").
 *
 * @return array<string, string> Map of ISO code → English display name.
 */
function loadLanguageNames(string $enXmlPath): array
{
    $xml = file_get_contents($enXmlPath);
    if ($xml === false) {
        fwrite(STDERR, "  ✗ Failed to read $enXmlPath\n");
        exit(1);
    }

    $dom = new DOMDocument();
    @$dom->loadXML($xml);
    $xpath = new DOMXPath($dom);

    $nameMap = [];
    $nodes = $xpath->query('//languages/language[@type and not(@alt)]');
    if ($nodes !== false) {
        foreach ($nodes as $node) {
            /** @var DOMElement $node */
            $code = $node->getAttribute('type');
            $name = trim($node->textContent);
            if ($code !== '' && $name !== '') {
                $nameMap[$code] = $name;
            }
        }
    }

    return $nameMap;
}

/**
 * Resolve a language name from the name map, handling legacy codes and variants.
 *
 * @param array<string, string> $nameMap Language name map parsed from en.xml.
 */
function getLanguageName(string $code, array $nameMap): string
{
    if (isset($nameMap[$code])) {
        return $nameMap[$code];
    }

    if (isset(LEGACY_CODE_MAP[$code], $nameMap[LEGACY_CODE_MAP[$code]])) {
        return $nameMap[LEGACY_CODE_MAP[$code]];
    }

    $baseCode = explode('_', $code)[0];
    if ($baseCode !== $code && isset($nameMap[$baseCode])) {
        return $nameMap[$baseCode];
    }

    return ucfirst($code);
}

/**
 * Parse a <pluralRule> element text content into rule + examples.
 *
 * @return array{rule: string, integer_examples: string}
 */
function parsePluralRuleText(string $text): array
{
    $text = trim((string) preg_replace('/\s+/', ' ', $text));

    $integerPos = strpos($text, '@integer');
    $decimalPos = strpos($text, '@decimal');

    if ($integerPos !== false) {
        $rule = trim(substr($text, 0, $integerPos));
        $end  = ($decimalPos !== false) ? $decimalPos - $integerPos - 8 : null;
        $integerExamples = trim(substr($text, $integerPos + 8, $end));
    } else {
        $rule = ($decimalPos !== false) ? trim(substr($text, 0, $decimalPos)) : trim($text);
        $integerExamples = '';
    }

    return ['rule' => $rule, 'integer_examples' => $integerExamples];
}

/**
 * Parse a CLDR plural rules XML file into a structured array.
 *
 * @param array<string, mixed> $result  Result array (passed by reference).
 * @param array<string, string> $nameMap Language name map.
 */
function parseXmlFile(string $xmlPath, string $type, array &$result, array $nameMap): void
{
    $xml = file_get_contents($xmlPath);
    if ($xml === false) {
        fwrite(STDERR, "  ✗ Failed to read $xmlPath\n");
        exit(1);
    }

    $dom = new DOMDocument();
    $dom->loadXML($xml);
    $xpath = new DOMXPath($dom);

    $pluralRulesNodes = $xpath->query('//plurals/pluralRules');
    if ($pluralRulesNodes === false) {
        fwrite(STDERR, "  ✗ No <pluralRules> found in $xmlPath\n");
        exit(1);
    }

    foreach ($pluralRulesNodes as $pluralRulesNode) {
        /** @var DOMElement $pluralRulesNode */
        $locales = preg_split('/\s+/', trim($pluralRulesNode->getAttribute('locales'))) ?: [];

        $rules = [];
        $ruleNodes = $xpath->query('./pluralRule', $pluralRulesNode);
        if ($ruleNodes === false) {
            continue;
        }

        foreach ($ruleNodes as $ruleNode) {
            /** @var DOMElement $ruleNode */
            $parsed = parsePluralRuleText($ruleNode->textContent);
            $rules[] = [
                'category' => $ruleNode->getAttribute('count'),
                'rule' => $parsed['rule'],
                'integer_examples' => $parsed['integer_examples'],
            ];
        }

        foreach ($locales as $locale) {
            $locale = trim($locale);
            if ($locale === '') {
                continue;
            }

            if (!isset($result[$locale])) {
                $result[$locale] = [
                    'name' => getLanguageName($locale, $nameMap),
                    'cardinal' => [],
                    'ordinal' => [],
                ];
            }

            $result[$locale][$type] = $rules;
        }
    }
}

// ═════════════════════════════════════════════════════════════════════════════
// STEP 1: Download CLDR source files
// ═════════════════════════════════════════════════════════════════════════════

section(1, 'Download CLDR source files');

if (!is_dir($sourcesDir)) {
    mkdir($sourcesDir, 0755, true);
}

foreach (CLDR_SOURCES as $filename => $url) {
    $content = download($url, $filename);
    file_put_contents($sourcesDir . '/' . $filename, $content);
}

$cardinalXmlPath = $sourcesDir . '/plurals.xml';
$ordinalXmlPath  = $sourcesDir . '/ordinals.xml';
$enXmlPath       = $sourcesDir . '/en.xml';

// ═════════════════════════════════════════════════════════════════════════════
// STEP 2: Parse XML → build cldr49_plural_rules.json
// ═════════════════════════════════════════════════════════════════════════════

section(2, 'Build cldr49_plural_rules.json');

$nameMap = loadLanguageNames($enXmlPath);
echo "  Loaded " . count($nameMap) . " language names from en.xml\n";

// Load existing JSON to preserve minimal_pair values
$existingCldr = readJson($cldrJsonPath);

// Parse both XML files
$cldrResult = [];
parseXmlFile($cardinalXmlPath, 'cardinal', $cldrResult, $nameMap);
parseXmlFile($ordinalXmlPath, 'ordinal', $cldrResult, $nameMap);

// Merge minimal_pair values from existing data
foreach ($cldrResult as $code => &$langData) {
    foreach (['cardinal', 'ordinal'] as $type) {
        $existingByCategory = [];
        foreach ($existingCldr[$code][$type] ?? [] as $entry) {
            if (isset($entry['category'], $entry['minimal_pair'])) {
                $existingByCategory[$entry['category']] = $entry['minimal_pair'];
            }
        }
        foreach ($langData[$type] as &$entry) {
            $entry['minimal_pair'] = $existingByCategory[$entry['category']] ?? '';
        }
        unset($entry);
    }
}
unset($langData);

ksort($cldrResult);

$json = json_encode($cldrResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
file_put_contents($cldrJsonPath, $json . "\n");

$langCount = count($cldrResult);
$cardCount = array_sum(array_map(fn($l) => count($l['cardinal']), $cldrResult));
$ordCount  = array_sum(array_map(fn($l) => count($l['ordinal']), $cldrResult));
echo "  Extracted $langCount languages, $cardCount cardinal entries, $ordCount ordinal entries\n";
echo "  Written to $cldrJsonPath\n";

// ═════════════════════════════════════════════════════════════════════════════
// STEP 3: Validate PluralRules.php against CLDR
// ═════════════════════════════════════════════════════════════════════════════

section(3, 'Validate PluralRules.php against CLDR');

$ref  = new ReflectionClass(PluralRules::class);
$prop = $ref->getProperty('rulesMap');
/** @var array<string, array{cardinal: int, ordinal: int}> $rulesMap */
$rulesMap = $prop->getValue();

$issues  = [];
$checked = 0;
$skipped = 0;

foreach ($rulesMap as $isoCode => $ruleGroups) {
    if (!isset($cldrResult[$isoCode])) {
        $skipped++;
        continue;
    }

    $checked++;
    $cldrLang = $cldrResult[$isoCode];

    // Cardinal categories
    $ourCardinal  = PluralRules::getCardinalCategories($isoCode);
    $cldrCardinal = array_column($cldrLang['cardinal'], 'category');
    if ($ourCardinal !== $cldrCardinal) {
        $issues[] = sprintf(
            "  CARDINAL %s: ours=[%s] cldr=[%s]",
            $isoCode,
            implode(',', $ourCardinal),
            implode(',', $cldrCardinal)
        );
    }

    // Ordinal categories
    $ourOrdinal  = PluralRules::getOrdinalCategories($isoCode);
    $cldrOrdinal = array_column($cldrLang['ordinal'], 'category');
    if ($ourOrdinal !== $cldrOrdinal) {
        if (empty($cldrOrdinal) && $ourOrdinal === ['other']) {
            continue; // acceptable default
        }
        $issues[] = sprintf(
            "  ORDINAL  %s: ours=[%s] cldr=[%s]",
            $isoCode,
            implode(',', $ourOrdinal),
            implode(',', $cldrOrdinal)
        );
    }
}

echo "  Checked $checked languages ($skipped skipped — not in CLDR)\n\n";

if (!empty($issues)) {
    echo "  ✗ Found " . count($issues) . " mismatches:\n\n";
    foreach ($issues as $issue) {
        echo "$issue\n";
    }
    echo "\n  Aborting. Fix PluralRules.php before regenerating overrides.\n";
    exit(1);
}

echo "  ✓ All languages match CLDR!\n";

// ═════════════════════════════════════════════════════════════════════════════
// STEP 4: Generate pluralRulesOverrides.json
// ═════════════════════════════════════════════════════════════════════════════

section(4, 'Generate pluralRulesOverrides.json');

// Load human-rule lookup tables (CLDR rule expression → human-readable description)
$cardinalHumanLookup = readJson(__DIR__ . '/../src/resources/build/cardinal_rules_human.json');
$ordinalHumanLookup  = readJson(__DIR__ . '/../src/resources/build/ordinal_rules_human.json');

// Load the parent map for non-CLDR locales
$parentMap = readJson(__DIR__ . '/../src/resources/build/nonCldrParentMap.json');

// Load existing overrides to preserve human_rule customizations
$existingOverrides = readJson($overridesPath);

// Get all enabled languages
$languages        = Languages::getInstance();
$enabledLanguages = $languages->getEnabledLanguages();
$processedIsos    = [];

foreach ($enabledLanguages as $rfc => $langInfo) {
    $isoCode = Languages::convertLanguageToIsoCode($rfc);
    if ($isoCode !== null) {
        $processedIsos[$isoCode] = true;
    }
}

$newOverrides = [];
$stats = ['kept' => 0, 'removed' => 0];

foreach (array_keys($processedIsos) as $isoCode) {
    // Resolve CLDR data: direct match → parent map fallback → null
    $cldrLang = $cldrResult[$isoCode] ?? null;
    if ($cldrLang === null && isset($parentMap[$isoCode], $cldrResult[$parentMap[$isoCode]])) {
        $cldrLang = $cldrResult[$parentMap[$isoCode]];
    }

    $existingLangOvr = $existingOverrides[$isoCode] ?? [];
    $langOverrides   = [];

    foreach (['cardinal', 'ordinal'] as $type) {
        $categories = ($type === 'cardinal')
            ? PluralRules::getCardinalCategories($isoCode)
            : PluralRules::getOrdinalCategories($isoCode);
        $humanLookup = ($type === 'cardinal') ? $cardinalHumanLookup : $ordinalHumanLookup;

        // Build CLDR category → entry map
        $cldrByCategory = [];
        if ($cldrLang !== null) {
            foreach ($cldrLang[$type] ?? [] as $cldrEntry) {
                $cldrByCategory[$cldrEntry['category']] = $cldrEntry;
            }
        }

        $isSingleForm  = count($categories) === 1;
        $typeOverrides  = [];

        foreach ($categories as $category) {
            $cldrEntry = $cldrByCategory[$category] ?? null;

            // Compute what PluralRulesBuilder would produce as defaults:
            // - rule: from CLDR, or empty
            // - human_rule: from lookup table applied to the rule, or generic fallback
            // - example: from CLDR integer_examples, or empty
            $defaultRule      = $cldrEntry['rule'] ?? '';
            $defaultHumanRule = ($isSingleForm && $defaultRule === '')
                ? 'Any number'
                : ($humanLookup[$defaultRule] ?? 'Any other number');
            $defaultExample   = $cldrEntry['integer_examples'] ?? '';

            $overrideHumanRule = null;
            $overrideExample   = null;

            // Preserve existing human_rule override if it differs from the default
            if (isset($existingLangOvr[$type][$category]['human_rule'])) {
                $candidate = $existingLangOvr[$type][$category]['human_rule'];
                if ($candidate !== $defaultHumanRule) {
                    $overrideHumanRule = $candidate;
                }
            }

            // Preserve existing example override if it differs from the default
            if (isset($existingLangOvr[$type][$category]['example'])) {
                $candidate = $existingLangOvr[$type][$category]['example'];
                if ($candidate !== $defaultExample) {
                    $overrideExample = $candidate;
                }
            }

            // If no existing example override, try CLDR minimal_pair
            // (localized example sentence that differs from generic integer examples)
            if ($overrideExample === null && isset($cldrByCategory[$category])) {
                $minimalPair = $cldrByCategory[$category]['minimal_pair'] ?? '';
                if ($minimalPair !== '' && $minimalPair !== $defaultExample) {
                    $overrideExample = $minimalPair;
                }
            }

            $entry = [];
            if ($overrideHumanRule !== null) {
                $entry['human_rule'] = $overrideHumanRule;
            }
            if ($overrideExample !== null) {
                $entry['example'] = $overrideExample;
            }

            if (!empty($entry)) {
                $typeOverrides[$category] = $entry;
                $stats['kept']++;
            } else {
                $stats['removed']++;
            }
        }

        if (!empty($typeOverrides)) {
            $langOverrides[$type] = $typeOverrides;
        }
    }

    if (!empty($langOverrides)) {
        $newOverrides[$isoCode] = $langOverrides;
    }
}

ksort($newOverrides);

$json = json_encode($newOverrides, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
file_put_contents($overridesPath, $json . "\n");

echo "  Override entries kept (real differences): {$stats['kept']}\n";
echo "  Override entries removed (same as default): {$stats['removed']}\n";
echo "  Languages with overrides: " . count($newOverrides) . "\n";
echo "  Written to $overridesPath\n";

// ═════════════════════════════════════════════════════════════════════════════
// Done
// ═════════════════════════════════════════════════════════════════════════════

echo "\n✅ All done.\n";


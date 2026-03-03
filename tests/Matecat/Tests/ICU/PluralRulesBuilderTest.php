<?php /** @noinspection PhpMultipleClassDeclarationsInspection */

declare(strict_types=1);

namespace Matecat\Tests\ICU;

use JsonException;
use Matecat\ICU\Plurals\PluralRules;
use Matecat\Locales\DTO\CategoryFragment;
use Matecat\Locales\DTO\LanguageRulesFragment;
use Matecat\Locales\Languages;
use Matecat\Locales\PluralRulesBuilder;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;

/**
 * Tests the functionality of the `PluralRulesBuilder` class.
 *
 * Covers:
 * - Singleton pattern (getInstance, destroyInstance)
 * - Cache read-through (reads from the existing JSON file)
 * - Force rebuild flag (bypasses cache)
 * - Custom file path
 * - Build output structure (LanguageRulesFragment / CategoryFragment)
 * - JSON serialization round-trip
 * - Category names match PluralRules at runtime
 * - CategoryFragment DTO public properties and jsonSerialize
 * - LanguageRulesFragment DTO public properties and jsonSerialize
 */
final class PluralRulesBuilderTest extends TestCase
{
    /**
     * Shared cache file built once for read-only tests.
     */
    private static string $sharedCacheFile;

    /**
     * Per-test temp file for tests that need their own JSON file.
     */
    private string $tempFile;

    /**
     * Build the shared cache once before all tests in this class.
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$sharedCacheFile = sys_get_temp_dir() . '/pluralRulesBuilderTest_shared_' . getmypid() . '.json';
        PluralRulesBuilder::destroyInstance();
        PluralRulesBuilder::getInstance(forceRebuild: true, filePath: self::$sharedCacheFile);
        PluralRulesBuilder::destroyInstance();
        Languages::destroyInstance();
    }

    /**
     * Clean up the shared cache file after all tests.
     */
    public static function tearDownAfterClass(): void
    {
        Languages::destroyInstance();
        if (file_exists(self::$sharedCacheFile)) {
            unlink(self::$sharedCacheFile);
        }
        parent::tearDownAfterClass();
    }

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        PluralRulesBuilder::destroyInstance();
        Languages::destroyInstance();
        $this->tempFile = sys_get_temp_dir() . '/pluralRulesBuilderTest_' . uniqid() . '.json';
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        PluralRulesBuilder::destroyInstance();
        Languages::destroyInstance();

        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }

        parent::tearDown();
    }

    /**
     * Get a builder instance that reads from the shared cache (no rebuild).
     *
     * @return PluralRulesBuilder
     */
    private function getBuilder(): PluralRulesBuilder
    {
        return PluralRulesBuilder::getInstance(filePath: self::$sharedCacheFile);
    }

    // =========================================================================
    // Singleton pattern
    // =========================================================================

    /**
     * @return void
     */
    public function testGetInstanceReturnsSameInstance(): void
    {
        $instance1 = PluralRulesBuilder::getInstance(forceRebuild: true, filePath: $this->tempFile);
        $instance2 = PluralRulesBuilder::getInstance(forceRebuild: true, filePath: $this->tempFile);

        self::assertSame($instance1, $instance2);
    }

    /**
     * @return void
     */
    public function testDestroyInstanceAllowsReInitialization(): void
    {
        $instance1 = PluralRulesBuilder::getInstance(forceRebuild: true, filePath: $this->tempFile);

        PluralRulesBuilder::destroyInstance();

        $instance2 = PluralRulesBuilder::getInstance(forceRebuild: true, filePath: $this->tempFile);

        self::assertNotSame($instance1, $instance2);
    }

    // =========================================================================
    // Force rebuild: writes cache file
    // =========================================================================

    /**
     * @return void
     */
    public function testForceRebuildWritesCacheFile(): void
    {
        self::assertFileDoesNotExist($this->tempFile);

        PluralRulesBuilder::getInstance(forceRebuild: true, filePath: $this->tempFile);

        self::assertFileExists($this->tempFile);

        $content = file_get_contents($this->tempFile);
        self::assertIsString($content);
        self::assertNotEmpty($content);
        self::assertJson($content);
    }

    // =========================================================================
    // Cache read-through: reads from existing file
    // =========================================================================

    /**
     * @return void
     */
    public function testCacheReadThroughReadsExistingFile(): void
    {
        // First: force build to create the cache file
        $builder1 = PluralRulesBuilder::getInstance(forceRebuild: true, filePath: $this->tempFile);
        $rules1 = $builder1->getRules();

        PluralRulesBuilder::destroyInstance();

        // Second: without forceRebuild should read from the file
        $builder2 = PluralRulesBuilder::getInstance(filePath: $this->tempFile);
        $rules2 = $builder2->getRules();

        // Both should produce equivalent data
        self::assertCount(count($rules1), $rules2);
        self::assertEquals(array_keys($rules1), array_keys($rules2));

        foreach ($rules1 as $isoCode => $lang1) {
            $lang2 = $rules2[$isoCode];
            self::assertSame($lang1->name, $lang2->name, "Name mismatch for $isoCode");
            self::assertEquals($lang1->jsonSerialize(), $lang2->jsonSerialize(), "JSON mismatch for $isoCode");
        }
    }

    /**
     * @return void
     */
    public function testCacheReadThroughDoesNotReadWhenForceRebuildTrue(): void
    {
        // Write a minimal JSON file that is clearly different from the built one
        file_put_contents($this->tempFile, json_encode(['test_lang' => [
            'name' => 'Test',
            'cardinal' => [],
            'ordinal' => [],
        ]], JSON_PRETTY_PRINT) . "\n");

        $builder = PluralRulesBuilder::getInstance(forceRebuild: true, filePath: $this->tempFile);
        $rules = $builder->getRules();

        // The result should NOT contain 'test_lang' (it's not a real language)
        self::assertArrayNotHasKey('test_lang', $rules);

        // It should contain real languages
        self::assertNotEmpty($rules);
    }

    /**
     * @return void
     */
    public function testCacheReadThroughFromNonExistentFileBuildsAndWrites(): void
    {
        // File does not exist, forceRebuild=false => should build and write
        $builder = PluralRulesBuilder::getInstance(filePath: $this->tempFile);
        $rules = $builder->getRules();

        self::assertNotEmpty($rules);
        self::assertFileExists($this->tempFile);
    }

    // =========================================================================
    // getRules() structure
    // =========================================================================

    /**
     * @return void
     */
    public function testGetRulesReturnsNonEmptyArray(): void
    {
        $rules = $this->getBuilder()->getRules();

        self::assertNotEmpty($rules);
    }

    /**
     * @return void
     */
    public function testGetRulesKeyedByIsoCode(): void
    {
        $rules = $this->getBuilder()->getRules();

        foreach ($rules as $value) {
            self::assertInstanceOf(LanguageRulesFragment::class, $value);
        }
    }

    /**
     * @return void
     */
    public function testGetRulesIsSortedAlphabetically(): void
    {
        $rules = $this->getBuilder()->getRules();

        $keys = array_keys($rules);
        $sorted = $keys;
        sort($sorted);

        self::assertSame($sorted, $keys);
    }

    // =========================================================================
    // English (en) — well-known reference language
    // =========================================================================

    /**
     * @return void
     */
    public function testEnglishCardinalCategories(): void
    {
        $rules = $this->getBuilder()->getRules();

        self::assertArrayHasKey('en', $rules);

        $en = $rules['en'];
        self::assertStringStartsWith('English', $en->name);

        // English cardinal: one/other (rule group 1)
        $cardinals = $en->cardinal;
        self::assertCount(2, $cardinals);

        self::assertSame('one', $cardinals[0]->category);
        self::assertSame('Exactly 1 (no decimals)', $cardinals[0]->human_rule);
        self::assertSame('1', $cardinals[0]->example);

        self::assertSame('other', $cardinals[1]->category);
        self::assertSame('Any other number', $cardinals[1]->human_rule);
    }

    /**
     * @return void
     */
    public function testEnglishOrdinalCategories(): void
    {
        $rules = $this->getBuilder()->getRules();

        $en = $rules['en'];

        // English ordinal: one/two/few/other (rule group 1)
        $ordinals = $en->ordinal;
        self::assertCount(4, $ordinals);

        $categories = array_map(fn(CategoryFragment $f) => $f->category, $ordinals);
        self::assertSame(['one', 'two', 'few', 'other'], $categories);
    }

    // =========================================================================
    // Arabic (ar) — 6 cardinal categories
    // =========================================================================

    /**
     * @return void
     */
    public function testArabicCardinalCategories(): void
    {
        $rules = $this->getBuilder()->getRules();

        self::assertArrayHasKey('ar', $rules);

        $ar = $rules['ar'];
        $cardinals = $ar->cardinal;
        self::assertCount(6, $cardinals);

        $categories = array_map(fn(CategoryFragment $f) => $f->category, $cardinals);
        self::assertSame(['zero', 'one', 'two', 'few', 'many', 'other'], $categories);
    }

    // =========================================================================
    // Japanese (ja) — 1 cardinal category (other only)
    // =========================================================================

    /**
     * @return void
     */
    public function testJapaneseCardinalCategories(): void
    {
        $rules = $this->getBuilder()->getRules();

        self::assertArrayHasKey('ja', $rules);

        $ja = $rules['ja'];
        $cardinals = $ja->cardinal;
        self::assertCount(1, $cardinals);
        self::assertSame('other', $cardinals[0]->category);
        self::assertSame('Any number', $cardinals[0]->human_rule);
    }

    // =========================================================================
    // Russian (ru) — 4 cardinal categories (Slavic rule 3)
    // =========================================================================

    /**
     * @return void
     */
    public function testRussianCardinalCategories(): void
    {
        $rules = $this->getBuilder()->getRules();

        self::assertArrayHasKey('ru', $rules);

        $ru = $rules['ru'];
        $cardinals = $ru->cardinal;
        self::assertCount(4, $cardinals);

        $categories = array_map(fn(CategoryFragment $f) => $f->category, $cardinals);
        self::assertSame(['one', 'few', 'many', 'other'], $categories);
    }

    // =========================================================================
    // Italian (it) — 3 cardinal categories (CLDR 49 rule 20)
    // =========================================================================

    /**
     * @return void
     */
    public function testItalianCardinalCategories(): void
    {
        $rules = $this->getBuilder()->getRules();

        self::assertArrayHasKey('it', $rules);

        $it = $rules['it'];
        $cardinals = $it->cardinal;
        self::assertCount(3, $cardinals);

        $categories = array_map(fn(CategoryFragment $f) => $f->category, $cardinals);
        self::assertSame(['one', 'many', 'other'], $categories);
    }

    // =========================================================================
    // Categories match PluralRules at runtime
    // =========================================================================

    /**
     * @return void
     */
    public function testBuiltCategoriesMatchPluralRulesCategories(): void
    {
        $rules = $this->getBuilder()->getRules();

        // Test a representative set of languages
        $testLocales = ['en', 'ar', 'ja', 'ru', 'fr', 'it', 'pl', 'cs'];

        foreach ($testLocales as $locale) {
            if (!isset($rules[$locale])) {
                continue;
            }

            $expectedCardinal = PluralRules::getCardinalCategories($locale);
            $actualCardinal = array_map(fn(CategoryFragment $f) => $f->category, $rules[$locale]->cardinal);
            self::assertSame($expectedCardinal, $actualCardinal, "Cardinal categories mismatch for $locale");

            $expectedOrdinal = PluralRules::getOrdinalCategories($locale);
            $actualOrdinal = array_map(fn(CategoryFragment $f) => $f->category, $rules[$locale]->ordinal);
            self::assertSame($expectedOrdinal, $actualOrdinal, "Ordinal categories mismatch for $locale");
        }
    }

    /**
     * @return void
     */
    public function testAllLanguagesHaveCategoriesMatchingPluralRules(): void
    {
        $rules = $this->getBuilder()->getRules();

        foreach ($rules as $isoCode => $langFragment) {
            $expectedCardinal = PluralRules::getCardinalCategories($isoCode);
            $actualCardinal = array_map(fn(CategoryFragment $f) => $f->category, $langFragment->cardinal);
            self::assertSame($expectedCardinal, $actualCardinal, "Cardinal categories mismatch for $isoCode");

            $expectedOrdinal = PluralRules::getOrdinalCategories($isoCode);
            $actualOrdinal = array_map(fn(CategoryFragment $f) => $f->category, $langFragment->ordinal);
            self::assertSame($expectedOrdinal, $actualOrdinal, "Ordinal categories mismatch for $isoCode");
        }
    }

    // =========================================================================
    // JSON serialization round-trip
    // =========================================================================

    /**
     * @return void
     * @throws JsonException
     */
    public function testJsonRoundTrip(): void
    {
        // Build and write
        $builder1 = PluralRulesBuilder::getInstance(forceRebuild: true, filePath: $this->tempFile);
        $rules1 = $builder1->getRules();

        // Read the raw JSON
        $json = file_get_contents($this->tempFile);
        self::assertIsString($json);
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        self::assertIsArray($data);
        self::assertCount(count($rules1), $data);

        // Check that each key exists and the structure is correct
        foreach ($data as $isoCode => $langData) {
            self::assertArrayHasKey('name', $langData, "Missing 'name' for $isoCode");
            self::assertArrayHasKey('cardinal', $langData, "Missing 'cardinal' for $isoCode");
            self::assertArrayHasKey('ordinal', $langData, "Missing 'ordinal' for $isoCode");
            self::assertIsArray($langData['cardinal'], "Cardinal should be array for $isoCode");
            self::assertIsArray($langData['ordinal'], "Ordinal should be array for $isoCode");

            foreach ($langData['cardinal'] as $i => $cat) {
                self::assertArrayHasKey('category', $cat, "Missing 'category' in cardinal[$i] for $isoCode");
                self::assertArrayHasKey('rule', $cat, "Missing 'rule' in cardinal[$i] for $isoCode");
                self::assertArrayHasKey('human_rule', $cat, "Missing 'human_rule' in cardinal[$i] for $isoCode");
                self::assertArrayHasKey('example', $cat, "Missing 'example' in cardinal[$i] for $isoCode");
            }

            foreach ($langData['ordinal'] as $i => $cat) {
                self::assertArrayHasKey('category', $cat, "Missing 'category' in ordinal[$i] for $isoCode");
                self::assertArrayHasKey('rule', $cat, "Missing 'rule' in ordinal[$i] for $isoCode");
                self::assertArrayHasKey('human_rule', $cat, "Missing 'human_rule' in ordinal[$i] for $isoCode");
                self::assertArrayHasKey('example', $cat, "Missing 'example' in ordinal[$i] for $isoCode");
            }
        }
    }

    /**
     * @return void
     */
    public function testJsonFileContainsValidUtf8(): void
    {
        PluralRulesBuilder::getInstance(forceRebuild: true, filePath: $this->tempFile);

        $json = file_get_contents($this->tempFile);
        self::assertIsString($json);
        self::assertTrue(mb_check_encoding($json, 'UTF-8'));
    }

    // =========================================================================
    // Read from cache produces equivalent objects
    // =========================================================================

    /**
     * @return void
     */
    public function testReadFromCacheProducesEquivalentDTOs(): void
    {
        $builder1 = PluralRulesBuilder::getInstance(forceRebuild: true, filePath: $this->tempFile);
        $rules1 = $builder1->getRules();

        PluralRulesBuilder::destroyInstance();

        $builder2 = PluralRulesBuilder::getInstance(filePath: $this->tempFile);
        $rules2 = $builder2->getRules();

        foreach ($rules1 as $isoCode => $lang1) {
            self::assertArrayHasKey($isoCode, $rules2);
            $lang2 = $rules2[$isoCode];

            self::assertInstanceOf(LanguageRulesFragment::class, $lang2);
            self::assertSame($lang1->name, $lang2->name);

            self::assertCount(count($lang1->cardinal), $lang2->cardinal);
            foreach ($lang1->cardinal as $i => $cat1) {
                $cat2 = $lang2->cardinal[$i];
                self::assertInstanceOf(CategoryFragment::class, $cat2);
                self::assertSame($cat1->category, $cat2->category);
                self::assertSame($cat1->rule, $cat2->rule);
                self::assertSame($cat1->human_rule, $cat2->human_rule);
                self::assertSame($cat1->example, $cat2->example);
            }

            self::assertCount(count($lang1->ordinal), $lang2->ordinal);
            foreach ($lang1->ordinal as $i => $cat1) {
                $cat2 = $lang2->ordinal[$i];
                self::assertSame($cat1->category, $cat2->category);
                self::assertSame($cat1->rule, $cat2->rule);
                self::assertSame($cat1->human_rule, $cat2->human_rule);
                self::assertSame($cat1->example, $cat2->example);
            }
        }
    }

    // =========================================================================
    // CategoryFragment DTO
    // =========================================================================

    /**
     * @return void
     */
    public function testCategoryFragmentPublicProperties(): void
    {
        $fragment = new CategoryFragment(
            category:   'one',
            rule:       'n = 1',
            human_rule: 'Exactly 1',
            example:    '1',
        );

        self::assertSame('one', $fragment->category);
        self::assertSame('n = 1', $fragment->rule);
        self::assertSame('Exactly 1', $fragment->human_rule);
        self::assertSame('1', $fragment->example);
    }

    /**
     * @return void
     */
    public function testCategoryFragmentJsonSerialize(): void
    {
        $fragment = new CategoryFragment(
            category:   'few',
            rule:       'n = 3–6',
            human_rule: 'Exactly 3, 4, 5, or 6',
            example:    '3~6',
        );

        $json = $fragment->jsonSerialize();

        self::assertSame([
            'category'   => 'few',
            'rule'       => 'n = 3–6',
            'human_rule' => 'Exactly 3, 4, 5, or 6',
            'example'    => '3~6',
        ], $json);
    }

    /**
     * @return void
     */
    public function testCategoryFragmentJsonEncode(): void
    {
        $fragment = new CategoryFragment(
            category:   'other',
            rule:       '',
            human_rule: 'Any other number',
            example:    '0, 2~16, 100, 1000, …',
        );

        $encoded = json_encode($fragment, JSON_UNESCAPED_UNICODE);
        self::assertIsString($encoded);
        $decoded = json_decode($encoded, true);

        self::assertSame('other', $decoded['category']);
        self::assertSame('', $decoded['rule']);
        self::assertSame('Any other number', $decoded['human_rule']);
        self::assertSame('0, 2~16, 100, 1000, …', $decoded['example']);
    }

    // =========================================================================
    // LanguageRulesFragment DTO
    // =========================================================================

    /**
     * @return void
     */
    public function testLanguageFragmentPublicProperties(): void
    {
        $cardinal = [
            new CategoryFragment('one', 'n = 1', 'Exactly 1', '1'),
            new CategoryFragment('other', '', 'Any other number', '0, 2~16'),
        ];

        $ordinal = [
            new CategoryFragment('other', '', 'Any number', '0, 1, 2, 3'),
        ];

        $fragment = new LanguageRulesFragment('English', 'en', $cardinal, $ordinal);

        self::assertSame('English', $fragment->name);
        self::assertSame('en', $fragment->isoCode);
        self::assertSame($cardinal, $fragment->cardinal);
        self::assertSame($ordinal, $fragment->ordinal);
    }

    /**
     * @return void
     */
    public function testLanguageFragmentJsonSerialize(): void
    {
        $cardinal = [
            new CategoryFragment('one', 'n = 1', 'Exactly 1', '1'),
        ];
        $ordinal = [
            new CategoryFragment('other', '', 'Any number', '0~15'),
        ];

        $fragment = new LanguageRulesFragment('Test', 'xx', $cardinal, $ordinal);
        $json = $fragment->jsonSerialize();

        self::assertSame('Test', $json['name']);
        self::assertCount(1, $json['cardinal']);
        self::assertCount(1, $json['ordinal']);
        self::assertSame('one', $json['cardinal'][0]->category);
        self::assertSame('other', $json['ordinal'][0]->category);
    }

    /**
     * @return void
     */
    public function testLanguageFragmentJsonEncode(): void
    {
        $cardinal = [
            new CategoryFragment('one', 'i = 1 and v = 0', 'Exactly 1 (no decimals)', '1'),
            new CategoryFragment('other', '', 'Any other number', '0, 2~16'),
        ];
        $ordinal = [
            new CategoryFragment('other', '', 'Any number', '0~15'),
        ];

        $fragment = new LanguageRulesFragment('English', 'en', $cardinal, $ordinal);

        $encoded = json_encode($fragment, JSON_UNESCAPED_UNICODE);
        self::assertIsString($encoded);
        $decoded = json_decode($encoded, true);

        self::assertSame('English', $decoded['name']);
        self::assertSame('en', $decoded['isoCode']);
        self::assertCount(2, $decoded['cardinal']);
        self::assertCount(1, $decoded['ordinal']);
        self::assertSame('one', $decoded['cardinal'][0]['category']);
        self::assertSame('other', $decoded['cardinal'][1]['category']);
    }

    // =========================================================================
    // Every CategoryFragment has non-empty category and human_rule
    // =========================================================================

    /**
     * @return void
     */
    public function testAllCategoryFragmentsHaveNonEmptyCategoryAndHumanRule(): void
    {
        $rules = $this->getBuilder()->getRules();

        foreach ($rules as $isoCode => $langFragment) {
            foreach ($langFragment->cardinal as $i => $cat) {
                self::assertNotEmpty($cat->category, "Empty category in cardinal[$i] for $isoCode");
                self::assertNotEmpty($cat->human_rule, "Empty human_rule in cardinal[$i] for $isoCode");
            }
            foreach ($langFragment->ordinal as $i => $cat) {
                self::assertNotEmpty($cat->category, "Empty category in ordinal[$i] for $isoCode");
                self::assertNotEmpty($cat->human_rule, "Empty human_rule in ordinal[$i] for $isoCode");
            }
        }
    }

    // =========================================================================
    // Every language has at least one cardinal and one ordinal category
    // =========================================================================

    /**
     * @return void
     */
    public function testAllLanguagesHaveAtLeastOneCardinalAndOrdinalCategory(): void
    {
        $rules = $this->getBuilder()->getRules();

        foreach ($rules as $isoCode => $langFragment) {
            self::assertNotEmpty($langFragment->cardinal, "No cardinal rules for $isoCode");
            self::assertNotEmpty($langFragment->ordinal, "No ordinal rules for $isoCode");
        }
    }

    // =========================================================================
    // The last cardinal/ordinal category is always 'other'
    // =========================================================================

    /**
     * @return void
     */
    public function testLastCategoryIsAlwaysOther(): void
    {
        $rules = $this->getBuilder()->getRules();

        foreach ($rules as $isoCode => $langFragment) {
            $cardinals = $langFragment->cardinal;
            $lastCardinal = $cardinals[array_key_last($cardinals)];
            self::assertSame('other', $lastCardinal->category, "Last cardinal category is not 'other' for $isoCode");

            $ordinals = $langFragment->ordinal;
            $lastOrdinal = $ordinals[array_key_last($ordinals)];
            self::assertSame('other', $lastOrdinal->category, "Last ordinal category is not 'other' for $isoCode");
        }

    }

    // =========================================================================
    // Every language has a non-empty name
    // =========================================================================

    /**
     * @return void
     */
    public function testAllLanguagesHaveNonEmptyName(): void
    {
        $rules = $this->getBuilder()->getRules();

        foreach ($rules as $isoCode => $langFragment) {
            self::assertNotEmpty($langFragment->name, "Empty name for $isoCode");
        }
    }

    // =========================================================================
    // Custom file path isolation
    // =========================================================================

    /**
     * @return void
     */
    public function testCustomFilePathDoesNotAffectDefaultLocation(): void
    {
        $defaultFile = dirname(__DIR__, 4) . '/src/Locales/pluralRules.json';
        $defaultModTime = file_exists($defaultFile) ? filemtime($defaultFile) : null;

        PluralRulesBuilder::getInstance(forceRebuild: true, filePath: $this->tempFile);

        if ($defaultModTime !== null) {
            // The default file should not have been modified
            self::assertSame($defaultModTime, filemtime($defaultFile));
        }
    }

    // =========================================================================
    // Second getInstance call ignores parameters (singleton is already set)
    // =========================================================================

    /**
     * @return void
     */
    public function testSecondGetInstanceIgnoresParameters(): void
    {
        $builder1 = PluralRulesBuilder::getInstance(forceRebuild: true, filePath: $this->tempFile);

        $tempFile2 = sys_get_temp_dir() . '/pluralRulesBuilderTest_other_' . uniqid() . '.json';

        // Second call: different params, but singleton returns the same instance
        $builder2 = PluralRulesBuilder::getInstance(forceRebuild: true, filePath: $tempFile2);

        self::assertSame($builder1, $builder2);

        // The second file should NOT have been created
        self::assertFileDoesNotExist($tempFile2);
    }

    // =========================================================================
    // Specific rule groups produce correct human_rule values
    // =========================================================================

    /**
     * @return void
     */
    public function testCardinalRule0ProducesAnyNumber(): void
    {
        $rules = $this->getBuilder()->getRules();

        // Japanese uses cardinal rule group 0
        self::assertArrayHasKey('ja', $rules);
        $ja = $rules['ja'];
        self::assertCount(1, $ja->cardinal);
        self::assertSame('Any number', $ja->cardinal[0]->human_rule);
    }

    /**
     * @return void
     */
    public function testCardinalRule13ArabicSixCategories(): void
    {
        $rules = $this->getBuilder()->getRules();

        self::assertArrayHasKey('ar', $rules);
        $ar = $rules['ar'];
        self::assertCount(6, $ar->cardinal);

        self::assertSame('Exactly 0', $ar->cardinal[0]->human_rule);
        self::assertSame('Exactly 1', $ar->cardinal[1]->human_rule);
        self::assertSame('Exactly 2', $ar->cardinal[2]->human_rule);
        self::assertSame('Ends in 03–10', $ar->cardinal[3]->human_rule);
        self::assertSame('Ends in 11-99', $ar->cardinal[4]->human_rule);
        self::assertSame('Any other number', $ar->cardinal[5]->human_rule);
    }

    /**
     * @return void
     */
    public function testOrdinalRule1EnglishFourCategories(): void
    {
        $rules = $this->getBuilder()->getRules();

        self::assertArrayHasKey('en', $rules);
        $en = $rules['en'];
        self::assertCount(4, $en->ordinal);

        self::assertSame('Ends in 1 (except 11)', $en->ordinal[0]->human_rule);
        self::assertSame('Ends in 2 (except 12)', $en->ordinal[1]->human_rule);
        self::assertSame('Ends in 3 (except 13)', $en->ordinal[2]->human_rule);
        self::assertSame('Any other number', $en->ordinal[3]->human_rule);
    }

    // =========================================================================
    // Build produces the correct number of languages
    // =========================================================================

    /**
     * @return void
     */
    public function testBuildProducesMultipleLanguages(): void
    {
        $rules = $this->getBuilder()->getRules();

        // There should be many languages (the supported_langs.json has 200+)
        self::assertGreaterThan(50, count($rules));
    }

    // =========================================================================
    // CategoryFragment is readonly
    // =========================================================================

    /**
     * @return void
     * @throws ReflectionException
     */
    public function testCategoryFragmentIsReadonly(): void
    {
        $fragment = new CategoryFragment(
            category: 'one',
            rule: 'n = 1',
            human_rule: 'Exactly 1',
            example: '1',
        );

        $reflection = new ReflectionClass($fragment);
        foreach (['category', 'rule', 'human_rule', 'example'] as $prop) {
            $property = $reflection->getProperty($prop);
            self::assertTrue($property->isReadOnly(), "Property $prop should be readonly");
            self::assertTrue($property->isPublic(), "Property $prop should be public");
        }
    }

    // =========================================================================
    // LanguageRulesFragment is readonly
    // =========================================================================

    /**
     * @return void
     * @throws ReflectionException
     */
    public function testLanguageFragmentIsReadonly(): void
    {
        $fragment = new LanguageRulesFragment('Test', 'xx', [], []);

        $reflection = new ReflectionClass($fragment);
        foreach (['name', 'isoCode', 'cardinal', 'ordinal'] as $prop) {
            $property = $reflection->getProperty($prop);
            self::assertTrue($property->isReadOnly(), "Property $prop should be readonly");
            self::assertTrue($property->isPublic(), "Property $prop should be public");
        }
    }

    // =========================================================================
    // getLanguageRules()
    // =========================================================================

    /**
     * @return void
     */
    public function testGetLanguageRulesWithIsoCode(): void
    {
        $builder = $this->getBuilder();

        $fragment = $builder->getLanguageRules('en');
        self::assertInstanceOf(LanguageRulesFragment::class, $fragment);
        self::assertCount(2, $fragment->cardinal);
        self::assertSame('one', $fragment->cardinal[0]->category);
    }

    /**
     * @return void
     */
    public function testGetLanguageRulesWithRfcCodeReturnsNull(): void
    {
        $builder = $this->getBuilder();

        $fragment = $builder->getLanguageRules('en-US');
        self::assertNull($fragment);
    }

    /**
     * @return void
     */
    public function testGetLanguageRulesWithRegionalVariantReturnsNull(): void
    {
        $builder = $this->getBuilder();

        $fragment = $builder->getLanguageRules('pt-BR');
        self::assertNull($fragment);
    }

    /**
     * @return void
     */
    public function testGetLanguageRulesReturnsNullForUnknownCode(): void
    {
        $builder = $this->getBuilder();

        $fragment = $builder->getLanguageRules('xxx');
        self::assertNull($fragment);
    }

    /**
     * @return void
     */
    public function testGetLanguageRulesMatchesGetRules(): void
    {
        $builder = $this->getBuilder();

        $allRules = $builder->getRules();
        foreach (['en', 'ar', 'ja', 'ru', 'fr', 'it', 'pl'] as $iso) {
            if (!isset($allRules[$iso])) {
                continue;
            }
            self::assertSame($allRules[$iso], $builder->getLanguageRules($iso));
        }
    }

    /**
     * @return void
     */
    public function testGetLanguageRulesArabicHasSixCardinalCategories(): void
    {
        $builder = $this->getBuilder();

        $fragment = $builder->getLanguageRules('ar');
        self::assertNotNull($fragment);
        self::assertCount(6, $fragment->cardinal);

        $categories = array_map(fn(CategoryFragment $f) => $f->category, $fragment->cardinal);
        self::assertSame(['zero', 'one', 'two', 'few', 'many', 'other'], $categories);
    }
}

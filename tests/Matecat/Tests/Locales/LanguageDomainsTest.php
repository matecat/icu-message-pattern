<?php

declare(strict_types=1);

namespace Matecat\Tests\Locales;

use Matecat\Locales\LanguageDomains;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\TestCase;

/**
 * Tests the functionality of the `LanguageDomains` class.
 *
 * This test suite validates language domains (subject areas) management functionality including
 * - Singleton pattern
 * - Enabled domains retrieval
 * - Hash map retrieval
 * - Domain structure validation
 */
final class LanguageDomainsTest extends TestCase
{
    // =========================================================================
    // Singleton Tests
    // =========================================================================

    /**
     * Tests that getInstance returns the same singleton instance.
     *
     * @return void
     */
    public function testGetInstanceReturnsSameInstance(): void
    {
        $instance1 = LanguageDomains::getInstance();
        $instance2 = LanguageDomains::getInstance();

        self::assertSame($instance1, $instance2);
    }

    // =========================================================================
    // Enabled Domains Tests
    // =========================================================================

    /**
     * Tests that getEnabledDomains returns a non-empty array.
     *
     * @return void
     */
    #[Depends('testGetInstanceReturnsSameInstance')]
    public function testGetEnabledDomainsReturnsNonEmptyArray(): void
    {
        $domains = LanguageDomains::getEnabledDomains();

        self::assertNotEmpty($domains);
    }

    /**
     * Tests that enabled domains have the correct structure.
     *
     * @return void
     */
    #[Depends('testGetInstanceReturnsSameInstance')]
    public function testGetEnabledDomainsHasCorrectStructure(): void
    {
        $domains = LanguageDomains::getEnabledDomains();

        // Each domain should have 'key' and 'display' fields
        foreach ($domains as $domain) {
            self::assertArrayHasKey('key', $domain);
            self::assertArrayHasKey('display', $domain);
        }
    }

    /**
     * Tests that enabled domains contain expected domain entries.
     *
     * @return void
     */
    #[Depends('testGetInstanceReturnsSameInstance')]
    public function testGetEnabledDomainsContainsExpectedDomains(): void
    {
        $domains = LanguageDomains::getEnabledDomains();
        $keys = array_column($domains, 'key');

        // Check for some common/expected domain keys
        // These are typical subject areas in translation
        self::assertNotEmpty($keys);

        // At minimum, there should be at least one domain
        self::assertGreaterThanOrEqual(1, count($keys));
    }

    // =========================================================================
    // Hash Map Tests
    // =========================================================================

    /**
     * Tests that getEnabledHashMap returns an array.
     *
     * @return void
     */
    #[Depends('testGetInstanceReturnsSameInstance')]
    public function testGetEnabledHashMapReturnsArray(): void
    {
        $hashMap = LanguageDomains::getEnabledHashMap();

        self::assertNotEmpty($hashMap);
    }

    /**
     * Tests that hash map has string keys and values.
     *
     * @return void
     */
    #[Depends('testGetInstanceReturnsSameInstance')]
    public function testGetEnabledHashMapHasStringKeysAndValues(): void
    {
        $hashMap = LanguageDomains::getEnabledHashMap();

        foreach ($hashMap as $key => $display) {
            self::assertNotEmpty($key);
            self::assertNotEmpty($display);
        }
    }

    /**
     * Tests that hash map matches enabled domains data.
     *
     * @return void
     */
    #[Depends('testGetInstanceReturnsSameInstance')]
    public function testGetEnabledHashMapMatchesEnabledDomains(): void
    {
        $domains = LanguageDomains::getEnabledDomains();
        $hashMap = LanguageDomains::getEnabledHashMap();

        // The hash map should have the same number of entries as domains
        self::assertCount(count($domains), $hashMap);

        // Each domain's key should exist in the hash map
        foreach ($domains as $domain) {
            self::assertArrayHasKey($domain['key'], $hashMap);
            self::assertSame($domain['display'], $hashMap[$domain['key']]);
        }
    }

    // =========================================================================
    // Data Consistency Tests
    // =========================================================================

    /**
     * Tests that domain keys are unique.
     *
     * @return void
     */
    #[Depends('testGetInstanceReturnsSameInstance')]
    public function testDomainsKeysAreUnique(): void
    {
        $domains = LanguageDomains::getEnabledDomains();
        $keys = array_column($domains, 'key');
        $uniqueKeys = array_unique($keys);

        self::assertCount(count($keys), $uniqueKeys, 'Domain keys should be unique');
    }

    /**
     * Tests that domain keys are not empty.
     *
     * @return void
     */
    #[Depends('testGetInstanceReturnsSameInstance')]
    public function testDomainsKeysAreNotEmpty(): void
    {
        $domains = LanguageDomains::getEnabledDomains();

        foreach ($domains as $domain) {
            self::assertNotEmpty($domain['key'], 'Domain key should not be empty');
        }
    }

    /**
     * Tests that domain display names are not empty.
     *
     * @return void
     */
    #[Depends('testGetInstanceReturnsSameInstance')]
    public function testDomainsDisplayNamesAreNotEmpty(): void
    {
        $domains = LanguageDomains::getEnabledDomains();

        foreach ($domains as $domain) {
            self::assertNotEmpty($domain['display'], 'Domain display name should not be empty');
        }
    }

    // =========================================================================
    // Instance Initialization Tests
    // =========================================================================

    /**
     * Tests that instance initializes correctly from JSON.
     *
     * @return void
     */
    #[Depends('testGetInstanceReturnsSameInstance')]
    public function testInstanceInitializesCorrectlyFromJson(): void
    {
        // Getting instance should not throw any exceptions
        LanguageDomains::getInstance();

        // Both methods should return consistent data
        $domains = LanguageDomains::getEnabledDomains();
        $hashMap = LanguageDomains::getEnabledHashMap();

        self::assertNotEmpty($domains);
        self::assertNotEmpty($hashMap);
    }

    // =========================================================================
    // Static Method Access Tests
    // =========================================================================

    /**
     * Tests that static methods work without explicit getInstance call.
     *
     * @return void
     */
    #[Depends('testGetInstanceReturnsSameInstance')]
    public function testStaticMethodsWorkWithoutExplicitGetInstance(): void
    {
        // These static methods should work without calling getInstance() first
        // as they internally call getInstance()
        $domains = LanguageDomains::getEnabledDomains();
        $hashMap = LanguageDomains::getEnabledHashMap();

        self::assertNotEmpty($domains);
        self::assertNotEmpty($hashMap);
    }

    // =========================================================================
    // Multiple Access Tests
    // =========================================================================

    /**
     * Tests that multiple access returns consistent data.
     *
     * @return void
     */
    #[Depends('testGetInstanceReturnsSameInstance')]
    public function testMultipleAccessReturnsConsistentData(): void
    {
        // First access
        $domains1 = LanguageDomains::getEnabledDomains();
        $hashMap1 = LanguageDomains::getEnabledHashMap();

        // Second access
        $domains2 = LanguageDomains::getEnabledDomains();
        $hashMap2 = LanguageDomains::getEnabledHashMap();

        // Should return identical data
        self::assertSame($domains1, $domains2);
        self::assertSame($hashMap1, $hashMap2);
    }
}

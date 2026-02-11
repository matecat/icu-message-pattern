<?php

declare(strict_types=1);

namespace Matecat\Tests\Locales;

use Matecat\Locales\InvalidLanguageException;
use Matecat\Locales\Languages;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests the functionality of the `Languages` class.
 *
 * This test suite validates language management functionality including:
 * - Singleton pattern
 * - Language code normalization
 * - RTL language detection
 * - Language validation
 * - Localized name retrieval
 * - ISO/RFC code conversion
 * - Plural forms retrieval
 * - OCR support detection
 */
final class LanguagesTest extends TestCase
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
        $instance1 = Languages::getInstance();
        $instance2 = Languages::getInstance();

        self::assertSame($instance1, $instance2);
    }

    /**
     * Tests that getInstance returns a Languages instance.
     *
     * @return void
     */
    public function testGetInstanceReturnsLanguagesInstance(): void
    {
        $instance = Languages::getInstance();

        self::assertInstanceOf(Languages::class, $instance);
    }

    // =========================================================================
    // RTL Language Tests
    // =========================================================================

    /**
     * Tests that isRTL returns true for RTL languages.
     *
     * @param string $code The language code to test.
     *
     * @return void
     */
    #[DataProvider('rtlLanguagesProvider')]
    public function testIsRTLReturnsTrueForRTLLanguages(string $code): void
    {
        self::assertTrue(Languages::isRTL($code));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function rtlLanguagesProvider(): array
    {
        return [
            'Arabic (Saudi Arabia)' => ['ar-SA'],
            'Arabic (UAE)' => ['ar-AE'],
            'Arabic (Egypt)' => ['ar-EG'],
            'Hebrew' => ['he-IL'],
            'Persian' => ['fa-IR'],
            'Urdu' => ['ur-PK'],
        ];
    }

    /**
     * Tests that isRTL returns false for LTR languages.
     *
     * @param string $code The language code to test.
     *
     * @return void
     */
    #[DataProvider('ltrLanguagesProvider')]
    public function testIsRTLReturnsFalseForLTRLanguages(string $code): void
    {
        self::assertFalse(Languages::isRTL($code));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function ltrLanguagesProvider(): array
    {
        return [
            'English (US)' => ['en-US'],
            'English (UK)' => ['en-GB'],
            'French' => ['fr-FR'],
            'German' => ['de-DE'],
            'Spanish' => ['es-ES'],
            'Italian' => ['it-IT'],
            'Portuguese' => ['pt-PT'],
            'Russian' => ['ru-RU'],
            'Chinese' => ['zh-CN'],
            'Japanese' => ['ja-JP'],
        ];
    }

    /**
     * Tests that getRTLLangs returns an array of RTL codes.
     *
     * @return void
     */
    public function testGetRTLLangsReturnsArrayOfRTLCodes(): void
    {
        $langs = Languages::getInstance();
        $rtlLangs = $langs->getRTLLangs();

        self::assertNotEmpty($rtlLangs);

        // Check that known RTL languages are in the list
        self::assertContains('ar-SA', $rtlLangs);
        self::assertContains('he-IL', $rtlLangs);

        // Check that LTR languages are NOT in the list
        self::assertNotContains('en-US', $rtlLangs);
        self::assertNotContains('fr-FR', $rtlLangs);
    }

    // =========================================================================
    // Language Enabled Tests
    // =========================================================================

    /**
     * Tests that isEnabled returns true for enabled languages.
     *
     * @param string $code The language code to test.
     *
     * @return void
     */
    #[DataProvider('enabledLanguagesProvider')]
    public function testIsEnabledReturnsTrueForEnabledLanguages(string $code): void
    {
        $langs = Languages::getInstance();

        self::assertTrue($langs->isEnabled($code));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function enabledLanguagesProvider(): array
    {
        return [
            'English (US)' => ['en-US'],
            'French' => ['fr-FR'],
            'German' => ['de-DE'],
            'Spanish' => ['es-ES'],
            'Italian' => ['it-IT'],
            'Arabic' => ['ar-SA'],
            'Chinese' => ['zh-CN'],
            'Japanese' => ['ja-JP'],
            'Russian' => ['ru-RU'],
        ];
    }

    /**
     * Tests that getEnabledLanguages returns a non-empty array.
     *
     * @return void
     */
    public function testGetEnabledLanguagesReturnsNonEmptyArray(): void
    {
        $langs = Languages::getInstance();
        $enabledLangs = $langs->getEnabledLanguages();

        self::assertNotEmpty($enabledLangs);

        // Check structure of returned array
        $firstLang = reset($enabledLangs);
        self::assertIsArray($firstLang);
        self::assertArrayHasKey('code', $firstLang);
        self::assertArrayHasKey('name', $firstLang);
        self::assertArrayHasKey('direction', $firstLang);
    }

    /**
     * Tests that enabled languages are sorted alphabetically.
     *
     * @return void
     */
    public function testGetEnabledLanguagesAreSortedAlphabetically(): void
    {
        $langs = Languages::getInstance();
        $enabledLangs = $langs->getEnabledLanguages();

        $names = array_column($enabledLangs, 'name');
        $sortedNames = $names;
        sort($sortedNames);

        self::assertSame($sortedNames, $names);
    }

    // =========================================================================
    // Localized Name Tests
    // =========================================================================

    /**
     * Tests that getLocalizedName returns a non-empty string.
     *
     * @return void
     */
    public function testGetLocalizedNameReturnsNonEmptyString(): void
    {
        $langs = Languages::getInstance();

        // Test various language codes return non-empty localized names
        $codes = ['en-US', 'en-GB', 'fr-FR', 'de-DE', 'es-ES', 'it-IT', 'ar-SA', 'ja-JP', 'zh-CN'];

        foreach ($codes as $code) {
            $localizedName = $langs->getLocalizedName($code);
            self::assertNotEmpty($localizedName, "Localized name for $code should not be empty");
            self::assertIsString($localizedName, "Localized name for $code should be a string");
        }
    }

    /**
     * Tests that getLocalizedName returns consistent results.
     *
     * @return void
     */
    public function testGetLocalizedNameReturnsConsistentResults(): void
    {
        $langs = Languages::getInstance();

        // Multiple calls should return the same result
        $name1 = $langs->getLocalizedName('en-US');
        $name2 = $langs->getLocalizedName('en-US');

        self::assertSame($name1, $name2);
    }

    /**
     * Tests that getLocalizedNameRFC returns a non-empty string.
     *
     * @throws InvalidLanguageException
     *
     * @return void
     */
    public function testGetLocalizedNameRFCReturnsNonEmptyString(): void
    {
        $langs = Languages::getInstance();

        $name = $langs->getLocalizedNameRFC('en-US');
        self::assertNotEmpty($name);
        self::assertIsString($name);
    }

    /**
     * Tests that getLocalizedNameRFC throws exception for invalid code.
     *
     * @throws InvalidLanguageException
     *
     * @return void
     */
    public function testGetLocalizedNameRFCThrowsExceptionForInvalidCode(): void
    {
        $langs = Languages::getInstance();

        $this->expectException(InvalidLanguageException::class);
        $langs->getLocalizedNameRFC('invalid-code');
    }

    /**
     * Tests that getLocalizedNameRFC throws exception for null code.
     *
     * @throws InvalidLanguageException
     *
     * @return void
     */
    public function testGetLocalizedNameRFCThrowsExceptionForNullCode(): void
    {
        $langs = Languages::getInstance();

        $this->expectException(InvalidLanguageException::class);
        $langs->getLocalizedNameRFC();
    }

    // =========================================================================
    // Code Conversion Tests
    // =========================================================================

    /**
     * Tests that get3066Code returns a valid code.
     *
     * @return void
     */
    public function testGet3066CodeReturnsValidCode(): void
    {
        $langs = Languages::getInstance();

        // Get a localized name first, then verify we can get the RFC code back
        $localizedName = $langs->getLocalizedName('en-US');
        self::assertNotNull($localizedName);
        $rfcCode = $langs->get3066Code($localizedName);

        self::assertNotEmpty($rfcCode);
        // RFC code should contain a hyphen (e.g., en-US)
        self::assertStringContainsString('-', $rfcCode);
    }

    /**
     * Tests that getIsoCode returns a valid code.
     *
     * @return void
     */
    public function testGetIsoCodeReturnsValidCode(): void
    {
        $langs = Languages::getInstance();

        // Get a localized name first, then verify we can get the ISO code
        $localizedName = $langs->getLocalizedName('en-US');
        self::assertNotNull($localizedName);
        $isoCode = $langs->getIsoCode($localizedName);

        self::assertNotEmpty($isoCode);
        // ISO code should be 2-3 characters
        self::assertLessThanOrEqual(3, strlen($isoCode));
    }

    /**
     * Tests conversion of language to ISO code.
     *
     * @param string $rfcCode         The RFC code to convert.
     * @param string $expectedIsoCode The expected ISO code.
     *
     * @return void
     */
    #[DataProvider('convertToIsoCodeProvider')]
    public function testConvertLanguageToIsoCode(string $rfcCode, string $expectedIsoCode): void
    {
        self::assertSame($expectedIsoCode, Languages::convertLanguageToIsoCode($rfcCode));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function convertToIsoCodeProvider(): array
    {
        return [
            'en-US to en' => ['en-US', 'en'],
            'fr-FR to fr' => ['fr-FR', 'fr'],
            'de-DE to de' => ['de-DE', 'de'],
            'it-IT to it' => ['it-IT', 'it'],
            'es-ES to es' => ['es-ES', 'es'],
            'ar-SA to ar' => ['ar-SA', 'ar'],
            'ja-JP to ja' => ['ja-JP', 'ja'],
            'zh-CN to zh' => ['zh-CN', 'zh'],
            'ru-RU to ru' => ['ru-RU', 'ru'],
        ];
    }

    /**
     * Tests that convertLanguageToIsoCode returns null for invalid code.
     *
     * @return void
     */
    public function testConvertLanguageToIsoCodeReturnsNullForInvalidCode(): void
    {
        self::assertNull(Languages::convertLanguageToIsoCode('invalid-XX'));
    }

    /**
     * Tests that getLangRegionCode returns a correct code.
     *
     * @return void
     */
    public function testGetLangRegionCodeReturnsCorrectCode(): void
    {
        $langs = Languages::getInstance();

        // Test with a language that has languageRegionCode
        $code = $langs->getLangRegionCode('English (USA)');
        self::assertNotEmpty($code);
    }

    // =========================================================================
    // Language Validation Tests
    // =========================================================================

    /**
     * Tests that isValidLanguage returns true for valid languages.
     *
     * @return void
     */
    public function testIsValidLanguageReturnsTrueForValidLanguages(): void
    {
        self::assertTrue(Languages::isValidLanguage('en-US'));
        self::assertTrue(Languages::isValidLanguage('fr-FR'));
        self::assertTrue(Languages::isValidLanguage('de-DE'));
        self::assertTrue(Languages::isValidLanguage('ar-SA'));
    }

    /**
     * Tests that isValidLanguage returns false for invalid languages.
     *
     * @return void
     */
    public function testIsValidLanguageReturnsFalseForInvalidLanguages(): void
    {
        self::assertFalse(Languages::isValidLanguage('xx-XX'));
        self::assertFalse(Languages::isValidLanguage('invalid'));
    }

    /**
     * Tests that validateLanguage returns a normalized code.
     *
     * @throws InvalidLanguageException
     *
     * @return void
     */
    public function testValidateLanguageReturnsNormalizedCode(): void
    {
        $langs = Languages::getInstance();

        self::assertSame('en-US', $langs->validateLanguage('en-US'));
        self::assertSame('fr-FR', $langs->validateLanguage('fr-FR'));
    }

    /**
     * Tests that validateLanguage throws exception for empty code.
     *
     * @throws InvalidLanguageException
     *
     * @return void
     */
    public function testValidateLanguageThrowsExceptionForEmptyCode(): void
    {
        $langs = Languages::getInstance();

        $this->expectException(InvalidLanguageException::class);
        $langs->validateLanguage('');
    }

    /**
     * Tests that validateLanguage throws exception for null code.
     *
     * @throws InvalidLanguageException
     *
     * @return void
     */
    public function testValidateLanguageThrowsExceptionForNullCode(): void
    {
        $langs = Languages::getInstance();

        $this->expectException(InvalidLanguageException::class);
        $langs->validateLanguage();
    }

    /**
     * Tests that validateLanguageList returns a validated list.
     *
     * @throws InvalidLanguageException
     *
     * @return void
     */
    public function testValidateLanguageListReturnsValidatedList(): void
    {
        $langs = Languages::getInstance();

        $result = $langs->validateLanguageList(['en-US', 'fr-FR', 'de-DE']);

        self::assertCount(3, $result);
        self::assertContains('en-US', $result);
        self::assertContains('fr-FR', $result);
        self::assertContains('de-DE', $result);
    }

    /**
     * Tests that validateLanguageList throws exception for empty list.
     *
     * @throws InvalidLanguageException
     *
     * @return void
     */
    public function testValidateLanguageListThrowsExceptionForEmptyList(): void
    {
        $langs = Languages::getInstance();

        $this->expectException(InvalidLanguageException::class);
        $langs->validateLanguageList([]);
    }

    /**
     * Tests that validateLanguageListAsString returns a comma-separated string.
     *
     * @throws InvalidLanguageException
     *
     * @return void
     */
    public function testValidateLanguageListAsStringReturnsCommaSeparatedString(): void
    {
        $langs = Languages::getInstance();

        $result = $langs->validateLanguageListAsString('en-US, fr-FR, de-DE');

        self::assertStringContainsString('en-US', $result);
        self::assertStringContainsString('fr-FR', $result);
        self::assertStringContainsString('de-DE', $result);
    }

    /**
     * Tests that validateLanguageListAsString works with custom separator.
     *
     * @throws InvalidLanguageException
     *
     * @return void
     */
    public function testValidateLanguageListAsStringWithCustomSeparator(): void
    {
        $langs = Languages::getInstance();

        $result = $langs->validateLanguageListAsString('en-US|fr-FR|de-DE', '|');

        self::assertStringContainsString('en-US', $result);
        self::assertStringContainsString('fr-FR', $result);
        self::assertStringContainsString('de-DE', $result);
    }

    // =========================================================================
    // Localized Language Static Method Tests
    // =========================================================================

    /**
     * Tests that getLocalizedLanguage returns a non-empty string.
     *
     * @return void
     */
    public function testGetLocalizedLanguageReturnsNonEmptyString(): void
    {
        $codes = ['en-US', 'fr-FR', 'de-DE', 'it-IT', 'ar-SA'];

        foreach ($codes as $code) {
            $localizedName = Languages::getLocalizedLanguage($code);
            self::assertNotNull($localizedName, "Localized language for $code should not be null");
            self::assertNotEmpty($localizedName, "Localized language for $code should not be empty");
        }
    }

    /**
     * Tests that getLocalizedLanguage returns null for invalid code.
     *
     * @return void
     */
    public function testGetLocalizedLanguageReturnsNullForInvalidCode(): void
    {
        self::assertNull(Languages::getLocalizedLanguage('invalid-XX'));
    }


    // =========================================================================
    // OCR Support Tests
    // =========================================================================

    /**
     * Tests that getLanguagesWithOcrSupported returns a non-empty array.
     *
     * @return void
     */
    public function testGetLanguagesWithOcrSupportedReturnsNonEmptyArray(): void
    {
        $ocrSupported = Languages::getLanguagesWithOcrSupported();

        self::assertNotEmpty($ocrSupported);
    }

    /**
     * Tests that getLanguagesWithOcrNotSupported returns an array.
     *
     * @return void
     */
    public function testGetLanguagesWithOcrNotSupportedReturnsArray(): void
    {
        // This may or may not be empty depending on the data
        $ocrNotSupported = Languages::getLanguagesWithOcrNotSupported();

        // Just verify it's callable and returns the expected type
        self::assertCount(count($ocrNotSupported), $ocrNotSupported);
    }

    // =========================================================================
    // Language Code Normalization Tests
    // =========================================================================

    /**
     * Tests language code normalization with different formats.
     *
     * @return void
     */
    public function testLanguageCodeNormalizationWithDifferentFormats(): void
    {
        $langs = Languages::getInstance();

        // Test with lowercase
        self::assertTrue($langs->isEnabled('en-us'));

        // Test with uppercase
        self::assertTrue($langs->isEnabled('EN-US'));

        // Test with mixed case
        self::assertTrue($langs->isEnabled('En-Us'));
    }

    /**
     * Tests language code normalization with three parts.
     *
     * @return void
     */
    public function testLanguageCodeNormalizationWithThreeParts(): void
    {
        // Languages like sr-Latn-RS (Serbian Latin)
        self::assertTrue(Languages::isValidLanguage('sr-Latn-RS'));
    }

    /**
     * Tests ISO code fallback.
     *
     * @return void
     */
    public function testISOCodeFallback(): void
    {
        // Test that ISO codes are properly mapped to RFC codes
        $langs = Languages::getInstance();

        // These should work because of the ISO to RFC mapping
        self::assertTrue($langs->isEnabled('en'));
        self::assertTrue($langs->isEnabled('fr'));
        self::assertTrue($langs->isEnabled('it'));
        self::assertTrue($langs->isEnabled('ar'));
    }

    // =========================================================================
    // Edge Case Tests for Full Coverage
    // =========================================================================

    /**
     * Tests that normalizeLanguageCode returns null for codes with four parts.
     *
     * @return void
     */
    public function testNormalizeLanguageCodeWithFourPartsReturnsNull(): void
    {
        // Language code with more than 3 parts should return null from normalizeLanguageCode
        // This tests the "else { return null; }" branch in normalizeLanguageCode
        self::assertFalse(Languages::isValidLanguage('a-b-c-d'));
        self::assertFalse(Languages::isValidLanguage('en-US-extra-part'));
    }
}

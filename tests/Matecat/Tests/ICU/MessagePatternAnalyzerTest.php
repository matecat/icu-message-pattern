<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 04/02/26
 * Time: 17:54
 *
 */

namespace Matecat\ICU\Tests;

use Matecat\ICU\MessagePattern;
use Matecat\ICU\MessagePatternAnalyzer;
use Matecat\ICU\PluralComplianceException;
use Matecat\ICU\PluralRules\PluralRules;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MessagePatternAnalyzerTest extends TestCase
{

    #[Test]
    public function testContainsComplexSyntax(): void
    {
        $complexPattern = new MessagePattern();
        $complexPattern->parse('You have {count, plural, one{# file} other{# files}}.');
        $complexAnalyzer = new MessagePatternAnalyzer($complexPattern);
        self::assertTrue($complexAnalyzer->containsComplexSyntax());

        $simplePattern = new MessagePattern();
        $simplePattern->parse('Hello {name}.');
        $simpleAnalyzer = new MessagePatternAnalyzer($simplePattern);
        self::assertFalse($simpleAnalyzer->containsComplexSyntax());
    }

    // =========================================================================
    // validatePluralCompliance() Tests
    // =========================================================================

    #[Test]
    public function testValidatePluralComplianceWithNoPluralForms(): void
    {
        $pattern = new MessagePattern();
        $pattern->parse('Hello {name}.');
        $analyzer = new MessagePatternAnalyzer($pattern, 'en');

        // Should not throw when there are no plural forms
        $analyzer->validatePluralCompliance();
    }

    #[Test]
    public function testValidatePluralComplianceValidEnglish(): void
    {
        $pattern = new MessagePattern();
        $pattern->parse('You have {count, plural, one{# item} other{# items}}.');
        $analyzer = new MessagePatternAnalyzer($pattern, 'en');

        // Should not throw when valid
        $analyzer->validatePluralCompliance();
    }

    #[Test]
    public function testValidatePluralComplianceValidArabic(): void
    {
        $pattern = new MessagePattern();
        $pattern->parse('{count, plural, zero{no items} one{one item} two{two items} few{# items} many{# items} other{# item}}');
        $analyzer = new MessagePatternAnalyzer($pattern, 'ar');

        // Should not throw when all categories are present
        $analyzer->validatePluralCompliance();
    }

    #[Test]
    public function testValidatePluralComplianceInvalidSelectorsForEnglish(): void
    {
        // English only has 'one' and 'other', so 'few' and 'many' are invalid
        $pattern = new MessagePattern();
        $pattern->parse('{count, plural, one{# item} few{# items} many{# items} other{# items}}');
        $analyzer = new MessagePatternAnalyzer($pattern, 'en');

        self::expectException(PluralComplianceException::class);
        self::expectExceptionMessageMatches('/Invalid selectors found/');

        $analyzer->validatePluralCompliance();
    }

    #[Test]
    public function testValidatePluralComplianceMissingCategories(): void
    {
        // Russian expects 'one', 'few', 'many' - only providing 'one' and 'other'
        // Note: 'other' is NOT in Russian's expected categories, so it's invalid
        $pattern = new MessagePattern();
        $pattern->parse('{count, plural, one{# item} other{# items}}');
        $analyzer = new MessagePatternAnalyzer($pattern, 'ru');

        self::expectException(PluralComplianceException::class);
        self::expectExceptionMessageMatches('/Invalid selectors found|Missing categories/');

        $analyzer->validatePluralCompliance();
    }

    #[Test]
    public function testValidatePluralComplianceWithExplicitNumericSelectors(): void
    {
        // Explicit numeric selectors (=0, =1, =2) are always valid as selectors,
        // but if we only have them and no category selectors, we're missing required categories
        $pattern = new MessagePattern();
        $pattern->parse('{count, plural, =0{no items} =1{one item} one{# item} other{# items}}');
        $analyzer = new MessagePatternAnalyzer($pattern, 'en');

        // Should not throw - we have 'one' and 'other' categories
        $analyzer->validatePluralCompliance();
    }

    #[Test]
    public function testValidatePluralComplianceWithSelectOrdinal(): void
    {
        $pattern = new MessagePattern();
        $pattern->parse('{count, selectordinal, one{#st} two{#nd} few{#rd} other{#th}}');
        $analyzer = new MessagePatternAnalyzer($pattern, 'en');

        // English selectordinal has: one, two, few, other - but English only expects one/other
        // So two and few are invalid
        self::expectException(PluralComplianceException::class);

        $analyzer->validatePluralCompliance();
    }

    #[Test]
    public function testValidatePluralComplianceWithOffset(): void
    {
        $pattern = new MessagePattern();
        $pattern->parse('{count, plural, =0{no one} =1{just you} one{you and # other} other{you and # others}}');
        $analyzer = new MessagePatternAnalyzer($pattern, 'en');

        // Should not throw
        $analyzer->validatePluralCompliance();
    }

    #[Test]
    public function testValidatePluralComplianceWithNestedPlurals(): void
    {
        $pattern = new MessagePattern();
        $pattern->parse('{gender, select, male{{count, plural, one{He has # item} other{He has # items}}} female{{count, plural, one{She has # item} other{She has # items}}} other{{count, plural, one{They have # item} other{They have # items}}}}');
        $analyzer = new MessagePatternAnalyzer($pattern, 'en');

        // Should not throw for valid nested plurals
        $analyzer->validatePluralCompliance();
    }

    #[Test]
    public function testValidatePluralComplianceWithLocaleVariants(): void
    {
        $pattern = new MessagePattern();
        $pattern->parse('{count, plural, one{# item} other{# items}}');

        // Test with underscore locale
        $analyzer1 = new MessagePatternAnalyzer($pattern, 'en_US');
        $analyzer1->validatePluralCompliance();

        // Test with hyphen locale
        $analyzer2 = new MessagePatternAnalyzer($pattern, 'en-GB');
        $analyzer2->validatePluralCompliance();
    }

    #[Test]
    public function testValidatePluralComplianceUnknownLocale(): void
    {
        $pattern = new MessagePattern();
        $pattern->parse('{count, plural, other{# items}}');
        $analyzer = new MessagePatternAnalyzer($pattern, 'unknown');

        // Unknown locales default to rule 0 (Asian, no plural) which only has 'other'
        $analyzer->validatePluralCompliance();
    }

    #[DataProvider('pluralComplianceProvider')]
    #[Test]
    public function testValidatePluralComplianceVariousLocales(
        string $locale,
        string $message,
        bool $shouldThrow,
        array $expectedInvalidSelectors
    ): void {
        $pattern = new MessagePattern();
        $pattern->parse($message);
        $analyzer = new MessagePatternAnalyzer($pattern, $locale);

        if ($shouldThrow) {
            try {
                $analyzer->validatePluralCompliance();
                self::fail('Expected PluralComplianceException to be thrown');
            } catch (PluralComplianceException $e) {
                // Verify the expected invalid selectors are in the exception
                foreach ($expectedInvalidSelectors as $selector) {
                    self::assertContains($selector, $e->invalidSelectors);
                }
            }
        } else {
            $analyzer->validatePluralCompliance();
        }
    }

    /**
     * @return array<array{string, string, bool, array<string>}>
     */
    public static function pluralComplianceProvider(): array
    {
        return [
            // Polish with invalid 'two' selector - Polish expects one/few/many, not two/other
            ['pl', '{n, plural, one{# file} two{# files} other{# files}}', true, ['two', 'other']],

            // Czech: one, few, other - complete
            ['cs', '{n, plural, one{# file} few{# files} other{# files}}', false, []],
            // Czech with invalid 'many' selector
            ['cs', '{n, plural, one{# file} many{# files} other{# files}}', true, ['many']],

            // Japanese: only other (no plural forms)
            ['ja', '{n, plural, other{# items}}', false, []],

            // French: one, other
            ['fr', '{n, plural, one{# element} other{# elements}}', false, []],
            // French with invalid 'zero' selector
            ['fr', '{n, plural, zero{none} one{# element} other{# elements}}', true, ['zero']],
        ];
    }

    #[Test]
    public function testPluralComplianceExceptionProperties(): void
    {
        $pattern = new MessagePattern();
        $pattern->parse('{count, plural, one{# item} few{# items} other{# items}}');
        $analyzer = new MessagePatternAnalyzer($pattern, 'en');

        try {
            $analyzer->validatePluralCompliance();
            self::fail('Expected PluralComplianceException to be thrown');
        } catch (PluralComplianceException $e) {
            self::assertSame([PluralRules::CATEGORY_ONE, PluralRules::CATEGORY_OTHER], $e->expectedCategories);
            self::assertContains('few', $e->invalidSelectors);
            self::assertEmpty($e->missingCategories); // English only expects one/other
            self::assertTrue($e->hasComplexPluralForm);
            self::assertStringContainsString('Invalid selectors found', $e->getMessage());
        }
    }

    #[Test]
    public function testPluralComplianceExceptionIsMissingOther(): void
    {
        $pattern = new MessagePattern();
        // Russian expects one/few/many - providing one/few/many/other (other is invalid)
        $pattern->parse('{count, plural, one{# item} few{# items} many{# items} other{# items}}');
        $analyzer = new MessagePatternAnalyzer($pattern, 'ru');

        try {
            $analyzer->validatePluralCompliance();
            self::fail('Expected PluralComplianceException to be thrown');
        } catch (PluralComplianceException $e) {
            // Russian doesn't require 'other', so this shouldn't be missing
            self::assertFalse($e->isMissingOther());
            // But 'other' is in invalid selectors since it's not in Russian's expected categories
            self::assertContains('other', $e->invalidSelectors);
        }
    }

}
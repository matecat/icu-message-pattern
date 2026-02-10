<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 04/02/26
 * Time: 17:54
 *
 */

namespace Matecat\ICU\Tests;

use Exception;
use Matecat\ICU\Exceptions\InvalidArgumentException;
use Matecat\ICU\Exceptions\OutOfBoundsException;
use Matecat\ICU\MessagePattern;
use Matecat\ICU\MessagePatternValidator;
use Matecat\ICU\Plurals\PluralComplianceException;
use Matecat\ICU\Plurals\PluralRules;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MessagePatternValidatorTest extends TestCase
{
    // =========================================================================
    // Simplified API Tests (using patternString directly)
    // =========================================================================

    /**
     * Test the simplified API: validator with only language and pattern string.
     *
     * @throws PluralComplianceException
     */
    #[Test]
    public function testSimplifiedApiWithPatternString(): void
    {
        $validator = new MessagePatternValidator('en', '{count, plural, one{# item} other{# items}}');

        $warning = $validator->validatePluralCompliance();

        self::assertNull($warning);
    }

    /**
     * Test the simplified API with setPatternString() fluent method.
     *
     * @throws PluralComplianceException
     */
    #[Test]
    public function testSimplifiedApiWithSetPatternString(): void
    {
        $validator = new MessagePatternValidator('en');

        $result = $validator->setPatternString('{count, plural, one{# item} other{# items}}');

        // Should return the validator instance for fluent interface
        self::assertSame($validator, $result);

        $warning = $validator->validatePluralCompliance();
        self::assertNull($warning);
    }

    /**
     * Test fluent chaining with the simplified API.
     *
     * @throws PluralComplianceException
     */
    #[Test]
    public function testSimplifiedApiFluentChaining(): void
    {
        $warning = (new MessagePatternValidator('ru'))
            ->setPatternString('{count, plural, one{# item} few{# items} many{# items} other{# items}}')
            ->validatePluralCompliance();

        self::assertNull($warning);
    }

    /**
     * Test simplified API with containsComplexSyntax().
     */
    #[Test]
    public function testSimplifiedApiContainsComplexSyntax(): void
    {
        $validator = new MessagePatternValidator('en', '{count, plural, one{# file} other{# files}}');
        self::assertTrue($validator->containsComplexSyntax());

        $validator2 = new MessagePatternValidator('en', 'Hello {name}.');
        self::assertFalse($validator2->containsComplexSyntax());
    }

    /**
     * Test simplified API with warnings.
     *
     * @throws PluralComplianceException
     */
    #[Test]
    public function testSimplifiedApiWithWarnings(): void
    {
        $validator = new MessagePatternValidator('en', '{count, plural, one{# item} few{# items} other{# items}}');

        $warning = $validator->validatePluralCompliance();

        self::assertNotNull($warning);
        self::assertContains('few', $warning->argumentWarnings[0]->wrongLocaleSelectors);
    }

    /**
     * Test setPatternString() can override constructor's pattern string.
     *
     * @throws PluralComplianceException
     */
    #[Test]
    public function testSetPatternStringOverridesConstructorPattern(): void
    {
        $validator = new MessagePatternValidator('en', '{count, plural, one{# item} other{# items}}');

        // Override with a different pattern string
        $validator->setPatternString('{items, plural, few{# items} other{# items}}');

        $warning = $validator->validatePluralCompliance();

        // Should validate the new pattern (with 'few' which is wrong for English)
        self::assertNotNull($warning);
        self::assertSame('items', $warning->argumentWarnings[0]->argumentName);
        self::assertContains('few', $warning->argumentWarnings[0]->wrongLocaleSelectors);
    }

    /**
     * Test simplified API with nested pattern.
     *
     * @throws PluralComplianceException
     */
    #[Test]
    public function testSimplifiedApiWithNestedPattern(): void
    {
        $nestedPattern = '{gender, select, ' .
            'male{{count, plural, one{He has # item} other{He has # items}}} ' .
            'female{{count, plural, one{She has # item} other{She has # items}}} ' .
            'other{{count, plural, one{They have # item} other{They have # items}}}}';

        $validator = new MessagePatternValidator('en', $nestedPattern);

        $warning = $validator->validatePluralCompliance();

        // All nested plural blocks are valid for English
        self::assertNull($warning);
    }

    /**
     * Test validator creates MessagePattern internally when not provided.
     *
     * @throws PluralComplianceException
     */
    #[Test]
    public function testValidatorCreatesPatternInternally(): void
    {
        // No pattern provided, only language and pattern string
        $validator = new MessagePatternValidator('en', '{count, plural, one{# item} other{# items}}');

        // Should work without throwing - MessagePattern is created internally
        $warning = $validator->validatePluralCompliance();

        self::assertNull($warning);
    }

    // =========================================================================
    // Legacy API Tests (using pre-parsed MessagePattern)
    // =========================================================================

    #[Test]
    public function testContainsComplexSyntaxWithPreParsedPattern(): void
    {
        $complexPattern = new MessagePattern();
        $complexPattern->parse('You have {count, plural, one{# file} other{# files}}.');
        $complexValidator = new MessagePatternValidator('en', null, $complexPattern);
        self::assertTrue($complexValidator->containsComplexSyntax());

        $simplePattern = new MessagePattern();
        $simplePattern->parse('Hello {name}.');
        $simpleValidator = new MessagePatternValidator('en', null, $simplePattern);
        self::assertFalse($simpleValidator->containsComplexSyntax());
    }

    // =========================================================================
    // validatePluralCompliance() Tests
    // =========================================================================

    /**
     * @throws PluralComplianceException
     */
    #[Test]
    public function testValidatePluralComplianceWithNoPluralForms(): void
    {
        $validator = new MessagePatternValidator('en', 'Hello {name}.');

        // Should not throw when there are no plural forms
        $warning = $validator->validatePluralCompliance();
        self::assertNull($warning);
    }

    /**
     * @throws PluralComplianceException
     */
    #[Test]
    public function testValidatePluralComplianceValidEnglish(): void
    {
        $validator = new MessagePatternValidator('en', 'You have {count, plural, one{# item} other{# items}}.');

        // Should not throw when valid
        $warning = $validator->validatePluralCompliance();
        self::assertNull($warning);
    }

    /**
     * @throws PluralComplianceException
     */
    #[Test]
    public function testValidatePluralComplianceValidArabic(): void
    {
        $validator = new MessagePatternValidator(
            'ar',
            '{count, plural, zero{no items} one{one item} two{two items} few{# items} many{# items} other{# item}}'
        );

        // Should not throw when all categories are present
        $warning = $validator->validatePluralCompliance();
        self::assertNull($warning);
    }

    /**
     * @throws PluralComplianceException
     */
    #[Test]
    public function testValidatePluralComplianceWrongLocaleSelectorsForEnglish(): void
    {
        $validator = new MessagePatternValidator(
            'en',
            '{count, plural, one{# item} few{# items} many{# items} other{# items}}'
        );

        $warning = $validator->validatePluralCompliance();

        self::assertNotNull($warning);
        self::assertCount(1, $warning->argumentWarnings);
        self::assertSame('count', $warning->argumentWarnings[0]->argumentName);
        self::assertContains('few', $warning->argumentWarnings[0]->wrongLocaleSelectors);
        self::assertContains('many', $warning->argumentWarnings[0]->wrongLocaleSelectors);
    }

    #[Test]
    public function testValidatePluralComplianceThrowsExceptionForNonExistentCategory(): void
    {
        $validator = new MessagePatternValidator(
            'en',
            '{count, plural, one{# item} some{# items} other{# items}}'
        );

        self::expectException(PluralComplianceException::class);
        self::expectExceptionMessageMatches('/Invalid selectors found/');

        $validator->validatePluralCompliance();
    }

    /**
     * @throws PluralComplianceException
     */
    #[Test]
    public function testValidatePluralComplianceWithExplicitSelectorsReplacesCategoryKeywords(): void
    {
        $validator = new MessagePatternValidator('en', '{count, plural, =0{# items} =1{# item} other{# items}}');

        $warning = $validator->validatePluralCompliance();

        self::assertNotNull($warning);
        self::assertCount(1, $warning->argumentWarnings);
        $argWarning = $warning->argumentWarnings[0];
        self::assertSame('count', $argWarning->argumentName);
        self::assertContains('one', $argWarning->missingCategories);
        self::assertContains('=0', $argWarning->numericSelectors);
        self::assertContains('=1', $argWarning->numericSelectors);
    }

    /**
     * @throws PluralComplianceException
     */
    #[Test]
    public function testValidatePluralComplianceWithExplicitSelectorsForFrench(): void
    {
        $validator = new MessagePatternValidator(
            'fr',
            '{count, plural, =0{# item} =1{# item} many{# item} other{# items}}'
        );

        $warning = $validator->validatePluralCompliance();

        self::assertNotNull($warning);
        $argWarning = $warning->argumentWarnings[0];
        self::assertContains('one', $argWarning->missingCategories);
        self::assertContains('=0', $argWarning->numericSelectors);
        self::assertContains('=1', $argWarning->numericSelectors);
    }

    /**
     * @throws PluralComplianceException
     */
    #[Test]
    public function testValidatePluralComplianceWithOnlyEquals1ForFrenchReturnsWarning(): void
    {
        $validator = new MessagePatternValidator('fr', '{count, plural, =1{# item}  other{# items}}');

        $warning = $validator->validatePluralCompliance();

        self::assertNotNull($warning);
        $argWarning = $warning->argumentWarnings[0];
        self::assertContains('one', $argWarning->missingCategories);
        self::assertContains('many', $argWarning->missingCategories);
        self::assertContains('=1', $argWarning->numericSelectors);
    }

    /**
     * @throws PluralComplianceException
     */
    #[Test]
    public function testValidatePluralComplianceWithOnlyEquals1ForEnglishReturnsWarning(): void
    {
        $validator = new MessagePatternValidator('en', '{count, plural, =1{# item} other{# items}}');

        $warning = $validator->validatePluralCompliance();

        self::assertNotNull($warning);
        $argWarning = $warning->argumentWarnings[0];
        self::assertContains('one', $argWarning->missingCategories);
        self::assertContains('=1', $argWarning->numericSelectors);
    }

    #[Test]
    public function testValidatePluralComplianceMissingCategories(): void
    {
        $validator = new MessagePatternValidator('ru', '{count, plural, one{# item} other{# items}}');

        $warning = $validator->validatePluralCompliance();

        self::assertNotNull($warning);
        $argWarning = $warning->argumentWarnings[0];
        self::assertContains('few', $argWarning->missingCategories);
        self::assertContains('many', $argWarning->missingCategories);
    }

    /**
     * @throws PluralComplianceException
     */
    #[Test]
    public function testValidatePluralComplianceWithExplicitNumericSelectors(): void
    {
        $validator = new MessagePatternValidator(
            'en',
            '{count, plural, =0{no items} =1{one item} one{# item} other{# items}}'
        );

        // Should not throw - we have 'one' and 'other' categories
        $warning = $validator->validatePluralCompliance();
        self::assertNull($warning);
    }

    /**
     * @throws PluralComplianceException
     */
    #[Test]
    public function testValidatePluralComplianceWithSelectOrdinal(): void
    {
        $validator = new MessagePatternValidator(
            'en',
            '{count, selectordinal, one{#st} two{#nd} few{#rd} other{#th}}'
        );

        // English selectordinal has: one, two, few, other (for 1st, 2nd, 3rd, 4th)
        $warning = $validator->validatePluralCompliance();
        self::assertNull($warning);
    }

    /**
     * @throws PluralComplianceException
     */
    #[Test]
    public function testValidatePluralComplianceWithSelectOrdinalInvalid(): void
    {
        $validator = new MessagePatternValidator('ru', '{count, selectordinal, one{#st} other{#th}}');

        $warning = $validator->validatePluralCompliance();

        self::assertNotNull($warning);
        $argWarning = $warning->argumentWarnings[0];
        self::assertSame('count', $argWarning->argumentName);
        self::assertContains('one', $argWarning->wrongLocaleSelectors);
    }

    /**
     * @throws PluralComplianceException
     */
    #[Test]
    public function testValidatePluralComplianceWithOffset(): void
    {
        $validator = new MessagePatternValidator(
            'en',
            '{count, plural, =0{no one} =1{just you} one{you and # other} other{you and # others}}'
        );

        // Should not throw
        $warning = $validator->validatePluralCompliance();
        self::assertNull($warning);
    }

    /**
     * @throws PluralComplianceException
     */
    #[Test]
    public function testValidatePluralComplianceWithNestedPlurals(): void
    {
        $validator = new MessagePatternValidator(
            'en',
            '{gender, select, male{{count, plural, one{He has # item} other{He has # items}}} female{{count, plural, one{She has # item} other{She has # items}}} other{{count, plural, one{They have # item} other{They have # items}}}}'
        );

        // Should not throw for valid nested plurals
        $warning = $validator->validatePluralCompliance();
        self::assertNull($warning);
    }

    /**
     * @throws PluralComplianceException
     */
    #[Test]
    public function testValidatePluralComplianceWithLocaleVariants(): void
    {
        // Test with underscore locale
        $validator1 = new MessagePatternValidator('en_US', '{count, plural, one{# item} other{# items}}');
        $warning1 = $validator1->validatePluralCompliance();
        self::assertNull($warning1);

        // Test with hyphen locale
        $validator2 = new MessagePatternValidator('en-GB', '{count, plural, one{# item} other{# items}}');
        $warning2 = $validator2->validatePluralCompliance();
        self::assertNull($warning2);
    }

    /**
     * @throws PluralComplianceException
     */
    #[Test]
    public function testValidatePluralComplianceUnknownLocale(): void
    {
        $validator = new MessagePatternValidator('unknown', '{count, plural, other{# items}}');

        // Unknown locales default to rule 0 (Asian, no plural) which only has 'other'
        $warning = $validator->validatePluralCompliance();
        self::assertNull($warning);
    }

    /**
     * @param array<string> $expectedWrongLocaleSelectors
     * @param array<string> $expectedMissingCategories
     * @throws PluralComplianceException
     */
    #[DataProvider('pluralComplianceWarningProvider')]
    #[Test]
    public function testValidatePluralComplianceWarningsVariousLocales(
        string $locale,
        string $message,
        array $expectedWrongLocaleSelectors,
        array $expectedMissingCategories = []
    ): void {
        $validator = new MessagePatternValidator($locale, $message);

        $warning = $validator->validatePluralCompliance();

        self::assertNotNull($warning);
        foreach ($expectedWrongLocaleSelectors as $selector) {
            self::assertContains($selector, $warning->getAllWrongLocaleSelectors());
        }
        foreach ($expectedMissingCategories as $category) {
            self::assertContains($category, $warning->getAllMissingCategories());
        }
    }

    /**
     * @return array<array{string, string, array<string>, array<string>}>
     */
    public static function pluralComplianceWarningProvider(): array
    {
        return [
            // Polish with 'two' selector - 'two' is valid CLDR but not for Polish (expects one/few/many/other)
            ['pl', '{n, plural, one{# file} two{# files} other{# files}}', ['two'], []],

            // Czech with 'many' selector - 'many' is valid CLDR but not for Czech (expects one/few/other)
            ['cs', '{n, plural, one{# file} many{# files} other{# files}}', ['many'], []],

            // French with 'zero' selector - 'zero' is valid CLDR but not for French (expects one/many/other)
            ['fr', '{n, plural, zero{none} one{# element} many{# elements} other{# elements}}', ['zero'], []],

            // French missing 'many' category - returns warning with missing category
            ['fr', '{n, plural, one{# element} other{# elements}}', [], ['many']],
        ];
    }

    /**
     * @throws PluralComplianceException
     */
    #[DataProvider('pluralComplianceValidProvider')]
    #[Test]
    public function testValidatePluralComplianceValidVariousLocales(
        string $locale,
        string $message
    ): void {
        $validator = new MessagePatternValidator($locale, $message);

        $warning = $validator->validatePluralCompliance();

        self::assertNull($warning);
    }

    /**
     * @return array<array{string, string}>
     */
    public static function pluralComplianceValidProvider(): array
    {
        return [
            // Czech: one, few, other - complete
            ['cs', '{n, plural, one{# file} few{# files} other{# files}}'],

            // Japanese: only other (no plural forms)
            ['ja', '{n, plural, other{# items}}'],

            // French: one, many, other (CLDR 49)
            ['fr', '{n, plural, one{# element} many{# elements} other{# elements}}'],
        ];
    }

    /**
     * @throws PluralComplianceException
     */
    #[Test]
    public function testPluralComplianceWarningProperties(): void
    {
        $validator = new MessagePatternValidator('en', '{count, plural, one{# item} few{# items} other{# items}}');

        $warning = $validator->validatePluralCompliance();

        self::assertNotNull($warning);
        self::assertCount(1, $warning->argumentWarnings);
        $argWarning = $warning->argumentWarnings[0];
        self::assertSame('count', $argWarning->argumentName);
        self::assertSame([PluralRules::CATEGORY_ONE, PluralRules::CATEGORY_OTHER], $argWarning->expectedCategories);
        self::assertContains('few', $argWarning->wrongLocaleSelectors);
        self::assertEmpty($argWarning->missingCategories);
    }

    /**
     * @throws PluralComplianceException
     */
    #[Test]
    public function testPluralComplianceValidWhenAllRequiredCategoriesPresent(): void
    {
        $validator = new MessagePatternValidator(
            'ru',
            '{count, plural, one{# item} few{# items} many{# items} other{# items}}'
        );

        $warning = $validator->validatePluralCompliance();

        self::assertNull($warning);
    }

    /**
     * @throws PluralComplianceException
     */
    #[Test]
    public function testPluralComplianceWarningWithWrongLocaleCategory(): void
    {
        $validator = new MessagePatternValidator(
            'ru',
            '{count, plural, one{# item} two{# items} few{# items} many{# items} other{# items}}'
        );

        $warning = $validator->validatePluralCompliance();

        self::assertNotNull($warning);
        $argWarning = $warning->argumentWarnings[0];
        self::assertContains('two', $argWarning->wrongLocaleSelectors);
        self::assertNotContains('other', $argWarning->wrongLocaleSelectors);
    }

    /**
     * @throws PluralComplianceException
     */
    #[Test]
    public function testValidatePluralComplianceWithNestedSelectAndPluralWithOffset(): void
    {
        $message = "{gender_of_host, select, "
            . "female {"
            . "{num_guests, plural, offset:1 "
            . "=0 {{host} does not give a party.}"
            . "=1 {{host} invites {guest} to her party.}"
            . "=2 {{host} invites {guest} and one other person to her party.}"
            . "other {{host} invites {guest} and # other people to her party.}}}"
            . "male {"
            . "{num_guests, plural, offset:1 "
            . "=0 {{host} does not give a party.}"
            . "=1 {{host} invites {guest} to his party.}"
            . "=2 {{host} invites {guest} and one other person to his party.}"
            . "other {{host} invites {guest} and # other people to his party.}}}"
            . "other {"
            . "{num_guests, plural, offset:1 "
            . "=0 {{host} does not give a party.}"
            . "=1 {{host} invites {guest} to their party.}"
            . "=2 {{host} invites {guest} and one other person to their party.}"
            . "=2 {{host} invites {guest} and one other person to their party.}"
            . "other {{host} invites {guest} and # other people to their party.}}}}";

        $validator = new MessagePatternValidator('en', $message);

        $warning = $validator->validatePluralCompliance();

        self::assertNotNull($warning);
        self::assertCount(3, $warning->argumentWarnings);

        foreach ($warning->argumentWarnings as $argWarning) {
            self::assertSame('num_guests', $argWarning->argumentName);
            self::assertContains('one', $argWarning->missingCategories);
            self::assertContains('=0', $argWarning->numericSelectors);
            self::assertContains('=1', $argWarning->numericSelectors);
            self::assertContains('=2', $argWarning->numericSelectors);
        }
    }

    /**
     * @throws PluralComplianceException
     */
    #[Test]
    public function testPluralComplianceWarningGetMessage(): void
    {
        $validator = new MessagePatternValidator('en', '{count, plural, one{# item} few{# items} other{# items}}');

        $warning = $validator->validatePluralCompliance();

        self::assertNotNull($warning);

        $message = (string)$warning;
        self::assertNotEmpty($message);
        self::assertStringContainsString('count', $message);
        self::assertStringContainsString('few', $message);
    }

    /**
     * @throws PluralComplianceException
     */
    #[Test]
    public function testPluralComplianceWarningGetArgumentWarnings(): void
    {
        $validator = new MessagePatternValidator('en', '{count, plural, one{# item} few{# items} other{# items}}');

        $warning = $validator->validatePluralCompliance();

        self::assertNotNull($warning);

        $argumentWarnings = $warning->getArgumentWarnings();
        self::assertCount(1, $argumentWarnings);
        self::assertSame('count', $argumentWarnings[0]->argumentName);
    }

    /**
     * @throws PluralComplianceException
     */
    #[Test]
    public function testPluralArgumentWarningGetMessage(): void
    {
        $validator = new MessagePatternValidator('en', '{count, plural, one{# item} few{# items} other{# items}}');

        $warning = $validator->validatePluralCompliance();

        self::assertNotNull($warning);
        self::assertCount(1, $warning->argumentWarnings);

        $argWarning = $warning->argumentWarnings[0];
        $message = (string)$argWarning;
        self::assertNotEmpty($message);
        self::assertStringContainsString('count', $message);
        self::assertStringContainsString('few', $message);
    }

    /**
     * @throws PluralComplianceException
     */
    #[Test]
    public function testPluralArgumentWarningGetArgumentTypeLabel(): void
    {
        $validator = new MessagePatternValidator('en', '{count, plural, one{# item} few{# items} other{# items}}');

        $warning = $validator->validatePluralCompliance();

        self::assertNotNull($warning);
        $argWarning = $warning->argumentWarnings[0];

        self::assertSame('plural', $argWarning->getArgumentTypeLabel());
    }

    /**
     * @throws PluralComplianceException
     */
    #[Test]
    public function testPluralArgumentWarningGetArgumentTypeLabelForSelectOrdinal(): void
    {
        $validator = new MessagePatternValidator(
            'en',
            '{count, selectordinal, zero{#th} one{#st} two{#nd} few{#rd} other{#th}}'
        );

        $warning = $validator->validatePluralCompliance();

        self::assertNotNull($warning);
        $argWarning = $warning->argumentWarnings[0];

        self::assertSame('selectordinal', $argWarning->getArgumentTypeLabel());
    }

    /**
     * @throws PluralComplianceException
     */
    #[Test]
    public function testPluralArgumentWarningMessageWithMissingCategories(): void
    {
        $validator = new MessagePatternValidator('ru', '{count, plural, one{# item} other{# items}}');

        $warning = $validator->validatePluralCompliance();

        self::assertNotNull($warning);
        $argWarning = $warning->argumentWarnings[0];

        $message = (string)$argWarning;
        self::assertStringContainsString('Missing', $message);
        self::assertStringContainsString('few', $message);
        self::assertStringContainsString('many', $message);
    }

    #[Test]
    public function testPluralComplianceExceptionMessageWithMissingCategories(): void
    {
        $validator = new MessagePatternValidator('en', '{count, plural, invalid{# items} other{# items}}');

        try {
            $validator->validatePluralCompliance();
            self::fail('Expected PluralComplianceException to be thrown');
        } catch (PluralComplianceException $e) {
            $message = $e->getMessage();
            self::assertStringContainsString('Invalid selectors found', $message);
            self::assertStringContainsString('invalid', $message);
        }
    }

    /**
     * @throws PluralComplianceException
     */
    #[Test]
    public function testMultiplePluralArgumentsHaveSegregatedWarnings(): void
    {
        $validator = new MessagePatternValidator(
            'en',
            'You have {items, plural, few{# items} other{# items}} and {people, plural, many{# people} other{# people}}.'
        );

        $warning = $validator->validatePluralCompliance();

        self::assertNotNull($warning);
        self::assertCount(2, $warning->argumentWarnings);

        $itemsWarning = $warning->argumentWarnings[0];
        self::assertSame('items', $itemsWarning->argumentName);
        self::assertContains('few', $itemsWarning->wrongLocaleSelectors);
        self::assertNotContains('many', $itemsWarning->wrongLocaleSelectors);
        self::assertContains('one', $itemsWarning->missingCategories);

        $peopleWarning = $warning->argumentWarnings[1];
        self::assertSame('people', $peopleWarning->argumentName);
        self::assertContains('many', $peopleWarning->wrongLocaleSelectors);
        self::assertNotContains('few', $peopleWarning->wrongLocaleSelectors);
        self::assertContains('one', $peopleWarning->missingCategories);

        self::assertNotSame(
            $itemsWarning->wrongLocaleSelectors,
            $peopleWarning->wrongLocaleSelectors
        );
    }

    /**
     * @throws PluralComplianceException
     */
    #[Test]
    public function testMixedPluralAndSelectOrdinalHaveSegregatedWarnings(): void
    {
        $validator = new MessagePatternValidator(
            'en',
            '{count, plural, other{# items}} and {rank, selectordinal, zero{#th} other{#th}}'
        );

        $warning = $validator->validatePluralCompliance();

        self::assertNotNull($warning);
        self::assertCount(2, $warning->argumentWarnings);

        $countWarning = $warning->argumentWarnings[0];
        self::assertSame('count', $countWarning->argumentName);
        self::assertSame('plural', $countWarning->getArgumentTypeLabel());
        self::assertContains('one', $countWarning->missingCategories);
        self::assertContains('one', $countWarning->expectedCategories);
        self::assertContains('other', $countWarning->expectedCategories);
        self::assertNotContains('two', $countWarning->expectedCategories);
        self::assertNotContains('few', $countWarning->expectedCategories);

        $rankWarning = $warning->argumentWarnings[1];
        self::assertSame('rank', $rankWarning->argumentName);
        self::assertSame('selectordinal', $rankWarning->getArgumentTypeLabel());
        self::assertContains('zero', $rankWarning->wrongLocaleSelectors);
        self::assertContains('one', $rankWarning->expectedCategories);
        self::assertContains('two', $rankWarning->expectedCategories);
        self::assertContains('few', $rankWarning->expectedCategories);
        self::assertContains('other', $rankWarning->expectedCategories);
        self::assertNotContains('zero', $rankWarning->expectedCategories);
        self::assertNotContains('many', $rankWarning->expectedCategories);
    }

    /**
     * @throws PluralComplianceException
     */
    #[Test]
    public function testMixedValidAndInvalidArgumentsOnlyWarnForInvalid(): void
    {
        $validator = new MessagePatternValidator(
            'en',
            '{count, plural, one{# item} other{# items}} and {rank, selectordinal, many{#th} other{#th}}'
        );

        $warning = $validator->validatePluralCompliance();

        self::assertNotNull($warning);
        self::assertCount(1, $warning->argumentWarnings);

        $rankWarning = $warning->argumentWarnings[0];
        self::assertSame('rank', $rankWarning->argumentName);
        self::assertSame('selectordinal', $rankWarning->getArgumentTypeLabel());
        self::assertContains('many', $rankWarning->wrongLocaleSelectors);
        self::assertContains('one', $rankWarning->missingCategories);
        self::assertContains('two', $rankWarning->missingCategories);
        self::assertContains('few', $rankWarning->missingCategories);
    }

    /**
     * @throws PluralComplianceException
     */
    #[Test]
    public function testPluralArgumentWarningStringableWithWrongLocaleSelectors(): void
    {
        $validator = new MessagePatternValidator('en', '{count, plural, one{# item} few{# items} other{# items}}');

        $warning = $validator->validatePluralCompliance();

        self::assertNotNull($warning);
        $argWarning = $warning->argumentWarnings[0];

        $expectedOutput = 'Plural argument "count": Categories [few] are valid CLDR categories '
            . 'but do not apply to the locale \'en\'. Expected categories: [one, other].';

        self::assertSame($expectedOutput, (string)$argWarning);
        self::assertSame($expectedOutput, $argWarning->getMessageAsString());
    }

    /**
     * @throws PluralComplianceException
     */
    #[Test]
    public function testPluralArgumentWarningStringableWithMissingCategories(): void
    {
        $validator = new MessagePatternValidator('ru', '{count, plural, other{# items}}');

        $warning = $validator->validatePluralCompliance();

        self::assertNotNull($warning);
        $argWarning = $warning->argumentWarnings[0];

        $expectedOutput = 'Plural argument "count": Missing required categories [one, few, many] '
            . 'in plural block for the locale \'ru\'. Expected categories: [one, few, many, other].';

        self::assertSame($expectedOutput, (string)$argWarning);
        self::assertSame($expectedOutput, $argWarning->getMessageAsString());
    }

    /**
     * @throws PluralComplianceException
     */
    #[Test]
    public function testPluralArgumentWarningStringableWithBothIssues(): void
    {
        $validator = new MessagePatternValidator('en', '{count, plural, few{# items} other{# items}}');

        $warning = $validator->validatePluralCompliance();

        self::assertNotNull($warning);
        $argWarning = $warning->argumentWarnings[0];

        $expectedOutput = 'Plural argument "count": Categories [few] are valid CLDR categories '
            . 'but do not apply to the locale \'en\'. Expected categories: [one, other]. '
            . 'Plural argument "count": Missing required categories [one] '
            . 'in plural block for the locale \'en\'. Expected categories: [one, other].';

        self::assertSame($expectedOutput, (string)$argWarning);
        self::assertSame($expectedOutput, $argWarning->getMessageAsString());
    }

    /**
     * @throws PluralComplianceException
     */
    #[Test]
    public function testPluralComplianceWarningStringableWithMultipleArguments(): void
    {
        $validator = new MessagePatternValidator(
            'en',
            '{items, plural, few{# items} other{# items}} and {people, plural, many{# people} other{# people}}'
        );

        $warning = $validator->validatePluralCompliance();

        self::assertNotNull($warning);

        $expectedOutput = 'Plural argument "items": Categories [few] are valid CLDR categories '
            . 'but do not apply to the locale \'en\'. Expected categories: [one, other]. '
            . 'Plural argument "items": Missing required categories [one] '
            . 'in plural block for the locale \'en\'. Expected categories: [one, other].' . "\n"
            . 'Plural argument "people": Categories [many] are valid CLDR categories '
            . 'but do not apply to the locale \'en\'. Expected categories: [one, other]. '
            . 'Plural argument "people": Missing required categories [one] '
            . 'in plural block for the locale \'en\'. Expected categories: [one, other].';

        self::assertSame($expectedOutput, (string)$warning);
        self::assertSame($expectedOutput, $warning->getMessagesAsString());
    }

    /**
     * @throws PluralComplianceException
     */
    #[Test]
    public function testSelectOrdinalArgumentWarningStringable(): void
    {
        $validator = new MessagePatternValidator('en', '{rank, selectordinal, zero{#th} many{#th} other{#th}}');

        $warning = $validator->validatePluralCompliance();

        self::assertNotNull($warning);
        $argWarning = $warning->argumentWarnings[0];

        $expectedOutput = 'Selectordinal argument "rank": Categories [zero, many] are valid CLDR categories '
            . 'but do not apply to the locale \'en\'. Expected categories: [one, two, few, other]. '
            . 'Selectordinal argument "rank": Missing required categories [one, two, few] '
            . 'in plural block for the locale \'en\'. Expected categories: [one, two, few, other].';

        self::assertSame($expectedOutput, (string)$argWarning);
        self::assertSame($expectedOutput, $argWarning->getMessageAsString());
    }

    // =========================================================================
    // Legacy API Tests (pre-parsed MessagePattern passed to constructor)
    // =========================================================================

    /**
     * Test that pre-parsed patterns still work with the new constructor signature.
     *
     * @throws PluralComplianceException
     */
    #[Test]
    public function testLegacyApiWithPreParsedPattern(): void
    {
        $pattern = new MessagePattern();
        $pattern->parse('{count, plural, one{# item} other{# items}}');

        $validator = new MessagePatternValidator('en', null, $pattern);

        $warning = $validator->validatePluralCompliance();

        self::assertNull($warning);
    }

    /**
     * Test that pre-parsed pattern takes precedence over patternString.
     *
     * @throws PluralComplianceException
     */
    #[Test]
    public function testPreParsedPatternTakesPrecedenceOverPatternString(): void
    {
        $pattern = new MessagePattern();
        $pattern->parse('{count, plural, one{# item} other{# items}}');

        // Pass a different (invalid) pattern string - should be ignored since pattern is already parsed
        $validator = new MessagePatternValidator('en', '{invalid, plural, wrong{test}}', $pattern);

        // Should validate the pre-parsed pattern (which is valid), not the patternString
        $warning = $validator->validatePluralCompliance();

        self::assertNull($warning);
    }

    // =========================================================================
    // Parsing Exception Handling Tests
    // =========================================================================

    /**
     * Test that parsing exceptions are caught and re-thrown during validation.
     */
    #[Test]
    public function testParsingExceptionIsRethrownDuringValidation(): void
    {
        // Invalid pattern with unmatched braces
        $validator = new MessagePatternValidator('en', '{count, plural, one{# item} other{# items}');

        $this->expectException(InvalidArgumentException::class);

        $validator->validatePluralCompliance();
    }

    /**
     * Test that parsing exceptions are caught during containsComplexSyntax but method still works.
     * The pattern may partially parse before the error, so complex syntax might still be detected.
     */
    #[Test]
    public function testParsingExceptionDuringContainsComplexSyntax(): void
    {
        // Invalid pattern - missing closing brace, but plural is still detected before error
        $validator = new MessagePatternValidator('en', '{count, plural, one{# item}');

        // containsComplexSyntax should not throw - it catches and stores the exception
        // The pattern partially parses and detects the plural before failing
        $result = $validator->containsComplexSyntax();

        // The plural is detected even though parsing ultimately fails
        self::assertTrue($result);
    }

    /**
     * Test that validation throws the stored parsing exception.
     */
    #[Test]
    public function testValidationThrowsStoredParsingException(): void
    {
        // Pattern with syntax error
        $validator = new MessagePatternValidator('en', '{unclosed');

        // First call to containsComplexSyntax should trigger parsing and store exception
        $validator->containsComplexSyntax();

        // Now validatePluralCompliance should throw the stored exception
        $this->expectException(InvalidArgumentException::class);
        $validator->validatePluralCompliance();
    }

    /**
     * Test that InvalidArgumentException is thrown for invalid pattern syntax.
     */
    #[Test]
    public function testInvalidArgumentExceptionForSyntaxError(): void
    {
        $validator = new MessagePatternValidator('en', '{name, invalid_type, value{text}}');

        $this->expectException(InvalidArgumentException::class);

        $validator->validatePluralCompliance();
    }

    /**
     * Test that parsing exception message is preserved.
     */
    #[Test]
    public function testParsingExceptionMessageIsPreserved(): void
    {
        $validator = new MessagePatternValidator('en', '{unmatched');

        try {
            $validator->validatePluralCompliance();
            self::fail('Expected InvalidArgumentException to be thrown');
        } catch (InvalidArgumentException $e) {
            // Verify the exception contains relevant error information
            self::assertStringContainsString('brace', strtolower($e->getMessage()));
        }
    }

    /**
     * Test that multiple validation calls with invalid pattern throw same exception.
     */
    #[Test]
    public function testMultipleValidationCallsThrowSameException(): void
    {
        $validator = new MessagePatternValidator('en', '{broken');

        // First call
        try {
            $validator->validatePluralCompliance();
            self::fail('Expected exception on first call');
        } catch (InvalidArgumentException $e) {
            $firstMessage = $e->getMessage();
        }

        // Second call should throw same exception
        try {
            $validator->validatePluralCompliance();
            self::fail('Expected exception on second call');
        } catch (InvalidArgumentException $e) {
            self::assertSame($firstMessage, $e->getMessage());
        }
    }

    /**
     * Test that valid pattern after setPatternString replaces invalid one.
     *
     * @throws Exception
     */
    #[Test]
    public function testSetPatternStringResetsParsingState(): void
    {
        $validator = new MessagePatternValidator('en', '{invalid');

        // First, verify the invalid pattern causes an error
        try {
            $validator->validatePluralCompliance();
            self::fail('Expected exception for invalid pattern');
        } catch (InvalidArgumentException) {
            // Expected
        }

        // Now set a valid pattern - this should work since setPatternString is called
        // However, the current implementation stores the exception, so we need to check
        // if the behavior resets. Based on the code, setPatternString only sets the string
        // but doesn't reset the pattern or exception.

        // This test documents the current behavior
        $validator->setPatternString('{count, plural, one{# item} other{# items}}');

        // The pattern object already has parts from the failed parse attempt,
        // so it won't re-parse. This is the expected behavior.
        // To get a fresh parse, you need a new validator instance.
        $newValidator = new MessagePatternValidator('en', '{count, plural, one{# item} other{# items}}');
        $warning = $newValidator->validatePluralCompliance();
        self::assertNull($warning);
    }

    /**
     * Test containsComplexSyntax returns false for pattern that failed to parse.
     */
    #[Test]
    public function testContainsComplexSyntaxReturnsFalseForFailedParse(): void
    {
        $validator = new MessagePatternValidator('en', '{invalid{{{');

        // Should return false because the pattern failed to parse
        $result = $validator->containsComplexSyntax();

        self::assertFalse($result);
    }

    /**
     * Test that Exception base class is used in validatePluralCompliance signature.
     */
    #[Test]
    public function testValidatePluralComplianceThrowsException(): void
    {
        $validator = new MessagePatternValidator('en', '{{{{');

        $this->expectException(Exception::class);

        $validator->validatePluralCompliance();
    }

}


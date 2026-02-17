<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 17/02/26
 * Time: 12:30
 *
 */

namespace Matecat\Tests\ICU;

use Matecat\ICU\Exceptions\MissingComplexFormException;
use Matecat\ICU\Exceptions\OutOfBoundsException;
use Matecat\ICU\MessagePattern;
use Matecat\ICU\MessagePatternComparator;
use Matecat\ICU\MessagePatternValidator;
use Matecat\ICU\Tokens\ArgType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tests the functionality of the `MessagePatternComparator` class.
 *
 * This test suite validates the comparison of source and target ICU message patterns
 * for complex form compatibility.
 */
class MessagePatternComparatorTest extends TestCase
{
    // =========================================================================
    // Factory Method Tests
    // =========================================================================

    /**
     * Test fromValidators() static factory method.
     * @throws MissingComplexFormException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testFromValidatorsFactoryMethod(): void
    {
        $this->expectNotToPerformAssertions();

        $sourceValidator = new MessagePatternValidator('en', '{count, plural, one{# item} other{# items}}');
        $targetValidator = new MessagePatternValidator('fr', '{count, plural, one{# article} many{# articles} other{# articles}}');

        $comparator = MessagePatternComparator::fromValidators($sourceValidator, $targetValidator);

        // Should not throw - both have plural for 'count'
        $comparator->validate();
    }

    /**
     * Test fromValidators() with missing complex form throws exception.
     * @throws MissingComplexFormException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testFromValidatorsWithMissingComplexFormThrowsException(): void
    {
        $sourceValidator = new MessagePatternValidator('en', '{count, plural, one{# item} other{# items}}');
        $targetValidator = new MessagePatternValidator('fr', 'Les articles {count}');

        $comparator = MessagePatternComparator::fromValidators($sourceValidator, $targetValidator);

        self::expectException(MissingComplexFormException::class);
        $comparator->validate();
    }

    /**
     * Test fromPatterns() static factory method.
     * @throws MissingComplexFormException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testFromPatternsFactoryMethod(): void
    {
        $this->expectNotToPerformAssertions();

        $sourcePattern = new MessagePattern('{count, plural, one{# item} other{# items}}');
        $targetPattern = new MessagePattern('{count, plural, one{# article} many{# articles} other{# articles}}');

        $comparator = MessagePatternComparator::fromPatterns('en', 'fr', $sourcePattern, $targetPattern);

        // Should not throw - both have plural for 'count'
        $comparator->validate();
    }

    /**
     * Test fromPatterns() with missing complex form throws exception.
     * @throws MissingComplexFormException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testFromPatternsWithMissingComplexFormThrowsException(): void
    {
        $sourcePattern = new MessagePattern('{count, plural, one{# item} other{# items}}');
        $targetPattern = new MessagePattern('Les articles {count}');

        $comparator = MessagePatternComparator::fromPatterns('en', 'fr', $sourcePattern, $targetPattern);

        self::expectException(MissingComplexFormException::class);
        $comparator->validate();
    }

    /**
     * Test fromPatterns() allows reusing parsed patterns for multiple locale comparisons.
     * @throws MissingComplexFormException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testFromPatternsReusesParsedPatterns(): void
    {
        $this->expectNotToPerformAssertions();

        // Parse patterns once
        $sourcePattern = new MessagePattern('{count, plural, one{# item} other{# items}}');
        $targetPattern = new MessagePattern('{count, plural, one{# article} many{# articles} other{# articles}}');

        // Reuse for multiple locale pairs
        $comparator1 = MessagePatternComparator::fromPatterns('en', 'fr', $sourcePattern, $targetPattern);
        $comparator2 = MessagePatternComparator::fromPatterns('en-US', 'fr-FR', $sourcePattern, $targetPattern);

        // Both should validate without throwing
        $comparator1->validate();
        $comparator2->validate();
    }

    /**
     * Test fromValidators() preserves validator state.
     * @throws MissingComplexFormException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testFromValidatorsPreservesValidatorState(): void
    {
        $sourceValidator = new MessagePatternValidator('en', '{count, plural, one{# item} other{# items}}');
        $targetValidator = new MessagePatternValidator('fr', '{count, plural, one{# article} other{# articles}}');

        // Pre-validate to initialize pattern
        self::assertTrue($sourceValidator->containsComplexSyntax());
        self::assertTrue($targetValidator->containsComplexSyntax());

        $comparator = MessagePatternComparator::fromValidators($sourceValidator, $targetValidator);

        // Should work with pre-initialized validators - no exception means success
        $comparator->validate();
    }

    // =========================================================================
    // Basic Validation Tests
    // =========================================================================

    /**
     * Test comparing valid simple patterns without complex forms.
     * @throws MissingComplexFormException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testValidSimplePatterns(): void
    {
        $this->expectNotToPerformAssertions();

        $comparator = new MessagePatternComparator(
            'en',
            'fr',
            'Hello {name}.',
            'Bonjour {name}.'
        );

        // Should not throw - no complex forms to validate
        $comparator->validate();
    }

    /**
     * Test comparing valid plural patterns.
     * @throws MissingComplexFormException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testValidPluralPatterns(): void
    {
        $this->expectNotToPerformAssertions();

        $comparator = new MessagePatternComparator(
            'en',
            'fr',
            '{count, plural, one{# item} other{# items}}',
            '{count, plural, one{# article} many{# articles} other{# articles}}'
        );

        // Should not throw - both have plural for 'count'
        $comparator->validate();
    }

    /**
     * Test comparing valid select patterns.
     * @throws MissingComplexFormException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testValidSelectPatterns(): void
    {
        $this->expectNotToPerformAssertions();

        $comparator = new MessagePatternComparator(
            'en',
            'de',
            '{gender, select, male{He} female{She} other{They}}',
            '{gender, select, male{Er} female{Sie} other{Sie}}'
        );

        // Should not throw - both have select for 'gender'
        $comparator->validate();
    }

    // =========================================================================
    // Complex Form Compatibility Tests
    // =========================================================================

    /**
     * Test that missing plural form in target throws MissingComplexFormException.
     * @throws MissingComplexFormException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testMissingPluralFormInTargetThrowsException(): void
    {
        $comparator = new MessagePatternComparator(
            'en',
            'fr',
            '{count, plural, one{# item} other{# items}}',
            'Les articles {count}' // Missing plural form
        );

        self::expectException(MissingComplexFormException::class);
        self::expectExceptionMessageMatches("/Argument 'count' has complex form 'PLURAL' in source.*but is missing in target/");

        $comparator->validate();
    }

    /**
     * Test that missing select form in target throws MissingComplexFormException.
     * @throws MissingComplexFormException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testMissingSelectFormInTargetThrowsException(): void
    {
        $comparator = new MessagePatternComparator(
            'en',
            'fr',
            '{gender, select, male{He} female{She} other{They}}',
            'Utilisateur {gender}' // Missing select form
        );

        self::expectException(MissingComplexFormException::class);
        self::expectExceptionMessageMatches("/Argument 'gender' has complex form 'SELECT' in source.*but is missing in target/");

        $comparator->validate();
    }

    /**
     * Test that mismatched complex form types throw MissingComplexFormException.
     * @throws MissingComplexFormException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testMismatchedComplexFormTypesThrowsException(): void
    {
        $comparator = new MessagePatternComparator(
            'en',
            'fr',
            '{count, plural, one{# item} other{# items}}',
            '{count, select, one{un article} other{des articles}}' // PLURAL vs SELECT
        );

        self::expectException(MissingComplexFormException::class);
        self::expectExceptionMessageMatches("/Argument 'count' has complex form 'PLURAL' in source.*but has 'SELECT' in target/");

        $comparator->validate();
    }

    /**
     * Test that PLURAL and SELECTORDINAL are not interchangeable.
     * @throws MissingComplexFormException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testPluralAndSelectOrdinalNotInterchangeable(): void
    {
        $comparator = new MessagePatternComparator(
            'en',
            'fr',
            '{count, plural, one{# item} other{# items}}',
            '{count, selectordinal, one{#er} two{#e} few{#e} other{#e}}'
        );

        self::expectException(MissingComplexFormException::class);
        self::expectExceptionMessageMatches("/Argument 'count' has complex form 'PLURAL' in source.*but has 'SELECTORDINAL' in target/");

        $comparator->validate();
    }

    /**
     * Test that matching SELECTORDINAL forms pass validation.
     * @throws MissingComplexFormException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testMatchingSelectOrdinalFormsPass(): void
    {
        $this->expectNotToPerformAssertions();

        $comparator = new MessagePatternComparator(
            'en',
            'fr',
            '{count, selectordinal, one{#st} two{#nd} few{#rd} other{#th}}',
            '{count, selectordinal, one{#er} two{#e} few{#e} other{#e}}'
        );

        // Should not throw
        $comparator->validate();
    }

    // =========================================================================
    // Nested Complex Form Tests
    // =========================================================================

    /**
     * Test nested complex forms are validated.
     * @throws MissingComplexFormException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testNestedComplexFormsValidation(): void
    {
        $this->expectNotToPerformAssertions();

        $sourcePattern = '{gender, select, ' .
            'male{{count, plural, one{He has # item} other{He has # items}}} ' .
            'female{{count, plural, one{She has # item} other{She has # items}}} ' .
            'other{{count, plural, one{They have # item} other{They have # items}}}}';

        $targetPattern = '{gender, select, ' .
            'male{{count, plural, one{Il a # article} many{Il a # articles} other{Il a # articles}}} ' .
            'female{{count, plural, one{Elle a # article} many{Elle a # articles} other{Elle a # articles}}} ' .
            'other{{count, plural, one{Ils ont # article} many{Ils ont # articles} other{Ils ont # articles}}}}';

        $comparator = new MessagePatternComparator('en', 'fr', $sourcePattern, $targetPattern);

        // Should not throw
        $comparator->validate();
    }

    /**
     * Test missing nested complex form throws exception.
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testMissingNestedComplexFormThrowsException(): void
    {
        $sourcePattern = '{gender, select, ' .
            'male{{count, plural, one{He has # item} other{He has # items}}} ' .
            'other{They have items}}';

        $targetPattern = '{gender, select, ' .
            'male{Il a des articles} ' . // Missing nested plural
            'other{Ils ont des articles}}';

        $comparator = new MessagePatternComparator('en', 'fr', $sourcePattern, $targetPattern);

        self::expectException(MissingComplexFormException::class);

        $comparator->validate();
    }

    // =========================================================================
    // Helper Methods Tests
    // =========================================================================

    /**
     * Test sourceContainsComplexSyntax returns true for complex patterns.
     */
    #[Test]
    public function testSourceContainsComplexSyntax(): void
    {
        $comparator = new MessagePatternComparator(
            'en',
            'fr',
            '{count, plural, one{# item} other{# items}}',
            '{count, plural, one{# article} other{# articles}}'
        );

        self::assertTrue($comparator->sourceContainsComplexSyntax());
    }

    /**
     * Test sourceContainsComplexSyntax returns false for simple patterns.
     */
    #[Test]
    public function testSourceContainsComplexSyntaxFalseForSimple(): void
    {
        $comparator = new MessagePatternComparator(
            'en',
            'fr',
            'Hello {name}.',
            'Bonjour {name}.'
        );

        self::assertFalse($comparator->sourceContainsComplexSyntax());
    }

    /**
     * Test targetContainsComplexSyntax returns true for complex patterns.
     */
    #[Test]
    public function testTargetContainsComplexSyntax(): void
    {
        $comparator = new MessagePatternComparator(
            'en',
            'fr',
            'Hello {name}.',
            '{count, plural, one{# article} other{# articles}}'
        );

        self::assertFalse($comparator->sourceContainsComplexSyntax());
        self::assertTrue($comparator->targetContainsComplexSyntax());
    }

    /**
     * Test getSourceLocale returns correct locale.
     */
    #[Test]
    public function testGetSourceLocale(): void
    {
        $comparator = new MessagePatternComparator(
            'en-US',
            'fr-FR',
            'Hello {name}.',
            'Bonjour {name}.'
        );

        self::assertSame('en-US', $comparator->getSourceLocale());
    }

    /**
     * Test getTargetLocale returns correct locale.
     */
    #[Test]
    public function testGetTargetLocale(): void
    {
        $comparator = new MessagePatternComparator(
            'en-US',
            'fr-FR',
            'Hello {name}.',
            'Bonjour {name}.'
        );

        self::assertSame('fr-FR', $comparator->getTargetLocale());
    }

    /**
     * Test getSourceValidator returns the source validator.
     */
    #[Test]
    public function testGetSourceValidator(): void
    {
        $comparator = new MessagePatternComparator(
            'en-US',
            'fr-FR',
            '{count, plural, one{# item} other{# items}}',
            '{count, plural, one{# article} other{# articles}}'
        );

        $sourceValidator = $comparator->getSourceValidator();

        self::assertTrue($sourceValidator->containsComplexSyntax());
    }

    /**
     * Test getTargetValidator returns the target validator.
     */
    #[Test]
    public function testGetTargetValidator(): void
    {
        $comparator = new MessagePatternComparator(
            'en-US',
            'fr-FR',
            '{count, plural, one{# item} other{# items}}',
            '{count, plural, one{# article} other{# articles}}'
        );

        $targetValidator = $comparator->getTargetValidator();

        self::assertTrue($targetValidator->containsComplexSyntax());
    }

    /**
     * Test getSourceValidator and getTargetValidator return injected validators from fromValidators().
     */
    #[Test]
    public function testGetValidatorsFromFactoryMethod(): void
    {
        $sourceValidator = new MessagePatternValidator('en', '{count, plural, one{# item} other{# items}}');
        $targetValidator = new MessagePatternValidator('fr', '{count, plural, one{# article} other{# articles}}');

        $comparator = MessagePatternComparator::fromValidators($sourceValidator, $targetValidator);

        self::assertSame($sourceValidator, $comparator->getSourceValidator());
        self::assertSame($targetValidator, $comparator->getTargetValidator());
    }

    // =========================================================================
    // MissingComplexFormException Tests
    // =========================================================================

    /**
     * Test MissingComplexFormException properties for missing argument.
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testMissingComplexFormExceptionPropertiesMissingArg(): void
    {
        $comparator = new MessagePatternComparator(
            'en-US',
            'fr-FR',
            '{count, plural, one{# item} other{# items}}',
            'Les articles'
        );

        try {
            $comparator->validate();
            self::fail('Expected MissingComplexFormException was not thrown');
        } catch (MissingComplexFormException $e) {
            self::assertSame('count', $e->argumentName);
            self::assertSame(ArgType::PLURAL, $e->sourceArgType);
            self::assertNull($e->targetArgType);
            self::assertSame('en-US', $e->sourceLocale);
            self::assertSame('fr-FR', $e->targetLocale);
            self::assertStringContainsString('count', $e->getMessage());
            self::assertStringContainsString('PLURAL', $e->getMessage());
            self::assertStringContainsString('missing in target', $e->getMessage());
        }
    }

    /**
     * Test MissingComplexFormException properties for a mismatched type.
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testMissingComplexFormExceptionPropertiesMismatchedType(): void
    {
        $comparator = new MessagePatternComparator(
            'en-US',
            'fr-FR',
            '{type, select, a{A} b{B} other{C}}',
            '{type, plural, one{un} other{plusieurs}}'
        );

        try {
            $comparator->validate();
            self::fail('Expected MissingComplexFormException was not thrown');
        } catch (MissingComplexFormException $e) {
            self::assertSame('type', $e->argumentName);
            self::assertSame(ArgType::SELECT, $e->sourceArgType);
            self::assertSame(ArgType::PLURAL, $e->targetArgType);
            self::assertStringContainsString('SELECT', $e->getMessage());
            self::assertStringContainsString('PLURAL', $e->getMessage());
        }
    }

    // =========================================================================
    // Edge Cases
    // =========================================================================

    /**
     * Test empty patterns are valid.
     * @throws MissingComplexFormException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testEmptyPatternsAreValid(): void
    {
        $this->expectNotToPerformAssertions();

        $comparator = new MessagePatternComparator(
            'en',
            'fr',
            '',
            ''
        );

        // Should not throw
        $comparator->validate();
    }

    /**
     * Test patterns with only literals are valid.
     * @throws MissingComplexFormException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testLiteralOnlyPatternsAreValid(): void
    {
        $this->expectNotToPerformAssertions();

        $comparator = new MessagePatternComparator(
            'en',
            'fr',
            'Hello World!',
            'Bonjour le monde!'
        );

        // Should not throw
        $comparator->validate();
    }

    /**
     * Test source without complex syntax skips complex form validation.
     * @throws MissingComplexFormException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testSourceWithoutComplexSyntaxSkipsComplexValidation(): void
    {
        $this->expectNotToPerformAssertions();

        // Source has no complex syntax, so target can have complex syntax without issues
        $comparator = new MessagePatternComparator(
            'en',
            'fr',
            'Hello {name}.',
            '{name, select, male{Bonjour Monsieur} female{Bonjour Madame} other{Bonjour}}'
        );

        // Should not throw
        $comparator->validate();
    }

    /**
     * Test multiple arguments with complex forms.
     * @throws MissingComplexFormException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testMultipleArgumentsWithComplexForms(): void
    {
        $this->expectNotToPerformAssertions();

        $comparator = new MessagePatternComparator(
            'en',
            'fr',
            '{gender, select, male{He} female{She} other{They}} bought {count, plural, one{# item} other{# items}}.',
            '{gender, select, male{Il} female{Elle} other{Ils}} a acheté {count, plural, one{# article} many{# articles} other{# articles}}.'
        );

        // Should not throw
        $comparator->validate();
    }

    /**
     * Test partial missing complex form throws exception.
     * @throws MissingComplexFormException
     * @throws OutOfBoundsException
     */
    #[Test]
    public function testPartialMissingComplexFormThrowsException(): void
    {
        // Source has two complex forms, target only has one
        $comparator = new MessagePatternComparator(
            'en',
            'fr',
            '{gender, select, male{He} female{She} other{They}} bought {count, plural, one{# item} other{# items}}.',
            '{gender, select, male{Il} female{Elle} other{Ils}} a acheté des articles {count}.'
        );

        self::expectException(MissingComplexFormException::class);
        self::expectExceptionMessageMatches("/Argument 'count' has complex form 'PLURAL'/");

        $comparator->validate();
    }

    // =========================================================================
    // Data Provider Tests
    // =========================================================================

    /**
     * @return array<string, array{string, string, string, string}>
     */
    public static function validPatternPairsProvider(): array
    {
        return [
            'simple placeholders' => [
                'en', 'fr',
                'Hello {name}!',
                'Bonjour {name}!',
            ],
            'plural en to fr' => [
                'en', 'fr',
                '{n, plural, one{# file} other{# files}}',
                '{n, plural, one{# fichier} many{# fichiers} other{# fichiers}}',
            ],
            'select' => [
                'en', 'de',
                '{gender, select, male{Mr.} female{Ms.} other{Mx.}}',
                '{gender, select, male{Herr} female{Frau} other{Person}}',
            ],
            'choice' => [
                'en', 'fr',
                '{count, choice, 0#no items|1#one item|1<# items}',
                '{count, choice, 0#aucun article|1#un article|1<# articles}',
            ],
        ];
    }

    /**
     * @param string $sourceLocale
     * @param string $targetLocale
     * @param string $sourcePattern
     * @param string $targetPattern
     * @return void
     * @throws MissingComplexFormException
     * @throws OutOfBoundsException
     */
    #[Test]
    #[DataProvider('validPatternPairsProvider')]
    public function testValidPatternPairs(
        string $sourceLocale,
        string $targetLocale,
        string $sourcePattern,
        string $targetPattern
    ): void {
        $this->expectNotToPerformAssertions();

        $comparator = new MessagePatternComparator(
            $sourceLocale,
            $targetLocale,
            $sourcePattern,
            $targetPattern
        );

        // Should not throw
        $comparator->validate();
    }
}


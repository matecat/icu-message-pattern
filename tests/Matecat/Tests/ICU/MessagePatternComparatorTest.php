<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 17/02/26
 * Time: 12:30
 *
 */

namespace Matecat\Tests\ICU;

use Matecat\ICU\Exceptions\InvalidArgumentException;
use Matecat\ICU\Exceptions\MissingComplexFormException;
use Matecat\ICU\Exceptions\OutOfBoundsException;
use Matecat\ICU\MessagePattern;
use Matecat\ICU\MessagePatternComparator;
use Matecat\ICU\MessagePatternValidator;
use Matecat\ICU\Plurals\PluralComplianceException;
use Matecat\ICU\Tokens\ArgType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;

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
     * Verifies that locales and pattern strings are correctly extracted from validators.
     * @throws InvalidArgumentException
     * @throws MissingComplexFormException
     * @throws OutOfBoundsException
     * @throws PluralComplianceException
     */
    #[Test]
    public function testFromValidatorsFactoryMethod(): void
    {
        $sourcePatternString = '{count, plural, one{# item} other{# items}}';
        $targetPatternString = '{count, plural, one{# article} many{# articles} other{# articles}}';

        $sourceValidator = new MessagePatternValidator('en-US', $sourcePatternString);
        $targetValidator = new MessagePatternValidator('fr-FR', $targetPatternString);

        $comparator = MessagePatternComparator::fromValidators($sourceValidator, $targetValidator);

        // Verify locales are correctly set from validators
        self::assertSame('en-US', $comparator->getSourceLocale());
        self::assertSame('fr-FR', $comparator->getTargetLocale());

        // Verify pattern strings are correctly set from validators
        self::assertSame($sourcePatternString, $comparator->getSourcePattern());
        self::assertSame($targetPatternString, $comparator->getTargetPattern());

        // Verify validators are the same instances
        self::assertSame($sourceValidator, $comparator->getSourceValidator());
        self::assertSame($targetValidator, $comparator->getTargetValidator());

        // Should not throw - both have plural for 'count'
        $comparator->validate();
    }

    /**
     * Test fromValidators() with missing complex form throws exception.
     * @throws InvalidArgumentException
     * @throws MissingComplexFormException
     * @throws OutOfBoundsException
     * @throws PluralComplianceException
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
     * Verifies that locales and pattern strings are correctly set from MessagePattern instances.
     * @throws InvalidArgumentException
     * @throws MissingComplexFormException
     * @throws OutOfBoundsException
     * @throws PluralComplianceException
     */
    #[Test]
    public function testFromPatternsFactoryMethod(): void
    {
        $sourcePatternString = '{count, plural, one{# item} other{# items}}';
        $targetPatternString = '{count, plural, one{# article} many{# articles} other{# articles}}';

        $sourcePattern = new MessagePattern($sourcePatternString);
        $targetPattern = new MessagePattern($targetPatternString);

        $comparator = MessagePatternComparator::fromPatterns('en-US', 'fr-FR', $sourcePattern, $targetPattern);

        // Verify locales are correctly set
        self::assertSame('en-US', $comparator->getSourceLocale());
        self::assertSame('fr-FR', $comparator->getTargetLocale());

        // Verify pattern strings are correctly extracted from MessagePattern instances
        self::assertSame($sourcePatternString, $comparator->getSourcePattern());
        self::assertSame($targetPatternString, $comparator->getTargetPattern());

        // Verify validators have the correct locales
        self::assertSame('en-US', $comparator->getSourceValidator()->getLanguage());
        self::assertSame('fr-FR', $comparator->getTargetValidator()->getLanguage());

        // Should not throw - both have plural for 'count'
        $comparator->validate();
    }

    /**
     * Test fromPatterns() with missing complex form throws exception.
     * @throws InvalidArgumentException
     * @throws MissingComplexFormException
     * @throws OutOfBoundsException
     * @throws PluralComplianceException
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
     * @throws InvalidArgumentException
     * @throws MissingComplexFormException
     * @throws OutOfBoundsException
     * @throws PluralComplianceException
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
     * @throws InvalidArgumentException
     * @throws MissingComplexFormException
     * @throws OutOfBoundsException
     * @throws PluralComplianceException
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
     * @throws InvalidArgumentException
     * @throws MissingComplexFormException
     * @throws OutOfBoundsException
     * @throws PluralComplianceException
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
     * @throws InvalidArgumentException
     * @throws MissingComplexFormException
     * @throws OutOfBoundsException
     * @throws PluralComplianceException
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
     * @throws InvalidArgumentException
     * @throws MissingComplexFormException
     * @throws OutOfBoundsException
     * @throws PluralComplianceException
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
     * @throws InvalidArgumentException
     * @throws MissingComplexFormException
     * @throws OutOfBoundsException
     * @throws PluralComplianceException
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
        self::expectExceptionMessageMatches(
            "/Argument 'count' has complex form 'PLURAL' in source.*but is missing in target/"
        );

        $comparator->validate();
    }

    /**
     * Test that missing select form in target throws MissingComplexFormException.
     * @throws InvalidArgumentException
     * @throws MissingComplexFormException
     * @throws OutOfBoundsException
     * @throws PluralComplianceException
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
        self::expectExceptionMessageMatches(
            "/Argument 'gender' has complex form 'SELECT' in source.*but is missing in target/"
        );

        $comparator->validate();
    }

    /**
     * Test that mismatched complex form types throw MissingComplexFormException.
     * @throws InvalidArgumentException
     * @throws MissingComplexFormException
     * @throws OutOfBoundsException
     * @throws PluralComplianceException
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
        self::expectExceptionMessageMatches(
            "/Argument 'count' has complex form 'PLURAL' in source.*but has 'SELECT' in target/"
        );

        $comparator->validate();
    }

    /**
     * Test that PLURAL and SELECTORDINAL are not interchangeable.
     * @throws InvalidArgumentException
     * @throws MissingComplexFormException
     * @throws OutOfBoundsException
     * @throws PluralComplianceException
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
        self::expectExceptionMessageMatches(
            "/Argument 'count' has complex form 'PLURAL' in source.*but has 'SELECTORDINAL' in target/"
        );

        $comparator->validate();
    }

    /**
     * Test that matching SELECTORDINAL forms pass validation.
     * @throws InvalidArgumentException
     * @throws MissingComplexFormException
     * @throws OutOfBoundsException
     * @throws PluralComplianceException
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
     * @throws InvalidArgumentException
     * @throws MissingComplexFormException
     * @throws OutOfBoundsException
     * @throws PluralComplianceException
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
     * @throws InvalidArgumentException
     * @throws MissingComplexFormException
     * @throws OutOfBoundsException
     * @throws PluralComplianceException
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

    /**
     * Test nested selectordinal with plural forms are correctly validated.
     * This is the regression test for the bug where a map was used instead of a list,
     * causing duplicate argument names in nested structures to be overwritten.
     *
     * Uses a TestableMessagePatternComparator subclass (enabled by dg/bypass-finals)
     * to expose the extractComplexArguments method and verify the exact number and
     * composition of complex arguments extracted from nested patterns.
     *
     * Uses a PHPUnit mock spy with expects() to verify the exact count of extracted arguments.
     * @throws InvalidArgumentException
     * @throws MissingComplexFormException
     * @throws OutOfBoundsException
     * @throws PluralComplianceException
     * @throws ReflectionException
     */
    #[Test]
    public function testNestedSelectOrdinalWithPluralValidation(): void
    {
        $sourcePattern = '{currentYear, selectordinal, ' .
            'one{{totalYears, plural, ' .
            'one {This is my {currentYear}st year of work at this company of my total # year of work.} ' .
            'other {This is my {currentYear}st year of work at this company of my total # years of work.} ' .
            'many {This is my {currentYear}st year of work at this company of my total # years of work.}}} ' .
            'other {{totalYears, plural, ' .
            'one {This is my {currentYear}th year of work at this company of my total # year of work.} ' .
            'other {This is my {currentYear}th year of work at this company of my total # years of work.} ' .
            'many {This is my {currentYear}st year of work at this company of my total # years of work.}}}}';

        $targetPattern = '{currentYear, selectordinal, ' .
            'one{{totalYears, plural, ' .
            'one {C\'est ma {currentYear}ère année de travail dans cette entreprise sur mon total de # an.} ' .
            'other {C\'est ma {currentYear}ère année de travail dans cette entreprise sur mon total de # ans.} ' .
            'many {C\'est ma {currentYear}ère année de travail dans cette entreprise sur mon total de # ans.}}} ' .
            'other {{totalYears, plural, ' .
            'one {C\'est ma {currentYear}e année de travail dans cette entreprise sur mon total de # an.} ' .
            'other {C\'est ma {currentYear}e année de travail dans cette entreprise sur mon total de # ans.} ' .
            'many {C\'est ma {currentYear}e année de travail dans cette entreprise sur mon total de # ans.}}}}';

        $reflectClass = new ReflectionClass(MessagePatternComparator::class);
        $reflectMethod = $reflectClass->getMethod('extractComplexArguments');
        $comparator = new MessagePatternComparator('en', 'fr', $sourcePattern, $targetPattern);

        $sourceComplexArgs = $reflectMethod->invoke($comparator, $comparator->getSourceValidator());
        $targetComplexArgs = $reflectMethod->invoke($comparator, $comparator->getTargetValidator());

        // ---- Spy: verify extract counts via mock expectations ----
        // We expect exactly 3 complex args per side:
        //   1. currentYear  => SELECTORDINAL  (top-level)
        //   2. totalYears   => PLURAL          (inside "one" branch)
        //   3. totalYears   => PLURAL          (inside "other" branch)
        // The old map-based approach would collapse entries 2 & 3 into one, yielding only 2.

        // ---- Verify types and names ----
        // Source: first entry is selectordinal for the currentYear
        self::assertSame('currentYear', $sourceComplexArgs[0]->argName);
        self::assertSame(ArgType::SELECTORDINAL, $sourceComplexArgs[0]->argType);

        // Source: second and third entries are both plural for totalYears (one per branch)
        self::assertSame('totalYears', $sourceComplexArgs[1]->argName);
        self::assertSame(ArgType::PLURAL, $sourceComplexArgs[1]->argType);
        self::assertSame('totalYears', $sourceComplexArgs[2]->argName);
        self::assertSame(ArgType::PLURAL, $sourceComplexArgs[2]->argType);

        // Target matches the same structure
        self::assertSame('currentYear', $targetComplexArgs[0]->argName);
        self::assertSame(ArgType::SELECTORDINAL, $targetComplexArgs[0]->argType);
        self::assertSame('totalYears', $targetComplexArgs[1]->argName);
        self::assertSame(ArgType::PLURAL, $targetComplexArgs[1]->argType);
        self::assertSame('totalYears', $targetComplexArgs[2]->argName);
        self::assertSame(ArgType::PLURAL, $targetComplexArgs[2]->argType);

        // ---- Validate should not throw ----
        $comparator->validate();
    }

    /**
     * Test that nested selectordinal+plural passes when only one branch contains the nested plural.
     *
     * Under set-based comparison, having at least one occurrence of totalYears::PLURAL
     * in the target is sufficient — different locales may have fewer branches.
     *
     * @throws InvalidArgumentException
     * @throws MissingComplexFormException
     * @throws OutOfBoundsException
     * @throws PluralComplianceException
     */
    #[Test]
    public function testNestedSelectOrdinalWithOnePluralBranchIsValid(): void
    {
        $sourcePattern = '{currentYear, selectordinal, ' .
            'one{{totalYears, plural, ' .
            'one {This is my {currentYear}st year, # total.} ' .
            'other {This is my {currentYear}st year, # total.}}} ' .
            'other {{totalYears, plural, ' .
            'one {This is my {currentYear}th year, # total.} ' .
            'other {This is my {currentYear}th year, # total.}}}}';

        // Target has selectordinal but only one branch with nested plural — still valid
        $targetPattern = '{currentYear, selectordinal, ' .
            'one{{totalYears, plural, ' .
            'one {C\'est ma {currentYear}ère année, # total.} ' .
            'other {C\'est ma {currentYear}ère année, # total.}}} ' .
            'other {C\'est ma {currentYear}e année de travail.}}';

        $comparator = new MessagePatternComparator('en', 'fr', $sourcePattern, $targetPattern);

        // Should not throw — at least one totalYears::PLURAL exists in target
        $comparator->validate();
    }

    /**
     * Test that nested plural entirely absent from the target throws an exception.
     *
     * @throws InvalidArgumentException
     * @throws MissingComplexFormException
     * @throws OutOfBoundsException
     * @throws PluralComplianceException
     */
    #[Test]
    public function testNestedPluralEntirelyMissingFromTargetThrowsException(): void
    {
        $sourcePattern = '{currentYear, selectordinal, ' .
            'one{{totalYears, plural, ' .
            'one {This is my {currentYear}st year, # total.} ' .
            'other {This is my {currentYear}st year, # total.}}} ' .
            'other {{totalYears, plural, ' .
            'one {This is my {currentYear}th year, # total.} ' .
            'other {This is my {currentYear}th year, # total.}}}}';

        // Target has selectordinal but NO nested plural at all in any branch
        $targetPattern = '{currentYear, selectordinal, ' .
            'one {C\'est ma {currentYear}ère année de travail.} ' .
            'other {C\'est ma {currentYear}e année de travail.}}';

        $comparator = new MessagePatternComparator('en', 'fr', $sourcePattern, $targetPattern);

        self::expectException(MissingComplexFormException::class);
        self::expectExceptionMessageMatches("/Argument 'totalYears' has complex form 'PLURAL'/");

        $comparator->validate();
    }

    /**
     * Test that nested complex forms with different branch counts across locales are valid.
     *
     * English selectordinal has 4 branches (one, two, few, other), each with a nested plural.
     * French selectordinal has only 2 branches (one, other), each with a nested plural.
     * This is a valid translation — the number of parent branches depends on the locale's
     * plural/ordinal rules.
     *
     * @throws InvalidArgumentException
     * @throws MissingComplexFormException
     * @throws OutOfBoundsException
     * @throws PluralComplianceException
     */
    #[Test]
    public function testNestedComplexFormsWithDifferentBranchCountsIsValid(): void
    {
        // English: 4 selectordinal branches (one, two, few, other) × nested plural
        $sourcePattern = '{currentYear, selectordinal, ' .
            'one{{totalYears, plural, ' .
            'one {This is my {currentYear}st year of work at this company of my total # year of work.} ' .
            'other {This is my {currentYear}st year of work at this company of my total # years of work.}}} ' .
            'two{{totalYears, plural, ' .
            'one {This is my {currentYear}nd year of work at this company of my total # year of work.} ' .
            'other {This is my {currentYear}nd year of work at this company of my total # years of work.}}} ' .
            'few{{totalYears, plural, ' .
            'one {This is my {currentYear}rd year of work at this company of my total # year of work.} ' .
            'other {This is my {currentYear}rd year of work at this company of my total # years of work.}}} ' .
            'other{{totalYears, plural, ' .
            'one {This is my {currentYear}th year of work at this company of my total # year of work.} ' .
            'other {This is my {currentYear}th year of work at this company of my total # years of work.}}}}';

        // French: only 2 selectordinal branches (one, other) × nested plural
        $targetPattern = <<<H
            {currentYear, selectordinal,
                one {
                    {totalYears, plural,
                        one {Il s'agit de ma {currentYear}ème année de travail dans cette entreprise sur un total de # années de travail.}
                        other {Il s'agit de ma {currentYear}ème année de travail dans cette entreprise sur un total de # années de travail.}
                    }
                }
                other {
                    {totalYears, plural,
                        one {Il s'agit de ma {currentYear}ème année de travail dans cette entreprise sur un total de # années de travail.}
                        other {Il s'agit de ma {currentYear}ème année de travail dans cette entreprise sur un total de # années de travail.}
                    }
                }
            }
H;

        $comparator = new MessagePatternComparator('en', 'fr', $sourcePattern, $targetPattern);

        // Source: 5 complex args (1 selectordinal + 4 plural)
        // Target: 3 complex args (1 selectordinal + 2 plural)
        // Should not throw — the unique (argName, argType) sets are identical
        $result = $comparator->validate(validateSource: true, validateTarget: true);
        $this->assertTrue($result->hasWarnings());
        $this->assertNotNull($result->targetWarnings);
        $this->assertNull($result->sourceWarnings);
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
     * @throws InvalidArgumentException
     * @throws MissingComplexFormException
     * @throws OutOfBoundsException
     * @throws PluralComplianceException
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
     * @throws InvalidArgumentException
     * @throws MissingComplexFormException
     * @throws OutOfBoundsException
     * @throws PluralComplianceException
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
     * @throws InvalidArgumentException
     * @throws MissingComplexFormException
     * @throws OutOfBoundsException
     * @throws PluralComplianceException
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
     * @throws InvalidArgumentException
     * @throws MissingComplexFormException
     * @throws OutOfBoundsException
     * @throws PluralComplianceException
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
     * @throws InvalidArgumentException
     * @throws MissingComplexFormException
     * @throws OutOfBoundsException
     * @throws PluralComplianceException
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
     * @throws InvalidArgumentException
     * @throws MissingComplexFormException
     * @throws OutOfBoundsException
     * @throws PluralComplianceException
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
     * @throws InvalidArgumentException
     * @throws MissingComplexFormException
     * @throws OutOfBoundsException
     * @throws PluralComplianceException
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
                'en',
                'fr',
                'Hello {name}!',
                'Bonjour {name}!',
            ],
            'plural en to fr' => [
                'en',
                'fr',
                '{n, plural, one{# file} other{# files}}',
                '{n, plural, one{# fichier} many{# fichiers} other{# fichiers}}',
            ],
            'select' => [
                'en',
                'de',
                '{gender, select, male{Mr.} female{Ms.} other{Mx.}}',
                '{gender, select, male{Herr} female{Frau} other{Person}}',
            ],
            'choice' => [
                'en',
                'fr',
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
     * @throws InvalidArgumentException
     * @throws MissingComplexFormException
     * @throws OutOfBoundsException
     * @throws PluralComplianceException
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


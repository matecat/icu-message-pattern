<?php

declare(strict_types=1);

/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 04/02/26
 * Time: 17:09
 *
 */

namespace Matecat\ICU;

use Matecat\ICU\Exceptions\InvalidArgumentException;
use Matecat\ICU\Exceptions\OutOfBoundsException;
use Matecat\ICU\Plurals\PluralArgumentWarning;
use Matecat\ICU\Plurals\PluralComplianceException;
use Matecat\ICU\Plurals\PluralComplianceWarning;
use Matecat\ICU\Plurals\PluralRules;
use Matecat\ICU\Tokens\ArgType;
use Matecat\ICU\Tokens\TokenType;

final class MessagePatternValidator
{

    private OutOfBoundsException|InvalidArgumentException|null $parsingException = null;
    protected ?MessagePattern $pattern = null;

    public function __construct(
        protected string $language = 'en-US',
        protected ?string $patternString = null,
    ) {
    }

    /**
     * Retrieves the language value.
     *
     * @return string
     */
    public function getLanguage(): string
    {
        return $this->language;
    }

    /**
     * Creates a validator from a pre-parsed MessagePattern.
     *
     * This is useful when:
     * - You've already parsed a MessagePattern elsewhere and want to validate it without re-parsing
     * - You want to validate the same pattern against multiple locales (reuse the parsed pattern)
     *
     * @param string $language The locale to validate against (e.g., 'en', 'ru', 'ar')
     * @param MessagePattern $pattern A pre-parsed MessagePattern instance
     * @return static A new validator instance
     */
    public static function fromPattern(string $language, MessagePattern $pattern): MessagePatternValidator
    {
        $validator = new MessagePatternValidator($language);
        $validator->pattern = $pattern;
        return $validator;
    }

    /**
     * @param string $patternString
     *
     * @return $this
     */
    public function setPatternString(string $patternString): MessagePatternValidator
    {
        $this->patternString = $patternString;
        $this->parsingException = null;
        $this->pattern?->clear();
        return $this;
    }

    /**
     * Returns the parsed MessagePattern instance.
     *
     * Note: This will trigger pattern parsing if not already done.
     *
     * @return MessagePattern The parsed pattern.
     */
    public function getPattern(): MessagePattern
    {
        $this->checkForPatternInitialized();
        assert($this->pattern !== null);
        return $this->pattern;
    }

    /**
     * @return bool Returns true if the message pattern contains complex syntax (plural, select, choice, selectordinal),
     * false otherwise.
     */
    public function containsComplexSyntax(): bool
    {
        $this->checkForPatternInitialized();
        assert($this->pattern !== null);

        foreach ($this->pattern as $part) {
            if ($part->getArgType()->isComplexType()) {
                return true; // Early exit is also more efficient
            }
        }
        return false;
    }

    /**
     * @return bool
     */
    public function isValidSyntax(): bool
    {
        $this->checkForPatternInitialized();
        return $this->parsingException === null;
    }

    /**
     * @return string|null
     */
    public function getSyntaxException(): ?string
    {
        return $this->parsingException?->getMessage();
    }

    /**
     * Checks if the pattern hasn't been analyzed yet. If not, it will be parsed.
     * @return void
     */
    private function checkForPatternInitialized(): void
    {
        if ($this->pattern === null) {
            $this->pattern = new MessagePattern();
        }

        if ($this->pattern->parts()->countParts() === 0 && $this->patternString !== null) {
            try {
                $this->pattern->parse($this->patternString);
            } catch (OutOfBoundsException|InvalidArgumentException $e) {
                $this->parsingException = $e;
            }
        }
    }

    /**
     * Validates whether the plural/selectordinal forms in the message pattern comply
     * with the expected CLDR plural categories for the configured locale.
     *
     * This method extracts all selectors from plural and selectordinal arguments
     * and checks if they match the valid categories for the language.
     *
     * Valid selectors include:
     * - CLDR category names: 'zero', 'one', 'two', 'few', 'many', 'other'
     * - Explicit numeric selectors: '=0', '=1', '=2', etc.
     *
     * Note: The 'other' category is always valid as ICU requires it as a fallback.
     * Cardinal (plural) and ordinal (selectordinal) rules are validated separately
     * according to CLDR specifications.
     *
     * Validation behavior:
     * - Throws PluralComplianceException ONLY for non-existent category names (e.g., 'some', 'foo')
     * - Returns PluralComplianceWarning for all other issues:
     *   - Valid CLDR categories that don't apply to the locale (e.g., 'few' in English)
     *   - Missing required categories for the locale
     *
     * @return PluralComplianceWarning|null Returns a warning object if there are compliance issues, null otherwise.
     * @throws PluralComplianceException Only if a selector is not a valid CLDR category name.
     * @throws OutOfBoundsException
     * @throws InvalidArgumentException
     *
     */
    public function validatePluralCompliance(): ?PluralComplianceWarning
    {
        $this->checkForPatternInitialized();
        assert($this->pattern !== null);

        if ($this->parsingException) {
            throw $this->parsingException;
        }

        $allInvalidSelectors = [];
        $allFoundSelectors = [];
        /** @var array<PluralArgumentWarning> $argumentWarnings */
        $argumentWarnings = [];
        $allMissingCategories = [];

        foreach ($this->pattern as $index => $part) {
            if ($part->getType() !== TokenType::ARG_START) {
                continue;
            }

            if (!$part->getArgType()->hasPluralStyle()) {
                continue;
            }

            $this->analyzePluralArgument(
                $index,
                $part->getArgType(),
                $allFoundSelectors,
                $allInvalidSelectors,
                $allMissingCategories,
                $argumentWarnings
            );
        }

        return $this->buildComplianceResult(
            $allFoundSelectors,
            $allInvalidSelectors,
            $allMissingCategories,
            $argumentWarnings
        );
    }

    /**
     * Analyzes a single plural/selectordinal argument, classifying its selectors
     * and collecting warnings for missing or wrong-locale categories.
     *
     * @param int $index The index of the ARG_START part in the pattern.
     * @param ArgType $argType The argument type (PLURAL or SELECTORDINAL).
     * @param array<string> &$allFoundSelectors Accumulator for all found selectors across arguments.
     * @param array<string> &$allInvalidSelectors Accumulator for all invalid selectors across arguments.
     * @param array<string> &$allMissingCategories Accumulator for all missing categories across arguments.
     * @param array<PluralArgumentWarning> &$argumentWarnings Accumulator for per-argument warnings.
     * @throws OutOfBoundsException
     */
    private function analyzePluralArgument(
        int $index,
        ArgType $argType,
        array &$allFoundSelectors,
        array &$allInvalidSelectors,
        array &$allMissingCategories,
        array &$argumentWarnings
    ): void {
        $argumentName = $this->getArgumentName($index + 1);
        $categories = $this->getCategoriesForArgType($argType);
        $selectors = $this->extractSelectorsForArgument($index);

        [$numericSelectors, $categorySelectors, $wrongLocaleSelectors, $invalidSelectors] =
            $this->classifySelectors($selectors, $categories);

        array_push($allFoundSelectors, ...$selectors);
        array_push($allInvalidSelectors, ...$invalidSelectors);

        $missingCategories = array_values(array_diff($categories, $categorySelectors));
        array_push($allMissingCategories, ...$missingCategories);

        if (!empty($wrongLocaleSelectors) || !empty($missingCategories)) {
            $argumentWarnings[] = new PluralArgumentWarning(
                argumentName: $argumentName,
                argumentType: $argType,
                expectedCategories: $categories,
                foundSelectors: $selectors,
                missingCategories: $missingCategories,
                numericSelectors: $numericSelectors,
                wrongLocaleSelectors: $wrongLocaleSelectors,
                locale: $this->language
            );
        }
    }

    /**
     * Classifies a list of selectors into numeric, category, wrong-locale, and invalid groups.
     *
     * @param array<string> $selectors The selectors to classify.
     * @param array<string> $validCategories The valid CLDR categories for this argument type and locale.
     * @return array{0: array<string>, 1: array<string>, 2: array<string>, 3: array<string>}
     *         [numericSelectors, categorySelectors, wrongLocaleSelectors, invalidSelectors]
     */
    private function classifySelectors(array $selectors, array $validCategories): array
    {
        $numeric = [];
        $category = [];
        $wrongLocale = [];
        $invalid = [];

        foreach ($selectors as $selector) {
            if (preg_match(self::NUMERIC_SELECTOR_PATTERN, $selector)) {
                $numeric[] = $selector;
                continue;
            }

            $category[] = $selector;

            if ($selector === PluralRules::CATEGORY_OTHER) {
                continue;
            }

            if (!PluralRules::isValidCategory($selector)) {
                $invalid[] = $selector;
                continue;
            }

            if (!in_array($selector, $validCategories, true)) {
                $wrongLocale[] = $selector;
            }
        }

        return [$numeric, $category, $wrongLocale, $invalid];
    }

    /**
     * Builds the final compliance result: throws on invalid selectors, returns a warning
     * if there are per-argument issues, or null if everything is compliant.
     *
     * @param array<string> $allFoundSelectors All selectors found across all plural arguments.
     * @param array<string> $allInvalidSelectors All non-existent category selectors found.
     * @param array<string> $allMissingCategories All missing categories across arguments.
     * @param array<PluralArgumentWarning> $argumentWarnings Per-argument compliance warnings.
     * @return PluralComplianceWarning|null
     * @throws PluralComplianceException If any selector is not a valid CLDR category name.
     */
    private function buildComplianceResult(
        array $allFoundSelectors,
        array $allInvalidSelectors,
        array $allMissingCategories,
        array $argumentWarnings
    ): ?PluralComplianceWarning {
        if (empty($allFoundSelectors)) {
            return null;
        }

        if (!empty($allInvalidSelectors)) {
            throw new PluralComplianceException(
                expectedCategories: PluralRules::VALID_CATEGORIES,
                foundSelectors: array_unique($allFoundSelectors),
                invalidSelectors: array_unique($allInvalidSelectors),
                missingCategories: array_unique($allMissingCategories),
                locale: $this->language
            );
        }

        if (!empty($argumentWarnings)) {
            return new PluralComplianceWarning($argumentWarnings);
        }

        return null;
    }

    /**
     * Gets the argument name for a plural/selectordinal argument.
     *
     * @param int $argNameIndex The index of the ARG_NAME or ARG_NUMBER part (ARG_START index + 1).
     * @return string The argument name.
     * @throws OutOfBoundsException
     */
    private function getArgumentName(int $argNameIndex): string
    {
        assert($this->pattern !== null);
        // According to ICU pattern structure: ARG_START is followed immediately by ARG_NAME or ARG_NUMBER
        $part = $this->pattern->parts()->getPart($argNameIndex);
        $type = $part->getType();
        if ($type === TokenType::ARG_NAME || $type === TokenType::ARG_NUMBER) {
            $name = $this->pattern->parts()->getSubstring($part);
        }

        return $name ?? 'unknown';
    }

    /**
     * Returns the appropriate CLDR categories for the given argument type.
     *
     * @param ArgType $argType The argument type (PLURAL or SELECTORDINAL).
     * @return array<string> The CLDR categories for this argument type and locale.
     */
    private function getCategoriesForArgType(ArgType $argType): array
    {
        if ($argType === ArgType::SELECTORDINAL) {
            return PluralRules::getOrdinalCategories($this->language);
        }

        return PluralRules::getCardinalCategories($this->language);
    }

    /**
     * Pattern for matching explicit numeric selectors (e.g., =0, =1, =2).
     */
    private const string NUMERIC_SELECTOR_PATTERN = '/^=(\\d+)$/';

    /**
     * Extracts all direct ARG_SELECTOR values for a given plural/selectordinal argument.
     *
     * Nested arguments (e.g., a plural inside a selectordinal) are skipped so that
     * only selectors belonging to the current argument level are collected.
     *
     * @param int $startIndex The index of the ARG_START part in the pattern.
     * @return array<string> List of selector strings found.
     * @throws OutOfBoundsException
     */
    private function extractSelectorsForArgument(int $startIndex): array
    {
        assert($this->pattern !== null);
        $selectors = [];
        $limitIndex = $this->pattern->parts()->getLimitPartIndex($startIndex);

        // Iterate only between ARG_START and ARG_LIMIT, skipping nested arguments
        for ($i = $startIndex + 1; $i < $limitIndex; $i++) {
            $part = $this->pattern->parts()->getPart($i);
            $type = $part->getType();

            // Skip over nested arguments entirely
            if ($type === TokenType::ARG_START) {
                $i = $this->pattern->parts()->getLimitPartIndex($i);
                continue;
            }

            if ($type === TokenType::ARG_SELECTOR) {
                $selectors[] = $this->pattern->parts()->getSubstring($part);
            }
        }

        return $selectors;
    }

}

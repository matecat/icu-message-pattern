<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 04/02/26
 * Time: 17:09
 *
 */

namespace Matecat\ICU;

use Exception;
use Matecat\ICU\Plurals\PluralArgumentWarning;
use Matecat\ICU\Plurals\PluralComplianceException;
use Matecat\ICU\Plurals\PluralComplianceWarning;
use Matecat\ICU\Plurals\PluralRules;
use Matecat\ICU\Tokens\ArgType;
use Matecat\ICU\Tokens\TokenType;

class MessagePatternValidator
{

    private ?Exception $parsingException = null;

    public function __construct(
        protected string $language = 'en-US',
        protected ?string $patternString = null,
        protected ?MessagePattern $pattern = null,
    ) {
    }

    /**
     * @param string $patternString
     *
     * @return $this
     */
    public function setPatternString(string $patternString): static
    {
        $this->patternString = $patternString;
        return $this;
    }

    /**
     * @return bool Returns true if the message pattern contains complex syntax (plural, select, choice, selectordinal),
     * false otherwise.
     */
    public function containsComplexSyntax(): bool
    {
        $this->checkForPatternInitialized();

        foreach ($this->pattern as $part) {
            $argType = $part->getArgType();
            if (
                $argType->hasPluralStyle() ||
                $argType === ArgType::SELECT ||
                $argType === ArgType::CHOICE
            ) {
                return true; // Early exit is also more efficient
            }
        }
        return false;
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

        if ($this->pattern->countParts() === 0 && $this->patternString !== null) {
            try {
                $this->pattern->parse($this->patternString);
            } catch (Exception $e) {
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
     * @throws Exception
     *
     */
    public function validatePluralCompliance(): ?PluralComplianceWarning
    {
        $this->checkForPatternInitialized();

        if ($this->parsingException) {
            throw $this->parsingException;
        }

        $allInvalidSelectors = [];      // Non-existent categories (like 'some') - throws exception
        $allFoundSelectors = [];
        /** @var array<PluralArgumentWarning> $argumentWarnings */
        $argumentWarnings = [];
        $allMissingCategories = [];

        foreach ($this->pattern as $index => $part) {
            // Only process ARG_START parts (skip ARG_LIMIT and all other part types)
            $partType = $part->getType();
            if ($partType !== TokenType::ARG_START) {
                continue;
            }

            $argType = $part->getArgType();

            // Only check plural and selectordinal arguments
            if (!$argType->hasPluralStyle()) {
                continue;
            }

            // Get the argument name
            $argumentName = $this->getArgumentName($index + 1);

            // Get the appropriate categories for THIS specific argument type (cardinal vs ordinal)
            $categories = $this->getCategoriesForArgType($argType);

            // Find all selectors within this argument
            $selectors = $this->extractSelectorsForArgument($index);

            // Separate numeric selectors from category selectors
            $argumentNumericSelectors = [];
            $argumentCategorySelectors = [];
            $argumentWrongLocaleSelectors = [];

            foreach ($selectors as $selector) {
                $allFoundSelectors[] = $selector;

                // Explicit numeric selectors (=0, =1, =2, etc.) are always valid
                if (preg_match(self::NUMERIC_SELECTOR_PATTERN, $selector)) {
                    $argumentNumericSelectors[] = $selector;
                    continue;
                }

                // It's a category selector
                $argumentCategorySelectors[] = $selector;

                // 'other' is always valid - ICU requires it as a fallback
                if ($selector === PluralRules::CATEGORY_OTHER) {
                    continue;
                }

                // Check if it's a valid CLDR category at all
                if (!PluralRules::isValidCategory($selector)) {
                    // Non-existent category - this is an error
                    $allInvalidSelectors[] = $selector;
                    continue;
                }

                // Check if the selector is valid for THIS argument type and locale
                if (!in_array($selector, $categories, true)) {
                    // Valid CLDR category but wrong for this locale/argument type - this is a warning
                    $argumentWrongLocaleSelectors[] = $selector;
                }
            }

            // Check for missing categories for THIS specific argument
            $argumentMissingCategories = array_values(array_diff($categories, $argumentCategorySelectors));
            $allMissingCategories = array_merge($allMissingCategories, $argumentMissingCategories);

            // Create a warning for this argument if there are issues
            if (!empty($argumentWrongLocaleSelectors) || !empty($argumentMissingCategories)) {
                $argumentWarnings[] = new PluralArgumentWarning(
                    argumentName: $argumentName,
                    argumentType: $argType,
                    expectedCategories: $categories,
                    foundSelectors: $selectors,
                    missingCategories: $argumentMissingCategories,
                    numericSelectors: $argumentNumericSelectors,
                    wrongLocaleSelectors: $argumentWrongLocaleSelectors,
                    locale: $this->language
                );
            }
        }

        // Validate only if plural forms are found in the message pattern
        if (!empty($allFoundSelectors)) {
            // Check for non-existent categories and throw an exception if any are found
            if (!empty($allInvalidSelectors)) {
                throw new PluralComplianceException(
                    expectedCategories: PluralRules::VALID_CATEGORIES,
                    foundSelectors: array_unique($allFoundSelectors),
                    invalidSelectors: array_unique($allInvalidSelectors),
                    missingCategories: array_unique($allMissingCategories),
                    locale: $this->language
                );
            }

            // Return warning for argument-level issues (wrong locale selectors or missing categories per argument)
            if (!empty($argumentWarnings)) {
                return new PluralComplianceWarning($argumentWarnings);
            }
        }

        return null;
    }

    /**
     * Gets the argument name for a plural/selectordinal argument.
     *
     * @param int $argNameIndex The index of the ARG_NAME or ARG_NUMBER part (ARG_START index + 1).
     * @return string The argument name.
     */
    private function getArgumentName(int $argNameIndex): string
    {
        // According to ICU pattern structure: ARG_START is followed immediately by ARG_NAME or ARG_NUMBER
        $part = $this->pattern->getPart($argNameIndex);
        $type = $part->getType();
        if ($type === TokenType::ARG_NAME || $type === TokenType::ARG_NUMBER) {
            $name = $this->pattern->getSubstring($part);
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
     * Extracts all ARG_SELECTOR values for a given plural/selectordinal argument.
     *
     * @param int $startIndex The index of the ARG_START part in the pattern.
     * @return array<string> List of selector strings found.
     */
    private function extractSelectorsForArgument(int $startIndex): array
    {
        $selectors = [];
        $limitIndex = $this->pattern->getLimitPartIndex($startIndex);

        // Iterate only between ARG_START and ARG_LIMIT, skipping full pattern iteration
        for ($i = $startIndex + 1; $i < $limitIndex; $i++) {
            $part = $this->pattern->getPart($i);
            if ($part->getType() === TokenType::ARG_SELECTOR) {
                $selectors[] = $this->pattern->getSubstring($part);
            }
        }

        return $selectors;
    }

}
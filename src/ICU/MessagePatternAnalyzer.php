<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 04/02/26
 * Time: 17:09
 *
 */

namespace Matecat\ICU;

use Matecat\ICU\Plurals\PluralComplianceException;
use Matecat\ICU\Plurals\PluralRules;
use Matecat\ICU\Tokens\ArgType;
use Matecat\ICU\Tokens\TokenType;

class MessagePatternAnalyzer
{

    public function __construct(
        protected MessagePattern $pattern,
        protected string $language = 'en-US'
    ) {
    }

    /**
     * @return bool Returns true if the message pattern contains complex syntax (plural, select, choice, selectordinal),
     * false otherwise.
     */
    public function containsComplexSyntax(): bool
    {
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
     * @return void
     * @throws PluralComplianceException If any selector is invalid or required categories are missing.
     *
     */
    public function validatePluralCompliance(): void
    {
        $foundSelectors = [];
        $invalidSelectors = [];
        $expectedCategories = [];

        foreach ($this->pattern as $index => $part) {
            $argType = $part->getArgType();

            // Only check plural and selectordinal arguments
            if (!$argType->hasPluralStyle()) {
                continue;
            }

            // Get the appropriate categories based on argument type (cardinal vs ordinal)
            $categories = $this->getCategoriesForArgType($argType);

            // Merge expected categories (they may differ between plural and selectordinal)
            $expectedCategories = array_unique(array_merge($expectedCategories, $categories));

            // Find all selectors within this argument - pass the index to avoid re-iterating
            $selectors = $this->extractSelectorsForArgument($index);

            foreach ($selectors as $selector) {
                $foundSelectors[] = $selector;

                // Explicit numeric selectors (=0, =1, =2, etc.) are always valid
                if (preg_match(self::NUMERIC_SELECTOR_PATTERN, $selector)) {
                    continue;
                }

                // 'other' is always valid - ICU requires it as fallback
                if ($selector === PluralRules::CATEGORY_OTHER) {
                    continue;
                }

                // Check if the selector is a valid CLDR category for this locale and argument type
                if (!in_array($selector, $categories, true)) {
                    $invalidSelectors[] = $selector;
                }
            }
        }

        // Only throw exception if we found plural forms with invalid selectors or missing categories
        if (!empty($foundSelectors)) {
            $missingCategories = $this->getMissingCategories($foundSelectors, $expectedCategories);

            if (!empty($invalidSelectors) || !empty($missingCategories)) {
                throw new PluralComplianceException(
                    expectedCategories: $expectedCategories,
                    foundSelectors: array_unique($foundSelectors),
                    invalidSelectors: array_unique($invalidSelectors),
                    missingCategories: $missingCategories
                );
            }
        }
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
     * Extracts category selectors (non-numeric) from a list of selectors.
     *
     * @param array<string> $selectors All selectors found in the message.
     * @return array<string> Only the category selectors, excluding numeric ones.
     */
    private function extractCategorySelectors(array $selectors): array
    {
        return array_values(array_filter($selectors, fn($s) => !preg_match(self::NUMERIC_SELECTOR_PATTERN, $s)));
    }

    /**
     * Calculates missing categories from expected categories against found selectors.
     *
     * This method enforces strict validation of plural categories. Explicit numeric selectors
     * (e.g., =0, =1, =2) are NOT allowed to substitute for CLDR plural category keywords
     * (e.g., 'zero', 'one', 'two', 'few', 'many', 'other').
     *
     * Every expected plural category for the locale MUST be explicitly provided using
     * the corresponding category keyword. While numeric selectors are syntactically valid,
     * they cannot fulfill the requirement for category-based selectors.
     *
     * Example:
     * - INVALID: {count, plural, =0 {no items} =1 {one item} other {many items}}
     *   (missing required 'one' category in en-US)
     * - VALID: {count, plural, one {one item} other {many items}}
     *   (uses proper CLDR category keywords)
     *
     * @param array<string> $foundSelectors All selectors found in the message.
     * @param array<string> $expectedCategories Expected CLDR categories for the locale.
     * @return array<string> Missing categories (not found in selectors).
     */
    private function getMissingCategories(array $foundSelectors, array $expectedCategories): array
    {
        // Extract only CLDR category selectors (exclude numeric selectors like =0, =1)
        $foundCategorySelectors = $this->extractCategorySelectors($foundSelectors);

        // Calculate missing categories by comparing expected categories against found category selectors
        // Numeric selectors (=0, =1, etc.) do NOT count toward fulfilling category requirements
        return array_values(array_diff($expectedCategories, $foundCategorySelectors));
    }

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
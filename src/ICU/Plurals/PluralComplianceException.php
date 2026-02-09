<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 05/02/26
 * Time: 10:30
 *
 */

namespace Matecat\ICU\Plurals;

use Exception;
use Throwable;

/**
 * Exception thrown when a message pattern's plural selectors do not comply
 * with the expected CLDR plural categories for a given locale.
 *
 * This exception is raised by MessagePatternValidator::validatePluralCompliance()
 * when the message contains:
 * - Invalid plural selectors that don't match expected CLDR categories
 * - Missing required plural categories for the locale
 *
 * Note: The 'other' category is always valid as ICU requires it as a fallback.
 */
class PluralComplianceException extends Exception
{
    /**
     * @param array<string> $expectedCategories The valid CLDR categories for this locale.
     * @param array<string> $foundSelectors All selectors found in the message.
     * @param array<string> $invalidSelectors Selectors that don't match expected categories.
     * @param array<string> $missingCategories Expected categories not found in the message.
     * @param string $locale The locale being validated.
     * @param int $code Exception code.
     * @param Throwable|null $previous Previous exception for chaining.
     */
    public function __construct(
        public readonly array $expectedCategories,
        public readonly array $foundSelectors,
        public readonly array $invalidSelectors,
        public readonly array $missingCategories,
        public readonly string $locale = '',
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($this->generateMessage(), $code, $previous);
    }

    /**
     * Generates a human-readable error message from the compliance details.
     */
    private function generateMessage(): string
    {
        $localeInfo = $this->locale ? " for locale '{$this->locale}'" : '';

        $parts = [];
        $parts[] = sprintf(
            'Invalid selectors found%s: [%s].',
            $localeInfo,
            implode(', ', $this->invalidSelectors)
        );

        if (!empty($this->foundSelectors)) {
            $parts[] = sprintf(
                'Found selectors: [%s].',
                implode(', ', $this->foundSelectors)
            );
        }

        if (!empty($this->missingCategories)) {
            $parts[] = sprintf(
                'Missing required categories: [%s].',
                implode(', ', $this->missingCategories)
            );
        }

        $parts[] = sprintf(
            'Valid CLDR categories are: [%s].',
            implode(', ', $this->expectedCategories)
        );

        return implode(' ', $parts);
    }
}

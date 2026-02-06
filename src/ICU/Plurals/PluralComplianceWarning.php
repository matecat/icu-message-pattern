<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 06/02/26
 * Time: 10:30
 *
 */

namespace Matecat\ICU\Plurals;

/**
 * Warning object returned when a message pattern's plural selectors have compliance issues
 * that don't warrant an exception.
 *
 * This is returned when:
 * - Valid CLDR categories are used that don't apply to the locale (e.g., 'few' in English)
 * - Required categories are missing but numeric selectors (=0, =1, =2) are present
 *
 * The pattern is still syntactically valid, but may not provide proper localization coverage.
 *
 * Note: When a pattern has truly invalid selectors (not valid CLDR categories at all),
 * a PluralComplianceException is thrown instead.
 */
readonly class PluralComplianceWarning
{
    /**
     * @param array<string> $expectedCategories The valid CLDR categories for this locale.
     * @param array<string> $foundSelectors All selectors found in the message.
     * @param array<string> $missingCategories Expected categories not found in the message.
     * @param array<string> $numericSelectors Explicit numeric selectors found (e.g., =0, =1, =2).
     * @param array<string> $wrongLocaleSelectors Valid CLDR categories that don't apply to this locale.
     */
    public function __construct(
        public array $expectedCategories,
        public array $foundSelectors,
        public array $missingCategories,
        public array $numericSelectors,
        public array $wrongLocaleSelectors = []
    ) {
    }

    /**
     * Generates a human-readable warning message.
     */
    public function getMessage(): string
    {
        $messages = [];

        if (!empty($this->wrongLocaleSelectors)) {
            $messages[] = sprintf(
                'Categories [%s] are valid CLDR categories but do not apply to this locale. '
                . 'Expected categories: [%s].',
                implode(', ', $this->wrongLocaleSelectors),
                implode(', ', $this->expectedCategories)
            );
        }

        if (!empty($this->missingCategories) && !empty($this->numericSelectors)) {
            $messages[] = sprintf(
                'Pattern uses explicit numeric selectors [%s] but is missing CLDR category keywords: [%s]. '
                . 'Consider using proper category keywords for better localization coverage.',
                implode(', ', $this->numericSelectors),
                implode(', ', $this->missingCategories)
            );
        }

        return implode(' ', $messages) ?: 'Plural compliance warning.';
    }
}

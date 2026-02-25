<?php

/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 25/02/26
 * Time: 12:00
 *
 */
declare(strict_types=1);

namespace Matecat\ICU\Comparator;

use Matecat\ICU\Plurals\PluralComplianceWarning;

/**
 * Result object returned by {@see MessagePatternComparator::validate()}.
 *
 * Contains optional plural compliance warnings for the source and target patterns.
 * A property is null when validation was not requested for that side or when no issues were found.
 */
readonly class ComparisonResult
{
    /**
     * @param PluralComplianceWarning|null $sourceWarnings Plural compliance warnings for the source pattern,
     *        or null if source validation was not requested or no issues were found.
     * @param PluralComplianceWarning|null $targetWarnings Plural compliance warnings for the target pattern,
     *        or null if target validation was not requested or no issues were found.
     */
    public function __construct(
        public ?PluralComplianceWarning $sourceWarnings = null,
        public ?PluralComplianceWarning $targetWarnings = null,
    ) {
    }

    /**
     * Returns true if either side has warnings.
     *
     * @return bool
     */
    public function hasWarnings(): bool
    {
        return $this->sourceWarnings !== null || $this->targetWarnings !== null;
    }
}


<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 03/03/26
 * Time: 14:27
 *
 */

namespace Matecat\Locales\DTO;

use JsonSerializable;

/**
 * Immutable DTO representing a single plural category with its rule and human-readable description.
 *
 * Each fragment describes one CLDR plural category (e.g. "one", "few", "other")
 * along with the formal rule, a human-readable explanation, and usage examples.
 */
final readonly class CategoryFragment implements JsonSerializable
{

    /**
     * @param string $category The CLDR category name (e.g. "zero", "one", "two", "few", "many", "other").
     * @param string $rule     The formal CLDR plural rule expression (empty string for the "other" fallback).
     * @param string $human_rule A human-readable description of the rule in English.
     * @param string $example  Example numbers that match this category.
     */
    public function __construct(
        public string $category,
        public string $rule,
        public string $human_rule,
        public string $example
    )
    {
    }


    /**
     * @inheritDoc
     *
     * @return array{category: string, rule: string, human_rule: string, example: string}
     */
    public function jsonSerialize(): array
    {
        return [
            'category'   => $this->category,
            'rule'       => $this->rule,
            'human_rule' => $this->human_rule,
            'example'    => $this->example,
        ];
    }
}
<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 03/03/26
 * Time: 14:41
 *
 */

namespace Matecat\Locales\DTO;

use JsonSerializable;

/**
 * Immutable DTO representing a language's plural rules for both cardinal and ordinal forms.
 *
 * Each fragment contains the language name and its CLDR plural categories
 * with human-readable descriptions for cardinal and ordinal number types.
 */
final readonly class LanguageRulesFragment implements JsonSerializable
{

    /**
     * @param string $name
     * @param string $isoCode
     * @param CategoryFragment[] $cardinal
     * @param CategoryFragment[] $ordinal
     */
    public function __construct(
        public string $name,
        public string $isoCode,
        public array  $cardinal,
        public array  $ordinal,
    )
    {
    }


    /**
     * @inheritDoc
     *
     * @return array{name: string, cardinal: CategoryFragment[], ordinal: CategoryFragment[]}
     */
    public function jsonSerialize(): array
    {
        return [
            'name'     => $this->name,
            'isoCode'  => $this->isoCode,
            'cardinal' => $this->cardinal,
            'ordinal'  => $this->ordinal,
        ];
    }
}
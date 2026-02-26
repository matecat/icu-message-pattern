<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 26/02/26
 * Time: 11:22
 *
 */

namespace Matecat\ICU\Exceptions;

use Throwable;

class BadPluralSelectPatternSyntaxException extends InvalidArgumentException
{
    public function __construct(
        string $argumentType = "",
        string $errorContext = "",
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct("Bad " . strtolower($argumentType) . " pattern syntax: " . $errorContext, $code, $previous);
    }
}

<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 26/02/26
 * Time: 12:08
 *
 */

namespace Matecat\ICU\Exceptions;

use Throwable;

class BadChoicePatternSyntaxException extends InvalidArgumentException
{
    public function __construct(string $errorContext = "", int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct("Bad choice pattern syntax: " . $errorContext, $code, $previous);
    }
}

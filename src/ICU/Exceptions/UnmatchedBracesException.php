<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 26/02/26
 * Time: 11:13
 *
 */

namespace Matecat\ICU\Exceptions;

use Throwable;

class UnmatchedBracesException extends InvalidArgumentException
{
    public function __construct(string $errorContext, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct("Unmatched '{' braces in message " . $errorContext, $code, $previous);
    }
}

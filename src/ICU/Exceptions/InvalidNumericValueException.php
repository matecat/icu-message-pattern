<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 26/02/26
 * Time: 11:32
 *
 */

namespace Matecat\ICU\Exceptions;

use Throwable;

class InvalidNumericValueException extends InvalidArgumentException
{
    public function __construct(int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct("Bad syntax for numeric value.", $code, $previous);
    }
}

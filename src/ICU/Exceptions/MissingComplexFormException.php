<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 17/02/26
 * Time: 12:00
 *
 */

namespace Matecat\ICU\Exceptions;

use Exception;
use Matecat\ICU\Tokens\ArgType;
use Throwable;

/**
 * Exception thrown when the target pattern is missing a complex form that exists in the source pattern.
 *
 * This exception is raised by MessagePatternComparator::validate() when:
 * - An argument in the source has a complex form (plural, select, choice, selectordinal)
 *   but the corresponding argument in the target is missing or has a different complex form
 */
class MissingComplexFormException extends Exception
{
    /**
     * @param string $argumentName The name of the argument with the missing complex form.
     * @param ArgType $sourceArgType The argument type in the source pattern.
     * @param ArgType|null $targetArgType The argument type in the target pattern (null if missing).
     * @param string $sourceLocale The source locale.
     * @param string $targetLocale The target locale.
     * @param int $code Exception code.
     * @param Throwable|null $previous Previous exception for chaining.
     */
    public function __construct(
        public readonly string $argumentName,
        public readonly ArgType $sourceArgType,
        public readonly ?ArgType $targetArgType,
        public readonly string $sourceLocale,
        public readonly string $targetLocale,
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($this->generateMessage(), $code, $previous);
    }

    private function generateMessage(): string
    {
        if ($this->targetArgType === null) {
            return sprintf(
                "Argument '%s' has complex form '%s' in source (%s) but is missing in target (%s).",
                $this->argumentName,
                $this->sourceArgType->name,
                $this->sourceLocale,
                $this->targetLocale
            );
        }

        return sprintf(
            "Argument '%s' has complex form '%s' in source (%s) but has '%s' in target (%s).",
            $this->argumentName,
            $this->sourceArgType->name,
            $this->sourceLocale,
            $this->targetArgType->name,
            $this->targetLocale
        );
    }
}


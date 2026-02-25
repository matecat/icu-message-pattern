<?php

/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 17/02/26
 * Time: 12:00
 *
 */
declare(strict_types=1);

namespace Matecat\ICU;

use Matecat\ICU\Exceptions\MissingComplexFormException;
use Matecat\ICU\Exceptions\OutOfBoundsException;
use Matecat\ICU\Tokens\TokenType;
use stdClass;

/**
 * Compares source and target ICU MessageFormat patterns for translation validation.
 *
 * This class validates that if the source contains complex forms (plural, select, choice, selectordinal),
 * the target must contain the same complex forms for the same arguments.
 *
 * Usage:
 * <pre>
 * ```
 * <?php
 * $comparator = new MessagePatternComparator(
 *     'en-US',
 *     'fr-FR',
 *     '{count, plural, one{# item} other{# items}}',
 *     '{count, plural, one{# article} many{# articles} other{# articles}}'
 * );
 *
 * $comparator->validate(); // throws exception if the target is missing complex forms from source
 * ```
 * </pre>
 */
final class MessagePatternComparator
{
    private MessagePatternValidator $sourceValidator;
    private MessagePatternValidator $targetValidator;

    /**
     * @param string $sourceLocale The locale for the source pattern (e.g., 'en-US', 'en')
     * @param string $targetLocale The locale for the target pattern (e.g., 'fr-FR', 'ru')
     * @param string $sourcePattern The ICU MessageFormat pattern string for the source
     * @param string $targetPattern The ICU MessageFormat pattern string for the target (translation)
     */
    public function __construct(
        private readonly string $sourceLocale,
        private readonly string $targetLocale,
        private readonly string $sourcePattern,
        private readonly string $targetPattern,
    ) {
        $this->sourceValidator = new MessagePatternValidator($this->sourceLocale, $this->sourcePattern);
        $this->targetValidator = new MessagePatternValidator($this->targetLocale, $this->targetPattern);
    }

    /**
     * Creates a comparator from pre-configured MessagePatternValidator instances.
     *
     * This is useful when:
     * - You've already created validators elsewhere and want to reuse them
     * - You need custom validator configurations
     *
     * @param MessagePatternValidator $sourceValidator The validator for the source pattern
     * @param MessagePatternValidator $targetValidator The validator for the target pattern
     * @return static A new comparator instance
     */
    public static function fromValidators(
        MessagePatternValidator $sourceValidator,
        MessagePatternValidator $targetValidator
    ): MessagePatternComparator {
        $comparator = new self(
            $sourceValidator->getLanguage(),
            $targetValidator->getLanguage(),
            $sourceValidator->getPattern()->getPatternString(),
            $targetValidator->getPattern()->getPatternString()
        );
        $comparator->sourceValidator = $sourceValidator;
        $comparator->targetValidator = $targetValidator;
        return $comparator;
    }

    /**
     * Creates a comparator from pre-parsed MessagePattern instances.
     *
     * This is useful when:
     * - You've already parsed MessagePattern objects and want to compare them without reparsing
     * - You want to compare the same patterns against different locale pairs (reuse parsed patterns)
     *
     * @param string $sourceLocale The locale for the source pattern (e.g., 'en-US', 'en')
     * @param string $targetLocale The locale for the target pattern (e.g., 'fr-FR', 'ru')
     * @param MessagePattern $sourcePattern A pre-parsed MessagePattern instance for the source
     * @param MessagePattern $targetPattern A pre-parsed MessagePattern instance for the target
     * @return static A new comparator instance
     */
    public static function fromPatterns(
        string $sourceLocale,
        string $targetLocale,
        MessagePattern $sourcePattern,
        MessagePattern $targetPattern
    ): MessagePatternComparator {
        $comparator = new self(
            $sourceLocale,
            $targetLocale,
            $sourcePattern->getPatternString(),
            $targetPattern->getPatternString()
        );
        $comparator->sourceValidator = MessagePatternValidator::fromPattern($sourceLocale, $sourcePattern);
        $comparator->targetValidator = MessagePatternValidator::fromPattern($targetLocale, $targetPattern);
        return $comparator;
    }

    /**
     * Validates that complex forms in the source pattern exist in the target pattern.
     *
     * If the source contains complex forms (plural, select, choice, selectordinal),
     * the target must contain the same complex forms for the same arguments.
     *
     * @return void
     * @throws MissingComplexFormException If the target is missing a complex form from the source.
     * @throws OutOfBoundsException If pattern parsing exceeds limits.
     */
    public function validate(): void
    {
        // If the source contains complex syntax, ensure the target has matching complex forms
        if ($this->sourceValidator->containsComplexSyntax()) {
            $this->validateComplexFormCompatibility();
        }
    }

    /**
     * Validates that all complex forms in the source pattern exist in the target pattern.
     *
     * Complex forms include: PLURAL, SELECT, CHOICE, SELECTORDINAL
     *
     * When patterns contain nested complex forms (e.g., plural inside selectordinal),
     * the same argument name may appear multiple times. Each occurrence in the source
     * must have a corresponding occurrence in the target with a compatible type.
     *
     * @throws MissingComplexFormException If the target is missing a complex form that exists in the source.
     * @throws OutOfBoundsException
     */
    private function validateComplexFormCompatibility(): void
    {
        $sourceComplexArgs = $this->extractComplexArguments($this->sourceValidator);
        $targetComplexArgs = $this->extractComplexArguments($this->targetValidator);

        // Build a count map for each (argName, argType) pair in the target
        // so we can match each source occurrence to a target occurrence
        $targetCountMap = [];
        foreach ($targetComplexArgs as $targetArg) {
            $key = $targetArg->argName . '::' . $targetArg->argType->name;
            $targetCountMap[$key] = ($targetCountMap[$key] ?? 0) + 1;
        }

        // For each source complex arg, find a compatible match in the target
        foreach ($sourceComplexArgs as $sourceArg) {
            $argName = $sourceArg->argName;
            $sourceArgType = $sourceArg->argType;

            // Try to find a compatible match in the target count map
            $matchKey = $argName . '::' . $sourceArgType->name;

            if (isset($targetCountMap[$matchKey]) && $targetCountMap[$matchKey] > 0) {
                // Exact match found, consume one occurrence
                $targetCountMap[$matchKey]--;
                continue;
            }

            // No exact match found — check if the target has this arg name at all
            // to provide a better error message (missing vs mismatched type)
            $targetArgType = null;
            foreach ($targetComplexArgs as $targetArg) {
                if ($targetArg->argName === $argName) {
                    $targetArgType = $targetArg->argType;
                    break;
                }
            }

            throw new MissingComplexFormException(
                $argName,
                $sourceArgType,
                $targetArgType !== $sourceArgType ? $targetArgType : null,
                $this->sourceLocale,
                $this->targetLocale
            );
        }
    }


    /**
     * Extracts all complex arguments from a validated pattern.
     *
     * Returns a list (not a map) to correctly handle nested patterns where the same
     * argument name may appear multiple times (e.g., a plural inside each branch of a selectordinal).
     *
     * @param MessagePatternValidator $validator The validator containing the parsed pattern.
     * @return list<stdClass> List of complex argument descriptors, each with properties: string $argName, ArgType $argType.
     * @throws OutOfBoundsException
     */
    private function extractComplexArguments(MessagePatternValidator $validator): array
    {
        $complexArgs = [];

        // Get the parsed pattern from the validator
        // getPattern() triggers pattern initialization if not already done
        $pattern = $validator->getPattern();

        foreach ($pattern as $index => $part) {
            $partType = $part->getType();
            if ($partType !== TokenType::ARG_START) {
                continue;
            }

            $argType = $part->getArgType();

            // Check if it's a complex form
            if (!$argType->isComplexType()) {
                continue;
            }

            // Get the argument name (next part after ARG_START)
            $argNamePart = $pattern->getPart($index + 1);
            $nameType = $argNamePart->getType();

            if ($nameType === TokenType::ARG_NAME || $nameType === TokenType::ARG_NUMBER) {
                $entry = new stdClass();
                $entry->argName = $pattern->getSubstring($argNamePart);
                $entry->argType = $argType;
                $complexArgs[] = $entry;
            }
        }

        return $complexArgs;
    }

    /**
     * Checks if the source pattern contains complex syntax.
     *
     * @return bool True if source contains complex syntax (plural, select, choice, selectordinal).
     */
    public function sourceContainsComplexSyntax(): bool
    {
        return $this->sourceValidator->containsComplexSyntax();
    }

    /**
     * Checks if the target pattern contains complex syntax.
     *
     * @return bool True if target contains complex syntax (plural, select, choice, selectordinal).
     */
    public function targetContainsComplexSyntax(): bool
    {
        return $this->targetValidator->containsComplexSyntax();
    }

    /**
     * Gets the source locale.
     *
     * @return string
     */
    public function getSourceLocale(): string
    {
        return $this->sourceLocale;
    }

    /**
     * Gets the target locale.
     *
     * @return string
     */
    public function getTargetLocale(): string
    {
        return $this->targetLocale;
    }

    /**
     * Gets the source pattern validator.
     *
     * @return MessagePatternValidator
     */
    public function getSourceValidator(): MessagePatternValidator
    {
        return $this->sourceValidator;
    }

    /**
     * Gets the target pattern validator.
     *
     * @return MessagePatternValidator
     */
    public function getTargetValidator(): MessagePatternValidator
    {
        return $this->targetValidator;
    }

    /**
     * Retrieves the ICU MessageFormat pattern string for the source.
     *
     * @return string The source pattern string.
     */
    public function getSourcePattern(): string
    {
        return $this->sourcePattern;
    }

    /**
     * Retrieves the ICU MessageFormat pattern string for the target (translation).
     *
     * @return string The target pattern string.
     */
    public function getTargetPattern(): string
    {
        return $this->targetPattern;
    }

}



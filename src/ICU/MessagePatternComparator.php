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

use Matecat\ICU\Comparator\ComparisonResult;
use Matecat\ICU\Exceptions\InvalidArgumentException;
use Matecat\ICU\Exceptions\MissingComplexFormException;
use Matecat\ICU\Exceptions\OutOfBoundsException;
use Matecat\ICU\Plurals\PluralComplianceException;
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
 *
 * // Optionally validate plural compliance against CLDR rules
 * $result = $comparator->validate(validateSource: true, validateTarget: true);
 * // $result->sourceWarnings and $result->targetWarnings contain PluralComplianceWarning or null
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
     * Validates that complex forms in the source pattern exist in the target pattern,
     * and optionally validates plural/ordinal compliance against each locale's CLDR rules.
     *
     * Validation steps:
     * 1. If the source contains complex forms (plural, select, choice, selectordinal),
     *    the target must contain the same complex forms for the same arguments.
     * 2. If `$validateSource` is true, the source pattern is validated against its locale's
     *    CLDR plural/ordinal categories via {@see MessagePatternValidator::validatePluralCompliance()}.
     * 3. If `$validateTarget` is true, the target pattern is validated against its locale's
     *    CLDR plural/ordinal categories via {@see MessagePatternValidator::validatePluralCompliance()}.
     *
     * By default, no plural compliance validation is performed (both flags are false).
     *
     * @param bool $validateSource Whether to validate the source pattern's plural compliance. Default: false.
     * @param bool $validateTarget Whether to validate the target pattern's plural compliance. Default: false.
     *
     * @return ComparisonResult Contains `sourceWarnings` and `targetWarnings` properties
     *         (each PluralComplianceWarning|null). A side is null if validation was not requested or if no issues were found.
     * @throws MissingComplexFormException If the target is missing a complex form from the source.
     * @throws PluralComplianceException If a selector is not a valid CLDR category name.
     * @throws InvalidArgumentException If pattern syntax is invalid.
     * @throws OutOfBoundsException If pattern parsing exceeds limits.
     */
    public function validate(bool $validateSource = false, bool $validateTarget = false): ComparisonResult
    {
        // If the source contains complex syntax, ensure the target has matching complex forms
        if ($this->sourceValidator->containsComplexSyntax()) {
            $this->validateComplexFormCompatibility();
        }

        // Validate plural/ordinal compliance only when explicitly requested
        return new ComparisonResult(
            sourceWarnings: $validateSource ? $this->sourceValidator->validatePluralCompliance() : null,
            targetWarnings: $validateTarget ? $this->targetValidator->validatePluralCompliance() : null,
        );
    }

    /**
     * Validates that all complex forms in the source pattern exist in the target pattern.
     *
     * Complex forms include: PLURAL, SELECT, CHOICE, SELECTORDINAL
     *
     * When patterns contain nested complex forms (e.g., plural inside selectordinal),
     * the same argument name may appear multiple times — once per parent branch.
     * The number of parent branches legitimately differs across locales due to
     * different plural/ordinal rules (e.g., English selectordinal has 4 branches
     * while French has only 2). Therefore, we compare the **unique set** of
     * (argName, argType) pairs rather than raw occurrence counts.
     *
     * @throws MissingComplexFormException If the target is missing a complex form that exists in the source.
     * @throws OutOfBoundsException
     */
    private function validateComplexFormCompatibility(): void
    {
        // Collect all complex args (plural, select, etc.) from both sides
        $sourceComplexArgs = $this->extractComplexArguments($this->sourceValidator);
        $targetComplexArgs = $this->extractComplexArguments($this->targetValidator);

        // Index target args as a hash set keyed by "argName::argType" for O(1) lookup
        $targetKeySet = [];
        foreach ($targetComplexArgs as $targetArg) {
            $key = $targetArg->argName . '::' . $targetArg->argType->name;
            $targetKeySet[$key] = true;
        }

        // Track already-checked pairs to deduplicate (same arg can appear in multiple branches)
        $checkedKeys = [];
        foreach ($sourceComplexArgs as $sourceArg) {
            $argName = $sourceArg->argName;
            $sourceArgType = $sourceArg->argType;
            $matchKey = $argName . '::' . $sourceArgType->name;

            // Deduplicate: only check each unique (argName, argType) once
            if (isset($checkedKeys[$matchKey])) {
                continue;
            }
            $checkedKeys[$matchKey] = true;

            // Exact match found in target — this arg is satisfied
            if (isset($targetKeySet[$matchKey])) {
                continue;
            }

            // No match — look for the same arg name with a *different* type
            // to distinguish "completely missing" from "wrong type" in the error
            $targetArgType = null;
            foreach ($targetComplexArgs as $targetArg) {
                if ($targetArg->argName === $argName) {
                    $targetArgType = $targetArg->argType;
                    break;
                }
            }

            // Throw with context: missing entirely (targetArgType=null) or type mismatch
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



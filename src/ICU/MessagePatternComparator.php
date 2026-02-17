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
use Matecat\ICU\Tokens\ArgType;
use Matecat\ICU\Tokens\TokenType;

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
        $comparator = new self('', '', '', '');
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
        $comparator = new self('', '', '', '');
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
     * @throws MissingComplexFormException If the target is missing a complex form that exists in the source.
     * @throws OutOfBoundsException
     */
    private function validateComplexFormCompatibility(): void
    {
        $sourceComplexArgs = $this->extractComplexArguments($this->sourceValidator);
        $targetComplexArgs = $this->extractComplexArguments($this->targetValidator);

        foreach ($sourceComplexArgs as $argName => $sourceArgType) {
            if (!isset($targetComplexArgs[$argName])) {
                throw new MissingComplexFormException(
                    $argName,
                    $sourceArgType,
                    null,
                    $this->sourceLocale,
                    $this->targetLocale
                );
            }

            $targetArgType = $targetComplexArgs[$argName];

            // For PLURAL and SELECTORDINAL, they are considered compatible with each other
            // as they both handle numeric plural forms (just cardinal vs. ordinal)
            if (!$this->areComplexTypesCompatible($sourceArgType, $targetArgType)) {
                throw new MissingComplexFormException(
                    $argName,
                    $sourceArgType,
                    $targetArgType,
                    $this->sourceLocale,
                    $this->targetLocale
                );
            }
        }
    }

    /**
     * Checks if two complex argument types are compatible.
     *
     * @param ArgType $sourceType The source argument type.
     * @param ArgType $targetType The target argument type.
     * @return bool True if compatible, false otherwise.
     */
    private function areComplexTypesCompatible(ArgType $sourceType, ArgType $targetType): bool
    {
        // Exact match is always compatible
        if ($sourceType === $targetType) {
            return true;
        }

        // PLURAL and SELECTORDINAL are NOT interchangeable - they serve different purposes
        // Cardinal (plural) = "1 item, 2 items"
        // Ordinal (selectordinal) = "1st, 2nd, 3rd"
        // A translation should maintain the same semantic meaning

        return false;
    }

    /**
     * Extracts all complex arguments from a validated pattern.
     *
     * @param MessagePatternValidator $validator The validator containing the parsed pattern.
     * @return array<string, ArgType> Map of argument names to their complex types.
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
                $argName = $pattern->getSubstring($argNamePart);
                $complexArgs[$argName] = $argType;
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
}



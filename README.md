# matecat/icu-intl

[![Quality Gate Status](https://sonarcloud.io/api/project_badges/measure?project=matecat_icu-intl&metric=alert_status)](https://sonarcloud.io/summary/new_code?id=matecat_icu-intl)
[![Coverage](https://sonarcloud.io/api/project_badges/measure?project=matecat_icu-intl&metric=coverage)](https://sonarcloud.io/summary/new_code?id=matecat_icu-intl)
[![Reliability Rating](https://sonarcloud.io/api/project_badges/measure?project=matecat_icu-intl&metric=reliability_rating)](https://sonarcloud.io/summary/new_code?id=matecat_icu-intl)
[![Maintainability Rating](https://sonarcloud.io/api/project_badges/measure?project=matecat_icu-intl&metric=sqale_rating)](https://sonarcloud.io/summary/new_code?id=matecat_icu-intl)

----

A PHP port of ICU4J's MessagePattern parser with locale utilities. Parses ICU MessageFormat patterns into an inspectable
AST and provides locale data support for internationalization in PHP.

This package focuses on:

1. **ICU MessagePattern Parser** - Parsing ICU MessageFormat patterns into a precise token stream and AST (Abstract
   Syntax Tree), exposing the internal structure of messages (literals, arguments, selects, plurals, nested
   sub-messages, offsets, quoted text, etc.).
2. **Locale Utilities** - Access language data, plural rules, and locale validation for internationalization support.

This package does not provide locale-aware date/number formatting itself — it provides the pattern model and utilities
so you can build formatters or validators that interoperate with PHP's intl extension or other formatting libraries.

## Authors

- Domenico Lupinetti — [Ostico](https://github.com/Ostico) — domenico@translated.net / ostico@gmail.com

## Features

### ICU MessagePattern Parser

- Full tokenization of ICU MessageFormat patterns (braces, argument names/indexes, type specifiers, selectors, offsets,
  quoted text, etc.).
- AST representation of a parsed message pattern (message, message parts, argument placeholders, plural/select blocks,
  and their sub-messages).
- Utilities for traversing, validating, and reconstructing patterns.
- Error reporting with position information for malformed patterns.
- Mirrored behavior of ICU4J MessagePattern parsing logic (same parsing rules and edge-case handling).

### Locale Utilities

- **Languages**: Comprehensive language data including localized names, RTL detection, and language code validation.
- **Plural Rules**: CLDR-based plural rules for 140+ languages to determine the correct plural form for any number.
- **Language Domains**: Domain-specific language groupings for translation workflows.

## Installation

### Install via Composer:

```bash
composer require matecat/icu-intl
```

### Requirements

- PHP 8.3+
- ext-mbstring required
- ext-intl recommended for full formatting integration (not required for parsing)
- Composer for installation and development tasks

## Namespaces

| Namespace         | Description                                       |
|-------------------|---------------------------------------------------|
| `Matecat\ICU`     | ICU MessagePattern parser and AST classes         |
| `Matecat\Locales` | Language data, plural rules, and locale utilities |

## Quick Usage

### ICU MessagePattern Parser

#### Basic parse and inspect

```php
use Matecat\ICU\MessagePattern;
use Matecat\ICU\Tokens\TokenType;

$patternText = "You have {num, plural, offset:1 =0{no messages} =1{one message} other{# messages}} in {folder}.";
$pattern = new MessagePattern($patternText);

// Get AST and traverse (Token is a small DTO with type, start, length, value, limit)
$indent = '';
foreach ($pattern as $i => $part) {
    $explanation = '';

    $partString = (string)$part;
    $type = $part->getType();

    if ($type === TokenType::MSG_START) {
        $indent = str_pad('', $part->getValue() * 4, ' ', STR_PAD_LEFT);
    }

    if ($part->getLength() > 0) {
        $explanation .= '="' . $pattern->parts()->getSubstring($part) . '"';
    }

    if ($type->hasNumericValue()) {
        $explanation .= '=' . $pattern->parts()->getNumericValue($part);
    }

    printf("%2d: %s%s%s\n", $i, $indent, $partString, $explanation);

    if ($type === TokenType::MSG_LIMIT) {
        $nestingLevel = $part->getValue();
        if ($nestingLevel > 1) {
            $indent = str_pad('', ($nestingLevel - 1) * 4, ' ', STR_PAD_LEFT);
        } else {
            $indent = '';
        }
    }
}

/*
     0: MSG_START(0)@0
     1: ARG_START(PLURAL)@9="{"
     2: ARG_NAME(0)@10="num"
     3: ARG_INT(1)@30="1"=1
     4: ARG_SELECTOR(0)@32="=0"
     5: ARG_INT(0)@33="0"=0
     6:     MSG_START(1)@34="{"
     7:     MSG_LIMIT(1)@46="}"
     8: ARG_SELECTOR(0)@48="=1"
     9: ARG_INT(1)@49="1"=1
    10:     MSG_START(1)@50="{"
    11:     MSG_LIMIT(1)@62="}"
    12: ARG_SELECTOR(0)@64="other"
    13:     MSG_START(1)@69="{"
    14:     REPLACE_NUMBER(0)@70="#"
    15:     MSG_LIMIT(1)@80="}"
    16: ARG_LIMIT(PLURAL)@81="}"
    17: ARG_START(NONE)@86="{"
    18: ARG_NAME(0)@87="folder"
    19: ARG_LIMIT(NONE)@93="}"
    20: MSG_LIMIT(0)@95
 */
```

#### Simple placeholders

```php
$pattern = new MessagePattern("Hello {name}, welcome!");
/*
     0: MSG_START(0)@0
     1: ARG_START(NONE)@6="{"
     2: ARG_NAME(0)@7="name"
     3: ARG_LIMIT(NONE)@11="}"
     4: MSG_LIMIT(0)@22
*/
```

#### Plural example

```php
$pattern = new MessagePattern();
$pattern->parse("You have {count, plural, =0{no messages} one{# message} other{# messages}}.");
```

#### Nested selects and plurals

```php
$pattern = MessagePattern::parse("{gender, select, female{{num, plural, one{She has one file} other{She has # files}}} male{{num, plural, one{He has one file} other{He has # files}}} other{{num, plural, one{They have one file} other{They have # files}}}}");
/*
     0: MSG_START(0)@0
     1: ARG_START(SELECT)@0="{"
     2: ARG_NAME(0)@1="gender"
     3: ARG_SELECTOR(0)@17="female"
     4:     MSG_START(1)@23="{"
     5:     ARG_START(PLURAL)@24="{"
     ...
    42: ARG_LIMIT(SELECT)@219="}"
    43: MSG_LIMIT(0)@220
 */
```

### Locale Utilities

#### Languages

```php
use Matecat\Locales\Languages;

// Get all supported languages
$languages = Languages::getInstance();
$allLanguages = $languages->getLanguages();

// Get a specific language by RFC3066 code
$english = $languages->getLanguage('en-US');
echo $english['name'];        // "English US"
echo $english['localized'];   // "English"
echo $english['direction'];   // "ltr"
echo $english['plurals'];     // 2

// Check if a language is RTL
$isRtl = $languages->isRTL('ar-SA'); // true

// Get the number of plural forms for a language
$pluralCount = Languages::getPluralsCount('ru-RU'); // 3
```

#### Plural Rules

```php
use Matecat\ICU\Plurals\PluralRules;

// Get the plural form index for a number in a specific language
$form = PluralRules::getCardinalFormIndex('en', 1);    // 0 (singular)
$form = PluralRules::getCardinalFormIndex('en', 5);    // 1 (plural)

$form = PluralRules::getCardinalFormIndex('ru', 1);    // 0 (one)
$form = PluralRules::getCardinalFormIndex('ru', 2);    // 1 (few)
$form = PluralRules::getCardinalFormIndex('ru', 5);    // 2 (many)

// Get the CLDR plural category name for a number
$category = PluralRules::getCardinalCategoryName('en', 1);    // "one"
$category = PluralRules::getCardinalCategoryName('en', 5);    // "other"

$category = PluralRules::getCardinalCategoryName('ru', 1);    // "one"
$category = PluralRules::getCardinalCategoryName('ru', 2);    // "few"
$category = PluralRules::getCardinalCategoryName('ru', 5);    // "many"

$category = PluralRules::getCardinalCategoryName('ar', 0);    // "zero"
$category = PluralRules::getCardinalCategoryName('ar', 1);    // "one"
$category = PluralRules::getCardinalCategoryName('ar', 2);    // "two"
$category = PluralRules::getCardinalCategoryName('ar', 5);    // "few"
$category = PluralRules::getCardinalCategoryName('ar', 11);   // "many"
$category = PluralRules::getCardinalCategoryName('ar', 100);  // "other"

// Get all available plural categories for a language
$categories = PluralRules::getCardinalCategories('en');  // ["one", "other"]
$categories = PluralRules::getCardinalCategories('ru');  // ["one", "few", "many"]
$categories = PluralRules::getCardinalCategories('ar');  // ["zero", "one", "two", "few", "many", "other"]

// Use category constants for comparison
if (PluralRules::getCardinalCategoryName('en', $count) === PluralRules::CATEGORY_ONE) {
    echo "Singular form";
}
```

#### Plural Compliance Validation

The `MessagePatternValidator` validates that plural/selectordinal selectors comply with CLDR plural categories for a
given locale. It provides per-argument warnings for detailed feedback.

##### Simplified API (recommended)

The validator can work directly with a pattern string, without needing to create a `MessagePattern` object first:

```php
use Matecat\ICU\MessagePatternValidator;
use Matecat\ICU\Plurals\PluralComplianceException;

// Simplified API: just provide locale and pattern string
$validator = new MessagePatternValidator('en', '{count, plural, one{# item} other{# items}}');
$warning = $validator->validatePluralCompliance();

// Returns null when all categories are valid and complete
var_dump($warning); // null - 'one' and 'other' are valid for English

// Fluent API with setPatternString()
$warning = (new MessagePatternValidator('ru'))
    ->setPatternString('{count, plural, one{# item} other{# items}}')
    ->validatePluralCompliance();

// Returns a PluralComplianceWarning - Russian requires one/few/many/other
$warning->getMessagesAsString();           // Human-readable warning message
$warning->getArgumentWarnings();           // Array of PluralArgumentWarning objects
$warning->getAllMissingCategories();       // ['few', 'many']
$warning->getAllWrongLocaleSelectors();    // []

// Check if pattern contains complex syntax (plural, select, choice, selectordinal)
$validator = new MessagePatternValidator('en', '{count, plural, one{# file} other{# files}}');
$validator->containsComplexSyntax(); // true

$validator = new MessagePatternValidator('en', 'Hello {name}.');
$validator->containsComplexSyntax(); // false

// Check if pattern has valid ICU syntax
$validator = new MessagePatternValidator('en', '{count, plural, one{# item} other{# items}}');
$validator->isValidSyntax(); // true

$validator = new MessagePatternValidator('en', '{invalid');
$validator->isValidSyntax(); // false
$validator->getSyntaxException(); // "Unmatched '{' braces in message..."
```

##### Factory Method (with pre-parsed MessagePattern)

Use the `fromPattern()` factory method when you have a pre-parsed `MessagePattern` or want to validate the same pattern
against multiple locales:

```php
use Matecat\ICU\MessagePattern;
use Matecat\ICU\MessagePatternValidator;

// Parse an ICU message first
$pattern = new MessagePattern();
$pattern->parse('{count, plural, one{# item} other{# items}}');

// Create validator using factory method
$validator = MessagePatternValidator::fromPattern('en', $pattern);
$warning = $validator->validatePluralCompliance();

// Validate same pattern against multiple locales (reuses the parsed pattern)
$enValidator = MessagePatternValidator::fromPattern('en', $pattern);
$ruValidator = MessagePatternValidator::fromPattern('ru', $pattern);
$arValidator = MessagePatternValidator::fromPattern('ar', $pattern);

$enValidator->validatePluralCompliance(); // null - English only needs 'one', 'other'
$ruValidator->validatePluralCompliance(); // warning - Russian needs 'one', 'few', 'many', 'other'
```

##### Working with Warnings

```php
use Matecat\ICU\MessagePatternValidator;

// Access per-argument warnings
$validator = new MessagePatternValidator('ru', '{count, plural, one{# item} other{# items}}');
$warning = $validator->validatePluralCompliance();

foreach ($warning->getArgumentWarnings() as $argWarning) {
    echo $argWarning->argumentName;           // 'count'
    echo $argWarning->getArgumentTypeLabel(); // 'plural' or 'selectordinal'
    print_r($argWarning->expectedCategories); // ['one', 'few', 'many', 'other']
    print_r($argWarning->missingCategories);  // ['few', 'many']
    print_r($argWarning->foundSelectors);     // ['one', 'other']
    echo $argWarning->getMessageAsString();   // Detailed message for this argument
}
```

##### Exception Handling

```php
use Matecat\ICU\MessagePatternValidator;
use Matecat\ICU\Plurals\PluralComplianceException;

// Invalid CLDR categories throw an exception
$validator = new MessagePatternValidator('en', '{count, plural, some{# items} other{# items}}');

try {
    $validator->validatePluralCompliance();
} catch (PluralComplianceException $e) {
    echo $e->getMessage();
    // "Invalid selectors found for locale 'en': [some]. Found selectors: [some, other]. Valid CLDR categories are: [zero, one, two, few, many, other]."
    echo $e->locale;                  // 'en'
    print_r($e->invalidSelectors);    // ['some']
    print_r($e->foundSelectors);      // ['some', 'other']
    print_r($e->expectedCategories);  // ['zero', 'one', 'two', 'few', 'many', 'other']
}
```

##### More Examples

```php
use Matecat\ICU\MessagePatternValidator;

// Valid CLDR categories wrong for locale return warnings (not exceptions)
$validator = new MessagePatternValidator('en', '{count, plural, one{# item} few{# items} other{# items}}');
$warning = $validator->validatePluralCompliance();

$argWarning = $warning->getArgumentWarnings()[0];
print_r($argWarning->wrongLocaleSelectors); // ['few'] - valid CLDR but not for English

// Explicit numeric selectors (=0, =1, =2) are always valid but don't substitute category keywords
$validator = new MessagePatternValidator('en', '{count, plural, =0{none} =1{one item} other{# items}}');
$warning = $validator->validatePluralCompliance();

$argWarning = $warning->getArgumentWarnings()[0];
print_r($argWarning->numericSelectors);    // ['=0', '=1']
print_r($argWarning->missingCategories);   // ['one'] - =1 doesn't substitute for 'one' keyword

// Nested messages with multiple plural arguments get per-argument validation
$validator = new MessagePatternValidator(
    'en',
    "{gender, select, female{{n, plural, one{her item} other{her items}}} male{{n, plural, one{his item} other{his items}}}}"
);
$warning = $validator->validatePluralCompliance(); // null - all valid

// SelectOrdinal validation uses ordinal rules (different from cardinal)
$validator = new MessagePatternValidator('en', '{rank, selectordinal, one{#st} two{#nd} few{#rd} other{#th}}');
$warning = $validator->validatePluralCompliance(); // null - English ordinal uses one/two/few/other
```

##### Validation Behavior Summary

| Selector Type                                                 | Behavior                                    |
|---------------------------------------------------------------|---------------------------------------------|
| Non-existent CLDR category (e.g., 'some')                     | Throws `PluralComplianceException`          |
| Valid CLDR category wrong for locale (e.g., 'few' in English) | Returns warning in `wrongLocaleSelectors`   |
| Missing required category for locale                          | Returns warning in `missingCategories`      |
| Explicit numeric selector (=0, =1, etc.)                      | Always valid, tracked in `numericSelectors` |
| 'other' category                                              | Always valid (ICU requires it as fallback)  |

#### Pattern Comparison for Translations

The `MessagePatternComparator` validates that translated ICU patterns maintain the same complex forms (plural, select,
choice, selectordinal) as the source pattern. This ensures translations don't accidentally lose required argument
structures.

##### Basic Usage

```php
use Matecat\ICU\MessagePatternComparator;
use Matecat\ICU\Exceptions\MissingComplexFormException;

// Compare source and target patterns
$comparator = new MessagePatternComparator(
    'en-US',                                                    // source locale
    'fr-FR',                                                    // target locale
    '{count, plural, one{# item} other{# items}}',              // source pattern
    '{count, plural, one{# article} many{# articles} other{# articles}}'  // target pattern
);

// Validate - throws exception if target is missing complex forms from source
$comparator->validate();

// Optionally validate plural compliance against CLDR rules for source, target, or both
$result = $comparator->validate(validateSource: true, validateTarget: true);

// $result->sourceWarnings — PluralComplianceWarning|null for the source pattern
// $result->targetWarnings — PluralComplianceWarning|null for the target pattern
if ($result->targetWarnings !== null) {
    echo $result->targetWarnings->getMessagesAsString();
}
```

##### Factory Methods

Use factory methods when you have pre-configured validators or pre-parsed patterns:

```php
use Matecat\ICU\MessagePattern;
use Matecat\ICU\MessagePatternComparator;
use Matecat\ICU\MessagePatternValidator;

// From pre-configured validators
$sourceValidator = new MessagePatternValidator('en', '{count, plural, one{# item} other{# items}}');
$targetValidator = new MessagePatternValidator('fr', '{count, plural, one{# article} other{# articles}}');
$comparator = MessagePatternComparator::fromValidators($sourceValidator, $targetValidator);

// From pre-parsed patterns (useful for comparing same patterns against multiple locale pairs)
$sourcePattern = new MessagePattern('{count, plural, one{# item} other{# items}}');
$targetPattern = new MessagePattern('{count, plural, one{# article} other{# articles}}');
$comparator = MessagePatternComparator::fromPatterns('en', 'fr', $sourcePattern, $targetPattern);
```

##### Exception Handling

```php
use Matecat\ICU\MessagePatternComparator;
use Matecat\ICU\Exceptions\MissingComplexFormException;

// Missing plural form in target
$comparator = new MessagePatternComparator(
    'en', 'fr',
    '{count, plural, one{# item} other{# items}}',
    'Les articles {count}'  // Missing plural form!
);

try {
    $comparator->validate();
} catch (MissingComplexFormException $e) {
    echo $e->getMessage();
    // "Argument 'count' has complex form 'PLURAL' in source (en) but is missing in target (fr)."
    
    echo $e->argumentName;     // 'count'
    echo $e->sourceArgType;    // ArgType::PLURAL
    echo $e->targetArgType;    // null (missing)
    echo $e->sourceLocale;     // 'en'
    echo $e->targetLocale;     // 'fr'
}

// Mismatched complex form types
$comparator = new MessagePatternComparator(
    'en', 'fr',
    '{count, plural, one{# item} other{# items}}',
    '{count, select, one{un article} other{des articles}}'  // SELECT instead of PLURAL!
);

try {
    $comparator->validate();
} catch (MissingComplexFormException $e) {
    echo $e->getMessage();
    // "Argument 'count' has complex form 'PLURAL' in source (en) but has 'SELECT' in target (fr)."
    
    echo $e->targetArgType;    // ArgType::SELECT
}
```

##### Helper Methods

```php
use Matecat\ICU\MessagePatternComparator;

$comparator = new MessagePatternComparator(
    'en', 'fr',
    '{count, plural, one{# item} other{# items}}',
    '{count, plural, one{# article} other{# articles}}'
);

// Check if patterns contain complex syntax
$comparator->sourceContainsComplexSyntax();  // true
$comparator->targetContainsComplexSyntax();  // true

// Get locales
$comparator->getSourceLocale();  // 'en'
$comparator->getTargetLocale();  // 'fr'
```

##### Validation Rules

| Scenario                                                 | Behavior                                                                   |
|----------------------------------------------------------|----------------------------------------------------------------------------|
| Source has no complex forms                              | Validation passes (nothing to check)                                       |
| Target has same complex forms for same arguments         | Validation passes                                                          |
| Target is missing a complex form argument                | Throws `MissingComplexFormException`                                       |
| Target has different complex form type for same argument | Throws `MissingComplexFormException`                                       |
| PLURAL vs SELECTORDINAL                                  | Not interchangeable (different semantics)                                  |
| Target has extra complex forms                           | Allowed (no exception)                                                     |
| Plural compliance (`validateSource`/`validateTarget`)    | Off by default; when enabled, validates selectors against CLDR rules       |
| Invalid CLDR category (e.g., `foo`)                      | Throws `PluralComplianceException` (when compliance validation is enabled) |
| Wrong locale selector (e.g., `few` in English)           | Returns `PluralComplianceWarning` (when compliance validation is enabled)  |

#### Language Domains

```php
use Matecat\Locales\LanguageDomains;

// Get all language domains
$domains = LanguageDomains::getInstance();
$allDomains = $domains->getDomains();

// Get a specific domain
$domain = $domains->getDomain('technical');
```

### Notes about formatting

This library focuses on parsing and structure. If you want to format values using parsed plural/select patterns:

- Use PHP's Intl MessageFormatter (intl extension) for end-to-end ICU MessageFormat formatting (it accepts message
  strings and values).
- Or, implement a custom formatter that walks the AST and applies number/date formatting from ext-intl or other
  libraries.

## High-level API

### Matecat\ICU\MessagePattern

- `__construct(?string $pattern = null, int $apostropheMode = MessagePattern::APOSTROPHE_DOUBLE_OPTIONAL)`
- `parse(string $pattern): self`
- `parseChoiceStyle(string $pattern): self`
- `parsePluralStyle(string $pattern): self`
- `parseSelectStyle(string $pattern): self`
- `clear(): void`
- `clearPatternAndSetApostropheMode(int $mode): void`
- `getApostropheMode(): int`
- `getPatternString(): string`
- `parts(): PartAccessor` — returns the part accessor for querying parsed tokens
- `validateArgumentName(string $name): int` (static helper)
- `appendReducedApostrophes(string $s, int $start, int $limit, string &$out): void` (static helper)
- Implements `Iterator` to iterate parts.

### Matecat\ICU\Parsing\PartAccessor

Accessed via `$pattern->parts()`:

- `countParts(): int`
- `getPart(int $index): Part`
- `getPartType(int $index): Parts\TokenType`
- `getSubstring(Part $part): string`
- `partSubstringMatches(Part $part, string $s): bool`
- `getNumericValue(Part $part): float|int` (returns `MessagePattern::NO_NUMERIC_VALUE` when not numeric)
- `getPluralOffset(int $argStartIndex): float`
- `getPatternIndex(int $partIndex): int`
- `getLimitPartIndex(int $start): int`

### Matecat\ICU\Part

Represents a parsed token/part with accessors:

- `getType(): Parts\TokenType`
- `getIndex(): int`
- `getLength(): int`
- `getValue(): mixed`
- `getLimit(): int`
- `getArgType(): ?ArgType`
- `Part::MAX_LENGTH`
- `Part::MAX_VALUE`

### Matecat\ICU\Parts\TokenType (enum)

Token types used by the parser: `MSG_START`, `MSG_LIMIT`, `ARG_START`, `ARG_NAME`, `ARG_NUMBER`, `ARG_INT`,
`ARG_DOUBLE`, `ARG_TYPE`, `ARG_STYLE`, `ARG_SELECTOR`, `ARG_LIMIT`, `INSERT_CHAR`, `REPLACE_NUMBER`, `SKIP_SYNTAX`, etc.

### Matecat\ICU\ArgType (enum)

Argument classifications: `NONE`, `SIMPLE`, `CHOICE`, `PLURAL`, `SELECT`, `SELECTORDINAL`.

### Matecat\ICU\MessagePatternValidator

- `__construct(string $language = 'en-US', ?string $patternString = null)` - Creates a validator with the specified
  locale and optional pattern string
- `static fromPattern(string $language, MessagePattern $pattern): MessagePatternValidator` - Factory method to create a
  validator from a pre-parsed MessagePattern (useful for validating the same pattern against multiple locales)
- `setPatternString(string $patternString): static` - Sets the pattern string for lazy parsing, resets any stored
  parsing exception, and clears the internal pattern (fluent interface)
- `getPattern(): MessagePattern` - Returns the parsed MessagePattern instance (triggers parsing if not already done)
- `containsComplexSyntax(): bool` - Returns true if the pattern contains plural, select, choice, or selectordinal
- `isValidSyntax(): bool` - Returns true if the pattern string has valid ICU MessageFormat syntax, false if there were
  parsing errors
- `getSyntaxException(): ?string` - Returns the parsing exception message if the pattern has invalid syntax, null
  otherwise
- `validatePluralCompliance(): ?PluralComplianceWarning` - Validates if plural forms comply with the locale's expected
  categories. Returns null if valid, a warning object if there are issues. Throws `PluralComplianceException` for
  invalid CLDR categories, or `InvalidArgumentException`/`OutOfBoundsException` for parsing errors.

### Matecat\ICU\MessagePatternComparator

Compares source and target ICU MessageFormat patterns for translation validation. Ensures target patterns maintain the same complex forms (plural, select, choice, selectordinal) as source patterns.

- `__construct(string $sourceLocale, string $targetLocale, string $sourcePattern, string $targetPattern)` - Creates a comparator with source/target locales and pattern strings
- `static fromValidators(MessagePatternValidator $sourceValidator, MessagePatternValidator $targetValidator): MessagePatternComparator` - Factory method to create a comparator from pre-configured validators
- `static fromPatterns(string $sourceLocale, string $targetLocale, MessagePattern $sourcePattern, MessagePattern $targetPattern): MessagePatternComparator` - Factory method to create a comparator from pre-parsed patterns (useful for reusing parsed patterns across multiple locale comparisons)
- `validate(bool $validateSource = false, bool $validateTarget = false): ComparisonResult` - Validates that all complex forms in source exist in target. Optionally validates plural/ordinal compliance against CLDR rules for the source and/or target locale. Returns a `ComparisonResult` with `sourceWarnings` and `targetWarnings` properties (each `PluralComplianceWarning|null`, null if validation was not requested or no issues found). Throws `MissingComplexFormException` if target is missing complex forms or has mismatched types. Throws `PluralComplianceException` if a selector is not a valid CLDR category name.
- `sourceContainsComplexSyntax(): bool` - Returns true if source contains plural, select, choice, or selectordinal
- `targetContainsComplexSyntax(): bool` - Returns true if target contains plural, select, choice, or selectordinal
- `getSourceLocale(): string` - Returns the source locale
- `getTargetLocale(): string` - Returns the target locale
- `getSourceValidator(): MessagePatternValidator` - Returns the source pattern validator
- `getTargetValidator(): MessagePatternValidator` - Returns the target pattern validator

### Matecat\ICU\ComparisonResult (readonly)

Result object returned by `MessagePatternComparator::validate()`. Contains optional plural compliance warnings for source and target patterns.

- `__construct(?PluralComplianceWarning $sourceWarnings = null, ?PluralComplianceWarning $targetWarnings = null)`
- `sourceWarnings: ?PluralComplianceWarning` - Plural compliance warnings for the source pattern, or null if validation was not requested or no issues were found
- `targetWarnings: ?PluralComplianceWarning` - Plural compliance warnings for the target pattern, or null if validation was not requested or no issues were found
- `hasWarnings(): bool` - Returns true if either side has warnings

### Matecat\ICU\Plurals\PluralComplianceWarning (readonly)

Returned when plural selectors have compliance issues that don't warrant an exception.

- `__construct(array $argumentWarnings)`
- `getArgumentWarnings(): array<PluralArgumentWarning>` - Get all argument-level warnings
- `getAllMissingCategories(): array<string>` - Get all missing categories across all arguments
- `getAllWrongLocaleSelectors(): array<string>` - Get all wrong locale selectors across all arguments
- `getMessages(): array<string>` - Get all warning messages as an array
- `getMessagesAsString(): string` - Human-readable warning message (joins all messages with newlines)
- Implements `Stringable` interface

### Matecat\ICU\Plurals\PluralArgumentWarning (readonly)

Detailed warning information for a single plural/selectordinal argument.

- `argumentName: string` - The argument name (e.g., 'count', 'num_guests')
- `argumentType: ArgType` - The argument type (PLURAL or SELECTORDINAL)
- `expectedCategories: array<string>` - Valid CLDR categories for this argument type and locale
- `foundSelectors: array<string>` - All selectors found in this argument
- `missingCategories: array<string>` - Expected categories not found
- `numericSelectors: array<string>` - Explicit numeric selectors found (e.g., =0, =1)
- `wrongLocaleSelectors: array<string>` - Valid CLDR categories that don't apply to this locale
- `getArgumentTypeLabel(): string` - Returns 'plural' or 'selectordinal'
- `getMessage(): string` - Human-readable message for this argument
- Implements `Stringable` interface

### Matecat\ICU\Plurals\PluralComplianceException

Thrown when a selector is not a valid CLDR category name (e.g., 'some', 'foo').

- `expectedCategories: array<string>` - Valid CLDR categories
- `foundSelectors: array<string>` - All selectors found in the message
- `invalidSelectors: array<string>` - Non-existent CLDR category names
- `missingCategories: array<string>` - (Always empty, for interface compatibility)

### Matecat\ICU\Exceptions\MissingComplexFormException

Thrown when a target pattern is missing a complex form that exists in the source pattern, or when the complex form type doesn't match.

- `argumentName: string` - The name of the argument with the missing/mismatched complex form
- `sourceArgType: ArgType` - The argument type in the source pattern (PLURAL, SELECT, CHOICE, SELECTORDINAL)
- `targetArgType: ?ArgType` - The argument type in the target pattern (null if argument is missing entirely)
- `sourceLocale: string` - The source locale
- `targetLocale: string` - The target locale

### Matecat\Locales\Languages

- `getInstance(): Languages` (singleton)
- `getLanguages(): array`
- `getLanguage(string $rfc3066code): ?array`
- `isRTL(string $rfc3066code): bool`
- `getPluralsCount(string $rfc3066code): int` (static)

### Matecat\ICU\Plurals\PluralRules

- `getCardinalFormIndex(string $locale, int $n): int` (static) - Returns the plural form index for a number
- `getCardinalCategoryName(string $locale, int $n): string` (static) - Returns the CLDR category name ('zero', 'one', '
  two', 'few', 'many', 'other')
- `getCardinalCategories(string $locale): array` (static) - Returns all available cardinal category names for a locale
- `getOrdinalCategories(string $locale): array` (static) - Returns all available ordinal category names for a locale
- `getOrdinalFormIndex(string $locale, int $n): int` (static) - Returns the ordinal form index for a number
- `isValidCategory(string $selector): bool` (static) - Checks if a selector is a valid CLDR category name

#### Constants

- `CATEGORY_ZERO` = 'zero'
- `CATEGORY_ONE` = 'one'
- `CATEGORY_TWO` = 'two'
- `CATEGORY_FEW` = 'few'
- `CATEGORY_MANY` = 'many'
- `CATEGORY_OTHER` = 'other'
- `VALID_CATEGORIES` = ['zero', 'one', 'two', 'few', 'many', 'other']

### Matecat\Locales\LanguageDomains

- `getInstance(): LanguageDomains` (singleton)
- `getDomains(): array`
- `getDomain(string $domainKey): ?array`

## Exceptions

- `InvalidArgumentException` for syntax errors
- `OutOfBoundsException` for excessive sizes/nesting and indexing errors
- `Matecat\Locales\InvalidLanguageException` for invalid language codes
- `Matecat\ICU\Plurals\PluralComplianceException` for invalid CLDR plural category names
- `Matecat\ICU\Exceptions\MissingComplexFormException` for missing or mismatched complex forms in pattern comparisons

## Development

### Run tests

```bash
vendor/bin/phpunit
```

or

```bash
composer test
```

### Run static analysis

```bash
vendor/bin/phpstan analyze
```

## Project Structure

- ICU Parser core: `src/ICU/MessagePattern.php`
- Pattern Validator: `src/ICU/MessagePatternValidator.php`
- Pattern Comparator: `src/ICU/MessagePatternComparator.php`
- Part/token model: `src/ICU/Tokens/Part.php`
- Token types: `src/ICU/Tokens/TokenType.php`
- Argument types: `src/ICU/Tokens/ArgType.php`
- Custom Exceptions: `src/ICU/Exceptions/InvalidArgumentException.php`, `src/ICU/Exceptions/OutOfBoundsException.php`, `src/ICU/Exceptions/MissingComplexFormException.php`
- Plural Rules: `src/ICU/Plurals/PluralRules.php`
- Plural Compliance Warning: `src/ICU/Plurals/PluralComplianceWarning.php`
- Plural Argument Warning: `src/ICU/Plurals/PluralArgumentWarning.php`
- Plural Compliance Exception: `src/ICU/Plurals/PluralComplianceException.php`
- Languages: `src/Locales/Languages.php`
- Language Domains: `src/Locales/LanguageDomains.php`
- Tests: `tests/`

## License

This project is licensed under the GNU Lesser General Public License v3.0 or later.

- SPDX-License-Identifier: `LGPL-3.0-or-later`
- See the `LICENSE` file for the full license text.
- More info: https://www.gnu.org/licenses/lgpl-3.0.html

### Credits

This PHP port mirrors ideas from ICU4J MessagePattern.java.

- See `LICENSE` for project license and attribution to ICU4J sources: https://github.com/unicode-org/icu
- Plural rules based on CLDR (Unicode Common Locale Data Repository): https://cldr.unicode.org/

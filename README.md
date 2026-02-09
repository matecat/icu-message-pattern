# matecat/icu-intl

[![Quality Gate Status](https://sonarcloud.io/api/project_badges/measure?project=matecat_icu-intl&metric=alert_status)](https://sonarcloud.io/summary/new_code?id=matecat_icu-intl)
[![Coverage](https://sonarcloud.io/api/project_badges/measure?project=matecat_icu-intl&metric=coverage)](https://sonarcloud.io/summary/new_code?id=matecat_icu-intl)
[![Reliability Rating](https://sonarcloud.io/api/project_badges/measure?project=matecat_icu-intl&metric=reliability_rating)](https://sonarcloud.io/summary/new_code?id=matecat_icu-intl)
[![Maintainability Rating](https://sonarcloud.io/api/project_badges/measure?project=matecat_icu-intl&metric=sqale_rating)](https://sonarcloud.io/summary/new_code?id=matecat_icu-intl)

----

A PHP port of ICU4J's MessagePattern parser with locale utilities. Parses ICU MessageFormat patterns into an inspectable AST and provides locale data support for internationalization in PHP.

This package focuses on:
1. **ICU MessagePattern Parser** - Parsing ICU MessageFormat patterns into a precise token stream and AST (Abstract Syntax Tree), exposing the internal structure of messages (literals, arguments, selects, plurals, nested sub-messages, offsets, quoted text, etc.).
2. **Locale Utilities** - Access language data, plural rules, and locale validation for internationalization support.

This package does not provide locale-aware date/number formatting itself — it provides the pattern model and utilities so you can build formatters or validators that interoperate with PHP's intl extension or other formatting libraries.

## Authors
- Domenico Lupinetti — [Ostico](https://github.com/Ostico) — domenico@translated.net / ostico@gmail.com

## Features

### ICU MessagePattern Parser
- Full tokenization of ICU MessageFormat patterns (braces, argument names/indexes, type specifiers, selectors, offsets, quoted text, etc.).
- AST representation of a parsed message pattern (message, message parts, argument placeholders, plural/select blocks, and their sub-messages).
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
        $explanation .= '="' . $pattern->getSubstring($part) . '"';
    }

    if ($type->hasNumericValue()) {
        $explanation .= '=' . $pattern->getNumericValue($part);
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

The `MessagePatternAnalyzer` validates that plural/selectordinal selectors comply with CLDR plural categories for a given locale. It provides per-argument warnings for detailed feedback.

```php
use Matecat\ICU\MessagePattern;
use Matecat\ICU\MessagePatternAnalyzer;
use Matecat\ICU\Plurals\PluralComplianceException;

// Parse an ICU message
$pattern = new MessagePattern();
$pattern->parse('{count, plural, one{# item} other{# items}}');

// Validate plural compliance for English
$analyzer = new MessagePatternAnalyzer($pattern, 'en');
$warning = $analyzer->validatePluralCompliance();

// Returns null when all categories are valid and complete
var_dump($warning); // null - 'one' and 'other' are valid for English

// Check with Russian locale - Russian requires one/few/many/other
$analyzer = new MessagePatternAnalyzer($pattern, 'ru');
$warning = $analyzer->validatePluralCompliance();

// Returns a PluralComplianceWarning with per-argument details
$warning->getMessage();                    // Human-readable warning message
$warning->getArgumentWarnings();           // Array of PluralArgumentWarning objects
$warning->getAllMissingCategories();       // ['few', 'many']
$warning->getAllWrongLocaleSelectors();    // []

// Access per-argument warnings
foreach ($warning->getArgumentWarnings() as $argWarning) {
    echo $argWarning->argumentName;        // 'count'
    echo $argWarning->getArgumentTypeLabel(); // 'plural' or 'selectordinal'
    print_r($argWarning->expectedCategories); // ['one', 'few', 'many', 'other']
    print_r($argWarning->missingCategories);  // ['few', 'many']
    print_r($argWarning->foundSelectors);     // ['one', 'other']
    echo $argWarning->getMessageAsString();         // Detailed message for this argument
}

// Invalid CLDR categories throw an exception
$pattern->parse('{count, plural, some{# items} other{# items}}'); // 'some' is not a valid CLDR category
$analyzer = new MessagePatternAnalyzer($pattern, 'en');

try {
    $analyzer->validatePluralCompliance();
} catch (PluralComplianceException $e) {
    echo $e->getMessage();
    // "Invalid selectors found for locale 'en': [some]. Found selectors: [one, some, other]. Valid CLDR categories are: [zero, one, two, few, many, other]."
    // If missingCategories is provided: "...Missing required categories: [one, few]. Valid CLDR categories are: [zero, one, two, few, many, other]."
    echo $e->locale;                  // 'en'
    print_r($e->invalidSelectors);    // ['some']
    print_r($e->foundSelectors);      // ['one', 'some', 'other']
    print_r($e->missingCategories);   // [] (typically empty for this exception, used in warnings)
    print_r($e->expectedCategories);  // ['zero', 'one', 'two', 'few', 'many', 'other']
}

// Valid CLDR categories wrong for locale return warnings (not exceptions)
$pattern->parse('{count, plural, one{# item} few{# items} other{# items}}');
$analyzer = new MessagePatternAnalyzer($pattern, 'en'); // English doesn't use 'few'
$warning = $analyzer->validatePluralCompliance();

$argWarning = $warning->getArgumentWarnings()[0];
print_r($argWarning->wrongLocaleSelectors); // ['few'] - valid CLDR but not for English

// Explicit numeric selectors (=0, =1, =2) are always valid but don't substitute category keywords
$pattern->parse('{count, plural, =0{none} =1{one item} other{# items}}');
$analyzer = new MessagePatternAnalyzer($pattern, 'en');
$warning = $analyzer->validatePluralCompliance();

$argWarning = $warning->getArgumentWarnings()[0];
print_r($argWarning->numericSelectors);    // ['=0', '=1']
print_r($argWarning->missingCategories);   // ['one'] - =1 doesn't substitute for 'one' keyword

// Nested messages with multiple plural arguments get per-argument validation
$pattern->parse("{gender, select, female{{n, plural, one{her item} other{her items}}} male{{n, plural, one{his item} other{his items}}}}");
$analyzer = new MessagePatternAnalyzer($pattern, 'en');
$warning = $analyzer->validatePluralCompliance(); // null - all valid

// SelectOrdinal validation uses ordinal rules (different from cardinal)
$pattern->parse('{rank, selectordinal, one{#st} two{#nd} few{#rd} other{#th}}');
$analyzer = new MessagePatternAnalyzer($pattern, 'en');
$warning = $analyzer->validatePluralCompliance(); // null - English ordinal uses one/two/few/other
```

##### Validation Behavior Summary

| Selector Type | Behavior |
|---------------|----------|
| Non-existent CLDR category (e.g., 'some') | Throws `PluralComplianceException` |
| Valid CLDR category wrong for locale (e.g., 'few' in English) | Returns warning in `wrongLocaleSelectors` |
| Missing required category for locale | Returns warning in `missingCategories` |
| Explicit numeric selector (=0, =1, etc.) | Always valid, tracked in `numericSelectors` |
| 'other' category | Always valid (ICU requires it as fallback) |

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
- Use PHP's Intl MessageFormatter (intl extension) for end-to-end ICU MessageFormat formatting (it accepts message strings and values).
- Or, implement a custom formatter that walks the AST and applies number/date formatting from ext-intl or other libraries.

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
- `countParts(): int`
- `getPart(int $index): Part`
- `getPartType(int $index): Parts\TokenType`
- `getSubstring(Part $part): string`
- `getNumericValue(Part $part): float|int` (returns `MessagePattern::NO_NUMERIC_VALUE` when not numeric)
- `getPluralOffset(int $argStartIndex): float`
- `validateArgumentName(string $name): int` (static helper)
- `appendReducedApostrophes(string $s, int $start, int $limit, string &$out): void` (static helper)
- Implements `Iterator` to iterate parts.

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
Token types used by the parser: `MSG_START`, `MSG_LIMIT`, `ARG_START`, `ARG_NAME`, `ARG_NUMBER`, `ARG_INT`, `ARG_DOUBLE`, `ARG_TYPE`, `ARG_STYLE`, `ARG_SELECTOR`, `ARG_LIMIT`, `INSERT_CHAR`, `REPLACE_NUMBER`, `SKIP_SYNTAX`, etc.

### Matecat\ICU\ArgType (enum)
Argument classifications: `NONE`, `SIMPLE`, `CHOICE`, `PLURAL`, `SELECT`, `SELECTORDINAL`.

### Matecat\ICU\MessagePatternAnalyzer
- `__construct(MessagePattern $pattern, string $language = 'en-US')`
- `containsComplexSyntax(): bool` - Returns true if the pattern contains plural, select, choice, or selectordinal
- `validatePluralCompliance(): ?PluralComplianceWarning` - Validates if plural forms comply with the locale's expected categories. Returns null if valid, a warning object if there are issues, or throws `PluralComplianceException` for invalid CLDR categories.

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

### Matecat\Locales\Languages
- `getInstance(): Languages` (singleton)
- `getLanguages(): array`
- `getLanguage(string $rfc3066code): ?array`
- `isRTL(string $rfc3066code): bool`
- `getPluralsCount(string $rfc3066code): int` (static)

### Matecat\ICU\Plurals\PluralRules
- `getCardinalFormIndex(string $locale, int $n): int` (static) - Returns the plural form index for a number
- `getCardinalCategoryName(string $locale, int $n): string` (static) - Returns the CLDR category name ('zero', 'one', 'two', 'few', 'many', 'other')
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
- Pattern Analyzer: `src/ICU/MessagePatternAnalyzer.php`
- Part/token model: `src/ICU/Tokens/Part.php`
- Token types: `src/ICU/Tokens/TokenType.php`
- Argument types: `src/ICU/Tokens/ArgType.php`
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

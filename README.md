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

| Namespace | Description |
|-----------|-------------|
| `Matecat\ICU` | ICU MessagePattern parser and AST classes |
| `Matecat\Locales` | Language data, plural rules, and locale utilities |

## Quick Usage

### ICU MessagePattern Parser

#### Basic parse and inspect

```php
use Matecat\ICU\MessagePattern;
use Matecat\ICU\Parts\TokenType;

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
use Matecat\Locales\PluralRules\PluralRules;

// Get the plural form index for a number in a specific language
$form = PluralRules::calculate('en', 1);    // 0 (singular)
$form = PluralRules::calculate('en', 5);    // 1 (plural)

$form = PluralRules::calculate('ru', 1);    // 0 (one)
$form = PluralRules::calculate('ru', 2);    // 1 (few)
$form = PluralRules::calculate('ru', 5);    // 2 (many)

// Get the number of plural forms for a language
$count = PluralRules::getPluralsCount('ar'); // 6
$count = PluralRules::getPluralsCount('ja'); // 1
```

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

### Matecat\Locales\Languages
- `getInstance(): Languages` (singleton)
- `getLanguages(): array`
- `getLanguage(string $rfc3066code): ?array`
- `isRTL(string $rfc3066code): bool`
- `getPluralsCount(string $rfc3066code): int` (static)

### Matecat\Locales\PluralRules\PluralRules
- `calculate(string $langCode, int|float $number): int` (static)
- `getPluralsCount(string $langCode): int` (static)

### Matecat\Locales\LanguageDomains
- `getInstance(): LanguageDomains` (singleton)
- `getDomains(): array`
- `getDomain(string $domainKey): ?array`

## Exceptions
- `InvalidArgumentException` for syntax errors
- `OutOfBoundsException` for excessive sizes/nesting and indexing errors
- `Matecat\Locales\InvalidLanguageException` for invalid language codes

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
- Part/token model: `src/ICU/Part.php`
- Token types: `src/ICU/Parts/TokenType.php`
- Argument types: `src/ICU/ArgType.php`
- Languages: `src/Locales/Languages.php`
- Plural Rules: `src/Locales/PluralRules/PluralRules.php`
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

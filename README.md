# ICU MessagePattern (PHP)

A PHP port of the core MessagePattern parsing logic from ICU4J/ICU4C (see: [MessagePattern.java](https://github.com/unicode-org/icu/blob/f1b3db8ecd39d5b3a6eff4d5641b176c7f914dfb/icu4j/main/core/src/main/java/com/ibm/icu/text/MessagePattern.java)).  
This package focuses on parsing ICU MessageFormat patterns into a precise token stream and AST (Abstract Syntax Tree), exposing the internal structure of messages (literals, arguments, selects, plurals, nested sub-messages, offsets, quoted text, etc.).

This package does not provide locale-aware date/number formatting itself — it provides the pattern model and utilities so you can build formatters or validators that interoperate with PHP's intl extension or other formatting libraries.

## Authors
- Domenico Lupinetti (ostico) — https://github.com/Ostico — domenico@translated.net / ostico@gmail.com

## Features
- Full tokenization of ICU MessageFormat patterns (braces, argument names/indexes, type specifiers, selectors, offsets, quoted text, etc.).
- AST representation of a parsed message pattern (message, message parts, argument placeholders, plural/select blocks, and their sub-messages).
- Utilities for traversing, validating, and reconstructing patterns.
- Error reporting with position information for malformed patterns.
- Mirrored behavior of ICU4J MessagePattern parsing logic (same parsing rules and edge-case handling).

## Installation
Replace your-vendor/icu-messagepattern with your package vendor name and the package name once published.

### Install via Composer:
composer require your-vendor/icu-messagepattern

#### Requirements
- PHP 8.3+
- ext-intl recommended for full formatting integration (not required for parsing)
- Composer for installation and development tasks

## Quick usage

### Basic parse and inspect

```php
use Matecat\ICU\MessagePattern;use Matecat\ICU\Parts\TokenType;

$patternText = "You have {num, plural, offset:1 =0{no messages} =1{one message} other{# messages}} in {folder}.";
$pattern = new MessagePattern($patternText);

// Get AST and traverse (Token is a small DTO with type, start, length, value, limit)
$indent = '';
foreach ($parse as $i => $part) {
    $explanation = '';

    $partString = (string)$part;
    $type = $part->getType();

    if ($type === TokenType::MSG_START) {
        $indent = str_pad('', $part->getValue() * 4, ' ', STR_PAD_LEFT);
    }

    if ($part->getLength() > 0) {
        $explanation .= '="' . $parse->getSubstring($part) . '"';
    }

    if ($type->hasNumericValue()) {
        $explanation .= '=' . $parse->getNumericValue($part);
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

### Notes about formatting

This library focuses on parsing and structure. If you want to format values using parsed plural/select patterns:
- Use PHP's Intl MessageFormatter (intl extension) for end-to-end ICU MessageFormat formatting (it accepts message strings and values).
- Or, implement a custom formatter that walks the AST and applies number/date formatting from ext-intl or other libraries.

## Examples

1) Simple placeholders
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

2) Plural example
```php
   $pattern = new MessagePattern();
   $pattern->parse("You have {count, plural, =0{no messages} one{# message} other{# messages}}."); // parse method alternative syntax
```

3) Nested selects and plurals
```php
   $pattern = MessagePattern::parse("{gender, select, female{{num, plural, one{She has one file} other{She has # files}}} male{{num, plural, one{He has one file} other{He has # files}}} other{{num, plural, one{They have one file} other{They have # files}}}}");
/*
     0: MSG_START(0)@0
     1: ARG_START(SELECT)@0="{"
     2: ARG_NAME(0)@1="gender"
     3: ARG_SELECTOR(0)@17="female"
     4:     MSG_START(1)@23="{"
     5:     ARG_START(PLURAL)@24="{"
     6:     ARG_NAME(0)@25="num"
     7:     ARG_SELECTOR(0)@38="one"
     8:         MSG_START(2)@41="{"
     9:         MSG_LIMIT(2)@58="}"
    10:     ARG_SELECTOR(0)@60="other"
    11:         MSG_START(2)@65="{"
    12:         REPLACE_NUMBER(0)@74="#"
    13:         MSG_LIMIT(2)@81="}"
    14:     ARG_LIMIT(PLURAL)@82="}"
    15:     MSG_LIMIT(1)@83="}"
    16: ARG_SELECTOR(0)@85="male"
    17:     MSG_START(1)@89="{"
    18:     ARG_START(PLURAL)@90="{"
    19:     ARG_NAME(0)@91="num"
    20:     ARG_SELECTOR(0)@104="one"
    21:         MSG_START(2)@107="{"
    22:         MSG_LIMIT(2)@123="}"
    23:     ARG_SELECTOR(0)@125="other"
    24:         MSG_START(2)@130="{"
    25:         REPLACE_NUMBER(0)@138="#"
    26:         MSG_LIMIT(2)@145="}"
    27:     ARG_LIMIT(PLURAL)@146="}"
    28:     MSG_LIMIT(1)@147="}"
    29: ARG_SELECTOR(0)@149="other"
    30:     MSG_START(1)@154="{"
    31:     ARG_START(PLURAL)@155="{"
    32:     ARG_NAME(0)@156="num"
    33:     ARG_SELECTOR(0)@169="one"
    34:         MSG_START(2)@172="{"
    35:         MSG_LIMIT(2)@191="}"
    36:     ARG_SELECTOR(0)@193="other"
    37:         MSG_START(2)@198="{"
    38:         REPLACE_NUMBER(0)@209="#"
    39:         MSG_LIMIT(2)@216="}"
    40:     ARG_LIMIT(PLURAL)@217="}"
    41:     MSG_LIMIT(1)@218="}"
    42: ARG_LIMIT(SELECT)@219="}"
    43: MSG_LIMIT(0)@220
 */
```

## High-level API

- `Utils\ICU\MessagePattern`
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

- `Utils\ICU\Part`
  - Represents a parsed token/part.
  - Accessors: 
    - `getType(): Parts\TokenType`, 
    - `getIndex(): int`, 
    - `getLength(): int`, 
    - `getValue(): mixed`, 
    - `getLimit(): int`, 
    - `getArgType(): ?ArgType`
    - `Part::MAX_LENGTH`, 
    - `Part::MAX_VALUE` 

- `Utils\ICU\Parts\TokenType` (enum)
  - Token types used by the parser, for example: `MSG_START`, `MSG_LIMIT`, `ARG_START`, `ARG_NAME`, `ARG_NUMBER`, `ARG_INT`, `ARG_DOUBLE`, `ARG_TYPE`, `ARG_STYLE`, `ARG_SELECTOR`, `ARG_LIMIT`, `INSERT_CHAR`, `REPLACE_NUMBER`, `SKIP_SYNTAX`, etc.
  - Token types expose whether they carry a numeric value.

- `Utils\ICU\ArgType` (enum)
  - Argument classifications such as `NONE`, `SIMPLE`, `CHOICE`, `PLURAL`, `SELECT`, `SELECTORDINAL`.

## Exceptions
The parser throws standard PHP exceptions used by the tests and implementation:
- `InvalidArgumentException` for syntax errors
- `OutOfBoundsException` for excessive sizes/nesting and indexing errors

## Differences & limitations
- This project is a parser only. It does not provide ICU's locale-aware formatting. Use `ext-intl` or another formatter to produce localized output.
- Complex style strings (skeletons, custom number/date patterns) are captured as raw `ARG_TYPE`/`ARG_STYLE` substrings and must be interpreted by a formatting layer.

## Tests
- Unit tests use PHPUnit.
- Run tests: `vendor/bin/phpunit` or `composer test`.

## Where to find parser code and tests
- Parser core: `lib/Utils/ICU/MessagePattern.php`
- Part/token model: `lib/Utils/ICU/Part.php`
- Token types: `lib/Utils/ICU/Parts/TokenType.php`
- Argument types: `lib/Utils/ICU/ArgType.php`
- Tests: `tests/unit/Utils/ICU/MessagePatternTest.php`

#### Search tips:
- `git grep -n "class MessagePattern\|class Part\|TokenType\|ArgType"`
- Use PhpStorm or an IDE with advanced code navigation features (e.g., go to symbol/class): Navigate > Symbol / Class and search for `MessagePattern`, `Part`, `TokenType`, `ArgType`.


## License
This project is licensed under the GNU Lesser General Public License v3.0 or later.

- SPDX-License-Identifier: `LGPL-3.0-or-later`
- See the `LICENSE` file for the full license text.
- More info: https://www.gnu.org/licenses/lgpl-3.0.html

### Credits
This PHP port mirrors ideas from ICU4J MessagePattern.java.
- See `LICENSE` for project license and attribution to ICU4J sources: https://github.com/unicode-org/icu (referenced commit in project credits).
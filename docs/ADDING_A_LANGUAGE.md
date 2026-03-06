# Adding a New Language — Step-by-Step Guide

This document explains how to add a new language to the library and rebuild all
the JSON resource files that depend on it.

---

## Overview

The library uses the following file hierarchy:

```
src/resources/                          ← runtime files (used by the library at execution time)
├── supported_langs.json                ← master list of supported languages
├── languageDomains.json                ← domain-specific language groupings
├── pluralRules.json                    ← built output (generated, do not edit manually)
└── build/                              ← build-time inputs (used only to generate pluralRules.json)
    ├── cldr49_plural_rules.json        ← CLDR 49 plural rules (downloaded from Unicode)
    ├── cardinal_rules_human.json       ← CLDR cardinal rule → human-readable description
    ├── ordinal_rules_human.json        ← CLDR ordinal rule → human-readable description
    ├── nonCldrParentMap.json           ← non-CLDR locale → CLDR parent mapping
    └── pluralRulesOverrides.json       ← per-locale overrides (localized examples, custom descriptions)

src/ICU/Plurals/PluralRules.php         ← plural rule evaluation engine ($rulesMap)
src/Locales/PluralRulesBuilder.php      ← builds pluralRules.json from all build inputs
```

The data flows like this:

```
supported_langs.json ──┐
PluralRules.php ───────┤
cldr49_plural_rules.json ──┤
cardinal_rules_human.json ─┤      PluralRulesBuilder
ordinal_rules_human.json ──┤  ──────────────────────→  pluralRules.json
nonCldrParentMap.json ─────┤
pluralRulesOverrides.json ─┘
```

---

## Step 1 — Add the language to `supported_langs.json`

**File:** `src/resources/supported_langs.json`

Add a new entry to the `langs` array. Example for Wolof:

```json
{
  "localized": [
    {
      "wo": "Wolof"
    }
  ],
  "isocode": "wo",
  "enabled": true,
  "rtl": false,
  "bcp47code": "wo-SN",
  "ocr": {
    "supported": false,
    "not_supported_or_rtl": true
  }
}
```

**Fields:**

| Field       | Description                                                                            |
|-------------|----------------------------------------------------------------------------------------|
| `localized` | Array of `{langCode: displayName}` pairs. At minimum, include the language's own code. |
| `isocode`   | ISO 639-1 or 639-3 code (lowercase).                                                   |
| `enabled`   | `true` to make the language available.                                                 |
| `rtl`       | `true` for right-to-left scripts (Arabic, Hebrew, etc.).                               |
| `bcp47code` | BCP 47 / RFC 5646 tag (e.g., `wo-SN`).                                                 |
| `ocr`       | OCR support flags.                                                                     |

---

## Step 2 — Add the language to `PluralRules.php`

**File:** `src/ICU/Plurals/PluralRules.php`

Add an entry to the `$rulesMap` array. The map is organized alphabetically.

```php
'wo' => ['cardinal' => 0, 'ordinal' => 0],    // Wolof
```

Each entry has two values:

- **`cardinal`** — the rule group number that determines how the language selects
  plural forms for cardinal numbers (e.g., "1 cat", "2 cats", "5 cats")
- **`ordinal`** — the rule group number that determines how the language selects
  ordinal forms (e.g., "1st", "2nd", "3rd")

These are **not** arbitrary numbers — they refer to specific rule groups defined in the
codebase, each implementing a different pluralization algorithm.

### Understanding rule groups

A rule group is a **shared pluralization algorithm** used by multiple languages.
For example, rule group `1` implements `n != 1` (singular for 1, plural for everything
else) — this is shared by English, German, Dutch, Italian, Spanish, and ~80 other languages.

Each rule group produces a specific set of **CLDR plural categories** in a fixed order.
The categories are: `zero`, `one`, `two`, `few`, `many`, `other` (not all groups use
all categories — most use only a subset).

### How to determine the correct cardinal rule group

1. **Look up the language on the CLDR plural rules page:**
   https://www.unicode.org/cldr/charts/latest/supplemental/language_plural_rules.html

2. **Identify which categories the language uses** and what the rules are. Example for
   Portuguese: categories are `one` (rule: `i = 0,1`) and `other` — this means
   "use 'one' when the integer part is 0 or 1, otherwise use 'other'".

3. **Find the matching group** in the reference table below. Match by **both** the
   category set and the rule logic:

#### Cardinal rule groups

| Group  | Categories                  | Rule logic                                                              | Example languages                                        |
|:------:|-----------------------------|-------------------------------------------------------------------------|----------------------------------------------------------|
| **0**  | other                       | No plural distinction (always "other")                                  | Japanese, Korean, Chinese, Thai, Indonesian, Malay       |
| **1**  | one/other                   | `n = 1` → one; else other                                               | English, German, Dutch, Greek, Finnish, Hebrew, Italian¹ |
| **2**  | one/other                   | `i = 0,1` or `n = 0..1` → one; else other                               | Hindi, Persian, Amharic, Bengali, Armenian, Filipino²    |
| **3**  | one/few/many/other          | East Slavic (ends in 1 not 11 / ends in 2-4 not 12-14 / else)           | Russian, Ukrainian, Belarusian                           |
| **4**  | one/few/many/other          | `n = 1` / `n = 2..4` / decimals / else                                  | Czech, Slovak                                            |
| **5**  | one/two/few/many/other      | `n = 1` / `n = 2` / `n = 3..6` / `n = 7..10` / else                     | Irish                                                    |
| **6**  | one/few/many/other          | Lithuanian (ends in 1 not 11 / ends in 2-9 not 12-19 / decimals / else) | Lithuanian                                               |
| **7**  | one/two/few/other           | `n%100 = 1` / `n%100 = 2` / `n%100 = 3..4` / else                       | Slovenian, Lower Sorbian, Upper Sorbian                  |
| **8**  | one/other                   | `n%10 = 1 and n%100 ≠ 11` → one; else other                             | Macedonian                                               |
| **10** | one/zero/other              | Latvian (ends in 1 not 11 / ends in 0 or 11-19 / else)                  | Latvian                                                  |
| **11** | one/few/other               | `n = 1` / ends in 2-4 not 12-14 / else                                  | Polish                                                   |
| **12** | one/few/other               | `n = 1` / `n = 0` or `n%100 = 2..19` / else                             | Romanian, Moldavian                                      |
| **13** | zero/one/two/few/many/other | Arabic (6 forms)                                                        | Arabic                                                   |
| **14** | zero/one/two/few/many/other | Welsh (6 forms)                                                         | Welsh                                                    |
| **15** | one/other                   | Icelandic (`n%10 ≠ 1` or `n%100 = 11`)                                  | Icelandic                                                |
| **16** | one/two/few/other           | `n = 1,11` / `n = 2,12` / `n = 3..19` / else                            | Scottish Gaelic                                          |
| **17** | one/two/few/many/other      | Breton (`n = 1` / `n = 2` / `n = 3` / etc.)                             | Breton                                                   |
| **18** | one/two/few/many/other      | Manx (`n%10 = 1` / `n%10 = 2` / `n%20 = 0` / etc.)                      | Manx                                                     |
| **19** | one/two/other               | `n = 1` / `n = 2` / else                                                | Hebrew                                                   |
| **20** | one/many/other              | `i = 1, v = 0` / `n ≠ 0, n%1000000 = 0` / else                          | Italian, Spanish, Catalan, Ladin                         |
| **21** | one/two/other               | `n = 1` / `n = 2` / else                                                | Inuktitut, Sami, Nama                                    |
| **22** | zero/one/other              | `n = 0` / `n = 1` / else                                                | Colognian, Anii, Langi                                   |
| **23** | one/few/other               | `n = 0..1` / `n = 2..10` / else                                         | Tachelhit                                                |
| **24** | one/two/few/many/other/zero | Cornish (complex CLDR 49 rules)                                         | Cornish                                                  |
| **25** | one/other                   | Filipino (does not end in 4, 6, 9)                                      | Filipino, Tagalog                                        |
| **26** | one/other                   | Central Atlas Tamazight                                                 | Central Atlas Tamazight                                  |
| **27** | one/few/other               | South Slavic (ends in 1 not 11 / ends in 2-4 not 12-14 / else)          | Bosnian, Croatian, Serbian                               |
| **28** | one/two/few/many/other      | Maltese (complex rules based on n%100)                                  | Maltese                                                  |
| **29** | one/many/other              | `i = 0,1` / `n ≠ 0, n%1000000 = 0` / else                               | French, Portuguese                                       |

¹ Italian uses group 20 (not group 1) because it has a "many" category for millions.
² Filipino uses group 25 (not group 2) because of the 4/6/9 ending rule.

### How to determine the correct ordinal rule group

Same process as cardinal — look up the language's ordinal rules in CLDR and find the
matching group. Many languages have **no ordinal distinction** (use group `0`).

#### Ordinal rule groups

| Group  | Categories                  | Rule logic                                           | Example languages                       |
|:------:|-----------------------------|------------------------------------------------------|-----------------------------------------|
| **0**  | other                       | No ordinal distinction (default)                     | Most languages                          |
| **1**  | one/two/few/other           | English (1st, 2nd, 3rd, 4th)                         | English                                 |
| **2**  | one/other                   | `n = 1` → one; else other                            | French, Italian (ordinal), Nepali, etc. |
| **8**  | one/two/many/other          | Macedonian                                           | Macedonian                              |
| **14** | zero/one/two/few/many/other | Welsh                                                | Welsh                                   |
| **16** | one/two/few/other           | Scottish Gaelic                                      | Scottish Gaelic                         |
| **20** | many/other                  | `n = 8,11,80,800` → many; else other                 | Italian                                 |
| **21** | many/other                  | `n%10 = 6,9` or `n%10 = 0, n ≠ 0` → many; else other | Kazakh                                  |
| **22** | few/other                   | `n%10 = 3, n%100 ≠ 13` → few; else other             | Ukrainian                               |
| **23** | one/two/few/many/other      | Bengali, Assamese (`one = 1,5,7,8,9,10`)             | Bengali, Assamese                       |
| **24** | one/two/few/many/other      | Gujarati, Hindi (`one = 1`)                          | Gujarati, Hindi                         |
| **26** | one/two/few/other           | Marathi, Konkani                                     | Marathi, Konkani                        |
| **27** | one/two/few/many/other      | Odia (`one = 1,5,7..9`)                              | Odia                                    |
| **29** | one/other                   | Nepali (`n = 1..4`)                                  | Nepali                                  |
| **30** | one/many/other              | Albanian (`n = 1` / `n%10 = 4, n%100 ≠ 14`)          | Albanian                                |
| **31** | zero/one/few/other          | Anii                                                 | Anii                                    |
| **32** | one/many/other              | Cornish                                              | Cornish                                 |
| **33** | few/other                   | Afrikaans (`i%100 = 2..19`)                          | Afrikaans                               |
| **34** | one/other                   | Spanish (`n%10 = 1,3, n%100 ≠ 11`)                   | Spanish                                 |
| **35** | one/other                   | Hungarian (`n = 1,5`)                                | Hungarian                               |
| **36** | one/few/many/other          | Azerbaijani                                          | Azerbaijani                             |
| **37** | few/other                   | Belarusian (`n%10 = 2,3, n%100 ≠ 12,13`)             | Belarusian                              |
| **38** | zero/one/two/few/many/other | Bulgarian                                            | Bulgarian                               |
| **39** | one/two/few/other           | Catalan (`n = 1,3` / `n = 2` / `n = 4`)              | Catalan                                 |
| **40** | one/many/other              | Georgian                                             | Georgian                                |
| **41** | one/other                   | Swedish (`n%10 = 1,2, n%100 ≠ 11,12`)                | Swedish                                 |
| **42** | many/other                  | Ligurian/Sicilian (`n = 8,11,80..89,800..899`)       | Ligurian, Sicilian                      |
| **43** | few/other                   | Turkmen (`n%10 = 6,9` or `n = 10`)                   | Turkmen                                 |

### Concrete examples

**Example 1 — Adding Wolof (wo), not in CLDR, no close relative:**

```php
'wo' => ['cardinal' => 0, 'ordinal' => 0],    // Wolof
```

Both set to `0` because there's no CLDR data and no close parent language.

**Example 2 — Adding Haitian Creole (ht), not in CLDR, inherits from French:**

```php
'ht' => ['cardinal' => 29, 'ordinal' => 2],   // Haitian Creole - inherits from 'fr'
```

Uses French's cardinal group (`29`: one = `i = 0,1`) and ordinal group (`2`: one = `n = 1`).

**Example 3 — Adding a language that IS in CLDR (e.g., Basque):**
Look up Basque in CLDR → cardinal has `one` (n = 1) and `other` → matches group `1`.
Ordinal has no distinction → group `0`.

```php
'eu' => ['cardinal' => 1, 'ordinal' => 0],    // Basque
```

### What if no existing rule group matches?

If the CLDR defines a pluralization rule that doesn't match any existing group:

1. Assign a **new group number** (use the next available number, e.g., `44` for ordinal, `30` for cardinal).
2. Implement the rule in the appropriate class:
    - `src/ICU/Plurals/Rules/CardinalIntegerRule.php` — for integer cardinal evaluation
    - `src/ICU/Plurals/Rules/CardinalDecimalRule.php` — for decimal cardinal evaluation
    - `src/ICU/Plurals/Rules/OrdinalRule.php` — for ordinal evaluation
3. Add the new group to the docblock comment above `$rulesMap`.
4. Add the human-readable rule description to `src/resources/build/cardinal_rules_human.json` or
   `ordinal_rules_human.json`.

---

## Step 3 — Update CLDR data (if needed)

**Script:** `scripts/update_cldr_plural_rules.php`

If the language is covered by CLDR, run the update script to refresh the CLDR data files:

```bash
php scripts/update_cldr_plural_rules.php
```

This script:

1. **Downloads** the latest CLDR source XML files from the Unicode GitHub repository
2. **Builds** `src/resources/build/cldr49_plural_rules.json` from the XML
3. **Validates** `PluralRules.php` categories against the new CLDR data — if this fails, fix `PluralRules.php` first (go
   back to Step 2)
4. **Generates** `src/resources/build/pluralRulesOverrides.json` with only the real deltas

> **Note:** If the language is NOT in CLDR, this step won't produce any new data for it — that's expected. The language
> will either inherit from its parent (Step 4) or use the generic "other" fallback.

---

## Step 4 — Register the parent locale (non-CLDR languages only)

**File:** `src/resources/build/nonCldrParentMap.json`

If the new language is **not in CLDR** but has a close relative that is, add the mapping:

```json
{
  "wo": "fr"
}
```

This tells `PluralRulesBuilder` to inherit CLDR rule text, human-readable descriptions, and examples from French (`fr`)
when building the plural rules for Wolof (`wo`).

**When to add a parent mapping:**

- The language is a creole, dialect, or close relative of a CLDR language
- The plural rules are identical or very similar to the parent

**When NOT to add a parent mapping:**

- The language has no close relative in CLDR (e.g., isolated language families)
- The language is already in CLDR directly

Also update the documentation:

**File:** `scripts/NON_CLDR_LOCALES.md`

Add the language to the appropriate section:

- **Section 1** if it has a derivable parent
- **Section 2** if it's standalone (no parent)

And update the `$parents` map in:

**File:** `scripts/find_non_cldr_locales.php`

---

## Step 5 — Add localized examples (optional)

**File:** `src/resources/build/pluralRulesOverrides.json`

If you want to provide localized example sentences (instead of generic number lists), add an override entry:

```json
{
  "wo": {
    "cardinal": {
      "other": {
        "example": "15 fan / 1,5 fan"
      }
    }
  }
}
```

**Override fields:**

- `example` — a localized example sentence showing the plural form in context
- `human_rule` — a custom human-readable description of the rule (rarely needed)

Overrides are keyed by **category name** (`one`, `two`, `few`, `many`, `other`), not by positional index.

> **Tip:** The `update_cldr_plural_rules.php` script automatically generates overrides from CLDR `minimal_pair` data. If
> the language is in CLDR, run the script first and then manually adjust only what's needed.

---

## Step 6 — Rebuild `pluralRules.json`

The final output file is built by `PluralRulesBuilder`. There are two ways to trigger a rebuild:

### Option A: Run the test suite (recommended)

```bash
php vendor/bin/phpunit
```

The test suite triggers `PluralRulesBuilder::getInstance(forceRebuild: true)` which regenerates the file.

### Option B: Force rebuild in PHP

```php
use Matecat\Locales\PluralRulesBuilder;

PluralRulesBuilder::destroyInstance();
PluralRulesBuilder::getInstance(forceRebuild: true);
```

This reads all build inputs and writes `src/resources/pluralRules.json`.

---

## Step 7 — Validate

### Run PHPStan

```bash
php vendor/bin/phpstan analyse src/
```

### Run the test suite

```bash
php vendor/bin/phpunit
```

### Run the CLDR validation script

```bash
php scripts/validate_rules_vs_cldr.php
```

This checks that the cardinal and ordinal categories in `PluralRules.php` match the CLDR reference.

### Spot-check the output

Open `src/resources/pluralRules.json` and find the new language entry. Verify:

- The `name` and `isoCode` are correct
- The `cardinal` array has the expected categories (e.g., `one`, `other`)
- The `ordinal` array has the expected categories
- The `rule` fields contain the correct CLDR expressions
- The `human_rule` fields are readable and accurate
- The `example` fields contain either CLDR examples or localized overrides

---

## Quick Reference — Decision Tree

```
Is the new language in CLDR?
│
├── YES
│   ├── 1. Add to supported_langs.json
│   ├── 2. Add to PluralRules.php $rulesMap with correct rule groups
│   ├── 3. Run update_cldr_plural_rules.php
│   ├── 4. (optional) Add localized examples to pluralRulesOverrides.json
│   ├── 5. Rebuild pluralRules.json
│   └── 6. Validate (phpstan + tests + validate_rules_vs_cldr.php)
│
└── NO
    │
    ├── Has a close CLDR relative?
    │   │
    │   ├── YES
    │   │   ├── 1. Add to supported_langs.json
    │   │   ├── 2. Add to PluralRules.php $rulesMap (use parent's rule groups)
    │   │   ├── 3. Add to nonCldrParentMap.json
    │   │   ├── 4. Update NON_CLDR_LOCALES.md (Section 1)
    │   │   ├── 5. Update find_non_cldr_locales.php $parents
    │   │   ├── 6. (optional) Add localized examples to pluralRulesOverrides.json
    │   │   ├── 7. Rebuild pluralRules.json
    │   │   └── 8. Validate
    │   │
    │   └── NO (standalone)
    │       ├── 1. Add to supported_langs.json
    │       ├── 2. Add to PluralRules.php $rulesMap with cardinal=0, ordinal=0
    │       ├── 3. Update NON_CLDR_LOCALES.md (Section 2)
    │       ├── 4. Update find_non_cldr_locales.php $parents (value: null)
    │       ├── 5. Rebuild pluralRules.json
    │       └── 6. Validate
```

---

## Files Modified Checklist

| File                                            |  Always  | CLDR only | Non-CLDR w/ parent | Non-CLDR standalone |
|-------------------------------------------------|:--------:|:---------:|:------------------:|:-------------------:|
| `src/resources/supported_langs.json`            |    ✅     |     ✅     |         ✅          |          ✅          |
| `src/ICU/Plurals/PluralRules.php`               |    ✅     |     ✅     |         ✅          |          ✅          |
| `src/resources/build/nonCldrParentMap.json`     |    —     |     —     |         ✅          |          —          |
| `src/resources/build/pluralRulesOverrides.json` | optional |   auto    |      optional      |      optional       |
| `src/resources/build/cldr49_plural_rules.json`  |    —     |   auto    |         —          |          —          |
| `scripts/NON_CLDR_LOCALES.md`                   |    —     |     —     |         ✅          |          ✅          |
| `scripts/find_non_cldr_locales.php`             |    —     |     —     |         ✅          |          ✅          |
| `src/resources/pluralRules.json`                |   auto   |   auto    |        auto        |        auto         |

*"auto" = generated by scripts/builder, not edited manually*


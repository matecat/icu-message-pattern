# MessagePattern Performance Benchmarks

This document contains performance benchmarks for the `MessagePattern` class, which parses ICU MessageFormat patterns.

## System Information

| Property | Value |
|----------|-------|
| CPU | 12th Gen Intel(R) Core(TM) i5-12500H |
| CPU Cores | 16 |
| PHP Version | 8.3.30 |
| OS | Linux |

## Configuration

| Parameter | Value |
|-----------|-------|
| Iterations per pattern | 1,000 |
| Warmup iterations | 100 |

## Benchmark Results

### Detailed Results by Pattern

| Pattern Name | Mean | Median | Min | Max | P95 | StdDev | Parts | Len |
|--------------|------|--------|-----|-----|-----|--------|-------|-----|
| simple_text | 17.84 µs | 16.99 µs | 15.21 µs | 98.22 µs | 23.22 µs | 4.91 µs | 2 | 13 |
| simple_placeholder | 28.97 µs | 24.78 µs | 21.60 µs | 160.33 µs | 47.58 µs | 11.20 µs | 5 | 14 |
| simple_numbered | 49.22 µs | 44.58 µs | 41.76 µs | 200.52 µs | 78.61 µs | 12.60 µs | 8 | 27 |
| multiple_placeholders | 84.35 µs | 76.45 µs | 72.72 µs | 266.81 µs | 144.89 µs | 23.20 µs | 14 | 68 |
| escaped_apostrophe | 25.43 µs | 23.57 µs | 22.59 µs | 138.89 µs | 33.44 µs | 5.58 µs | 3 | 22 |
| escaped_braces | 44.65 µs | 37.52 µs | 33.67 µs | 231.98 µs | 68.88 µs | 16.82 µs | 6 | 33 |
| complex_escaping | 78.85 µs | 75.02 µs | 69.06 µs | 163.24 µs | 102.59 µs | 12.23 µs | 11 | 74 |
| number_format | 35.68 µs | 33.87 µs | 32.01 µs | 77.70 µs | 44.37 µs | 5.17 µs | 6 | 15 |
| currency_format | 45.14 µs | 40.16 µs | 38.13 µs | 148.44 µs | 74.31 µs | 13.02 µs | 7 | 25 |
| percent_format | 44.96 µs | 39.98 µs | 37.61 µs | 287.41 µs | 75.75 µs | 15.70 µs | 7 | 23 |
| date_format | 46.79 µs | 37.13 µs | 29.38 µs | 118.43 µs | 74.15 µs | 17.58 µs | 7 | 19 |
| time_format | 33.23 µs | 30.52 µs | 29.59 µs | 197.55 µs | 43.37 µs | 8.79 µs | 7 | 18 |
| datetime_format | 68.25 µs | 62.53 µs | 60.65 µs | 222.37 µs | 97.08 µs | 15.59 µs | 12 | 45 |
| plural_simple | 83.40 µs | 68.97 µs | 66.43 µs | 256.45 µs | 141.64 µs | 28.68 µs | 13 | 45 |
| plural_with_offset | 161.08 µs | 147.24 µs | 140.97 µs | 330.91 µs | 273.77 µs | 36.10 µs | 22 | 108 |
| plural_categories | 162.61 µs | 149.69 µs | 135.07 µs | 419.86 µs | 273.50 µs | 36.89 µs | 24 | 113 |
| plural_explicit | 138.78 µs | 126.58 µs | 116.42 µs | 394.99 µs | 239.64 µs | 38.66 µs | 21 | 80 |
| select_simple | 94.99 µs | 82.24 µs | 74.55 µs | 291.30 µs | 172.45 µs | 31.42 µs | 14 | 53 |
| select_detailed | 174.27 µs | 134.16 µs | 116.23 µs | 792.85 µs | 272.97 µs | 64.47 µs | 14 | 102 |
| selectordinal_simple | 105.96 µs | 102.20 µs | 93.94 µs | 304.29 µs | 134.62 µs | 14.04 µs | 21 | 63 |
| selectordinal_full | 128.79 µs | 124.85 µs | 114.71 µs | 270.34 µs | 159.18 µs | 20.27 µs | 21 | 88 |
| nested_select_plural | 331.98 µs | 306.54 µs | 294.26 µs | 678.03 µs | 495.28 µs | 66.45 µs | 47 | 223 |
| nested_plural_select | 248.95 µs | 226.77 µs | 218.54 µs | 616.41 µs | 412.25 µs | 59.66 µs | 37 | 162 |
| real_world_notification | 188.57 µs | 177.09 µs | 170.52 µs | 439.34 µs | 264.50 µs | 33.74 µs | 23 | 147 |
| real_world_purchase | 244.77 µs | 220.38 µs | 199.09 µs | 552.31 µs | 411.92 µs | 60.47 µs | 30 | 174 |
| real_world_time_ago | 116.66 µs | 106.06 µs | 99.78 µs | 291.66 µs | 195.25 µs | 28.25 µs | 17 | 72 |
| deeply_nested | 212.16 µs | 187.96 µs | 180.85 µs | 508.26 µs | 370.17 µs | 58.82 µs | 31 | 108 |
| many_arguments | 200.79 µs | 187.08 µs | 180.71 µs | 529.01 µs | 278.73 µs | 43.74 µs | 50 | 63 |
| long_text | 212.22 µs | 199.54 µs | 190.22 µs | 387.54 µs | 298.00 µs | 33.26 µs | 2 | 231 |
| unicode_content | 106.22 µs | 101.45 µs | 96.42 µs | 208.80 µs | 137.07 µs | 15.91 µs | 19 | 65 |
| mixed_unicode | 131.77 µs | 114.89 µs | 111.19 µs | 386.24 µs | 212.50 µs | 36.30 µs | 19 | 85 |
| choice_simple | 68.06 µs | 63.86 µs | 60.90 µs | 191.96 µs | 88.72 µs | 12.80 µs | 12 | 34 |
| choice_complex | 113.23 µs | 106.98 µs | 105.28 µs | 248.57 µs | 144.02 µs | 15.44 µs | 24 | 49 |
| choice_infinity | 91.29 µs | 79.24 µs | 75.97 µs | 241.04 µs | 153.57 µs | 26.57 µs | 16 | 35 |

### Summary

| Metric | Value |
|--------|-------|
| Total benchmark time | 4.42 s |
| Total patterns tested | 34 |
| Total parse operations | 34,000 |
| Average mean parse time | 115.29 µs |
| Fastest pattern | simple_text (17.84 µs) |
| Slowest pattern | nested_select_plural (331.98 µs) |

### Memory Usage

| Metric | Value |
|--------|-------|
| Memory at start | 4.00 MB |
| Memory at end | 4.00 MB |
| Peak memory | 4.00 MB |

### Performance by Category

| Category | Avg Mean | Avg Min | Avg Max |
|----------|----------|---------|---------|
| Simple | 45.10 µs | 37.82 µs | 181.47 µs |
| Escaped | 49.64 µs | 41.77 µs | 178.04 µs |
| Formatting | 45.68 µs | 37.89 µs | 175.32 µs |
| Plural | 136.47 µs | 114.72 µs | 350.55 µs |
| Select | 126.00 µs | 99.85 µs | 414.69 µs |
| Nested | 264.36 µs | 231.21 µs | 600.90 µs |
| Real-world | 183.33 µs | 156.46 µs | 427.77 µs |
| Edge cases | 162.75 µs | 144.63 µs | 377.90 µs |
| Choice style | 90.86 µs | 80.72 µs | 227.19 µs |

### Throughput Analysis

| Metric | Value |
|--------|-------|
| Operations per second | 8,674 ops/s |
| Characters processed | 636,745 chars/s |

### Top 5 Fastest Patterns

1. **simple_text** (17.84 µs)
2. **escaped_apostrophe** (25.43 µs)
3. **simple_placeholder** (28.97 µs)
4. **time_format** (33.23 µs)
5. **number_format** (35.68 µs)

### Top 5 Slowest Patterns

1. **nested_select_plural** (331.98 µs)
2. **nested_plural_select** (248.95 µs)
3. **real_world_purchase** (244.77 µs)
4. **long_text** (212.22 µs)
5. **deeply_nested** (212.16 µs)

## Correlation Analysis

**Pattern Length vs Parse Time:**

| Metric | Value |
|--------|-------|
| Pearson correlation coefficient | 0.9169 |
| Linear regression equation | `time(µs) = 1.2157 × length + 26.0492` |

**Interpretation:** There is a **strong correlation** between pattern length and parse time.

- Each additional character adds approximately **~1.22 µs** to the parsing time
- There is a base overhead of approximately **~26.05 µs** regardless of pattern length

## Test Patterns

The benchmark includes the following pattern categories:

### Simple Patterns
- `simple_text`: `Hello, World!`
- `simple_placeholder`: `Hello, {name}!`
- `simple_numbered`: `Hello, {0}! Welcome to {1}.`
- `multiple_placeholders`: `Dear {title} {firstName} {lastName}, your order #{orderId} is ready.`

### Escaped Patterns
- `escaped_apostrophe`: `It''s a beautiful day!`
- `escaped_braces`: `Use '{' and '}' for placeholders.`
- `complex_escaping`: `Don''t forget: '{name}' means literal {name} but {actualName} is replaced.`

### Number/Date/Time Formatting
- `number_format`: `{count, number}`
- `currency_format`: `{price, number, currency}`
- `percent_format`: `{rate, number, percent}`
- `date_format`: `{today, date, long}`
- `time_format`: `{now, time, short}`
- `datetime_format`: `On {date, date, full} at {time, time, medium}`

### Plural Patterns
- `plural_simple`: `{count, plural, one {# item} other {# items}}`
- `plural_with_offset`: `{count, plural, offset:1 =0 {no one} =1 {yourself} one {yourself and # other} other {yourself and # others}}`
- `plural_categories`: `{n, plural, zero {zero items} one {one item} two {two items} few {a few items} many {many items} other {# items}}`
- `plural_explicit`: `{count, plural, =0 {no files} =1 {one file} =2 {a couple files} other {# files}}`

### Select Patterns
- `select_simple`: `{gender, select, male {He} female {She} other {They}}`
- `select_detailed`: `{gender, select, male {He is a good man.} female {She is a good woman.} other {They are good people.}}`

### SelectOrdinal Patterns
- `selectordinal_simple`: `{pos, selectordinal, one {#st} two {#nd} few {#rd} other {#th}}`
- `selectordinal_full`: `{rank, selectordinal, one {#st place} two {#nd place} few {#rd place} other {#th place}}`

### Nested Patterns
- `nested_select_plural`: `{gender, select, female {{count, plural, one {She has # cat} other {She has # cats}}} male {{count, plural, one {He has # cat} other {He has # cats}}} other {{count, plural, one {They have # cat} other {They have # cats}}}}`
- `nested_plural_select`: `{count, plural, one {{gender, select, male {He} female {She} other {They}} has # item} other {{gender, select, male {He} female {She} other {They}} have # items}}`

### Real-world Patterns
- `real_world_notification`: `{count, plural, =0 {You have no new messages} one {You have # new message from {sender}} other {You have # new messages, the latest from {sender}}}`
- `real_world_purchase`: `{itemCount, plural, =0 {Your cart is empty} one {You have # item ({itemName}) totaling {total, number, currency}} other {You have # items totaling {total, number, currency}}}`
- `real_world_time_ago`: `{minutes, plural, =0 {just now} =1 {a minute ago} other {# minutes ago}}`

### Edge Cases
- `deeply_nested`: `{a, select, x {{b, select, y {{c, plural, one {deep #} other {deeper #}}} other {b-other}}} other {a-other}}`
- `many_arguments`: `{a} {b} {c} {d} {e} {f} {g} {h} {i} {j} {k} {l} {m} {n} {o} {p}`
- `long_text`: Long text without placeholders (231 characters)
- `unicode_content`: Chinese characters with plural patterns
- `mixed_unicode`: Mixed Unicode (Cyrillic, Chinese, emoji) with plural patterns

### Choice Style Patterns
- `choice_simple`: `0#no files|1#one file|1<many files`
- `choice_complex`: `0#none|1#one|2#two|3<several|10<many|100≤hundreds`
- `choice_infinity`: `-∞<negative|0#zero|0<positive|∞≤max`

## Running the Benchmark

To run the benchmark yourself:

```bash
# Default: 1000 iterations, 100 warmup
php tests/Matecat/benchmark.php

# Custom iterations and warmup
php tests/Matecat/benchmark.php 5000 200
```

## Notes

- **Warmup phase**: The benchmark includes a warmup phase to allow PHP's JIT compiler to optimize hot paths and populate CPU caches before measurements begin.
- **High-resolution timing**: Uses `hrtime(true)` for nanosecond-precision timing.
- **Statistical measures**: Includes mean, median, min, max, P95, and standard deviation for comprehensive analysis.


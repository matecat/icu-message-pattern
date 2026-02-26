# MessagePattern Performance Benchmarks

This document contains performance benchmarks for the `MessagePattern` class, which parses ICU MessageFormat patterns.

## System Information

| Property    | Value                                |
|-------------|--------------------------------------|
| CPU         | 12th Gen Intel(R) Core(TM) i5-12500H |
| CPU Cores   | 16                                   |
| PHP Version | 8.3.30                               |
| OS          | Linux                                |

## Configuration

| Parameter              | Value |
|------------------------|-------|
| Iterations per pattern | 1,000 |
| Warmup iterations      | 100   |

## Benchmark Results

### Detailed Results by Pattern

| Pattern Name            | Mean      | Median    | Min       | Max        | P95       | StdDev   | Parts | Len |
|-------------------------|-----------|-----------|-----------|------------|-----------|----------|-------|-----|
| simple_text             | 20.08 µs  | 18.86 µs  | 16.05 µs  | 39.41 µs   | 26.97 µs  | 3.73 µs  | 2     | 13  |
| simple_placeholder      | 28.49 µs  | 26.62 µs  | 22.71 µs  | 64.61 µs   | 39.32 µs  | 5.78 µs  | 5     | 14  |
| simple_numbered         | 55.53 µs  | 51.91 µs  | 44.30 µs  | 103.04 µs  | 73.18 µs  | 9.58 µs  | 8     | 27  |
| multiple_placeholders   | 90.59 µs  | 85.81 µs  | 76.40 µs  | 810.34 µs  | 120.48 µs | 26.49 µs | 14    | 68  |
| escaped_apostrophe      | 29.84 µs  | 27.29 µs  | 24.13 µs  | 896.89 µs  | 38.68 µs  | 27.89 µs | 3     | 22  |
| escaped_braces          | 48.58 µs  | 43.95 µs  | 37.33 µs  | 1265.70 µs | 63.41 µs  | 39.69 µs | 6     | 33  |
| complex_escaping        | 96.63 µs  | 93.76 µs  | 74.51 µs  | 588.43 µs  | 126.59 µs | 22.80 µs | 11    | 74  |
| number_format           | 40.59 µs  | 39.35 µs  | 35.09 µs  | 72.20 µs   | 53.25 µs  | 5.54 µs  | 6     | 15  |
| currency_format         | 50.79 µs  | 47.40 µs  | 41.86 µs  | 154.73 µs  | 68.86 µs  | 10.11 µs | 7     | 25  |
| percent_format          | 49.26 µs  | 46.26 µs  | 41.36 µs  | 719.06 µs  | 64.33 µs  | 22.57 µs | 7     | 23  |
| date_format             | 40.29 µs  | 36.84 µs  | 32.56 µs  | 741.74 µs  | 54.19 µs  | 23.95 µs | 7     | 19  |
| time_format             | 38.50 µs  | 36.87 µs  | 32.75 µs  | 90.28 µs   | 50.01 µs  | 6.24 µs  | 7     | 18  |
| datetime_format         | 79.98 µs  | 74.51 µs  | 67.03 µs  | 891.08 µs  | 104.37 µs | 36.53 µs | 12    | 45  |
| plural_simple           | 87.01 µs  | 81.23 µs  | 72.74 µs  | 1462.50 µs | 112.75 µs | 50.02 µs | 13    | 45  |
| plural_with_offset      | 181.75 µs | 170.21 µs | 156.03 µs | 1300.40 µs | 233.16 µs | 57.00 µs | 22    | 108 |
| plural_categories       | 180.38 µs | 172.26 µs | 157.78 µs | 1335.20 µs | 225.48 µs | 57.25 µs | 24    | 113 |
| plural_explicit         | 150.12 µs | 140.36 µs | 128.67 µs | 860.19 µs  | 200.38 µs | 38.34 µs | 21    | 80  |
| select_simple           | 95.93 µs  | 89.42 µs  | 81.86 µs  | 783.69 µs  | 129.67 µs | 28.76 µs | 14    | 53  |
| select_detailed         | 147.99 µs | 136.14 µs | 125.74 µs | 1273.10 µs | 205.40 µs | 56.50 µs | 14    | 102 |
| selectordinal_simple    | 120.08 µs | 107.61 µs | 96.84 µs  | 804.52 µs  | 217.71 µs | 41.84 µs | 21    | 63  |
| selectordinal_full      | 147.70 µs | 131.41 µs | 118.90 µs | 1243.80 µs | 269.07 µs | 63.16 µs | 21    | 88  |
| nested_select_plural    | 352.49 µs | 337.60 µs | 322.90 µs | 1151.60 µs | 396.04 µs | 62.69 µs | 47    | 223 |
| nested_plural_select    | 263.63 µs | 252.07 µs | 223.61 µs | 1059.60 µs | 314.90 µs | 54.02 µs | 37    | 162 |
| real_world_notification | 203.98 µs | 193.10 µs | 183.68 µs | 1018.30 µs | 241.57 µs | 50.88 µs | 23    | 147 |
| real_world_purchase     | 255.76 µs | 243.00 µs | 231.00 µs | 1112.30 µs | 305.01 µs | 59.34 µs | 30    | 174 |
| real_world_time_ago     | 132.09 µs | 115.35 µs | 110.21 µs | 1263.70 µs | 236.99 µs | 63.57 µs | 17    | 72  |
| deeply_nested           | 224.92 µs | 212.50 µs | 200.18 µs | 1036.70 µs | 272.72 µs | 53.38 µs | 31    | 108 |
| many_arguments          | 215.50 µs | 200.46 µs | 177.61 µs | 966.03 µs  | 261.72 µs | 59.86 µs | 50    | 63  |
| long_text               | 224.10 µs | 217.26 µs | 203.07 µs | 1876.80 µs | 255.90 µs | 55.06 µs | 2     | 231 |
| unicode_content         | 117.75 µs | 112.27 µs | 104.58 µs | 969.74 µs  | 143.74 µs | 38.86 µs | 19    | 65  |
| mixed_unicode           | 134.77 µs | 127.15 µs | 120.34 µs | 1047.50 µs | 163.03 µs | 50.89 µs | 19    | 85  |
| choice_simple           | 81.84 µs  | 73.80 µs  | 68.34 µs  | 951.75 µs  | 135.02 µs | 44.44 µs | 12    | 34  |
| choice_complex          | 140.76 µs | 126.63 µs | 111.77 µs | 833.40 µs  | 257.24 µs | 44.06 µs | 24    | 49  |
| choice_infinity         | 99.37 µs  | 90.72 µs  | 85.71 µs  | 815.82 µs  | 140.77 µs | 38.34 µs | 16    | 35  |

### Summary

| Metric                  | Value                            |
|-------------------------|----------------------------------|
| Total benchmark time    | 4.90 s                           |
| Total patterns tested   | 34                               |
| Total parse operations  | 34,000                           |
| Average mean parse time | 124.32 µs                        |
| Fastest pattern         | simple_text (20.08 µs)           |
| Slowest pattern         | nested_select_plural (352.49 µs) |

### Memory Usage

| Metric          | Value    |
|-----------------|----------|
| Memory at start | 4.00 MB  |
| Memory at end   | 12.00 MB |
| Peak memory     | 16.00 MB |

### Performance by Category

| Category     | Avg Mean  | Avg Min   | Avg Max    |
|--------------|-----------|-----------|------------|
| Simple       | 48.67 µs  | 39.86 µs  | 254.35 µs  |
| Escaped      | 58.35 µs  | 45.32 µs  | 917.00 µs  |
| Formatting   | 49.90 µs  | 41.77 µs  | 444.85 µs  |
| Plural       | 149.81 µs | 128.81 µs | 1239.60 µs |
| Select       | 127.92 µs | 105.84 µs | 1026.30 µs |
| Nested       | 280.35 µs | 248.90 µs | 1082.60 µs |
| Real-world   | 197.28 µs | 174.96 µs | 1131.50 µs |
| Edge cases   | 173.03 µs | 151.40 µs | 1215.00 µs |
| Choice style | 107.32 µs | 88.61 µs  | 867.00 µs  |

### Throughput Analysis

| Metric                | Value           |
|-----------------------|-----------------|
| Operations per second | 8,043 ops/s     |
| Characters processed  | 590,484 chars/s |

### Top 5 Fastest Patterns

1. **simple_text** (20.08 µs)
2. **simple_placeholder** (28.49 µs)
3. **escaped_apostrophe** (29.84 µs)
4. **time_format** (38.50 µs)
5. **date_format** (40.29 µs)

### Top 5 Slowest Patterns

1. **nested_select_plural** (352.49 µs)
2. **nested_plural_select** (263.63 µs)
3. **real_world_purchase** (255.76 µs)
4. **deeply_nested** (224.92 µs)
5. **long_text** (224.10 µs)

## Correlation Analysis

**Pattern Length vs Parse Time:**

| Metric                          | Value                                  |
|---------------------------------|----------------------------------------|
| Pearson correlation coefficient | 0.9127                                 |
| Linear regression equation      | `time(µs) = 1.2708 × length + 31.0312` |

**Interpretation:** There is a **strong correlation** between pattern length and parse time.

- Each additional character adds approximately **~1.27 µs** to the parsing time
- There is a base overhead of approximately **~31.03 µs** regardless of pattern length

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
- `plural_with_offset`:
  `{count, plural, offset:1 =0 {no one} =1 {yourself} one {yourself and # other} other {yourself and # others}}`
- `plural_categories`:
  `{n, plural, zero {zero items} one {one item} two {two items} few {a few items} many {many items} other {# items}}`
- `plural_explicit`: `{count, plural, =0 {no files} =1 {one file} =2 {a couple files} other {# files}}`

### Select Patterns

- `select_simple`: `{gender, select, male {He} female {She} other {They}}`
- `select_detailed`:
  `{gender, select, male {He is a good man.} female {She is a good woman.} other {They are good people.}}`

### SelectOrdinal Patterns

- `selectordinal_simple`: `{pos, selectordinal, one {#st} two {#nd} few {#rd} other {#th}}`
- `selectordinal_full`: `{rank, selectordinal, one {#st place} two {#nd place} few {#rd place} other {#th place}}`

### Nested Patterns

- `nested_select_plural`:
  `{gender, select, female {{count, plural, one {She has # cat} other {She has # cats}}} male {{count, plural, one {He has # cat} other {He has # cats}}} other {{count, plural, one {They have # cat} other {They have # cats}}}}`
- `nested_plural_select`:
  `{count, plural, one {{gender, select, male {He} female {She} other {They}} has # item} other {{gender, select, male {He} female {She} other {They}} have # items}}`

### Real-world Patterns

- `real_world_notification`:
  `{count, plural, =0 {You have no new messages} one {You have # new message from {sender}} other {You have # new messages, the latest from {sender}}}`
- `real_world_purchase`:
  `{itemCount, plural, =0 {Your cart is empty} one {You have # item ({itemName}) totaling {total, number, currency}} other {You have # items totaling {total, number, currency}}}`
- `real_world_time_ago`: `{minutes, plural, =0 {just now} =1 {a minute ago} other {# minutes ago}}`

### Edge Cases

- `deeply_nested`:
  `{a, select, x {{b, select, y {{c, plural, one {deep #} other {deeper #}}} other {b-other}}} other {a-other}}`
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

- **Warmup phase**: The benchmark includes a warmup phase to allow PHP's JIT compiler to optimize hot paths and populate
  CPU caches before measurements begin.
- **High-resolution timing**: Uses `hrtime(true)` for nanosecond-precision timing.
- **Statistical measures**: Includes mean, median, min, max, P95, and standard deviation for comprehensive analysis.

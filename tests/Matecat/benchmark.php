#!/usr/bin/env php
<?php

/**
 * Benchmark script for measuring MessagePattern performance.
 *
 * This script measures parsing performance across various ICU MessageFormat patterns,
 * including simple patterns, plural/select styles, nested structures, and edge cases.
 *
 * Usage: php benchmark.php [iterations] [warmup]
 *   - iterations: Number of iterations per test (default: 1000)
 *   - warmup: Number of warmup iterations (default: 100)
 *
 * Example: php benchmark.php 5000 200
 */

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use Matecat\ICU\MessagePattern;

// Configuration
$iterations = (int)($argv[1] ?? 1000);
$warmupIterations = (int)($argv[2] ?? 100);

/**
 * Test patterns organized by complexity and type
 */
$testPatterns = [
    // Simple patterns
        'simple_text' => 'Hello, World!',
        'simple_placeholder' => 'Hello, {name}!',
        'simple_numbered' => 'Hello, {0}! Welcome to {1}.',
        'multiple_placeholders' => 'Dear {title} {firstName} {lastName}, your order #{orderId} is ready.',

    // Escaped patterns
        'escaped_apostrophe' => "It''s a beautiful day!",
        'escaped_braces' => "Use '{' and '}' for placeholders.",
        'complex_escaping' => "Don''t forget: '{name}' means literal {name} but {actualName} is replaced.",

    // Number/Date/Time formatting
        'number_format' => '{count, number}',
        'currency_format' => '{price, number, currency}',
        'percent_format' => '{rate, number, percent}',
        'date_format' => '{today, date, long}',
        'time_format' => '{now, time, short}',
        'datetime_format' => 'On {date, date, full} at {time, time, medium}',

    // Plural patterns
        'plural_simple' => '{count, plural, one {# item} other {# items}}',
        'plural_with_offset' => '{count, plural, offset:1 =0 {no one} =1 {yourself} one {yourself and # other} other {yourself and # others}}',
        'plural_categories' => '{n, plural, zero {zero items} one {one item} two {two items} few {a few items} many {many items} other {# items}}',
        'plural_explicit' => '{count, plural, =0 {no files} =1 {one file} =2 {a couple files} other {# files}}',

    // Select patterns
        'select_simple' => '{gender, select, male {He} female {She} other {They}}',
        'select_detailed' => '{gender, select, male {He is a good man.} female {She is a good woman.} other {They are good people.}}',

    // SelectOrdinal patterns
        'selectordinal_simple' => '{pos, selectordinal, one {#st} two {#nd} few {#rd} other {#th}}',
        'selectordinal_full' => '{rank, selectordinal, one {#st place} two {#nd place} few {#rd place} other {#th place}}',

    // Nested patterns
        'nested_select_plural' => '{gender, select, female {{count, plural, one {She has # cat} other {She has # cats}}} male {{count, plural, one {He has # cat} other {He has # cats}}} other {{count, plural, one {They have # cat} other {They have # cats}}}}',
        'nested_plural_select' => '{count, plural, one {{gender, select, male {He} female {She} other {They}} has # item} other {{gender, select, male {He} female {She} other {They}} have # items}}',

    // Complex real-world patterns
        'real_world_notification' => '{count, plural, =0 {You have no new messages} one {You have # new message from {sender}} other {You have # new messages, the latest from {sender}}}',
        'real_world_purchase' => '{itemCount, plural, =0 {Your cart is empty} one {You have # item ({itemName}) totaling {total, number, currency}} other {You have # items totaling {total, number, currency}}}',
        'real_world_time_ago' => '{minutes, plural, =0 {just now} =1 {a minute ago} other {# minutes ago}}',

    // Edge cases
        'deeply_nested' => '{a, select, x {{b, select, y {{c, plural, one {deep #} other {deeper #}}} other {b-other}}} other {a-other}}',
        'many_arguments' => '{a} {b} {c} {d} {e} {f} {g} {h} {i} {j} {k} {l} {m} {n} {o} {p}',
        'long_text' => 'This is a very long text message that contains multiple sentences. ' .
                'It should test how the parser handles longer strings without any placeholders. ' .
                'The quick brown fox jumps over the lazy dog. ' .
                'Pack my box with five dozen liquor jugs.',
        'unicode_content' => '你好 {name}！欢迎来到 {place}。{count, plural, one {# 个项目} other {# 个项目}}',
        'mixed_unicode' => '{greeting} мир! Привет {name}! 🎉 {count, plural, one {# элемент} other {# элементов}}',

    // Choice style patterns (parsed separately)
        'choice_simple' => '0#no files|1#one file|1<many files',
        'choice_complex' => '0#none|1#one|2#two|3<several|10<many|100≤hundreds',
        'choice_infinity' => '-∞<negative|0#zero|0<positive|∞≤max',
];

/**
 * Format bytes to human-readable format
 */
function formatBytes(int $bytes): string
{
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    }
    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    }
    return $bytes . ' B';
}

/**
 * Format time in microseconds to human-readable format
 */
function formatTime(float $microseconds): string
{
    if ($microseconds >= 1000000) {
        return number_format($microseconds / 1000000, 4) . ' s ';
    }
    if ($microseconds >= 1000) {
        return number_format($microseconds / 1000, 4) . ' ms';
    }
    return number_format($microseconds, 2) . ' µs';
}

/**
 * Calculate statistics from an array of measurements
 *
 * @param array<int, float> $measurements
 * @return array<string, float>
 */
function calculateStats(array $measurements): array
{
    sort($measurements);
    $count = count($measurements);

    $sum = array_sum($measurements);
    $mean = $sum / $count;

    $variance = 0;
    foreach ($measurements as $value) {
        $variance += pow($value - $mean, 2);
    }
    $stdDev = sqrt($variance / $count);

    return [
            'min' => $measurements[0],
            'max' => $measurements[$count - 1],
            'mean' => $mean,
            'median' => $measurements[(int)floor($count / 2)],
            'p95' => $measurements[(int)floor($count * 0.95)],
            'p99' => $measurements[(int)floor($count * 0.99)],
            'stdDev' => $stdDev,
            'total' => $sum,
    ];
}

/**
 * Run benchmark for a single pattern
 *
 * @param string $pattern
 * @param int $iterations
 * @param bool $isChoiceStyle
 * @return array<string, mixed>
 * @throws \Matecat\ICU\Exceptions\InvalidArgumentException
 * @throws \Matecat\ICU\Exceptions\OutOfBoundsException
 */
function benchmarkPattern(string $pattern, int $iterations, bool $isChoiceStyle = false): array
{
    $measurements = [];
    $memoryBefore = memory_get_usage(true);
    $partsCount = 0;

    for ($i = 0; $i < $iterations; $i++) {
        $mp = new MessagePattern();

        $start = hrtime(true);
        if ($isChoiceStyle) {
            $mp->parseChoiceStyle($pattern);
        } else {
            $mp->parse($pattern);
        }
        $end = hrtime(true);

        $measurements[] = ($end - $start) / 1000; // Convert to microseconds
        $partsCount = $mp->countParts();
    }

    $memoryAfter = memory_get_usage(true);

    return [
            'stats' => calculateStats($measurements),
            'partsCount' => $partsCount,
            'memoryDelta' => $memoryAfter - $memoryBefore,
            'patternLength' => mb_strlen($pattern),
    ];
}

/**
 * Run warmup iterations
 *
 * @param array<string, string> $patterns
 * @param int $warmupIterations
 * @throws \Matecat\ICU\Exceptions\InvalidArgumentException
 * @throws \Matecat\ICU\Exceptions\OutOfBoundsException
 */
function warmup(array $patterns, int $warmupIterations): void
{
    foreach ($patterns as $name => $pattern) {
        $isChoiceStyle = str_starts_with($name, 'choice_');
        for ($i = 0; $i < $warmupIterations; $i++) {
            $mp = new MessagePattern();
            if ($isChoiceStyle) {
                $mp->parseChoiceStyle($pattern);
            } else {
                $mp->parse($pattern);
            }
        }
    }
}

/**
 * Print a separator line
 */
function printSeparator(int $width = 140): void
{
    echo str_repeat('─', $width) . PHP_EOL;
}

/**
 * Print table header
 */
function printHeader(): void
{
    echo str_pad('Pattern Name', 30) . ' │ ';
    echo str_pad('Mean', 9, ' ', STR_PAD_LEFT) . ' │ ';
    echo str_pad('Median', 9, ' ', STR_PAD_LEFT) . ' │ ';
    echo str_pad('Min', 9, ' ', STR_PAD_LEFT) . ' │ ';
    echo str_pad('Max', 9, ' ', STR_PAD_LEFT) . ' │ ';
    echo str_pad('P95', 9, ' ', STR_PAD_LEFT) . ' │ ';
    echo str_pad('StdDev', 9, ' ', STR_PAD_LEFT) . ' │ ';
    echo str_pad('Parts', 8, ' ', STR_PAD_LEFT) . ' │ ';
    echo str_pad('Len', 8, ' ', STR_PAD_LEFT) . PHP_EOL;
    printSeparator();
}

// Main execution
echo PHP_EOL;
echo "╔══════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════╗" . PHP_EOL;
echo "║                                    MessagePattern Performance Benchmark                                                                  ║" . PHP_EOL;
echo "╚══════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════╝" . PHP_EOL;
echo PHP_EOL;

// Get CPU information
$cpuInfo = 'Unknown';
$cpuCores = 'Unknown';
if (PHP_OS_FAMILY === 'Linux') {
    $cpuInfoRaw = @file_get_contents('/proc/cpuinfo');
    if ($cpuInfoRaw !== false) {
        if (preg_match('/model name\s*:\s*(.+)/i', $cpuInfoRaw, $matches)) {
            $cpuInfo = trim($matches[1]);
        }
        $cpuCores = substr_count($cpuInfoRaw, 'processor');
    }
} elseif (PHP_OS_FAMILY === 'Darwin') {
    $cpuInfo = trim((string)@shell_exec('sysctl -n machdep.cpu.brand_string 2>/dev/null'));
    $cpuCores = (int)trim((string)@shell_exec('sysctl -n hw.ncpu 2>/dev/null'));
} elseif (PHP_OS_FAMILY === 'Windows') {
    $cpuInfo = trim((string)@shell_exec('wmic cpu get name 2>nul | findstr /v "Name"'));
    $cpuCores = (int)trim(
            (string)@shell_exec('wmic cpu get NumberOfLogicalProcessors 2>nul | findstr /v "NumberOfLogicalProcessors"')
    );
}

echo "System Information:" . PHP_EOL;
echo "  • CPU: " . $cpuInfo . PHP_EOL;
echo "  • CPU Cores: " . $cpuCores . PHP_EOL;
echo "  • PHP Version: " . PHP_VERSION . PHP_EOL;
echo "  • OS: " . PHP_OS_FAMILY . " (" . PHP_OS . ")" . PHP_EOL;
echo PHP_EOL;

echo "Configuration:" . PHP_EOL;
echo "  • Iterations per pattern: " . number_format($iterations) . PHP_EOL;
echo "  • Warmup iterations: " . number_format($warmupIterations) . PHP_EOL;
echo PHP_EOL;

$memoryStart = memory_get_usage(true);
$startTime = hrtime(true);

echo "Running warmup..." . PHP_EOL;
warmup($testPatterns, $warmupIterations);
echo "Warmup complete." . PHP_EOL . PHP_EOL;

// Run benchmarks
$results = [];
$totalPatterns = count($testPatterns);
$currentPattern = 0;

echo "Running benchmarks..." . PHP_EOL;
printSeparator();
printHeader();

foreach ($testPatterns as $name => $pattern) {
    $currentPattern++;
    $isChoiceStyle = str_starts_with($name, 'choice_');

    $result = benchmarkPattern($pattern, $iterations, $isChoiceStyle);
    $results[$name] = $result;

    echo str_pad($name, 30) . ' │ ';
    echo str_pad(formatTime($result['stats']['mean']), 10, ' ', STR_PAD_LEFT) . ' │ ';
    echo str_pad(formatTime($result['stats']['median']), 10, ' ', STR_PAD_LEFT) . ' │ ';
    echo str_pad(formatTime($result['stats']['min']), 10, ' ', STR_PAD_LEFT) . ' │ ';
    echo str_pad(formatTime($result['stats']['max']), 10, ' ', STR_PAD_LEFT) . ' │ ';
    echo str_pad(formatTime($result['stats']['p95']), 10, ' ', STR_PAD_LEFT) . ' │ ';
    echo str_pad(formatTime($result['stats']['stdDev']), 10, ' ', STR_PAD_LEFT) . ' │ ';
    echo str_pad((string)$result['partsCount'], 8, ' ', STR_PAD_LEFT) . ' │ ';
    echo str_pad((string)$result['patternLength'], 8, ' ', STR_PAD_LEFT) . PHP_EOL;
}

printSeparator();

$endTime = hrtime(true);
$memoryEnd = memory_get_usage(true);
$memoryPeak = memory_get_peak_usage(true);

// Calculate aggregate statistics
$allMeans = array_map(fn($r) => $r['stats']['mean'], $results);
$overallMean = array_sum($allMeans) / count($allMeans);
$fastestPattern = array_keys($results, min($results))[0];
$slowestPattern = array_keys($results, max($results))[0];

// Find fastest and slowest by mean time
$sortedByMean = $results;
uasort($sortedByMean, fn($a, $b) => $a['stats']['mean'] <=> $b['stats']['mean']);
$sortedKeys = array_keys($sortedByMean);
$fastestPattern = $sortedKeys[0];
$slowestPattern = $sortedKeys[count($sortedKeys) - 1];

echo PHP_EOL;
echo "═══════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════" . PHP_EOL;
echo "                                                         Summary                                                                           " . PHP_EOL;
echo "═══════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════════" . PHP_EOL;
echo PHP_EOL;

echo "Performance:" . PHP_EOL;
echo "  • Total benchmark time: " . formatTime(($endTime - $startTime) / 1000) . PHP_EOL;
echo "  • Total patterns tested: " . $totalPatterns . PHP_EOL;
echo "  • Total parse operations: " . number_format($totalPatterns * $iterations) . PHP_EOL;
echo "  • Average mean parse time: " . formatTime($overallMean) . PHP_EOL;
echo "  • Fastest pattern: " . $fastestPattern . " (" . formatTime(
                $results[$fastestPattern]['stats']['mean']
        ) . ")" . PHP_EOL;
echo "  • Slowest pattern: " . $slowestPattern . " (" . formatTime(
                $results[$slowestPattern]['stats']['mean']
        ) . ")" . PHP_EOL;
echo PHP_EOL;

echo "Memory:" . PHP_EOL;
echo "  • Memory at start: " . formatBytes($memoryStart) . PHP_EOL;
echo "  • Memory at end: " . formatBytes($memoryEnd) . PHP_EOL;
echo "  • Peak memory: " . formatBytes($memoryPeak) . PHP_EOL;
echo PHP_EOL;

// Performance by category
$categories = [
        'Simple' => ['simple_text', 'simple_placeholder', 'simple_numbered', 'multiple_placeholders'],
        'Escaped' => ['escaped_apostrophe', 'escaped_braces', 'complex_escaping'],
        'Formatting' => [
                'number_format',
                'currency_format',
                'percent_format',
                'date_format',
                'time_format',
                'datetime_format'
        ],
        'Plural' => ['plural_simple', 'plural_with_offset', 'plural_categories', 'plural_explicit'],
        'Select' => ['select_simple', 'select_detailed', 'selectordinal_simple', 'selectordinal_full'],
        'Nested' => ['nested_select_plural', 'nested_plural_select', 'deeply_nested'],
        'Real-world' => ['real_world_notification', 'real_world_purchase', 'real_world_time_ago'],
        'Edge cases' => ['many_arguments', 'long_text', 'unicode_content', 'mixed_unicode'],
        'Choice style' => ['choice_simple', 'choice_complex', 'choice_infinity'],
];

echo "Performance by Category:" . PHP_EOL;
printSeparator(80);
echo str_pad('Category', 20) . ' │ ';
echo str_pad('Avg Mean', 11, ' ', STR_PAD_LEFT) . ' │ ';
echo str_pad('Avg Min', 11, ' ', STR_PAD_LEFT) . ' │ ';
echo str_pad('Avg Max', 11, ' ', STR_PAD_LEFT) . PHP_EOL;
printSeparator(80);

foreach ($categories as $category => $patternNames) {
    $categoryResults = array_filter($results, fn($k) => in_array($k, $patternNames), ARRAY_FILTER_USE_KEY);
    if (empty($categoryResults)) {
        continue;
    }

    $avgMean = array_sum(array_map(fn($r) => $r['stats']['mean'], $categoryResults)) / count($categoryResults);
    $avgMin = array_sum(array_map(fn($r) => $r['stats']['min'], $categoryResults)) / count($categoryResults);
    $avgMax = array_sum(array_map(fn($r) => $r['stats']['max'], $categoryResults)) / count($categoryResults);

    echo str_pad($category, 20) . ' │ ';
    echo str_pad(formatTime($avgMean), 12, ' ', STR_PAD_LEFT) . ' │ ';
    echo str_pad(formatTime($avgMin), 12, ' ', STR_PAD_LEFT) . ' │ ';
    echo str_pad(formatTime($avgMax), 12, ' ', STR_PAD_LEFT) . PHP_EOL;
}

printSeparator(80);
echo PHP_EOL;

// Throughput calculation
echo "Throughput Analysis:" . PHP_EOL;
printSeparator(60);

$totalParseTime = array_sum(array_map(fn($r) => $r['stats']['total'], $results));
$opsPerSecond = ($totalPatterns * $iterations) / ($totalParseTime / 1000000);
echo "  • Operations per second: " . number_format($opsPerSecond) . " ops/s" . PHP_EOL;

// Average characters per microsecond
$totalChars = array_sum(array_map(fn($r) => $r['patternLength'] * $iterations, $results));
$charsPerMicrosecond = $totalChars / $totalParseTime;
echo "  • Characters processed: " . number_format($charsPerMicrosecond * 1000000) . " chars/s" . PHP_EOL;

printSeparator(60);
echo PHP_EOL;

// Top 5 fastest and slowest
echo "Top 5 Fastest Patterns:" . PHP_EOL;
$i = 0;
foreach ($sortedByMean as $name => $result) {
    if ($i >= 5) {
        break;
    }
    echo "  " . ($i + 1) . ". " . $name . " (" . formatTime($result['stats']['mean']) . ")" . PHP_EOL;
    $i++;
}
echo PHP_EOL;

echo "Top 5 Slowest Patterns:" . PHP_EOL;
$sortedDesc = array_reverse($sortedByMean, true);
$i = 0;
foreach ($sortedDesc as $name => $result) {
    if ($i >= 5) {
        break;
    }
    echo "  " . ($i + 1) . ". " . $name . " (" . formatTime($result['stats']['mean']) . ")" . PHP_EOL;
    $i++;
}
echo PHP_EOL;

// Correlation analysis: pattern length vs parse time
echo "Correlation Analysis (Pattern Length vs Parse Time):" . PHP_EOL;
$lengths = array_map(fn($r) => $r['patternLength'], $results);
$means = array_map(fn($r) => $r['stats']['mean'], $results);

$avgLength = array_sum($lengths) / count($lengths);
$avgMean = array_sum($means) / count($means);

$covariance = 0;
$varLength = 0;
$varMean = 0;
$keys = array_keys($results);

for ($i = 0; $i < count($keys); $i++) {
    $l = $results[$keys[$i]]['patternLength'];
    $m = $results[$keys[$i]]['stats']['mean'];
    $covariance += ($l - $avgLength) * ($m - $avgMean);
    $varLength += pow($l - $avgLength, 2);
    $varMean += pow($m - $avgMean, 2);
}

$correlation = ($varLength > 0 && $varMean > 0) ? $covariance / sqrt($varLength * $varMean) : 0;

// Linear regression: y = mx + b (where y = parse time in µs, x = pattern length)
$slope = ($varLength > 0) ? $covariance / $varLength : 0;
$intercept = $avgMean - ($slope * $avgLength);

echo "  • Pearson correlation coefficient: " . number_format($correlation, 4) . PHP_EOL;
echo "  • Linear regression equation: time(µs) = " . number_format($slope, 4) . " × length + " . number_format(
                $intercept,
                4
        ) . PHP_EOL;
echo "  • Meaning: Each additional character adds ~" . number_format(
                $slope,
                2
        ) . " µs, with a base overhead of ~" . number_format($intercept, 2) . " µs" . PHP_EOL;
echo "  • Interpretation: " . match (true) {
            abs($correlation) >= 0.7 => "Strong correlation",
            abs($correlation) >= 0.4 => "Moderate correlation",
            abs($correlation) >= 0.2 => "Weak correlation",
            default => "Very weak or no correlation"
        } . " between pattern length and parse time." . PHP_EOL;

echo PHP_EOL;
echo "Benchmark complete!" . PHP_EOL;
echo PHP_EOL;


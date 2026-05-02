<?php
/**
 * Tool: slow queries report (CLI)
 *
 * Reads logs/slow_queries.log (one JSON entry per line, written by
 * Database::query() when a query exceeds SLOW_QUERY_THRESHOLD_MS) and
 * aggregates by *normalized SQL pattern* (numbers and string literals
 * replaced with `?`, whitespace collapsed).
 *
 * Output columns:
 *   count, total_ms, avg_ms, max_ms, p95_ms, sample_caller, pattern
 *
 * Usage:
 *   php tools/slow_queries_report.php [--top=N] [--since=YYYY-MM-DD] [--reset]
 *
 * Flags:
 *   --top=N            Limit output to top-N rows by total_ms (default 20)
 *   --since=YYYY-MM-DD Only include entries with ts >= this date
 *   --reset            Truncate logs/slow_queries.log (asks confirmation)
 *   --help             Show this help
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') {
    http_response_code(403);
    echo "This tool is CLI-only.\n";
    exit(1);
}

$opts = getopt('', ['top::', 'since::', 'reset', 'help']);

if (isset($opts['help'])) {
    echo <<<TXT
Usage: php tools/slow_queries_report.php [--top=N] [--since=YYYY-MM-DD] [--reset]

Options:
  --top=N              Top-N rows ranked by total_ms (default 20)
  --since=YYYY-MM-DD   Only include entries with ts >= this date
  --reset              Truncate logs/slow_queries.log (asks confirmation)
  --help               Show this help

TXT;
    exit(0);
}

$logFile = dirname(__DIR__) . '/logs/slow_queries.log';

// --- --reset mode ---------------------------------------------------------
if (array_key_exists('reset', $opts)) {
    if (!is_file($logFile)) {
        echo "Nothing to reset: {$logFile} does not exist.\n";
        exit(0);
    }
    $size = (int) @filesize($logFile);
    echo "About to truncate {$logFile} ({$size} bytes).\n";
    echo "Type 'yes' to confirm: ";
    $answer = trim((string) fgets(STDIN));
    if (strtolower($answer) !== 'yes') {
        echo "Aborted.\n";
        exit(1);
    }
    $fp = @fopen($logFile, 'w');
    if ($fp === false) {
        fwrite(STDERR, "Cannot open log file for truncation: {$logFile}\n");
        exit(1);
    }
    fclose($fp);
    echo "Truncated.\n";
    exit(0);
}

$top = isset($opts['top']) ? max(1, (int) $opts['top']) : 20;
$sinceTs = null;
if (isset($opts['since']) && $opts['since'] !== '') {
    $sinceTs = strtotime($opts['since'] . 'T00:00:00');
    if ($sinceTs === false) {
        fwrite(STDERR, "Invalid --since date: {$opts['since']} (expected YYYY-MM-DD)\n");
        exit(1);
    }
}

if (!is_file($logFile)) {
    echo "No slow queries log found at: {$logFile}\n";
    echo "(File is created on first slow query exceeding SLOW_QUERY_THRESHOLD_MS.)\n";
    exit(0);
}

$fp = @fopen($logFile, 'r');
if ($fp === false) {
    fwrite(STDERR, "Cannot open log file: {$logFile}\n");
    exit(1);
}

$buckets = [];
$totalRows = 0;
$skippedRows = 0;
$filteredOut = 0;

while (($line = fgets($fp)) !== false) {
    $line = trim($line);
    if ($line === '') continue;

    $entry = json_decode($line, true);
    if (!is_array($entry) || !isset($entry['duration_ms'], $entry['sql'])) {
        $skippedRows++;
        continue;
    }
    if ($sinceTs !== null) {
        $entryTs = isset($entry['ts']) ? strtotime((string) $entry['ts']) : false;
        if ($entryTs === false || $entryTs < $sinceTs) {
            $filteredOut++;
            continue;
        }
    }

    $totalRows++;

    $pattern = normalizeSql((string) $entry['sql']);
    $caller  = (string) ($entry['caller'] ?? '');
    $ms      = (float) $entry['duration_ms'];

    if (!isset($buckets[$pattern])) {
        $buckets[$pattern] = [
            'pattern'       => $pattern,
            'sample_caller' => $caller,
            'count'         => 0,
            'total_ms'      => 0.0,
            'max_ms'        => 0.0,
            'samples'       => [],
        ];
    }
    $buckets[$pattern]['count']++;
    $buckets[$pattern]['total_ms'] += $ms;
    if ($ms > $buckets[$pattern]['max_ms']) {
        $buckets[$pattern]['max_ms'] = $ms;
    }
    if ($buckets[$pattern]['sample_caller'] === '' && $caller !== '') {
        $buckets[$pattern]['sample_caller'] = $caller;
    }
    $buckets[$pattern]['samples'][] = $ms;
}
fclose($fp);

if (empty($buckets)) {
    echo "No slow query entries to report";
    if ($sinceTs !== null) echo " since " . date('Y-m-d', $sinceTs);
    echo ".\n";
    if ($filteredOut > 0) echo "({$filteredOut} entries filtered out by --since)\n";
    if ($skippedRows > 0) echo "({$skippedRows} malformed lines skipped)\n";
    exit(0);
}

foreach ($buckets as &$b) {
    sort($b['samples'], SORT_NUMERIC);
    $n = count($b['samples']);
    $b['avg_ms'] = $b['total_ms'] / $n;
    $idx = (int) ceil(0.95 * $n) - 1;
    if ($idx < 0) $idx = 0;
    if ($idx >= $n) $idx = $n - 1;
    $b['p95_ms'] = $b['samples'][$idx];
    unset($b['samples']);
}
unset($b);

usort($buckets, fn($a, $b) => $b['total_ms'] <=> $a['total_ms']);
$rows = array_slice($buckets, 0, $top);

echo "Slow query report\n";
echo str_repeat('=', 80) . "\n";
echo "Source:       {$logFile}\n";
echo "Entries:      {$totalRows} total";
if ($sinceTs !== null) echo " (since " . date('Y-m-d', $sinceTs) . ", {$filteredOut} filtered out)";
if ($skippedRows > 0)  echo ", {$skippedRows} malformed";
echo "\n";
echo "Patterns:     " . count($buckets) . "\n";
echo "Top:          {$top} (ranked by total_ms)\n\n";

renderTable($rows);

function normalizeSql(string $sql): string {
    // Strip block/line comments
    $s = preg_replace('!/\*.*?\*/!s', ' ', $sql) ?? $sql;
    $s = preg_replace('/--[^\n]*/', ' ', $s) ?? $s;

    // Replace single-quoted string literals with ?
    $s = preg_replace("/'(?:''|[^'])*'/", '?', $s) ?? $s;
    // Replace double-quoted string literals with ?
    $s = preg_replace('/"(?:""|[^"])*"/', '?', $s) ?? $s;
    // Replace numeric literals with ?
    $s = preg_replace('/\b\d+(?:\.\d+)?\b/', '?', $s) ?? $s;

    // Collapse whitespace
    $s = preg_replace('/\s+/', ' ', $s) ?? $s;

    return trim($s);
}

function renderTable(array $rows): void {
    if (empty($rows)) {
        echo "(no rows)\n";
        return;
    }
    $headers = ['#', 'count', 'total_ms', 'avg_ms', 'p95_ms', 'max_ms', 'sample_caller', 'pattern'];
    $widths  = [3, 7, 11, 10, 10, 10, 32, 80];

    printRow($headers, $widths);
    echo str_repeat('-', array_sum($widths) + count($widths) * 3) . "\n";

    foreach ($rows as $i => $r) {
        $pattern = (string) $r['pattern'];
        if (mb_strlen($pattern) > $widths[7]) {
            $pattern = mb_substr($pattern, 0, $widths[7] - 3) . '...';
        }
        $caller = (string) $r['sample_caller'];
        if (mb_strlen($caller) > $widths[6]) {
            $caller = mb_substr($caller, 0, $widths[6] - 3) . '...';
        }
        printRow([
            (string) ($i + 1),
            (string) $r['count'],
            number_format($r['total_ms'], 1),
            number_format($r['avg_ms'], 1),
            number_format($r['p95_ms'], 1),
            number_format($r['max_ms'], 1),
            $caller,
            $pattern,
        ], $widths);
    }
}

function printRow(array $cells, array $widths): void {
    $parts = [];
    $last = count($cells) - 1;
    foreach ($cells as $i => $cell) {
        $padDir = ($i === $last || $i === $last - 1) ? STR_PAD_RIGHT : STR_PAD_LEFT;
        $parts[] = str_pad((string) $cell, $widths[$i], ' ', $padDir);
    }
    echo implode(' | ', $parts) . "\n";
}

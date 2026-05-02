<?php
/**
 * Embedding cache maintenance / stats CLI tool.
 *
 * Usage:
 *   php tools/embedding_cache_stats.php                  # report only
 *   php tools/embedding_cache_stats.php --prune-older=30 # delete entries with last_hit_at < NOW()-30d (or NULL & created>30d)
 *   php tools/embedding_cache_stats.php --top=50         # show top N by hit_count (default 20)
 *   php tools/embedding_cache_stats.php --dry-run        # with --prune-older: count only, don't delete
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "This tool is CLI-only.\n";
    exit(1);
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';

function parse_args(array $argv): array {
    $opts = ['prune_older' => null, 'top' => 20, 'dry_run' => false];
    foreach (array_slice($argv, 1) as $a) {
        if ($a === '--dry-run') { $opts['dry_run'] = true; continue; }
        if (preg_match('/^--prune-older=(\d+)$/', $a, $m)) { $opts['prune_older'] = (int)$m[1]; continue; }
        if (preg_match('/^--top=(\d+)$/', $a, $m)) { $opts['top'] = max(1, (int)$m[1]); continue; }
        if ($a === '--help' || $a === '-h') {
            echo "Usage: php tools/embedding_cache_stats.php [--prune-older=DAYS] [--top=N] [--dry-run]\n";
            exit(0);
        }
    }
    return $opts;
}

$opts = parse_args($argv ?? []);

try {
    $db = Database::getInstance();
} catch (\Throwable $e) {
    fwrite(STDERR, 'DB connection failed: ' . $e->getMessage() . "\n");
    exit(2);
}

// Verify table exists
try {
    $exists = $db->fetchOne(
        "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'embedding_cache' LIMIT 1"
    );
    if (!$exists) {
        fwrite(STDERR, "Table embedding_cache not found. Run migration 88_embedding_cache.sql first.\n");
        exit(3);
    }
} catch (\Throwable $e) {
    fwrite(STDERR, 'Schema check failed: ' . $e->getMessage() . "\n");
    exit(2);
}

echo str_repeat('=', 60) . "\n";
echo "Embedding Cache Stats\n";
echo str_repeat('=', 60) . "\n";

$summary = $db->fetchOne(
    "SELECT
        COUNT(*) AS total_entries,
        COALESCE(SUM(LENGTH(vec)), 0) AS total_bytes,
        COALESCE(SUM(hit_count), 0) AS total_hits,
        COUNT(DISTINCT model) AS distinct_models,
        MIN(created_at) AS oldest,
        MAX(created_at) AS newest
     FROM embedding_cache"
);

$totalEntries = (int)($summary['total_entries'] ?? 0);
$totalBytes = (int)($summary['total_bytes'] ?? 0);
$totalHits = (int)($summary['total_hits'] ?? 0);

printf("Total entries:    %d\n", $totalEntries);
printf("Total cache size: %.2f MB (%.2f KB avg/entry)\n",
    $totalBytes / (1024 * 1024),
    $totalEntries > 0 ? ($totalBytes / 1024 / $totalEntries) : 0
);
printf("Total hits:       %d\n", $totalHits);
printf("Distinct models:  %d\n", (int)($summary['distinct_models'] ?? 0));
printf("Oldest entry:     %s\n", $summary['oldest'] ?? 'n/a');
printf("Newest entry:     %s\n", $summary['newest'] ?? 'n/a');

echo "\nBy model:\n";
$byModel = $db->fetchAll(
    "SELECT model, COUNT(*) AS entries, COALESCE(SUM(hit_count), 0) AS hits, COALESCE(SUM(LENGTH(vec)), 0) AS bytes
     FROM embedding_cache
     GROUP BY model
     ORDER BY entries DESC"
);
foreach ($byModel as $row) {
    printf("  %-40s entries=%-8d hits=%-8d size=%.2f MB\n",
        (string)$row['model'],
        (int)$row['entries'],
        (int)$row['hits'],
        ((int)$row['bytes']) / (1024 * 1024)
    );
}

$top = (int)$opts['top'];
echo "\nTop {$top} by hit_count:\n";
$topRows = $db->fetchAll(
    "SELECT SUBSTRING(text_hash, 1, 12) AS hash, model, dim, hit_count, last_hit_at, created_at
     FROM embedding_cache
     ORDER BY hit_count DESC, last_hit_at DESC
     LIMIT " . $top
);
foreach ($topRows as $row) {
    printf("  %s... model=%-30s dim=%-5d hits=%-6d last=%s\n",
        (string)$row['hash'],
        (string)$row['model'],
        (int)$row['dim'],
        (int)$row['hit_count'],
        (string)($row['last_hit_at'] ?? 'never')
    );
}

// Dead entries (never hit, or last hit older than threshold)
$deadThresholdDays = $opts['prune_older'] !== null ? (int)$opts['prune_older'] : 30;
$dead = $db->fetchOne(
    "SELECT COUNT(*) AS cnt, COALESCE(SUM(LENGTH(vec)), 0) AS bytes
     FROM embedding_cache
     WHERE (last_hit_at IS NULL AND created_at < (NOW() - INTERVAL ? DAY))
        OR last_hit_at < (NOW() - INTERVAL ? DAY)",
    [$deadThresholdDays, $deadThresholdDays]
);
$deadCnt = (int)($dead['cnt'] ?? 0);
$deadBytes = (int)($dead['bytes'] ?? 0);
echo "\nDead entries (no hits in last {$deadThresholdDays} days): {$deadCnt} ({$deadBytes} bytes / "
    . sprintf('%.2f MB', $deadBytes / (1024 * 1024)) . ")\n";

if ($opts['prune_older'] !== null) {
    $days = (int)$opts['prune_older'];
    if ($opts['dry_run']) {
        echo "\nDRY RUN: would delete {$deadCnt} entries (last_hit older than {$days} days).\n";
    } else {
        echo "\nPruning entries with last_hit_at older than {$days} days...\n";
        $stmt = $db->query(
            "DELETE FROM embedding_cache
             WHERE (last_hit_at IS NULL AND created_at < (NOW() - INTERVAL ? DAY))
                OR last_hit_at < (NOW() - INTERVAL ? DAY)",
            [$days, $days]
        );
        $deleted = $stmt->rowCount();
        echo "Deleted: {$deleted} rows.\n";
    }
}

echo "\nDone.\n";
exit(0);

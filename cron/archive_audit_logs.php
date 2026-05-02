#!/usr/bin/env php
<?php
/**
 * Cron Job: Archive old audit_logs rows into audit_logs_archive
 *
 * Moves rows older than --older-than-days from audit_logs to audit_logs_archive
 * in transactional batches. Default behavior is DRY-RUN (no DB changes); pass
 * --apply to actually move rows.
 *
 * Schedule (suggested): weekly, Sunday 03:00.
 *   Linux:   0 3 * * 0 /usr/bin/php /path/to/CollaboraNexio/cron/archive_audit_logs.php --apply
 *   Windows: schtasks /Create /SC WEEKLY /D SUN /ST 03:00 /TN "CNX Audit Archive" ^
 *              /TR "C:\xampp\php\php.exe C:\xampp\htdocs\CollaboraNexio\cron\archive_audit_logs.php --apply"
 *
 * CLI flags:
 *   --older-than-days=N  rows with created_at < NOW() - INTERVAL N DAY (default 90)
 *   --batch-size=N       rows per transaction (default 1000)
 *   --max-batches=N      safety cap on batches per run (default 50)
 *   --apply              perform writes (default is dry-run)
 *   --dry-run            explicit dry-run (default behavior, kept for clarity)
 *   --help               print usage
 *
 * Output: JSON-line summary on stdout at end of run.
 *
 * Safety:
 *   - Snapshots ids BEFORE INSERT/DELETE so concurrent INSERTs into audit_logs
 *     are never touched (their ids are higher than the snapshot).
 *   - Each batch runs in its own transaction; rollback fires BEFORE error_log
 *     (CLAUDE.md rule).
 *   - flock(LOCK_EX|LOCK_NB) prevents two concurrent runs.
 *   - PDO uses positional placeholders only (BUG-148c).
 *   - Column list is detected from information_schema (BUG-156) so the script
 *     adapts if audit_logs gains new columns post-migration.
 *
 * @author CollaboraNexio
 */

if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

date_default_timezone_set('Europe/Rome');

require_once __DIR__ . '/../includes/db.php';

// ----------------------------------------------------------------------------
// CLI args
// ----------------------------------------------------------------------------

$opts = [
    'older-than-days' => 90,
    'batch-size'      => 1000,
    'max-batches'     => 50,
    'apply'           => false,
    'dry-run'         => false,
];

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--help' || $arg === '-h') {
        echo "Usage: php archive_audit_logs.php [--older-than-days=90] [--batch-size=1000] [--max-batches=50] [--apply|--dry-run]\n";
        exit(0);
    }
    if ($arg === '--apply') { $opts['apply'] = true; continue; }
    if ($arg === '--dry-run') { $opts['dry-run'] = true; continue; }
    if (preg_match('/^--([a-z\-]+)=(.+)$/', $arg, $m)) {
        $key = $m[1];
        if (array_key_exists($key, $opts)) {
            $opts[$key] = is_int($opts[$key]) ? (int)$m[2] : $m[2];
        }
    }
}

$olderThanDays = max(1, (int)$opts['older-than-days']);
$batchSize     = max(1, min(10000, (int)$opts['batch-size']));
$maxBatches    = max(1, min(10000, (int)$opts['max-batches']));
$apply         = (bool)$opts['apply'] && !((bool)$opts['dry-run']);
$mode          = $apply ? 'apply' : 'dry-run';

// ----------------------------------------------------------------------------
// Lock (prevent concurrent runs)
// ----------------------------------------------------------------------------

$lockDir = __DIR__ . '/../logs';
if (!is_dir($lockDir)) { @mkdir($lockDir, 0775, true); }
$lockPath = $lockDir . '/audit_archive.lock';
$lockFp = @fopen($lockPath, 'c');
if (!$lockFp || !@flock($lockFp, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "Another archive run is in progress (lock: {$lockPath}). Exiting.\n");
    exit(2);
}
register_shutdown_function(function () use ($lockFp, $lockPath) {
    if (is_resource($lockFp)) {
        @flock($lockFp, LOCK_UN);
        @fclose($lockFp);
    }
});

// ----------------------------------------------------------------------------
// DB
// ----------------------------------------------------------------------------

$startMs = (int)(microtime(true) * 1000);
$errors  = [];
$rowsArchived = 0;
$batchesRun   = 0;

try {
    $db = Database::getInstance();
} catch (Throwable $e) {
    $payload = [
        'ts'              => date('c'),
        'mode'            => $mode,
        'older_than_days' => $olderThanDays,
        'batch_size'      => $batchSize,
        'max_batches'     => $maxBatches,
        'batches_run'     => 0,
        'rows_archived'   => 0,
        'duration_ms'     => (int)(microtime(true) * 1000) - $startMs,
        'errors'          => ['db_init: ' . $e->getMessage()],
    ];
    echo json_encode($payload, JSON_UNESCAPED_SLASHES) . "\n";
    error_log('[audit_archive] db_init failed: ' . $e->getMessage());
    exit(3);
}

// ----------------------------------------------------------------------------
// Detect column list shared by audit_logs and audit_logs_archive (BUG-156)
// ----------------------------------------------------------------------------

function getCommonColumns(Database $db): array {
    $rows = $db->fetchAll(
        "SELECT TABLE_NAME, COLUMN_NAME, ORDINAL_POSITION
           FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME IN ('audit_logs', 'audit_logs_archive')",
        []
    );
    $byTable = ['audit_logs' => [], 'audit_logs_archive' => []];
    foreach ($rows as $r) {
        $byTable[$r['TABLE_NAME']][] = $r['COLUMN_NAME'];
    }
    if (empty($byTable['audit_logs']) || empty($byTable['audit_logs_archive'])) {
        return [];
    }
    // Intersect, preserving audit_logs ordering for stability.
    $archSet = array_flip($byTable['audit_logs_archive']);
    $common = [];
    foreach ($byTable['audit_logs'] as $col) {
        if (isset($archSet[$col])) { $common[] = $col; }
    }
    return $common;
}

$columns = getCommonColumns($db);
if (empty($columns)) {
    $payload = [
        'ts'              => date('c'),
        'mode'            => $mode,
        'older_than_days' => $olderThanDays,
        'batch_size'      => $batchSize,
        'max_batches'     => $maxBatches,
        'batches_run'     => 0,
        'rows_archived'   => 0,
        'duration_ms'     => (int)(microtime(true) * 1000) - $startMs,
        'errors'          => ['schema: audit_logs or audit_logs_archive missing (run migration 89?)'],
    ];
    echo json_encode($payload, JSON_UNESCAPED_SLASHES) . "\n";
    error_log('[audit_archive] schema missing: audit_logs_archive not found');
    exit(4);
}

$colListBacktick = '`' . implode('`, `', $columns) . '`';
$colListSelect   = $colListBacktick;

// ----------------------------------------------------------------------------
// Batch loop
// ----------------------------------------------------------------------------

for ($i = 0; $i < $maxBatches; $i++) {
    // 1) Snapshot ids
    try {
        $rows = $db->fetchAll(
            "SELECT id FROM audit_logs
              WHERE created_at < (NOW() - INTERVAL ? DAY)
              ORDER BY id
              LIMIT " . $batchSize,
            [$olderThanDays]
        );
    } catch (Throwable $e) {
        $errors[] = 'snapshot: ' . $e->getMessage();
        error_log('[audit_archive] snapshot failed: ' . $e->getMessage());
        break;
    }

    if (empty($rows)) {
        break; // nothing left to archive
    }

    $ids = array_map(static fn($r) => (int)$r['id'], $rows);
    $minId = $ids[0];
    $maxId = $ids[count($ids) - 1];
    $count = count($ids);

    if (!$apply) {
        // Dry-run: report what we WOULD do, but do not advance state.
        $msg = sprintf(
            "DRY-RUN: batch %d would archive %d rows (id range: %d..%d)",
            $i + 1, $count, $minId, $maxId
        );
        echo $msg . "\n";
        $rowsArchived += $count;
        $batchesRun++;
        // In dry-run we must stop after one batch — otherwise the same rows
        // would be reported in every iteration (we never DELETE them).
        break;
    }

    // 2) Apply: transactional INSERT ... SELECT + DELETE
    $placeholders = implode(',', array_fill(0, $count, '?'));
    $insertSql = "INSERT INTO audit_logs_archive ({$colListBacktick}, archived_at)
                  SELECT {$colListSelect}, NOW() FROM audit_logs
                  WHERE id IN ({$placeholders})";
    $deleteSql = "DELETE FROM audit_logs WHERE id IN ({$placeholders})";

    try {
        $db->beginTransaction();
        $db->query($insertSql, $ids);
        $db->query($deleteSql, $ids);
        $db->commit();
    } catch (Throwable $e) {
        // ROLLBACK FIRST (CLAUDE.md critical rule) THEN log.
        try {
            if ($db->inTransaction()) { $db->rollback(); }
        } catch (Throwable $e2) {
            error_log('[audit_archive] rollback also failed: ' . $e2->getMessage());
        }
        $errors[] = 'batch ' . ($i + 1) . ': ' . $e->getMessage();
        error_log('[audit_archive] batch ' . ($i + 1) . ' failed: ' . $e->getMessage());
        break; // stop on first error to avoid cascading damage
    }

    $rowsArchived += $count;
    $batchesRun++;

    // Anti-pressure pause between batches (200ms)
    usleep(200000);
}

// ----------------------------------------------------------------------------
// Summary
// ----------------------------------------------------------------------------

$payload = [
    'ts'              => date('c'),
    'mode'            => $mode,
    'older_than_days' => $olderThanDays,
    'batch_size'      => $batchSize,
    'max_batches'     => $maxBatches,
    'batches_run'     => $batchesRun,
    'rows_archived'   => $rowsArchived,
    'duration_ms'     => (int)(microtime(true) * 1000) - $startMs,
    'errors'          => $errors,
];
$line = json_encode($payload, JSON_UNESCAPED_SLASHES);
echo $line . "\n";
error_log('[audit_archive] ' . $line);

exit(empty($errors) ? 0 : 5);

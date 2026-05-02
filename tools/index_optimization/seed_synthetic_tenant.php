<?php
/**
 * Synthetic data seeder for Migration 84 benchmark.
 *
 * Creates ~10,000 rows in `files`, `notifications`, `audit_logs` under
 * tenant_id = 99999 (sentinel), suitable for EXPLAIN ANALYZE before/after.
 *
 * 100% reversible via tools/index_optimization/cleanup_synthetic_tenant.php.
 *
 * Run: php tools/index_optimization/seed_synthetic_tenant.php
 */
declare(strict_types=1);

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/db.php';

if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') {
    http_response_code(403);
    echo "CLI only\n"; exit(1);
}

const SENTINEL_TENANT_ID = 99999;
const SENTINEL_TENANT_NAME = '__BENCH_99999__';
const ROWS_PER_TABLE = 10000;
const BATCH_SIZE = 500;

$pdo = Database::getInstance()->getConnection();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function out(string $msg): void { echo "[seed] $msg\n"; }

try {
    out("Begin seed for tenant_id=" . SENTINEL_TENANT_ID);

    // Refuse if sentinel data already partially exists — operator must clean up first.
    $existing = (int)$pdo->query(
        'SELECT COUNT(*) FROM files WHERE tenant_id=' . SENTINEL_TENANT_ID .
        ' UNION ALL SELECT COUNT(*) FROM notifications WHERE tenant_id=' . SENTINEL_TENANT_ID .
        ' UNION ALL SELECT COUNT(*) FROM audit_logs WHERE tenant_id=' . SENTINEL_TENANT_ID
    )->fetchColumn();
    if ($existing > 0) {
        out("ABORT: rows for tenant_id=" . SENTINEL_TENANT_ID . " already exist. Run cleanup first.");
        exit(2);
    }

    // 1) tenants marker (chk_tenant_fiscal_code requires codice_fiscale OR partita_iva)
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('INSERT INTO tenants (id, name, denominazione, status, partita_iva) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([SENTINEL_TENANT_ID, SENTINEL_TENANT_NAME, SENTINEL_TENANT_NAME, 'active', '00000000000']);
    $pdo->commit();
    out("Inserted tenants marker (id=" . SENTINEL_TENANT_ID . ")");

    // Pick an existing user_id for notifications.user_id
    $userId = (int)$pdo->query('SELECT id FROM users WHERE deleted_at IS NULL LIMIT 1')->fetchColumn();
    if ($userId === 0) {
        throw new RuntimeException('No active user found to use as notifications.user_id');
    }

    // 2) files (10000 rows). Mostly tenant_id=99999, deleted_at=NULL; ~5% soft-deleted.
    $pdo->beginTransaction();
    $valuesPerRow = '(?, ?, ?, ?, ?)'; // name, tenant_id, deleted_at, created_at, file_type
    for ($offset = 0; $offset < ROWS_PER_TABLE; $offset += BATCH_SIZE) {
        $batchN = min(BATCH_SIZE, ROWS_PER_TABLE - $offset);
        $placeholders = implode(',', array_fill(0, $batchN, $valuesPerRow));
        $stmt = $pdo->prepare(
            "INSERT INTO files (name, tenant_id, deleted_at, created_at, file_type) VALUES $placeholders"
        );
        $params = [];
        for ($i = 0; $i < $batchN; $i++) {
            $idx = $offset + $i;
            $params[] = sprintf('bench-file-%05d.txt', $idx);                    // name
            $params[] = SENTINEL_TENANT_ID;                                       // tenant_id
            $params[] = ($idx % 20 === 0) ? date('Y-m-d H:i:s') : null;          // deleted_at (5% soft-deleted)
            $params[] = date('Y-m-d H:i:s', time() - random_int(0, 30 * 86400)); // created_at within last 30 days
            $params[] = 'text/plain';                                             // file_type
        }
        $stmt->execute($params);
    }
    $pdo->commit();
    out("Inserted " . ROWS_PER_TABLE . " rows into files");

    // 3) notifications (10000 rows)
    $pdo->beginTransaction();
    $valuesPerRow = '(?, ?, ?, ?, ?, ?)'; // tenant_id, user_id, type, title, message, created_at
    for ($offset = 0; $offset < ROWS_PER_TABLE; $offset += BATCH_SIZE) {
        $batchN = min(BATCH_SIZE, ROWS_PER_TABLE - $offset);
        $placeholders = implode(',', array_fill(0, $batchN, $valuesPerRow));
        $stmt = $pdo->prepare(
            "INSERT INTO notifications (tenant_id, user_id, type, title, message, created_at) VALUES $placeholders"
        );
        $params = [];
        for ($i = 0; $i < $batchN; $i++) {
            $idx = $offset + $i;
            $params[] = SENTINEL_TENANT_ID;
            $params[] = $userId;
            $params[] = 'system';
            $params[] = 'Bench notification #' . $idx;
            $params[] = 'Synthetic notification for index benchmark';
            $params[] = date('Y-m-d H:i:s', time() - random_int(0, 30 * 86400));
        }
        $stmt->execute($params);
    }
    $pdo->commit();
    out("Inserted " . ROWS_PER_TABLE . " rows into notifications");

    // 4) audit_logs (10000 rows)
    $pdo->beginTransaction();
    $valuesPerRow = '(?, ?, ?, ?, ?, ?)'; // tenant_id, user_id, action, entity_type, deleted_at, created_at
    for ($offset = 0; $offset < ROWS_PER_TABLE; $offset += BATCH_SIZE) {
        $batchN = min(BATCH_SIZE, ROWS_PER_TABLE - $offset);
        $placeholders = implode(',', array_fill(0, $batchN, $valuesPerRow));
        $stmt = $pdo->prepare(
            "INSERT INTO audit_logs (tenant_id, user_id, action, entity_type, deleted_at, created_at) VALUES $placeholders"
        );
        $params = [];
        for ($i = 0; $i < $batchN; $i++) {
            $idx = $offset + $i;
            $params[] = SENTINEL_TENANT_ID;
            $params[] = $userId;
            $params[] = 'create';
            $params[] = 'file';
            $params[] = ($idx % 20 === 0) ? date('Y-m-d H:i:s') : null; // 5% soft-deleted (if column exists)
            $params[] = date('Y-m-d H:i:s', time() - random_int(0, 30 * 86400));
        }
        $stmt->execute($params);
    }
    $pdo->commit();
    out("Inserted " . ROWS_PER_TABLE . " rows into audit_logs");

    // Verify
    $files = (int)$pdo->query('SELECT COUNT(*) FROM files WHERE tenant_id=' . SENTINEL_TENANT_ID)->fetchColumn();
    $notif = (int)$pdo->query('SELECT COUNT(*) FROM notifications WHERE tenant_id=' . SENTINEL_TENANT_ID)->fetchColumn();
    $audit = (int)$pdo->query('SELECT COUNT(*) FROM audit_logs WHERE tenant_id=' . SENTINEL_TENANT_ID)->fetchColumn();
    out("Verification: files=$files, notifications=$notif, audit_logs=$audit");

    if ($files !== ROWS_PER_TABLE || $notif !== ROWS_PER_TABLE || $audit !== ROWS_PER_TABLE) {
        out("WARNING: row counts do not match expected " . ROWS_PER_TABLE);
        exit(3);
    }

    out("Seed complete. Run benchmark, then cleanup_synthetic_tenant.php.");
    exit(0);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    out("ERROR: " . $e->getMessage());
    exit(1);
}

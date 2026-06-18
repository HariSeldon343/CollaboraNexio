<?php
/**
 * Cleanup synthetic data created by seed_synthetic_tenant.php.
 *
 * Removes ALL rows where tenant_id = 99999 from:
 *   - audit_logs
 *   - notifications
 *   - files
 *   - tenants (the marker row)
 *
 * Verifies COUNT(*)=0 on each table afterward; exits non-zero if any leftover.
 *
 * Run: php tools/index_optimization/cleanup_synthetic_tenant.php
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

$pdo = Database::getInstance()->getConnection();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function out(string $msg): void { echo "[cleanup] $msg\n"; }

try {
    out("Begin cleanup for tenant_id=" . SENTINEL_TENANT_ID);

    // Order: child tables first, then tenants marker.
    $deletions = [
        ['audit_logs',    'DELETE FROM audit_logs    WHERE tenant_id = ?'],
        ['notifications', 'DELETE FROM notifications WHERE tenant_id = ?'],
        ['files',         'DELETE FROM files         WHERE tenant_id = ?'],
    ];

    foreach ($deletions as [$tab, $sql]) {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare($sql);
        $stmt->execute([SENTINEL_TENANT_ID]);
        $rows = $stmt->rowCount();
        $pdo->commit();
        out("Deleted {$rows} rows from {$tab}");
    }

    // tenants marker — only if name matches (defense in depth: never delete tenants(99999) if it has a different name)
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('DELETE FROM tenants WHERE id = ? AND name = ?');
    $stmt->execute([SENTINEL_TENANT_ID, SENTINEL_TENANT_NAME]);
    $rows = $stmt->rowCount();
    $pdo->commit();
    out("Deleted {$rows} rows from tenants (marker)");

    // Verify
    $verify = [
        'audit_logs'    => (int)$pdo->query('SELECT COUNT(*) FROM audit_logs    WHERE tenant_id = ' . SENTINEL_TENANT_ID)->fetchColumn(),
        'notifications' => (int)$pdo->query('SELECT COUNT(*) FROM notifications WHERE tenant_id = ' . SENTINEL_TENANT_ID)->fetchColumn(),
        'files'         => (int)$pdo->query('SELECT COUNT(*) FROM files         WHERE tenant_id = ' . SENTINEL_TENANT_ID)->fetchColumn(),
        'tenants'       => (int)$pdo->query('SELECT COUNT(*) FROM tenants       WHERE id        = ' . SENTINEL_TENANT_ID)->fetchColumn(),
    ];

    out("Verification: " . json_encode($verify, JSON_UNESCAPED_SLASHES));
    foreach ($verify as $tab => $count) {
        if ($count !== 0) {
            out("FAILURE: {$tab} still has {$count} sentinel rows");
            exit(2);
        }
    }
    out("All sentinel data removed. Cleanup complete.");
    exit(0);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    out("ERROR: " . $e->getMessage());
    exit(1);
}

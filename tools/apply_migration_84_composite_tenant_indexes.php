<?php
/**
 * Tool: Apply migration 84 (Composite tenant pagination indexes)
 *
 * - Applies: database/migrations/84_composite_tenant_indexes.sql
 * - Idempotent: yes (information_schema.STATISTICS guards on every CREATE)
 * - Web: super_admin only (CSRF protected)
 * - CLI: allowed (prints JSON)
 */
declare(strict_types=1);

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$isCli = (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/sql_migration_runner.php';

$migrationFile = __DIR__ . '/../database/migrations/84_composite_tenant_indexes.sql';

/**
 * @return array{ok:bool,executed:int,errors:array<int,array{statement_preview:string,error:string}>}
 */
function cnx_apply_migration_84(): array {
    global $migrationFile;

    if (!is_file($migrationFile)) {
        return ['ok' => false, 'executed' => 0, 'errors' => [[
            'statement_preview' => 'N/A',
            'error' => 'Migration file not found: ' . $migrationFile,
        ]]];
    }

    $sql = file_get_contents($migrationFile);
    if ($sql === false || trim((string)$sql) === '') {
        return ['ok' => false, 'executed' => 0, 'errors' => [[
            'statement_preview' => 'N/A',
            'error' => 'Cannot read migration file: ' . $migrationFile,
        ]]];
    }

    $stmts = cnx_split_sql_statements((string)$sql);
    $executed = 0;
    $errors = [];

    $pdo = Database::getInstance()->getConnection();

    $oldErrMode = null;
    try { $oldErrMode = $pdo->getAttribute(PDO::ATTR_ERRMODE); } catch (Throwable $e) {}
    try { $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); } catch (Throwable $e) {}

    foreach ($stmts as $stmt) {
        $trimmed = ltrim($stmt);
        try {
            $isQuery = (bool)preg_match('/^(SELECT|SHOW|DESCRIBE|EXPLAIN|EXECUTE)\b/i', $trimmed);
            if ($isQuery) {
                $stmtObj = $pdo->query($stmt);
                if ($stmtObj === false) {
                    $info = $pdo->errorInfo();
                    throw new RuntimeException("SQL query failed ({$info[0]}/{$info[1]}): {$info[2]}");
                }
                try {
                    do {
                        try { $stmtObj->fetchAll(); } catch (Throwable $_e) { break; }
                    } while (method_exists($stmtObj, 'nextRowset') && $stmtObj->nextRowset());
                } catch (Throwable $e) {}
                try { $stmtObj->closeCursor(); } catch (Throwable $e) {}
            } else {
                $res = $pdo->exec($stmt);
                if ($res === false) {
                    $info = $pdo->errorInfo();
                    throw new RuntimeException("SQL exec failed ({$info[0]}/{$info[1]}): {$info[2]}");
                }
            }
            $executed++;
        } catch (Throwable $e) {
            $preview = preg_replace('/\\s+/', ' ', trim($stmt)) ?: trim($stmt);
            $errors[] = [
                'statement_preview' => function_exists('mb_substr')
                    ? (string)mb_substr($preview, 0, 220, 'UTF-8')
                    : substr($preview, 0, 220),
                'error' => $e->getMessage(),
            ];
            break;
        }
    }

    if ($oldErrMode !== null) {
        try { $pdo->setAttribute(PDO::ATTR_ERRMODE, $oldErrMode); } catch (Throwable $e) {}
    }

    return ['ok' => empty($errors), 'executed' => $executed, 'errors' => $errors];
}

if ($isCli) {
    header('Content-Type: application/json; charset=utf-8');
    $res = cnx_apply_migration_84();
    echo json_encode(['success' => $res['ok'], 'data' => $res], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

require_once __DIR__ . '/../includes/session_init.php';
require_once __DIR__ . '/../includes/auth_simple.php';

$auth = new Auth();
if (!$auth->checkAuth()) {
    header('Location: ../index.php?timeout=1');
    exit;
}
$u = $auth->getCurrentUser();
if (!$u || (($u['role'] ?? '') !== 'super_admin')) {
    header('Location: ../dashboard.php');
    exit;
}

$csrf = $auth->generateCSRFToken();
$ran = false;
$result = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string)($_POST['csrf_token'] ?? '');
    if (!$auth->verifyCSRFToken($token)) {
        $error = 'CSRF token non valido.';
    } else {
        $ran = true;
        $result = cnx_apply_migration_84();
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
<?php
    $pageTitle = 'Apply Migration 84 - Nexio';
    require __DIR__ . '/../includes/layout_head.php';
?>
<style>
  .wrap { padding: var(--space-6); max-width: 1000px; }
  .card { background: var(--color-white); border: 1px solid var(--color-gray-200); border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); }
  .card-h { padding: var(--space-4) var(--space-5); border-bottom: 1px solid var(--color-gray-200); display:flex; align-items:center; justify-content:space-between; gap: var(--space-3); }
  .card-b { padding: var(--space-5); }
  .muted { color: var(--color-gray-600); font-size: var(--text-sm); }
  pre { background: var(--color-gray-50); border: 1px solid var(--color-gray-200); border-radius: 10px; padding: 12px; overflow:auto; }
  .pill { display:inline-flex; align-items:center; gap:6px; padding:2px 8px; border-radius:999px; font-size:12px; font-weight:700; }
  .pill.ok { background:#ECFDF5; color:#065F46; }
  .pill.fail { background:#FEE2E2; color:#991B1B; }
</style>
</head>
<?php require __DIR__ . '/../includes/layout_start.php'; ?>
<div class="wrap">
  <div class="header">
    <h1 class="page-title">Apply migration 84 — Composite tenant pagination indexes</h1>
    <div class="muted">Adds 8 idempotent composite indexes on hot listing tables.</div>
  </div>
  <div class="card">
    <div class="card-h">
      <div>
        <div style="font-weight:700;">Target</div>
        <div class="muted"><code><?php echo htmlspecialchars((string)$migrationFile, ENT_QUOTES, 'UTF-8'); ?></code></div>
      </div>
      <form method="post" style="margin:0;">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
        <button type="submit" class="btn btn-primary btn-sm">Applica migrazione</button>
      </form>
    </div>
    <div class="card-b">
      <?php if ($error): ?>
        <div class="pill fail">FAIL</div>
        <div class="muted" style="margin-top:8px;"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
      <?php elseif ($ran && is_array($result)): ?>
        <div class="pill <?php echo ($result['ok'] ?? false) ? 'ok' : 'fail'; ?>"><?php echo ($result['ok'] ?? false) ? 'OK' : 'FAIL'; ?></div>
        <div class="muted" style="margin-top:8px;">Statement eseguiti: <strong><?php echo (int)($result['executed'] ?? 0); ?></strong>
        <?php if (!empty($result['errors'])): ?> — Errori: <strong><?php echo count($result['errors']); ?></strong><?php endif; ?>
        </div>
        <?php if (!empty($result['errors'])): ?>
          <h3 style="margin-top:16px;">Dettagli errore</h3>
          <pre><?php echo htmlspecialchars(json_encode($result['errors'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8'); ?></pre>
        <?php endif; ?>
      <?php else: ?>
        <div class="muted">Pronto. Clicca "Applica migrazione".</div>
      <?php endif; ?>
      <h3 style="margin-top:18px;">Uso CLI (opzionale)</h3>
      <pre>php tools/apply_migration_84_composite_tenant_indexes.php</pre>
      <div class="muted">Rollback CLI: <code>mysql -u root collaboranexio &lt; database/migrations/84_composite_tenant_indexes_rollback.sql</code></div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/layout_end.php'; ?>
</html>

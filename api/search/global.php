<?php
/**
 * Global Search API Endpoint
 * GET /api/search/global.php?q=<query>
 *
 * Searches across files, users, tickets, tasks (tenant-scoped + soft-delete-aware).
 * super_admin bypasses tenant isolation. CSRF-protected.
 *
 * Returns:
 *   {
 *     success: true,
 *     data: {
 *       results: { files: [...], users: [...], tickets: [...], tasks: [...] },
 *       total: <int>,
 *       query: "<echoed>"
 *     }
 *   }
 *
 * Each result row: { id, label, sublabel, url, icon }
 */
declare(strict_types=1);

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_auth.php';

initializeApiEnvironment();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
verifyApiAuthentication();

$userInfo  = getApiUserInfo();
$tenantId  = (int)($userInfo['tenant_id'] ?? 0);
$userRole  = (string)($userInfo['role'] ?? 'user');
$isSuper   = $userRole === 'super_admin';

verifyApiCsrfToken();

$q = trim((string)($_GET['q'] ?? ''));
if ($q === '' || mb_strlen($q) < 2) {
    api_success(['results' => ['files'=>[],'users'=>[],'tickets'=>[],'tasks'=>[]], 'total' => 0, 'query' => $q], 'OK');
}

$like  = '%' . $q . '%';
$limit = 6;
$base  = defined('BASE_URL') ? rtrim((string)BASE_URL, '/') : '';

$db = Database::getInstance();

// Helper: feature-detect a column existence (BUG-156 pattern)
$hasColumn = static function(string $table, string $column) use ($db): bool {
    static $cache = [];
    $key = "$table.$column";
    if (isset($cache[$key])) return $cache[$key];
    try {
        $row = $db->fetchOne(
            "SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?",
            [$table, $column]
        );
        return $cache[$key] = (bool)$row;
    } catch (\Throwable $e) {
        return $cache[$key] = false;
    }
};

$out = ['files'=>[], 'users'=>[], 'tickets'=>[], 'tasks'=>[]];

// ---- Files (folders + files) -------------------------------------------------
try {
    $tenantFilter = $isSuper ? '' : 'AND f.tenant_id = ?';
    $params = [$like];
    if (!$isSuper) $params[] = $tenantId;
    $params[] = $limit;
    $rows = $db->fetchAll(
        "SELECT f.id, f.name, f.is_folder, f.folder_id, f.tenant_id
         FROM files f
         WHERE f.deleted_at IS NULL
           AND f.name LIKE ?
           $tenantFilter
         ORDER BY f.is_folder DESC, f.updated_at DESC
         LIMIT ?",
        $params
    );
    foreach ($rows as $r) {
        $out['files'][] = [
            'id'       => (int)$r['id'],
            'label'    => (string)$r['name'],
            'sublabel' => $r['is_folder'] ? 'Cartella' : 'File',
            'icon'     => $r['is_folder'] ? 'folder' : 'file',
            'url'      => $base . '/files.php' . ($r['folder_id'] ? '?folder=' . (int)$r['folder_id'] : ''),
        ];
    }
} catch (\Throwable $e) { error_log('search.files: ' . $e->getMessage()); }

// ---- Users -------------------------------------------------------------------
try {
    $tenantFilter = $isSuper ? '' : 'AND u.tenant_id = ?';
    $params = [$like, $like];
    if (!$isSuper) $params[] = $tenantId;
    $params[] = $limit;
    $rows = $db->fetchAll(
        "SELECT u.id, u.name, u.email, u.role
         FROM users u
         WHERE u.deleted_at IS NULL
           AND (u.name LIKE ? OR u.email LIKE ?)
           $tenantFilter
         ORDER BY u.name ASC
         LIMIT ?",
        $params
    );
    foreach ($rows as $r) {
        $out['users'][] = [
            'id'       => (int)$r['id'],
            'label'    => (string)$r['name'],
            'sublabel' => (string)$r['email'] . ' · ' . (string)$r['role'],
            'icon'     => 'user',
            'url'      => $base . '/utenti.php?focus=' . (int)$r['id'],
        ];
    }
} catch (\Throwable $e) { error_log('search.users: ' . $e->getMessage()); }

// ---- Tickets -----------------------------------------------------------------
try {
    $tenantFilter = $isSuper ? '' : 'AND t.tenant_id = ?';
    $titleLike = '';
    $titleParams = [];
    if ($hasColumn('tickets', 'title')) {
        $titleLike = ' OR t.title LIKE ?';
        $titleParams[] = $like;
    }
    $params = [$like];
    array_push($params, ...$titleParams);
    if (!$isSuper) $params[] = $tenantId;
    $params[] = $limit;
    $rows = $db->fetchAll(
        "SELECT t.id, t.ticket_number" . ($hasColumn('tickets','title') ? ', t.title' : '') . ", t.status, t.urgency
         FROM tickets t
         WHERE t.deleted_at IS NULL
           AND (t.ticket_number LIKE ? $titleLike)
           $tenantFilter
         ORDER BY t.created_at DESC
         LIMIT ?",
        $params
    );
    foreach ($rows as $r) {
        $title = (string)($r['title'] ?? '');
        $out['tickets'][] = [
            'id'       => (int)$r['id'],
            'label'    => (string)$r['ticket_number'] . ($title !== '' ? ' · ' . $title : ''),
            'sublabel' => 'Stato: ' . (string)($r['status'] ?? '-') . ' · Urgenza: ' . (string)($r['urgency'] ?? '-'),
            'icon'     => 'ticket',
            'url'      => $base . '/ticket.php?id=' . (int)$r['id'],
        ];
    }
} catch (\Throwable $e) { error_log('search.tickets: ' . $e->getMessage()); }

// ---- Tasks -------------------------------------------------------------------
try {
    if ($hasColumn('tasks', 'title')) {
        $tenantFilter = $isSuper ? '' : 'AND tk.tenant_id = ?';
        $params = [$like];
        if (!$isSuper) $params[] = $tenantId;
        $params[] = $limit;
        $statusCol = $hasColumn('tasks', 'status') ? ', tk.status' : '';
        $rows = $db->fetchAll(
            "SELECT tk.id, tk.title $statusCol
             FROM tasks tk
             WHERE tk.deleted_at IS NULL
               AND tk.title LIKE ?
               $tenantFilter
             ORDER BY tk.created_at DESC
             LIMIT ?",
            $params
        );
        foreach ($rows as $r) {
            $out['tasks'][] = [
                'id'       => (int)$r['id'],
                'label'    => (string)$r['title'],
                'sublabel' => 'Stato: ' . (string)($r['status'] ?? '-'),
                'icon'     => 'task',
                'url'      => $base . '/tasks.php?focus=' . (int)$r['id'],
            ];
        }
    }
} catch (\Throwable $e) { error_log('search.tasks: ' . $e->getMessage()); }

$total = count($out['files']) + count($out['users']) + count($out['tickets']) + count($out['tasks']);

api_success([
    'results' => $out,
    'total'   => $total,
    'query'   => $q,
], 'OK');

<?php
/**
 * Quick smoke test for the email_outbox queue.
 *
 * Enqueues a fake email, claims it, marks it sent. Verifies state transitions.
 * Cleans up after itself (DELETE).
 *
 * Run: php tools/test_email_outbox.php
 */
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/email_outbox.php';

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }

$db = Database::getInstance();
$marker = '__OUTBOX_TEST_' . uniqid() . '@example.invalid';

echo "[test] enqueue...\n";
$ok = enqueueEmail($marker, 'outbox smoke test', '<p>hello</p>', '', [
    'context' => ['action' => 'smoke_test'],
    'priority' => 5,
]);
if (!$ok) { exit("[test] FAIL: enqueue returned false\n"); }

$row = $db->fetchOne('SELECT * FROM email_outbox WHERE to_email = ?', [$marker]);
if (!$row) { exit("[test] FAIL: row not found after enqueue\n"); }
echo "[test] enqueued id={$row['id']} status={$row['status']} attempts={$row['attempts']}\n";
if ($row['status'] !== 'pending' || (int)$row['attempts'] !== 0) {
    exit("[test] FAIL: expected status=pending attempts=0\n");
}

echo "[test] claim batch...\n";
$batch = claimOutboxBatch(10);
$claimed = null;
foreach ($batch as $r) {
    if ($r['to_email'] === $marker) { $claimed = $r; break; }
}
if (!$claimed) { exit("[test] FAIL: row not claimed\n"); }
echo "[test] claimed id={$claimed['id']} status={$claimed['status']} attempts={$claimed['attempts']}\n";
if ($claimed['status'] !== 'sending' || (int)$claimed['attempts'] !== 1) {
    exit("[test] FAIL: expected status=sending attempts=1 after claim\n");
}

echo "[test] mark sent...\n";
markOutboxSent((int)$claimed['id']);
$row = $db->fetchOne('SELECT status, sent_at FROM email_outbox WHERE id = ?', [(int)$claimed['id']]);
if ($row['status'] !== 'sent' || empty($row['sent_at'])) {
    exit("[test] FAIL: expected status=sent with sent_at set\n");
}
echo "[test] sent ok\n";

echo "[test] simulate failure path...\n";
$fakeId = $db->insert('email_outbox', [
    'to_email' => $marker, 'subject' => 'fail-test', 'html_body' => '<p>x</p>',
    'status' => 'pending', 'priority' => 5,
]);
markOutboxFailed((int)$fakeId, 'simulated SMTP failure');
$row = $db->fetchOne('SELECT status, attempts, last_error, next_attempt_at FROM email_outbox WHERE id = ?', [(int)$fakeId]);
echo "[test] after failure: status={$row['status']} attempts={$row['attempts']} next_attempt_at={$row['next_attempt_at']}\n";
if ($row['status'] !== 'pending' || empty($row['last_error'])) {
    exit("[test] FAIL: expected status=pending with last_error after first failure\n");
}

// Cleanup
$db->delete('email_outbox', ['to_email' => $marker]);
$count = (int)$db->query('SELECT COUNT(*) FROM email_outbox WHERE to_email = ?', [$marker])->fetchColumn();
if ($count !== 0) { exit("[test] FAIL: cleanup left rows\n"); }

echo "[test] PASS — all transitions verified, no leftover rows\n";
exit(0);

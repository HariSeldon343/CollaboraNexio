<?php
/**
 * Cron worker: drain the email_outbox queue.
 *
 * Schedule: every 1-2 minutes via Windows Task Scheduler / cron.
 * Lock:     MySQL GET_LOCK('process_email_outbox', 0) so concurrent runs no-op.
 *
 * For each pending row whose next_attempt_at <= NOW():
 *   - set status='sending', attempts++
 *   - call sendEmail() with the deserialized payload
 *   - on success: status='sent', sent_at=NOW()
 *   - on failure:
 *       - if attempts < max_attempts: status='pending', next_attempt_at=now+backoff
 *       - else: status='failed', last_error=...
 *
 * CLI only.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') {
    http_response_code(403);
    echo "CLI only\n"; exit(1);
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/email_outbox.php';

const BATCH_SIZE = 25;
const MAX_RUN_SECONDS = 60;

function out(string $msg): void {
    echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n";
}

$db = Database::getInstance();
$pdo = $db->getConnection();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Acquire global lock — concurrent cron runs are a no-op.
$lockOk = (int)$pdo->query("SELECT GET_LOCK('process_email_outbox', 0)")->fetchColumn();
if ($lockOk !== 1) {
    out('Another worker is running; exiting.');
    exit(0);
}

$startedAt = time();
$processed = 0;
$ok = 0;
$failed = 0;

try {
    while ((time() - $startedAt) < MAX_RUN_SECONDS) {
        $batch = claimOutboxBatch(BATCH_SIZE);
        if (empty($batch)) {
            break;
        }

        foreach ($batch as $row) {
            $processed++;
            $id = (int)$row['id'];

            $options = [];
            if (!empty($row['cc_emails']))   { $options['cc']        = array_filter(array_map('trim', explode(',', (string)$row['cc_emails']))); }
            if (!empty($row['bcc_emails']))  { $options['bcc']       = array_filter(array_map('trim', explode(',', (string)$row['bcc_emails']))); }
            if (!empty($row['reply_to']))    { $options['replyTo']   = (string)$row['reply_to']; }
            if (!empty($row['from_name']))   { $options['fromName']  = (string)$row['from_name']; }
            if (!empty($row['attachments_json'])) {
                $att = json_decode((string)$row['attachments_json'], true);
                if (is_array($att)) { $options['attachments'] = $att; }
            }
            if (!empty($row['context_json'])) {
                $ctx = json_decode((string)$row['context_json'], true);
                if (is_array($ctx)) { $options['context'] = $ctx; }
            }

            try {
                $result = sendEmail(
                    (string)$row['to_email'],
                    (string)$row['subject'],
                    (string)$row['html_body'],
                    (string)($row['text_body'] ?? ''),
                    $options
                );

                if ($result === true) {
                    markOutboxSent($id);
                    $ok++;
                    out("sent id={$id} to=" . $row['to_email']);
                } else {
                    markOutboxFailed($id, 'sendEmail returned false (see mailer_error.log)');
                    $failed++;
                    out("failed id={$id} (sendEmail returned false)");
                }
            } catch (Throwable $e) {
                markOutboxFailed($id, $e->getMessage());
                $failed++;
                out("failed id={$id}: " . $e->getMessage());
            }
        }

        // Avoid hot-looping if SMTP is rate-limiting.
        if ($failed > 5 && $ok === 0) {
            out('High failure ratio; pausing batch loop.');
            break;
        }
    }

    out("Run summary: processed={$processed} ok={$ok} failed={$failed}");

} finally {
    $pdo->query("SELECT RELEASE_LOCK('process_email_outbox')")->fetchColumn();
}
exit(0);

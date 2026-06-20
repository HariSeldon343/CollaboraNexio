<?php
/**
 * Email outbox helper.
 *
 * Provides `enqueueEmail()` — drop-in replacement for `sendEmail()` for cases
 * where the caller doesn't need synchronous SMTP delivery. Persists the email
 * to the `email_outbox` table; cron/process_email_outbox.php drains the queue.
 *
 * Same signature as sendEmail() so callers can be migrated 1:1.
 *
 * @author CollaboraNexio
 * @version 1.0.0
 */
declare(strict_types=1);

require_once __DIR__ . '/db.php';

/**
 * Persist an email to the outbox queue. Returns true on enqueue success.
 *
 * @param string $to        Recipient email
 * @param string $subject   Subject line
 * @param string $htmlBody  HTML content
 * @param string $textBody  Plain-text alt (optional)
 * @param array  $options   Same shape as sendEmail() options:
 *   - cc: array of CC addresses
 *   - bcc: array of BCC addresses
 *   - replyTo: reply-to address
 *   - fromName: custom from name
 *   - attachments: array of [['path' => '', 'name' => '']]
 *   - context: array {tenant_id, user_id, action} for audit/logging
 *   - priority: int 1-9 (default 5)
 * @return bool
 */
function enqueueEmail(string $to, string $subject, string $htmlBody, string $textBody = '', array $options = []): bool {
    try {
        $db = Database::getInstance();

        $tenantId = $options['context']['tenant_id'] ?? null;
        if ($tenantId !== null) {
            $tenantId = (int)$tenantId;
            if ($tenantId <= 0) { $tenantId = null; }
        }

        $cc = !empty($options['cc']) ? implode(',', array_map('trim', (array)$options['cc'])) : null;
        $bcc = !empty($options['bcc']) ? implode(',', array_map('trim', (array)$options['bcc'])) : null;
        $replyTo = $options['replyTo'] ?? null;
        $fromName = $options['fromName'] ?? null;

        $attachmentsJson = null;
        if (!empty($options['attachments']) && is_array($options['attachments'])) {
            $attachmentsJson = json_encode($options['attachments'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $contextJson = null;
        if (!empty($options['context']) && is_array($options['context'])) {
            $contextJson = json_encode($options['context'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $priority = isset($options['priority']) ? (int)$options['priority'] : 5;
        if ($priority < 1) $priority = 1;
        if ($priority > 9) $priority = 9;

        $id = $db->insert('email_outbox', [
            'tenant_id' => $tenantId,
            'to_email' => $to,
            'subject' => $subject,
            'html_body' => $htmlBody,
            'text_body' => $textBody !== '' ? $textBody : null,
            'cc_emails' => $cc,
            'bcc_emails' => $bcc,
            'reply_to' => $replyTo,
            'from_name' => $fromName,
            'attachments_json' => $attachmentsJson,
            'context_json' => $contextJson,
            'status' => 'pending',
            'priority' => $priority,
            // next_attempt_at defaults to CURRENT_TIMESTAMP
        ]);

        return $id > 0;

    } catch (Throwable $e) {
        // Non-blocking: log and return false so caller can fall back to sync if it cares.
        error_log('[email_outbox] enqueue failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Helper used by cron worker — atomically claim the next batch of pending emails.
 * Returns array of row arrays. Updates status='sending' and attempts++ on the
 * claimed rows. Caller MUST call markOutboxSent() or markOutboxFailed() per row.
 *
 * Locking: uses an UPDATE that scopes by status + next_attempt_at; on MariaDB
 * this is single-statement atomic so concurrent cron runs cannot double-claim.
 *
 * @param int $batchSize maximum number of rows to claim
 * @return array<int, array<string, mixed>>
 */
function claimOutboxBatch(int $batchSize = 25): array {
    $db = Database::getInstance();
    $pdo = $db->getConnection();

    $batchSize = max(1, min(200, $batchSize));

    // Pick candidate IDs first, then UPDATE-lock them, then SELECT for return.
    $rows = $db->fetchAll(
        "SELECT id FROM email_outbox
         WHERE status = 'pending' AND next_attempt_at <= NOW() AND attempts < max_attempts
         ORDER BY priority ASC, next_attempt_at ASC, id ASC
         LIMIT $batchSize"
    );
    if (empty($rows)) {
        return [];
    }
    $ids = array_map(static fn($r) => (int)$r['id'], $rows);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    // Atomic claim: only flip rows still pending (defends against another worker).
    $stmt = $pdo->prepare(
        "UPDATE email_outbox
            SET status = 'sending', attempts = attempts + 1
          WHERE id IN ($placeholders) AND status = 'pending'"
    );
    $stmt->execute($ids);
    $claimedCount = $stmt->rowCount();
    if ($claimedCount === 0) {
        return [];
    }

    return $db->fetchAll(
        "SELECT * FROM email_outbox WHERE id IN ($placeholders) AND status = 'sending'",
        $ids
    );
}

function markOutboxSent(int $id): void {
    Database::getInstance()->update('email_outbox', [
        'status' => 'sent',
        'sent_at' => date('Y-m-d H:i:s'),
        'last_error' => null,
    ], ['id' => $id]);
}

/**
 * Mark a row failed. If attempts < max_attempts, leaves status as 'pending' with
 * an exponential-backoff next_attempt_at (1m, 5m, 15m, 1h, 6h). Otherwise marks
 * 'failed' permanently.
 */
function markOutboxFailed(int $id, string $errorMessage): void {
    $db = Database::getInstance();
    $row = $db->fetchOne('SELECT attempts, max_attempts FROM email_outbox WHERE id = ?', [$id]);
    if (!$row) { return; }
    $attempts = (int)$row['attempts'];
    $max = (int)$row['max_attempts'];

    if ($attempts >= $max) {
        $db->update('email_outbox', [
            'status' => 'failed',
            'last_error' => mb_substr($errorMessage, 0, 65000, 'UTF-8'),
        ], ['id' => $id]);
        return;
    }

    $backoffMinutes = [1, 5, 15, 60, 360][min($attempts - 1, 4)] ?? 360;
    $next = date('Y-m-d H:i:s', time() + $backoffMinutes * 60);
    $db->update('email_outbox', [
        'status' => 'pending',
        'next_attempt_at' => $next,
        'last_error' => mb_substr($errorMessage, 0, 65000, 'UTF-8'),
    ], ['id' => $id]);
}

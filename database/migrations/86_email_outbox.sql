-- ============================================
-- Module:      Email outbox queue
-- Version:     2026-05-02
-- Description: Persistent queue for outbound email so API endpoints can return
--              fast without waiting for SMTP. A cron worker drains the queue.
--
-- Usage:
--   - Replace inline `sendEmail(...)` calls in latency-sensitive endpoints with
--     `enqueueEmail(...)` (helpers/email_outbox.php).
--   - Authentication / password-reset flows that need synchronous delivery
--     keep using sendEmail().
--
-- Idempotent:  yes (CREATE TABLE IF NOT EXISTS + signature-guarded indexes)
-- Rollback:    86_email_outbox_rollback.sql
-- ============================================

USE collaboranexio;

CREATE TABLE IF NOT EXISTS `email_outbox` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id` INT UNSIGNED DEFAULT NULL COMMENT 'Optional: scope for filtering / audit',
    `to_email` VARCHAR(255) NOT NULL,
    `subject` VARCHAR(500) NOT NULL,
    `html_body` LONGTEXT NOT NULL,
    `text_body` LONGTEXT DEFAULT NULL,
    `cc_emails` TEXT DEFAULT NULL COMMENT 'Comma-separated CC addresses',
    `bcc_emails` TEXT DEFAULT NULL COMMENT 'Comma-separated BCC addresses',
    `reply_to` VARCHAR(255) DEFAULT NULL,
    `from_name` VARCHAR(255) DEFAULT NULL,
    `attachments_json` LONGTEXT DEFAULT NULL COMMENT 'JSON: [{path, name}, ...]',
    `context_json` LONGTEXT DEFAULT NULL COMMENT 'JSON: {tenant_id, user_id, action, ...} for logging',
    `status` ENUM('pending','sending','sent','failed') NOT NULL DEFAULT 'pending',
    `attempts` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `max_attempts` SMALLINT UNSIGNED NOT NULL DEFAULT 5,
    `next_attempt_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_error` TEXT DEFAULT NULL,
    `priority` TINYINT UNSIGNED NOT NULL DEFAULT 5 COMMENT '1=high, 9=low',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `sent_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_email_outbox_pickup` (`status`, `next_attempt_at`, `priority`),
    KEY `idx_email_outbox_tenant_created` (`tenant_id`, `created_at`),
    KEY `idx_email_outbox_to_email` (`to_email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT 'email_outbox table ready' AS status;
SELECT COUNT(*) AS row_count FROM email_outbox;

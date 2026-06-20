-- Rollback for migration 86: drop email_outbox table.
-- WARNING: any pending/unsent emails are lost. Drain the queue first via cron
-- (or manual UPDATE status='cancelled') before running this rollback.

USE collaboranexio;

SELECT 'Migration 86 rollback starting' AS status;

SET @pending := (SELECT COUNT(*) FROM email_outbox WHERE status IN ('pending','sending'));
SELECT @pending AS pending_emails_about_to_be_lost;

DROP TABLE IF EXISTS `email_outbox`;

SELECT 'email_outbox table dropped' AS status;

-- Migration 89 ROLLBACK: drop audit_logs_archive
--
-- WARNING: this destroys archived audit rows. If those rows still need to be
-- retained, re-import them into audit_logs first (see docs/migrations/89_audit_archive.md)
-- before running this rollback.

DROP TABLE IF EXISTS `audit_logs_archive`;

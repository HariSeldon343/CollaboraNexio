-- ============================================
-- Module:      Drop redundant indexes (cleanup)
-- Version:     2026-05-02
-- Description: Drops indexes that are exact structural duplicates of other
--              indexes on the same table (same column tuple).
--              Each DROP is gated: it only fires if (a) the index to drop
--              exists AND (b) at least one other index with the same column
--              signature also exists on the same table. This means the
--              migration is safe to re-run, safe to apply on databases that
--              never had the duplicate, and never leaves a table without
--              an index covering that signature.
--
-- Idempotent:  yes
-- Rollback:    85_drop_redundant_indexes_rollback.sql (re-creates dropped indexes)
--
-- Discovered via tools/index_optimization/find_redundant_indexes.sql:
--   audit_logs (tenant_id, deleted_at, created_at):
--     keep `idx_audit_tenant_deleted`
--     drop `idx_audit_dashboard`
--   files (deleted_at):
--     keep `idx_files_deleted_at`
--     drop `idx_deleted`
--   ticket_history (tenant_id, created_at):
--     keep `idx_ticket_history_tenant_created`
--     drop `idx_ticket_history_tenant`
--
-- Verified: no PHP file uses USE INDEX/FORCE INDEX/IGNORE INDEX with these names.
-- ============================================

USE collaboranexio;

SET @migration_start := NOW(6);
SELECT 'Migration 85 starting' AS status, @migration_start AS started_at;

-- ============================================
-- (1/3) audit_logs: drop idx_audit_dashboard if idx_audit_tenant_deleted also exists
-- ============================================
SET @sql := (
    SELECT CASE
        WHEN COUNT(DISTINCT idx) = 2 THEN
            'DROP INDEX idx_audit_dashboard ON audit_logs'
        ELSE
            'SELECT "skip: audit_logs idx_audit_dashboard not redundant or already dropped" AS info'
    END
    FROM (
        SELECT index_name AS idx
        FROM information_schema.STATISTICS
        WHERE table_schema = DATABASE() AND table_name = 'audit_logs'
          AND index_name IN ('idx_audit_dashboard','idx_audit_tenant_deleted')
        GROUP BY index_name
    ) pair);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT 'audit_logs.idx_audit_dashboard processed (1/3)' AS status;

-- ============================================
-- (2/3) files: drop idx_deleted if idx_files_deleted_at also exists
-- ============================================
SET @sql := (
    SELECT CASE
        WHEN COUNT(DISTINCT idx) = 2 THEN
            'DROP INDEX idx_deleted ON files'
        ELSE
            'SELECT "skip: files idx_deleted not redundant or already dropped" AS info'
    END
    FROM (
        SELECT index_name AS idx
        FROM information_schema.STATISTICS
        WHERE table_schema = DATABASE() AND table_name = 'files'
          AND index_name IN ('idx_deleted','idx_files_deleted_at')
        GROUP BY index_name
    ) pair);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT 'files.idx_deleted processed (2/3)' AS status;

-- ============================================
-- (3/3) ticket_history: drop idx_ticket_history_tenant if idx_ticket_history_tenant_created also exists
-- ============================================
SET @sql := (
    SELECT CASE
        WHEN COUNT(DISTINCT idx) = 2 THEN
            'DROP INDEX idx_ticket_history_tenant ON ticket_history'
        ELSE
            'SELECT "skip: ticket_history idx_ticket_history_tenant not redundant or already dropped" AS info'
    END
    FROM (
        SELECT index_name AS idx
        FROM information_schema.STATISTICS
        WHERE table_schema = DATABASE() AND table_name = 'ticket_history'
          AND index_name IN ('idx_ticket_history_tenant','idx_ticket_history_tenant_created')
        GROUP BY index_name
    ) pair);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT 'ticket_history.idx_ticket_history_tenant processed (3/3)' AS status;

SELECT TIMESTAMPDIFF(SECOND, @migration_start, NOW(6)) AS migration_duration_seconds,
       'Migration 85 complete' AS status;

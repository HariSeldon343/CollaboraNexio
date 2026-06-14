-- ============================================
-- Rollback for migration 85: re-creates the dropped redundant indexes.
-- Idempotent (skip if already present).
-- ============================================

USE collaboranexio;

SELECT 'Migration 85 rollback starting' AS status;

-- audit_logs.idx_audit_dashboard
SET @sql := (
    SELECT CASE WHEN COUNT(*) = 0 THEN
        'CREATE INDEX idx_audit_dashboard ON audit_logs (tenant_id, deleted_at, created_at)'
    ELSE
        'SELECT "skip: audit_logs.idx_audit_dashboard already exists" AS info' END
    FROM information_schema.STATISTICS
    WHERE table_schema=DATABASE() AND table_name='audit_logs' AND index_name='idx_audit_dashboard');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- files.idx_deleted
SET @sql := (
    SELECT CASE WHEN COUNT(*) = 0 THEN
        'CREATE INDEX idx_deleted ON files (deleted_at)'
    ELSE
        'SELECT "skip: files.idx_deleted already exists" AS info' END
    FROM information_schema.STATISTICS
    WHERE table_schema=DATABASE() AND table_name='files' AND index_name='idx_deleted');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ticket_history.idx_ticket_history_tenant
SET @sql := (
    SELECT CASE WHEN COUNT(*) = 0 THEN
        'CREATE INDEX idx_ticket_history_tenant ON ticket_history (tenant_id, created_at)'
    ELSE
        'SELECT "skip: ticket_history.idx_ticket_history_tenant already exists" AS info' END
    FROM information_schema.STATISTICS
    WHERE table_schema=DATABASE() AND table_name='ticket_history' AND index_name='idx_ticket_history_tenant');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT 'Migration 85 rollback complete' AS status;

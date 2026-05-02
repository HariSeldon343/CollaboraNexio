-- ============================================
-- Module:      Composite Tenant Pagination Indexes
-- Version:     2026-05-02
-- Author:      Performance Optimization
-- Description: Adds 8 composite indexes (tenant_id, [deleted_at,] created_at)
--              on hot listing tables to eliminate filesort/temporary on
--              paginated queries that follow the standard tenant-scoped pattern:
--                  WHERE tenant_id = ? AND deleted_at IS NULL
--                  ORDER BY created_at DESC LIMIT ? OFFSET ?
--
-- Idempotent:  yes — guards check for any existing index on the same table
--              with the same column tuple (signature-based, not just name).
--              Avoids creating structural duplicates of pre-existing indexes
--              like `idx_files_recent` and `idx_audit_tenant_deleted`.
-- Online DDL:  InnoDB default ALGORITHM for ADD INDEX is INPLACE
--              (no need to specify; MariaDB 10.4 prepared statements
--               cannot include ALGORITHM/LOCK clauses).
--              For prod under load, run via raw mysql client to attach
--              ALGORITHM=INPLACE, LOCK=NONE explicitly.
-- Rollback:    84_composite_tenant_indexes_rollback.sql
--
-- Note on DESC: MariaDB <10.8 ignores DESC on index columns (parsed as ASC).
-- The optimizer still produces "Backward index scan" for ORDER BY ... DESC
-- queries, so performance is unaffected.
-- ============================================

USE collaboranexio;

SET @migration_start := NOW(6);
SELECT 'Migration 84 starting' AS status, @migration_start AS started_at;

-- Helper note: each guard runs:
--   SELECT COUNT(*) FROM (
--     SELECT 1 FROM information_schema.STATISTICS
--     WHERE table_schema = DATABASE() AND table_name = '<tab>'
--     GROUP BY index_name
--     HAVING GROUP_CONCAT(column_name ORDER BY seq_in_index) = '<tuple>'
--   ) sig
-- and only emits CREATE INDEX when COUNT(*) = 0 (no existing index with that signature).

-- ============================================
-- (1/8) idx_users_tenant_deleted_created
-- ============================================
SET @sql := (
    SELECT CASE WHEN COUNT(*) = 0 THEN
        'CREATE INDEX idx_users_tenant_deleted_created ON users (tenant_id, deleted_at, created_at)'
    ELSE
        'SELECT "skip: users already has equivalent index (tenant_id, deleted_at, created_at)" AS info'
    END
    FROM (SELECT 1 FROM information_schema.STATISTICS
          WHERE table_schema = DATABASE() AND table_name = 'users'
          GROUP BY index_name
          HAVING GROUP_CONCAT(column_name ORDER BY seq_in_index) = 'tenant_id,deleted_at,created_at') sig);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT 'users index processed (1/8)' AS status;

-- ============================================
-- (2/8) idx_file_shares_tenant_created
-- ============================================
SET @sql := (
    SELECT CASE WHEN COUNT(*) = 0 THEN
        'CREATE INDEX idx_file_shares_tenant_created ON file_shares (tenant_id, created_at)'
    ELSE
        'SELECT "skip: file_shares already has equivalent index (tenant_id, created_at)" AS info'
    END
    FROM (SELECT 1 FROM information_schema.STATISTICS
          WHERE table_schema = DATABASE() AND table_name = 'file_shares'
          GROUP BY index_name
          HAVING GROUP_CONCAT(column_name ORDER BY seq_in_index) = 'tenant_id,created_at') sig);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT 'file_shares index processed (2/8)' AS status;

-- ============================================
-- (3/8) idx_folders_tenant_deleted_created
-- ============================================
SET @sql := (
    SELECT CASE WHEN COUNT(*) = 0 THEN
        'CREATE INDEX idx_folders_tenant_deleted_created ON folders (tenant_id, deleted_at, created_at)'
    ELSE
        'SELECT "skip: folders already has equivalent index (tenant_id, deleted_at, created_at)" AS info'
    END
    FROM (SELECT 1 FROM information_schema.STATISTICS
          WHERE table_schema = DATABASE() AND table_name = 'folders'
          GROUP BY index_name
          HAVING GROUP_CONCAT(column_name ORDER BY seq_in_index) = 'tenant_id,deleted_at,created_at') sig);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT 'folders index processed (3/8)' AS status;

-- ============================================
-- (4/8) idx_task_comments_tenant_created
-- ============================================
SET @sql := (
    SELECT CASE WHEN COUNT(*) = 0 THEN
        'CREATE INDEX idx_task_comments_tenant_created ON task_comments (tenant_id, created_at)'
    ELSE
        'SELECT "skip: task_comments already has equivalent index (tenant_id, created_at)" AS info'
    END
    FROM (SELECT 1 FROM information_schema.STATISTICS
          WHERE table_schema = DATABASE() AND table_name = 'task_comments'
          GROUP BY index_name
          HAVING GROUP_CONCAT(column_name ORDER BY seq_in_index) = 'tenant_id,created_at') sig);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT 'task_comments index processed (4/8)' AS status;

-- ============================================
-- (5/8) idx_notifications_tenant_created
-- ============================================
SET @sql := (
    SELECT CASE WHEN COUNT(*) = 0 THEN
        'CREATE INDEX idx_notifications_tenant_created ON notifications (tenant_id, created_at)'
    ELSE
        'SELECT "skip: notifications already has equivalent index (tenant_id, created_at)" AS info'
    END
    FROM (SELECT 1 FROM information_schema.STATISTICS
          WHERE table_schema = DATABASE() AND table_name = 'notifications'
          GROUP BY index_name
          HAVING GROUP_CONCAT(column_name ORDER BY seq_in_index) = 'tenant_id,created_at') sig);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT 'notifications index processed (5/8)' AS status;

-- ============================================
-- (6/8) idx_chat_messages_tenant_created
-- ============================================
SET @sql := (
    SELECT CASE WHEN COUNT(*) = 0 THEN
        'CREATE INDEX idx_chat_messages_tenant_created ON chat_messages (tenant_id, created_at)'
    ELSE
        'SELECT "skip: chat_messages already has equivalent index (tenant_id, created_at)" AS info'
    END
    FROM (SELECT 1 FROM information_schema.STATISTICS
          WHERE table_schema = DATABASE() AND table_name = 'chat_messages'
          GROUP BY index_name
          HAVING GROUP_CONCAT(column_name ORDER BY seq_in_index) = 'tenant_id,created_at') sig);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT 'chat_messages index processed (6/8)' AS status;

-- ============================================
-- (7/8) idx_files_tenant_deleted_created
-- NOTE: skipped on dbs that already have idx_files_recent with same signature.
-- ============================================
SET @sql := (
    SELECT CASE WHEN COUNT(*) = 0 THEN
        'CREATE INDEX idx_files_tenant_deleted_created ON files (tenant_id, deleted_at, created_at)'
    ELSE
        'SELECT "skip: files already has equivalent index (likely idx_files_recent)" AS info'
    END
    FROM (SELECT 1 FROM information_schema.STATISTICS
          WHERE table_schema = DATABASE() AND table_name = 'files'
          GROUP BY index_name
          HAVING GROUP_CONCAT(column_name ORDER BY seq_in_index) = 'tenant_id,deleted_at,created_at') sig);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT 'files index processed (7/8)' AS status;

-- ============================================
-- (8/8) idx_audit_logs_tenant_deleted_created
-- NOTE: skipped on dbs that already have idx_audit_tenant_deleted with same signature.
-- ============================================
SET @sql := (
    SELECT CASE WHEN COUNT(*) = 0 THEN
        'CREATE INDEX idx_audit_logs_tenant_deleted_created ON audit_logs (tenant_id, deleted_at, created_at)'
    ELSE
        'SELECT "skip: audit_logs already has equivalent index (likely idx_audit_tenant_deleted)" AS info'
    END
    FROM (SELECT 1 FROM information_schema.STATISTICS
          WHERE table_schema = DATABASE() AND table_name = 'audit_logs'
          GROUP BY index_name
          HAVING GROUP_CONCAT(column_name ORDER BY seq_in_index) = 'tenant_id,deleted_at,created_at') sig);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT 'audit_logs index processed (8/8)' AS status;

-- ============================================
-- VERIFICATION: list all (table, signature) coverage. Useful as a transcript.
-- ============================================
SELECT 'Verification: tenant pagination signatures present' AS section;
SELECT table_name, index_name, GROUP_CONCAT(column_name ORDER BY seq_in_index) AS cols
FROM information_schema.STATISTICS
WHERE table_schema = DATABASE()
  AND table_name IN ('users','file_shares','folders','task_comments',
                     'notifications','chat_messages','files','audit_logs')
GROUP BY table_name, index_name
HAVING cols IN ('tenant_id,created_at','tenant_id,deleted_at,created_at')
ORDER BY table_name, index_name;

SELECT TIMESTAMPDIFF(SECOND, @migration_start, NOW(6)) AS migration_duration_seconds,
       'Migration 84 complete' AS status;

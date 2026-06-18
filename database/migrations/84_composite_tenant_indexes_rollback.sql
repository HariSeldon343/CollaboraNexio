-- ============================================
-- Rollback for Migration 84: Composite Tenant Pagination Indexes
-- Idempotent: yes (information_schema.STATISTICS guards on every DROP)
-- Order: largest table first (mirror of forward order, reversed)
--
-- WARNING: pre-existing indexes that the forward migration would have skipped
-- (e.g., idx_chat_messages_tenant_created and idx_notifications_tenant_created
-- which existed before migration 84 on this database) will ALSO be dropped by
-- this rollback because the rollback drops by index name regardless of origin.
-- If you need to preserve them, manually re-create them after rollback.
-- ============================================

USE collaboranexio;

SET @rollback_start := NOW(6);
SELECT 'Migration 84 rollback starting' AS status, @rollback_start AS started_at;

-- (8/8 → 1/8 reversed): drop audit_logs first, users last
SET @sql := (
    SELECT CASE WHEN COUNT(*) = 1
                THEN 'DROP INDEX idx_audit_logs_tenant_deleted_created ON audit_logs'
                ELSE 'SELECT "skip: idx_audit_logs_tenant_deleted_created absent" AS info'
           END
    FROM information_schema.STATISTICS
    WHERE table_schema = DATABASE()
      AND table_name = 'audit_logs'
      AND index_name = 'idx_audit_logs_tenant_deleted_created');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT 'audit_logs index processed' AS status;

SET @sql := (
    SELECT CASE WHEN COUNT(*) = 1
                THEN 'DROP INDEX idx_files_tenant_deleted_created ON files'
                ELSE 'SELECT "skip: idx_files_tenant_deleted_created absent" AS info'
           END
    FROM information_schema.STATISTICS
    WHERE table_schema = DATABASE()
      AND table_name = 'files'
      AND index_name = 'idx_files_tenant_deleted_created');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT 'files index processed' AS status;

SET @sql := (
    SELECT CASE WHEN COUNT(*) = 1
                THEN 'DROP INDEX idx_chat_messages_tenant_created ON chat_messages'
                ELSE 'SELECT "skip: idx_chat_messages_tenant_created absent" AS info'
           END
    FROM information_schema.STATISTICS
    WHERE table_schema = DATABASE()
      AND table_name = 'chat_messages'
      AND index_name = 'idx_chat_messages_tenant_created');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT 'chat_messages index processed' AS status;

SET @sql := (
    SELECT CASE WHEN COUNT(*) = 1
                THEN 'DROP INDEX idx_notifications_tenant_created ON notifications'
                ELSE 'SELECT "skip: idx_notifications_tenant_created absent" AS info'
           END
    FROM information_schema.STATISTICS
    WHERE table_schema = DATABASE()
      AND table_name = 'notifications'
      AND index_name = 'idx_notifications_tenant_created');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT 'notifications index processed' AS status;

SET @sql := (
    SELECT CASE WHEN COUNT(*) = 1
                THEN 'DROP INDEX idx_task_comments_tenant_created ON task_comments'
                ELSE 'SELECT "skip: idx_task_comments_tenant_created absent" AS info'
           END
    FROM information_schema.STATISTICS
    WHERE table_schema = DATABASE()
      AND table_name = 'task_comments'
      AND index_name = 'idx_task_comments_tenant_created');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT 'task_comments index processed' AS status;

SET @sql := (
    SELECT CASE WHEN COUNT(*) = 1
                THEN 'DROP INDEX idx_folders_tenant_deleted_created ON folders'
                ELSE 'SELECT "skip: idx_folders_tenant_deleted_created absent" AS info'
           END
    FROM information_schema.STATISTICS
    WHERE table_schema = DATABASE()
      AND table_name = 'folders'
      AND index_name = 'idx_folders_tenant_deleted_created');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT 'folders index processed' AS status;

SET @sql := (
    SELECT CASE WHEN COUNT(*) = 1
                THEN 'DROP INDEX idx_file_shares_tenant_created ON file_shares'
                ELSE 'SELECT "skip: idx_file_shares_tenant_created absent" AS info'
           END
    FROM information_schema.STATISTICS
    WHERE table_schema = DATABASE()
      AND table_name = 'file_shares'
      AND index_name = 'idx_file_shares_tenant_created');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT 'file_shares index processed' AS status;

SET @sql := (
    SELECT CASE WHEN COUNT(*) = 1
                THEN 'DROP INDEX idx_users_tenant_deleted_created ON users'
                ELSE 'SELECT "skip: idx_users_tenant_deleted_created absent" AS info'
           END
    FROM information_schema.STATISTICS
    WHERE table_schema = DATABASE()
      AND table_name = 'users'
      AND index_name = 'idx_users_tenant_deleted_created');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SELECT 'users index processed' AS status;

-- ============================================
-- VERIFICATION: assert all 8 index names are absent
-- ============================================
SELECT 'Verification: all 8 indexes absent' AS section;
SELECT COUNT(*) AS remaining_indexes,
       CASE WHEN COUNT(*) = 0 THEN 'PASS' ELSE 'FAIL' END AS check_result
FROM information_schema.STATISTICS
WHERE table_schema = DATABASE()
  AND index_name IN ('idx_users_tenant_deleted_created',
                     'idx_file_shares_tenant_created',
                     'idx_folders_tenant_deleted_created',
                     'idx_task_comments_tenant_created',
                     'idx_notifications_tenant_created',
                     'idx_chat_messages_tenant_created',
                     'idx_files_tenant_deleted_created',
                     'idx_audit_logs_tenant_deleted_created');

SELECT TIMESTAMPDIFF(SECOND, @rollback_start, NOW(6)) AS rollback_duration_seconds,
       'Migration 84 rollback complete' AS status;

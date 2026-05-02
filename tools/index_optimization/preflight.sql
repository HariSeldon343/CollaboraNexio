-- Migration 84 preflight (read-only).
-- Aborts implicitly: if any check fails (no row / wrong row), the operator must
-- decide whether to proceed. All queries are idempotent.

USE collaboranexio;

SELECT '=== 1. MySQL version (>= 8.0.18 recommended for EXPLAIN ANALYZE) ===' AS section;
SELECT VERSION() AS mysql_version, @@innodb_version AS innodb_version;

SELECT '=== 2. Target tables exist (expect 8 rows) ===' AS section;
SELECT table_name
FROM information_schema.TABLES
WHERE table_schema = DATABASE()
  AND table_name IN ('files','folders','users','notifications',
                     'audit_logs','chat_messages','task_comments','file_shares')
ORDER BY table_name;

SELECT '=== 3. Required columns exist (expect 22 rows) ===' AS section;
SELECT table_name, column_name
FROM information_schema.COLUMNS
WHERE table_schema = DATABASE()
  AND ((table_name='files'         AND column_name IN ('tenant_id','deleted_at','created_at'))
    OR (table_name='folders'       AND column_name IN ('tenant_id','deleted_at','created_at'))
    OR (table_name='users'         AND column_name IN ('tenant_id','deleted_at','created_at'))
    OR (table_name='notifications' AND column_name IN ('tenant_id','created_at'))
    OR (table_name='audit_logs'    AND column_name IN ('tenant_id','deleted_at','created_at'))
    OR (table_name='chat_messages' AND column_name IN ('tenant_id','created_at'))
    OR (table_name='task_comments' AND column_name IN ('tenant_id','created_at'))
    OR (table_name='file_shares'   AND column_name IN ('tenant_id','created_at')))
ORDER BY table_name, column_name;

SELECT '=== 4. Index name collisions (expect 0 rows) ===' AS section;
SELECT table_name, index_name
FROM information_schema.STATISTICS
WHERE table_schema = DATABASE()
  AND index_name IN ('idx_files_tenant_deleted_created',
                     'idx_folders_tenant_deleted_created',
                     'idx_users_tenant_deleted_created',
                     'idx_notifications_tenant_created',
                     'idx_audit_logs_tenant_deleted_created',
                     'idx_chat_messages_tenant_created',
                     'idx_task_comments_tenant_created',
                     'idx_file_shares_tenant_created')
GROUP BY table_name, index_name;

SELECT '=== 5. Baseline table sizes ===' AS section;
SELECT table_name, table_rows,
       ROUND((data_length + index_length) / 1024 / 1024, 2) AS size_mb,
       ROUND(index_length / 1024 / 1024, 2) AS index_size_mb
FROM information_schema.TABLES
WHERE table_schema = DATABASE()
  AND table_name IN ('files','folders','users','notifications',
                     'audit_logs','chat_messages','task_comments','file_shares')
ORDER BY data_length + index_length DESC;

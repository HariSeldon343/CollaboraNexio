-- =====================================================================
-- Rollback for Migration 87: Soft-Delete Extension
-- =====================================================================
--
-- WARNING:
--   Dropping `deleted_at` is potentially destructive: any rows that
--   carry a non-NULL value will lose their soft-delete state. Each
--   DROP COLUMN block below is guarded by a count of non-NULL rows
--   and SKIPS the drop if any soft-deleted rows exist. To force a
--   drop in that case, hard-delete those rows first or set their
--   deleted_at to NULL deliberately, then re-run.
--
--   The composite (tenant_id, deleted_at) indexes are dropped only
--   if they exist under the standardized name we created.
--   Pre-existing differently-named indexes (e.g. idx_task_tenant_deleted
--   on `tasks`) are LEFT INTACT — this rollback only undoes what
--   migration 87 actually added.
--
-- MariaDB 10.4 caveats:
--   Same as 87: no IF EXISTS on DROP INDEX/COLUMN inside PREPARE.
-- =====================================================================

-- ---------------------------------------------------------------------
-- Helper macro pattern (inline per block):
--   1) Drop the standardized index if present.
--   2) Drop the column only if NO non-NULL deleted_at rows exist.
-- ---------------------------------------------------------------------

-- ---------------------------------------------------------------------
-- 5) chat_message_reads (reverse order vs forward migration)
-- ---------------------------------------------------------------------
SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'chat_message_reads'
       AND INDEX_NAME = 'idx_chat_message_reads_tenant_deleted') > 0,
  'DROP INDEX idx_chat_message_reads_tenant_deleted ON chat_message_reads',
  'SELECT ''idx_chat_message_reads_tenant_deleted not present; skip'' AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'chat_message_reads'
      AND COLUMN_NAME = 'deleted_at'
);
SET @nondel := IF(@col_exists = 0, 0,
  (SELECT COUNT(*) FROM chat_message_reads WHERE deleted_at IS NOT NULL));
SET @sql := IF(
  @col_exists > 0 AND @nondel = 0,
  'ALTER TABLE chat_message_reads DROP COLUMN deleted_at',
  IF(@col_exists = 0,
     'SELECT ''chat_message_reads.deleted_at not present; skip drop'' AS info',
     'SELECT ''chat_message_reads has soft-deleted rows; refusing to drop deleted_at'' AS warn')
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------
-- 4) chat_messages
-- ---------------------------------------------------------------------
SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'chat_messages'
       AND INDEX_NAME = 'idx_chat_messages_tenant_deleted') > 0,
  'DROP INDEX idx_chat_messages_tenant_deleted ON chat_messages',
  'SELECT ''idx_chat_messages_tenant_deleted not present; skip'' AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'chat_messages'
      AND COLUMN_NAME = 'deleted_at'
);
SET @nondel := IF(@col_exists = 0, 0,
  (SELECT COUNT(*) FROM chat_messages WHERE deleted_at IS NOT NULL));
SET @sql := IF(
  @col_exists > 0 AND @nondel = 0,
  'ALTER TABLE chat_messages DROP COLUMN deleted_at',
  IF(@col_exists = 0,
     'SELECT ''chat_messages.deleted_at not present; skip drop'' AS info',
     'SELECT ''chat_messages has soft-deleted rows; refusing to drop deleted_at'' AS warn')
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------
-- 3) chat_channels
-- ---------------------------------------------------------------------
SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'chat_channels'
       AND INDEX_NAME = 'idx_chat_channels_tenant_deleted') > 0,
  'DROP INDEX idx_chat_channels_tenant_deleted ON chat_channels',
  'SELECT ''idx_chat_channels_tenant_deleted not present; skip'' AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'chat_channels'
      AND COLUMN_NAME = 'deleted_at'
);
SET @nondel := IF(@col_exists = 0, 0,
  (SELECT COUNT(*) FROM chat_channels WHERE deleted_at IS NOT NULL));
SET @sql := IF(
  @col_exists > 0 AND @nondel = 0,
  'ALTER TABLE chat_channels DROP COLUMN deleted_at',
  IF(@col_exists = 0,
     'SELECT ''chat_channels.deleted_at not present; skip drop'' AS info',
     'SELECT ''chat_channels has soft-deleted rows; refusing to drop deleted_at'' AS warn')
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------
-- 2) notifications
-- ---------------------------------------------------------------------
SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'notifications'
       AND INDEX_NAME = 'idx_notifications_tenant_deleted') > 0,
  'DROP INDEX idx_notifications_tenant_deleted ON notifications',
  'SELECT ''idx_notifications_tenant_deleted not present; skip'' AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'notifications'
      AND COLUMN_NAME = 'deleted_at'
);
SET @nondel := IF(@col_exists = 0, 0,
  (SELECT COUNT(*) FROM notifications WHERE deleted_at IS NOT NULL));
SET @sql := IF(
  @col_exists > 0 AND @nondel = 0,
  'ALTER TABLE notifications DROP COLUMN deleted_at',
  IF(@col_exists = 0,
     'SELECT ''notifications.deleted_at not present; skip drop'' AS info',
     'SELECT ''notifications has soft-deleted rows; refusing to drop deleted_at'' AS warn')
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------
-- 1) tasks
-- ---------------------------------------------------------------------
SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'tasks'
       AND INDEX_NAME = 'idx_tasks_tenant_deleted') > 0,
  'DROP INDEX idx_tasks_tenant_deleted ON tasks',
  'SELECT ''idx_tasks_tenant_deleted not present; skip'' AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tasks'
      AND COLUMN_NAME = 'deleted_at'
);
SET @nondel := IF(@col_exists = 0, 0,
  (SELECT COUNT(*) FROM tasks WHERE deleted_at IS NOT NULL));
SET @sql := IF(
  @col_exists > 0 AND @nondel = 0,
  'ALTER TABLE tasks DROP COLUMN deleted_at',
  IF(@col_exists = 0,
     'SELECT ''tasks.deleted_at not present; skip drop'' AS info',
     'SELECT ''tasks has soft-deleted rows; refusing to drop deleted_at'' AS warn')
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================================
-- End of rollback 87.
-- =====================================================================

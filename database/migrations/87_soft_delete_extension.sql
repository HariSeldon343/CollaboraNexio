-- =====================================================================
-- Migration 87: Soft-Delete Extension to 5 Additional Tables
-- =====================================================================
--
-- Purpose:
--   Ensure `deleted_at TIMESTAMP NULL` and a composite
--   `(tenant_id, deleted_at)` index exist on 5 tables that historically
--   may have lacked them in some environments. This unlocks the
--   universal pattern `WHERE tenant_id = ? AND deleted_at IS NULL`
--   (CLAUDE.md sec. 3.1, BUG-072, BUG-127) consistently across the
--   schema.
--
-- Target tables:
--   1. tasks
--   2. notifications
--   3. chat_channels
--   4. chat_messages
--   5. chat_message_reads
--
-- IMPORTANT — Idempotent / no-op friendly:
--   Each ALTER is guarded against information_schema. On the canonical
--   local dev DB (verified 2026-05-02) all 5 tables already have both
--   the column and a composite (tenant_id, deleted_at) index, so this
--   migration is a SAFETY NET for environments that drifted (older
--   prod, partial backups, fresh installs from older snapshots).
--
-- Foreign key policy:
--   Existing FK ON DELETE CASCADE relationships are intentionally
--   LEFT UNCHANGED. Soft-deleting a tenant means the row in `tenants`
--   stays and CASCADE never fires; touching FKs introduces risk
--   without benefit. See docs/migrations/87_soft_delete_extension.md.
--
-- Backfill policy:
--   None. NULL == not deleted. Existing rows remain live.
--
-- MariaDB 10.4 caveats:
--   - Cannot use `ADD COLUMN IF NOT EXISTS` portably -> guarded PREPARE
--   - Cannot use `CREATE INDEX IF NOT EXISTS` -> guarded PREPARE
--   - Cannot use `ALGORITHM=INPLACE, LOCK=NONE` inside PREPARE
--     (MariaDB rejects it). For online DDL on a busy production table,
--     run the inner ALTER manually as non-prepared, e.g.:
--       ALTER TABLE tasks
--         ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL,
--         ALGORITHM=INPLACE, LOCK=NONE;
--
-- Index naming:
--   New indexes use the standardized name `idx_<table>_tenant_deleted`.
--   The existence guard checks for ANY index whose first two columns
--   are (tenant_id, deleted_at), so duplicates are NOT created on
--   tables that already have a similarly-shaped index under a
--   different name (e.g. `idx_task_tenant_deleted` on `tasks`).
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1) tasks.deleted_at
-- ---------------------------------------------------------------------
SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'tasks'
       AND COLUMN_NAME = 'deleted_at') = 0,
  'ALTER TABLE tasks ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL COMMENT ''Soft delete timestamp''',
  'SELECT ''tasks.deleted_at already exists'' AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 1b) tasks (tenant_id, deleted_at) composite index
SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS s1
     INNER JOIN information_schema.STATISTICS s2
       ON s1.TABLE_SCHEMA = s2.TABLE_SCHEMA
      AND s1.TABLE_NAME   = s2.TABLE_NAME
      AND s1.INDEX_NAME   = s2.INDEX_NAME
     WHERE s1.TABLE_SCHEMA = DATABASE()
       AND s1.TABLE_NAME   = 'tasks'
       AND s1.COLUMN_NAME  = 'tenant_id'  AND s1.SEQ_IN_INDEX = 1
       AND s2.COLUMN_NAME  = 'deleted_at' AND s2.SEQ_IN_INDEX = 2) = 0,
  'CREATE INDEX idx_tasks_tenant_deleted ON tasks (tenant_id, deleted_at)',
  'SELECT ''tasks (tenant_id, deleted_at) index already present'' AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------
-- 2) notifications.deleted_at
-- ---------------------------------------------------------------------
SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'notifications'
       AND COLUMN_NAME = 'deleted_at') = 0,
  'ALTER TABLE notifications ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL COMMENT ''Soft delete timestamp''',
  'SELECT ''notifications.deleted_at already exists'' AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2b) notifications (tenant_id, deleted_at) composite index
SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS s1
     INNER JOIN information_schema.STATISTICS s2
       ON s1.TABLE_SCHEMA = s2.TABLE_SCHEMA
      AND s1.TABLE_NAME   = s2.TABLE_NAME
      AND s1.INDEX_NAME   = s2.INDEX_NAME
     WHERE s1.TABLE_SCHEMA = DATABASE()
       AND s1.TABLE_NAME   = 'notifications'
       AND s1.COLUMN_NAME  = 'tenant_id'  AND s1.SEQ_IN_INDEX = 1
       AND s2.COLUMN_NAME  = 'deleted_at' AND s2.SEQ_IN_INDEX = 2) = 0,
  'CREATE INDEX idx_notifications_tenant_deleted ON notifications (tenant_id, deleted_at)',
  'SELECT ''notifications (tenant_id, deleted_at) index already present'' AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------
-- 3) chat_channels.deleted_at
-- ---------------------------------------------------------------------
SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'chat_channels'
       AND COLUMN_NAME = 'deleted_at') = 0,
  'ALTER TABLE chat_channels ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL COMMENT ''Soft delete timestamp''',
  'SELECT ''chat_channels.deleted_at already exists'' AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3b) chat_channels (tenant_id, deleted_at) composite index
SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS s1
     INNER JOIN information_schema.STATISTICS s2
       ON s1.TABLE_SCHEMA = s2.TABLE_SCHEMA
      AND s1.TABLE_NAME   = s2.TABLE_NAME
      AND s1.INDEX_NAME   = s2.INDEX_NAME
     WHERE s1.TABLE_SCHEMA = DATABASE()
       AND s1.TABLE_NAME   = 'chat_channels'
       AND s1.COLUMN_NAME  = 'tenant_id'  AND s1.SEQ_IN_INDEX = 1
       AND s2.COLUMN_NAME  = 'deleted_at' AND s2.SEQ_IN_INDEX = 2) = 0,
  'CREATE INDEX idx_chat_channels_tenant_deleted ON chat_channels (tenant_id, deleted_at)',
  'SELECT ''chat_channels (tenant_id, deleted_at) index already present'' AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------
-- 4) chat_messages.deleted_at
-- ---------------------------------------------------------------------
SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'chat_messages'
       AND COLUMN_NAME = 'deleted_at') = 0,
  'ALTER TABLE chat_messages ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL COMMENT ''Soft delete timestamp''',
  'SELECT ''chat_messages.deleted_at already exists'' AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 4b) chat_messages (tenant_id, deleted_at) composite index
SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.STATISTICS s1
     INNER JOIN information_schema.STATISTICS s2
       ON s1.TABLE_SCHEMA = s2.TABLE_SCHEMA
      AND s1.TABLE_NAME   = s2.TABLE_NAME
      AND s1.INDEX_NAME   = s2.INDEX_NAME
     WHERE s1.TABLE_SCHEMA = DATABASE()
       AND s1.TABLE_NAME   = 'chat_messages'
       AND s1.COLUMN_NAME  = 'tenant_id'  AND s1.SEQ_IN_INDEX = 1
       AND s2.COLUMN_NAME  = 'deleted_at' AND s2.SEQ_IN_INDEX = 2) = 0,
  'CREATE INDEX idx_chat_messages_tenant_deleted ON chat_messages (tenant_id, deleted_at)',
  'SELECT ''chat_messages (tenant_id, deleted_at) index already present'' AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------
-- 5) chat_message_reads.deleted_at
--
-- Note: chat_message_reads DOES carry a tenant_id column on this
-- codebase (verified via SHOW CREATE TABLE on local dev DB), so the
-- composite index is applicable. If a future variant of this table
-- ever drops tenant_id, the index guard below will simply find no
-- matching column and the CREATE INDEX will skip without error.
-- ---------------------------------------------------------------------
SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'chat_message_reads'
       AND COLUMN_NAME = 'deleted_at') = 0,
  'ALTER TABLE chat_message_reads ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL COMMENT ''Soft delete timestamp''',
  'SELECT ''chat_message_reads.deleted_at already exists'' AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 5b) chat_message_reads (tenant_id, deleted_at) composite index
-- Guard depends on tenant_id existing on the table; if it does not,
-- the SEQ_IN_INDEX 1 constraint will not be satisfiable and we skip.
SET @has_tenant_col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'chat_message_reads'
      AND COLUMN_NAME = 'tenant_id'
);

SET @sql := IF(
  @has_tenant_col = 0,
  'SELECT ''chat_message_reads has no tenant_id column; skipping composite index'' AS warn',
  IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS s1
       INNER JOIN information_schema.STATISTICS s2
         ON s1.TABLE_SCHEMA = s2.TABLE_SCHEMA
        AND s1.TABLE_NAME   = s2.TABLE_NAME
        AND s1.INDEX_NAME   = s2.INDEX_NAME
       WHERE s1.TABLE_SCHEMA = DATABASE()
         AND s1.TABLE_NAME   = 'chat_message_reads'
         AND s1.COLUMN_NAME  = 'tenant_id'  AND s1.SEQ_IN_INDEX = 1
         AND s2.COLUMN_NAME  = 'deleted_at' AND s2.SEQ_IN_INDEX = 2) = 0,
    'CREATE INDEX idx_chat_message_reads_tenant_deleted ON chat_message_reads (tenant_id, deleted_at)',
    'SELECT ''chat_message_reads (tenant_id, deleted_at) index already present'' AS info'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================================
-- End of migration 87.
-- =====================================================================

# Migration 87 — Soft-Delete Extension

**Status:** Schema-only PR. **NOT yet applied** to local dev DB or production.
**Branch:** `perf/soft-delete-extension`
**Files:**
- `database/migrations/87_soft_delete_extension.sql`
- `database/migrations/87_soft_delete_extension_rollback.sql`

---

## 1. Goal

Make the universal multi-tenant query pattern

```sql
WHERE tenant_id = ? AND deleted_at IS NULL
```

(see CLAUDE.md §3.1, BUG-072, BUG-127) usable on **every** tenant-scoped table by guaranteeing that 5 historically inconsistent tables carry a `deleted_at` column **and** a composite `(tenant_id, deleted_at)` index suitable for range/exclusion scans.

---

## 2. Target tables

| # | Table                | Purpose                                             |
|---|----------------------|-----------------------------------------------------|
| 1 | `tasks`              | Task records (already partially scoped)             |
| 2 | `notifications`      | In-app notifications                                |
| 3 | `chat_channels`      | Chat channels                                       |
| 4 | `chat_messages`      | Chat messages                                       |
| 5 | `chat_message_reads` | Per-user read receipts (carries tenant_id)          |

### Local dev DB state (verified 2026-05-02, MariaDB 10.4.32)

All 5 tables **already** have:
- `deleted_at TIMESTAMP NULL DEFAULT NULL`
- a composite index whose first two columns are `(tenant_id, deleted_at)` (under various legacy names)

So on this DB the migration is a **no-op**. It is included as a **schema drift safety net** for environments seeded from older snapshots, partial backups, or pre-soft-delete forks (e.g. older production state, fresh installs from older `*.sql` dumps in this repo).

---

## 3. What this migration does NOT do

### 3.1 No FK changes

We deliberately do **not** alter any existing `FOREIGN KEY ... ON DELETE CASCADE` definition.

**Rationale:** soft-deleting a tenant means the row in `tenants` stays — `CASCADE` never fires for soft deletes. Touching FKs introduces risk (lock escalation on busy tables, accidental change of cascade semantics) for zero functional gain. The `deleted_at IS NULL` filter is enforced at the application layer (PHP), as the rest of the codebase already does for `users`, `tenants`, `files`, `events`, etc.

### 3.2 No backfill

`deleted_at IS NULL` already means "not deleted". Existing rows remain live. No `UPDATE` is run.

### 3.3 No application code changes

This PR ships schema only. The follow-up code-level fixes (queries that don't yet filter by `deleted_at IS NULL`) are listed in §6 and must land in a separate PR.

---

## 4. Idempotency & MariaDB 10.4 compatibility

Every `ALTER`/`CREATE INDEX` is wrapped in a guard against `information_schema`. Pattern (column example):

```sql
SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'tasks'
       AND COLUMN_NAME = 'deleted_at') = 0,
  'ALTER TABLE tasks ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL',
  'SELECT ''tasks.deleted_at already exists'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
```

For composite indexes, the guard checks `information_schema.STATISTICS` for **any** index whose `SEQ_IN_INDEX = 1` is `tenant_id` and `SEQ_IN_INDEX = 2` is `deleted_at`. This deduplicates across legacy index names (e.g. `idx_task_tenant_deleted` already on `tasks`, `idx_chat_message_tenant_deleted` on `chat_messages`).

**MariaDB 10.4 caveats addressed:**
- ❌ `ADD COLUMN IF NOT EXISTS` — not portable in 10.4 prepared statements → guarded `PREPARE`
- ❌ `CREATE INDEX IF NOT EXISTS` — not available → guarded `PREPARE`
- ❌ `ALGORITHM=INPLACE, LOCK=NONE` inside `PREPARE` — rejected by parser
  - For online DDL on a busy production `tasks` or `chat_messages`, run the inner `ALTER` manually as **non-prepared**:
    ```sql
    ALTER TABLE tasks
      ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL,
      ALGORITHM=INPLACE, LOCK=NONE;
    ```

### `chat_message_reads` special case

The `chat_message_reads` table on this codebase **does** carry `tenant_id` (verified). The composite index guard is still defensive — if a future variant drops `tenant_id`, the index creation will skip with a `WARN`-style `SELECT` rather than fail.

---

## 5. Risk & rollback

### Risk assessment

- **On local dev DB / current state of repo:** zero — every block is a no-op.
- **On environments missing the columns:** small. `ALTER TABLE ... ADD COLUMN <nullable>` is fast and online on InnoDB; `CREATE INDEX` is the only operation that may take time on large tables (most relevantly `chat_messages` and `notifications`). For multi-million-row tables, prefer running the inner `CREATE INDEX` manually with `ALGORITHM=INPLACE, LOCK=NONE`.

### Rollback semantics

`87_soft_delete_extension_rollback.sql` only undoes what migration 87 added:
- Drops indexes named `idx_<table>_tenant_deleted` (the standardized names this migration creates), leaving any pre-existing differently-named indexes intact.
- Drops `deleted_at` columns **only if the column has zero non-NULL values**. If any row is soft-deleted, the rollback **refuses** that drop (emits a `WARN` row, no error) to prevent silent data loss.

To force-drop, hard-delete the soft-deleted rows first (or set `deleted_at = NULL` deliberately) and re-run the rollback.

---

## 6. Required follow-up code changes

The following call sites currently `SELECT/COUNT FROM <table>` **without** `deleted_at IS NULL`. Each one should be updated in a follow-up PR (out of scope here). Reviewers should use this list to confirm scope; a few of these are intentionally global (audit/migration tools) and should be left alone — annotated below.

### `tasks`

| File | Line(s) | Notes |
|------|---------|-------|
| `api/router.php` | 545 | Dashboard "my_tasks" count — **fix needed** |
| `api/router.php` | 585 | Dashboard tasks list — **fix needed** |
| `api/projects_complete.php` | 144, 145 | Project task-count subqueries — **fix needed** |
| `api/projects_complete.php` | 325 | Project task stats — **fix needed** |
| `api/projects_complete.php` | 738 | Project tasks list — **fix needed** |
| `tools/apply_db_remediation_tenant_mismatches_cli.php` | 102, 110 | Audit tool — **leave as-is** (intentional cross-tenant audit) |
| `tools/db_remediation_tenant_mismatches.php` | (similar pattern) | Audit tool — **leave as-is** |
| `run_simple_task_migration.php` | 72, 99 | One-shot migration script — **leave as-is** |

### `notifications`

| File | Line(s) | Notes |
|------|---------|-------|
| `api/chat-poll.php` | 456 | Poll for new notifications — **fix needed** |
| `api/router.php` | 627 | Notifications list — **fix needed** |
| `api/notifications.php` | 111, 166, 293 | Notifications API — **fix needed** |

### `chat_channels`

| File | Line(s) | Notes |
|------|---------|-------|
| `api/channels.php` | 183, 298, 482, 705, 886, 1322 | Channel list/detail/create-dup-check/admin — **fix needed** |
| `api/users/cleanup_deleted.php` | 116 | `DELETE FROM` for hard-cleanup — **leave as-is** (intentional hard delete) |

### `chat_messages`

| File | Line(s) | Notes |
|------|---------|-------|
| `includes/polling.php` | 175, 498 | Live-message polling, 24h stats — **fix needed** (already filters `is_deleted = 0` at line 181, but `deleted_at` filter still missing) |
| `api/messages.php` | (per grep, multiple) | **fix needed** — review file |
| `api/chat_messages.php` | (per grep) | **fix needed** — review file |
| `api/chat-poll.php` | (per grep) | **fix needed** — review file |
| `api/channels.php` | (per grep) | **fix needed** — review file |
| `api/users/cleanup_deleted.php` | (DELETE) | **leave as-is** (hard cleanup) |

### `chat_message_reads`

| File | Line(s) | Notes |
|------|---------|-------|
| `api/users/cleanup_deleted.php` | 173 | `DELETE FROM` — **leave as-is** (hard cleanup) |

> **Audit method:** ripgrep over `**/*.php` for `FROM\s+<table>\b`, manual context inspection. The lists above are best-effort but not exhaustive — the follow-up PR should re-run the audit after merge and add a `tools/check_soft_delete_coverage.php` linter if the team wants this enforced going forward.

---

## 7. How to apply

```bash
# Local
mysql -u root collaboranexio < database/migrations/87_soft_delete_extension.sql

# Production (recommended: run online for large tables)
# 1. Apply column adds via the migration (fast).
# 2. For CREATE INDEX on large tables, run manually:
#    ALTER TABLE chat_messages ADD INDEX idx_chat_messages_tenant_deleted (tenant_id, deleted_at), ALGORITHM=INPLACE, LOCK=NONE;
# 3. Re-run the migration; the guards will skip the now-existing indexes.
```

## 8. Verification

After apply, confirm on each table:

```sql
SHOW CREATE TABLE tasks\G
SHOW CREATE TABLE notifications\G
SHOW CREATE TABLE chat_channels\G
SHOW CREATE TABLE chat_messages\G
SHOW CREATE TABLE chat_message_reads\G
```

Each should show:
- `deleted_at TIMESTAMP NULL DEFAULT NULL`
- A `KEY (tenant_id, deleted_at)` (any name)

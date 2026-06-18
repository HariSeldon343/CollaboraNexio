# Migration 84 — Production Apply Checklist

This migration was tested on dev (XAMPP localhost MariaDB 10.4.32). Production
(`app.nexiosolution.it`) requires the steps below before applying.

> **Status**: NOT applied to production as of 2026-05-02. Do not run blindly.

## Pre-conditions

- [ ] Identify a **low-traffic window** (~10 min). Migration is online but adds 4 new indexes; index build time scales with table size.
- [ ] **Full backup** taken: `mysqldump --single-transaction --routines --triggers collaboranexio > backup_pre_mig84_$(date +%Y%m%d_%H%M%S).sql`. Backup stored offsite, not on the same host.
- [ ] Confirm DB engine + version on prod via `SELECT VERSION();`. If MySQL 8.0+ instead of MariaDB, the same migration runs unchanged (signature guards are portable).
- [ ] Run preflight on prod: `mysql -u <prod-user> -p collaboranexio < tools/index_optimization/preflight.sql`. Capture transcript. Confirm 8 tables exist, 22 columns exist, no name collisions.
- [ ] Estimate row counts on prod for `audit_logs`, `files`, `notifications`. If `audit_logs > 10M rows`, consider adding `, ALGORITHM=INPLACE, LOCK=NONE` to the CREATE INDEX statements (run via raw mysql client, not via the PHP wrapper, since prepared statements cannot include ALGORITHM/LOCK clauses on MariaDB 10.4). For tables under 1M rows on a host with adequate buffer pool, the default INPLACE behavior is sufficient.

## Apply

- [ ] Connect a tmux/screen session so an SSH disconnect doesn't abort.
- [ ] Run: `mysql -u <prod-user> -p collaboranexio < database/migrations/84_composite_tenant_indexes.sql 2>&1 | tee mig84_apply_$(date +%Y%m%d_%H%M%S).log`.
- [ ] Verify final transcript shows all 8 indexes processed (created OR skipped — both are valid outcomes for a healthy run). The verification block at the bottom reports each `(table, index_name, cols)` tuple with the target signature.
- [ ] Capture migration_duration_seconds from final SELECT. Anomalous duration (>5 min) on a small DB suggests something else is contending for the table; investigate before proceeding.

## Post-apply validation

- [ ] Spot-check 3 listing endpoints (file browser, audit log feed, notifications) under typical user. Watch for HTTP 5xx and visible slowness.
- [ ] Re-run preflight (step 4 in `preflight.sql`): no name collisions remaining is irrelevant now, but the column-existence check remains valid.
- [ ] `php tools/db_integrity_audit.php` exit 0, no NEW warnings vs a baseline transcript captured before the migration. Save both transcripts side-by-side for audit.
- [ ] `php tools/db_integrity_quick_check.php` exit 0, JSON `success=true`. Compare counts vs pre-migration snapshot — should match.
- [ ] Check application logs: `logs/php_errors.log` and `logs/database_errors.log` for new entries timestamped after the migration apply. Investigate any.

## Rollback

If post-apply validation reveals a regression, run:
```
mysql -u <prod-user> -p collaboranexio < database/migrations/84_composite_tenant_indexes_rollback.sql
```

**WARNING**: the rollback drops indexes by name (8 names total). On any DB where some of those names referred to pre-existing indexes (most notably `idx_notifications_tenant_created` and `idx_chat_messages_tenant_created` on the dev DB), rollback will drop them too. If preservation is required, manually recreate them from `database/03_complete_schema.sql` after rollback.

## Notes for future operators

- Migration 84 is **signature-guarded**, not name-guarded. Re-applying on a DB that already has equivalent indexes under a different name (e.g., `idx_files_recent` covers `(tenant_id, deleted_at, created_at)`) will skip — it doesn't matter that the name differs.
- 2 indexes that look like targets are intentionally skipped on most DBs because pre-existing equivalents already cover the pattern: `idx_files_recent` (files) and `idx_audit_tenant_deleted` (audit_logs).
- See `working_journal.md` (same directory) for the full execution transcript including discovered duplicates and resolution.

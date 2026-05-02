# Migration 84 — Working Journal

> **Protocol**: read this file at the START of every step, update it BEFORE moving to the next step.
> Single source of truth — supersedes any in-memory assumption.

Last updated: 2026-05-02 (after preflight)
Operator: claude-opus-4-7
Environment: dev (localhost XAMPP — **MariaDB 10.4.32**, NOT MySQL 8.0)
Branch: perf/composite-tenant-indexes

## Current step
done — committing

## Resolution of duplicate indexes
- User authorized DROP via option B.
- Dropped: idx_files_tenant_deleted_created, idx_audit_logs_tenant_deleted_created.
- Migration 84 SQL rewritten with **signature-based** guards (group_concat over information_schema.STATISTICS), so re-applying on any DB skips creation when an index with the same column tuple already exists, regardless of its name.
- Re-applied with new guards: 46/46 statements ok, idempotent.

## Final DB state (8 target tables, signature `(tenant_id, [deleted_at,] created_at)`)
| Table          | Index                                | Source       |
|----------------|--------------------------------------|--------------|
| users          | idx_users_tenant_deleted_created     | NEW          |
| file_shares    | idx_file_shares_tenant_created       | NEW          |
| folders        | idx_folders_tenant_deleted_created   | NEW          |
| task_comments  | idx_task_comments_tenant_created     | NEW          |
| notifications  | idx_notifications_tenant_created     | pre-existing |
| chat_messages  | idx_chat_messages_tenant_created     | pre-existing |
| files          | idx_files_recent                     | pre-existing |
| audit_logs     | idx_audit_tenant_deleted             | pre-existing |

## Smoke tests
- db_integrity_quick_check.php: success=true; pre-existing tenant_mismatch counts unchanged (legitimate Nexio data, not introduced by migration).
- apply_migration_84 re-run: 46/46 statements, 0 errors → idempotency confirmed.
- grep USE INDEX|FORCE INDEX|IGNORE INDEX in *.php: 0 matches → no hardcoded hints to verify.

## Synthetic data state
- seeded: yes (10000+10000+10000 rows)
- cleaned: yes — 30001 rows removed, COUNT=0 on all 4 tables (audit_logs, notifications, files, tenants marker).

## Benchmark interpretation (honest)
On dev with 10K synthetic rows per table, the 3 benchmark queries continued to use pre-existing indexes (which were already structurally adequate for files/notifications/audit_logs). The actual win of migration 84 is on the 4 net-new indexes (users, file_shares, folders, task_comments) — those tables are too small in dev to produce a measurable EXPLAIN delta but will benefit under production growth. No regression observed. r_filtered for Q3 audit_logs hovers between 8-10% across runs (variance), unchanged.

## Errors encountered (chronological)
1. PREPARE-with-ALGORITHM-LOCK syntax error (MariaDB 10.4 limitation). Fixed by removing ALGORITHM/LOCK from prepared CREATE INDEX strings.
2. Discovered post-apply that idx_files_tenant_deleted_created and idx_audit_logs_tenant_deleted_created were structural duplicates of idx_files_recent and idx_audit_tenant_deleted. User authorized DROP. Migration rewritten with signature guard; replays clean on any DB.
3. Verification query at end of migration used GROUP_CONCAT in WHERE → 1111 invalid use of group function. Moved to HAVING.

## Rollback state
not-invoked (forward state is clean and validated)

## Last completed action
Step 1 preflight: ✓
- DB engine = MariaDB 10.4.32 (InnoDB 10.4.32). Implications below.
- All 8 target tables exist (verified via information_schema.TABLES).
- All 22 required (table, column) pairs exist.
- Baseline sizes (row_estimate / total_mb): audit_logs=13401/17.5, files=5331/5.7, notifications=559/0.2, users=38/0.2, folders=7/0.2, file_shares=0/0.1, chat_messages=3/0.1, task_comments=0/0.1.
- **Pre-existing indexes** with planned names (will be skipped by idempotency guard):
    * `chat_messages.idx_chat_messages_tenant_created` — already (tenant_id ASC, created_at ASC). Identical to plan.
    * `notifications.idx_notifications_tenant_created` — already (tenant_id ASC, created_at ASC). Identical to plan.
- Net: 6 indexes to actually create, 2 idempotent skips.

## MariaDB 10.4 implications (load-bearing for execution)
- `EXPLAIN ANALYZE` is NOT MariaDB syntax. Use `ANALYZE SELECT ...` or `ANALYZE FORMAT=JSON SELECT ...` (MariaDB 10.1+).
- `DESC` in `CREATE INDEX ... (col DESC)` is parsed but silently treated as ASC on MariaDB 10.4 (descending indexes added in 10.8+). All indexes will be ASC. The optimizer still produces "Backward index scan" for `ORDER BY ... DESC`, which is comparably efficient — performance unaffected, but be aware when reading EXPLAIN.
- `ALGORITHM=INPLACE, LOCK=NONE` for ADD INDEX on InnoDB IS supported on MariaDB 10.4. Same online DDL semantics as MySQL.
- `IF NOT EXISTS` on CREATE INDEX is also NOT supported on MariaDB 10.4 — confirms the prepared-statement guard idiom from the plan is the correct approach.

## Next planned action
Step 2: write migration files
- database/migrations/84_composite_tenant_indexes.sql (8 guarded blocks)
- database/migrations/84_composite_tenant_indexes_rollback.sql (mirror)
- tools/apply_migration_84_composite_tenant_indexes.php (wrapper)

## Indexes state in DB after applying migration 84
- [x] idx_users_tenant_deleted_created          — NEW, useful
- [x] idx_file_shares_tenant_created            — NEW, useful
- [x] idx_folders_tenant_deleted_created        — NEW, useful
- [x] idx_task_comments_tenant_created          — NEW, useful
- [x] idx_notifications_tenant_created          — already existed (pre-existing pre-migration), guarded skip OK
- [x] idx_chat_messages_tenant_created          — already existed (pre-existing pre-migration), guarded skip OK
- [x] idx_files_tenant_deleted_created          — **DUPLICATE** of `idx_files_recent` (same cols `tenant_id, deleted_at, created_at`). Should be removed.
- [x] idx_audit_logs_tenant_deleted_created     — **DUPLICATE** of `idx_audit_tenant_deleted` (same cols `tenant_id, deleted_at, created_at`). Should be removed.

## Synthetic data state
- seeded: yes (2026-05-02; tenant_id=99999; files=10000, notifications=10000, audit_logs=10000)
- cleaned: no — pending
- tenant_id used for benchmark: 99999

## Benchmark summary (before/after)
- Q1 files paginated: rows=8185, r_rows=250, key=idx_files_recent (pre-existing). No change because the new index is identical in structure.
- Q2 notifications feed: rows=5318, r_rows=50, key=idx_notifications_tenant_created (pre-existing). No change because the index already existed.
- Q3 audit_logs time-range: rows=2245, r_rows=100, r_filtered=8.34% post-migration vs 9.59% pre. Marginal; same key chosen by optimizer.

Conclusion: under the actual schema, only 4 of the planned 8 indexes are net-new. Of these, 2 are likely beneficial (users, file_shares, folders, task_comments — small tables today, will help under growth). The remaining 4 are no-op (pre-existing or structural duplicates).

## Errors encountered
1. PREPARE-with-ALGORITHM-LOCK syntax error (MariaDB 10.4 limitation). Fixed by removing ALGORITHM/LOCK clauses from the prepared CREATE INDEX strings.
2. Discovered post-apply that 2 indexes are structural duplicates of pre-existing ones (same column tuple under different name). DROP INDEX and full rollback both blocked by harness permission system; awaiting user decision.

## Rollback state
not-invoked

## Reference files
- Migration:        database/migrations/84_composite_tenant_indexes.sql
- Rollback:         database/migrations/84_composite_tenant_indexes_rollback.sql
- Wrapper PHP:      tools/apply_migration_84_composite_tenant_indexes.php
- Seed:             tools/index_optimization/seed_synthetic_tenant.php
- Cleanup:          tools/index_optimization/cleanup_synthetic_tenant.php
- Benchmark SQL:    tools/index_optimization/benchmark_queries.sql
- Preflight SQL:    tools/index_optimization/preflight.sql
- Before transcript: tools/index_optimization/explain_before.txt
- After transcript:  tools/index_optimization/explain_after.txt
- Prod checklist:    tools/index_optimization/PROD_APPLY_CHECKLIST.md (created at step 9)

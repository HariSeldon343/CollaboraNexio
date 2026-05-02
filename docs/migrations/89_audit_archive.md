# Migration 89 — `audit_logs_archive` + transactional archive job

## Why

`audit_logs` grows unbounded. Every login, CRUD action, document open, ticket
update, etc. produces a row, and rows are never deleted in normal operation.
On busy tenants this table becomes the largest table in the schema within
months, slowing INSERTs (every secondary index has to be updated on every
write) and forensic queries on recent activity (large index trees).

This migration introduces a **cold-storage** companion table,
`audit_logs_archive`, plus a cron-driven archival job that moves rows older
than N days from the hot table into the cold table in transactional batches.

Key design points:

- **Compressed storage**: `ROW_FORMAT=COMPRESSED KEY_BLOCK_SIZE=8`. Audit
  payloads (`old_values`, `new_values`, `request_data`, `user_agent`) are
  highly compressible JSON/text — typical 3–5x reduction on disk.
- **No FK constraints on the archive**: an archived row must survive even if
  its `tenant_id` or `user_id` is later hard-deleted. The archive is the
  authoritative compliance record after that point.
- **Same column set + `archived_at`**: makes `INSERT ... SELECT` trivial and
  keeps forensic UNION queries simple (see "Querying" below).
- **All audit_logs indexes mirrored** on the archive (without FK
  constraints): forensic queries on archived data stay fast.

## Schema (summary)

`audit_logs_archive` mirrors `audit_logs` 1:1, plus a trailing column:

```sql
archived_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
```

`archived_at` records when the row was moved (useful to confirm retention
windows and to debug archive runs).

The PRIMARY KEY is the original `id` from `audit_logs` (no AUTO_INCREMENT on
the archive — we just preserve the source id, which is what audit references
expect).

## Apply

```bash
mysql -u root collaboranexio < database/migrations/89_audit_archive.sql
```

Idempotent (`CREATE TABLE IF NOT EXISTS`). Safe to re-run.

## Rollback

```bash
mysql -u root collaboranexio < database/migrations/89_audit_archive_rollback.sql
```

> **WARNING**: this destroys archived rows. If you need to keep them,
> re-import into `audit_logs` first (see "Recovery" below).

## Cron job

Script: `cron/archive_audit_logs.php`

### Flags

| Flag | Default | Meaning |
|---|---|---|
| `--older-than-days=N` | `90` | Rows with `created_at < NOW() - INTERVAL N DAY` are eligible |
| `--batch-size=N` | `1000` | Rows per transaction |
| `--max-batches=N` | `50` | Hard cap on batches per run (so a run never moves more than `batch_size * max_batches` rows = 50,000 by default) |
| `--apply` | _off_ | Required to actually write to the DB |
| `--dry-run` | _on by default_ | Explicit dry-run flag (script defaults to dry-run anyway) |

### Default behavior is DRY-RUN

Without `--apply`, the script reports the **first batch** it would archive
and exits without touching the DB. This is intentional: it makes accidental
production runs safe.

### Dry-run example

```bash
$ php cron/archive_audit_logs.php --older-than-days=90 --batch-size=1000 --max-batches=50
DRY-RUN: batch 1 would archive 1000 rows (id range: 12345..13344)
{"ts":"2026-05-02T03:00:00+02:00","mode":"dry-run","older_than_days":90,"batch_size":1000,"max_batches":50,"batches_run":1,"rows_archived":1000,"duration_ms":42,"errors":[]}
```

> Dry-run stops after one batch — otherwise it would report the same rows
> on every iteration since DELETE never runs. The summary's `rows_archived`
> in dry-run mode reflects only that first batch, not the total eligible.

### Apply example

```bash
$ php cron/archive_audit_logs.php --older-than-days=90 --batch-size=1000 --max-batches=50 --apply
{"ts":"2026-05-02T03:00:00+02:00","mode":"apply","older_than_days":90,"batch_size":1000,"max_batches":50,"batches_run":12,"rows_archived":11843,"duration_ms":7251,"errors":[]}
```

## Algorithm (per batch)

1. **Snapshot ids** of the next N eligible rows (oldest first, by id).
2. **DRY-RUN**: print what would happen, exit (one batch only).
3. **APPLY**:
   - `BEGIN`
   - `INSERT INTO audit_logs_archive (...) SELECT ..., NOW() FROM audit_logs WHERE id IN (?...)`
   - `DELETE FROM audit_logs WHERE id IN (?...)`
   - `COMMIT`
   - On exception: **rollback first**, then `error_log()` (CLAUDE.md rule),
     then break out of the batch loop.
4. Sleep 200ms (anti-pressure on a busy DB).

The id snapshot is taken **before** INSERT/DELETE, so concurrent INSERTs into
`audit_logs` during the batch are never touched: their ids are higher than
anything in the snapshot.

## Concurrency

- File lock: `flock(LOCK_EX | LOCK_NB)` on `logs/audit_archive.lock`. If a
  second run starts while one is in progress, it exits with code 2 instead
  of competing.
- Within a single run, batches are sequential (not parallel) by design: this
  keeps the lock window per batch small and bounded.

## Schedule (recommended)

**Weekly, Sunday 03:00 local time.**

### Linux crontab

```cron
0 3 * * 0 /usr/bin/php /var/www/CollaboraNexio/cron/archive_audit_logs.php --apply >> /var/log/cnx_audit_archive.log 2>&1
```

### Windows Task Scheduler

```powershell
schtasks /Create /SC WEEKLY /D SUN /ST 03:00 /TN "CNX Audit Archive" `
  /TR "C:\xampp\php\php.exe C:\xampp\htdocs\CollaboraNexio\cron\archive_audit_logs.php --apply"
```

> The script does **not** install itself in the scheduler. Operators decide
> when (and whether) to enable it.

## Querying across hot + cold

For most use cases (recent forensic queries) you only need `audit_logs`. When
you need a unified view spanning the retention window, `UNION ALL`:

```sql
SELECT id, tenant_id, user_id, action, entity_type, entity_id, created_at,
       'hot' AS source
  FROM audit_logs
 WHERE tenant_id = :tenant_id
   AND created_at >= :from
   AND deleted_at IS NULL
UNION ALL
SELECT id, tenant_id, user_id, action, entity_type, entity_id, created_at,
       'archive' AS source
  FROM audit_logs_archive
 WHERE tenant_id = :tenant_id
   AND created_at >= :from
ORDER BY created_at DESC
LIMIT 200;
```

> The archive does **not** filter by `deleted_at`: rows that were soft-deleted
> in the hot table before being archived simply carry their original
> `deleted_at` value. Decide per-query whether you want to include them.

## Recovery (un-archive)

If you need to bring rows back into the hot table (e.g. for an active
investigation, or before running rollback):

```sql
INSERT INTO audit_logs
  (tenant_id, id, user_id, action, entity_type, entity_id, old_values,
   new_values, metadata, description, ip_address, user_agent, session_id,
   request_method, request_url, request_data, response_code,
   execution_time_ms, memory_usage_kb, severity, status, created_at,
   integrity_algo, integrity_key_id, integrity_prev_hash, integrity_hash,
   integrity_signed_at, deleted_at, updated_at, tenant_deleted_at)
SELECT
   tenant_id, id, user_id, action, entity_type, entity_id, old_values,
   new_values, metadata, description, ip_address, user_agent, session_id,
   request_method, request_url, request_data, response_code,
   execution_time_ms, memory_usage_kb, severity, status, created_at,
   integrity_algo, integrity_key_id, integrity_prev_hash, integrity_hash,
   integrity_signed_at, deleted_at, updated_at, tenant_deleted_at
  FROM audit_logs_archive
 WHERE tenant_id = :tenant_id
   AND created_at BETWEEN :from AND :to;

DELETE FROM audit_logs_archive
 WHERE tenant_id = :tenant_id
   AND created_at BETWEEN :from AND :to;
```

Wrap in a transaction.

## Risks

| Risk | Mitigation |
|---|---|
| Concurrent run pile-up | `flock` lock file, NB mode |
| Long-running run blocking writes | Small batches (1000) + 200ms sleep + per-batch transactions |
| Schema drift (audit_logs gains a column) | Cron detects common columns at runtime via `information_schema` (BUG-156) — extra columns in `audit_logs` not present in `audit_logs_archive` are silently dropped during archive. If full fidelity is needed, add the column to `audit_logs_archive` first. |
| Dry-run false sense of completion | `mode` field in JSON summary makes it explicit; only first batch is reported in dry-run |
| Accidental destructive run | Default behavior is dry-run; `--apply` required explicitly |

## CLAUDE.md compliance

- Tenant scope: archive table is tenant-scoped via `tenant_id` (no FK so it
  survives tenant hard-deletes; soft-deleted-tenant rows carry
  `tenant_deleted_at` from the source row).
- Transactions: rollback **before** `error_log` (CLAUDE.md critical rule).
- PDO: positional placeholders only — no named parameter duplication
  (BUG-148c).
- Schema feature-detection via `information_schema` (BUG-156).
- DDL idempotent (`CREATE TABLE IF NOT EXISTS`, `DROP TABLE IF EXISTS`).

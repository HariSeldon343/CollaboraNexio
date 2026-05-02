-- Migration 84 benchmark queries.
-- Run BEFORE the migration to capture explain_before.txt
-- Run AFTER  the migration to capture explain_after.txt
-- Diff the two transcripts to validate the optimization.
--
-- MariaDB syntax: `ANALYZE [FORMAT=JSON] SELECT ...` is the equivalent
-- of MySQL 8.0.18+ `EXPLAIN ANALYZE`. Both run the query and report the
-- actual execution plan with timings/row counts.

USE collaboranexio;

SELECT '=== Q1: paginated active files (tenant=99999) ===' AS section;
EXPLAIN SELECT id, name, created_at FROM files
  WHERE tenant_id = 99999 AND deleted_at IS NULL
  ORDER BY created_at DESC LIMIT 50 OFFSET 200;
ANALYZE SELECT id, name, created_at FROM files
  WHERE tenant_id = 99999 AND deleted_at IS NULL
  ORDER BY created_at DESC LIMIT 50 OFFSET 200;

SELECT '=== Q2: notifications feed (tenant=99999) ===' AS section;
EXPLAIN SELECT id, message, created_at FROM notifications
  WHERE tenant_id = 99999
  ORDER BY created_at DESC LIMIT 50;
ANALYZE SELECT id, message, created_at FROM notifications
  WHERE tenant_id = 99999
  ORDER BY created_at DESC LIMIT 50;

SELECT '=== Q3: audit log time-range (tenant=99999, last 7 days) ===' AS section;
EXPLAIN SELECT id, action, created_at FROM audit_logs
  WHERE tenant_id = 99999 AND deleted_at IS NULL
    AND created_at >= NOW() - INTERVAL 7 DAY
  ORDER BY created_at ASC LIMIT 100;
ANALYZE SELECT id, action, created_at FROM audit_logs
  WHERE tenant_id = 99999 AND deleted_at IS NULL
    AND created_at >= NOW() - INTERVAL 7 DAY
  ORDER BY created_at ASC LIMIT 100;

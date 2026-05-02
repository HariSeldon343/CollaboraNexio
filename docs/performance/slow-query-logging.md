# Slow Query Logging

Lightweight application-level slow-query log for CollaboraNexio. Designed to
help debug performance issues on environments where MySQL/MariaDB's native
`slow_query_log` cannot be enabled or is not granular enough (e.g. shared
hosting, Cloudflare-fronted production).

## How it works

- Every database query that goes through `Database::query()`
  (`includes/db.php`) is wrapped with a `microtime(true)` measurement.
- If the elapsed time meets or exceeds `SLOW_QUERY_THRESHOLD_MS`, a single
  JSON line is appended to `logs/slow_queries.log`.
- Below the threshold there is **zero overhead** beyond the leading
  `microtime(true)` call — no allocation, no formatting, no I/O.
- Because all helper methods (`fetchOne`, `fetchAll`, `insert`, `update`,
  `delete`, `count`, `exists`, `batchQuery`) ultimately call `query()`, this
  wrap point alone covers every application database call.
- Failed queries (PDOException) are also logged with an `error` field, so
  timeouts and lock waits are visible.

The logger is **non-blocking**: any failure to write the log entry is sent
to PHP `error_log()` and never propagates to the caller.

## Configuration

The threshold is defined in `config.php`:

```php
if (!defined('SLOW_QUERY_THRESHOLD_MS')) define('SLOW_QUERY_THRESHOLD_MS', 500);
```

Production overrides live in `config.production.php` (default `1000` ms).

- **Lower it** (e.g. `100`) when actively profiling.
- **Set it to `0`** to disable logging entirely.
- Changes take effect on the next request — no restart required.

## Log format

One JSON object per line:

```json
{"ts":"2026-05-02T12:34:56.789+02:00","duration_ms":1234,"sql":"SELECT * FROM users WHERE tenant_id = ? AND deleted_at IS NULL","caller":"C:\\xampp\\htdocs\\CollaboraNexio\\api\\users\\list.php:87 listUsers()"}
```

Fields:

| Field          | Type      | Notes                                                          |
| -------------- | --------- | -------------------------------------------------------------- |
| `ts`           | string    | ISO-8601 with milliseconds and timezone offset                 |
| `duration_ms`  | int       | Elapsed milliseconds, rounded                                  |
| `sql`          | string    | Parametrised SQL (truncated to 2000 chars; trailing `…`)       |
| `caller`       | string    | First frame outside `includes/db.php` — `file:line function()` |
| `error`        | string    | Present only on PDOException; truncated to 500 chars           |

### Why no `params`

We deliberately **do not log query parameters**. They frequently contain
PII (emails, names, IDs that map to PII) and would inflate the log. The
parametrised SQL is enough to identify the query pattern and the caller.

## Reading the log

The raw log is plain text — `tail -f logs/slow_queries.log` works, but for
anything beyond a quick eyeball use the report tool:

```bash
php tools/slow_queries_report.php
```

The tool aggregates entries by *normalised SQL pattern* (numeric and
string literals replaced with `?`, whitespace collapsed) and prints a
top-N table sorted by cumulative time.

### CLI flags

| Flag                | Default | Purpose                                                  |
| ------------------- | ------- | -------------------------------------------------------- |
| `--top=N`           | `20`    | Number of patterns to print                              |
| `--since=YYYY-MM-DD`| (none)  | Skip entries whose `ts` is earlier than this date        |
| `--reset`           | -       | Truncate `logs/slow_queries.log` (asks `yes` on stdin)   |
| `--help`            | -       | Show help                                                |

### Output columns

```
#   count   total_ms   avg_ms   p95_ms   max_ms   sample_caller   pattern
```

- **count** — how many times the pattern appeared in the log
- **total_ms** — cumulative duration; primary ranking key
- **avg_ms** — total / count
- **p95_ms** — 95th-percentile duration for the pattern
- **max_ms** — single worst hit
- **sample_caller** — one example caller (first non-empty seen)
- **pattern** — normalised SQL

## Operational notes

- The log file is created on first slow query — no need to pre-create it.
- The log can grow without bound. Either:
  - lower the threshold only when actively investigating, or
  - rotate it (`logrotate` / Windows scheduled task), or
  - call `php tools/slow_queries_report.php --reset` periodically.
- The tool reads the file sequentially — comfortable up to ~hundreds of
  thousands of lines on local disk.
- Concurrent writes from multiple PHP-FPM/Apache workers are safe:
  `file_put_contents(..., FILE_APPEND | LOCK_EX)` serialises appends.

## Troubleshooting

- **No log file created** — either no query exceeded the threshold yet, or
  `logs/` is not writable by the PHP user. Check `logs/php_errors.log`
  for `slow_query_logger:` lines from the non-blocking error path.
- **Logged duration looks wrong** — the timer covers `prepare + execute`
  on the PHP side, including driver-side parsing and result fetching up
  to the point `execute()` returns. Time spent later in `fetchAll()` /
  `fetchOne()` is also wrapped because those go through `query()` again
  is **not** the case — they call `query()` once per statement, so the
  measurement is accurate to that single `prepare+execute` cycle.
- **Caller looks wrong** — the helper skips frames inside
  `includes/db.php` and reports the first application frame. Closures
  may show as `{closure}` if the function name is anonymous.

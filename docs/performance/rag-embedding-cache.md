# RAG Embedding Cache

Persistent DB-backed cache for OpenAI embedding API calls.

## Why

The RAG pipeline (`api/rag/*`, `ai_doc_chunks`) calls `cnx_openai_embed_texts()` repeatedly:

- During indexing of new/updated documents
- For every query at retrieval time

Embeddings are **deterministic** for a given `(model, normalized_text)` pair. Re-calling the API for identical inputs is pure waste:

- **Cost:** OpenAI embeddings are billed per token (e.g. `text-embedding-3-small` ~$0.02 / 1M tokens). Repeated identical calls scale linearly.
- **Latency:** Each call adds ~100–300ms network round-trip even for cached-on-OpenAI's-side inputs. A 1000-chunk document re-index can be dominated by HTTP overhead.
- **Rate limits:** Avoid burning quota on duplicates.

A persistent cache keyed by `sha256(normalized_text)` + model lets us skip both the network call and the billing for any text we've embedded before.

## Hit-rate target

After ~1 week of steady use:
- Indexing pipeline: **>80%** (re-indexes touch many unchanged chunks)
- Query pipeline: **>50%** (common questions, FAQ-style queries repeat)
- Combined: **>70%**

Monitor via `tools/embedding_cache_stats.php`.

## Schema

Table `embedding_cache` (migration `88_embedding_cache.sql`):

| Column         | Type                | Purpose                                       |
|----------------|---------------------|-----------------------------------------------|
| `id`           | BIGINT UNSIGNED PK  | surrogate key                                 |
| `text_hash`    | CHAR(64)            | SHA-256 of normalized text                    |
| `model`        | VARCHAR(64)         | OpenAI model name (e.g. `text-embedding-3-small`) |
| `dim`          | SMALLINT UNSIGNED   | vector dimensionality (verified on read)      |
| `vec`          | LONGBLOB            | binary float32 array (`pack('g*', ...)`)      |
| `token_count`  | INT UNSIGNED NULL   | best-effort, for cost analytics               |
| `hit_count`    | INT UNSIGNED        | incremented on every cache hit                |
| `created_at`   | TIMESTAMP           | initial insert                                |
| `last_hit_at`  | TIMESTAMP NULL      | most recent read (NULL = never hit)           |

- Unique key: `(text_hash, model)` — INSERT IGNORE handles parallel writes.
- Index: `idx_last_hit` for prune queries.
- `ROW_FORMAT=COMPRESSED KEY_BLOCK_SIZE=8` — `vec` blobs compress poorly (random-ish floats), but the rest of the row benefits.

### Vector packing

```php
$packed = pack('g*', ...$floats);    // little-endian float32
$floats = array_values(unpack('g*', $packed));
```

For a 1536-dim embedding (`text-embedding-3-small`): 6144 bytes per blob.
At 100k cached entries → ~600 MB raw, ~400 MB after InnoDB compression. Adjust prune cadence if storage matters.

## Why no `tenant_id`

Standard CLAUDE.md rule §3.1 says all tenant-scoped tables must have `tenant_id` + `deleted_at`. **This table is intentionally global**:

- Embeddings are deterministic functions of `(model, text)`. The same Italian phrase produces the same vector for tenant A and tenant B.
- The text was already sent to OpenAI; storing only its SHA-256 hash + the resulting vector reveals nothing more about who sent it.
- Cross-tenant sharing maximizes hit rate (common phrases like "fattura n. ___", "data di scadenza", procedural boilerplate).
- No business logic depends on this table; it's a pure derivative of API output.

This is a deliberate, documented exception. If a future regulatory/PII review demands tenant scoping, switch to `(tenant_id, text_hash, model)` unique key — but expect hit rate to drop sharply.

## Wrap point

Cache logic lives inside `cnx_openai_embed_texts()` in `includes/openai_client.php`. Callers don't change. Behavior is 100% backward compatible: same input shape, same output shape, same error contract.

Per-call flow:
1. Normalize each text: `mb_strtolower(preg_replace('/\s+/', ' ', trim($text)), 'UTF-8')`
2. SHA-256 hash → cache lookup (per text)
3. Hits returned immediately; misses batched into a single OpenAI call
4. API response written back to cache (`INSERT IGNORE`)
5. Output reassembled in original input order

Logging: every wrapper invocation appends a JSON-line to `logs/embedding_cache.log` with hit/miss counts and elapsed ms.

## Feature flag / rollback

`config.php` defines:

```php
if (!defined('RAG_EMBEDDING_CACHE_ENABLED')) define('RAG_EMBEDDING_CACHE_ENABLED', true);
```

Set to `false` to bypass the cache entirely (every call hits OpenAI). Use this for emergency rollback without dropping the table or rolling back the migration.

For a full rollback: `database/migrations/88_embedding_cache_rollback.sql`.

## Maintenance

```bash
# Report only
php tools/embedding_cache_stats.php

# Top 50 hot entries
php tools/embedding_cache_stats.php --top=50

# Delete entries not hit in 90 days
php tools/embedding_cache_stats.php --prune-older=90

# Preview prune
php tools/embedding_cache_stats.php --prune-older=90 --dry-run
```

Suggested cron: weekly prune at `--prune-older=180`. The cache rebuilds from the next embed call for any pruned (but still active) text.

## Risks

- **Schema drift on `dim`:** If OpenAI changes the dimension of an existing model name (very unlikely), cached vectors mismatch. The lookup verifies `dim` on unpack and treats mismatches as cache misses (warn-logged) — self-healing.
- **Stale embeddings on model retire:** Old model rows linger after a model is retired. They cost storage but never hit. Prune script removes them.
- **Hash collision:** SHA-256, ignorable.

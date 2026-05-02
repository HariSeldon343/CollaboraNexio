-- Migration 88: Persistent embedding cache for OpenAI embeddings
-- Additive, idempotent. Global (NOT tenant-scoped) by design: embeddings
-- are deterministic for (model, normalized_text) and shared safely cross-tenant.
-- See docs/performance/rag-embedding-cache.md for rationale.

CREATE TABLE IF NOT EXISTS `embedding_cache` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `text_hash` CHAR(64) NOT NULL,
  `model` VARCHAR(64) NOT NULL,
  `dim` SMALLINT UNSIGNED NOT NULL,
  `vec` LONGBLOB NOT NULL,
  `token_count` INT UNSIGNED NULL,
  `hit_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_hit_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_hash_model` (`text_hash`, `model`),
  KEY `idx_last_hit` (`last_hit_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=COMPRESSED KEY_BLOCK_SIZE=8;

-- Rollback for migration 88: drop embedding cache table.
DROP TABLE IF EXISTS `embedding_cache`;

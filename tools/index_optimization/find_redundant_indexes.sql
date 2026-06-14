-- Find pairs/groups of indexes that share the same exact column tuple on the same table.
-- Output: table_name, signature (cols), comma-separated list of index names sharing that signature.
-- Operator manually chooses which to keep (usually the most-frequently-referenced name from app code).

USE collaboranexio;

SELECT
    t.table_name,
    t.sig AS column_signature,
    GROUP_CONCAT(t.index_name ORDER BY t.index_name SEPARATOR ', ') AS redundant_index_names,
    COUNT(*) AS index_count
FROM (
    SELECT
        table_name,
        index_name,
        GROUP_CONCAT(column_name ORDER BY seq_in_index) AS sig
    FROM information_schema.STATISTICS
    WHERE table_schema = DATABASE()
      AND non_unique = 1
    GROUP BY table_name, index_name
) t
GROUP BY t.table_name, t.sig
HAVING COUNT(*) > 1
ORDER BY index_count DESC, t.table_name;

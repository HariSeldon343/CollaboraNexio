-- Migration 89: audit_logs_archive (cold storage table)
--
-- Purpose:
--   Provide a separate, compressed table to hold rows aged out of audit_logs
--   so the hot table stays small (faster writes, faster forensic queries on
--   recent activity, smaller backups). The cron job cron/archive_audit_logs.php
--   moves rows transactionally from audit_logs -> audit_logs_archive.
--
-- Schema design:
--   - Mirrors audit_logs columns 1:1 (so INSERT ... SELECT works without
--     transformation), plus archived_at as the trailing column.
--   - ROW_FORMAT=COMPRESSED KEY_BLOCK_SIZE=8: audit payloads (old_values,
--     new_values, request_data, user_agent) are highly compressible JSON/text.
--   - NO foreign keys: an archived row must survive even if its tenant or
--     user is later hard-deleted; FK to tenants/users would break that.
--   - Indexes mirror audit_logs (without FK constraints) so forensic queries
--     remain efficient on the archive.
--
-- Idempotent: CREATE TABLE IF NOT EXISTS.
-- Additive: does not modify audit_logs.

CREATE TABLE IF NOT EXISTS `audit_logs_archive` (
  `tenant_id` int(10) unsigned NOT NULL,
  `id` bigint(20) unsigned NOT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `entity_type` varchar(50) NOT NULL,
  `entity_id` int(10) unsigned DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `metadata` longtext DEFAULT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `session_id` varchar(128) DEFAULT NULL,
  `request_method` varchar(10) DEFAULT NULL,
  `request_url` text DEFAULT NULL,
  `request_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `response_code` int(11) DEFAULT NULL,
  `execution_time_ms` int(10) unsigned DEFAULT NULL,
  `memory_usage_kb` int(10) unsigned DEFAULT NULL,
  `severity` enum('info','warning','error','critical') DEFAULT 'info',
  `status` enum('success','failed','pending') DEFAULT 'success',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `integrity_algo` varchar(16) DEFAULT NULL COMMENT 'Integrity algorithm (e.g. hmac-sha256)',
  `integrity_key_id` varchar(32) DEFAULT NULL COMMENT 'HMAC key identifier (supports rotation)',
  `integrity_prev_hash` char(64) DEFAULT NULL COMMENT 'Previous hash in tenant chain (hex sha256)',
  `integrity_hash` char(64) DEFAULT NULL COMMENT 'Row integrity hash (hex hmac-sha256)',
  `integrity_signed_at` timestamp NULL DEFAULT NULL COMMENT 'When integrity hash was computed',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `tenant_deleted_at` timestamp NULL DEFAULT NULL COMMENT 'Timestamp when parent tenant was soft-deleted (for compliance tracking)',
  `archived_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When this row was moved from audit_logs to archive',
  PRIMARY KEY (`id`),
  KEY `idx_arch_tenant` (`tenant_id`),
  KEY `idx_arch_user` (`user_id`),
  KEY `idx_arch_tenant_user_created` (`tenant_id`, `user_id`, `created_at`),
  KEY `idx_arch_action_created` (`action`, `created_at`),
  KEY `idx_arch_entity` (`entity_type`, `entity_id`),
  KEY `idx_arch_created` (`created_at`),
  KEY `idx_arch_severity` (`severity`, `status`, `created_at`),
  KEY `idx_arch_ip_created` (`ip_address`, `created_at`),
  KEY `idx_arch_session` (`session_id`),
  KEY `idx_arch_tenant_deleted_status` (`tenant_deleted_at`),
  KEY `idx_arch_tenant_status` (`tenant_id`, `tenant_deleted_at`),
  KEY `idx_arch_archived_at` (`archived_at`),
  KEY `idx_arch_tenant_archived` (`tenant_id`, `archived_at`),
  KEY `idx_arch_integrity_hash` (`tenant_id`, `integrity_hash`),
  KEY `idx_arch_integrity_chain` (`tenant_id`, `created_at`, `id`)
) ENGINE=InnoDB
  ROW_FORMAT=COMPRESSED
  KEY_BLOCK_SIZE=8
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Cold storage for rows aged out of audit_logs. Populated by cron/archive_audit_logs.php.';

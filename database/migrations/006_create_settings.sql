-- Single global settings row (org-wide only — no per-user grace-period
-- override column, even though docs/spec.md §6 asked for schema room for
-- one). `late_threshold_minutes` is this schema's name for what spec.md
-- calls the "grace period" — same concept, different vocabulary; worth
-- aligning the docs to this name since the table already exists.
--
-- `google_sheets_sync_enabled` is a live toggle with nothing behind it yet:
-- no table anywhere stores OAuth tokens for it. See schema-notes.md.

CREATE TABLE `settings` (
  `id` tinyint unsigned NOT NULL DEFAULT '1',
  `company_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'TAPP Botanical Co.',
  `working_hours_start` time NOT NULL DEFAULT '08:00:00',
  `working_hours_end` time NOT NULL DEFAULT '17:00:00',
  `late_threshold_minutes` smallint unsigned NOT NULL DEFAULT '10',
  `qr_clock_in_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `google_sheets_sync_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `chk_settings_singleton` CHECK ((`id` = 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

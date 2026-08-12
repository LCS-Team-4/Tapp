-- ==== 009_create_settings_table_live.sql ====
-- The `settings` table from 006_create_settings.sql was missing in the live
-- hosted database (only attendance, google_credentials, leave_balances,
-- leave_requests, and users existed). This migration creates it so the
-- admin working-hours / late-threshold settings work end-to-end.

CREATE TABLE IF NOT EXISTS `settings` (
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

INSERT IGNORE INTO `settings` (`id`) VALUES (1);
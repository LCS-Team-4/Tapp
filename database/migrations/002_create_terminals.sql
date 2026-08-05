-- Physical clock-in terminals (the Pi, and any future scanner hardware).
-- Table is named `devices` in the live schema; file kept as "terminals" to
-- match architecture.md's TerminalRepository/TerminalController naming —
-- same concept, table name is the one place the two vocabularies differ.

CREATE TABLE `devices` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `device_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `device_type` enum('raspberry_pi','qr_scanner','other') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'raspberry_pi',
  `status` enum('connected','disconnected') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'disconnected',
  `last_seen` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

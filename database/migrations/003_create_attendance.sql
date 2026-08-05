-- One row per employee per work day (not one row per scan event — a cleaner
-- design than the old Clock-it/Supabase-era attendance_logs + sessions split).
--
-- FIXED: fk_attendance_user now uses `ON DELETE RESTRICT`, not CASCADE.
-- users.status already gives a soft-delete path ('inactive'); nothing
-- should ever hard-DELETE a user row, so a hard delete should fail loudly
-- instead of silently wiping attendance history. Fixed directly in this
-- migration rather than a follow-up, since this schema isn't merged or
-- deployed anywhere yet and there's no live data to migrate around.
--
-- KNOWN GAP, not fixed here: no `sync_id` column for the offline-sync queue
-- decided in docs/spec.md §11. The UNIQUE (user_id, work_date) key may already
-- serve as the natural idempotency key for that upsert — see schema-notes.md.

CREATE TABLE `attendance` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `work_date` date NOT NULL,
  `clock_in` datetime DEFAULT NULL,
  `clock_out` datetime DEFAULT NULL,
  `total_hours` decimal(5,2) DEFAULT NULL,
  `status` enum('present','late','absent','onsite') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'absent',
  `source` enum('manual','qr','device') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_attendance_user_date` (`user_id`,`work_date`),
  KEY `idx_attendance_date` (`work_date`),
  KEY `idx_attendance_status` (`status`),
  CONSTRAINT `fk_attendance_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Leave requests. Table is `leave_requests` in the live schema.
-- `duration_days` is decimal(4,1), which technically permits half-days
-- (e.g. 0.5) even though docs/spec.md §7 scopes V1 to full-day leave only —
-- flagged in schema-notes.md, not changed here since it may just be
-- forward-compatible precision rather than an actual half-day feature.
--
-- fk_leave_user uses `ON DELETE RESTRICT`, same fix and reasoning as
-- fk_attendance_user in attendance.sql (see that file for details).

CREATE TABLE `leave_requests` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `leave_type` enum('annual','sick','unpaid','emergency') COLLATE utf8mb4_unicode_ci NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `duration_days` decimal(4,1) NOT NULL,
  `reason` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('pending','approved','declined') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `decided_by` int unsigned DEFAULT NULL,
  `decided_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_leave_decided_by` (`decided_by`),
  KEY `idx_leave_status` (`status`),
  KEY `idx_leave_user` (`user_id`),
  CONSTRAINT `fk_leave_decided_by` FOREIGN KEY (`decided_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_leave_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

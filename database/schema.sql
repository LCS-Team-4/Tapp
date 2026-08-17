-- TAPP database schema — rolled up from database/migrations/*.sql
-- Regenerate this file by hand whenever a migration is added; it is not auto-built.

-- ==== 000_create_departments.sql ====
-- Departments. Created before 001_create_users.sql because users.department_id
-- has a foreign key into this table — departments must exist first. (Numbered
-- 000, out of the original six-file scaffold sequence, for exactly that reason;
-- see docs/schema-notes.md.)

CREATE TABLE `departments` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==== 001_create_users.sql ====
-- Employees and admins, one table (role column distinguishes them — see
-- docs/adr/0001-lamp-over-supabase.md for why this replaced the old Supabase
-- "profiles" table naming). Depends on 000_create_departments.sql.

CREATE TABLE `users` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('admin','employee') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'employee',
  `department_id` int unsigned DEFAULT NULL,
  `position` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `annual_leave_balance` decimal(4,1) NOT NULL DEFAULT '15.0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `employee_id` (`employee_id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_users_role` (`role`),
  KEY `idx_users_department` (`department_id`),
  CONSTRAINT `fk_users_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==== 002_create_terminals.sql ====
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

-- ==== 003_create_attendance.sql ====
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

-- ==== 005_create_leave.sql ====
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

-- ==== 006_create_settings.sql ====
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

-- ==== 007_add_rfid_to_users.sql ====
-- Adds the RFID/NFC credential field directly onto `users`, rather than a
-- separate `profiles` table — see docs/schema-notes.md §2 for why the split
-- was considered and rejected.
--
-- Nullable + UNIQUE: not every employee will necessarily have a tapped
-- credential (some may clock in via phone/QR only, per `attendance.source`),
-- but any RFID/NFC tag that IS registered must map to exactly one person.
-- MySQL and MariaDB both allow multiple NULLs in a UNIQUE index, so "optional
-- but unique when present" needs no extra application-level checking.

ALTER TABLE `users`
  ADD COLUMN `rfid_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `employee_id`,
  ADD UNIQUE KEY `rfid_id` (`rfid_id`);

-- ==== 008_add_attendance_correction_fields.sql ====
-- Adds admin-only correction fields to `attendance`, for the case decided
-- in docs/spec.md §6: a rejected third tap (AttendanceService::redeem()'s
-- "already clocked out for today") has no self-service fix — only an admin
-- can correct the record, via AttendanceService::correctRecord(). Same
-- pattern as the leave-decision workflow: only an admin acts on it, and
-- `attendance.source` already anticipated a 'manual' value for exactly
-- this case.
--
-- `corrected_by` uses the same `ON DELETE SET NULL` pattern as
-- `leave_requests.decided_by` — if the correcting admin's account is later
-- removed, that shouldn't block deleting it, just null the reference on
-- whatever they corrected.
--
-- Both columns positioned directly after `source`, keeping "who touched
-- this row, and how" grouped together ahead of the row's own `created_at`.

ALTER TABLE `attendance`
  ADD COLUMN `corrected_by` int unsigned DEFAULT NULL AFTER `source`,
  ADD COLUMN `corrected_at` datetime DEFAULT NULL AFTER `corrected_by`,
  ADD KEY `fk_attendance_corrected_by` (`corrected_by`),
  ADD CONSTRAINT `fk_attendance_corrected_by` FOREIGN KEY (`corrected_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

-- ==== 010_add_google_sheets_webhook_url.sql ====
-- Adds the Google Sheets Web App URL to the settings table so admins can
-- configure the sync target from the admin portal without touching code.
-- The URL is the Apps Script Web App deployment URL (e.g.
-- https://script.google.com/macros/s/AKfycb.../exec) that appends rows
-- to the Google Sheet.

ALTER TABLE `settings`
  ADD COLUMN `google_sheets_webhook_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `google_sheets_sync_enabled`;


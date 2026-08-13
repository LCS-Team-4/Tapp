-- TAPP dev seed data.
--
-- Run once against a freshly-migrated database (all of database/migrations/*.sql
-- applied, in order). NOT idempotent — employee_id, email, rfid_id, and
-- departments.name are all UNIQUE, so re-running this against a database that
-- already has this data will fail on a duplicate-key error. To reset: TRUNCATE
-- attendance, leave_requests, users, devices, departments (in that order, since
-- foreign keys point toward departments/users) and re-run.
--
-- Every login uses the password `password123`, hashed with bcrypt. The hash
-- below was generated with Python's bcrypt library ($2b$ prefix) rather than
-- PHP's password_hash() ($2y$ prefix) — PHP's password_verify() treats both
-- prefixes as the same underlying algorithm and verifies either correctly, so
-- this is safe to use once AuthController exists. If that ever turns out not
-- to hold on the actual PHP/MariaDB version in use, regenerate with
-- `php -r "echo password_hash('password123', PASSWORD_BCRYPT);"` instead.
--
-- employee_id format: A-### for admins, S-### for employees. This matches
-- the live production convention (A-001, A-002 for admins; S-001..S-004 for
-- employees) and the ID generators in UsersController (inviteAdmin uses
-- A-%03d, register uses S-%03d).

-- ==== Departments ====

INSERT INTO `departments` (`name`) VALUES
  ('Administration'),
  ('Operations');

-- ==== Devices ====
-- Single V1 terminal, per docs/spec.md §10. `status` is 'disconnected' since
-- this is dev data, not a live heartbeat from an actual Pi.

INSERT INTO `devices` (`device_name`, `device_type`, `status`) VALUES
  ('Front Door Pi', 'raspberry_pi', 'disconnected');

-- ==== Users ====
-- One admin, two employees. `rfid_id` is deliberately set on only one employee
-- to demonstrate the nullable+UNIQUE behaviour from 007_add_rfid_to_users.sql
-- — Sarah has a registered tag, Thabo and the admin don't yet.

INSERT INTO `users`
  (`employee_id`, `rfid_id`, `name`, `email`, `password_hash`, `role`, `department_id`, `position`, `status`)
VALUES
  ('A-001', NULL, 'Priya Naidoo', 'admin@tapp.app',
   '$2b$10$ovXjyK8UhvUwm9Qidqe5IuPm5UNzhcAudlFuEPpW7LXzcHGP52RhW', 'admin',
   (SELECT id FROM `departments` WHERE `name` = 'Administration'), 'Administrator', 'active'),

  ('S-001', '04A3B2C1', 'Sarah Mthembu', 'sarah@tapp.app',
   '$2b$10$ovXjyK8UhvUwm9Qidqe5IuPm5UNzhcAudlFuEPpW7LXzcHGP52RhW', 'employee',
   (SELECT id FROM `departments` WHERE `name` = 'Operations'), 'Groundskeeper', 'active'),

  ('S-002', NULL, 'Thabo Nkosi', 'thabo@tapp.app',
   '$2b$10$ovXjyK8UhvUwm9Qidqe5IuPm5UNzhcAudlFuEPpW7LXzcHGP52RhW', 'employee',
   (SELECT id FROM `departments` WHERE `name` = 'Operations'), 'Groundskeeper', 'active');

-- ==== Leave requests ====
-- One approved (tests the leave-overrides-absence path in docs/spec.md §6),
-- one still pending (tests that pending leave does NOT shield from an
-- absence mark).

INSERT INTO `leave_requests`
  (`user_id`, `leave_type`, `start_date`, `end_date`, `duration_days`, `reason`, `status`, `decided_by`, `decided_at`)
VALUES
  ((SELECT id FROM `users` WHERE `employee_id` = 'S-001'), 'annual',
   '2026-08-03', '2026-08-05', 3.0, 'Family event', 'approved',
   (SELECT id FROM `users` WHERE `employee_id` = 'A-001'), '2026-07-29 09:15:00'),

  ((SELECT id FROM `users` WHERE `employee_id` = 'S-002'), 'sick',
   '2026-08-01', '2026-08-01', 1.0, 'Doctor appointment', 'pending', NULL, NULL);

-- ==== Attendance ====
-- One row per status value, across the two working days before "today" and
-- today itself, so AttendanceService has real rows to query against before
-- any actual clock-in flow exists. `work_date`/`created_at` assume "today" is
-- 2026-07-30 — shift these forward if the seed is run much later, since
-- `onsite` (still clocked in, no clock_out) is meant to represent today.

INSERT INTO `attendance`
  (`user_id`, `work_date`, `clock_in`, `clock_out`, `total_hours`, `status`, `source`)
VALUES
  -- Sarah: on time, Monday
  ((SELECT id FROM `users` WHERE `employee_id` = 'S-001'), '2026-07-27',
   '2026-07-27 07:55:00', '2026-07-27 17:02:00', 9.12, 'present', 'device'),

  -- Sarah: late, Tuesday (clocked in after the 10-minute grace period)
  ((SELECT id FROM `users` WHERE `employee_id` = 'S-001'), '2026-07-28',
   '2026-07-28 08:14:00', '2026-07-28 17:00:00', 8.77, 'late', 'device'),

  -- Thabo: absent, Wednesday (no clock-in by the deadline; would have been
  -- written by mark_absences.php, not a real clock-in — hence source='manual')
  ((SELECT id FROM `users` WHERE `employee_id` = 'S-002'), '2026-07-29',
   NULL, NULL, NULL, 'absent', 'manual'),

  -- Thabo: onsite today — clocked in, hasn't clocked out yet
  ((SELECT id FROM `users` WHERE `employee_id` = 'S-002'), '2026-07-30',
   '2026-07-30 08:01:00', NULL, NULL, 'onsite', 'qr');

-- ==== Settings ====
-- Singleton row. Values mostly match the column defaults already in
-- 006_create_settings.sql — written out explicitly here so the seed is a
-- complete, readable picture rather than relying on defaults silently.

INSERT INTO `settings`
  (`id`, `company_name`, `working_hours_start`, `working_hours_end`, `late_threshold_minutes`, `qr_clock_in_enabled`, `google_sheets_sync_enabled`)
VALUES
  (1, 'TAPP Botanical Co.', '08:00:00', '17:00:00', 10, 1, 0);

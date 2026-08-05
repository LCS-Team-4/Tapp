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

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

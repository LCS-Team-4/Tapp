-- Flag set when an admin enrols an employee with an auto-generated password.
-- Employee is forced to choose their own password on first visit to the
-- employee portal. Cleared after a successful password change.

ALTER TABLE `users`
  ADD COLUMN `must_change_password` tinyint(1) NOT NULL DEFAULT 0
  AFTER `status`;

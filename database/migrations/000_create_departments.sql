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

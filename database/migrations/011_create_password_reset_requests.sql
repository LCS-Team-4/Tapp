-- Pending password reset requests. Employee submits via forgot-password page;
-- admin must verify before the new password is applied to users.password.

CREATE TABLE IF NOT EXISTS `password_reset_requests` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `employee_id` varchar(20) NOT NULL,
  `email` varchar(150) NOT NULL,
  `new_password_hash` varchar(255) NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `reviewed_by` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_prr_status` (`status`),
  KEY `idx_prr_employee` (`employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

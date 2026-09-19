-- CSC attendance computation settings.
-- Grace defaults to zero because CSC rules do not prescribe a universal grace period.
INSERT INTO `settings` (`key_name`, `value`, `description`) VALUES
('csc_am_start', '08:00', 'CSC morning session start'),
('csc_am_end', '12:00', 'CSC morning session end'),
('csc_pm_start', '13:00', 'CSC afternoon session start'),
('csc_pm_end', '17:00', 'CSC afternoon session end'),
('csc_grace_minutes', '0', 'Agency-authorized grace period; use 0 when none')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

CREATE TABLE IF NOT EXISTS `holidays` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `holiday_date` DATE NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `scope` ENUM('national','local','agency') NOT NULL DEFAULT 'national',
  `is_working` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_holidays_date_scope` (`holiday_date`, `scope`),
  KEY `idx_holidays_date` (`holiday_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


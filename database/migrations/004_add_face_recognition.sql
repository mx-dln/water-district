-- Add Face Recognition Support for Office Attendance Kiosk
-- Run after 003_record_csc_attendance_computations.sql

CREATE TABLE IF NOT EXISTS `employee_faces` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id` INT UNSIGNED NOT NULL,
  `descriptor` MEDIUMTEXT NOT NULL,
  `photo_path` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_faces_employee` (`employee_id`),
  CONSTRAINT `fk_faces_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Optional method flags per punch
ALTER TABLE `attendance`
  ADD COLUMN `time_in_method` VARCHAR(20) DEFAULT 'polygon' AFTER `time_in_photo`,
  ADD COLUMN `time_out_method` VARCHAR(20) DEFAULT 'polygon' AFTER `time_out_photo`,
  ADD COLUMN `break_in_method` VARCHAR(20) DEFAULT 'polygon' AFTER `break_in_photo`,
  ADD COLUMN `break_out_method` VARCHAR(20) DEFAULT 'polygon' AFTER `break_out_photo`;

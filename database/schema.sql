-- GeoSnap Workforce Management System
-- Database Schema for Cauayan City Water District
-- Database: water-district

CREATE DATABASE IF NOT EXISTS `water-district` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `water-district`;

-- -----------------------------------------------------------
-- Roles Table
-- -----------------------------------------------------------
CREATE TABLE `roles` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(50) NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_roles_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Users Table
-- -----------------------------------------------------------
CREATE TABLE `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `role_id` INT UNSIGNED NOT NULL,
  `username` VARCHAR(100) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `remember_token` VARCHAR(255) DEFAULT NULL,
  `reset_token` VARCHAR(255) DEFAULT NULL,
  `reset_expires` DATETIME DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `last_login` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_users_username` (`username`),
  UNIQUE KEY `uk_users_email` (`email`),
  KEY `idx_users_role` (`role_id`),
  KEY `idx_users_active` (`is_active`),
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Departments Table
-- -----------------------------------------------------------
CREATE TABLE `departments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `code` VARCHAR(50) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_departments_name` (`name`),
  KEY `idx_departments_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Employees Table
-- -----------------------------------------------------------
CREATE TABLE `employees` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `department_id` INT UNSIGNED DEFAULT NULL,
  `employee_no` VARCHAR(50) NOT NULL,
  `first_name` VARCHAR(100) NOT NULL,
  `last_name` VARCHAR(100) NOT NULL,
  `middle_name` VARCHAR(100) DEFAULT NULL,
  `position` VARCHAR(255) DEFAULT NULL,
  `contact_no` VARCHAR(50) DEFAULT NULL,
  `email` VARCHAR(255) DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `employment_status` ENUM('regular','probationary','contractual','part-time') DEFAULT 'regular',
  `profile_pic` VARCHAR(255) DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_employees_no` (`employee_no`),
  UNIQUE KEY `uk_employees_user` (`user_id`),
  KEY `idx_employees_dept` (`department_id`),
  KEY `idx_employees_status` (`employment_status`),
  KEY `idx_employees_active` (`is_active`),
  CONSTRAINT `fk_employees_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_employees_dept` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Office Polygons Table
-- -----------------------------------------------------------
CREATE TABLE `office_polygons` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `coordinates` JSON NOT NULL,
  `color` VARCHAR(20) DEFAULT '#2563eb',
  `description` TEXT DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_by` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_polygons_active` (`is_active`),
  CONSTRAINT `fk_polygons_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Attendance Table
-- -----------------------------------------------------------
CREATE TABLE `attendance` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id` INT UNSIGNED NOT NULL,
  `date` DATE NOT NULL,
  `time_in` DATETIME DEFAULT NULL,
  `time_out` DATETIME DEFAULT NULL,
  `break_in` DATETIME DEFAULT NULL,
  `break_out` DATETIME DEFAULT NULL,
  `time_in_lat` DECIMAL(10,7) DEFAULT NULL,
  `time_in_lng` DECIMAL(10,7) DEFAULT NULL,
  `time_out_lat` DECIMAL(10,7) DEFAULT NULL,
  `time_out_lng` DECIMAL(10,7) DEFAULT NULL,
  `break_in_lat` DECIMAL(10,7) DEFAULT NULL,
  `break_in_lng` DECIMAL(10,7) DEFAULT NULL,
  `break_out_lat` DECIMAL(10,7) DEFAULT NULL,
  `break_out_lng` DECIMAL(10,7) DEFAULT NULL,
  `time_in_status` ENUM('verified','outside','pending') DEFAULT 'pending',
  `time_out_status` ENUM('verified','outside','pending') DEFAULT 'pending',
  `break_in_status` ENUM('verified','outside','pending') DEFAULT 'pending',
  `break_out_status` ENUM('verified','outside','pending') DEFAULT 'pending',
  `time_in_photo` VARCHAR(255) DEFAULT NULL,
  `time_out_photo` VARCHAR(255) DEFAULT NULL,
  `break_in_photo` VARCHAR(255) DEFAULT NULL,
  `break_out_photo` VARCHAR(255) DEFAULT NULL,
  `device_info` TEXT DEFAULT NULL,
  `browser_info` TEXT DEFAULT NULL,
  `remarks` TEXT DEFAULT NULL,
  `worked_minutes` INT UNSIGNED NOT NULL DEFAULT 0,
  `tardiness_minutes` INT UNSIGNED NOT NULL DEFAULT 0,
  `undertime_minutes` INT UNSIGNED NOT NULL DEFAULT 0,
  `deficiency_minutes` INT UNSIGNED NOT NULL DEFAULT 0,
  `overtime_minutes` INT UNSIGNED NOT NULL DEFAULT 0,
  `csc_status` VARCHAR(50) DEFAULT NULL,
  `computed_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_attendance_date` (`employee_id`, `date`),
  KEY `idx_attendance_date` (`date`),
  KEY `idx_attendance_employee` (`employee_id`),
  CONSTRAINT `fk_attendance_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Attendance Photos Table
-- -----------------------------------------------------------
CREATE TABLE `attendance_photos` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `attendance_id` INT UNSIGNED NOT NULL,
  `type` ENUM('time_in','time_out','break_in','break_out') NOT NULL,
  `filename` VARCHAR(255) NOT NULL,
  `filepath` VARCHAR(500) NOT NULL,
  `filesize` INT UNSIGNED DEFAULT 0,
  `latitude` DECIMAL(10,7) DEFAULT NULL,
  `longitude` DECIMAL(10,7) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_photos_attendance` (`attendance_id`),
  CONSTRAINT `fk_photos_attendance` FOREIGN KEY (`attendance_id`) REFERENCES `attendance` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Attendance Locations (Historical Log)
-- -----------------------------------------------------------
CREATE TABLE `attendance_locations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `attendance_id` INT UNSIGNED NOT NULL,
  `type` ENUM('time_in','time_out','break_in','break_out') NOT NULL,
  `latitude` DECIMAL(10,7) NOT NULL,
  `longitude` DECIMAL(10,7) NOT NULL,
  `geofence_status` ENUM('inside','outside') NOT NULL,
  `full_response` JSON DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_locations_attendance` (`attendance_id`),
  CONSTRAINT `fk_locations_attendance` FOREIGN KEY (`attendance_id`) REFERENCES `attendance` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Leave Types Table
-- -----------------------------------------------------------
CREATE TABLE `leave_types` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `code` VARCHAR(20) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `days_allowed` INT UNSIGNED DEFAULT 15,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_leavetypes_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Leave Requests Table
-- -----------------------------------------------------------
CREATE TABLE `leave_requests` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id` INT UNSIGNED NOT NULL,
  `leave_type_id` INT UNSIGNED NOT NULL,
  `date_from` DATE NOT NULL,
  `date_to` DATE NOT NULL,
  `days` INT UNSIGNED NOT NULL,
  `reason` TEXT NOT NULL,
  `details` JSON DEFAULT NULL COMMENT 'CS Form 6 employee-section details',
  `attachment` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('pending','approved','rejected','cancelled') DEFAULT 'pending',
  `approved_by` INT UNSIGNED DEFAULT NULL,
  `approved_at` DATETIME DEFAULT NULL,
  `remarks` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_leave_employee` (`employee_id`),
  KEY `idx_leave_status` (`status`),
  KEY `idx_leave_dates` (`date_from`, `date_to`),
  CONSTRAINT `fk_leave_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_leave_type` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_leave_approver` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Settings Table
-- -----------------------------------------------------------
CREATE TABLE `settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `key_name` VARCHAR(100) NOT NULL,
  `value` TEXT DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_settings_key` (`key_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Holidays / non-working days used by CSC attendance reports
-- -----------------------------------------------------------
CREATE TABLE `holidays` (
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

-- -----------------------------------------------------------
-- Audit Logs Table
-- -----------------------------------------------------------
CREATE TABLE `audit_logs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `action` VARCHAR(255) NOT NULL,
  `module` VARCHAR(100) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_user` (`user_id`),
  KEY `idx_audit_action` (`action`),
  KEY `idx_audit_module` (`module`),
  KEY `idx_audit_created` (`created_at`),
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Notifications Table
-- -----------------------------------------------------------
CREATE TABLE `notifications` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `type` ENUM('info','success','warning','danger') DEFAULT 'info',
  `is_read` TINYINT(1) DEFAULT 0,
  `link` VARCHAR(500) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notif_user` (`user_id`),
  KEY `idx_notif_read` (`is_read`),
  KEY `idx_notif_created` (`created_at`),
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Password Resets Table
-- -----------------------------------------------------------
CREATE TABLE `password_resets` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `token` VARCHAR(255) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_resets_user` (`user_id`),
  KEY `idx_resets_token` (`token`),
  CONSTRAINT `fk_resets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------
-- Insert Default Data
-- -----------------------------------------------------------

-- Roles
INSERT INTO `roles` (`id`, `name`, `description`) VALUES
(1, 'Administrator', 'System administrator with full access'),
(2, 'Employee', 'Regular employee with limited access');

-- Leave Types (CS Form No. 6 leave types)
INSERT INTO `leave_types` (`id`, `name`, `code`, `description`, `days_allowed`) VALUES
(1, 'Vacation Leave', 'VL', 'Annual vacation leave', 15),
(2, 'Mandatory/Forced Leave', 'MFL', 'Mandatory five-day leave', 5),
(3, 'Sick Leave', 'SL', 'Medical or health-related leave', 15),
(4, 'Maternity Leave', 'ML', 'Maternity leave for female employees', 105),
(5, 'Paternity Leave', 'PL', 'Paternity leave for male employees', 7),
(6, 'Special Privilege Leave', 'SPL', 'Special privilege leave', 3),
(7, 'Solo Parent Leave', 'SPLP', 'Solo parent leave', 7),
(8, 'Study Leave', 'STL', 'Study leave', 0),
(9, '10-Day VAWC Leave', 'VAWC', 'Leave for victims of violence against women and children', 10),
(10, 'Rehabilitation Privilege', 'RP', 'Rehabilitation privilege', 0),
(11, 'Special Leave Benefits for Women', 'SLBW', 'Special leave benefits for women', 60),
(12, 'Special Emergency (Calamity) Leave', 'SECL', 'Special emergency/calamity leave', 5),
(13, 'Adoption Leave', 'AL', 'Adoption leave', 0),
(14, 'Others', 'OTH', 'Other leave type', 0);

-- Settings
INSERT INTO `settings` (`key_name`, `value`, `description`) VALUES
('site_name', 'GeoSnap Workforce Management', 'System name'),
('company_name', 'Cauayan City Water District', 'Organization name'),
('company_address', 'Cauayan City, Philippines', 'Organization address'),
('company_logo', '', 'Agency logo image path'),
('timezone', 'Asia/Manila', 'System timezone'),
('office_hours_start', '08:00', 'Office hours start time'),
('office_hours_end', '17:00', 'Office hours end time'),
('grace_period', '15', 'Grace period in minutes'),
('late_threshold', '30', 'Late threshold in minutes after grace period'),
('attendance_radius', '50', 'Geofence radius in meters (fallback)'),
('session_timeout', '3600', 'Session timeout in seconds');

INSERT INTO `settings` (`key_name`, `value`, `description`) VALUES
('csc_am_start', '08:00', 'CSC morning session start'),
('csc_am_end', '12:00', 'CSC morning session end'),
('csc_pm_start', '13:00', 'CSC afternoon session start'),
('csc_pm_end', '17:00', 'CSC afternoon session end'),
('csc_grace_minutes', '0', 'Agency-authorized grace period; use 0 when none');

-- Admin user (password: admin123)
-- Password hash generated with PHP password_hash('admin123', PASSWORD_BCRYPT)
INSERT INTO `users` (`id`, `role_id`, `username`, `email`, `password`, `is_active`) VALUES
(1, 1, 'admin', 'admin@ccwd.gov.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1);

-- Default departments
INSERT INTO `departments` (`id`, `name`, `code`, `description`) VALUES
(1, 'General Administration', 'GA', 'General administrative department'),
(2, 'Finance Department', 'FIN', 'Finance and accounting'),
(3, 'Operations Division', 'OPS', 'Water operations and maintenance');

-- Default employee for admin
INSERT INTO `employees` (`id`, `user_id`, `department_id`, `employee_no`, `first_name`, `last_name`, `position`, `employment_status`) VALUES
(1, 1, 1, 'ADM-001', 'System', 'Administrator', 'System Administrator', 'regular');

-- Sample employee accounts (password: employee123)
INSERT INTO `users` (`id`, `role_id`, `username`, `email`, `password`, `is_active`) VALUES
(2, 2, 'jdelacruz', 'juan.delacruz@ccwd.gov.ph', '$2y$10$HxJxd3KvKBZ3ZFy8P5FPGOdGJCjGQ7oVzkZJDYdxmYOe5FxPRROK6', 1),
(3, 2, 'msantos', 'maria.santos@ccwd.gov.ph', '$2y$10$HxJxd3KvKBZ3ZFy8P5FPGOdGJCjGQ7oVzkZJDYdxmYOe5FxPRROK6', 1),
(4, 2, 'rreyes', 'ricardo.reyes@ccwd.gov.ph', '$2y$10$HxJxd3KvKBZ3ZFy8P5FPGOdGJCjGQ7oVzkZJDYdxmYOe5FxPRROK6', 1);

INSERT INTO `employees` (`id`, `user_id`, `department_id`, `employee_no`, `first_name`, `last_name`, `middle_name`, `position`, `employment_status`) VALUES
(2, 2, 1, 'EMP-001', 'Juan', 'Dela Cruz', 'Santos', 'Administrative Assistant', 'regular'),
(3, 3, 2, 'EMP-002', 'Maria', 'Santos', 'Lopez', 'Accountant', 'regular'),
(4, 4, 3, 'EMP-003', 'Ricardo', 'Reyes', 'Garcia', 'Field Technician', 'regular');

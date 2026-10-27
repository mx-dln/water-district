-- Demo seed data for all main GeoSnap pages.
-- Run after database/schema.sql and migrations.
-- This file is intended for local/client demo environments, not production data.

USE `water-district`;

-- Department pages and employee filters
INSERT INTO `departments` (`id`, `name`, `code`, `description`, `is_active`) VALUES
(10, 'Administrative and Human Resources Department', 'AHRD', 'Personnel, records, and administrative services', 1),
(11, 'Commercial Services Department', 'CSD', 'Customer accounts, billing, and collection', 1),
(12, 'Engineering and Operations Department', 'EOD', 'Water production, distribution, and field maintenance', 1),
(13, 'Finance and Accounting Department', 'FAD', 'Budget, accounting, payroll, and cashiering', 1)
ON DUPLICATE KEY UPDATE
  `code` = VALUES(`code`),
  `description` = VALUES(`description`),
  `is_active` = VALUES(`is_active`);

-- Employee and login pages
-- Password for all demo users: employee123
INSERT INTO `users` (`id`, `role_id`, `username`, `email`, `password`, `is_active`) VALUES
(5, 2, 'lgonio', 'lorence.gonio@ccwd.gov.ph', '$2y$10$HxJxd3KvKBZ3ZFy8P5FPGOdGJCjGQ7oVzkZJDYdxmYOe5FxPRROK6', 1),
(6, 2, 'mpvaldez', 'marie.valdez@ccwd.gov.ph', '$2y$10$HxJxd3KvKBZ3ZFy8P5FPGOdGJCjGQ7oVzkZJDYdxmYOe5FxPRROK6', 1),
(7, 2, 'jcastro', 'jonathan.castro@ccwd.gov.ph', '$2y$10$HxJxd3KvKBZ3ZFy8P5FPGOdGJCjGQ7oVzkZJDYdxmYOe5FxPRROK6', 1),
(8, 2, 'aramos', 'ana.ramos@ccwd.gov.ph', '$2y$10$HxJxd3KvKBZ3ZFy8P5FPGOdGJCjGQ7oVzkZJDYdxmYOe5FxPRROK6', 1)
ON DUPLICATE KEY UPDATE
  `role_id` = VALUES(`role_id`),
  `email` = VALUES(`email`),
  `is_active` = VALUES(`is_active`);

INSERT INTO `employees` (
  `id`, `user_id`, `department_id`, `employee_no`, `first_name`, `last_name`,
  `middle_name`, `position`, `contact_no`, `email`, `address`, `employment_status`, `is_active`
) VALUES
(5, 5, 10, 'EMP-005', 'Lorence', 'Gonio', 'Mendoza', 'Administrative Aide', '09170000005', 'lorence.gonio@ccwd.gov.ph', 'Cauayan City, Isabela', 'regular', 1),
(6, 6, 10, 'EMP-006', 'Marie Paz', 'Valdez', 'Santos', 'HR Management Officer', '09170000006', 'marie.valdez@ccwd.gov.ph', 'Cauayan City, Isabela', 'regular', 1),
(7, 7, 12, 'EMP-007', 'Jonathan', 'Castro', 'Reyes', 'Utility Worker', '09170000007', 'jonathan.castro@ccwd.gov.ph', 'Cauayan City, Isabela', 'regular', 1),
(8, 8, 13, 'EMP-008', 'Ana', 'Ramos', 'Lopez', 'Cashier', '09170000008', 'ana.ramos@ccwd.gov.ph', 'Cauayan City, Isabela', 'regular', 1)
ON DUPLICATE KEY UPDATE
  `department_id` = VALUES(`department_id`),
  `first_name` = VALUES(`first_name`),
  `last_name` = VALUES(`last_name`),
  `middle_name` = VALUES(`middle_name`),
  `position` = VALUES(`position`),
  `contact_no` = VALUES(`contact_no`),
  `email` = VALUES(`email`),
  `address` = VALUES(`address`),
  `employment_status` = VALUES(`employment_status`),
  `is_active` = VALUES(`is_active`);

-- Settings page defaults used by reports and forms
INSERT INTO `settings` (`key_name`, `value`, `description`) VALUES
('company_name', 'Cauayan City Water District', 'Organization name'),
('company_address', 'Cauayan City, Isabela, Philippines', 'Organization address'),
('site_name', 'GeoSnap Workforce Management', 'System name'),
('csc_am_start', '08:00', 'CSC morning session start'),
('csc_am_end', '12:00', 'CSC morning session end'),
('csc_pm_start', '13:00', 'CSC afternoon session start'),
('csc_pm_end', '17:00', 'CSC afternoon session end'),
('csc_grace_minutes', '0', 'Agency-authorized grace period; use 0 when none')
ON DUPLICATE KEY UPDATE
  `value` = VALUES(`value`),
  `description` = VALUES(`description`);

-- Office Polygon page
DELETE FROM `office_polygons` WHERE `name` LIKE 'SEED:%';
INSERT INTO `office_polygons` (`name`, `coordinates`, `color`, `description`, `is_active`, `created_by`) VALUES
(
  'SEED: Cauayan City Water District Office Boundary',
  JSON_ARRAY(
    JSON_OBJECT('lat', 16.932950, 'lng', 121.771950),
    JSON_OBJECT('lat', 16.933180, 'lng', 121.772620),
    JSON_OBJECT('lat', 16.932540, 'lng', 121.772850),
    JSON_OBJECT('lat', 16.932260, 'lng', 121.772190)
  ),
  '#2563eb',
  'Demo polygon for attendance geofencing.',
  1,
  1
);

-- Holiday/report support
INSERT INTO `holidays` (`holiday_date`, `name`, `scope`, `is_working`) VALUES
('2026-09-08', 'SEED: Local Charter Day', 'local', 0),
('2026-09-21', 'SEED: Agency Training Day', 'agency', 1)
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `is_working` = VALUES(`is_working`);

-- Attendance, reports, dashboards, employee details, and evidence pages
DELETE FROM `attendance_locations`
WHERE `attendance_id` IN (SELECT `id` FROM `attendance` WHERE `remarks` LIKE 'SEED:%');
DELETE FROM `attendance_photos`
WHERE `attendance_id` IN (SELECT `id` FROM `attendance` WHERE `remarks` LIKE 'SEED:%');
DELETE FROM `attendance`
WHERE `remarks` LIKE 'SEED:%';

INSERT INTO `attendance` (
  `employee_id`, `date`,
  `time_in`, `break_out`, `break_in`, `time_out`,
  `time_in_lat`, `time_in_lng`, `break_out_lat`, `break_out_lng`,
  `break_in_lat`, `break_in_lng`, `time_out_lat`, `time_out_lng`,
  `time_in_status`, `break_out_status`, `break_in_status`, `time_out_status`,
  `remarks`, `worked_minutes`, `tardiness_minutes`, `undertime_minutes`,
  `deficiency_minutes`, `overtime_minutes`, `csc_status`, `computed_at`
) VALUES
-- Lorence Gonio: mix of complete, late, and undertime rows
(5, '2026-09-01', '2026-09-01 07:50:00', '2026-09-01 12:01:00', '2026-09-01 12:19:00', '2026-09-01 17:05:00', 16.9327000, 121.7724000, 16.9327000, 121.7724000, 16.9327000, 121.7724000, 16.9327000, 121.7724000, 'verified', 'verified', 'verified', 'verified', 'SEED: complete day', 480, 0, 0, 0, 0, 'Present', NOW()),
(5, '2026-09-02', '2026-09-02 07:38:00', '2026-09-02 12:03:00', '2026-09-02 12:25:00', '2026-09-02 17:03:00', 16.9327000, 121.7724000, 16.9327000, 121.7724000, 16.9327000, 121.7724000, 16.9327000, 121.7724000, 'verified', 'verified', 'verified', 'verified', 'SEED: complete day', 480, 0, 0, 0, 0, 'Present', NOW()),
(5, '2026-09-03', '2026-09-03 08:05:00', '2026-09-03 12:00:00', '2026-09-03 13:00:00', '2026-09-03 17:00:00', 16.9327000, 121.7724000, 16.9327000, 121.7724000, 16.9327000, 121.7724000, 16.9327000, 121.7724000, 'verified', 'verified', 'verified', 'verified', 'SEED: five-minute late', 475, 5, 0, 5, 0, 'Tardy', NOW()),
(5, '2026-09-04', '2026-09-04 07:38:00', '2026-09-04 12:30:00', '2026-09-04 12:37:00', '2026-09-04 17:01:00', 16.9327000, 121.7724000, 16.9327000, 121.7724000, 16.9327000, 121.7724000, 16.9327000, 121.7724000, 'verified', 'verified', 'verified', 'verified', 'SEED: complete day', 480, 0, 0, 0, 0, 'Present', NOW()),
(5, '2026-09-07', '2026-09-07 07:16:00', '2026-09-07 12:03:00', '2026-09-07 12:56:00', '2026-09-07 17:14:00', 16.9327000, 121.7724000, 16.9327000, 121.7724000, 16.9327000, 121.7724000, 16.9327000, 121.7724000, 'verified', 'verified', 'verified', 'verified', 'SEED: complete day', 480, 0, 0, 0, 0, 'Present', NOW()),
(5, '2026-09-11', '2026-09-11 07:40:00', '2026-09-11 12:04:00', '2026-09-11 12:22:00', '2026-09-11 14:50:00', 16.9327000, 121.7724000, 16.9327000, 121.7724000, 16.9327000, 121.7724000, 16.9327000, 121.7724000, 'verified', 'verified', 'verified', 'verified', 'SEED: undertime afternoon', 412, 0, 130, 68, 0, 'Undertime', NOW()),
-- Marie Valdez: report template rows
(6, '2026-09-02', '2026-09-02 07:53:00', '2026-09-02 12:06:00', '2026-09-02 12:16:00', '2026-09-02 17:11:00', 16.9327500, 121.7724300, 16.9327500, 121.7724300, 16.9327500, 121.7724300, 16.9327500, 121.7724300, 'verified', 'verified', 'verified', 'verified', 'SEED: complete day', 480, 0, 0, 0, 0, 'Present', NOW()),
(6, '2026-09-03', '2026-09-03 07:56:00', '2026-09-03 12:04:00', '2026-09-03 12:53:00', '2026-09-03 17:05:00', 16.9327500, 121.7724300, 16.9327500, 121.7724300, 16.9327500, 121.7724300, 16.9327500, 121.7724300, 'verified', 'verified', 'verified', 'verified', 'SEED: complete day', 480, 0, 0, 0, 0, 'Present', NOW()),
(6, '2026-09-09', '2026-09-09 07:56:00', '2026-09-09 12:16:00', '2026-09-09 12:17:00', '2026-09-09 17:14:00', 16.9327500, 121.7724300, 16.9327500, 121.7724300, 16.9327500, 121.7724300, 16.9327500, 121.7724300, 'verified', 'verified', 'verified', 'verified', 'SEED: complete day', 480, 0, 0, 0, 0, 'Present', NOW()),
-- Field and finance sample rows
(7, '2026-09-21', '2026-09-21 08:12:00', '2026-09-21 12:02:00', '2026-09-21 13:18:00', '2026-09-21 17:02:00', 16.9329000, 121.7725500, 16.9329000, 121.7725500, 16.9329000, 121.7725500, 16.9329000, 121.7725500, 'verified', 'verified', 'verified', 'verified', 'SEED: late and PM late', 450, 30, 0, 30, 0, 'Tardy', NOW()),
(8, '2026-09-21', '2026-09-21 07:48:00', '2026-09-21 12:00:00', '2026-09-21 13:00:00', '2026-09-21 16:40:00', 16.9328500, 121.7725000, 16.9328500, 121.7725000, 16.9328500, 121.7725000, 16.9328500, 121.7725000, 'verified', 'verified', 'verified', 'verified', 'SEED: early out', 460, 0, 20, 20, 0, 'Undertime', NOW());

INSERT INTO `attendance_locations` (`attendance_id`, `type`, `latitude`, `longitude`, `geofence_status`)
SELECT a.`id`, punch.`type`, 16.9327000, 121.7724000, 'inside'
FROM `attendance` a
JOIN (
  SELECT 'time_in' AS `type` UNION ALL
  SELECT 'break_out' UNION ALL
  SELECT 'break_in' UNION ALL
  SELECT 'time_out'
) punch
WHERE a.`remarks` LIKE 'SEED:%';

-- Leave Requests and CS Form 6 pages
DELETE FROM `leave_requests` WHERE `reason` LIKE 'SEED:%';
INSERT INTO `leave_requests` (
  `employee_id`, `leave_type_id`, `date_from`, `date_to`, `days`, `reason`,
  `details`, `attachment`, `status`, `approved_by`, `approved_at`, `remarks`, `created_at`
) VALUES
(5, 3, '2026-09-16', '2026-09-18', 3, 'SEED: Fever and medical consultation',
 JSON_OBJECT('salary','PHP 25,000.00','sick_outpatient',true,'sick_outpatient_specify','Fever','commutation','not_requested'),
 NULL, 'pending', NULL, NULL, NULL, '2026-09-12 09:15:00'),
(6, 1, '2026-09-23', '2026-09-24', 2, 'SEED: Family errand within the Philippines',
 JSON_OBJECT('salary','PHP 32,000.00','vacation_within',true,'vacation_within_specify','Cauayan City','commutation','requested'),
 NULL, 'approved', 1, '2026-09-14 14:10:00', 'Approved for demo seed.', '2026-09-13 10:30:00'),
(8, 11, '2026-09-28', '2026-09-30', 3, 'SEED: Special leave benefits for women',
 JSON_OBJECT('salary','PHP 28,500.00','special_women_illness','Post-operation recovery','commutation','not_requested'),
 NULL, 'rejected', 1, '2026-09-15 16:20:00', 'Sample rejected request.', '2026-09-14 08:45:00');

-- Notifications page/dropdown
DELETE FROM `notifications` WHERE `title` LIKE 'SEED:%';
INSERT INTO `notifications` (`user_id`, `title`, `message`, `type`, `is_read`, `link`) VALUES
(1, 'SEED: New Leave Request', 'Lorence Gonio submitted a sick leave request.', 'info', 0, 'index.php?page=leaves'),
(5, 'SEED: Attendance Reminder', 'Remember to complete all four punches today.', 'warning', 0, 'index.php?page=attendance'),
(6, 'SEED: Leave Approved', 'Your vacation leave request has been approved.', 'success', 1, 'index.php?page=leaves');

-- Audit log page/support for admin dashboard history
DELETE FROM `audit_logs` WHERE `description` LIKE 'SEED:%';
INSERT INTO `audit_logs` (`user_id`, `action`, `module`, `description`, `ip_address`, `user_agent`) VALUES
(1, 'seed_demo_data', 'database', 'SEED: demo data loaded for all main pages', '127.0.0.1', 'Seeder'),
(5, 'attendance_time_in', 'attendance', 'SEED: Lorence Gonio time-in demo', '127.0.0.1', 'Seeder'),
(6, 'submit_leave', 'leaves', 'SEED: Marie Paz Valdez leave demo', '127.0.0.1', 'Seeder');

SELECT 'Demo seed data loaded. Demo employee password is employee123.' AS message;

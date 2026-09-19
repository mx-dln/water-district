-- Sample CSC attendance data for August 2026.
-- Prerequisite: run migrations/002_add_csc_attendance_settings.sql first.
-- Safe behavior: INSERT IGNORE preserves any attendance already recorded for a day.

USE `water-district`;

INSERT IGNORE INTO `holidays` (`holiday_date`, `name`, `scope`, `is_working`) VALUES
('2026-08-21', 'Ninoy Aquino Day', 'national', 0),
('2026-08-31', 'National Heroes Day', 'national', 0);

INSERT IGNORE INTO `attendance` (
    `employee_id`, `date`, `time_in`, `break_out`, `break_in`, `time_out`,
    `time_in_status`, `break_out_status`, `break_in_status`, `time_out_status`,
    `remarks`
)
SELECT
    e.id,
    workdays.work_date,
    CASE
        WHEN e.employee_no = 'EMP-001' AND workdays.work_date = '2026-08-12' THEN CONCAT(workdays.work_date, ' 13:03:00')
        WHEN e.employee_no = 'EMP-002' AND workdays.work_date = '2026-08-18' THEN CONCAT(workdays.work_date, ' 08:04:00')
        WHEN e.employee_no = 'EMP-003' AND workdays.work_date = '2026-08-26' THEN CONCAT(workdays.work_date, ' 08:06:00')
        WHEN MOD(DAY(workdays.work_date) + e.id, 7) = 0 THEN CONCAT(workdays.work_date, ' 08:24:00')
        WHEN MOD(DAY(workdays.work_date) + e.id, 5) = 0 THEN CONCAT(workdays.work_date, ' 08:09:00')
        ELSE CONCAT(workdays.work_date, ' 07:', LPAD(48 + MOD(DAY(workdays.work_date) + e.id, 10), 2, '0'), ':00')
    END AS time_in,
    CASE
        WHEN e.employee_no = 'EMP-001' AND workdays.work_date = '2026-08-12' THEN NULL
        WHEN e.employee_no = 'EMP-003' AND workdays.work_date = '2026-08-26' THEN CONCAT(workdays.work_date, ' 12:01:00')
        ELSE CONCAT(workdays.work_date, ' 12:', LPAD(MOD(DAY(workdays.work_date) + e.id, 6), 2, '0'), ':00')
    END AS break_out,
    CASE
        WHEN e.employee_no = 'EMP-001' AND workdays.work_date = '2026-08-12' THEN CONCAT(workdays.work_date, ' 13:03:00')
        WHEN e.employee_no = 'EMP-003' AND workdays.work_date = '2026-08-26' THEN NULL
        WHEN MOD(DAY(workdays.work_date) + e.id, 6) = 0 THEN CONCAT(workdays.work_date, ' 13:17:00')
        ELSE CONCAT(workdays.work_date, ' 12:', LPAD(52 + MOD(DAY(workdays.work_date) + e.id, 8), 2, '0'), ':00')
    END AS break_in,
    CASE
        WHEN e.employee_no = 'EMP-002' AND workdays.work_date = '2026-08-18' THEN CONCAT(workdays.work_date, ' 12:02:00')
        WHEN e.employee_no = 'EMP-003' AND workdays.work_date = '2026-08-26' THEN CONCAT(workdays.work_date, ' 17:03:00')
        WHEN MOD(DAY(workdays.work_date) + e.id, 8) = 0 THEN CONCAT(workdays.work_date, ' 16:36:00')
        WHEN MOD(DAY(workdays.work_date) + e.id, 5) = 0 THEN CONCAT(workdays.work_date, ' 16:52:00')
        ELSE CONCAT(workdays.work_date, ' 17:', LPAD(MOD(DAY(workdays.work_date) + e.id, 9), 2, '0'), ':00')
    END AS time_out,
    'verified', 'verified', 'verified', 'verified',
    CASE
        WHEN e.employee_no = 'EMP-001' AND workdays.work_date = '2026-08-12' THEN 'SAMPLE: Half-day morning absence / tardiness'
        WHEN e.employee_no = 'EMP-002' AND workdays.work_date = '2026-08-18' THEN 'SAMPLE: Half-day afternoon absence / undertime'
        WHEN e.employee_no = 'EMP-003' AND workdays.work_date = '2026-08-26' THEN 'SAMPLE: Missing PM-in punch'
        ELSE 'SAMPLE: August 2026 CSC computation test'
    END
FROM `employees` e
CROSS JOIN (
    SELECT '2026-08-03' AS work_date UNION ALL SELECT '2026-08-04' UNION ALL SELECT '2026-08-05' UNION ALL
    SELECT '2026-08-06' UNION ALL SELECT '2026-08-07' UNION ALL SELECT '2026-08-10' UNION ALL
    SELECT '2026-08-11' UNION ALL SELECT '2026-08-12' UNION ALL SELECT '2026-08-13' UNION ALL
    SELECT '2026-08-14' UNION ALL SELECT '2026-08-17' UNION ALL SELECT '2026-08-18' UNION ALL
    SELECT '2026-08-19' UNION ALL SELECT '2026-08-20' UNION ALL SELECT '2026-08-24' UNION ALL
    SELECT '2026-08-25' UNION ALL SELECT '2026-08-26' UNION ALL SELECT '2026-08-27' UNION ALL
    SELECT '2026-08-28'
) workdays
WHERE e.employee_no IN ('EMP-001', 'EMP-002', 'EMP-003');

-- Expected when the three sample employees exist: up to 57 attendance rows.
SELECT COUNT(*) AS august_sample_rows
FROM `attendance`
WHERE `date` BETWEEN '2026-08-01' AND '2026-08-31'
  AND `remarks` LIKE 'SAMPLE:%';


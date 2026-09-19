-- Store CSC attendance computations on each daily attendance row.
-- Run this after 002_add_csc_attendance_settings.sql.

ALTER TABLE `attendance`
  ADD COLUMN `worked_minutes` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `remarks`,
  ADD COLUMN `tardiness_minutes` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `worked_minutes`,
  ADD COLUMN `undertime_minutes` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `tardiness_minutes`,
  ADD COLUMN `deficiency_minutes` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `undertime_minutes`,
  ADD COLUMN `overtime_minutes` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `deficiency_minutes`,
  ADD COLUMN `csc_status` VARCHAR(50) DEFAULT NULL AFTER `overtime_minutes`,
  ADD COLUMN `computed_at` DATETIME DEFAULT NULL AFTER `csc_status`;


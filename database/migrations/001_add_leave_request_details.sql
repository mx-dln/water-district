-- Migration: Add CS Form 6 details JSON column and full leave type list
-- Run this on existing databases that were created before the CS Form 6 feature.

ALTER TABLE `leave_requests`
    ADD COLUMN `details` JSON DEFAULT NULL COMMENT 'CS Form 6 employee-section details'
    AFTER `reason`;

INSERT IGNORE INTO `leave_types` (`id`, `name`, `code`, `description`, `days_allowed`) VALUES
(2, 'Mandatory/Forced Leave', 'MFL', 'Mandatory five-day leave', 5),
(6, 'Special Privilege Leave', 'SPL', 'Special privilege leave', 3),
(7, 'Solo Parent Leave', 'SPLP', 'Solo parent leave', 7),
(8, 'Study Leave', 'STL', 'Study leave', 0),
(9, '10-Day VAWC Leave', 'VAWC', 'Leave for victims of violence against women and children', 10),
(10, 'Rehabilitation Privilege', 'RP', 'Rehabilitation privilege', 0),
(11, 'Special Leave Benefits for Women', 'SLBW', 'Special leave benefits for women', 60),
(12, 'Special Emergency (Calamity) Leave', 'SECL', 'Special emergency/calamity leave', 5),
(13, 'Adoption Leave', 'AL', 'Adoption leave', 0),
(14, 'Others', 'OTH', 'Other leave type', 0);

INSERT IGNORE INTO `settings` (`key_name`, `value`, `description`) VALUES
('company_logo', '', 'Agency logo image path');

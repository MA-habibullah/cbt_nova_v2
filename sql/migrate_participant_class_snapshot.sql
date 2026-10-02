-- Migration: Snapshot class_id on cbt_exam_participants for Historical Data Immobility
-- Ensures past exam results remain intact with the student's historical class upon grade promotion.

ALTER TABLE `cbt_exam_participants` 
ADD COLUMN IF NOT EXISTS `class_id` bigint unsigned NULL DEFAULT NULL AFTER `student_id`;

-- Add Index for fast lookup on class_id
ALTER TABLE `cbt_exam_participants` 
ADD INDEX IF NOT EXISTS `idx_ep_class_id` (`class_id`);

-- Backfill existing participant records with student current class_id
UPDATE `cbt_exam_participants` p 
JOIN `cbt_students` s ON p.student_id = s.id 
SET p.class_id = s.class_id 
WHERE p.class_id IS NULL OR p.class_id = 0;

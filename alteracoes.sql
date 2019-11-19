ALTER TABLE job_type ADD COLUMN `fixed_budget_value` DECIMAL(3,2) DEFAULT 1;
UPDATE job_type SET `fixed_budget_value` = 1.3 WHERE description = 'Cenografia';
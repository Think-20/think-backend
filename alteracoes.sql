ALTER TABLE job_type ADD COLUMN `fixed_budget_value` DECIMAL(3,2) DEFAULT 1;
UPDATE job_type SET `fixed_budget_value` = 1.3 WHERE description = 'Cenografia';

ALTER TABLE job_activity ADD COLUMN `min_budget_value_to_more_days` DOUBLE(12,2) DEFAULT 0;
UPDATE job_activity SET `min_budget_value_to_more_days` = 200000 WHERE description IN ('Orçamento',
'Modificação de orçamento', 'Opção de orçamento');

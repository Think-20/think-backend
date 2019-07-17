ALTER TABLE job_activity ADD COLUMN only_edit TINYINT(1) DEFAULT 0 AFTER no_params;
INSERT INTO job_activity VALUES (15, 'Modificação de orçamento', 0, 1, 0, 1, NULL);
#INSERT INTO job_activity VALUES (16, 'Opção de orçamento', 0, 1, 0, 0, NULL);
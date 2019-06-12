ALTER TABLE task CHANGE COLUMN project_file_done done tinyint(1) not null default 0;
UPDATE employee SET department_id = 6, position_id = 6 WHERE id = 11; 
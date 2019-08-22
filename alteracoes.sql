DROP TABLE IF EXISTS job_activity_employee;

CREATE TABLE job_activity_employee (
	id int not null auto_increment primary key,
    job_activity_id int not null,
    employee_id int not null,
    
    constraint `fk_employee_id_id_employee` FOREIGN KEY(employee_id) references employee(id),
    constraint `fk_job_activity_id_id_job_activity` FOREIGN KEY(job_activity_id) references job_activity(id),
    unique(job_activity_id, employee_id)
);

#Criação de responsáveis por projeto
INSERT INTO job_activity_employee SELECT NULL, 1, id FROM employee WHERE department_id = 5 AND schedule_active = 1
UNION 
SELECT NULL, 8, id FROM employee WHERE department_id = 5 AND schedule_active = 1
UNION 
SELECT NULL, 9, id FROM employee WHERE department_id = 5 AND schedule_active = 1
UNION 
SELECT NULL, 11, id FROM employee WHERE department_id = 5 AND schedule_active = 1;

#Criação de responsáveis por orçamento
INSERT INTO job_activity_employee SELECT NULL, 2, id FROM employee WHERE department_id = 6 AND schedule_active = 1 AND position_id = 6 and id = 21
UNION 
SELECT NULL, 14, id FROM employee WHERE department_id = 6 AND schedule_active = 1 AND position_id = 6 and id = 21
UNION 
SELECT NULL, 15, id FROM employee WHERE department_id = 6 AND schedule_active = 1 AND position_id = 6 and id = 21
UNION 
SELECT NULL, 16, id FROM employee WHERE department_id = 6 AND schedule_active = 1 AND position_id = 6 and id = 21;

#Criação de responsáveis por memorial
INSERT INTO job_activity_employee SELECT NULL, 13, id FROM employee WHERE department_id = 4 AND schedule_active = 1;

#Criação de responsáveis por detalhamento
INSERT INTO job_activity_employee SELECT NULL, 13, id FROM employee WHERE department_id = 6 AND schedule_active = 1 AND position_id = 8;


ALTER TABLE job_activity ADD COLUMN fixed_duration DOUBLE(3,2) DEFAULT 0;
ALTER TABLE job_activity ADD COLUMN min_duration DOUBLE(3,2) DEFAULT 0;
ALTER TABLE job_activity ADD COLUMN max_duration DOUBLE(4,2) DEFAULT 0;
ALTER TABLE job_activity ADD COLUMN max_budget_value_per_day DOUBLE(12,2) DEFAULT 0;
ALTER TABLE job_activity ADD COLUMN max_duration_value_per_day DOUBLE(4,2) DEFAULT 1;
ALTER TABLE job_activity ADD COLUMN next_period TINYINT(1) DEFAULT 1;
ALTER TABLE job_activity ADD COLUMN next_day TINYINT(1) DEFAULT 1;
ALTER TABLE job_activity ADD COLUMN counter TINYINT(1) DEFAULT 0;
ALTER TABLE job_activity ADD COLUMN initial int DEFAULT 0;  
ALTER TABLE job_activity ADD COLUMN share_max_budget_value_per_day int DEFAULT 0;  

ALTER TABLE job_activity ADD COLUMN `modification_id` INT DEFAULT NULL;
ALTER TABLE job_activity ADD CONSTRAINT `fk_modification_id_job_activity_id` FOREIGN KEY(modification_id) REFERENCES job_activity(id);

ALTER TABLE job_activity ADD COLUMN `option_id` INT DEFAULT NULL;
ALTER TABLE job_activity ADD CONSTRAINT `fk_option_id_job_activity_id` FOREIGN KEY(option_id) REFERENCES job_activity(id);

INSERT INTO `job_activity` (`no_params`, `redirect_after_save`, `fixed_duration`, `min_duration`, `max_duration`, `max_budget_value_per_day`,
`max_duration_value_per_day`, `next_period`, `next_day`, `counter`, `description`) 
VALUES(0, NULL, 1, 0, 0, 0, 1, 0, 1, 1, 'Modificação de outsider');

INSERT INTO `job_activity` (`no_params`, `redirect_after_save`, `fixed_duration`, `min_duration`, `max_duration`, `max_budget_value_per_day`,
`max_duration_value_per_day`, `next_period`, `next_day`, `counter`, `description`) 
VALUES(0, NULL, 0, 0, 0, 0, 1, 0, 1, 1, 'Opção de outsider');

INSERT INTO `job_activity` (`no_params`, `redirect_after_save`, `fixed_duration`, `min_duration`, `max_duration`, `max_budget_value_per_day`,
`max_duration_value_per_day`, `next_period`, `next_day`, `counter`, `description`) 
VALUES(0, NULL, 1, 0, 0, 0, 1, 0, 1, 1, 'Modificação de projeto externo');

INSERT INTO `job_activity` (`no_params`, `redirect_after_save`, `fixed_duration`, `min_duration`, `max_duration`, `max_budget_value_per_day`,
`max_duration_value_per_day`, `next_period`, `next_day`, `counter`, `description`) 
VALUES(0, NULL, 0, 0, 0, 0, 1, 0, 1, 1, 'Opção de projeto externo');

/* Testado o limite das durações (min_duration, max_duration), max_duration_value_per_day = somatória de valores < 1 */
UPDATE `job_activity` SET `no_params` = 0, `redirect_after_save` = NULL, `modification_id` = 8, `option_id` = 9,
`fixed_duration` = 0, `min_duration` = 1, `max_duration` = 3, `max_budget_value_per_day` = 0, `max_duration_value_per_day` = 1,
`next_period` = 0, `next_day` = 1, `counter` = 0, `initial` = 1
WHERE description = 'Projeto';

/* Testado o limite das durações (min_duration, max_duration), max_duration_value_per_day = somatória de valores < 1 */
UPDATE `job_activity` SET `no_params` = 0, `redirect_after_save` = NULL, `modification_id` = 17, `option_id` = 18,
`fixed_duration` = 0, `min_duration` = 4, `max_duration` = 10, `max_budget_value_per_day` = 0, `max_duration_value_per_day` = 1,
`next_period` = 0, `next_day` = 1, `counter` = 0, `initial` = 1
WHERE description = 'Outsider';


/* Somente edição não aparece na inserção, fixed_duration OK, max_budget_value_per_day OK, max_duration_value_per_day OK next_period OK! */
UPDATE `job_activity` SET `no_params` = 0, `redirect_after_save` = NULL, `modification_id` = 15, `option_id` = 16,
`fixed_duration` = 0.2, `min_duration` = 0, `max_duration` = 0, `max_budget_value_per_day` = 400000, `max_duration_value_per_day` = 1,
`next_period` = 1, `next_day` = 0, `counter` = 1, `share_max_budget_value_per_day` = 1
WHERE description = 'Orçamento';

/* Habilitando espaço nos orçamentos antigos */
UPDATE task_item 
LEFT JOIN task ON task.id = task_item.task_id
SET task_item.duration = 0.2 WHERE task.job_activity_id = 2;

/* Somente edição não aparece na inserção, fixed_duration automático - OK */
UPDATE `job_activity` SET `no_params` = 0, `redirect_after_save` = NULL,
`fixed_duration` = 1, `min_duration` = 0, `max_duration` = 0, `max_budget_value_per_day` = 0, `max_duration_value_per_day` = 0,
`next_period` = 0, `next_day` = 0, `counter` = 1
WHERE description = 'Memorial descritivo';

/* Testar fixed_duration????  max_duration_value_per_day = somatória de valores < 1 */
UPDATE `job_activity` SET `no_params` = 0, `redirect_after_save` = NULL,
`fixed_duration` = 1, `min_duration` = 0, `max_duration` = 0, `max_budget_value_per_day` = 0, `max_duration_value_per_day` = 1,
`next_period` = 0, `next_day` = 1, `counter` = 1, `description` = 'Modificação de projeto'
WHERE description = 'Modificação';

UPDATE `job_activity` SET `no_params` = 0, `redirect_after_save` = NULL,
`fixed_duration` = 0, `min_duration` = 0, `max_duration` = 0, `max_budget_value_per_day` = 0, `max_duration_value_per_day` = 1,
`next_period` = 0, `next_day` = 1, `counter` = 1, `description` = 'Opção de projeto'
WHERE description = 'Opção';

/* Testado o limite das durações (min_duration, max_duration), max_duration_value_per_day = somatória de valores < 1 */
UPDATE `job_activity` SET `no_params` = 1, `redirect_after_save` = '/jobs/edit/:id?tab=project',  `modification_id` = 19, `option_id` = 20,
`fixed_duration` = 0, `min_duration` = 0, `max_duration` = 0, `max_budget_value_per_day` = 0, `max_duration_value_per_day` = 0,
`next_period` = 0, `next_day` = 0, `counter` = 0, `initial` = 1
WHERE description = 'Projeto externo';

UPDATE `job_activity` SET `no_params` = 0, `redirect_after_save` = NULL,
`fixed_duration` = 0, `min_duration` = 0, `max_duration` = 0, `max_budget_value_per_day` = 0, `max_duration_value_per_day` = 1,
`next_period` = 0, `next_day` = 1, `counter` = 1
WHERE description = 'Detalhamento';

UPDATE `job_activity` SET `no_params` = 0, `redirect_after_save` = NULL,
`fixed_duration` = 0.5, `min_duration` = 0, `max_duration` = 0, `max_budget_value_per_day` = 0, `max_duration_value_per_day` = 1,
`next_period` = 1, `next_day` = 0, `counter` = 1
WHERE description = 'Modificação de orçamento';

UPDATE `job_activity` SET `redirect_after_save` = NULL,
`fixed_duration` = 0.5, `min_duration` = 0, `max_duration` = 0, `max_budget_value_per_day` = 0, `max_duration_value_per_day` = 1,
`next_period` = 1, `next_day` = 0, `counter` = 1
WHERE description = 'Opção de orçamento';

UPDATE `job_activity` SET `no_params` = 0, `redirect_after_save` = NULL,
`fixed_duration` = 0, `min_duration` = 0, `max_duration` = 0, `max_budget_value_per_day` = 0, `max_duration_value_per_day` = 1,
`next_period` = 0, `next_day` = 1, `counter` = 1
WHERE description = 'Continuação';

ALTER TABLE task DROP COLUMN available_date;
ALTER TABLE task DROP COLUMN duration;

ALTER TABLE job_activity DROP COLUMN `show`;
ALTER TABLE job_activity DROP COLUMN `master`;
ALTER TABLE job_activity DROP COLUMN `only_edit`;
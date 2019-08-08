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
INSERT INTO job_activity_employee SELECT NULL, 2, id FROM employee WHERE department_id = 6 AND schedule_active = 1 AND position_id = 6
UNION 
SELECT NULL, 14, id FROM employee WHERE department_id = 6 AND schedule_active = 1 AND position_id = 6
UNION 
SELECT NULL, 15, id FROM employee WHERE department_id = 6 AND schedule_active = 1 AND position_id = 6
UNION 
SELECT NULL, 16, id FROM employee WHERE department_id = 6 AND schedule_active = 1 AND position_id = 6;

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

/*
-- Query: SELECT * FROM companybook.job_activity
LIMIT 0, 50000

-- Date: 2019-08-08 09:02
*/
UPDATE `job_activity` SET `master` = 0, `show` = 1, `no_params` = 0, `only_edit` = 0, `redirect_after_save` = NULL,
`fixed_duration` = 0, `min_duration` = 1, `max_duration` = 4, `max_budget_value_per_day` = 0, `max_duration_value_per_day` = 1,
`next_period` = 0, `next_day` = 1, `counter` = 0
WHERE description = 'Projeto';


/*
INSERT INTO `job_activity` (`id`,`description`,`master`,`show`,`no_params`,`only_edit`,`redirect_after_save`,`fixed_duration`,`min_duration`,`max_duration`,`max_budget_value_per_day`,`max_duration_value_per_day`,`next_period`,`next_day`,`counter`) VALUES (2,'Orçamento',1,1,0,0,NULL,0.00,0.00,0.00,0.00,1.00,1,1,0);
INSERT INTO `job_activity` (`id`,`description`,`master`,`show`,`no_params`,`only_edit`,`redirect_after_save`,`fixed_duration`,`min_duration`,`max_duration`,`max_budget_value_per_day`,`max_duration_value_per_day`,`next_period`,`next_day`,`counter`) VALUES (8,'Modificação',0,1,0,0,NULL,0.00,0.00,0.00,0.00,1.00,1,1,0);
INSERT INTO `job_activity` (`id`,`description`,`master`,`show`,`no_params`,`only_edit`,`redirect_after_save`,`fixed_duration`,`min_duration`,`max_duration`,`max_budget_value_per_day`,`max_duration_value_per_day`,`next_period`,`next_day`,`counter`) VALUES (9,'Opção',0,1,0,0,NULL,0.00,0.00,0.00,0.00,1.00,1,1,0);
INSERT INTO `job_activity` (`id`,`description`,`master`,`show`,`no_params`,`only_edit`,`redirect_after_save`,`fixed_duration`,`min_duration`,`max_duration`,`max_budget_value_per_day`,`max_duration_value_per_day`,`next_period`,`next_day`,`counter`) VALUES (10,'Detalhamento',0,1,0,0,NULL,0.00,0.00,0.00,0.00,1.00,1,1,0);
INSERT INTO `job_activity` (`id`,`description`,`master`,`show`,`no_params`,`only_edit`,`redirect_after_save`,`fixed_duration`,`min_duration`,`max_duration`,`max_budget_value_per_day`,`max_duration_value_per_day`,`next_period`,`next_day`,`counter`) VALUES (11,'Outsider',0,1,0,0,NULL,0.00,0.00,0.00,0.00,1.00,1,1,0);
INSERT INTO `job_activity` (`id`,`description`,`master`,`show`,`no_params`,`only_edit`,`redirect_after_save`,`fixed_duration`,`min_duration`,`max_duration`,`max_budget_value_per_day`,`max_duration_value_per_day`,`next_period`,`next_day`,`counter`) VALUES (12,'Continuação',1,1,0,0,NULL,0.00,0.00,0.00,0.00,1.00,1,1,0);
INSERT INTO `job_activity` (`id`,`description`,`master`,`show`,`no_params`,`only_edit`,`redirect_after_save`,`fixed_duration`,`min_duration`,`max_duration`,`max_budget_value_per_day`,`max_duration_value_per_day`,`next_period`,`next_day`,`counter`) VALUES (13,'Memorial descritivo',1,0,0,0,NULL,0.00,0.00,0.00,0.00,1.00,1,1,0);
INSERT INTO `job_activity` (`id`,`description`,`master`,`show`,`no_params`,`only_edit`,`redirect_after_save`,`fixed_duration`,`min_duration`,`max_duration`,`max_budget_value_per_day`,`max_duration_value_per_day`,`next_period`,`next_day`,`counter`) VALUES (14,'Projeto externo',0,1,1,0,'/jobs/edit/:id?tab=project',0.00,0.00,0.00,0.00,1.00,1,1,0);
INSERT INTO `job_activity` (`id`,`description`,`master`,`show`,`no_params`,`only_edit`,`redirect_after_save`,`fixed_duration`,`min_duration`,`max_duration`,`max_budget_value_per_day`,`max_duration_value_per_day`,`next_period`,`next_day`,`counter`) VALUES (15,'Modificação de orçamento',0,1,0,1,NULL,0.00,0.00,0.00,0.00,1.00,1,1,0);
*/

ALTER TABLE task DROP COLUMN available_date;
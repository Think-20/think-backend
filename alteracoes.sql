CREATE TABLE job_activity_employee (
	id int not null auto_increment primary key,
    job_activity_id int not null,
    employee_id int not null,
    
    constraint `fk_employee_id_id_employee` FOREIGN KEY(employee_id) references employee(id),
    constraint `fk_job_activity_id_id_job_activity` FOREIGN KEY(job_activity_id) references job_activity(id)
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

ALTER TABLE task DROP COLUMN available_date;
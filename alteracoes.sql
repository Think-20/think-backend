alter table briefing modify column budget DECIMAL(14,2) not null;

alter table job rename to job_activity;
alter table briefing_status rename to job_status;
alter table briefing rename to job;

alter table briefing_file rename to job_file;
alter table briefing_how_come rename to job_how_come;
alter table briefing_level rename to job_level;
alter table briefing_level_briefing rename to job_level_job;
alter table briefing_main_expectation rename to job_main_expectation;
alter table briefing_competition rename to job_competition;

alter table job_file drop foreign key briefing_id_briefing_file_id_briefing;
alter table job_file change column briefing_id job_id int not null;
alter table job_file add constraint `fk_job_id_job_file_id_job` foreign key (job_id) references job (id) on update cascade on delete no action;

alter table job_level_job drop foreign key briefing_id_briefing_level_briefing_id_briefing;
alter table job_level_job change column briefing_id job_id int not null;
alter table job_level_job add constraint `fk_job_id_job_level_job_id_job` foreign key (job_id) references job (id) on update cascade on delete no action;

drop table briefing_special_presentation;

ALTER TABLE job change column `job_id` `job_activity_id` int not null;

UPDATE functionality SET url = REPLACE(url, 'briefing', 'job') WHERE url LIKE '%%';
UPDATE functionality SET description = REPLACE(description, 'briefing', 'job') WHERE url LIKE '%%';
UPDATE display SET url = REPLACE(url, 'briefing', 'job') WHERE url LIKE '%%';
UPDATE display SET description = REPLACE(description, 'briefing', 'job') WHERE url LIKE '%%';

ALTER TABLE job DROP COLUMN internal_creation;
ALTER TABLE job DROP COLUMN available_date;
ALTER TABLE job DROP FOREIGN KEY job_ibfk_6;
ALTER TABLE job DROP COLUMN creation_id;
ALTER TABLE job DROP COLUMN estimated_time;

CREATE TABLE briefing (
	id int not null auto_increment primary key,
    job_id int not null,
    available_date date not null,
    responsible_id int not null,
    estimated_time DECIMAL(3,1) not null,
    
    constraint `fk_job_id_briefing_id_job` foreign key (job_id) references job (id) on update cascade on delete no action,
    constraint `fk_responsible_id_briefing_id_employee` foreign key (responsible_id) references employee (id) on update cascade on delete no action
);

CREATE TABLE budget (
	id int not null auto_increment primary key,
    job_id int not null,
    available_date date not null,
    responsible_id int not null,
    
    constraint `fk_job_id_budget_id_job` foreign key (job_id) references job (id) on update cascade on delete no action,
    constraint `fk_responsible_id_budget_id_employee` foreign key (responsible_id) references employee (id) on update cascade on delete no action
);


DELETE FROM briefing_presentation_briefing;
DELETE FROM briefing;
DELETE FROM job_level_job;
DELETE FROM job_file;
DELETE FROM job;
DELETE FROM job_activity WHERE description NOT IN ('Orçamento', 'Projeto');

insert into functionality values(null, '/briefing/save', 'Cadastrar um briefing');
insert into functionality values(null, '/briefing/edit', 'Editar um briefing');

insert into functionality values(null, '/budget/save', 'Cadastrar um orçamento');
insert into functionality values(null, '/budget/edit', 'Editar um orçamento');

alter table job change column budget budget_value decimal(14,2) not null;




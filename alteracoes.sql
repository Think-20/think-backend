alter table briefing drop foreign key fk_briefing_id_briefing_id;
alter table briefing drop column briefing_id;
alter table briefing drop foreign key fk_responsible_id_employee_id;
alter table briefing drop column responsible_id;

alter table briefing modify column estimated_time DECIMAL(3,1) default null;
alter table briefing add column internal_creation tinyint(1) not null default 1;
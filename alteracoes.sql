insert into job_activity values (null, 'Continuação');
alter table job_activity add column master tinyint(1) default 0;
update job_activity set master = 1 where description = 'Continuação';
insert into job_activity values (null, 'Projeto externo', 0, 1);
update job_activity set `show` = 0 where id = 2;
alter table job_activity add column no_params tinyint(1) default 0;
update job_activity set `no_params` = 1 where id = 14;
alter table job_activity add column redirect_after_save varchar(100);
update job_activity set `redirect_after_save` = '/jobs/edit/:id?tab=project' where id = 14;

alter table task modify duration decimal(3,1);
alter table task modify available_date date;
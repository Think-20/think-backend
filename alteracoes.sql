alter table job_activity add column `show` tinyint(1) not null default 1;
insert into job_activity values (null, 'Memorial descritivo', 1, 0);

alter table task add column `task_id` int default null; 
alter table task add constraint `task_task_id` foreign key (task_id) references task(id);

insert into notification_type values (16, 'Entrega de projeto', 6, 1);
insert into notification_rule select null, 16, id from user;




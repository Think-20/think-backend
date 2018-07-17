alter table job add column note text(5000);

create table task (
	id int not null auto_increment primary key,
    job_id int not null,
    job_activity_id int not null,
    duration decimal(3,1) not null,
    available_date datetime not null,
    responsible_id int not null,
    
    constraint `fk_job_id_task` foreign key(job_id) references job (id) on update cascade on delete no action,
    constraint `fk_job_activity_id_task` foreign key(job_activity_id) references job_activity (id) on update cascade on delete no action,
    constraint `fk_responsible_id_task` foreign key(responsible_id) references employee (id) on update cascade on delete no action
);

create table task_item (
	id int not null auto_increment primary key,
    task_id int not null,
    duration decimal(3,1) not null,
    date date not null,
    
    constraint `fk_task_id_task_item` foreign key(task_id) references task (id) on update cascade on delete no action
);

insert into job_activity values (null, 'Modificação'), (null, 'Opção'), (null, 'Detalhamento');
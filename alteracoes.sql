create table briefing_status (
    id int not null auto_increment primary key,
    description varchar(20) not null,
    unique(description)
);

insert into briefing_status values (1, 'Stand-by');
insert into briefing_status values (2, 'Declinado');
insert into briefing_status values (3, 'Aprovado');
insert into briefing_status values (4, 'Reprovado');

alter table briefing add column status_id int not null default 1;
alter table briefing add constraint `fk_status_id_briefing_status_id` foreign key (status_id) references briefing_status (id) on update cascade on delete no action;
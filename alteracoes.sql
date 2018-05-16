alter table briefing add column not_client varchar(100);
alter table briefing modify column agency_id int(11);
alter table briefing modify column client_id int(11);

/*
alter table briefing drop column latest_mounts_file;
alter table briefing drop column colors_file;
alter table briefing add column estimated_time int(2);

create table briefing_file (
	id int not null auto_increment primary key,
    filename varchar(255),
    briefing_id int not null,
    
    constraint `briefing_id_briefing_file_id_briefing` foreign key (briefing_id) references briefing (id) on update cascade on delete no action
);

create table briefing_level_briefing (
	id int not null auto_increment primary key,
    briefing_id int not null,
    level_id int not null,
    
	constraint `briefing_id_briefing_level_briefing_id_briefing` foreign key (briefing_id) references briefing (id) on update cascade on delete no action,
    constraint `level_id_briefing_level_briefing_id_briefing_level` foreign key (level_id) references briefing_level (id) on update cascade on delete no action
);

alter table briefing add column budget DECIMAL(10,2) not null;
alter table briefing drop foreign key briefing_ibfk_12;
alter table briefing drop column level_id;

insert into display values (33, '/schedule', 'Cronograma do atendimento');

alter table timecard modify column autoEntryPlace varchar(100) not null;
alter table timecard modify column autoExitPlace varchar(100);
*/
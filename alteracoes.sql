create table client_comission (
	id int not null auto_increment primary key,
    description varchar(50),
    unique(description)
);

insert into client_comission values (null, 'Atendimento via empresa'), (null, 'Atendimento parcial'), (null, 'Atendimento compartilhado');

alter table client add column comission_id int;
update client set comission_id = 1;
alter table client modify column comission_id int not null;

alter table client add constraint `fk_client_comission_id_client` foreign key(comission_id) 
references client_comission (id) on update cascade on delete no action;
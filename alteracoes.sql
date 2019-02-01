create table log_client (
	id int not null auto_increment primary key,
    client_id int not null,
    description text(1000) not null,
    type varchar(255) not null,
    date datetime default current_timestamp,
    
    constraint `fk_client_id_log_client_id_employee` foreign key(client_id) 
    references client(id)
);
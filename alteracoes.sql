create table notification_genre (
	id int not null auto_increment primary key,
    description varchar(200) not null,
    unique(description)
);

insert into notification_genre values (null, 'Cadastro'), (null, 'Alteração'), (null, 'Deleção'), (null, 'Aniversário'), 
(null, 'Aprovação'), (null, 'Movimentação'), (null, 'Diversos'), (null, 'Sistema');


create table notification_type (
	id int not null auto_increment primary key,
    description varchar(200) not null,
    genre_id int not null,
    active tinyint(1) default 1,
    unique(description),
    constraint `fk_genre_id_notification_type` foreign key(genre_id) references notification_genre(id)
);

insert into notification_type values (null, 'Cadastro de cliente', 1), (null, 'Alteração de cliente', 2), (null, 'Deleção de cliente', 3),
(null, 'Cadastro de job', 1), (null, 'Alteração de job', 2), (null, 'Deleção de job', 3), (null, 'Aprovação de job', 5), (null, 'Sinalização de job', 6),
(null, 'Cadastro de tarefa', 1), (null, 'Alteração de tarefa', 2), (null, 'Deleção de tarefa', 3), (null, 'Movimentação de tarefa', 6),
(null, 'Aniversário', 4),  (null, 'Diversos', 7), (null, 'Sistema', 8);

create table notification_rule (
	id int not null auto_increment primary key,
    type_id int not null,
    user_id int not null,
    unique(type_id, user_id),
    constraint `fk_type_id_notification_rule` foreign key(type_id) references notification_type(id),
    constraint `fk_user_id_notification_rule` foreign key(user_id) references user(id)
);

create table notification (
	id int not null auto_increment primary key,
    type_id int not null,
    notifiable_id int not null,
    notifiable_type varchar(30) not null,
    info varchar(15),
    date datetime default current_timestamp,
    message varchar(255),
    constraint `fk_type_id_notification` foreign key(type_id) references notification_type(id)
);

create table user_notification (
	id int not null auto_increment primary key,
    notification_id int not null,
    user_id int not null,
    `special` tinyint(1) not null default 0,
    `special_message` varchar(50),
    `received` tinyint(1) not null default 0,
    `received_date` datetime default null,    
    `read` tinyint(1) not null default 0,
    `read_date` datetime default null,
    constraint `fk_notification_id_user_notification` foreign key(notification_id) references notification(id),
    constraint `fk_user_id_user_notification` foreign key(user_id) references user(id)    	
);

create table agent (
	id int not null auto_increment primary key,
    name varchar(30) not null,
    description varchar(30) not null
);

insert into agent values (null, 'Sistema', 'Sistema');
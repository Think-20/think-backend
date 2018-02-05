create table job (
	id int not null auto_increment primary key,
    description varchar(30) not null,
    
    unique(description)
);

insert into job values (null, 'Projeto'), (null, 'Modificação'), (null, 'Detalhamento'), (null, 'Outside'), (null, 'Opção'), (null, 'Orçamento'), (null, 'Continuação');

create table job_type (
	id int not null auto_increment primary key,
    description varchar(30) not null,
    unique(description)
);

insert into job_type values (null, 'Cenografia'), (null, 'Stand'), (null, 'PDV'), (null, 'Showroom');

create table briefing_competition (
	id int not null auto_increment primary key,
    description varchar(30) not null,
    unique(description)
);

insert into briefing_competition values (null, 'Sem concorrência'), (null, 'Agências'), (null, 'Montadoras');

create table briefing_presentation (
	id int not null auto_increment primary key,
    description varchar(60) not null,
    note varchar(255) not null,
    unique(description)
);

insert into briefing_presentation values (null, 'Padrão', ''), (null, 'Padrão + ilustração de produtos ou equipamentos', ''), (null, 'Padrão + imagens de detalhes', '');

create table briefing_special_presentation (
	id int not null auto_increment primary key,
    description varchar(30) not null,
    unique(description)
);

insert into briefing_special_presentation values (null, 'PDF margem para e-mail'), (null, 'PDF margem alta definição'), (null, 'Apenas JPG'), (null, 'Apenas JPG em alta definição'), (null, 'Animação / Interativa'), (null, 'Especial');

create table briefing (
	id int not null auto_increment primary key,
    job_id int not null,
    exhibitor_id int not null,
    event varchar(150) not null,
    deadline date not null,
    job_type_id int not null,
    agency_id int not null,
    attendance_id int not null,
    creation_id int not null,
    area decimal (7,2) not null,
    budget decimal (10,2) not null,
    rate int not null,
    competition_id int not null,
    latest_mounts_file varchar(255) not null,
    colors_file varchar(255) not null,
    guide_file varchar(255) not null,
    presentation_id int not null,
    special_presentation_id int not null,
    approval_expectation_rate int not null,
    created_at timestamp default current_timestamp,
    updated_at timestamp on update current_timestamp,
    
    foreign key (job_id) references job (id) on update cascade on delete no action,
    foreign key (exhibitor_id) references client (id) on update cascade on delete no action,
    foreign key (job_type_id) references job_type (id) on update cascade on delete no action,
    foreign key (agency_id) references client (id) on update cascade on delete no action,
    foreign key (attendance_id) references employee (id) on update cascade on delete no action,
    foreign key (creation_id) references employee (id) on update cascade on delete no action,
    foreign key (competition_id) references briefing_competition (id) on update cascade on delete no action,
    foreign key (presentation_id) references briefing_presentation (id) on update cascade on delete no action,
    foreign key (special_presentation_id) references briefing_special_presentation (id) on update cascade on delete no action
);

create table stand_configuration (
	id int not null auto_increment primary key,
    description varchar(30) not null,
    unique(description)	
);

insert into stand_configuration values (null, 'Interna'), (null, 'Corredor'), (null, 'Esquina'), (null, 'Ponta de ilha'), (null, 'Ilha'), (null, 'Irregular');

create table stand_genre (
	id int not null auto_increment primary key,
    description varchar(30) not null,
    unique(description)	
);

insert into stand_genre values (null, 'Livre'), (null, 'Minimalista'), (null, 'Temático'), (null, 'Clean'), (null, 'Sofisticado'), (null, 'Sustentável');

create table stand (
	id int not null auto_increment primary key,
    briefing_id int not null,
    configuration_id int not null,
    place varchar(150) not null,
    plan varchar(255) not null,
    regulation varchar(255) not null,
    `column` tinyint(1) not null,
    street_number varchar(4) not null,
    genre_id int not null,
    closed_area_percent int(3) not null,
    note text(5000),
    foreign key (briefing_id) references briefing (id) on update cascade on delete no action,
    foreign key (configuration_id) references stand_configuration (id) on update cascade on delete no action,
    foreign key (genre_id) references stand_genre (id) on update cascade on delete no action
);

insert into functionality values (45, '/briefing/save', 'Cadastrar um briefing');
insert into functionality values (46, '/briefing/edit', 'Editar um briefing');
insert into functionality values (47, '/briefing/remove/{id}', 'Remover um briefing');
insert into functionality values (48, '/briefings/all', 'Listar todos os briefings');
insert into functionality values (49, '/briefings/get/{id}', 'Visualizar informações de um briefing');
insert into functionality values (50, '/briefings/filter/{query}', 'Filtrar um briefing');

insert into user_functionality
	select null, user.id, 45
		from user left join employee on user.employee_id = employee.id
	where employee.department_id in (1,2,4);
insert into user_functionality
	select null, user.id, 46
		from user left join employee on user.employee_id = employee.id
	where employee.department_id in (1,2,4);
insert into user_functionality
	select null, user.id, 47
		from user left join employee on user.employee_id = employee.id
	where employee.department_id in (1,2,4);
insert into user_functionality
	select null, user.id, 48
		from user left join employee on user.employee_id = employee.id
	where employee.department_id in (1,2,4);
insert into user_functionality
	select null, user.id, 49
		from user left join employee on user.employee_id = employee.id
	where employee.department_id in (1,2,4);
insert into user_functionality
	select null, user.id, 50
		from user left join employee on user.employee_id = employee.id
	where employee.department_id in (1,2,4);

insert into position values (7, 'Designer', '.');
insert into department values (5, 'Criação');

insert into employee values (19, 'Edgar', 0.0, 7, 5);







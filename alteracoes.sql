create table place (
	id int not null auto_increment primary key,
	name varchar(100) not null,
    street VARCHAR(50) not null,
    number VARCHAR(11),
    neighborhood VARCHAR(30) not null,
    complement VARCHAR(255),
    city_id INT(11) not null,
    cep INT(11) not null,
    created_at datetime default CURRENT_TIMESTAMP,
    updated_at datetime default CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	
    unique(name),
    constraint `fk_city_id_place_id_city` foreign key(city_id) references city(id)
);

insert into functionality (id, url, description) values 
(123, '/place/save', 'Cadastrar um local'),
(124, '/place/edit', 'Editar local'),
(125, '/place/remove/{id}',	'Deletar um local'),
(126, '//places/all', 'Listar todos os locais'),
(127, '/places/filter',	'Filtrar um local'),
(128, '/places/get/{id}', 'Visualizar dados de um Local');

insert into display (id, url, description) values
(39,	'/places/list',	'Listagem de locais'),
(40,	'/places/new',	'Cadastro de local'),
(41,	'/places/edit/:id',	'Editar local');
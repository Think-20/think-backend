drop table pricing;

create table pricing (
	id int not null auto_increment primary key,
    item_id int not null,
    measure_id int not null,
    provider_id int not null,
    price decimal(7,2) not null,
    date timestamp default current_timestamp,
    constraint `fk_item_id_pricing_id_item` foreign key(item_id) references item(id),
    constraint `fk_measure_id_pricing_id_measure` foreign key(measure_id) references measure(id),
    constraint `fk_provider_id_pricing_id_provider` foreign key(provider_id) references provider(id)
);

create table item_type (
	id int not null auto_increment primary key,
    description varchar(30) not null,
    unique(description)
);

insert into item_type (description) values ('Simples'), ('Composto');

create table child_item (
	id int not null auto_increment primary key,
    parent_item_id int not null,
    child_item_id int not null,
    measure_id int not null,
    quantity decimal(7,2),
    
    constraint `fk_parent_item_id_child_item_id_item` foreign key(parent_item_id) references item(id),
    constraint `fk_child_item_id_child_item_id_item` foreign key(child_item_id) references item(id),
    constraint `fk_measure_id_child_item_measure` foreign key(measure_id) references measure(id)
);

alter table item add column item_type_id int not null default 1 after image;
alter table item add constraint `fk_item_type_id_item_id_item_type` foreign key (item_type_id) references item_type(id) on update cascade;

insert into functionality values (null, '/item/save-measure/{id}', 'Relacionar unidades de medida ao item');
insert into functionality values (null, '/item/{itemId}/remove-measure/{measureId}', 'Remover relacionamento entre unidades de medida e item');

insert into functionality values (null, '/item/save-child-item/{id}', 'Relacionar itens ao item composto');
insert into functionality values (null, '/item/{itemId}/remove-child-item/{childItemId}', 'Remover relacionamento entre item e item composto');

insert into functionality values (null, '/item-types/all', 'Listar tipos de item');

insert into user_functionality (user_id, functionality_id)
	select 
		user.id,
        (select id from functionality where url = '/item/save-measure/{id}')
    from user
		inner join employee on user.employee_id = employee.id
	where department_id in (1,2,3);
    
insert into user_functionality (user_id, functionality_id)
	select 
		user.id,
        (select id from functionality where url = '/item/{itemId}/remove-measure/{measureId}')
    from user
		inner join employee on user.employee_id = employee.id
	where department_id in (1,2,3);
    
    insert into user_functionality (user_id, functionality_id)
	select 
		user.id,
        (select id from functionality where url = '/item/save-child-item/{id}')
    from user
		inner join employee on user.employee_id = employee.id
	where department_id in (1,2,3);
    
insert into user_functionality (user_id, functionality_id)
	select 
		user.id,
        (select id from functionality where url = '/item/{itemId}/remove-child-item/{childItemId}')
    from user
		inner join employee on user.employee_id = employee.id
	where department_id in (1,2,3);
    
insert into user_functionality (user_id, functionality_id)
	select 
		user.id,
        (select id from functionality where url = '/item-types/all')
    from user
		inner join employee on user.employee_id = employee.id
	where department_id in (1,2,3);

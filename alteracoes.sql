create table display (
	id int not null auto_increment primary key,
    url varchar(255) not null,
    description varchar(255) not null,
    unique(url)
);

insert into display (id, url, description) values (1,'/cost-categories','Listagem de categorias de custo');
insert into display (id, url, description) values (2,'/cost-categories/new','Cadastro de categorias de custo');
insert into display (id, url, description) values (3,'/cost-categories/edit/:id','Editar categorias de custo');
insert into display (id, url, description) values (4,'/cost-categories/show/:id','Visualizar todas as informações referentes a categoria de custo');
insert into display (id, url, description) values (5,'/cost-categories/list','Listagem de categorias de custo');
insert into display (id, url, description) values (6,'/item-categories','Listagem de categorias de item');
insert into display (id, url, description) values (7,'/item-categories/new','Cadastro de categorias de item');
insert into display (id, url, description) values (8,'/item-categories/edit/:id','Editar categorias de item');
insert into display (id, url, description) values (9,'/item-categories/show/:id','Visualizar todas as informações referentes a categoria de item');
insert into display (id, url, description) values (10,'/item-categories/list','Listagem de categorias de item');
insert into display (id, url, description) values (11,'/items','Listagem de itens');
insert into display (id, url, description) values (12,'/items/new','Cadastro de itens');
insert into display (id, url, description) values (13,'/items/edit/:id','Editar item');
insert into display (id, url, description) values (14,'/items/show/:id','Visualizar todas as informações referentes ao item');
insert into display (id, url, description) values (15,'/items/list','Listagem de itens');
insert into display (id, url, description) values (16,'/clients','Listagem de clientes');
insert into display (id, url, description) values (17,'/clients/new','Cadastro de clientes');
insert into display (id, url, description) values (18,'/clients/edit/:id','Editar cliente');
insert into display (id, url, description) values (19,'/clients/show/:id','Visualizar todas as informações referentes ao cliente');
insert into display (id, url, description) values (20,'/clients/list','Listagem de clientes');
insert into display (id, url, description) values (21,'/clients/import','Importação de clientes');
insert into display (id, url, description) values (22,'/providers','Listagem de fornecedores');
insert into display (id, url, description) values (23,'/providers/new','Cadastro de fornecedores');
insert into display (id, url, description) values (24,'/providers/edit/:id','Editar fornecedor');
insert into display (id, url, description) values (25,'/providers/show/:id','Visualizar todas as informações referentes ao fornecedor');
insert into display (id, url, description) values (26,'/providers/list','Listagem de fornecedores');


create table display_user (
	id int not null auto_increment primary key,
    user_id int not null,
    display_id int not null,
    unique(user_id, display_id),
    constraint `fk_user_id_display_user_id_user` foreign key(user_id) references user(id),
    constraint `fk_display_id_display_user_id_display` foreign key(display_id) references display(id) 
);

#1 a 5 - categoria de custo - produção & diretoria
insert into display_user (user_id, display_id) select u.id, 1 from user u left join employee e on e.id = u.employee_id where department_id in (1,2,3);
insert into display_user (user_id, display_id) select u.id, 2 from user u left join employee e on e.id = u.employee_id where department_id in (1,2,3);
insert into display_user (user_id, display_id) select u.id, 3 from user u left join employee e on e.id = u.employee_id where department_id in (1,2,3);
insert into display_user (user_id, display_id) select u.id, 4 from user u left join employee e on e.id = u.employee_id where department_id in (1,2,3);
insert into display_user (user_id, display_id) select u.id, 5 from user u left join employee e on e.id = u.employee_id where department_id in (1,2,3);
#6 a 10 - categoria de item - produção & diretoria
insert into display_user (user_id, display_id) select u.id, 6 from user u left join employee e on e.id = u.employee_id where department_id in (1,2,3);
insert into display_user (user_id, display_id) select u.id, 7 from user u left join employee e on e.id = u.employee_id where department_id in (1,2,3);
insert into display_user (user_id, display_id) select u.id, 8 from user u left join employee e on e.id = u.employee_id where department_id in (1,2,3);
insert into display_user (user_id, display_id) select u.id, 9 from user u left join employee e on e.id = u.employee_id where department_id in (1,2,3);
insert into display_user (user_id, display_id) select u.id, 10 from user u left join employee e on e.id = u.employee_id where department_id in (1,2,3);
#11 a 15 - itens - produção & diretoria
insert into display_user (user_id, display_id) select u.id, 11 from user u left join employee e on e.id = u.employee_id where department_id in (1,2,3);
insert into display_user (user_id, display_id) select u.id, 12 from user u left join employee e on e.id = u.employee_id where department_id in (1,2,3);
insert into display_user (user_id, display_id) select u.id, 13 from user u left join employee e on e.id = u.employee_id where department_id in (1,2,3);
insert into display_user (user_id, display_id) select u.id, 14 from user u left join employee e on e.id = u.employee_id where department_id in (1,2,3);
insert into display_user (user_id, display_id) select u.id, 15 from user u left join employee e on e.id = u.employee_id where department_id in (1,2,3);
#16 a 21 - clientes - atendimento & diretoria
insert into display_user (user_id, display_id) select u.id, 16 from user u left join employee e on e.id = u.employee_id where department_id in (1,2,4);
insert into display_user (user_id, display_id) select u.id, 17 from user u left join employee e on e.id = u.employee_id where department_id in (1,2,4);
insert into display_user (user_id, display_id) select u.id, 18 from user u left join employee e on e.id = u.employee_id where department_id in (1,2,4);
insert into display_user (user_id, display_id) select u.id, 19 from user u left join employee e on e.id = u.employee_id where department_id in (1,2,4);
insert into display_user (user_id, display_id) select u.id, 20 from user u left join employee e on e.id = u.employee_id where department_id in (1,2,4);
insert into display_user (user_id, display_id) select u.id, 21 from user u left join employee e on e.id = u.employee_id where department_id in (1,2,4);
#22 a 26 - fornecedores - produção & diretoria
insert into display_user (user_id, display_id) select u.id, 22 from user u left join employee e on e.id = u.employee_id where department_id in (1,2,3);
insert into display_user (user_id, display_id) select u.id, 23 from user u left join employee e on e.id = u.employee_id where department_id in (1,2,3);
insert into display_user (user_id, display_id) select u.id, 24 from user u left join employee e on e.id = u.employee_id where department_id in (1,2,3);
insert into display_user (user_id, display_id) select u.id, 25 from user u left join employee e on e.id = u.employee_id where department_id in (1,2,3);
insert into display_user (user_id, display_id) select u.id, 26 from user u left join employee e on e.id = u.employee_id where department_id in (1,2,3);

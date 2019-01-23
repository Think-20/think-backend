insert into functionality values (110, '/display/save', 'Cadastrar um acesso');
insert into functionality values (111, '/display/edit', 'Editar um acesso');
insert into functionality values (112, '/display/remove/{id}', 'Deletar um acesso');
insert into functionality values (113, '/displays/all', 'Listar todos os acesso');
insert into functionality values (114, '/displays/get/{id}', 'Visualizar informações de um acesso');
insert into functionality values (115, '/displays/filter', 'Filtrar um acesso');

alter table display add column created_at datetime default current_timestamp;
alter table display add column updated_at datetime default current_timestamp on update current_timestamp;

insert into functionality values (116, '/functionality/save', 'Cadastrar uma rota');
insert into functionality values (117, '/functionality/edit', 'Editar uma rota');
insert into functionality values (118, '/functionality/remove/{id}', 'Deletar uma rota');
insert into functionality values (119, '/functionalities/all', 'Listar todos as rotas');
insert into functionality values (120, '/functionalities/get/{id}', 'Visualizar informações de uma rota');
insert into functionality values (121, '/functionalities/filter', 'Filtrar uma rota');

alter table functionality add column created_at datetime default current_timestamp;
alter table functionality add column updated_at datetime default current_timestamp on update current_timestamp;
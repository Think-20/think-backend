insert into display values (39, '/employees/profile', 'Edição de perfil');
delete from display_user where display_id = 39;
delete from display where id = 39;

insert into functionality values (107, '/my-user/edit', 'Editar o próprio usuário');
insert into functionality values (108, '/my-employee/edit', 'Editar os próprios dados como funcionário');
insert into functionality values (109, '/my-employees/get/{id}', 'Visualizar os próprios dados como funcionário');
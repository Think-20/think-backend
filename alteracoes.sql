update functionality set url = '/employees/filter' where id = 32;
insert into functionality values (98, '/employees/get/{id}', 'Visualizar dados de um funcionário');

alter table employee add column created_at datetime default current_timestamp;
alter table employee add column updated_at datetime on update current_timestamp;
alter table employee add column updated_by int;
alter table employee add constraint `fk_updated_by_employee_id_employee` foreign key (updated_by) references employee (id);

update employee set updated_by = 2;
update employee set image = 'sem-foto.jpg' where id IN (34, 32);


update employee set name = 'Pamela' where id  = 11;
update employee set name = 'Fabio' where id  = 12;
update employee set name = 'Rubens' where id  = 13;

insert into functionality values (99, '/employee/save', 'Cadastrar um funcionário');
insert into functionality values (100, '/employee/edit', 'Editar um funcionário');
insert into functionality values (101, '/employee/remove/{id}', 'Deletar um funcionário');

alter table bank_account modify column account_number varchar(20) not null;
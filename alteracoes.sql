create table employee_office_hours (
	id int not null auto_increment primary key,
    entry datetime not null,
    `exit` datetime default null,
    employee_id int not null,
    
    constraint `fk_employee_id_employee_office_hours_id_employee` foreign key (employee_id) references employee (id) 
    on update cascade on delete no action
);

insert into functionality values (null, '/employees/office-hours/register/another', 'Registrar ponto de outro funcionário');
insert into functionality values (null, '/employees/office-hours/register/yourself', 'Registrar ponto somente do seu horário');
insert into functionality values (null, '/employees/office-hours/show/another/{employeeId}', 'Visualizar ponto de outro funcionário');
insert into functionality values (null, '/employees/office-hours/show/yourself', 'Visualizar ponto somente do seu horário');
insert into functionality values (null, '/employees/office-hours/get/{id}', 'Visualizar ponto específico');
insert into functionality values (null, '/employees/office-hours/edit', 'Editar ponto específico');
insert into functionality values (null, '/employees/office-hours/remove/{id}', 'Deletar ponto específico');





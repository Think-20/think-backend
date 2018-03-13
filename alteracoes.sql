truncate table employee_office_hours;

alter table employee_office_hours add column reason text(1000);
alter table employee_office_hours add column approved tinyint(1) default 0;

alter table provider modify column site varchar(100) default null;
alter table client modify column site varchar(100) default null;
alter table provider modify column number varchar(11) default null;
alter table client modify column number varchar(11) default null;


insert into functionality values(null, '/employees/office-hours/approvals-pending/show', 'Visualização de aprovações de horário pendente.');
insert into functionality values(null, '/employees/office-hours/approvals-pending/approve/{id}', 'Aprovação de horário de funcionário');

insert into functionality values (null, '/task/save', 'Cadastrar tarefas no cronograma');
insert into functionality values (null, '/tasks/filter', 'Filtrar tarefas no cronograma');
insert into functionality values (null, '/task/edit-available-date', 'Mover uma tarefa no cronograma');

insert into functionality values (null, '/my-task/save', 'Cadastrar tarefas no cronograma');
insert into functionality values (null, '/my-tasks/filter', 'Filtrar tarefas no cronograma');
insert into functionality values (null, '/my-task/edit-available-date', 'Mover somente tarefas próprias no cronograma');

alter table task modify column available_date date not null;
insert into job_activity values (null, 'Outsider');
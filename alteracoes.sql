alter table task add column created_at timestamp default current_timestamp;
alter table task add column updated_at timestamp default CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

update functionality set url = '/my-jobs/filter' where id = 57;
update employee set department_id = 6 where id = 21;

insert into functionality values (null, '/task/remove/{id}', 'Remover tarefas do cronograma');
insert into functionality values (null, '/my-task/remove/{id}', 'Remover tarefas do cronograma pertencentes ao atendimento');
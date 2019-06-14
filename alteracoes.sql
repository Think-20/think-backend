insert into notification_genre values (9, 'Atraso');
insert into notification_type values (18, 'Atraso de tarefa', 9, 1);
alter table agent add column deleted_at datetime default null;
INSERT INTO functionality values (141, '/my-tasks/get/{id}', 'Visualizar tarefa específica como responsável', now(), now());
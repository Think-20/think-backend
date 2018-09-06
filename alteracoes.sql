insert into functionality values (null, '/project-files/save-multiple', 'Upload de arquivos de projeto para entrega');
insert into functionality values (null, '/project-files/remove/{id}', 'Remoção de arquivos de projeto para entrega');
insert into functionality values (null, '/project-files/download/{id}', 'Download de arquivos de projeto para entrega');

alter table employee add column schedule_active tinyint(1) not null default 0;
update employee set schedule_active = 1;
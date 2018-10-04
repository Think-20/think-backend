create table schedule_block (
	id int not null auto_increment primary key,
    date date not null,
    
    unique(date)
);

insert into functionality values (91, '/schedule-block/save', 'Bloquear datas no cronograma');
insert into functionality values (92, '/schedule-block/remove/{id}', 'Desbloquear datas no cronograma');
insert into functionality values (93, '/schedule-blocks/all', 'Obter todas as datas bloqueadas no cronograma');
insert into functionality values (94, '/schedule-blocks/valid', 'Obter datas do mês bloqueadas no cronograma');
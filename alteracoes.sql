create table schedule_block_user (
	id int not null auto_increment primary key,
    schedule_id int not null, 
    user_id int not null,
    
    constraint `fk_schedule_id_schedule_block_user` foreign key(schedule_id) references schedule_block (id) on update cascade on delete no action,
    constraint `fk_user_id_schedule_block_user` foreign key(user_id) references user (id) on update cascade on delete no action
);

insert into schedule_block_user (schedule_id, user_id) select schedule_block.id, user.id from user inner join schedule_block order by user.id, schedule_block.date;

insert into functionality values (97, '/my-schedule-blocks/valid', 'Obter somente datas bloqueadas para o próprio usuário');
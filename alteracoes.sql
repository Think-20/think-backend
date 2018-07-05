alter table stand drop foreign key stand_ibfk_1;
alter table stand add constraint `stand_ibfk_1` foreign key(briefing_id) references briefing (id) on update cascade on delete no action;


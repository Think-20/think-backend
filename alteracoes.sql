alter table briefing add column briefing_id int default null;
alter table briefing add constraint `fk_briefing_id_briefing_id` foreign key (briefing_id) references briefing (id) on update cascade on delete no action;
alter table briefing add column responsible_id int default null;
alter table briefing add constraint `fk_responsible_id_employee_id` foreign key (responsible_id) references employee (id) on update cascade on delete no action;

alter table briefing modify column creation_id int default null;
alter table event change description name varchar(100) not null;
alter table event add column employee_id int not null after fin_hour;

truncate event;
truncate place;

alter table event add constraint `fk_employee_id_event_id_employee` foreign key(employee_id) 
references employee(id);

alter table event add column site varchar(100) not null after fin_hour;
alter table event add column phone BIGINT(13) not null after fin_hour;
alter table event add column email varchar(80) not null after fin_hour;
alter table event add column organizer varchar(100) not null after fin_hour;

alter table event add column plan varchar(40) not null after fin_hour;
alter table event add column manual varchar(40) not null after fin_hour;
alter table event add column regulation varchar(40) not null after fin_hour;
 
alter table event add column fin_hour_mounting time not null after fin_hour; 
alter table event add column ini_hour_mounting time not null after fin_hour; 
alter table event add column fin_date_mounting date not null after fin_hour; 
alter table event add column ini_date_mounting date not null after fin_hour;
 
alter table event add column fin_hour_unmounting time not null after fin_hour; 
alter table event add column ini_hour_unmounting time not null after fin_hour; 
alter table event add column fin_date_unmounting date not null after fin_hour; 
alter table event add column ini_date_unmounting date not null after fin_hour;

insert into functionality (id, url, description) values 
(135, '/event/download/{id}/{type}/{file}', 'Download de arquivos de evento');
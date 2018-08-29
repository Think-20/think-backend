drop table budget;

create table budget (
	id int not null auto_increment primary key,
    responsible_id int not null,
    gross_value decimal (8,2) not null,
    bv_value decimal (8,2) not null,
    equipments_value decimal (8,2) not null,
    logistics_value decimal (8,2) not null,
    sales_commission_value decimal (8,2) not null
);
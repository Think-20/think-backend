



#drop table budget;

create table budget (
	id int not null auto_increment primary key,
    responsible_id int not null,
    task_id int not null,
    gross_value decimal (8,2) not null,
    bv_value decimal (8,2) not null,
    equipments_value decimal (8,2) not null,
    logistics_value decimal (8,2) not null,
    sales_commission_value decimal (8,2) not null,
    tax_aliquot decimal (3,2) not null,
    others_value decimal (8,2) not null,
    discount_aliquot decimal (8,2),
    created_at datetime default current_timestamp,
    updated_at datetime on update current_timestamp,
    
    constraint `fk_responsible_id_budget_id_employee` foreign key(responsible_id) references employee(id),
    constraint `fk_task_id_budget_id_task` foreign key(task_id) references task(id)
);

#drop table project_file;

create table project_file (
	id int not null auto_increment primary key,
    responsible_id int not null,
    task_id int not null,
	name varchar(255) not null,
    original_name varchar(255) not null,
    type char(4) not null,
    created_at datetime default current_timestamp,
    updated_at datetime on update current_timestamp,
    
    constraint `fk_responsible_id_project_file_id_employee` foreign key(responsible_id) references employee(id),
    constraint `fk_task_id_project_file_id_task` foreign key(task_id) references task(id)
);

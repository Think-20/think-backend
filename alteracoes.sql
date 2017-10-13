create table measure (
	id int not null auto_increment primary key,
    description varchar(50),
    
    unique(description)
);

alter table item drop column price;
alter table item add column image varchar(255) after description;

create table pricing (
	id int not null auto_increment primary key,
    item_id int not null,
    provider_id int not null,
    measure_id int not null,
    price decimal(8,2),
    
    unique(item_id, provider_id, measure_id),
    
    constraint `fk_item_id_pricing_id_item` foreign key (`item_id`) references item (id),
    constraint `fk_provider_id_pricing_id_provider` foreign key (`provider_id`) references provider (id),
    constraint `fk_measure_id_pricing_id_measure` foreign key (`measure_id`) references measure (id)    
);
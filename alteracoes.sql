create table person_type (
	id int not null auto_increment primary key,
    description varchar(20) not null,
    
    unique(description)
);

insert into person_type values (null, 'Pessoa física'), (null, 'Pessoa jurídica');

create table bank_account_type (
	id int not null auto_increment primary key,
    description varchar(50) not null,
    
    unique(description)
);

insert into bank_account_type values (null, 'Conta corrente'), (null, 'Poupança'), (null, 'Investimentos');

create table bank (
	id int not null auto_increment primary key,
    name varchar(50) not null,
    
    unique(name)
);

insert into bank values (null, 'Itaú'), (null, 'Santander'), (null, 'Bradesco');

create table bank_account (
	id int not null auto_increment primary key,
    favored varchar(50) not null,
    agency int(6) not null,
    account_number bigint(15) not null,
	bank_account_type_id int not null,
    bank_id int not null,
    
    unique(agency, account_number),
    
    constraint `fk_bank_account_type_id_bank_account_id_bank_account_type` foreign key(bank_account_type_id) references bank_account_type(id),
    constraint `fk_bank_id_bank_account_id_bank` foreign key(bank_id) references bank_account_type(id)
);

create table client_contact (
	id int not null auto_increment primary key,
    client_id int not null,
    contact_id int not null,
    
    constraint `fk_client_id_client_contact_id_client` foreign key (client_id) references client (id),
    constraint `fk_contact_id_client_contact_id_contact` foreign key (contact_id) references contact (id)
);

insert into client_contact (client_id, contact_id) select clientId, id from contact;

alter table contact drop foreign key `fk_clientId_contact_id_client`;
alter table contact drop column clientId;

alter table city change column stateId state_id int not null; #OK
alter table client change column fantasyName fantasy_name varchar(30) not null; #OK
alter table client change column cityId city_id int not null; #OK
alter table client change column employeeId employee_id int not null; #OK
alter table client change column clientTypeId client_type_id int not null; #OK
alter table client change column clientStatusId client_status_id int not null; #OK

alter table employee change column positionId position_id int not null; #OK 
alter table employee change column departmentId department_id int not null; #OK

alter table item change column itemCategoryId item_category_id int not null; #OK
alter table item change column costCategoryId cost_category_id int not null; #OK

alter table item_category change column itemCategoryId item_category_id int; #OK

alter table user change column employeeId employee_id int not null; #OK

alter table user_functionality change column userId user_id int not null; #OK
alter table user_functionality change column functionalityId functionality_id int not null; #OK

create table provider (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `fantasy_name` varchar(30) NOT NULL,
  `cnpj` bigint(14) NOT NULL,
  `ie` mediumint(12),
  `cpf` int(11) NOT NULL,
  `mainphone` bigint(13) NOT NULL,
  `secundaryphone` bigint(13) DEFAULT NULL,
  `site` varchar(30) DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `rate` int(3) DEFAULT NULL,
  `street` varchar(50) NOT NULL,
  `number` int(11) NOT NULL,
  `neighborhood` varchar(30) NOT NULL,
  `complement` varchar(255) DEFAULT NULL,
  `city_id` int(11) NOT NULL,
  `cep` int(11) NOT NULL,
  `person_type_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cnpj_UNIQUE_cpf` (`cnpj`, `cpf`),
  KEY `fk_city_id_client_id_city` (`city_id`),
  KEY `fk_employee_id_client_id_employee` (`employee_id`),
  CONSTRAINT `fk_city_id_client_id_city` FOREIGN KEY (`city_id`) REFERENCES `city` (`id`),
  CONSTRAINT `fk_employee_id_client_id_employee` FOREIGN KEY (`employee_id`) REFERENCES `employee` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
  );
  
  
create table provider_contact (
	id int not null auto_increment primary key,
    provider_id int not null,
    contact_id int not null,
    
    constraint `fk_provider_id_provider_contact_id_provider` foreign key (provider_id) references provider (id),
    constraint `fk_contact_id_provider_contact_id_contact` foreign key (contact_id) references contact (id)
);  
  
create table provider_account (
	id int not null auto_increment primary key,
    provider_id int not null,
    account_id int not null,
    
    constraint `fk_provider_id_provider_account_id_provider` foreign key (provider_id) references provider (id),
    constraint `fk_account_id_provider_account_id_bank_account` foreign key (account_id) references bank_account (id)
);

insert into functionality values (null, '/provider/save', 'Cadastrar um fornecedor');
insert into functionality values (null, '/provider/edit', 'Editar um fornecedor');
insert into functionality values (null, '/provider/remove/{id}', 'Deletar um fornecedor');
insert into functionality values (null, '/providers/all', 'Listar todos os fornecedores');
insert into functionality values (null, '/providers/get/{id}', 'Visualizar informações de um fornecedor');
insert into functionality values (null, '/providers/filter/{query}', 'Filtrar um fornecedor por nome');

INSERT INTO user_functionality (user_id, functionality_id) SELECT user.id, 33 FROM user left join employee on user.employee_id = employee.id where employee.department_id IN (1,2,3);
INSERT INTO user_functionality (user_id, functionality_id) SELECT user.id, 34 FROM user left join employee on user.employee_id = employee.id where employee.department_id IN (1,2,3);
INSERT INTO user_functionality (user_id, functionality_id) SELECT user.id, 35 FROM user left join employee on user.employee_id = employee.id where employee.department_id IN (1,2,3);
INSERT INTO user_functionality (user_id, functionality_id) SELECT user.id, 36 FROM user left join employee on user.employee_id = employee.id where employee.department_id IN (1,2,3);
INSERT INTO user_functionality (user_id, functionality_id) SELECT user.id, 37 FROM user left join employee on user.employee_id = employee.id where employee.department_id IN (1,2,3);
INSERT INTO user_functionality (user_id, functionality_id) SELECT user.id, 38 FROM user left join employee on user.employee_id = employee.id where employee.department_id IN (1,2,3);

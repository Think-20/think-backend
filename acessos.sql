TRUNCATE TABLE display_user;
TRUNCATE TABLE user_functionality;

#Telas e permissões para diretoria e administradores
insert into display_user (display_id, user_id) 
select d.id, u.id from display d 
inner join employee e on e.department_id IN (1,2)
left join user u on u.employee_id = e.id;

insert into user_functionality (functionality_id, user_id) 
select f.id, u.id from functionality f 
inner join employee e on e.department_id IN (1,2)
left join user u on u.employee_id = e.id
where (f.id >= 1 AND f.id <= 18) OR (f.id >= 25 AND f.id <= 51) 
OR (f.id >= 63 AND f.id <= 67) OR (f.id >= 71 AND f.id <= 74) 
OR (f.id >= 85 AND f.id <= 96) OR (f.id IN(59,61,69,77,78,79,83))
OR (f.id >= 98 AND f.id <= 106) OR (f.id >= 110 AND f.id <= 140) OR (f.id = 142);

#Telas e permissões para produção
insert into display_user (display_id, user_id) 
select d.id, u.id from display d 
inner join employee e on e.department_id IN (3)
left join user u on u.employee_id = e.id
where (d.id >= 22 AND d.id <= 26) OR (d.id >= 1 AND d.id <= 15);

insert into user_functionality (functionality_id, user_id) 
select f.id, u.id from functionality f 
inner join employee e on e.department_id IN (3)
left join user u on u.employee_id = e.id
where (f.id >= 7 AND f.id <= 18) OR (f.id >= 25 AND f.id <= 38)
OR (f.id >= 40 AND f.id <= 44) OR (f.id IN(60,62,68,70,81,89,90))
OR (f.id IN (93,95,96,97)) OR (f.id >= 107 AND f.id <= 109) OR (f.id = 143);

#Telas e permissões para atendimento
insert into display_user (display_id, user_id) 
select d.id, u.id from display d 
inner join employee e on e.department_id IN (4)
left join user u on u.employee_id = e.id
where (d.id >= 16 AND d.id <= 20) OR (d.id >= 27 AND d.id <= 31) OR (d.id = 33)
OR (d.id >= 39 AND d.id <= 44);

insert into user_functionality (functionality_id, user_id) 
select f.id, u.id from functionality f 
inner join employee e on e.department_id IN (4)
left join user u on u.employee_id = e.id
where f.id = 4 OR f.id = 6 OR (f.id >= 19 AND f.id <= 20)
OR (f.id >= 23 AND f.id <= 23) OR (f.id >= 31 AND f.id <= 32) 
OR f.id = 39 OR (f.id IN (52,53,55,56,57,58)) OR (f.id IN(60,62,68,70,71,72,80,78,82,87,89,90,141))
OR (f.id IN (93,95,96,97)) OR (f.id >= 107 AND f.id <= 109) OR (f.id >= 123 AND f.id <= 135)
OR (f.id >= 137 AND f.id <= 140) OR (f.id = 142);

#Telas e permissões para criação
insert into display_user (display_id, user_id) 
select d.id, u.id from display d 
left join employee e on e.department_id IN (5)
left join user u on u.employee_id = e.id
where d.id IN (27,30,31,33);

insert into user_functionality (functionality_id, user_id) 
select f.id, u.id from functionality f 
inner join employee e on e.department_id IN (5)
left join user u on u.employee_id = e.id
where (f.id IN (31,55,56,57,49,51)) OR (f.id IN(60,62,68,81,87,88,89,90,141))
OR (f.id IN (93,95,96,97)) OR (f.id >= 107 AND f.id <= 109) OR (f.id = 143);

#Telas e permissões para planejamento
insert into display_user (display_id, user_id) 
select d.id, u.id from display d 
inner join employee e on e.department_id IN (6)
left join user u on u.employee_id = e.id
where d.id IN (27,30,31,33);

insert into user_functionality (functionality_id, user_id) 
select f.id, u.id from functionality f 
inner join employee e on e.department_id IN (6)
left join user u on u.employee_id = e.id
where (f.id IN (31,55,56,57,49,51)) OR (f.id IN(60,62,68,81,89,90))
OR (f.id IN (93,95,96,97)) OR (f.id >= 107 AND f.id <= 109) OR (f.id = 143)
OR (f.id >= 139 AND f.id <= 140);

delete from user_functionality where user_id = 27;
delete from display_user where user_id = 27;

insert into user_functionality (user_id, functionality_id) select 27, id from functionality where id IN (78,77,46);
insert into display_user (user_id, display_id) values (27, 33);

/* ------------------ Notificações --------------------- */
truncate table notification_rule;
insert into notification_rule select null, n.id, u.id from notification_type n inner join user u;


/* ------------------ Acessos orçamento para Pamela e Rafaela --------------------- */
insert into user_functionality (user_id, functionality_id) values (14, 73), (14, 74);
insert into user_functionality (user_id, functionality_id) values (4, 73), (4, 74);



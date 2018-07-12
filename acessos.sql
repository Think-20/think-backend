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
OR (f.id IN(59,61,63,64,65,66,67,69,71,72,73,74,75));

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
OR (f.id >= 40 AND f.id <= 44) OR (f.id IN(60,62,68,70,76));

#Telas e permissões para atendimento
insert into display_user (display_id, user_id) 
select d.id, u.id from display d 
inner join employee e on e.department_id IN (4)
left join user u on u.employee_id = e.id
where (d.id >= 16 AND d.id <= 21) OR (d.id >= 27 AND d.id <= 31) OR (d.id = 33);

insert into user_functionality (functionality_id, user_id) 
select f.id, u.id from functionality f 
inner join employee e on e.department_id IN (4)
left join user u on u.employee_id = e.id
where f.id = 4 OR f.id = 6 OR (f.id >= 19 AND f.id <= 21)
OR (f.id >= 23 AND f.id <= 23) OR (f.id >= 31 AND f.id <= 32) 
OR f.id = 39 OR (f.id >= 52 AND f.id <= 58) OR (f.id IN(60,62,68,70,71,72,73,74));

#Telas e permissões para criação
insert into display_user (display_id, user_id) 
select d.id, u.id from display d 
left join employee e on e.department_id IN (5)
left join user u on u.employee_id = e.id
where d.id IN (27,30,31);

insert into user_functionality (functionality_id, user_id) 
select f.id, u.id from functionality f 
inner join employee e on e.department_id IN (5)
left join user u on u.employee_id = e.id
where (f.id >= 48 AND f.id <= 51) OR (f.id IN(60,62,68));

#Telas e permissões para planejamento
insert into display_user (display_id, user_id) 
select d.id, u.id from display d 
inner join employee e on e.department_id IN (6)
left join user u on u.employee_id = e.id
where d.id IN (27,30,31);

insert into user_functionality (functionality_id, user_id) 
select f.id, u.id from functionality f 
inner join employee e on e.department_id IN (6)
left join user u on u.employee_id = e.id
where (f.id >= 48 AND f.id <= 51) OR (f.id IN(60,62,68));



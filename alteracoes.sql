update companybook.notification n
left join user u on u.id = n.notifier_id
left join employee e on e.id = u.employee_id
set n.notifier_id = e.id, notifier_type = 'App\\Employee'
where notifier_type = 'App\\User';

alter table agent add column image varchar(255);
alter table employee add column image varchar(255);

update agent set image = 'logo/think.png';
update employee e
left join user u on u.employee_id = e.id 
set image = concat('users/', u.id, '.jpg');

update agent set name = "Think Ideias", description = "Think Ideias";
update notification set notifier_id = 1, notifier_type = 'App\\Agent' where message like "%memorial%";
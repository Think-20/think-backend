alter table employee add column deleted_at datetime default null;
update functionality set url = '/employee/toggle-deleted/{id}' where id = 101;
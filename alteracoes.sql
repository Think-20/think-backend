alter table job add column status_updated_at datetime default current_timestamp;
update job j set j.status_updated_at = j.updated_at, j.updated_at = j.updated_at where j.id > 0;

alter table job add column area DECIMAL(8,2);
alter table job add column moments INT(2);
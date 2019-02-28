alter table job add column status_updated_at datetime default current_timestamp;
update job j set j.status_updated_at = j.updated_at, j.updated_at = j.updated_at where j.id > 0;
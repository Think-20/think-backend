alter table job drop column reopened;
alter table task add column reopened tinyint(2) default 0;
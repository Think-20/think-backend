alter table client add column external tinyint(1) not null default 0;
alter table client modify column cnpj bigint(14) null;

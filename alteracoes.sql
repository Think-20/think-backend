alter table budget modify column tax_aliquot decimal (5,2) not null;
alter table budget add column optional_value decimal (8,2) not null;
RENAME TABLE  `employee_office_hours` TO  `timecard`;

insert into functionality values(null, '/employees/office-hours/status/yourself', 'Status do horário de funcionário');

TRUNCATE TABLE `timecard`;
ALTER TABLE `timecard` ADD COLUMN `approved_by` INT;
ALTER TABLE `timecard` ADD CONSTRAINT `fk_approved_by_timecard_id_user` FOREIGN KEY(`approved_by`) REFERENCES `user`(`id`) ON UPDATE CASCADE ON DELETE NO ACTION; 
ALTER TABLE `timecard` ADD COLUMN `entryPlace` VARCHAR(50) NOT NULL;
ALTER TABLE `timecard` ADD COLUMN `exitPlace`  VARCHAR(50);
ALTER TABLE `timecard` ADD COLUMN `autoEntryPlaceCoordinates` VARCHAR(50) NOT NULL;
ALTER TABLE `timecard` ADD COLUMN `autoEntryPlace` VARCHAR(50) NOT NULL;
ALTER TABLE `timecard` ADD COLUMN `autoExitPlaceCoordinates` VARCHAR(50);
ALTER TABLE `timecard` ADD COLUMN `autoExitPlace` VARCHAR(50);

ALTER TABLE `client` MODIFY COLUMN `name` VARCHAR(100) NOT NULL;
ALTER TABLE `client` MODIFY COLUMN `fantasy_name` VARCHAR(50) NOT NULL;
ALTER TABLE `provider` MODIFY COLUMN `name` VARCHAR(100) NOT NULL;
ALTER TABLE `provider` MODIFY COLUMN `fantasy_name` VARCHAR(50) NOT NULL;
ALTER TABLE `contact` MODIFY COLUMN `email` VARCHAR(80) NOT NULL;

UPDATE functionality SET url = '/clients/filter' WHERE id = 6;
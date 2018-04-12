TRUNCATE TABLE timecard;

CREATE TABLE timecard_place (
	id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    description VARCHAR(50),
    
    unique(description)
);

INSERT INTO timecard_place VALUES (null, 'Think'), (null,'Home Office'), (null,'Externo');

ALTER TABLE `timecard` MODIFY COLUMN `entryPlace` VARCHAR(50);
ALTER TABLE `timecard` MODIFY COLUMN `exitPlace`  VARCHAR(50);

ALTER TABLE `timecard` ADD COLUMN `entry_place_id` INT NOT NULL;
ALTER TABLE `timecard` ADD COLUMN `exit_place_id`  INT;

ALTER TABLE `timecard` ADD CONSTRAINT `entry_place_id_timecard_id_timecard_place` FOREIGN KEY(`entry_place_id`)
REFERENCES timecard_place (`id`) ON UPDATE CASCADE ON DELETE NO ACTION;
ALTER TABLE `timecard` ADD CONSTRAINT `exit_place_id_timecard_id_timecard_place` FOREIGN KEY(`exit_place_id`)
REFERENCES timecard_place (`id`) ON UPDATE CASCADE ON DELETE NO ACTION;
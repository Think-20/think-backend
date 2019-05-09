CREATE TABLE `specification_file` (
	`id` int(11) NOT NULL AUTO_INCREMENT,
	`responsible_id` int(11) NOT NULL,
	`task_id` int(11) NOT NULL,
	`name` varchar(255) NOT NULL,
	`original_name` varchar(255) NOT NULL,
	`type` char(4) NOT NULL,
	`created_at` datetime DEFAULT CURRENT_TIMESTAMP,
	`updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	KEY `fk_responsible_id_specification_file_id_employee` (`responsible_id`),
	KEY `fk_task_id_specification_file_id_task` (`task_id`),
	CONSTRAINT `fk_responsible_id_specification_file_id_employee` FOREIGN KEY (`responsible_id`) REFERENCES `employee` (`id`),
	CONSTRAINT `fk_task_id_specification_file_id_task` FOREIGN KEY (`task_id`) REFERENCES `task` (`id`)
);

insert into functionality values (137, '/specification-files/save-multiple', 'Upload de arquivos de memorial para entrega', now(), now());
insert into functionality values (138, '/specification-files/remove/{id}', 'Remoção de arquivos de memorial para entrega', now(), now());
insert into functionality values (139, '/specification-files/download/{id}', 'Download de arquivos de memorial para entrega', now(), now());
insert into functionality values (140, '/specification-files/download-all/{taskId}', 'Download de todos os arquivos do memorial', now(), now());
insert into notification_type values (17, 'Entrega de memorial', 6, 1);
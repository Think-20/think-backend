SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE job;
INSERT INTO job VALUES (null, 'Projeto'), (null, 'Orçamento'), (null, 'Modificação'), (null, 'Opção');

SET FOREIGN_KEY_CHECKS = 1;

ALTER TABLE briefing DROP FOREIGN KEY briefing_ibfk_2;
ALTER TABLE briefing DROP COLUMN exhibitor_id;
UPDATE functionality SET url = '/clients/filter' WHERE id = 6;

ALTER TABLE briefing ADD COLUMN client_id INT NOT NULL;
ALTER TABLE briefing ADD CONSTRAINT briefing_ibfk_2 FOREIGN KEY(client_id) REFERENCES client (id) ON UPDATE CASCADE ON DELETE NO ACTION;

CREATE TABLE briefing_main_expectation (
	id int not null auto_increment primary key,
    description varchar(50) not null,
    unique(description)
);

INSERT INTO briefing_main_expectation VALUES (null, 'Encantar'), (null, 'Budget'), (null, 'Prazo'), (null, 'Custo x Benefício');

ALTER TABLE briefing ADD COLUMN main_expectation_id INT NOT NULL;
ALTER TABLE briefing ADD CONSTRAINT briefing_ibfk_11 FOREIGN KEY(main_expectation_id) REFERENCES briefing_main_expectation (id) ON UPDATE CASCADE ON DELETE NO ACTION;

ALTER TABLE briefing ADD COLUMN available_date DATE;

ALTER TABLE briefing DROP COLUMN area;
ALTER TABLE briefing DROP COLUMN budget;

ALTER TABLE briefing DROP COLUMN colors_file;
ALTER TABLE briefing DROP COLUMN budget;

ALTER TABLE stand ADD COLUMN area DECIMAL(7,2) NOT NULL;
ALTER TABLE stand ADD COLUMN budget DECIMAL(10,2) NOT NULL;

ALTER TABLE briefing ADD COLUMN last_provider VARCHAR(100);

CREATE TABLE briefing_level (
	id int not null auto_increment primary key,
    description varchar(50) not null,
    unique(description)
);

INSERT INTO briefing_level VALUES (null, 'Material predominante'), (null, 'Acabamento'), (null, 'Mobiliário'), (null, 'Displays personalizados'),
(null, 'Comunicação'), (null, 'Atendimento/Produtor dedicado'), (null, 'Dificuldade técnica'), (null, 'Expectativa');

ALTER TABLE briefing ADD COLUMN level_id INT NOT NULL;
ALTER TABLE briefing ADD CONSTRAINT briefing_ibfk_12 FOREIGN KEY(level_id) REFERENCES briefing_level (id) ON UPDATE CASCADE ON DELETE NO ACTION;

ALTER TABLE briefing DROP COLUMN guide_file;

CREATE TABLE briefing_how_come (
	id int not null auto_increment primary key,
    description varchar(50) not null,
    unique(description)
);

INSERT INTO briefing_how_come VALUES (null, 'Já trabalha conosco'), (null, 'Indicação'), (null, 'Conheceu na internet'), (null, 'Nos viu em evento'),
(null, 'Prospecção');

ALTER TABLE briefing ADD COLUMN how_come_id INT NOT NULL;
ALTER TABLE briefing ADD CONSTRAINT briefing_ibfk_13 FOREIGN KEY(how_come_id) REFERENCES briefing_how_come (id) ON UPDATE CASCADE ON DELETE NO ACTION;

SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE briefing_presentation;
INSERT INTO briefing_presentation VALUES (null, 'Envio por e-mail', ''), (null, 'Modelagem de produtos', ''), (null, 'Imagem de detalhes', ''), 
(null, 'Alta resolução', ''), (null, 'Impressão', ''), (null, 'Vídeo/multimídia', ''), (null, 'Cotas e medidas', ''), (null, 'Preço decupado', '');

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE briefing_presentation_briefing (
	id int not null auto_increment primary key,
    presentation_id int not null,
    briefing_id int not null,
    
    foreign key(presentation_id) references briefing_presentation (id),
    foreign key(briefing_id) references briefing (id)
);

ALTER TABLE briefing DROP FOREIGN KEY briefing_ibfk_9;
ALTER TABLE briefing DROP COLUMN special_presentation_id;

ALTER TABLE briefing DROP FOREIGN KEY briefing_ibfk_8;
ALTER TABLE briefing DROP COLUMN presentation_id;




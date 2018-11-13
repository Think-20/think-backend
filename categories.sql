DROP TABLE IF EXISTS `item`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `item` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `description` varchar(255) NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `item_type_id` int(11) NOT NULL DEFAULT '1',
  `item_category_id` int(11) NOT NULL,
  `cost_category_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `fk_itemCategoryId_item_id_item_category` (`item_category_id`),
  KEY `fk_costCategoryId_item_id_cost_category` (`cost_category_id`),
  KEY `fk_item_type_id_item_id_item_type` (`item_type_id`),
  CONSTRAINT `fk_costCategoryId_item_id_cost_category` FOREIGN KEY (`cost_category_id`) REFERENCES `cost_category` (`id`),
  CONSTRAINT `fk_itemCategoryId_item_id_item_category` FOREIGN KEY (`item_category_id`) REFERENCES `item_category` (`id`),
  CONSTRAINT `fk_item_type_id_item_id_item_type` FOREIGN KEY (`item_type_id`) REFERENCES `item_type` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `item`
--

LOCK TABLES `item` WRITE;
/*!40000 ALTER TABLE `item` DISABLE KEYS */;
INSERT INTO `item` VALUES (5,'Tablado elevado á 10cm nivelado e chapeado.','Teste','sem-foto.jpg',0,15,13),(6,'Tablado elevado á 30cm nivelado e chapeado.','Teste','sem-foto.jpg',0,15,13),(7,'Praticável com elevação personalizada em Xcm','Nivelado e chapeado.','sem-foto.jpg',0,16,13),(9,'Carpete Forração (Tipos)','Teste','sem-foto.jpg',0,17,13),(10,'Grama Sintética (Tipos)','Teste','sem-foto.jpg',0,17,13),(11,'Piso Vinílico (Tipos)','Teste','sem-foto.jpg',0,17,13),(12,'Vidro Retroiluminado (Tipos)','Teste','sem-foto.jpg',0,17,13),(13,'Deck de Madeira (Tipos)','Teste','sem-foto.jpg',0,17,13),(14,'MDF Laminado Tipo 1 Cor B3 Atributo X Especial','Teste','sem-foto.jpg',0,18,13);
/*!40000 ALTER TABLE `item` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `item_category`
--

DROP TABLE IF EXISTS `item_category`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `item_category` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `description` varchar(50) NOT NULL,
  `item_category_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `description` (`description`),
  KEY `fk_itemCategoryId_item_category_id_item_category` (`item_category_id`),
  CONSTRAINT `fk_itemCategoryId_item_category_id_item_category` FOREIGN KEY (`item_category_id`) REFERENCES `item_category` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `item_category`
--

LOCK TABLES `item_category` WRITE;
/*!40000 ALTER TABLE `item_category` DISABLE KEYS */;
INSERT INTO `item_category` VALUES (7,'Piso',NULL),(15,'Estrutura',7),(16,'Elevação adicional',7),(17,'Revestimentos',7),(18,'MDF Laminado',17);
/*!40000 ALTER TABLE `item_category` ENABLE KEYS */;
UNLOCK TABLES;
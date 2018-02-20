ALTER TABLE `companybook`.`stand_item` 
CHANGE COLUMN `description` `description` TEXT(5000) NULL DEFAULT NULL ;

DROP FUNCTION IF EXISTS UC_FIRST;
CREATE FUNCTION UC_FIRST(oldWord VARCHAR(255)) RETURNS VARCHAR(255)
  RETURN CONCAT(UCASE(SUBSTRING(oldWord, 1, 1)),SUBSTRING(oldWord, 2));

DROP FUNCTION IF EXISTS UC_DELIMETER;
DELIMITER //
CREATE FUNCTION UC_DELIMETER(oldName VARCHAR(255), delim VARCHAR(1), trimSpaces BOOL) RETURNS VARCHAR(255)
BEGIN
  SET @oldString := oldName;
  SET @newString := "";
 
  tokenLoop: LOOP
    IF trimSpaces THEN SET @oldString := TRIM(BOTH " " FROM @oldString); END IF;
 
    SET @splitPoint := LOCATE(delim, @oldString);
 
    IF @splitPoint = 0 THEN
      SET @newString := CONCAT(@newString, UC_FIRST(@oldString));
      LEAVE tokenLoop;
    END IF;
 
    SET @newString := CONCAT(@newString, UC_FIRST(SUBSTRING(@oldString, 1, @splitPoint)));
    SET @oldString := SUBSTRING(@oldString, @splitPoint+1);
  END LOOP tokenLoop;
 
  RETURN @newString;
END//
DELIMITER ;

UPDATE client SET fantasy_name = UC_DELIMETER(LOWER(fantasy_name), ' ', FALSE);
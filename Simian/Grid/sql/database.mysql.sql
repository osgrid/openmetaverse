CREATE  TABLE IF NOT EXISTS `AssetData` (
  `ID` CHAR(36) NOT NULL ,
  `Data` MEDIUMBLOB NOT NULL ,
  `ContentType` VARCHAR(50) NOT NULL DEFAULT 'application/octet-stream' ,
  `CreatorID` CHAR(36) NOT NULL ,
  `SHA256` CHAR(64) NOT NULL ,
  `CreationDate` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ,
  `LastAccessed` TIMESTAMP NULL ,
  `Public` TINYINT(1) NOT NULL DEFAULT TRUE ,
  `Temporary` TINYINT(1) NOT NULL DEFAULT FALSE ,
  PRIMARY KEY (`ID`) ,
  INDEX `last_accessed` (`LastAccessed` ASC) ,
  INDEX `temporary` (`Temporary` ASC) ,
  INDEX `sha256` (`SHA256` ASC) )
ENGINE = InnoDB;

CREATE  TABLE IF NOT EXISTS `Capabilities` (
  `ID` CHAR(36) NOT NULL ,
  `OwnerID` CHAR(36) NOT NULL ,
  `Resource` VARCHAR(255) NOT NULL ,
  `ExpirationDate` TIMESTAMP NOT NULL ,
  PRIMARY KEY (`ID`) ,
  UNIQUE INDEX `user_resource` (`OwnerID` ASC, `Resource` ASC) ,
  INDEX `user_id` (`OwnerID` ASC) ,
  INDEX `expiration_date` (`ExpirationDate` ASC) )
ENGINE = InnoDB;

CREATE  TABLE IF NOT EXISTS `Users` (
  `ID` CHAR(36) NOT NULL ,
  `Name` VARCHAR(60) NOT NULL ,
  `Email` VARCHAR(60) NOT NULL ,
  `AccessLevel` TINYINT UNSIGNED NOT NULL DEFAULT 0 ,
  PRIMARY KEY (`ID`) ,
  UNIQUE INDEX `name` (`Name` ASC) ,
  UNIQUE INDEX `email` (`Email` ASC) )
ENGINE = InnoDB;

CREATE  TABLE IF NOT EXISTS `Identities` (
  `Identifier` VARCHAR(255) NOT NULL ,
  `Type` VARCHAR(50) NOT NULL ,
  `Credential` VARCHAR(255) NULL ,
  `UserID` CHAR(36) NOT NULL ,
  `Enabled` TINYINT(1) NOT NULL DEFAULT TRUE ,
  PRIMARY KEY (`Identifier`, `Type`) ,
  INDEX `fk_id` (`UserID` ASC) ,
  CONSTRAINT `fk_id`
    FOREIGN KEY (`UserID` )
    REFERENCES `Users` (`ID` )
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB;

CREATE  TABLE IF NOT EXISTS `Inventory` (
  `ID` CHAR(36) NOT NULL ,
  `Name` VARCHAR(255) NOT NULL ,
  `ParentID` CHAR(36) NULL ,
  `OwnerID` CHAR(36) NOT NULL ,
  `CreatorID` CHAR(36) NOT NULL ,
  `AssetID` CHAR(36) NULL ,
  `Description` VARCHAR(255) NULL ,
  `ContentType` VARCHAR(50) NULL ,
  `Version` INT UNSIGNED NOT NULL DEFAULT 1 ,
  `CreationDate` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ,
  `Type` ENUM('Folder','Item') NOT NULL ,
  `LeftNode` INT NOT NULL ,
  `RightNode` INT NOT NULL ,
  `ExtraData` TEXT NULL ,
  PRIMARY KEY (`ID`) ,
  INDEX `owner` (`OwnerID` ASC) ,
  INDEX `asset` (`AssetID` ASC) ,
  INDEX `fk_parent` (`ParentID` ASC) ,
  CONSTRAINT `fk_parent`
    FOREIGN KEY (`ParentID` )
    REFERENCES `Inventory` (`ID` )
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB;

CREATE  TABLE IF NOT EXISTS `Scenes` (
  `ID` CHAR(36) NOT NULL ,
  `Name` VARCHAR(50) NOT NULL ,
  `MinX` DOUBLE NOT NULL ,
  `MinY` DOUBLE NOT NULL ,
  `MinZ` DOUBLE NOT NULL ,
  `MaxX` DOUBLE NOT NULL ,
  `MaxY` DOUBLE NOT NULL ,
  `MaxZ` DOUBLE NOT NULL ,
  `Address` VARCHAR(255) NOT NULL ,
  `Enabled` TINYINT(1) NOT NULL ,
  `XYPlane` POLYGON NOT NULL ,
  `ExtraData` TEXT NULL ,
  PRIMARY KEY (`ID`) ,
  UNIQUE INDEX `name` (`Name` ASC) ,
  SPATIAL INDEX `xyplane` (`XYPlane` ASC) )
ENGINE = MyISAM;

CREATE  TABLE IF NOT EXISTS `Sessions` (
  `SessionID` CHAR(36) NOT NULL ,
  `UserID` CHAR(36) NOT NULL ,
  `SecureSessionID` CHAR(36) NOT NULL ,
  `SceneID` CHAR(36) NOT NULL ,
  `ScenePosition` VARCHAR(45) NULL ,
  `SceneLookAt` VARCHAR(45) NULL ,
  `ExtraData` TEXT NULL ,
  PRIMARY KEY (`SessionID`) ,
  UNIQUE INDEX `fk_user` (`UserID` ASC) ,
  CONSTRAINT `fk_user`
    FOREIGN KEY (`UserID` )
    REFERENCES `Users` (`ID` )
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB;

CREATE  TABLE IF NOT EXISTS `UserData` (
  `ID` CHAR(36) NOT NULL ,
  `Key` VARCHAR(50) NOT NULL ,
  `Value` MEDIUMTEXT NOT NULL ,
  PRIMARY KEY (`ID`, `Key`) ,
  INDEX `id` (`ID` ASC) ,
  CONSTRAINT `id`
    FOREIGN KEY (`ID` )
    REFERENCES `Users` (`ID` )
    ON DELETE CASCADE
    ON UPDATE CASCADE)
ENGINE = InnoDB;

CREATE  TABLE IF NOT EXISTS `Generic` (
  `OwnerID` CHAR(36) NOT NULL ,
  `Type` VARCHAR(45) NOT NULL ,
  `Key` VARCHAR(45) NOT NULL ,
  `Value` MEDIUMTEXT NULL ,
  PRIMARY KEY (`OwnerID`, `Type`, `Key`) )
ENGINE = InnoDB;

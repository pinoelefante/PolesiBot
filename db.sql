-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Versione server:              10.1.34-MariaDB - mariadb.org binary distribution
-- S.O. server:                  Win32
-- HeidiSQL Versione:            9.5.0.5196
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;

-- Dump della struttura di tabella football.my_match
CREATE TABLE IF NOT EXISTS `my_match` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `chatId` bigint(20) NOT NULL DEFAULT '0',
  `field` varchar(32) DEFAULT NULL,
  `creation` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `external` bit(1) NOT NULL DEFAULT b'0',
  `startBy` bigint(20) NOT NULL DEFAULT '0',
  `endBy` bigint(20) NOT NULL DEFAULT '0',
  `players` tinyint(3) unsigned NOT NULL DEFAULT '10',
  `status` tinyint(3) NOT NULL DEFAULT '1',
  `startDay` date DEFAULT NULL,
  `startHour` time DEFAULT NULL,
  `quota` float NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=latin1;

-- L’esportazione dei dati non era selezionata.
-- Dump della struttura di tabella football.my_match_player
CREATE TABLE IF NOT EXISTS `my_match_player` (
  `userId` bigint(20) NOT NULL,
  `matchId` int(11) unsigned NOT NULL,
  `nickname` varchar(32) DEFAULT NULL,
  `firstname` varchar(24) DEFAULT NULL,
  `lastname` varchar(24) DEFAULT NULL,
  `insertBy` bigint(20) NOT NULL,
  `insertTime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`userId`,`matchId`),
  KEY `FK_match_player_match` (`matchId`),
  CONSTRAINT `FK_match_player_match` FOREIGN KEY (`matchId`) REFERENCES `my_match` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- L’esportazione dei dati non era selezionata.
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IF(@OLD_FOREIGN_KEY_CHECKS IS NULL, 1, @OLD_FOREIGN_KEY_CHECKS) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;

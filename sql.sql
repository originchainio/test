/*
Navicat MySQL Data Transfer

Source Server         : localhost
Source Server Version : 50717
Source Host           : localhost:3306
Source Database       : my_coin

Target Server Type    : MYSQL
Target Server Version : 50717
File Encoding         : 65001

Date: 2019-01-13 13:47:06
*/

SET FOREIGN_KEY_CHECKS=0;

-- ----------------------------
-- Table structure for accounts
-- ----------------------------
DROP TABLE IF EXISTS `accounts`;
CREATE TABLE `accounts` (
  `id` varbinary(128) NOT NULL,
  `public_key` varbinary(1024) DEFAULT NULL,
  `block` varbinary(128) DEFAULT NULL,
  `balance` decimal(20,8) NOT NULL,
  `alias` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `alias` (`alias`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=COMPACT;

-- ----------------------------
-- Table structure for blocks
-- ----------------------------
DROP TABLE IF EXISTS `blocks`;
CREATE TABLE `blocks` (
  `id` varbinary(128) NOT NULL,
  `generator` varbinary(128) NOT NULL,
  `height` int(11) NOT NULL,
  `date` int(11) NOT NULL,
  `nonce` varbinary(128) NOT NULL,
  `signature` varbinary(256) NOT NULL,
  `difficulty` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `argon` varbinary(128) NOT NULL,
  `transactions` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `height` (`height`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Table structure for masternode
-- ----------------------------
DROP TABLE IF EXISTS `masternode`;
CREATE TABLE `masternode` (
  `public_key` varchar(128) COLLATE utf8mb4_bin NOT NULL,
  `height` int(11) NOT NULL,
  `ip` varchar(128) COLLATE utf8mb4_bin NOT NULL,
  `last_won` int(11) NOT NULL DEFAULT '0',
  `blacklist` int(11) NOT NULL DEFAULT '0',
  `fails` int(11) NOT NULL DEFAULT '0',
  `status` tinyint(4) NOT NULL DEFAULT '1',
  PRIMARY KEY (`public_key`),
  KEY `last_won` (`last_won`),
  KEY `status` (`status`),
  KEY `blacklist` (`blacklist`),
  KEY `height` (`height`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- ----------------------------
-- Table structure for mempool
-- ----------------------------
DROP TABLE IF EXISTS `mempool`;
CREATE TABLE `mempool` (
  `id` varbinary(128) NOT NULL,
  `height` int(11) NOT NULL,
  `dst` varbinary(128) NOT NULL,
  `val` decimal(20,8) NOT NULL,
  `fee` decimal(20,8) NOT NULL,
  `signature` varbinary(256) NOT NULL,
  `version` tinyint(4) NOT NULL,
  `message` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT '',
  `date` int(11) NOT NULL,
  `public_key` varbinary(1024) NOT NULL,
  `peer` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `height` (`height`),
  KEY `peer` (`peer`),
  KEY `val` (`val`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Table structure for peers
-- ----------------------------
DROP TABLE IF EXISTS `peers`;
CREATE TABLE `peers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hostname` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `blacklisted` int(11) NOT NULL DEFAULT '0',
  `ping` int(11) NOT NULL,
  `reserve` tinyint(1) NOT NULL DEFAULT '1',
  `ip` varchar(45) NOT NULL,
  `fails` tinyint(4) NOT NULL DEFAULT '0',
  `stuckfail` tinyint(4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `hostname` (`hostname`),
  UNIQUE KEY `ip` (`ip`),
  KEY `blacklisted` (`blacklisted`),
  KEY `ping` (`ping`),
  KEY `reserve` (`reserve`),
  KEY `stuckfail` (`stuckfail`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Table structure for transactions
-- ----------------------------
DROP TABLE IF EXISTS `transactions`;
CREATE TABLE `transactions` (
  `id` varbinary(128) NOT NULL,
  `height` int(11) NOT NULL,
  `dst` varbinary(128) NOT NULL,
  `val` decimal(20,8) NOT NULL,
  `fee` decimal(20,8) NOT NULL,
  `signature` varbinary(256) NOT NULL,
  `version` tinyint(4) NOT NULL,
  `message` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT '',
  `date` int(11) unsigned NOT NULL,
  `public_key` varbinary(1024) NOT NULL,
  `block` varbinary(128) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `dst` (`dst`),
  KEY `height` (`height`),
  KEY `public_key` (`public_key`),
  KEY `block` (`block`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

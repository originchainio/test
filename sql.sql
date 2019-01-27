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
  `id` varbinary(128) NOT NULL COMMENT 'public三列9次得到的address 长度为>=70和<=128',
  `public_key` varbinary(1024) DEFAULT NULL,
  `block` varbinary(128) DEFAULT NULL COMMENT 'block hash',
  `balance` decimal(20,8) NOT NULL,
  `alias` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '别名  4-25 长度',
  PRIMARY KEY (`id`),
  KEY `alias` (`alias`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=COMPACT;

-- ----------------------------
-- Table structure for blocks
-- ----------------------------
DROP TABLE IF EXISTS `blocks`;
CREATE TABLE `blocks` (
  `id` varbinary(128) NOT NULL,
  `generator` varbinary(128) NOT NULL COMMENT 'address',
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
-- Table structure for config
-- ----------------------------
DROP TABLE IF EXISTS `config`;
CREATE TABLE `config` (
  `cfg` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `val` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  PRIMARY KEY (`cfg`),
  KEY `cfg` (`cfg`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Table structure for masternode
-- ----------------------------
DROP TABLE IF EXISTS `masternode`;
CREATE TABLE `masternode` (
  `public_key` varchar(128) COLLATE utf8mb4_bin NOT NULL,
  `height` int(11) NOT NULL,
  `ip` varchar(16) COLLATE utf8mb4_bin NOT NULL COMMENT 'host',
  `last_won` int(11) NOT NULL DEFAULT '0',
  `blacklist` int(11) NOT NULL DEFAULT '0' COMMENT '好像是记录上一个赢得的block的height 或者是mn的最新块height',
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
  `dst` varbinary(128) NOT NULL COMMENT '接收方 的address地址或者 别名',
  `val` decimal(20,8) NOT NULL,
  `fee` decimal(20,8) NOT NULL,
  `signature` varbinary(256) NOT NULL,
  `version` tinyint(4) NOT NULL COMMENT '0->矿工奖励事务 不参与签名 用公钥和签名发送给peer\r\n1->寄给address的付款\r\n2->寄给别名的付款\r\n3->增加alias\r\n4->masternode奖励事务 不参与签名 用公钥和签名发送给peer\r\n5->fee燃烧事务\r\n100->增加节点 \r\n101->暂停节点 \r\n102->开启节点 \r\n103->删除节点 \r\n111->升级节点状态 message=》$check_public_key.'',''.$fails\r\n\r\n',
  `message` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT '' COMMENT '增加别名时的别名 或  增加mn时候是mn的ip',
  `date` int(11) NOT NULL,
  `public_key` varbinary(1024) NOT NULL COMMENT '发送方公钥',
  `peer` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'md5(hostname),可选值:local',
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
  `blacklisted` int(11) NOT NULL DEFAULT '0' COMMENT '下一次检测和得到 get peer的 time',
  `ping` int(11) NOT NULL,
  `reserve` tinyint(1) NOT NULL DEFAULT '1',
  `ip` varchar(45) NOT NULL,
  `fails` tinyint(4) NOT NULL DEFAULT '0' COMMENT '连接失败次数',
  `stuckfail` tinyint(4) NOT NULL DEFAULT '0' COMMENT '高度不在最高的检测失败次数',
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
  `dst` varbinary(128) NOT NULL COMMENT '接收方 的address地址或者 别名',
  `val` decimal(20,8) NOT NULL,
  `fee` decimal(20,8) NOT NULL,
  `signature` varbinary(256) NOT NULL,
  `version` tinyint(4) NOT NULL COMMENT '0->矿工奖励事务 不参与签名 用公钥和签名发送给peer\r\n1->寄给address的付款\r\n2->寄给别名的付款\r\n3->增加alias\r\n4->masternode奖励事务 不参与签名 用公钥和签名发送给peer\r\n5->fee燃烧事务\r\n100->增加节点 \r\n101->暂停节点 \r\n102->开启节点 \r\n103->删除节点 \r\n111->升级节点状态 message=》$check_public_key.'',''.$fails\r\n\r\n',
  `message` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT '' COMMENT '有可能是masternode的ip',
  `date` int(11) unsigned NOT NULL,
  `public_key` varbinary(1024) NOT NULL COMMENT '发送方公钥',
  `block` varbinary(128) NOT NULL COMMENT 'block  hash   block id',
  PRIMARY KEY (`id`),
  KEY `dst` (`dst`),
  KEY `height` (`height`),
  KEY `public_key` (`public_key`),
  KEY `block` (`block`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

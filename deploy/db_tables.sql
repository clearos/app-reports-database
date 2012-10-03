DROP TABLE IF EXISTS `device`;
CREATE TABLE `device` (
  `id` bigint(20) unsigned NOT NULL auto_increment,
  `ip` varbinary(16) NOT NULL,
  `mac` bigint unsigned default NULL,
  `description` varchar(127) default NULL,
  `username` varchar(64) default NULL,
  `hostname` varchar(255) default NULL,
  `created` timestamp NOT NULL default CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=innodb DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;

DROP TABLE IF EXISTS `hostname`;
CREATE TABLE `hostname` (
  `id` bigint(20) unsigned NOT NULL auto_increment,
  `ip` varbinary(16) NOT NULL,
  `resolved` tinyint(3) unsigned NOT NULL default '0',
  `hostname` varchar(255) default NULL,
  `country_code` char(3) default NULL,
  `country_name` varchar(20) default NULL,
  `city` varchar(20) default NULL,
  `region` varchar(20) default NULL,
  `latitude` float(10,7) default '0.0000000',
  `longitude` float(10,7) default '0.0000000',
  `created` timestamp NOT NULL default CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ip` (`ip`)
) ENGINE=innodb DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;

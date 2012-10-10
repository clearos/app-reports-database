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

DROP TABLE IF EXISTS `calendar`;
CREATE TABLE calendar (
    tdate date NOT NULL PRIMARY KEY,
    year smallint NULL,
    quarter tinyint NULL,
    month tinyint NULL,
    day tinyint NULL,
    day_of_week tinyint NULL,
    week tinyint NULL,
    is_weekday BINARY(1) NULL
) ENGINE=innodb DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;

DROP TABLE IF EXISTS `ints`;
CREATE TABLE ints ( i tinyint );
INSERT INTO ints VALUES (0),(1),(2),(3),(4),(5),(6),(7),(8),(9);
INSERT INTO calendar (tdate)
SELECT date('2012-01-01') + interval a.i*10000 + b.i*1000 + c.i*100 + d.i*10 + e.i day
FROM ints a JOIN ints b JOIN ints c JOIN ints d JOIN ints e
WHERE (a.i*10000 + b.i*1000 + c.i*100 + d.i*10 + e.i) <= 11322
ORDER BY 1;
DROP TABLE IF EXISTS `ints`;

UPDATE calendar
SET is_weekday = case when dayofweek(tdate) IN (1,7) then 0 else 1 end,
    year = year(tdate),
    quarter = quarter(tdate),
    month = month(tdate),
    day = dayofmonth(tdate),
    day_of_week = dayofweek(tdate),
    week = week(tdate);

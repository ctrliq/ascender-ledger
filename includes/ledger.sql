SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

CREATE TABLE IF NOT EXISTS `changes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `server` varchar(128) NOT NULL DEFAULT '',
  `host` int(11) NOT NULL,
  `time` int(11) NOT NULL,
  `job` int(11) NOT NULL,
  `playbook` varchar(256) NOT NULL,
  `play` varchar(256) NOT NULL,
  `role` varchar(64) NOT NULL,
  `task` varchar(256) NOT NULL,
  `task_action` varchar(64) NOT NULL,
  `res` text NOT NULL,
  PRIMARY KEY (`id`),
  KEY `time` (`time`),
  KEY `task_action` (`task_action`),
  KEY `playbook` (`playbook`),
  KEY `job` (`job`),
  KEY `host` (`host`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `facts` (
  `host` int(11) NOT NULL,
  `fact` varchar(128) NOT NULL,
  `type` varchar(32) NOT NULL,
  `data` text DEFAULT NULL,
  `time` int(11) DEFAULT NULL,
  PRIMARY KEY (`host`,`fact`),
  KEY `host` (`host`),
  KEY `fact` (`fact`),
  KEY `type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `jobs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `job` int(11) NOT NULL,
  `job_template_id` int(11) NOT NULL,
  `timestamp` varchar(32) DEFAULT '',
  `host` varchar(128) DEFAULT '',
  `name` varchar(512) DEFAULT '',
  `job_type` varchar(32) DEFAULT '',
  `inventory` varchar(128) DEFAULT '',
  `project` varchar(128) DEFAULT '',
  `scm_branch` varchar(128) DEFAULT '',
  `execution_environment` varchar(128) DEFAULT '',
  `actor` varchar(64) DEFAULT '',
  `limit` text DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `job` (`job`),
  KEY `job_template_id` (`job_template_id`),
  KEY `actor` (`actor`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `hosts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hostname` varchar(128) NOT NULL,
  `time` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `hostname` (`hostname`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `log_emails` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `date` int(11) NOT NULL,
  `to` tinytext NOT NULL,
  `subject` varchar(64) NOT NULL,
  `status` tinytext NOT NULL,
  PRIMARY KEY (`id`),
  KEY `date` (`date`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `mail_queue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `status` int(11) NOT NULL DEFAULT 0,
  `created` int(11) NOT NULL DEFAULT 0,
  `last_attempt` int(11) NOT NULL DEFAULT 0,
  `attempts` int(11) NOT NULL DEFAULT 0,
  `to` varchar(512) NOT NULL,
  `from` varchar(64) NOT NULL,
  `fromname` varchar(64) NOT NULL,
  `subject` varchar(512) NOT NULL,
  `message` text NOT NULL,
  `error` text NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `created` (`created`),
  KEY `last_attempt` (`last_attempt`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `owner` int(11) NOT NULL,
  `name` varchar(256) NOT NULL,
  `created` int(11) NOT NULL,
  `filters` text NOT NULL,
  `columns` text NOT NULL,
  `sortc` int(11) NOT NULL,
  `sortd` varchar(4) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user` (`owner`),
  KEY `created` (`created`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `reports_perms` (
  `report` int(11) NOT NULL,
  `user` int(11) NOT NULL,
  `role` varchar(16) NOT NULL DEFAULT 'view',
  PRIMARY KEY (`report`,`user`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

INSERT INTO `reports_perms` (`report`, `user`, `role`) VALUES
(1,	1,	'owner'),
(2,	1,	'owner');

INSERT INTO `reports` (`id`, `owner`, `name`, `created`, `filters`, `columns`, `sortc`, `sortd`) VALUES
(1,	1,	'Linux Servers',	1663558186,	'YToxOntpOjA7YTozOntzOjQ6ImZhY3QiO3M6MTQ6ImFuc2libGVfc3lzdGVtIjtzOjc6ImNvbXBhcmUiO3M6MjoiZXEiO3M6NToidmFsdWUiO3M6NToiTGludXgiO319',	'YTo3OntzOjg6Ikhvc3RuYW1lIjtzOjE2OiJhbnNpYmxlX2hvc3RuYW1lIjtzOjEwOiJJUCBBZGRyZXNzIjtzOjI4OiJhbnNpYmxlX2RlZmF1bHRfaXB2NC5hZGRyZXNzIjtzOjY6IkRpc3RybyI7czoyMDoiYW5zaWJsZV9kaXN0cmlidXRpb24iO3M6MTQ6IkRpc3RybyBWZXJzaW9uIjtzOjI4OiJhbnNpYmxlX2Rpc3RyaWJ1dGlvbl92ZXJzaW9uIjtzOjE0OiJQeXRob24gVmVyc2lvbiI7czoyMjoiYW5zaWJsZV9weXRob25fdmVyc2lvbiI7czo0OiJDUFVzIjtzOjIzOiJhbnNpYmxlX3Byb2Nlc3Nvcl92Y3B1cyI7czo2OiJNZW1vcnkiO3M6MTk6ImFuc2libGVfbWVtdG90YWxfbWIiO30=',	0,	'asc'),
(2,	1,	'Windows Servers',	1664418280,	'YToxOntpOjA7YTozOntzOjQ6ImZhY3QiO3M6MTc6ImFuc2libGVfb3NfZmFtaWx5IjtzOjc6ImNvbXBhcmUiO3M6MjoiZXEiO3M6NToidmFsdWUiO3M6NzoiV2luZG93cyI7fX0=',	'YTo2OntzOjg6Ikhvc3RuYW1lIjtzOjE2OiJhbnNpYmxlX2hvc3RuYW1lIjtzOjEwOiJJUCBBZGRyZXNzIjtzOjIyOiJhbnNpYmxlX2lwX2FkZHJlc3Nlcy4wIjtzOjEwOiJPUyBWZXJzaW9uIjtzOjIwOiJhbnNpYmxlX2Rpc3RyaWJ1dGlvbiI7czoxMDoiUG93ZXJzaGVsbCI7czoyNjoiYW5zaWJsZV9wb3dlcnNoZWxsX3ZlcnNpb24iO3M6NDoiQ1BVcyI7czoyMzoiYW5zaWJsZV9wcm9jZXNzb3JfdmNwdXMiO3M6NjoiTWVtb3J5IjtzOjE5OiJhbnNpYmxlX21lbXRvdGFsX21iIjt9',	0,	'asc');

CREATE TABLE IF NOT EXISTS `reports_schedules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `report` int(11) NOT NULL,
  `owner` int(11) NOT NULL,
  `start` int(11) NOT NULL,
  `repeat` int(11) NOT NULL,
  `next` int(11) NOT NULL,
  `process` int(11) NOT NULL DEFAULT 0,
  `emails` tinytext NOT NULL,
  `subject` varchar(256) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `report` (`report`),
  KEY `owner` (`owner`),
  KEY `next` (`next`),
  KEY `process` (`process`),
  KEY `enabled` (`enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `servers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(128) NOT NULL,
  `ip` varchar(15) NOT NULL,
  `trusted` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ip` (`ip`),
  KEY `trusted` (`trusted`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `sessions` (
  `id` varchar(32) NOT NULL,
  `access` int(10) unsigned DEFAULT NULL,
  `user` int(11) DEFAULT 0,
  `data` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `settings` (
  `setting` varchar(64) NOT NULL,
  `value` text NOT NULL,
  PRIMARY KEY (`setting`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(64) NOT NULL,
  `username` varchar(64) NOT NULL,
  `email` varchar(64) NOT NULL DEFAULT '',
  `password` varchar(128) NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `registered` tinyint(1) NOT NULL DEFAULT 0,
  `view_changes` tinyint(1) NOT NULL DEFAULT 1,
  `view_facts` tinyint(1) NOT NULL DEFAULT 1,
  `code` varchar(256) NOT NULL DEFAULT '',
  `super` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `enabled` (`enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;



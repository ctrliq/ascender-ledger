<?php

$new_db_version = 10;

$db_version = read_setting('db_version', 1);

if ($new_db_version != $db_version) {
	try {
		switch ($db_version) {
			case 2:
				if (!db_column_exists('users', 'super')) {
					db_execute("ALTER TABLE `users` ADD `super` tinyint(1) NOT NULL DEFAULT '0';");
				}
			case 3:
				if (!db_table_exists('servers')) {
					db_execute("CREATE TABLE `servers` (
						  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
						  `name` varchar(128) NOT NULL,
						  `ip` varchar(15) NOT NULL,
						  `trusted` int NOT NULL DEFAULT '0')");
					db_execute("ALTER TABLE `servers`
								ADD UNIQUE `ip` (`ip`),
								ADD INDEX `trusted` (`trusted`)");
				}
			case 4:
				if (!db_column_exists('changes', 'server')) {
					db_execute("ALTER TABLE `changes` ADD `server` varchar(128) NOT NULL DEFAULT '' AFTER `id`");
				}
			case 5:
				if (!db_column_exists('servers', 'url')) {
					db_execute("ALTER TABLE `servers` ADD `url` varchar(128) NOT NULL DEFAULT '' AFTER `ip`");
				}
			case 6:
				db_execute("DELETE FROM `sessions`");
			case 7:
				if (!db_column_exists('users', 'view_changes')) {
					db_execute("ALTER TABLE `users` ADD `view_changes` tinyint(1) NOT NULL DEFAULT '1' AFTER `super`");
				}
				if (!db_column_exists('users', 'view_facts')) {
					db_execute("ALTER TABLE `users` ADD `view_facts` tinyint(1) NOT NULL DEFAULT '1' AFTER `super`");
				}
			case 8:
				if (!read_setting('allowed_modules')) {
					db_execute("INSERT INTO `settings` (`setting`, `value`) VALUES ('allowed_modules', 'gather_facts')");
				}
			case 9:
				if (!db_table_exists('packages')) {
					db_execute("CREATE TABLE IF NOT EXISTS `packages` (
								`host` int NOT NULL,
								`name` varchar(128) NOT NULL,
								`version` varchar(32) NOT NULL,
								`release` varchar(32) NOT NULL,
								`epoch` varchar(32) NOT NULL,
								`arch` varchar(32) NOT NULL,
								`source` varchar(32) NOT NULL,
								`check` tinyint(1) NOT NULL DEFAULT '0')");
					db_execute("ALTER TABLE `packages`
								ADD PRIMARY KEY `host_name` (`host`, `name`),
								ADD INDEX `host` (`host`),
								ADD INDEX `name` (`name`),
								ADD INDEX `arch` (`arch`),
								ADD INDEX `check` (`check`);");
				}
				if (!db_table_exists('services')) {
					db_execute("CREATE TABLE IF NOT EXISTS `services` (
								`host` int NOT NULL,
								`name` varchar(128) NOT NULL,
								`state` varchar(32) NOT NULL,
								`status` varchar(32) NOT NULL,
								`source` varchar(32) NOT NULL);");
					db_execute("ALTER TABLE `services`
								ADD PRIMARY KEY `host_name` (`host`, `name`),
								ADD INDEX `host` (`host`),
								ADD INDEX `name` (`name`),
								ADD INDEX `state` (`state`),
								ADD INDEX `status` (`status`),
								ADD INDEX `source` (`source`);");
				}
		}
	} catch (Exception $e) {

	}
	db_execute_prepare("UPDATE `settings` SET `value` = ? WHERE `setting` = 'db_version'", array($new_db_version));
}

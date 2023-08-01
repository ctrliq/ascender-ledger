<?php

$new_db_version = 5;

$db_version = read_setting('db_version', 1);

if ($new_db_version != $db_version) {
	try {
		switch ($db_version) {
			case 2:
				db_execute("ALTER TABLE `users` ADD `super` tinyint(1) NOT NULL DEFAULT '0';");
			case 3:
				db_execute("CREATE TABLE `servers` (
					  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
					  `name` varchar(128) NOT NULL,
					  `ip` varchar(15) NOT NULL,
					  `trusted` int NOT NULL DEFAULT '0')");
				db_execute("ALTER TABLE `servers`
							ADD UNIQUE `ip` (`ip`),
							ADD INDEX `trusted` (`trusted`)");
			case 4:
				db_execute("ALTER TABLE `changes` ADD `server` varchar(128) NOT NULL DEFAULT '' AFTER `id`");

		}
	} catch (Exception $e) {

	}
	db_execute_prepare("UPDATE `settings` SET `value` = ? WHERE `setting` = 'db_version'", array($new_db_version));
}

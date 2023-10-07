<?php

$new_db_version = 9;

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
			case 5:
				db_execute("ALTER TABLE `servers` ADD `url` varchar(128) NOT NULL DEFAULT '' AFTER `ip`");
			case 6:
				db_execute("DELETE FROM `sessions`");
			case 7:
				db_execute("ALTER TABLE `users` ADD `view_changes` tinyint(1) NOT NULL DEFAULT '1' AFTER `super`");
				db_execute("ALTER TABLE `users` ADD `view_facts` tinyint(1) NOT NULL DEFAULT '1' AFTER `super`");
			case 8:
				db_execute_prepare("INSERT INTO `settings` (`setting`, `value`) VALUES (?, ?)", array('allowed_modules', 'gather_facts'));

		}
	} catch (Exception $e) {

	}
	db_execute_prepare("UPDATE `settings` SET `value` = ? WHERE `setting` = 'db_version'", array($new_db_version));
}

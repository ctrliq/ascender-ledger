<?php

$new_db_version = 19;

$db_version = read_setting('db_version', 1);

/*
	The first requests after an upgrade are usually a burst of parser posts, all of which would
	otherwise run the same ALTERs at once.  Only the one holding the lock upgrades.  The others
	wait briefly for it, and if it is still altering a large table they are turned away (see the
	end of this file) rather than holding every PHP worker until it is done.
*/
$db_upgrade_locked = false;
if ($new_db_version != $db_version) {
	$db_upgrade_locked = (bool) db_fetch_cell("SELECT GET_LOCK('ledger_db_upgrade', 10) AS `l`", 'l');
	$db_version = db_fetch_cell("SELECT `value` FROM `settings` WHERE `setting` = 'db_version'", 'value');
	$settings_cache = array();
}

if ($new_db_version != $db_version && $db_upgrade_locked && intval($db_version) < 18) {
	try {
		switch ($db_version) {
			case 0:
			case 1:
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
								ADD PRIMARY KEY (`host`,`name`,`version`,`release`,`epoch`,`arch`),
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
			case 10:
				db_execute("ALTER TABLE `packages` DROP PRIMARY KEY, ADD PRIMARY KEY (`host`,`name`,`version`,`release`,`epoch`,`arch`);");
			case 11:
			case 12:
			case 13:
			case 14:
			case 15:
			case 16:
				db_execute("ALTER TABLE `users` CHANGE `password` `password` varchar(256) NOT NULL AFTER `email`;");
			case 17:
				if (!db_column_exists('reports', 'type')) {
					db_execute("ALTER TABLE `reports` ADD `type` varchar(16) NOT NULL DEFAULT 'facts' AFTER `name`");
				}
		}
	} catch (Exception $e) {
		error_log('Ledger: database upgrade from version ' . $db_version . ' failed: ' . $e->getMessage());
	}
	$db_version = 18;
	db_execute_prepare("UPDATE `settings` SET `value` = ? WHERE `setting` = 'db_version'", array($db_version));
}

/*
	From version 19 on, a version is only recorded once its step has succeeded, so a step that
	fails part way is tried again rather than left half done.  Each step must be safe to rerun.
*/
if ($new_db_version != $db_version && $db_upgrade_locked && intval($db_version) == 18) {
	try {
		db_upgrade_19();
		db_execute_prepare("UPDATE `settings` SET `value` = ? WHERE `setting` = 'db_version'", array(19));
		$db_version = 19;
	} catch (Exception $e) {
		error_log('Ledger: database upgrade to version 19 failed, and will be retried: ' . $e->getMessage());
	}
}

if ($db_upgrade_locked) {
	db_fetch_cell("SELECT RELEASE_LOCK('ledger_db_upgrade') AS `l`", 'l');
}

/*
	Ledger's code expects the current schema (the parser relies on the unique host name, for
	one), so nothing runs against an older one: not while another request is still upgrading,
	and not after an upgrade step failed.  The request is answered with 503, which Ascender's
	log shipping retries, so no data is lost; the upgrade is tried again on a later request.
*/
if ($new_db_version != $db_version) {
	$upgrading = !$db_upgrade_locked;
	if (php_sapi_name() == 'cli') {
		echo ($upgrading ? "The database is being upgraded by another process; try again shortly.\n" : "The database upgrade failed; see the error log.\n");
		exit(1);
	}
	http_response_code(503);
	header('Retry-After: 60');
	header('Content-Type: text/plain');
	print ($upgrading ? 'Ledger is upgrading its database.  Try again in a minute.' : 'Ledger could not upgrade its database.  The administrator can find the reason in the PHP error log.');
	exit;
}

/*
	Version 19
	- Servers are identified by the install UUID Ascender sends with every record, and are
	  authorized by token instead of by a trusted IP address, so `trusted` goes and `uuid` and
	  `lastseen` arrive.  An IP is only remembered for display, and can be IPv6.
	- Host names become unique, so the parser can insert a new host atomically instead of
	  racing itself into duplicates when several events for a new host arrive at once.
	- mail_queue.message grows to MEDIUMTEXT, since a scheduled report is easily over 64KB.
	- A cache of the distinct fact names, which the fact pickers search instead of the facts
	  table itself.
*/
function db_upgrade_19() {
	if (!db_column_exists('servers', 'uuid')) {
		db_execute("ALTER TABLE `servers` ADD `uuid` varchar(64) NULL DEFAULT NULL AFTER `name`, ADD UNIQUE `uuid` (`uuid`)");
	}
	if (!db_column_exists('users', 'code_expires')) {
		db_execute("ALTER TABLE `users` ADD `code_expires` int NOT NULL DEFAULT '0' AFTER `code`");
	}
	if (!db_column_exists('servers', 'lastseen')) {
		db_execute("ALTER TABLE `servers` ADD `lastseen` int NOT NULL DEFAULT '0'");
	}
	if (!db_column_exists('servers', 'auth_ok')) {
		db_execute("ALTER TABLE `servers` ADD `auth_ok` tinyint(1) NOT NULL DEFAULT '0'");
	}
	if (db_index_exists('servers', 'ip')) {
		db_execute("ALTER TABLE `servers` DROP INDEX `ip`");
	}
	db_execute("ALTER TABLE `servers` MODIFY `ip` varchar(45) NOT NULL DEFAULT '', ADD INDEX `ip` (`ip`)");
	if (db_column_exists('servers', 'trusted')) {
		if (db_index_exists('servers', 'trusted')) {
			db_execute("ALTER TABLE `servers` DROP INDEX `trusted`");
		}
		db_execute("ALTER TABLE `servers` DROP `trusted`");
	}

	if (trim((string) read_setting('auth_token', '')) == '') {
		write_setting('auth_token', generate_auth_token());
	}
	/*
		Mail used to skip certificate checks.  Check them from now on, except where mail is
		already set up, which keeps working as it did until an administrator turns checks on.
	*/
	if (read_setting('smtp_verify_cert') === false) {
		write_setting('smtp_verify_cert', ((string) read_setting('smtp_server', '') != '' ? '0' : '1'));
	}

	// Off by default, so an upgraded Ledger keeps receiving data until Ascender has the token
	read_setting('require_auth_token', '0');

	// A scheduled report's email is easily over the 64KB of a TEXT column
	db_execute("ALTER TABLE `mail_queue` MODIFY `message` mediumtext NOT NULL");

	// A schedule's recipients were limited to 255 characters, a handful of addresses
	db_execute("ALTER TABLE `reports_schedules` MODIFY `emails` text NOT NULL");
	// When a schedule was claimed by a cron run, so the claim of a run that died can be taken over
	if (!db_column_exists('reports_schedules', 'claimed')) {
		db_execute("ALTER TABLE `reports_schedules` ADD `claimed` int NOT NULL DEFAULT '0' AFTER `process`");
	}

	// Job numbers are only unique per Ascender server, so jobs record which one they came from
	if (!db_column_exists('jobs', 'server')) {
		db_execute("ALTER TABLE `jobs` ADD `server` varchar(128) NOT NULL DEFAULT '' AFTER `job`, ADD INDEX `job_server` (`job`, `server`)");
	}
	/*
		Jobs recorded before this have no server.  Where every change made by a job came from
		one server, that was the job's server.  A number used by more than one server is left
		without one (see jobs_join_on() in misc.php).
	*/
	db_execute("UPDATE `jobs` AS `j`
		JOIN (SELECT `job`, MIN(`server`) AS `server` FROM `changes` GROUP BY `job` HAVING COUNT(DISTINCT `server`) = 1) AS `c` ON `c`.`job` = `j`.`job`
		SET `j`.`server` = `c`.`server`
		WHERE `j`.`server` = ''");

	if (!db_index_exists('changes', 'role')) {
		db_execute("ALTER TABLE `changes` ADD INDEX `role` (`role`)");
	}

	db_execute("CREATE TABLE IF NOT EXISTS `fact_names` (
		`fact` varchar(128) NOT NULL,
		PRIMARY KEY (`fact`)
		) ENGINE=InnoDB DEFAULT CHARSET=latin1");

	if (!db_index_exists('hosts', 'hostname', true)) {
		/*
			Fold any duplicate hosts into the most recently seen row with that name first.  Its
			packages and services are taken as the current ones where both rows have the same
			entry, a fact is taken from whichever row gathered it last, and the kept row's time
			stops the merged host from being pruned as stale.
		*/
		$dups = db_fetch_assocs("SELECT `hostname` FROM `hosts` GROUP BY `hostname` HAVING COUNT(*) > 1");
		foreach ($dups as $d) {
			$rows = db_fetch_assocs_prepare('SELECT `id` FROM `hosts` WHERE `hostname` = ? ORDER BY `time` DESC, `id` ASC', array($d['hostname']));
			$keep = $rows[0]['id'];
			foreach (array_slice($rows, 1) as $o) {
				// Facts carry their own time, so a fact the other row gathered more recently wins
				db_execute_prepare('UPDATE `facts` AS `k` JOIN `facts` AS `o` ON `o`.`fact` = `k`.`fact` AND `o`.`host` = ?
					SET `k`.`data` = `o`.`data`, `k`.`type` = `o`.`type`, `k`.`time` = `o`.`time`
					WHERE `k`.`host` = ? AND `o`.`time` > IFNULL(`k`.`time`, 0)', array($o['id'], $keep));
				foreach (array('facts', 'packages', 'services') as $t) {
					db_execute_prepare("UPDATE IGNORE `$t` SET `host` = ? WHERE `host` = ?", array($keep, $o['id']));
					db_execute_prepare("DELETE FROM `$t` WHERE `host` = ?", array($o['id']));
				}
				db_execute_prepare('UPDATE `changes` SET `host` = ? WHERE `host` = ?', array($keep, $o['id']));
				db_execute_prepare('DELETE FROM `hosts` WHERE `id` = ?', array($o['id']));
			}
		}
		if (db_index_exists('hosts', 'hostname')) {
			db_execute("ALTER TABLE `hosts` DROP INDEX `hostname`");
		}
		db_execute("ALTER TABLE `hosts` ADD UNIQUE `hostname` (`hostname`)");
	}
}

<?php

if (php_sapi_name() != 'cli') {
	exit;
}

chdir(dirname($_SERVER['argv'][0]));
chdir("..");

require_once('includes/version.php');
require_once('includes/sql.php');
require_once('vendor/autoload.php');
require_once('includes/email.php');

$changes_retention = read_setting('changes_retention', 30);
$hosts_retention = read_setting('hosts_retention', 30);
$facts_retention = read_setting('facts_retention', 30);
$packages_retention = read_setting('packages_retention', 30);

$hr = time() - ($hosts_retention * 86400);
$fr = time() - ($facts_retention * 86400);
$cr = time() - ($changes_retention * 86400);

// Clean up old Hosts
$hosts = db_fetch_assocs_prepare('SELECT * FROM `hosts` WHERE `time` < ?', array($hr));
if (!empty($hosts)) {
    foreach ($hosts as $h) {
        db_execute_prepare('DELETE FROM `services` WHERE `host` = ?', array($h['id']));
        db_execute_prepare('DELETE FROM `packages` WHERE `host` = ?', array($h['id']));
        db_execute_prepare('DELETE FROM `facts` WHERE `host` = ?', array($h['id']));
        db_execute_prepare('DELETE FROM `changes` WHERE `host` = ?', array($h['id']));
        db_execute_prepare('DELETE FROM `hosts` WHERE `id` = ?', array($h['id']));
    }
}

// Clean up old Facts
db_execute_prepare('DELETE FROM `facts` WHERE `time` < ?', array($fr));

# Clean Up old Changes
db_execute_prepare('DELETE FROM `changes` WHERE `time` < ?', array($cr));

# Jobs are only kept for the changes that refer to them, and were never removed before
db_execute_prepare('DELETE `j` FROM `jobs` AS `j` WHERE `j`.`timestamp` < ? AND NOT EXISTS (SELECT 1 FROM `changes` AS `c` WHERE `c`.`job` = `j`.`job` AND (`c`.`server` = `j`.`server` OR `j`.`server` = \'\'))',
	array(gmdate('Y-m-d\TH:i:s', $cr)));

# Fact names that no host has any more
db_execute('DELETE FROM `fact_names` WHERE NOT EXISTS (SELECT 1 FROM `facts` WHERE `facts`.`fact` = `fact_names`.`fact`)');

# Clean Up old Sessions (they last a week, see includes/session.php)
db_execute_prepare('DELETE FROM `sessions` WHERE `access` < ?', array(time() - (60 * 60 * 24 * 7)));

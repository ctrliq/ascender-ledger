<?php

if (php_sapi_name() != 'cli') {
	exit;
}

chdir(dirname($_SERVER['argv'][0]));
chdir("..");

require_once('includes/version.php');
require_once('includes/sql.php');
require_once('vendor/autoload.php');
#require_once('includes/arrays.php');
require_once('includes/email.php');
#require_once('includes/misc.php');

$changes_retention = read_setting('changes_retention', 30);
$hosts_retention = read_setting('hosts_retention', 30);
$facts_retention = read_setting('facts_retention', 30);
$packages_retention = read_setting('packages_retention', 30);

$hr = time() - ($hosts_retention * 86400);
$fr = time() - ($facts_retention * 86400);
$pr = time() - ($packages_retention * 86400);
$cr = time() - ($changes_retention * 86400);
$sr = time() - ($packages_retention * 86400);


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

# Clean Up old Sessions
db_execute_prepare('DELETE FROM `sessions` WHERE `access` < ?', array($sr));
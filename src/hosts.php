<?php

include_once('includes/global.php');

if (!$account['super']) {
    header("Location: /\n\n");
    exit;
}


$ahosts = db_fetch_assocs("SELECT * FROM `hosts` ORDER BY `hostname`");

$hosts = array();
foreach ($ahosts as $h) {
	$h['changes'] = db_fetch_cell_prepare('SELECT COUNT(id) as count FROM `changes` WHERE `host` = ?', 'count', array($h['id']));
	$h['packages'] = db_fetch_cell_prepare('SELECT COUNT(name) as count FROM `packages` WHERE `host` = ?', 'count', array($h['id']));
	$h['packages_log'] = db_fetch_cell_prepare('SELECT COUNT(name) as count FROM `packages_log` WHERE `host` = ?', 'count', array($h['id']));
	$h['cfacts'] = db_fetch_cell_prepare('SELECT COUNT(fact) as count FROM `facts` WHERE `host` = ?', 'count', array($h['id']));

	$h['facts'] = reindex_arr_by_col(db_fetch_assocs_prepare("SELECT * FROM `facts` WHERE `host` = ? 
							AND (`fact` = 'ansible_default_ipv4.address' OR `fact` = 'ansible_distribution' OR `fact` = 'ansible_date_time.date' OR `fact` = 'ansible_distribution_version')",
						array($h['id'])), 'fact');
	$hosts[] = $h;
}

echo $twig->render('hosts.html', array_merge($twigarr, array('hosts' => $hosts)));

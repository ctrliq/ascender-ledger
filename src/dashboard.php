<?php

include_once('includes/global.php');
$t = time() -  (86400 * 30);
$changes = db_fetch_assocs_prepare('SELECT COUNT(`id`) as `count`, DATE_FORMAT(FROM_UNIXTIME(time), "%m-%d") as `d` FROM `changes` WHERE `time` > ? GROUP BY DATE_FORMAT(FROM_UNIXTIME(time), "%m-%d")', array($t));

$untrusted = db_fetch_cell('SELECT count(`id`) as count FROM `servers` WHERE `trusted` = 0', 'count');


$counts = array('hosts' => 0, 'facts' => 0, 'changes' => 0);
$counts['hosts'] = db_fetch_cell('SELECT count(`id`) as count FROM `hosts`', 'count');
$counts['facts'] = db_fetch_cell('SELECT count(`host`) as count FROM `facts`', 'count');
$counts['changes'] = db_fetch_cell('SELECT count(`id`) as count FROM `changes`', 'count');

echo $twig->render('dashboard.html', array_merge($twigarr, array('changes' => $changes, 'counts' => $counts, 'untrusted' => $untrusted)));

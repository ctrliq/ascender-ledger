<?php

include_once('includes/global.php');

if (!$account['super']) {
    header("Location: /\n\n");
    exit;
}

if (isset($_GET['action']) && $_GET['action'] == 'trust' && isset($_GET['id'])) {

	$id = intval($_GET['id']);
	$server = db_fetch_assoc_prepare('SELECT * FROM `servers` WHERE `id` = ?', array($id));
	if (isset($server['id'])) {
		$trust = ($server['trusted'] ? 0 : 1);
		db_execute_prepare('UPDATE `servers` SET `trusted` = ? WHERE `id` = ?', array($trust, $id));
	}

	Header("Location: /servers/\n\n");
	exit;
}


if (isset($_GET['action']) && $_GET['action'] == 'delete') {

	$id = intval($_GET['id']);
	$server = db_fetch_assoc_prepare('SELECT * FROM `servers` WHERE `id` = ?', array($id));
	if (isset($server['id'])) {
		db_execute_prepare('DELETE FROM `servers` WHERE `id` = ?', array($id));
	}

	Header("Location: /servers/\n\n");
	exit;
}

$msg = '';
if (isset($_GET['msg'])) {
	switch ($_GET['msg']) {
		case 'invited':
			$msg = 'Invite sent';
	}
}

$servers = db_fetch_assocs("SELECT * FROM `servers` ORDER BY `ip`");

echo $twig->render('servers.html', array_merge($twigarr, array('servers' => $servers, 'msg' => $msg)));

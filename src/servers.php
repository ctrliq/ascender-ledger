<?php

include_once('includes/global.php');

if (!$account['super']) {
    header("Location: /\n\n");
    exit;
}

if (isset($_GET['action']) && isset($_GET['id']) && intval($_GET['id']) == $_GET['id']) {
	switch ($_GET['action']) {
		case 'trust':
			$id = intval($_GET['id']);
			$server = db_fetch_assoc_prepare('SELECT * FROM `servers` WHERE `id` = ?', array($id));
			if (isset($server['id'])) {
				$trust = ($server['trusted'] ? 0 : 1);
				db_execute_prepare('UPDATE `servers` SET `trusted` = ? WHERE `id` = ?', array($trust, $id));
			}
			break;

		case 'edit':
			$id = intval($_GET['id']);
			$server = db_fetch_assoc_prepare('SELECT * FROM `servers` WHERE `id` = ?', array($id));
			if (isset($server['id'])) {
				echo $twig->render('server_edit.html', array_merge($twigarr, array('server' => $server)));
				exit;
			}
			break;

		case 'save':
			$id = intval($_GET['id']);
			$server = db_fetch_assoc_prepare('SELECT * FROM `servers` WHERE `id` = ?', array($id));
			if (isset($server['id'])) {
				$url = sql_clean_url($_POST['url']);
				if ($url != '' && $url[-1] == '/') {
					$url = sql_clean_url(substr($url, 0, -1));
				}
				db_execute_prepare('UPDATE `servers` SET `url` = ? WHERE `id` = ?', array($url, $id));
			}
			break;
		case 'delete':
			$id = intval($_GET['id']);
			$server = db_fetch_assoc_prepare('SELECT * FROM `servers` WHERE `id` = ?', array($id));
			if (isset($server['id'])) {
				db_execute_prepare('DELETE FROM `servers` WHERE `id` = ?', array($id));
			}
			break;

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

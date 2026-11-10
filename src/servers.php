<?php

include_once('includes/global.php');

if (empty($account['super'])) {
    header("Location: /\n\n");
    exit;
}

/*
	The servers that have sent data.  Ascender authenticates with the Authorization Token from
	Settings -> General, so there is nothing to approve here; the list is for setting each
	server's URL, which the change pages link jobs back to.
*/

if (isset($_GET['action']) && isset($_GET['id']) && intval($_GET['id'])) {
	$id = intval($_GET['id']);
	$server = db_fetch_assoc_prepare('SELECT * FROM `servers` WHERE `id` = ?', array($id));

	switch ($_GET['action']) {
		case 'edit':
			if (isset($server['id'])) {
				echo $twig->render('server_edit.html', array_merge($twigarr, array('server' => $server, 'app_error' => (isset($_GET['error']) && $_GET['error'] == 'url' ? 'url' : ''))));
				exit;
			}
			break;

		case 'save':
			if (isset($server['id']) && $_SERVER['REQUEST_METHOD'] == 'POST') {
				$url = (isset($_POST['url']) && is_string($_POST['url']) ? rtrim(trim($_POST['url']), '/') : '');
				if ($url == '' || valid_http_url($url)) {
					db_execute_prepare('UPDATE `servers` SET `url` = ? WHERE `id` = ?', array(substr($url, 0, 128), $id));
				} else {
					Header("Location: /servers/edit/$id?error=url\n\n");
					exit;
				}
			}
			break;

		case 'delete':
			csrf_verify();
			if (isset($server['id'])) {
				db_execute_prepare('DELETE FROM `servers` WHERE `id` = ?', array($id));
			}
			break;
	}
	Header("Location: /servers/\n\n");
	exit;
}

$servers = db_fetch_assocs("SELECT * FROM `servers` ORDER BY `name`");

$rejected = json_decode((string) read_setting('auth_rejected', '{}'), true);

echo $twig->render('servers.html', array_merge($twigarr, array('servers' => $servers, 'rejected' => (isset($rejected['time']) ? $rejected : null),
	'require_auth_token' => read_setting('require_auth_token', '0'))));

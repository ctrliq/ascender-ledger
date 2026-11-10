<?php

/*
	This file simply loads our Twig environment
*/

$loader = new \Twig\Loader\FilesystemLoader('templates');

$is_dev = read_setting('is_dev', 0);
$disable_email = read_setting('disable_email', 0);

/*
	Invite and password reset emails link back to Ledger through this setting.  While it is
	empty, take it from an administrator's own request (and only theirs: the
	Host header of an anonymous request is whatever the sender chose).
*/
if ((string) read_setting('base_url') === '' && !empty($account['super']) && isset($_SERVER['HTTP_HOST']) && preg_match('/^[A-Za-z0-9.\-]+(:[0-9]+)?$/', $_SERVER['HTTP_HOST'])) {
	$scheme = (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') || (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? 'https' : 'http';
	write_setting('base_url', $scheme . '://' . $_SERVER['HTTP_HOST']);
}

define('BASE_URL', read_setting('base_url'));

$imp = (isset($_SESSION['imp']) && intval($_SESSION['imp']) ? intval($_SESSION['imp']) : 0);

if ($is_dev) {
	$twig = new \Twig\Environment($loader, array('debug' => true));
	$twig->addExtension(new \Twig\Extension\DebugExtension());
} else {
	$twig = new \Twig\Environment($loader);
}

/*
	We pass in the account variable automatically since we use it in most templates
*/
if (!isset($account['id'])) {
	$account = array('id' => 0);
}

$twigarr = array('account' => $account, 'request_scheme' => (isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'cli'),
				'server_name' => (isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : ''), 'server' => (isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : ''),
				'is_dev' => $is_dev, 'disable_email' => $disable_email, 'imp' => $imp, 'version' => VERSION, 'base_url' => BASE_URL,
				'csrf_token' => (isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : ''),
				'export_limit' => (function_exists('export_limit') ? export_limit() : 10000));

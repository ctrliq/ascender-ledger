<?php

/*
	This file simply loads our Twig environment
*/

$loader = new Twig_Loader_Filesystem('templates');

$twig = new Twig_Environment($loader, array('debug' => true));

/*
	We pass in the account variable automatically since we use it in most templates
*/
$is_dev = read_setting('is_dev', 0);
$disable_email = read_setting('disable_email', 0);

define('BASE_URL', read_setting('base_url'));

$imp = (isset($_SESSION['imp']) && intval($_SESSION['imp']) ? intval($_SESSION['imp']) : 0); 
// REMOVE ME
$is_dev = read_setting('is_dev', 0);

if ($is_dev) {
	$twig->addExtension(new Twig_Extension_Debug());
} else {
	$twig = new Twig_Environment($loader, array(
	#    'cache' => '../cache',
	));
}

if (!isset($account['id'])) {
	$account = array('id' => 0);
}

$twigarr = array('account' => $account, 'request_scheme' => (isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'cli'), 
				'server_name' => (isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : ''), 'server' => (isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : ''), 
				'is_dev' => $is_dev, 'disable_email' => $disable_email, 'imp' => $imp, 'version' => VERSION, 'base_url' => BASE_URL);
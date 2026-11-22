<?php
$account = array();
include_once('includes/global.php');
echo $twig->render('login.html', array_merge($twigarr, array('custom_login' => read_setting('custom_login', ''),
	'oidc' => oidc_configured(), 'local_auth' => local_auth_enabled(), 'oidc_failed' => (isset($_GET['error']) && $_GET['error'] == 'oidc'))));

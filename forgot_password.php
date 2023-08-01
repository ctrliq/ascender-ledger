<?php
$account = array();
include_once('includes/global.php');

if (isset($_POST['email'])) {
	sleep(1);
	if (filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
	       $email = str_replace("\n", '', sql_clean_ans($_POST['email']));
		$user = db_fetch_assoc_prepare('SELECT * FROM `users` WHERE `email` = ?', array($email));
		if (isset($user['id'])) {
			$code     = md5(md5(mt_rand(100, 10000)));

			db_execute_prepare('UPDATE `users` SET `code` = ? WHERE `email` = ?', array($code, $email));

			$base_url = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['SERVER_NAME'];
			$url      = $base_url . '/index.php?code=' . $code;

			$body = str_replace("\n", '<br>', $twig->render('emails/forgot_password.html', array_merge($twigarr, array('base_url' => $base_url, 'url' => $url, 'code' => $code, 'email' => $email))));
			send_email ($email, 'Request Tracker - Password Reset', $body);
		}
	}
	Header("Location: forgot_password.php?recovered=1\n\n");
	exit;
}


$recovered = 0;
if (isset($_GET['recovered'])) {
	$recovered = 1;
}

echo $twig->render('forgot_password.html', array_merge($twigarr, array('recovered' => $recovered)));


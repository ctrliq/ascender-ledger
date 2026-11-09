<?php
$account = array();
include_once('includes/global.php');

if (isset($_POST['email']) && is_string($_POST['email'])) {
	sleep(1);
	if (filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
		$email = sql_clean_email($_POST['email']);
		$user = db_fetch_assoc_prepare('SELECT * FROM `users` WHERE `email` = ? AND `enabled` = 1', array($email));

		/*
			The link is built from the configured Base URL, never from the request: the Host
			header is the requester's to choose, and would let them send a victim a reset link
			pointing at their own site.
		*/
		$base_url = rtrim((string) BASE_URL, '/');
		if (isset($user['id']) && $base_url == '') {
			error_log('Ledger: a password reset was requested, but no Base URL is set under Settings -> General to build the link from');
		} elseif (isset($user['id'])) {
			$code = random_str(32);

			db_execute_prepare('UPDATE `users` SET `code` = ?, `code_expires` = ? WHERE `id` = ?', array($code, time() + 86400, $user['id']));

			$url = $base_url . '/login/' . $code;

			$body = $twig->render('emails/forgot_password.html', array_merge($twigarr, array('base_url' => $base_url, 'url' => $url, 'email' => $email)));
			send_email($email, 'Ascender Ledger - Password Reset', $body);
		}
	}
	Header("Location: /forgot_password/?recovered=1\n\n");
	exit;
}


$recovered = 0;
if (isset($_GET['recovered'])) {
	$recovered = 1;
}

echo $twig->render('forgot_password.html', array_merge($twigarr, array('recovered' => $recovered)));


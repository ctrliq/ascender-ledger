<?php
include_once('includes/global.php');

if (!isset($account['id']) || !intval($account['id'])) {
	exit;
}

$error = "";
$user = new User($account['id']);
$name = $user->name;

if (isset($_POST['action']) && $_POST['action'] == 'save') {
	if (isset($_POST['name'])) {
		$name = $user->clean_name($_POST['name']);
		if ($name != '' && $name != $user->name) {
			$user->set_name($name);
			$user->save();
		}
	}

	$cpass = (isset($_POST['cpass']) && is_string($_POST['cpass']) ? $_POST['cpass'] : '');
	// Signed in with a still-valid invite or reset code: that code stands in for the current password
	$code_login = (!empty($_SESSION['code']) && $user->code != '' && hash_equals((string) $user->code, (string) $_SESSION['code']));
	if ($cpass != '' || $code_login) {
		// Passwords are stored hashed with a key (see User::set_password), so check them the way login does
		if ($code_login || check_user_password($user->username, trim($cpass))) {
			$newpass = (isset($_POST['newpass']) ? (string) $_POST['newpass'] : '');
			$newpass2 = (isset($_POST['newpass2']) ? (string) $_POST['newpass2'] : '');
			if ($newpass != '' && strlen($newpass) > 7 && $newpass == $newpass2) {
				$user->set_password($newpass);
				$user->set_code('');
				$user->save();
				$_SESSION['code'] = '';
			}
		
			Header("Location: /profile/\n\n");
			exit;
		} else {
			$error = "current";
		}
	} else {
		Header("Location: /profile/\n\n");
		exit;
	}
}

$code_login = (!empty($_SESSION['code']) && $user->code != '' && hash_equals((string) $user->code, (string) $_SESSION['code']));
echo $twig->render('profile.html', array_merge($twigarr, array('name' => $name, 'error' => $error, 'code_login' => $code_login)));

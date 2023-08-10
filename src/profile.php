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
		if ($name != $user->name) {
			$user->set_name($name);
			$user->save();
		}
	}

	$cpass = (isset($_POST['cpass']) ? $_POST['cpass'] : '');
	if ($cpass != '' || $user->code != '') {
		if (hash('sha256', $cpass) == $user->password || $user->code != '') {
			$newpass = $_POST['newpass'];
			$newpass2 = $_POST['newpass2'];
			if ($newpass != '' && strlen($newpass) > 7 && $newpass == $newpass2) {
				$user->set_password($newpass);
				$user->set_code('');
				$user->save();
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

echo $twig->render('profile.html', array_merge($twigarr, array('name' => $name, 'error' => $error)));

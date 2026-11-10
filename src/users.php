<?php

include_once('includes/global.php');

if (!$account['super']) {
    header("Location: /\n\n");
    exit;
}

if (isset($_GET['user']) && $_GET['user'] == intval($_GET['user']) && intval($_GET['user'])) {
    $user = new User(intval($_GET['user']));
} else {
    $user = new User();
}

if (isset($_GET['action'])) {
	switch ($_GET['action']) {
		case 'invite':
			if ($_SERVER['REQUEST_METHOD'] != 'POST' || !isset($_POST['email'])) {
				Header("Location: /users/\n\n");
				exit;
			}
			$email = strtolower($user->clean_username(post_str('email')));
			if (!filter_var($email , FILTER_VALIDATE_EMAIL)) {
				Header("Location: /users/?error=invalidemail\n\n");
				exit;
			}
			if (db_fetch_cell_prepare('SELECT `id` FROM `users` WHERE `username` = ? OR `email` = ?', 'id', array($email, $email))) {
				Header("Location: /users/?error=exists\n\n");
				exit;
			}
			$code = random_str(32);
			$user->set_username($email);
			$user->set_email($email);
			$user->set_code($code, 7 * 86400);
			$user->save();

			$html = $twig->render('emails/user_invite.html', array_merge($twigarr, array('email' => $email, 'code' => $code))); 
			send_email($email, 'Invite - Ascender Ledger', $html);

			Header("Location: /users/?msg=invited\n\n");
			exit;

		case 'delete':
			csrf_verify();
			// Deleting yourself would leave the session pointing at nobody
			if ($user->id && $user->id != $account['id']) {
				$user->delete();
			}
			Header("Location: /users/\n\n");
			exit;

		case 'edit':
			if ($user->id) {
      		  echo $twig->render('user.html', array_merge($twigarr, array('user' => $user)));
      		  exit;
   			}
			exit;

		case 'save':
			if ($user->id && $_SERVER['REQUEST_METHOD'] == 'POST') {
				$self = ($user->id == $account['id']);
				if (isset($_POST['name']) && $user->clean_name($_POST['name']) != '') {
					$user->set_name($_POST['name']);
				}

				if (isset($_POST['username']) && $user->clean_username($_POST['username']) != '') {
					$user->set_username($_POST['username']);
				}

				if (isset($_POST['email']) && $user->clean_username($_POST['email']) != '') {
					$user->set_email($_POST['email']);
				}

				// An administrator cannot disable or demote themselves, and so lock everyone out
				if (isset($_POST['enabled']) && !$self) {
					$user->set_enabled(intval($_POST['enabled']));
				}

				if (isset($_POST['super']) && !$self) {
					$user->set_super($_POST['super']);
				}

				if (isset($_POST['view_changes'])) {
					$user->set_view_changes($_POST['view_changes']);
				}

				if (isset($_POST['view_facts'])) {
					$user->set_view_facts($_POST['view_facts']);
				}

				$newpass = (isset($_POST['newpass']) ? (string) $_POST['newpass'] : '');
				$newpass2 = (isset($_POST['newpass2']) ? (string) $_POST['newpass2'] : '');
				if ($newpass != '' && strlen($newpass) > 7 && $newpass == $newpass2) {
					$user->set_password($newpass);
					$user->set_code('');
				}
				$user->save();
			}
			Header("Location: /users/\n\n");
			exit;
	}
}


$error = '';
if (isset($_GET['error'])) {
	switch ($_GET['error']) {
		case 'invalidemail':
			$error = 'Invalid Email Address';
			break;
		case 'exists':
			$error = 'A user with that email address already exists';
	}
}

$msg = '';
if (isset($_GET['msg'])) {
	switch ($_GET['msg']) {
		case 'invited':
			$msg = 'Invite sent';
	}
}

$users = db_fetch_assocs("SELECT * FROM `users` ORDER BY `username`");

echo $twig->render('users.html', array_merge($twigarr, array('users' => $users, 'error' => $error, 'msg' => $msg)));

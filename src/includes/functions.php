<?php

/*
	This file contains various misc functions that didn't fit in well anywhere else
*/

// Start the session
if (!isset($_SESSION)) {
	session_start();
}

$logged = false;
$account = array();

// $account holds information about the current logged in user.  We should probably swap this to a session variable
global $account;
$account['code'] = '';

if (isset($_SESSION['imp']) && $_SESSION['imp'] && $_SESSION['imp'] != '' && isset($_GET['revertimp'])) {
	$id = intval($_SESSION['imp']);
	$user = new User($id);
	if ($user->id && $user->enabled && $user->operator) {
		unset($_SESSION['imp']);
		$_SESSION['username'] = $user->username;
	}
	Header("Location: /?reverted=1\n\n");
	exit;
}

if (isset($_SESSION['auth']) && $_SESSION['auth'] == TRUE) {
	$username = $_SESSION['username'];
	$logged = true;
}

if (isset($_SERVER['PHP_SELF']) && $_SERVER['PHP_SELF'] != '/login.php' && $_SERVER['PHP_SELF'] != '/forgot_password.php') {
	auth_check();
}

if (isset($_SERVER['PHP_SELF']) && $_SERVER['PHP_SELF'] == '/login.php' && isset($_GET['code'])) {
	auth_check();
}

if (isset($_SERVER['PHP_SELF']) && $_SERVER['PHP_SELF'] == '/logout.php' && isset($_SESSION['code'])) {
	unset($_SESSION['auth']);
	unset($_SESSION['username']);
	unset($_SESSION['auth']);
	unset($_SESSION['code']);
	session_destroy();
}


if ($account['code'] != '' && $_SERVER['PHP_SELF'] != '/profile.php') {
	Header("Location: /profile/\n\n");
	exit;
}

include_once('includes/templates.php');


/*
	A simple function to check if we are an operator.  It is used on pages that are for admins only to 
	redirect back to the main page if they are not authorized
*/
function op_check() {
	global $account;
	if ($account['super'] == 0) {
		Header("Location: /\n\n");
		exit;	
	}
	return true;
}

/*
	This function does most everything for authorization.  It will check to see if you are already logged in or if a 
	username / password is passed and will check it.  It then sets a few session variables and an account variable
*/
function auth_check() {
	global $username, $logged, $userid, $account;
	if (isset($_SESSION['auth']) && $_SESSION['auth'] == TRUE) {
		// Check for authorization on specific pages
		$user = new User();
		$account = $user->retrieve_by_username($username);
		if (!$user->enabled) {
			$account = null;
		}
		return true;
	}
	$_SESSION['auth'] = 0;
	if (isset($_GET['code']) && sql_clean_code($_GET['code']) != '') {
		$code = sql_clean_code($_GET['code']);
		$user = new User();
		$account = $user->retrieve_by_code($code);
		if ($user->enabled) {
			$_SESSION['auth'] = TRUE;	
			$_SESSION['username'] = $account['username'];
			$_SESSION['code'] = $code;
			return true;
		} else {
			sleep(5);
			$account = null;
			Header("Location: /\n\n");
			exit;
		}
	}

	if (isset($_POST['username']) && $_POST['username'] != '') {
		$username = sql_clean_username($_POST['username']);
	}

	if (!isset($_POST['username']) || !isset($_POST['password'])) {
		$url = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
		$_SESSION['referrer'] = sql_clean_url($url);
		showloginbox(0);
		return false;
	}

	$check = check_user_password(trim($username), trim($_POST['password']));

	if ($check) {
		$_SESSION['auth'] = TRUE;	
		$_SESSION['username'] = $username;
		$_SESSION['password'] = $_POST['password'];
		$_SESSION['code'] = '';

		$logged = true;
		//db_execute_prepare('UPDATE users SET lastlogin = ? WHERE username = ?', array(time(), $username));
		//$ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
		//$ip = trim($ip[0]);
		//db_execute_prepare('INSERT INTO ip_log (`time`, `type`, `username`, `ip`) VALUES (?, ?, ?, ?)', array(time(), 'Website', $username, $ip));
              $user = new User();
              $account = $user->retrieve_by_username($username);
		if (!$user->enabled) {
			$account = null;
		}

		//if (isset($_SESSION['referrer']) && $_SESSION['referrer'] != '') {
		//	$url = $_SESSION['referrer'];
		//	$_SESSION['referrer'] = '';
		//	header("Location: $url\n\n");
		//	exit;
		//}

		return true;
	} else {
		showloginbox(1);;
		exit;
	}
}

function check_user_password($username, $pass) {
	$username  = sql_clean_username(strtolower($username));
	$user = new User();
	$account = $user->retrieve_by_username($username);
	if ($user->id && $user->enabled) {
		if (password_verify(hash_hmac('sha256', $pass, FIRSTKEY), $user->password)) {
			// Check if password needs rehashing
			if (password_needs_rehash($user->password, PASSWORD_DEFAULT)) {
				$user->set_password($pass);
				$user->save();
			}
			return true;
		// Check for old password hash, rehash if necessary
		} elseif (hash('sha256', $pass) == $user->password) {
			$user->set_password($pass);
			$user->save();
			return true;
		}
	}
	sleep(1);
	return false;
}

function showloginbox($error) {
	Header("Location: /login/\n\n");
	exit;
}

<?php

/*
	This file contains various misc functions that didn't fit in well anywhere else
*/

// Start the session
if (session_status() !== PHP_SESSION_ACTIVE) {
	session_start();
}

$logged = false;
$account = array();

// $account holds information about the current logged in user.  We should probably swap this to a session variable
global $account;
$account['code'] = '';

if (isset($_SESSION['auth']) && $_SESSION['auth'] == TRUE) {
	$username = $_SESSION['username'];
	$logged = true;
}

/*
	Every POST made by a signed in user has to carry the session's token, so another site
	cannot submit forms (create an admin, change a password, change settings) through an
	administrator's browser.  The layout adds the token to every form on the page.  The login
	form itself is posted before there is a signed in session, so it is not covered.
*/
if ($logged && isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'POST') {
	csrf_verify();
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
	unset($_SESSION['code']);
	session_destroy();
}

if (empty($_SESSION['csrf_token'])) {
	$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/*
	Signed in with an invite or reset code: the password has to be set before anything else.
	Only that session is held to it; someone requesting a reset for an address does not
	affect the owner's existing sessions.
*/
if (!empty($_SESSION['auth']) && !empty($_SESSION['code']) && $_SERVER['PHP_SELF'] != '/profile.php' && $_SERVER['PHP_SELF'] != '/logout.php') {
	Header("Location: /profile/\n\n");
	exit;
}

include_once('includes/templates.php');

/*
	Checks the request carries the session's CSRF token, from a form field, a header or (for
	the actions that are still links) the query string.  Stops the request if it does not.
*/
function csrf_verify() {
	$sent = '';
	if (isset($_POST['csrf_token'])) {
		$sent = $_POST['csrf_token'];
	} elseif (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
		$sent = $_SERVER['HTTP_X_CSRF_TOKEN'];
	} elseif (isset($_GET['csrf_token'])) {
		$sent = $_GET['csrf_token'];
	}

	if (!is_string($sent) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $sent)) {
		http_response_code(403);
		print 'The form has expired or did not come from Ledger.  Go back, reload the page and try again.';
		exit;
	}
	return true;
}

/*
	A simple function to check if we are an operator.  It is used on pages that are for admins only to
	redirect back to the main page if they are not authorized
*/
function op_check() {
	global $account;
	if (empty($account['super'])) {
		Header("Location: /\n\n");
		exit;
	}
	return true;
}

/*
	Signs the session out and sends the browser to the login page
*/
function auth_reset_session() {
	$_SESSION = array();
	session_regenerate_id(true);
	showloginbox(0);
}

/*
	This function does most everything for authorization.  It will check to see if you are already logged in or if a
	username / password is passed and will check it.  It then sets a few session variables and an account variable
*/
function auth_check() {
	global $username, $logged, $userid, $account;
	if (isset($_SESSION['auth']) && $_SESSION['auth'] == TRUE) {
		// A user who was disabled or deleted since signing in loses the session with it
		$user = new User();
		$account = $user->retrieve_by_username($username);
		if (!$user->id || !$user->enabled) {
			$account = array();
			auth_reset_session();
		}
		// A session signed in with a code ends when that code expires or is replaced
		if (!empty($_SESSION['code']) && (!hash_equals((string) $user->code, (string) $_SESSION['code']) || ($user->code_expires && $user->code_expires < time()))) {
			$account = array();
			auth_reset_session();
		}
		return true;
	}
	$_SESSION['auth'] = 0;
	if (isset($_GET['code']) && is_string($_GET['code']) && sql_clean_code($_GET['code']) != '') {
		$code = sql_clean_code($_GET['code']);
		$user = new User();
		$account = $user->retrieve_by_code($code);
		if ($user->id && $user->enabled) {
			session_regenerate_id(true);
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

	if (isset($_POST['username']) && is_string($_POST['username']) && $_POST['username'] != '') {
		$username = sql_clean_username($_POST['username']);
	}

	if (!isset($_POST['username']) || !isset($_POST['password']) || !is_string($_POST['password'])) {
		showloginbox(0);
		return false;
	}

	$check = check_user_password(trim($username), trim($_POST['password']));

	if ($check) {
		// A new session id on login, so one fixed before login is worthless after it
		session_regenerate_id(true);
		$username = strtolower(trim($username));
		$_SESSION['auth'] = TRUE;
		$_SESSION['username'] = $username;
		$_SESSION['code'] = '';
		$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

		$logged = true;
		$user = new User();
		$account = $user->retrieve_by_username($username);
		if (!$user->enabled) {
			$account = null;
		}

		return true;
	} else {
		showloginbox(1);
		exit;
	}
}

function check_user_password($username, $pass) {
	$username  = sql_clean_username(strtolower($username));
	$user = new User();
	$account = $user->retrieve_by_username($username);
	if ($user->id && $user->enabled && $user->password != '') {
		if (password_verify(hash_hmac('sha256', $pass, FIRSTKEY), $user->password)) {
			// Check if password needs rehashing
			if (password_needs_rehash($user->password, PASSWORD_DEFAULT)) {
				$user->set_password($pass);
				$user->save();
			}
			return true;
		// Set from the admin-password secret by the database container (files/mysql_first.sh): a
		// salted bcrypt of the SHA-256, since that container has no FIRSTKEY.  Convert it now.
		} elseif (password_verify(hash('sha256', $pass), $user->password)) {
			$user->set_password($pass);
			$user->save();
			return true;
		// Check for old password hash, rehash if necessary
		} elseif (hash_equals((string) $user->password, hash('sha256', $pass))) {
			$user->set_password($pass);
			$user->save();
			return true;
		}
	}
	sleep(1);
	return false;
}

/*
	A form field as a string: '' when it is missing, or an array (name[]=x) where text was expected
*/
function post_str($k) {
	return (isset($_POST[$k]) && is_scalar($_POST[$k]) ? (string) $_POST[$k] : '');
}

function showloginbox($error) {
	Header("Location: /login/\n\n");
	exit;
}

/*
	Makes a change to users, teams or permissions, unless it would take Administrator away from
	the administrator making it: removing yourself from a team, a team's permissions, or your
	own.  That would leave nobody who could put it back.  Returns whether the change was kept.
*/
function change_keeping_admin($change) {
	global $account;
	db_begin();
	try {
		$change();
	} catch (Exception $e) {
		db_rollback();
		throw $e;
	}
	$p = user_permissions($account['id']);
	if (empty($p['super'])) {
		db_rollback();
		return false;
	}
	db_commit();
	return true;
}

/*
	The message or error named in the query string (?msg=saved, ?error=lockout), for the admin pages
*/
function admin_messages() {
	$msgs = array(
		'saved'   => 'Saved',
		'invited' => 'Invite sent',
		'created' => 'Team created',
		'deleted' => 'Deleted',
	);
	$errors = array(
		'invalidemail' => 'Invalid Email Address',
		'exists'       => 'A user with that email address already exists',
		'userexists'   => 'Another user already has that username',
		'teamexists'   => 'A team with that name already exists',
		'teamname'     => 'A team name needs letters or numbers',
		'lockout'      => 'Not saved: that would take away your own Administrator permission',
	);
	return array(
		'msg'   => (isset($_GET['msg']) && is_string($_GET['msg']) && isset($msgs[$_GET['msg']]) ? $msgs[$_GET['msg']] : ''),
		'error' => (isset($_GET['error']) && is_string($_GET['error']) && isset($errors[$_GET['error']]) ? $errors[$_GET['error']] : ''),
	);
}

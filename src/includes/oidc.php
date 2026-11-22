<?php

// How often (seconds) an OIDC session is checked against the provider
define('OIDC_SESSION_CHECK', 300);

function oidc_configured() {
	return (trim((string) read_setting('oidc_key', '')) != '' && trim((string) read_setting('oidc_provider_url', '')) != '');
}

// Local authentication can only be disabled while OIDC is configured, so nobody gets locked out
function local_auth_enabled() {
	return (read_setting('disable_local_auth', '0') != '1' || !oidc_configured());
}

function oidc_redirect_url() {
	$base_url = rtrim((string) read_setting('base_url', ''), '/');
	return ($base_url == '' ? '' : $base_url . '/oidc/');
}

function oidc_clean_claim($text) {
	return (is_scalar($text) ? preg_replace('/[^A-Za-z0-9_\-:\.]/', '', (string) $text) : '');
}

function oidc_clean_group($text) {
	return (is_scalar($text) ? substr(trim(preg_replace('/[^A-Za-z0-9 _\-\.\/:@]/', '', (string) $text)), 0, 255) : '');
}

function oidc_client() {
	$secret = (string) read_setting('oidc_secret', '');
	if ($secret != '') {
		$secret = (string) secured_decrypt($secret);
	}

	$oidc = new \Jumbojett\OpenIDConnectClient(trim((string) read_setting('oidc_provider_url', '')), trim((string) read_setting('oidc_key', '')), $secret);
	$oidc->setRedirectURL(oidc_redirect_url());
	$oidc->addScope(array('profile', 'email'));
	$oidc->setCodeChallengeMethod('S256');
	$oidc->setTimeout(10);
	if (read_setting('oidc_verify_ssl', '1') != '1') {
		$oidc->setVerifyHost(false);
		$oidc->setVerifyPeer(false);
	}
	return $oidc;
}

/*
	Finds the user for the verified claims by their OIDC subject, links an existing user with the
	same username, or creates one.  Returns the User, or the reason the login is refused.
*/
function oidc_user($claims) {
	$claim = oidc_clean_claim(read_setting('oidc_username_claim', 'preferred_username'));
	$sub = (isset($claims->sub) && is_string($claims->sub) ? $claims->sub : '');
	$username = (isset($claims->$claim) && is_string($claims->$claim) ? strtolower(trim($claims->$claim)) : '');

	if ($sub == '' || strlen($sub) > 255) {
		return 'no subject in the ID token';
	}

	$user = new User();
	if ($username == '' || strlen($username) > 64 || $user->clean_username($username) !== $username) {
		return "claim $claim is missing or not a valid username";
	}

	if ($claim == 'email' && isset($claims->email_verified) && $claims->email_verified !== true) {
		return "$username is not verified";
	}

	$id = db_fetch_cell_prepare('SELECT `id` FROM `users` WHERE `oidc_sub` = ?', 'id', array($sub));
	if ($id) {
		$user->retrieve($id);
	} else {
		$u = db_fetch_assoc_prepare('SELECT `id`, `oidc_sub` FROM `users` WHERE `username` = ?', array($username));
		if (isset($u['id']) && (string) $u['oidc_sub'] != '') {
			return "$username is linked to another OIDC subject";
		}
		if (isset($u['id'])) {
			$user->retrieve($u['id']);
		} elseif (read_setting('oidc_create_users', '1') == '1') {
			$user->set_username($username);
			$user->set_registered(1);
		} else {
			return "user $username does not exist";
		}
		$user->set_oidc_sub($sub);
	}

	if (!$user->enabled) {
		return "$username is disabled";
	}

	if (isset($claims->name) && is_string($claims->name) && $user->clean_name($claims->name) != '') {
		$user->set_name(substr($claims->name, 0, 64));
	}
	if (isset($claims->email) && is_string($claims->email) && strlen($claims->email) <= 64 && filter_var($claims->email, FILTER_VALIDATE_EMAIL)) {
		$user->set_email(strtolower($claims->email));
	}
	$user->save();

	return $user;
}

// Teams with an OIDC group get their members from the groups claim, other teams are left alone
function oidc_sync_teams($user, $claims) {
	$claim = oidc_clean_claim(read_setting('oidc_groups_claim', 'groups'));
	$groups = array();
	if ($claim != '' && isset($claims->$claim) && is_array($claims->$claim)) {
		foreach ($claims->$claim as $g) {
			if (is_string($g)) {
				$groups[$g] = true;
			}
		}
	}

	db_begin();
	try {
		foreach (db_fetch_assocs("SELECT `id`, `oidc_group` FROM `teams` WHERE `oidc_group` != ''") as $t) {
			if (isset($groups[$t['oidc_group']])) {
				db_execute_prepare('INSERT IGNORE INTO `team_user` (`team`, `user`) VALUES (?, ?)', array($t['id'], $user->id));
			} else {
				db_execute_prepare('DELETE FROM `team_user` WHERE `team` = ? AND `user` = ?', array($t['id'], $user->id));
			}
		}
	} catch (Exception $e) {
		db_rollback();
		throw $e;
	}
	db_commit();
}

/*
	Uses the refresh token to check an OIDC session is still valid at the provider, so a user that
	is disabled or logged out there is logged out here too.  If the provider can't be reached the
	session is kept.
*/
function oidc_session_valid() {
	if (empty($_SESSION['oidc']) || !is_array($_SESSION['oidc'])) {
		return true;
	}

	if (!oidc_configured()) {
		return false;
	}

	if (time() - intval($_SESSION['oidc']['checked']) < OIDC_SESSION_CHECK) {
		return true;
	}
	$_SESSION['oidc']['checked'] = time();

	if (empty($_SESSION['oidc']['refresh_token'])) {
		return true;
	}

	try {
		$r = oidc_client()->refreshToken($_SESSION['oidc']['refresh_token']);
	} catch (Exception $e) {
		error_log('Ledger: OIDC session check failed: ' . $e->getMessage());
		return true;
	}

	if (!is_object($r)) {
		return true;
	}
	if (isset($r->error)) {
		return false;
	}

	if (!empty($r->refresh_token) && is_string($r->refresh_token)) {
		$_SESSION['oidc']['refresh_token'] = $r->refresh_token;
	}
	return true;
}

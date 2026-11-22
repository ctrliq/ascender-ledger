<?php
$account = array();
include_once('includes/global.php');

if (!oidc_configured()) {
	Header("Location: /login/\n\n");
	exit;
}

if (oidc_redirect_url() == '') {
	error_log('Ledger: OIDC login needs the Base URL to be set under Settings -> General');
	Header("Location: /login/?error=oidc\n\n");
	exit;
}

try {
	$oidc = oidc_client();
	// Redirects to the provider, or verifies the ID token when the provider sends us back
	$oidc->authenticate();
	$claims = $oidc->getVerifiedClaims();
	$user = oidc_user($claims);
	if (is_object($user)) {
		oidc_sync_teams($user, $claims);
	}
} catch (Exception $e) {
	error_log('Ledger: OIDC login failed: ' . $e->getMessage());
	Header("Location: /login/?error=oidc\n\n");
	exit;
}

if (!is_object($user)) {
	error_log('Ledger: OIDC login refused: ' . $user);
	Header("Location: /login/?error=oidc\n\n");
	exit;
}

session_regenerate_id(true);
$_SESSION['auth'] = TRUE;
$_SESSION['username'] = $user->username;
$_SESSION['code'] = '';
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$_SESSION['oidc'] = array(
	'id_token'      => (string) $oidc->getIdToken(),
	'refresh_token' => (string) $oidc->getRefreshToken(),
	'checked'       => time(),
);

$account = $user->retrieve($user->id);

Header("Location: /\n\n");
exit;

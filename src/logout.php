<?php

include_once('includes/global.php');

if( !isset($_SESSION)) {
	session_start();
}

$id_token = (!empty($_SESSION['oidc']['id_token']) ? (string) $_SESSION['oidc']['id_token'] : '');

unset($_SESSION['auth']);
unset($_SESSION['username']);
unset($_SESSION['auth']);
unset($_SESSION['code']);
unset($_SESSION['oidc']);
@session_destroy();

// Also log out of the OIDC provider
if ($id_token != '' && oidc_configured()) {
	try {
		$base_url = rtrim((string) read_setting('base_url', ''), '/');
		oidc_client()->signOut($id_token, ($base_url != '' ? $base_url . '/login/' : null));
	} catch (Exception $e) {
		error_log('Ledger: OIDC logout failed: ' . $e->getMessage());
	}
}

Header("Location: /\n\n");
exit;

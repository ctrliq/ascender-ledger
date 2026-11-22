<?php

// Enables or disables local authentication, for when the OIDC provider is unavailable
// php utility/local_auth.php enable|disable

if (php_sapi_name() != 'cli') {
	exit;
}

chdir(dirname($_SERVER['argv'][0]));
chdir("..");

require_once('includes/version.php');
require_once('includes/sql.php');
require_once('includes/oidc.php');

$action = (isset($_SERVER['argv'][1]) ? $_SERVER['argv'][1] : '');

if ($action == 'enable') {
	write_setting('disable_local_auth', '0');
	echo "Local authentication enabled\n";
	exit(0);
}

if ($action == 'disable') {
	write_setting('disable_local_auth', '1');
	echo (oidc_configured() ? "Local authentication disabled\n" : "Local authentication stays enabled until OIDC is configured\n");
	exit(0);
}

echo "Usage: php utility/local_auth.php enable|disable\n";
exit(1);

<?php

global $mydb;

$sql_server = 'ledger-db';
$port = '3306';
$mydb = 'ledger';
$sql_user = 'ledger';

//ini_set('display_errors', 0);
//ini_set('display_startup_errors', 0);
//error_reporting(E_NONE);

$firstkey = '';
$secondkey = '';

if (file_exists('/run/secrets/db-ledger-password/db-ledger-password')) {
	$sql_pass = file_get_contents('/run/secrets/db-ledger-password/db-ledger-password');
} else {
	$sql_pass = file_get_contents('/run/secrets/db-ledger-password');
}

if (@file_exists('/run/secrets/firstkey/firstkey')) {
	$firstkey = @file_get_contents('/run/secrets/firstkey/firstkey');
} elseif (file_exists('/run/secrets/firstkey')) {
	$firstkey = @file_get_contents('/run/secrets/firstkey');
} else {
	$firstkey = sha1($sql_pass);
}

if (file_exists('/run/secrets/secondkey/secondkey')) {
	$secondkey = @file_get_contents('/run/secrets/secondkey/secondkey');
} elseif (file_exists('/run/secrets/secondkey')) {
	$secondkey = @file_get_contents('/run/secrets/secondkey');
} else {
	$secondkey = md5($sql_pass);
}

@define('FIRSTKEY', $firstkey);
@define('SECONDKEY', $secondkey);

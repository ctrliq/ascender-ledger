<?php

include_once('includes/global.php');

if (!$account['super']) {
    header("Location: /\n\n");
    exit;
}

// General
$base_url = read_setting('base_url', (isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] . "://" : '') . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : ''));
$remove_invocation = read_setting('remove_invocation', 0);
$changes_retention = read_setting('changes_retention', 30);
$facts_retention = read_setting('facts_retention', 30);
$packages_retention = read_setting('packages_retention', 30);
$hosts_retention = read_setting('hosts_retention', 30);
$allowed_modules = read_setting('allowed_modules', 'gather_facts');

// Mail
$email_fromname = read_setting('email_fromname', 'Ascender Ledger');
$email_from = read_setting('email_from', 'ledger@ascender.local');
$smtp_server = read_setting('smtp_server', '');
$smtp_port = read_setting('smtp_port', '25');
$smtp_security = read_setting('smtp_security', '');
$smtp_username = read_setting('smtp_username', '');
$smtp_password = read_setting('smtp_password', '');

if (isset($_GET['action']) && $_GET['action'] == 'save') {
	if (count($_POST)) {
		foreach ($_POST as $f => $v) {
			$skip = false;
			if ($f == 'changes_retention' || $f == 'packages_retention' || $f == 'facts_retention' || $f == 'hosts_retention') {
				if (intval($v / 30) == $v / 30) {
					$v = intval($v);
				} else {
					$skip = true;
				}
			}
			if ($f == 'remove_invocation' || $f == 'cache_templates' || $f == 'disable_email') {
				$v = (intval($v) ? 1 : 0);
			}
			if ($f == 'allowed_modules') {
				$allowed_modules = array();
				foreach ($v as $a) {
					$allowed_modules[] = strtolower(sql_clean_fact($a));
				}
				$v = implode(',', $allowed_modules);
			}
			if ($f == 'base_url') {
				$v = sql_clean_url($v);
			}
			if ($f == 'email_fromname') {
				$v = sql_clean_name($v);
			}
			if ($f == 'email_from') {
				$v = sql_clean_email($v);
			}
			if ($f == 'smtp_server') {
				$v = sql_clean_hostname($v);
			}
			if ($f == 'smtp_port') {
				$v = ($v != '' ? intval($v) : '');
			}
			if ($f == 'smtp_security') {
				$v = ($v != '' ? 'tls' : '');
			}
			if ($f == 'smtp_username') {
				$v = sql_clean_email($v);
			}
			if ($f == 'smtp_password') {
				if ($v == '') {
					$skip = true;
				} else {
					$v = ($v != ''  ? secured_encrypt($v) : '');
				}
			}
			if (!$skip) {
				db_execute_prepare('UPDATE `settings` SET `value` = ? WHERE `setting` = ?', array($v, $f));
			}
		}
	}
	Header("Location: /settings/\n\n");
	exit;
}

$settings = array();
$set      = db_fetch_assocs('SELECT * FROM `settings`');

foreach ($set as $s) {
	$settings[$s['setting']] = $s['value'];
}

$default_modules = array('gather_facts');

if (isset($_GET['action']) && $_GET['action'] == 'general') {
	echo $twig->render('settings_general.html', array_merge($twigarr, array('settings' => $settings, 'default_modules' => $default_modules)));
	exit;
}

if (isset($_GET['action']) && $_GET['action'] == 'mail') {
	echo $twig->render('settings_mail.html', array_merge($twigarr, array('settings' => $settings, 'default_modules' => $default_modules)));
	exit;
}


echo $twig->render('settings.html', array_merge($twigarr, array('settings' => $settings, 'default_modules' => $default_modules)));

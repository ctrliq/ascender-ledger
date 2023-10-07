<?php

include_once('includes/global.php');

if (!$account['super']) {
    header("Location: /\n\n");
    exit;
}

$changes_retention = read_setting('changes_retention', 30);
$facts_retention = read_setting('facts_retention', 30);
$hosts_retention = read_setting('hosts_retention', 30);
$remove_invocation = read_setting('remove_invocation', 0);
$email_fromname = read_setting('email_fromname', 'Ascender Ledger');
$email_from = read_setting('email_from', 'ledger@ascender.local');

$base_url = read_setting('base_url', (isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] . "://" : '') . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : ''));
$smtp_server = read_setting('smtp_server', '');
$smtp_port = read_setting('smtp_port', '25');
$smtp_security = read_setting('smtp_security', '');
$smtp_username = read_setting('smtp_username', '');
$smtp_password = read_setting('smtp_password', '');

if (isset($_GET['action']) && $_GET['action'] == 'save') {
	$changes_retention = intval($_POST['changes_retention']);
	$facts_retention = intval($_POST['facts_retention']);
	$hosts_retention = intval($_POST['hosts_retention']);
	$remove_invocation = (isset($_POST['remove_invocation']) ? 1 : 0);

	if (isset($_POST['allowed_modules']) && is_array($_POST['allowed_modules'])) {
		$allowed_modules = array();
		foreach ($_POST['allowed_modules'] as $a) {
			$allowed_modules[] = strtolower(sql_clean_fact($a));
		}
		$allowed_modules = implode(',', $allowed_modules);
	} else {
		$allowed_modules = '';
	}
	db_execute_prepare('UPDATE `settings` SET `value` = ? WHERE `setting` = ?', array($allowed_modules, 'allowed_modules'));
	db_execute_prepare('UPDATE `settings` SET `value` = ? WHERE `setting` = ?', array($changes_retention, 'changes_retention'));
	db_execute_prepare('UPDATE `settings` SET `value` = ? WHERE `setting` = ?', array($facts_retention, 'facts_retention'));
	db_execute_prepare('UPDATE `settings` SET `value` = ? WHERE `setting` = ?', array($hosts_retention, 'hosts_retention'));

	$base_url       = sql_clean_url($_POST['base_url']);
	$email_fromname = sql_clean_name($_POST['email_fromname']);
	$email_from     = sql_clean_email($_POST['email_from']);
	$smtp_server    = sql_clean_hostname($_POST['smtp_server']);
	$smtp_port      = ($_POST['smtp_port'] != '' ? intval($_POST['smtp_port']) : '25');
	$smtp_security  = ($_POST['smtp_security'] != '' ? 'tls' : '');
	$smtp_username  = sql_clean_email($_POST['smtp_username']);
	if ($_POST['smtp_password'] != '') {
		$smtp_password  = $_POST['smtp_password'];
		db_execute_prepare('UPDATE `settings` SET `value` = ? WHERE `setting` = ?', array(secured_encrypt($smtp_password), 'smtp_password'));
	}

	db_execute_prepare('UPDATE `settings` SET `value` = ? WHERE `setting` = ?', array($base_url, 'base_url'));
	db_execute_prepare('UPDATE `settings` SET `value` = ? WHERE `setting` = ?', array($email_fromname, 'email_fromname'));
	db_execute_prepare('UPDATE `settings` SET `value` = ? WHERE `setting` = ?', array($email_from, 'email_from'));
	db_execute_prepare('UPDATE `settings` SET `value` = ? WHERE `setting` = ?', array($smtp_server, 'smtp_server'));
	db_execute_prepare('UPDATE `settings` SET `value` = ? WHERE `setting` = ?', array($smtp_port, 'smtp_port'));
	db_execute_prepare('UPDATE `settings` SET `value` = ? WHERE `setting` = ?', array($smtp_security, 'smtp_security'));
	db_execute_prepare('UPDATE `settings` SET `value` = ? WHERE `setting` = ?', array($smtp_username, 'smtp_username'));

	$remove_invocation = (isset($_POST['remove_invocation']) ? 1 : 0);
	db_execute_prepare('UPDATE `settings` SET `value` = ? WHERE `setting` = ?', array($remove_invocation, 'remove_invocation'));

	$disable_email = (isset($_POST['disable_email']) ? 1 : 0);
	db_execute_prepare('UPDATE `settings` SET `value` = ? WHERE `setting` = ?', array($disable_email, 'disable_email'));

	Header("Location: /settings/\n\n");
	exit;
}

$settings = array();
$set      = db_fetch_assocs('SELECT * FROM `settings`');

foreach ($set as $s) {
	$settings[$s['setting']] = $s['value'];
}

$default_modules = array('gather_facts', 'ansible.builtin.package_facts', 'ansible.builtin.service_facts');

echo $twig->render('settings.html', array_merge($twigarr, array('settings' => $settings, 'default_modules' => $default_modules)));

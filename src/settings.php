<?php

include_once('includes/global.php');

if (empty($account['super'])) {
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
$require_auth_token = read_setting('require_auth_token', '0');
if (trim((string) read_setting('auth_token', '')) == '') {
	write_setting('auth_token', generate_auth_token());
}

// Mail
$email_fromname = read_setting('email_fromname', 'Ascender Ledger');
$email_from = read_setting('email_from', 'ledger@ascender.local');
$smtp_server = read_setting('smtp_server', '');
$smtp_port = read_setting('smtp_port', '25');
$smtp_security = read_setting('smtp_security', '');
$smtp_username = read_setting('smtp_username', '');
$smtp_password = read_setting('smtp_password', '');
$smtp_verify_cert = read_setting('smtp_verify_cert', '1');

$retention_settings = array('hosts_retention', 'facts_retention', 'changes_retention', 'packages_retention');

/*
	Each form saves exactly its own fields.  Switches are saved whether or not they were
	posted, since a browser leaves an unchecked checkbox out of the form entirely.
*/
if (isset($_GET['action']) && $_GET['action'] == 'save' && $_SERVER['REQUEST_METHOD'] == 'POST') {
	$form = (isset($_POST['form']) ? $_POST['form'] : '');

	if ($form == 'general') {
		$url = (isset($_POST['base_url']) && is_string($_POST['base_url']) ? rtrim(trim($_POST['base_url']), '/') : '');
		if ($url == '' || valid_http_url($url)) {
			write_setting('base_url', $url);
		}

		write_setting('remove_invocation', (isset($_POST['remove_invocation']) ? 1 : 0));
		write_setting('require_auth_token', (isset($_POST['require_auth_token']) ? '1' : '0'));

		$modules = array();
		if (isset($_POST['allowed_modules']) && is_array($_POST['allowed_modules'])) {
			foreach ($_POST['allowed_modules'] as $a) {
				$a = strtolower(sql_clean_fact((string) $a));
				if ($a != '' && !in_array($a, $modules)) {
					$modules[] = $a;
				}
			}
		}
		write_setting('allowed_modules', implode(',', $modules));

		if (isset($_POST['regenerate_auth_token']) && $_POST['regenerate_auth_token'] === '1') {
			write_setting('auth_token', generate_auth_token());
			// No server has the new token yet; each shows Valid again once it sends it
			db_execute('UPDATE `servers` SET `auth_ok` = 0');
		}

		Header("Location: /settings/general/?saved=1\n\n");
		exit;
	}

	if ($form == 'visual') {
		if (isset($_POST['export_limit']) && in_array(intval($_POST['export_limit']), $export_limits, true)) {
			write_setting('export_limit', intval($_POST['export_limit']));
		}
		if (isset($_POST['callback_limit']) && in_array(intval($_POST['callback_limit']), $callback_limits, true)) {
			write_setting('callback_limit', intval($_POST['callback_limit']));
		}

		Header("Location: /settings/visual/?saved=1\n\n");
		exit;
	}

	if ($form == 'maint') {
		foreach ($retention_settings as $f) {
			if (isset($_POST[$f])) {
				$v = intval($_POST[$f]);
				if ($v >= 30 && $v <= 360 && $v % 30 == 0) {
					write_setting($f, $v);
				}
			}
		}

		Header("Location: /settings/maint/?saved=1\n\n");
		exit;
	}

	if ($form == 'mail') {
		write_setting('disable_email', (isset($_POST['disable_email']) ? 1 : 0));
		write_setting('email_fromname', sql_clean_name(post_str('email_fromname')));
		write_setting('email_from', sql_clean_email(post_str('email_from')));
		write_setting('smtp_server', sql_clean_hostname(post_str('smtp_server')));
		write_setting('smtp_port', (isset($_POST['smtp_port']) && $_POST['smtp_port'] != '' ? intval($_POST['smtp_port']) : ''));
		write_setting('smtp_security', (isset($_POST['smtp_security']) && $_POST['smtp_security'] != '' ? 'tls' : ''));
		write_setting('smtp_verify_cert', (isset($_POST['smtp_verify_cert']) ? '1' : '0'));
		write_setting('smtp_username', sql_clean_email(post_str('smtp_username')));

		// A blank password field keeps the saved password; Clear removes it
		if (isset($_POST['smtp_password_clear'])) {
			write_setting('smtp_password', '');
		} elseif (isset($_POST['smtp_password']) && is_string($_POST['smtp_password']) && $_POST['smtp_password'] != '') {
			write_setting('smtp_password', secured_encrypt($_POST['smtp_password']));
		}

		Header("Location: /settings/mail/?saved=1\n\n");
		exit;
	}

	Header("Location: /settings/\n\n");
	exit;
}

$settings = array();
$set      = db_fetch_assocs('SELECT * FROM `settings`');

foreach ($set as $s) {
	$settings[$s['setting']] = $s['value'];
}
// Never sent back to the browser
$settings['smtp_password_saved'] = ($settings['smtp_password'] != '');
unset($settings['smtp_password']);

$default_modules = array('gather_facts');
$saved = isset($_GET['saved']);

if (isset($_GET['action']) && $_GET['action'] == 'general') {
	echo $twig->render('settings_general.html', array_merge($twigarr, array('settings' => $settings, 'default_modules' => $default_modules, 'saved' => $saved)));
	exit;
}

if (isset($_GET['action']) && $_GET['action'] == 'visual') {
	echo $twig->render('settings_visual.html', array_merge($twigarr, array('saved' => $saved, 'export_limits' => $export_limits, 'callback_limits' => $callback_limits,
		'export_limit' => export_limit(), 'callback_limit' => callback_limit())));
	exit;
}

if (isset($_GET['action']) && $_GET['action'] == 'maint') {
	echo $twig->render('settings_maint.html', array_merge($twigarr, array('settings' => $settings, 'saved' => $saved)));
	exit;
}

if (isset($_GET['action']) && $_GET['action'] == 'mail') {
	echo $twig->render('settings_mail.html', array_merge($twigarr, array('settings' => $settings, 'saved' => $saved)));
	exit;
}

echo $twig->render('settings.html', array_merge($twigarr, array('settings' => $settings, 'default_modules' => $default_modules)));

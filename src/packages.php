<?php

include_once('includes/global.php');

if (empty($account['view_facts']) && empty($account['super'])) {
	header("Location: /\n\n");
	exit;
}

// The filter menus are only needed to draw the page, not for each page of table rows
$arches = dt_is_request() ? array() : reindex_col(db_fetch_assocs("SELECT DISTINCT `arch` FROM `packages` ORDER BY `arch` ASC"), 'arch');

if (isset($_GET['clear'])) {
	if (in_array($_GET['clear'], array('host', 'package', 'arch'), true)) {
		unset($_SESSION['packages_' . $_GET['clear']]);
	}
	header("Location: /packages/");
	exit;
}

if (isset($_GET['host'])) {
	if (host_name($_GET['host']) != '') {
		$_SESSION['packages_host'] = intval($_GET['host']);
	} else {
		unset($_SESSION['packages_host']);
	}
	header("Location: /packages/");
	exit;
}

if (isset($_GET['package']) && is_string($_GET['package'])) {
	$p = sql_clean_package($_GET['package']);
	if ($p != '') {
		$_SESSION['packages_package'] = $p;
	} else {
		unset($_SESSION['packages_package']);
	}
	header("Location: /packages/");
	exit;
}

if (isset($_GET['arch']) && is_string($_GET['arch'])) {
	if (in_array($_GET['arch'], $arches, true)) {
		$_SESSION['packages_arch'] = $_GET['arch'];
	} else {
		unset($_SESSION['packages_arch']);
	}
	header("Location: /packages/");
	exit;
}

$host    = (isset($_SESSION['packages_host']) ? intval($_SESSION['packages_host']) : 0);
$package = (isset($_SESSION['packages_package']) ? $_SESSION['packages_package'] : '');
$arch    = (isset($_SESSION['packages_arch']) ? $_SESSION['packages_arch'] : '');

if (dt_is_request()) {
	$where = array();
	$params = array();

	if ($host) {
		$where[] = '`p`.`host` = ?';
		$params[] = $host;
	}
	if ($package != '') {
		$where[] = '`p`.`name` = ?';
		$params[] = $package;
	}
	if ($arch != '') {
		$where[] = '`p`.`arch` = ?';
		$params[] = $arch;
	}

	dt_serve(
		'`p`.`host`, `h`.`hostname`, `p`.`name`, `p`.`version`, `p`.`release`, `p`.`epoch`, `p`.`arch`, `p`.`source`',
		'FROM `packages` AS `p` LEFT JOIN `hosts` AS `h` ON `h`.`id` = `p`.`host`',
		$where,
		$params,
		array(
			0 => array('`h`.`hostname`', '`p`.`name`'),
			1 => array('`p`.`name`', '`p`.`host`'),
			2 => array('`p`.`version`'),
			3 => array('`p`.`release`'),
			4 => array('`p`.`epoch`'),
			5 => array('`p`.`arch`'),
			6 => array('`p`.`source`'),
		),
		'`p`.`name`, `p`.`host`',
		array('`h`.`hostname`', '`p`.`name`', '`p`.`version`', '`p`.`release`'),
		function ($r) {
			return array(
				'host_id'  => intval($r['host']),
				'hostname' => $r['hostname'],
				'name'     => $r['name'],
				'version'  => $r['version'],
				'release'  => $r['release'],
				'epoch'    => $r['epoch'],
				'arch'     => $r['arch'],
				'source'   => $r['source'],
			);
		},
		array('`p`.`host`', '`p`.`name`', '`p`.`version`', '`p`.`release`', '`p`.`epoch`', '`p`.`arch`')
	);
}

echo $twig->render('packages.html', array_merge($twigarr, array(
	'arches'   => $arches,
	'arch'     => $arch,
	'host'     => $host,
	'hostname' => host_name($host),
	'package'  => $package,
)));

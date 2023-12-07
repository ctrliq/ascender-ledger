<?php

include_once('includes/global.php');

if (!$account['view_facts'] && !$account['super']) {
    header("Location: /\n\n");
    exit;
}

$hosts = reindex_arr_by_id(db_fetch_assocs("SELECT * FROM `hosts` ORDER BY `hostname`"));
$upackages = reindex_col(db_fetch_assocs("SELECT DISTINCT `name` FROM `packages` ORDER BY `name` ASC"), 'name');
$arches = reindex_col(db_fetch_assocs("SELECT DISTINCT `arch` FROM `packages` ORDER BY `arch` ASC"), 'arch');

$filters = array();
$host = '';
$package = '';
$arch = '';

if (isset($_GET['clear'])) {
	unset($_SESSION['packages_host']);
	unset($_SESSION['packages_package']);
	unset($_SESSION['packages_arch']);
	header("Location: /packages/");
	exit;
}

if (isset($_GET['host'])) {
	if (intval($_GET['host']) == $_GET['host'] && isset($hosts[$_GET['host']])) {
		$_SESSION['packages_host'] = intval($_GET['host']);
	} else {
		unset($_SESSION['packages_host']);
	}
	header("Location: /packages/");
	exit;
}

if (isset($_GET['package'])) {
	if (in_array(sql_clean_package($_GET['package']), $upackages)) {
		$_SESSION['packages_package'] = sql_clean_package($_GET['package']);
	} else {
		unset($_SESSION['packages_package']);
	}
	header("Location: /packages/");
	exit;
}

if (isset($_GET['arch'])) {
	if (in_array(sql_clean_package($_GET['arch']), $arches)) {
		$_SESSION['packages_arch'] = sql_clean_package($_GET['arch']);
	} else {
		unset($_SESSION['packages_arch']);
	}
	header("Location: /packages/");
	exit;
}

// Create filters
if (isset($_SESSION['packages_host']) && $_SESSION['packages_host'] != '') {
	$filters[] = " `host` = " . intval($_SESSION['packages_host']);
	$host = intval($_SESSION['packages_host']);
}

if (isset($_SESSION['packages_package']) && $_SESSION['packages_package'] != '') {
	$filters[] = " `name` = '" . sql_clean_package($_SESSION['packages_package']) . "'";
	$package = sql_clean_fact($_SESSION['packages_package']);
}

if (isset($_SESSION['packages_arch']) && $_SESSION['packages_arch'] != '') {
	$filters[] = " `arch` = '" . sql_clean_package($_SESSION['packages_arch']) . "'";
	$arch = sql_clean_fact($_SESSION['packages_arch']);
}

$h = array();
foreach ($hosts as $h2) {
	$h[$h2['id']] = $h2['hostname'];
}
if (!empty($filters)) {
	$filters = "WHERE " . implode(' AND ', $filters);
} else {
	$filters = '';
}

$packages = db_fetch_assocs("SELECT * FROM `packages` $filters ORDER BY `host`, `name` ASC" . ($filters == '' ? ' LIMIT 0,500' : ''));

echo $twig->render('packages.html', array_merge($twigarr, array('arches' => $arches, 'arch' => $arch, 'upackages' => $upackages, 'hosts' => $h, 'host' => $host, 'package' => $package, 'packages' => $packages)));

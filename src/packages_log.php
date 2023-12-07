<?php

include_once('includes/global.php');

if (!$account['view_facts'] && !$account['super']) {
    header("Location: /\n\n");
    exit;
}

$hosts = reindex_arr_by_id(db_fetch_assocs("SELECT * FROM `hosts` ORDER BY `hostname`"));
$upackages = reindex_col(db_fetch_assocs("SELECT DISTINCT `name` FROM `packages_log` ORDER BY `name` ASC"), 'name');
$statuses = array(1 => 'Added', 2 => 'Removed');

$filters = array();
$host = '';
$package = '';
$status = '';

if (isset($_GET['clear'])) {
	unset($_SESSION['packages_host']);
	unset($_SESSION['packages_package']);
	unset($_SESSION['packages_status']);
	header("Location: /packages/log/");
	exit;
}

if (isset($_GET['host'])) {
	if (intval($_GET['host']) == $_GET['host'] && isset($hosts[$_GET['host']])) {
		$_SESSION['packages_host'] = intval($_GET['host']);
	} else {
		unset($_SESSION['packages_host']);
	}
	header("Location: /packages/log/");
	exit;
}

if (isset($_GET['package'])) {
	if (in_array(sql_clean_package($_GET['package']), $upackages)) {
		$_SESSION['packages_package'] = sql_clean_package($_GET['package']);
	} else {
		unset($_SESSION['packages_package']);
	}
	header("Location: /packages/log/");
	exit;
}

if (isset($_GET['status'])) {
	if (isset($statuses[intval($_GET['status'])])) {
		$_SESSION['packages_status'] = intval($_GET['status']);
	} else {
		unset($_SESSION['packages_status']);
	}
	header("Location: /packages/log/");
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

if (isset($_SESSION['packages_status']) && $_SESSION['packages_status'] != '') {
	$filters[] = " `status` = '" . $_SESSION['packages_status'] . "'";
	$status = $_SESSION['packages_status'];
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

$packages = db_fetch_assocs("SELECT * FROM `packages_log` $filters ORDER BY `time` DESC, `host`, `name` ASC, `status` ASC" . ($filters == '' ? ' LIMIT 0,500' : ''));
echo $twig->render('packages_log.html', array_merge($twigarr, array('statuses' => $statuses, 'status' => $status, 'upackages' => $upackages, 'hosts' => $h, 'host' => $host, 'package' => $package, 'packages' => $packages)));

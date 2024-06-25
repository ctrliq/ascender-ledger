<?php

include_once('includes/global.php');

if (!$account['view_facts'] && !$account['super']) {
    header("Location: /\n\n");
    exit;
}

$hosts = reindex_arr_by_id(db_fetch_assocs("SELECT * FROM `hosts` ORDER BY `hostname`"));
$uservices = reindex_col(db_fetch_assocs("SELECT DISTINCT `name` FROM `services` ORDER BY `name` ASC"), 'name');
$states = reindex_col(db_fetch_assocs("SELECT DISTINCT `state` FROM `services` ORDER BY `state` ASC"), 'state');
$statuses = reindex_col(db_fetch_assocs("SELECT DISTINCT `status` FROM `services` ORDER BY `status` ASC"), 'status');

$filters = array();
$host = '';
$service = '';
$state = '';
$status = '';

if (isset($_GET['clear'])) {
	if ($_GET['clear'] == 'host') {
		unset($_SESSION['services_host']);
	}
	if ($_GET['clear'] == 'service') {
		unset($_SESSION['services_service']);
	}
	if ($_GET['clear'] == 'state') {
		unset($_SESSION['services_state']);
	}
	if ($_GET['clear'] == 'status') {
		unset($_SESSION['services_status']);
	}
}

if (isset($_GET['host'])) {
	if (intval($_GET['host']) == $_GET['host'] && isset($hosts[$_GET['host']])) {
		$_SESSION['services_host'] = intval($_GET['host']);
	} else {
		unset($_SESSION['services_host']);
	}
	header("Location: /services/");
	exit;
}

if (isset($_GET['service'])) {
	if (in_array($_GET['service'], $uservices)) {
		$_SESSION['services_service'] = sql_clean_service_name($_GET['service']);
	} else {
		unset($_SESSION['services_service']);
	}
	header("Location: /services/");
	exit;
}

if (isset($_GET['state'])) {
	if (in_array($_GET['state'], $states)) {
		$_SESSION['services_state'] = sql_clean_fact($_GET['state']);
	} else {
		unset($_SESSION['services_state']);
	}
	header("Location: /services/");
	exit;
}

if (isset($_GET['status'])) {
	if (in_array($_GET['status'], $statuses)) {
		$_SESSION['services_status'] = sql_clean_fact($_GET['status']);
	} else {
		unset($_SESSION['services_status']);
	}
	header("Location: /services/");
	exit;
}


// Create filters
if (isset($_SESSION['services_host']) && $_SESSION['services_host'] != '') {
	$filters[] = " `host` = " . intval($_SESSION['services_host']);
	$host = intval($_SESSION['services_host']);
}

if (isset($_SESSION['services_service']) && $_SESSION['services_service'] != '') {
	$filters[] = " `name` = '" . sql_clean_service_name($_SESSION['services_service']) . "'";
	$service = sql_clean_service_name($_SESSION['services_service']);
}

if (isset($_SESSION['services_state']) && $_SESSION['services_state'] != '') {
	$filters[] = " `state` = '" . sql_clean_fact($_SESSION['services_state']) . "'";
	$state = sql_clean_fact($_SESSION['services_state']);
}

if (isset($_SESSION['services_status']) && $_SESSION['services_status'] != '') {
	$filters[] = " `status` = '" . sql_clean_fact($_SESSION['services_status']) . "'";
	$status = sql_clean_fact($_SESSION['services_status']);
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

$services = db_fetch_assocs("SELECT * FROM `services` $filters ORDER BY `host`, `name` ASC" . ($filters == '' ? ' LIMIT 0,500' : ''));

echo $twig->render('services.html', array_merge($twigarr, array('statuses' => $statuses, 'status' => $status, 'states' => $states, 'state' => $state, 'uservices' => $uservices, 'hosts' => $h, 'host' => $host, 'service' => $service, 'services' => $services)));

<?php

include_once('includes/global.php');

if (!check_perm('services')) {
	header("Location: /\n\n");
	exit;
}

// The filter menus are only needed to draw the page, not for each page of table rows
$states   = dt_is_request() ? array() : reindex_col(db_fetch_assocs("SELECT DISTINCT `state` FROM `services` ORDER BY `state` ASC"), 'state');
$statuses = dt_is_request() ? array() : reindex_col(db_fetch_assocs("SELECT DISTINCT `status` FROM `services` ORDER BY `status` ASC"), 'status');

if (isset($_GET['clear'])) {
	$names = array('host', 'service', 'state', 'status');
	if (in_array($_GET['clear'], $names, true)) {
		unset($_SESSION['services_' . $_GET['clear']]);
	} elseif ($_GET['clear'] === 'all') {
		foreach ($names as $f) {
			unset($_SESSION['services_' . $f]);
		}
	}
	header("Location: /services/");
	exit;
}

if (isset($_GET['host'])) {
	if (host_name($_GET['host']) != '') {
		$_SESSION['services_host'] = intval($_GET['host']);
	} else {
		unset($_SESSION['services_host']);
	}
	header("Location: /services/");
	exit;
}

if (isset($_GET['service']) && is_string($_GET['service'])) {
	$s = sql_clean_service_name($_GET['service']);
	if ($s != '') {
		$_SESSION['services_service'] = $s;
	} else {
		unset($_SESSION['services_service']);
	}
	header("Location: /services/");
	exit;
}

if (isset($_GET['state']) && is_string($_GET['state'])) {
	if (in_array($_GET['state'], $states, true)) {
		$_SESSION['services_state'] = $_GET['state'];
	} else {
		unset($_SESSION['services_state']);
	}
	header("Location: /services/");
	exit;
}

if (isset($_GET['status']) && is_string($_GET['status'])) {
	if (in_array($_GET['status'], $statuses, true)) {
		$_SESSION['services_status'] = $_GET['status'];
	} else {
		unset($_SESSION['services_status']);
	}
	header("Location: /services/");
	exit;
}

$host    = (isset($_SESSION['services_host']) ? intval($_SESSION['services_host']) : 0);
$service = (isset($_SESSION['services_service']) ? $_SESSION['services_service'] : '');
$state   = (isset($_SESSION['services_state']) ? $_SESSION['services_state'] : '');
$status  = (isset($_SESSION['services_status']) ? $_SESSION['services_status'] : '');

if (dt_is_request()) {
	$where = array();
	$params = array();

	if ($host) {
		$where[] = '`s`.`host` = ?';
		$params[] = $host;
	}
	if ($service != '') {
		$where[] = '`s`.`name` = ?';
		$params[] = $service;
	}
	if ($state != '') {
		$where[] = '`s`.`state` = ?';
		$params[] = $state;
	}
	if ($status != '') {
		$where[] = '`s`.`status` = ?';
		$params[] = $status;
	}

	dt_serve(
		'`s`.`host`, `h`.`hostname`, `s`.`name`, `s`.`state`, `s`.`status`, `s`.`source`',
		'FROM `services` AS `s` LEFT JOIN `hosts` AS `h` ON `h`.`id` = `s`.`host`',
		$where,
		$params,
		array(
			0 => array('`h`.`hostname`', '`s`.`name`'),
			1 => array('`s`.`name`', '`s`.`host`'),
			2 => array('`s`.`state`'),
			3 => array('`s`.`status`'),
			4 => array('`s`.`source`'),
		),
		'`s`.`host`, `s`.`name`',
		array('`h`.`hostname`', '`s`.`name`'),
		function ($r) {
			return array(
				'host_id'  => intval($r['host']),
				'hostname' => $r['hostname'],
				'name'     => $r['name'],
				'state'    => ucwords((string) $r['state']),
				'status'   => ucwords((string) $r['status']),
				'source'   => ucwords((string) $r['source']),
			);
		},
		array('`s`.`host`', '`s`.`name`')
	);
}

echo $twig->render('services.html', array_merge($twigarr, array(
	'statuses' => $statuses,
	'status'   => $status,
	'states'   => $states,
	'state'    => $state,
	'host'     => $host,
	'hostname' => host_name($host),
	'service'  => $service,
)));

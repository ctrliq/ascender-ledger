<?php

include_once('includes/global.php');

if (!check_perm('facts')) {
	header("Location: /\n\n");
	exit;
}

/*
	A fleet can store millions of facts under hundreds of thousands of names, so neither the
	facts nor the list of names are ever loaded whole.  The table is filled a page at a time by
	the DataTables request below, and the host and fact pickers search the server as you type.
*/

// The filter menus are only needed to draw the page, not for each page of table rows
$types = dt_is_request() ? array() : reindex_col(db_fetch_assocs("SELECT DISTINCT `type` FROM `facts` ORDER BY `type` ASC"), 'type');

$names = array('host', 'fact', 'type', 'search');
$saved = (dt_is_request() ? array() : saved_filters('facts', $names));

if (isset($_GET['clear'])) {
	if (in_array($_GET['clear'], $names, true)) {
		unset($_SESSION['facts_' . $_GET['clear']]);
	} elseif ($_GET['clear'] === 'all') {
		foreach ($names as $f) {
			unset($_SESSION['facts_' . $f]);
		}
	}
	header("Location: /facts/");
	exit;
}

if (isset($_GET['host'])) {
	if (host_name($_GET['host']) != '') {
		$_SESSION['facts_host'] = intval($_GET['host']);
	} else {
		unset($_SESSION['facts_host']);
	}
	header("Location: /facts/");
	exit;
}

if (isset($_GET['fact']) && is_string($_GET['fact'])) {
	$f = sql_clean_fact($_GET['fact']);
	if ($f != '') {
		$_SESSION['facts_fact'] = $f;
	} else {
		unset($_SESSION['facts_fact']);
	}
	header("Location: /facts/");
	exit;
}

if (isset($_GET['type']) && is_string($_GET['type'])) {
	if (in_array($_GET['type'], $types, true)) {
		$_SESSION['facts_type'] = $_GET['type'];
	} else {
		unset($_SESSION['facts_type']);
	}
	header("Location: /facts/");
	exit;
}

if (isset($_GET['search']) && is_string($_GET['search']) && !dt_is_request()) {
	$s = trim(substr($_GET['search'], 0, 128));
	if ($s == '') {
		unset($_SESSION['facts_search']);
	} else {
		$_SESSION['facts_search'] = $s;
	}
	header("Location: /facts/");
	exit;
}

$host   = (isset($_SESSION['facts_host']) ? intval($_SESSION['facts_host']) : 0);
$fact   = (isset($_SESSION['facts_fact']) ? $_SESSION['facts_fact'] : '');
$type   = (isset($_SESSION['facts_type']) ? $_SESSION['facts_type'] : '');
$search = (isset($_SESSION['facts_search']) ? $_SESSION['facts_search'] : '');

if (dt_is_request()) {
	$where = array();
	$params = array();

	if ($host) {
		$where[] = '`f`.`host` = ?';
		$params[] = $host;
	}
	if ($fact != '') {
		$where[] = '`f`.`fact` = ?';
		$params[] = $fact;
	}
	if ($type != '') {
		$where[] = '`f`.`type` = ?';
		$params[] = $type;
	}
	if ($search != '') {
		foreach (preg_split('/\s+/', $search) as $s) {
			if ($s != '') {
				$where[] = '`f`.`data` LIKE ?';
				$params[] = '%' . dt_like_escape($s) . '%';
			}
		}
	}

	dt_serve(
		'`f`.`host`, `h`.`hostname`, `f`.`fact`, `f`.`data`, `f`.`type`',
		'FROM `facts` AS `f` LEFT JOIN `hosts` AS `h` ON `h`.`id` = `f`.`host`',
		$where,
		$params,
		array(
			0 => array('`h`.`hostname`', '`f`.`fact`'),
			1 => array('`f`.`fact`', '`f`.`host`'),
			3 => array('`f`.`type`', '`f`.`fact`'),
		),
		'`f`.`fact`, `f`.`host`',
		array('`h`.`hostname`', '`f`.`fact`', '`f`.`data`'),
		function ($r) {
			return array(
				'host_id'  => intval($r['host']),
				'hostname' => $r['hostname'],
				'fact'     => $r['fact'],
				'data'     => $r['data'],
				'type'     => $r['type'],
			);
		},
		array('`f`.`host`', '`f`.`fact`')
	);
}

echo $twig->render('facts.html', array_merge($twigarr, array(
	'types'    => $types,
	'type'     => $type,
	'host'     => $host,
	'hostname' => host_name($host),
	'fact'     => $fact,
	'search'   => $search,
	'saved'    => $saved,
)));

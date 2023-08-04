<?php

include_once('includes/global.php');

if (!$account['view_facts'] && !$account['super']) {
    header("Location: /\n\n");
    exit;
}

$hosts = reindex_arr_by_id(db_fetch_assocs("SELECT * FROM `hosts` ORDER BY `hostname`"));
$ufacts = reindex_col(db_fetch_assocs("SELECT DISTINCT `fact` FROM `facts` ORDER BY `fact` ASC"), 'fact');
$types = reindex_col(db_fetch_assocs("SELECT DISTINCT `type` FROM `facts` ORDER BY `type` ASC"), 'type');

$filters = array();
$host = '';
$fact = '';
$type = '';

if (isset($_GET['clear'])) {
	unset($_SESSION['facts_host']);
	unset($_SESSION['facts_fact']);
	unset($_SESSION['facts_type']);
	header("Location: /facts/");
	exit;
}

if (isset($_GET['host'])) {
	if (intval($_GET['host']) == $_GET['host'] && isset($hosts[$_GET['host']])) {
		$_SESSION['facts_host'] = intval($_GET['host']);
	} else {
		unset($_SESSION['facts_host']);
	}
	header("Location: /facts/");
	exit;
}

if (isset($_GET['fact'])) {
	if (in_array($_GET['fact'], $ufacts)) {
		$_SESSION['facts_fact'] = sql_clean_fact($_GET['fact']);
	} else {
		unset($_SESSION['facts_fact']);
	}
	header("Location: /facts/");
	exit;
}

if (isset($_GET['type'])) {
	if (in_array($_GET['type'], $types)) {
		$_SESSION['facts_type'] = sql_clean_fact($_GET['type']);
	} else {
		unset($_SESSION['facts_type']);
	}
	header("Location: /facts/");
	exit;
}

// Create filters
if (isset($_SESSION['facts_host']) && $_SESSION['facts_host'] != '') {
	$filters[] = " `host` = " . intval($_SESSION['facts_host']);
	$host = intval($_SESSION['facts_host']);
}

if (isset($_SESSION['facts_fact']) && $_SESSION['facts_fact'] != '') {
	$filters[] = " `fact` = '" . sql_clean_fact($_SESSION['facts_fact']) . "'";
	$fact = sql_clean_fact($_SESSION['facts_fact']);
}

if (isset($_SESSION['facts_type']) && $_SESSION['facts_type'] != '') {
	$filters[] = " `type` = '" . sql_clean_fact($_SESSION['facts_type']) . "'";
	$type = sql_clean_fact($_SESSION['facts_type']);
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

$facts = db_fetch_assocs("SELECT * FROM `facts` $filters ORDER BY `fact` ASC" . ($filters == '' ? ' LIMIT 0,500' : ''));

echo $twig->render('facts.html', array_merge($twigarr, array('types' => $types, 'type' => $type, 'facts' => $facts, 'ufacts' => $ufacts, 'hosts' => $h, 'host' => $host, 'fact' => $fact)));

<?php

/*
	Search-as-you-type for the host, fact, package and service pickers (select2's ajax format).

	A large fleet can have hundreds of thousands of distinct fact names, far too many to put in
	a page, so the pickers ask for one page of matches at a time instead.
*/

include_once('includes/global.php');

define('LOOKUP_PAGE', 25);

$what = (isset($_GET['what']) && is_string($_GET['what']) ? $_GET['what'] : '');
$q    = (isset($_GET['q']) && is_string($_GET['q']) ? trim(substr($_GET['q'], 0, 128)) : '');
$page = (isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1);

$view_facts = (!empty($account['view_facts']) || !empty($account['super']));

$like   = '%' . dt_like_escape($q) . '%';
$offset = ($page - 1) * LOOKUP_PAGE;
$limit  = 'LIMIT ' . $offset . ', ' . (LOOKUP_PAGE + 1);
$rows   = array();

switch ($what) {
	case 'hosts':
		if (!$view_facts && empty($account['view_changes'])) {
			break;
		}
		$rows = db_fetch_assocs_prepare("SELECT `id`, `hostname` AS `text` FROM `hosts` WHERE `hostname` LIKE ? ORDER BY `hostname` $limit", array($like));
		break;

	case 'facts':
		if (!$view_facts) {
			break;
		}
		if (db_fetch_cell('SELECT 1 AS `e` FROM `fact_names` LIMIT 1', 'e')) {
			$rows = db_fetch_assocs_prepare("SELECT `fact` AS `id`, `fact` AS `text` FROM `fact_names` WHERE `fact` LIKE ? ORDER BY `fact` $limit", array($like));
		} else {
			// The cache is filled by the minute cron; until it has run, ask the facts table
			$rows = db_fetch_assocs_prepare("SELECT DISTINCT `fact` AS `id`, `fact` AS `text` FROM `facts` WHERE `fact` LIKE ? ORDER BY `fact` $limit", array($like));
		}
		break;

	case 'packages':
		if ($view_facts) {
			$rows = db_fetch_assocs_prepare("SELECT DISTINCT `name` AS `id`, `name` AS `text` FROM `packages` WHERE `name` LIKE ? ORDER BY `name` $limit", array($like));
		}
		break;

	case 'services':
		if ($view_facts) {
			$rows = db_fetch_assocs_prepare("SELECT DISTINCT `name` AS `id`, `name` AS `text` FROM `services` WHERE `name` LIKE ? ORDER BY `name` $limit", array($like));
		}
		break;
}

$more = (count($rows) > LOOKUP_PAGE);
if ($more) {
	array_pop($rows);
}

header('Content-Type: application/json');
header('Cache-Control: no-store');
print json_encode(array('results' => $rows, 'pagination' => array('more' => $more)), JSON_INVALID_UTF8_SUBSTITUTE);

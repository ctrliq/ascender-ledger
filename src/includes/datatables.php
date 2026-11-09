<?php

/*
	Server side processing for the DataTables on the facts, packages, services and changes
	pages.  Those tables can hold millions of rows on a large fleet, so the browser is only
	ever sent the page it is showing, and every query is bounded.

	Column names and sort expressions come from the page's own whitelist, never from the
	request; the request only picks among them by index.
*/

define('DT_MAX_LENGTH', 500);
define('DT_COUNT_CAP', 100000);

/*
	True when the request is a DataTables data request rather than a page load
*/
function dt_is_request() {
	return isset($_GET['draw']);
}

/*
	Escapes the LIKE wildcards in a search term, so a search for "50%" means that text
*/
function dt_like_escape($s) {
	return str_replace(array('\\', '%', '_'), array('\\\\', '\\%', '\\_'), $s);
}

/*
	Serves one DataTables request and exits
	$select        the SELECT list
	$from          FROM and JOIN clauses
	$where         array of conditions, ANDed together, using ? placeholders
	$params        the values for those placeholders, in order
	$orders        column index => SQL to order by for that column (columns not listed cannot be sorted)
	$default_order SQL to order by when the request does not ask for an orderable column
	$search_cols   the columns the table's search box looks in
	$row           callback turning a result row into the array sent to the browser
*/
function dt_serve($select, $from, $where, $params, $orders, $default_order, $search_cols, $row) {
	$draw   = isset($_GET['draw']) ? intval($_GET['draw']) : 0;
	$start  = isset($_GET['start']) ? max(0, intval($_GET['start'])) : 0;
	$length = isset($_GET['length']) ? intval($_GET['length']) : 25;
	if ($length < 1 || $length > DT_MAX_LENGTH) {
		$length = 25;
	}
	if ($start > DT_COUNT_CAP) {
		$start = DT_COUNT_CAP;
	}

	$search = '';
	if (isset($_GET['search']['value']) && is_string($_GET['search']['value'])) {
		$search = trim(substr($_GET['search']['value'], 0, 200));
	}

	if ($search != '' && !empty($search_cols)) {
		foreach (preg_split('/\s+/', $search) as $term) {
			if ($term === '') {
				continue;
			}
			$or = array();
			foreach ($search_cols as $c) {
				$or[] = "$c LIKE ?";
				$params[] = '%' . dt_like_escape($term) . '%';
			}
			$where[] = '(' . implode(' OR ', $or) . ')';
		}
	}

	$sort = array();
	if (isset($_GET['order']) && is_array($_GET['order'])) {
		foreach ($_GET['order'] as $o) {
			if (!is_array($o) || !isset($o['column'])) {
				continue;
			}
			$c = intval($o['column']);
			if (isset($orders[$c])) {
				$dir = (isset($o['dir']) && is_string($o['dir']) && strtolower($o['dir']) == 'desc') ? 'DESC' : 'ASC';
				foreach ((array) $orders[$c] as $expr) {
					$sort[] = "$expr $dir";
				}
			}
		}
	}
	$order_sql = 'ORDER BY ' . (!empty($sort) ? implode(', ', $sort) : $default_order);

	$where_sql = (!empty($where) ? 'WHERE ' . implode(' AND ', $where) : '');

	list($count, $capped) = db_count_capped("$from $where_sql", $params, DT_COUNT_CAP);

	$rows = db_fetch_assocs_prepare("SELECT $select $from $where_sql $order_sql LIMIT $start, $length", $params);

	$data = array();
	foreach ($rows as $r) {
		$data[] = $row($r);
	}

	header('Content-Type: application/json');
	header('Cache-Control: no-store');
	print json_encode(array(
		'draw'            => $draw,
		'recordsTotal'    => $count,
		'recordsFiltered' => $count,
		'capped'          => $capped,
		'data'            => $data,
	), JSON_INVALID_UTF8_SUBSTITUTE);
	exit;
}

/*
	Looks up the host name for an id, for showing the active host filter
*/
function host_name($id) {
	$id = intval($id);
	if (!$id) {
		return '';
	}
	$name = db_fetch_cell_prepare('SELECT `hostname` FROM `hosts` WHERE `id` = ?', 'hostname', array($id));
	return ($name === false ? '' : $name);
}

/*
	True when a fact with this name is stored for any host
*/
function fact_exists($fact) {
	if (!is_string($fact) || $fact === '') {
		return false;
	}
	return (bool) db_fetch_cell_prepare('SELECT 1 AS `e` FROM `facts` WHERE `fact` = ? LIMIT 1', 'e', array($fact));
}

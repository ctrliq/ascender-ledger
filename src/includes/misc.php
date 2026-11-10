<?php

function stripUnwantedTagsAndAttrs($html_str){
	$xml = new DOMDocument();
  //Suppress warnings: proper error handling is needed
	libxml_use_internal_errors(false);
  //List the tags you want to allow here, NOTE you MUST allow html and body otherwise entire string will be cleared
	$allowed_tags = array("b", "br", "em", "i", "li", "ol", "u", "ul", "p");
  //List the attributes you want to allow here
	$allowed_attrs = array ();
	if (!strlen($html_str)){return false;}
	if ($xml->loadHTML($html_str, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD)){
	  foreach ($xml->getElementsByTagName("*") as $tag){
		if (!in_array($tag->tagName, $allowed_tags)){
		  $tag->parentNode->removeChild($tag);
		}else{
		  foreach ($tag->attributes as $attr){
			if (!in_array($attr->nodeName, $allowed_attrs)){
			  $tag->removeAttribute($attr->nodeName);
			}
		  }
		}
	  }
	}
	$d = $xml->saveHTML();
	return strip_tags($d);
}

/*
	Row totals for the dashboard.  Slow on a large fleet, which is why the minute cron runs it
	and stores the result.
*/
function dashboard_counts() {
	return array(
		'hosts'    => intval(db_fetch_cell('SELECT COUNT(*) AS `count` FROM `hosts`', 'count')),
		'facts'    => intval(db_fetch_cell('SELECT COUNT(*) AS `count` FROM `facts`', 'count')),
		'changes'  => intval(db_fetch_cell('SELECT COUNT(*) AS `count` FROM `changes`', 'count')),
		'packages' => intval(db_fetch_cell('SELECT COUNT(*) AS `count` FROM `packages`', 'count')),
		'time'     => time(),
	);
}

function random_str($length) {
	$keyspace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
	$str = '';
	$max = mb_strlen($keyspace, '8bit') - 1;
	for ($i = 0; $i < $length; ++$i) {
		$str .= $keyspace[random_int(0, $max)];
	}
	return $str;
}

function reindex_col($a, $c) {
	$d = array();

	if (!empty($a)) {
		foreach ($a as $b) {
			$d[] = $b[$c];
		}
	}

	return $d;
}

function reindex_arr_by_id($a) {
	$d = array();

	if (!empty($a)) {
		foreach ($a as $b) {
			$d[$b['id']] = $b;
		}
	}

	return $d;
}

function reindex_arr_by_col($a, $c) {
	$d = array();

	if (!empty($a)) {
		foreach ($a as $b) {
			$d[$b[$c]] = $b;
		}
	}

	return $d;
}

function reindex_arr_by_id_col($a, $c) {
	$d = array();

	if (!empty($a)) {
		foreach ($a as $b) {
			$d[$b['id']] = $b[$c];
		}
	}

	return $d;
}

function ppre($arr) {
	print "<pre>";
	print_r($arr);
	print "</pre>";
}

/*
	Turns the filters of a facts report into a query for the ids of the matching hosts.  Each
	filter joins the one fact it tests (host and fact are the facts table's primary key), so a
	host matches when it has every filtered fact with a value that passes.
*/
function build_filter($filters) {
	$sql = "SELECT `h`.`id` AS `host` FROM `hosts` AS `h`\n";
	$p = array();
	$c = 0;
	foreach ($filters as $f) {
		if (!isset($f['fact']) || !isset($f['compare']) || !isset($f['value'])) {
			continue;
		}
		$c++;
		$col = "`f$c`.`data`";
		$v = $f['value'];
		$num = (is_numeric($v) ? $v + 0 : $v);

		switch ($f['compare']) {
			case 'eq':
				$cond = "$col = ?";
				break;
			case 'ne':
				$cond = "$col != ?";
				break;
			case 'gt':
				$cond = "CAST($col AS SIGNED) > ?";
				$v = $num;
				break;
			case 'lt':
				$cond = "CAST($col AS SIGNED) < ?";
				$v = $num;
				break;
			case 'contains':
				$cond = "$col LIKE ?";
				$v = '%' . dt_like_escape($v) . '%';
				break;
			case 'starts':
				$cond = "$col LIKE ?";
				$v = dt_like_escape($v) . '%';
				break;
			case 'ends':
				$cond = "$col LIKE ?";
				$v = '%' . dt_like_escape($v);
				break;
			default:
				$c--;
				continue 2;
		}

		$sql .= "INNER JOIN `facts` AS `f$c` ON `f$c`.`host` = `h`.`id` AND `f$c`.`fact` = ? AND $cond\n";
		$p[] = $f['fact'];
		$p[] = $v;
	}

	return array($sql, $p);
}

function sql_placeholders($n) {
	return implode(', ', array_fill(0, max(1, $n), '?'));
}

/*
	The ON condition joining a change ($c) to its job ($j).  Job numbers are per Ascender
	server, so a job matches on number and server.  Jobs recorded before Ledger kept the server
	have none, and are used only when the change's server has no job with that number, so a
	change never joins to two jobs (and is never listed twice).
*/
function jobs_join_on($j = 'j', $c = 'c') {
	return "`$j`.`job` = `$c`.`job` AND (`$j`.`server` = `$c`.`server` OR (`$j`.`server` = '' AND NOT EXISTS (SELECT 1 FROM `jobs` AS `jx` WHERE `jx`.`job` = `$c`.`job` AND `jx`.`server` = `$c`.`server`)))";
}

/*
	Builds a facts report: one row per matching host, holding the value of each column.  A
	column made of several facts shows their values joined with commas.  Only the facts the
	columns use are read, a few hundred hosts at a time.
*/
function build_report ($id) {
	$id = intval($id);
	$report = new Report($id);
	$data = array();

	if (!$report->id || !is_array($report->columns)) {
		return $data;
	}

	$w = build_filter(is_array($report->filters) ? $report->filters : array());
	$hosts = reindex_col(db_fetch_assocs_prepare($w[0], $w[1]), 'host');

	$needed = array();
	foreach ($report->columns as $k) {
		foreach ((array) $k as $f) {
			$needed[$f] = true;
		}
	}
	$needed = array_keys($needed);

	if (empty($hosts) || empty($needed)) {
		return $data;
	}

	foreach (array_chunk($hosts, 500) as $chunk) {
		$values = array();
		$rows = db_fetch_assocs_prepare('SELECT `host`, `fact`, `data` FROM `facts`
			WHERE `host` IN (' . sql_placeholders(count($chunk)) . ') AND `fact` IN (' . sql_placeholders(count($needed)) . ')
			ORDER BY `host`, `fact`', array_merge($chunk, $needed));
		foreach ($rows as $r) {
			$values[$r['host']][$r['fact']] = $r['data'];
		}

		foreach ($chunk as $h) {
			if (!isset($values[$h])) {
				continue;
			}
			$row = array();
			$empty = true;
			foreach ($report->columns as $k) {
				if (is_array($k)) {
					$parts = array();
					foreach ($values[$h] as $fact => $v) {
						if (in_array($fact, $k) && $v != '') {
							$parts[] = $v;
						}
					}
					$cell = implode(', ', $parts);
				} else {
					$cell = (isset($values[$h][$k]) ? $values[$h][$k] : '');
				}
				if ($cell != '') {
					$empty = false;
				}
				$row[] = $cell;
			}
			if (!$empty) {
				$data[$h] = $row;
			}
		}
	}

	return $data;
}

/*
	The columns a changes report shows.  Unlike a facts report, where the columns are the facts
	the user picked, a changes report always shows the same fields the changes page lists, so the
	report is that page's table with its filters saved and schedulable.
*/
function changes_report_columns() {
	return array(
		'Time'         => 'time',
		'Host'         => 'hostname',
		'Job Template' => 'template',
		'Playbook'     => 'playbook',
		'Role'         => 'role',
		'Task'         => 'task',
		'Module'       => 'task_action',
	);
}

/*
	The fields a changes report can be filtered on.  These are the filter menus of the changes
	page, plus a time window, which is what makes a scheduled report useful: "the changes of the
	last 24 hours" rather than everything ever recorded.
*/
function changes_report_filter_fields() {
	return array(
		'host'      => 'Host',
		'template'  => 'Job Template',
		'playbook'  => 'Playbook',
		'role'      => 'Role',
		'module'    => 'Module',
		'type'      => 'Job Type',
		'inventory' => 'Inventory',
		'project'   => 'Project',
		'search'    => 'Search',
		'hours'     => 'Last Hours',
	);
}

/*
	The filter fields whose value is an id or a count rather than a name.
*/
function changes_report_numeric_filter_fields() {
	return array('host', 'template', 'inventory', 'project', 'hours');
}

/*
	Turns the filters of a changes report into a WHERE clause and its parameters.  The comparisons
	are the ones the changes page makes, with the values bound rather than interpolated.
*/
function build_changes_filter($filters) {
	$where = array();
	$p = array();

	foreach ($filters as $f) {
		if (!isset($f['field']) || !isset($f['value']) || !is_scalar($f['value'])) {
			continue;
		}

		switch ($f['field']) {
			case 'host':
				$where[] = '`changes`.`host` = ?';
				$p[] = intval($f['value']);
				break;
			case 'template':
				$where[] = '`jobs`.`job_template_id` = ?';
				$p[] = intval($f['value']);
				break;
			case 'playbook':
				$where[] = '`changes`.`playbook` = ?';
				$p[] = $f['value'];
				break;
			case 'role':
				$where[] = '`changes`.`role` = ?';
				$p[] = $f['value'];
				break;
			case 'module':
				$where[] = '`changes`.`task_action` = ?';
				$p[] = $f['value'];
				break;
			case 'type':
				$where[] = '`jobs`.`job_type` = ?';
				$p[] = $f['value'];
				break;
			case 'inventory':
				$where[] = '`jobs`.`inventory` LIKE ?';
				$p[] = '%-' . intval($f['value']);
				break;
			case 'project':
				$where[] = '`jobs`.`project` LIKE ?';
				$p[] = '%-' . intval($f['value']);
				break;
			case 'search':
				foreach (explode(' ', $f['value']) as $s) {
					if ($s == '') {
						continue;
					}
					$where[] = '(`changes`.`res` LIKE ? OR `changes`.`task_action` LIKE ? OR `changes`.`task` LIKE ? OR `changes`.`role` LIKE ? OR `changes`.`play` LIKE ?)';
					for ($i = 0; $i < 5; $i++) {
						$p[] = '%' . $s . '%';
					}
				}
				break;
			case 'hours':
				$where[] = '`changes`.`time` >= ?';
				$p[] = time() - (intval($f['value']) * 3600);
				break;
		}
	}

	if (empty($where)) {
		return array('', $p);
	}

	return array('WHERE ' . implode(' AND ', $where), $p);
}

/*
	Builds a changes report, returning the same shape as build_report: one row per change, each
	holding the cell values of changes_report_columns() in order, so the report and email
	templates render both kinds of report the same way.
*/
function build_changes_report($id, $limit = 1000) {
	$id = intval($id);
	$report = new Report($id);
	$data = array();

	if (!$report->id || $report->type != 'changes') {
		return $data;
	}

	$w = build_changes_filter($report->filters);

	$sql = "SELECT `changes`.`id`, `changes`.`time`, `changes`.`playbook`, `changes`.`role`, `changes`.`task`, `changes`.`task_action`,
					`hosts`.`hostname`, `jobs`.`name` as `template`
			FROM `changes`
			LEFT JOIN `jobs` ON " . jobs_join_on('jobs', 'changes') . "
			LEFT JOIN `hosts` ON `hosts`.`id` = `changes`.`host`
			" . $w[0] . "
			ORDER BY `changes`.`time` DESC
			LIMIT " . intval($limit);

	$changes = db_fetch_assocs_prepare($sql, $w[1]);

	foreach ($changes as $c) {
		$data[$c['id']] = array(
			date('m/d/Y H:i', $c['time']),
			$c['hostname'],
			$c['template'],
			$c['playbook'],
			$c['role'],
			$c['task'],
			$c['task_action'],
		);
	}

	return $data;
}

/*
	The values the filter menus of a changes report offer, built from the changes that were
	actually recorded, the same way the changes page builds its own menus.
*/
function changes_report_filter_options() {
	$templates = array();
	foreach (db_fetch_assocs("SELECT DISTINCT `j`.`job_template_id`, `j`.`name` FROM `jobs` AS `j` WHERE EXISTS (SELECT 1 FROM `changes` AS `c` WHERE `c`.`job` = `j`.`job` AND (`c`.`server` = `j`.`server` OR `j`.`server` = '')) ORDER BY `j`.`name` ASC") as $t) {
		if ($t['job_template_id']) {
			$templates[$t['job_template_id']] = $t['name'];
		}
	}

	$inventories = array();
	foreach (db_fetch_assocs("SELECT DISTINCT `j`.`inventory` FROM `jobs` AS `j` WHERE EXISTS (SELECT 1 FROM `changes` AS `c` WHERE `c`.`job` = `j`.`job` AND (`c`.`server` = `j`.`server` OR `j`.`server` = '')) ORDER BY `j`.`inventory` ASC") as $i) {
		$i = explode('-', $i['inventory']);
		$id = array_pop($i);
		if (intval($id)) {
			$inventories[$id] = implode('-', $i);
		}
	}

	$projects = array();
	foreach (db_fetch_assocs("SELECT DISTINCT `j`.`project` FROM `jobs` AS `j` WHERE EXISTS (SELECT 1 FROM `changes` AS `c` WHERE `c`.`job` = `j`.`job` AND (`c`.`server` = `j`.`server` OR `j`.`server` = '')) ORDER BY `j`.`project` ASC") as $i) {
		$i = explode('-', $i['project']);
		$id = array_pop($i);
		if (intval($id)) {
			$projects[$id] = implode('-', $i);
		}
	}

	return array(
		'template'  => $templates,
		'playbook'  => changes_report_option_list("SELECT DISTINCT `playbook` FROM `changes` ORDER BY `playbook` ASC", 'playbook'),
		'role'      => changes_report_option_list("SELECT DISTINCT `role` FROM `changes` ORDER BY `role` ASC", 'role'),
		'module'    => changes_report_option_list("SELECT DISTINCT `task_action` FROM `changes` ORDER BY `task_action` ASC", 'task_action'),
		'type'      => array('run' => 'Run Mode', 'check' => 'Check Mode'),
		'inventory' => $inventories,
		'project'   => $projects,
	);
}

/*
	Reads a column of distinct values into an option list where the value and the label are the
	same, which is how the changes table stores playbooks, roles and modules.
*/
function changes_report_option_list($sql, $column) {
	$options = array();
	foreach (db_fetch_assocs($sql) as $row) {
		if ($row[$column] != '') {
			$options[$row[$column]] = $row[$column];
		}
	}
	return $options;
}

/*
	The Timespan filter's choices, as Ascender Ledger Pro has them.  "Last ..." spans end now and
	move with the clock; "This ..." and "Previous ..." are whole calendar periods (a week starts
	on Sunday).  0 is a custom span with its own start and end.
*/
function timespan_choices() {
	return array(
		1 => 'Last 30 Minutes', 2 => 'Last 1 Hour', 3 => 'Last 2 Hours', 4 => 'Last 4 Hours',
		5 => 'Last 6 Hours', 6 => 'Last 12 Hours', 7 => 'Last Day', 8 => 'Last 2 Days',
		9 => 'Last 3 Days', 10 => 'Last 4 Days', 11 => 'Last Week', 12 => 'Last 2 Weeks',
		13 => 'Last Month', 14 => 'Last 2 Months', 15 => 'Last 3 Months', 16 => 'Last 4 Months',
		17 => 'Last 6 Months', 18 => 'Last Year', 19 => 'Last 2 Years',
		20 => 'This Day', 21 => 'This Week', 22 => 'This Month', 23 => 'This Year',
		24 => 'Previous Day', 25 => 'Previous Week', 26 => 'Previous Month', 27 => 'Previous Year',
		0 => 'Custom',
	);
}

/*
	The start and end (unix times, inclusive) of a preset timespan, or null for an unknown one
*/
function timespan_range($id, $now = null) {
	$now = ($now === null ? time() : $now);
	$t = (new DateTimeImmutable())->setTimestamp($now);
	$back = array(
		1 => 'PT30M', 2 => 'PT1H', 3 => 'PT2H', 4 => 'PT4H', 5 => 'PT6H', 6 => 'PT12H',
		7 => 'P1D', 8 => 'P2D', 9 => 'P3D', 10 => 'P4D', 11 => 'P7D', 12 => 'P14D',
	);
	if (isset($back[$id])) {
		return array($t->sub(new DateInterval($back[$id]))->getTimestamp(), $now);
	}
	/*
		Months back land on the same day of the month, or the last day of a shorter month: a
		month before March 31 is February 28, where PHP's own arithmetic gives March 3.
	*/
	$months = array(13 => 1, 14 => 2, 15 => 3, 16 => 4, 17 => 6, 18 => 12, 19 => 24);
	if (isset($months[$id])) {
		$first = $t->setDate((int) $t->format('Y'), (int) $t->format('n'), 1)->modify('-' . $months[$id] . ' months');
		$day = min((int) $t->format('j'), (int) $first->format('t'));
		return array($first->setDate((int) $first->format('Y'), (int) $first->format('n'), $day)->getTimestamp(), $now);
	}

	$day   = $t->setTime(0, 0);
	$week  = $day->modify('-' . $day->format('w') . ' days');
	$month = $day->modify('first day of this month');
	$year  = $day->setDate((int) $day->format('Y'), 1, 1);
	$spans = array(
		20 => array($day, '+1 day'),
		21 => array($week, '+1 week'),
		22 => array($month, '+1 month'),
		23 => array($year, '+1 year'),
		24 => array($day->modify('-1 day'), '+1 day'),
		25 => array($week->modify('-1 week'), '+1 week'),
		26 => array($month->modify('-1 month'), '+1 month'),
		27 => array($year->modify('-1 year'), '+1 year'),
	);
	if (isset($spans[$id])) {
		return array($spans[$id][0]->getTimestamp(), $spans[$id][0]->modify($spans[$id][1])->getTimestamp() - 1);
	}
	return null;
}

/*
	A date and time from a datetime-local input (2026-11-09T14:30), as a unix time, or null
*/
function timespan_parse_date($s) {
	if (!is_string($s)) {
		return null;
	}
	$d = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', substr($s, 0, 16));
	return ($d === false ? null : $d->getTimestamp());
}

/*
	Everything a user may do, as array(permission => true): their own permissions and those of
	their enabled teams.  An administrator gets every permission.
*/
function user_permissions($user) {
	global $permissions;
	$p = array();
	$rows = db_fetch_assocs_prepare('SELECT `perm` FROM `user_permission` WHERE `user` = ?
		UNION SELECT `tp`.`perm` FROM `team_permission` AS `tp`
		JOIN `team_user` AS `tu` ON `tu`.`team` = `tp`.`team`
		JOIN `teams` AS `t` ON `t`.`id` = `tp`.`team` AND `t`.`enabled` = 1
		WHERE `tu`.`user` = ?', array(intval($user), intval($user)));
	foreach ($rows as $r) {
		$p[$r['perm']] = true;
	}
	if (isset($p['super'])) {
		$p = array_fill_keys(array_keys($permissions), true);
	}
	return $p;
}

/*
	Whether the signed in user has a permission
*/
function check_perm($perm) {
	global $account;
	return !empty($account['perms'][$perm]);
}

/*
	The enabled teams a user is in, by id
*/
function user_team_ids($user) {
	return array_map('intval', array_column(db_fetch_assocs_prepare('SELECT `tu`.`team` FROM `team_user` AS `tu`
		JOIN `teams` AS `t` ON `t`.`id` = `tu`.`team` AND `t`.`enabled` = 1 WHERE `tu`.`user` = ?', array(intval($user))), 'team'));
}

/*
	A user's role on a report: their own, or the best of their enabled teams'.  '' for none.
*/
function report_role($report, $user) {
	$rank = array('view' => 1, 'edit' => 2, 'owner' => 3);
	$best = '';
	$roles = db_fetch_assocs_prepare('SELECT `role` FROM `reports_perms` WHERE `report` = ? AND `user` = ?
		UNION SELECT `rt`.`role` FROM `reports_team_perms` AS `rt`
		JOIN `team_user` AS `tu` ON `tu`.`team` = `rt`.`team`
		JOIN `teams` AS `t` ON `t`.`id` = `rt`.`team` AND `t`.`enabled` = 1
		WHERE `rt`.`report` = ? AND `tu`.`user` = ?', array(intval($report), intval($user), intval($report), intval($user)));
	foreach ($roles as $r) {
		if (isset($rank[$r['role']]) && ($best == '' || $rank[$r['role']] > $rank[$best])) {
			$best = $r['role'];
		}
	}
	return $best;
}

/*
	The most rows a CSV or PDF export of a list page holds (Settings > Visual)
*/
function export_limit() {
	global $export_limits;
	$v = intval(read_setting('export_limit', 10000));
	return (in_array($v, $export_limits, true) ? $v : 10000);
}

/*
	How many matches a search-as-you-type menu loads at a time (Settings > Visual)
*/
function callback_limit() {
	global $callback_limits;
	$v = intval(read_setting('callback_limit', 20));
	return (in_array($v, $callback_limits, true) ? $v : 20);
}

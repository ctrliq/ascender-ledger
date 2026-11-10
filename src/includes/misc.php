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
	host matches when it has every filtered fact with a value that passes.  With $span
	(array(start, end)), only hosts last seen within it.
*/
function build_filter($filters, $span = null) {
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

	if ($span !== null) {
		$sql .= "WHERE `h`.`time` BETWEEN ? AND ?\n";
		$p[] = $span[0];
		$p[] = $span[1];
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

	// All Data covers every host, however long ago it was seen
	$w = build_filter(is_array($report->filters) ? $report->filters : array(), ($report->timespan == TIMESPAN_ALL ? null : report_span($report->timespan)));
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
	The columns a changes report shows, as in Ascender Ledger Pro: the Changes page's fields.
	The first column of the report page is a link to the change, which is not listed here.
*/
function changes_report_columns() {
	return array(
		'Date'     => 'time',
		'Host'     => 'hostname',
		'Playbook' => 'playbook',
		'Role'     => 'role',
		'Task'     => 'task',
		'Module'   => 'task_action',
	);
}

/*
	A changes report's change filters, kept in its options: Pro's Playbook, Role, Module, Job
	Type and Search, and the Host, Job Template, Inventory and Project filters of the Changes
	page.  Which hosts it covers is chosen by its fact filters, as on a facts report.
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
	);
}

/*
	The change filters whose value is an id rather than a name
*/
function changes_report_numeric_filter_fields() {
	return array('host', 'template', 'inventory', 'project');
}

// The FROM of a changes report, as the Changes page joins it
function changes_report_from() {
	return 'FROM `changes` AS `c` LEFT JOIN `hosts` AS `h` ON `h`.`id` = `c`.`host` LEFT JOIN `jobs` AS `j` ON ' . jobs_join_on('j', 'c');
}

/*
	The conditions choosing a changes report's rows, and their parameters: changes within its
	timespan, on hosts its fact filters match, narrowed by its change filters.
*/
function changes_report_where($report) {
	$span = report_span($report->timespan);
	$where = array('`c`.`time` BETWEEN ? AND ?');
	$p = array($span[0], $span[1]);

	if (!empty($report->filters)) {
		$hosts = build_filter($report->filters);
		$where[] = '`c`.`host` IN (' . $hosts[0] . ')';
		$p = array_merge($p, $hosts[1]);
	}

	$o = $report->options;
	$exact = array('playbook' => '`c`.`playbook`', 'role' => '`c`.`role`', 'module' => '`c`.`task_action`', 'type' => '`j`.`job_type`');
	foreach ($exact as $k => $col) {
		if (isset($o[$k]) && $o[$k] !== '') {
			$where[] = "$col = ?";
			$p[] = (string) $o[$k];
		}
	}
	if (!empty($o['host'])) {
		$where[] = '`c`.`host` = ?';
		$p[] = intval($o['host']);
	}
	if (!empty($o['template'])) {
		$where[] = '`j`.`job_template_id` = ?';
		$p[] = intval($o['template']);
	}
	foreach (array('inventory', 'project') as $k) {
		if (!empty($o[$k])) {
			$where[] = "`j`.`$k` LIKE ?";
			$p[] = '%-' . intval($o[$k]);
		}
	}
	if (isset($o['search']) && is_string($o['search'])) {
		foreach (preg_split('/\s+/', $o['search'], -1, PREG_SPLIT_NO_EMPTY) as $term) {
			$like = '%' . dt_like_escape($term) . '%';
			$where[] = '(`h`.`hostname` LIKE ? OR `c`.`playbook` LIKE ? OR `c`.`role` LIKE ? OR `c`.`task` LIKE ? OR `c`.`task_action` LIKE ? OR `c`.`res` LIKE ?)';
			array_push($p, $like, $like, $like, $like, $like, $like);
		}
	}

	return array($where, $p);
}

/*
	A changes report for its email: the newest changes, up to the export limit, and how many
	there are in all.  Each row holds the cells of changes_report_columns() in order.
*/
function build_changes_report($id, $limit = null) {
	$report = new Report(intval($id));
	if (!$report->id || $report->type != 'changes') {
		return array('rows' => array(), 'total' => 0);
	}
	$limit = ($limit === null ? export_limit() : intval($limit));

	list($where, $p) = changes_report_where($report);
	$where = ' WHERE ' . implode(' AND ', $where);
	$total = intval(db_fetch_cell_prepare('SELECT COUNT(*) AS `n` ' . changes_report_from() . $where, 'n', $p));

	$rows = array();
	foreach (db_fetch_assocs_prepare('SELECT `c`.`id`, `c`.`time`, `h`.`hostname`, `c`.`playbook`, `c`.`role`, `c`.`task`, `c`.`task_action`, `j`.`job_type` '
			. changes_report_from() . $where . ' ORDER BY `c`.`time` DESC, `c`.`id` DESC LIMIT ' . $limit, $p) as $c) {
		$rows[] = array(
			'check' => ($c['job_type'] == 'check'),
			'cells' => array(date('m/d/Y H:i', $c['time']), $c['hostname'], $c['playbook'], $c['role'], $c['task'], $c['task_action']),
		);
	}

	return array('rows' => $rows, 'total' => $total);
}

/*
	What a changes report's change filters are set to, as label => value for its header
*/
function changes_report_filter_summary($report) {
	$fields = changes_report_filter_fields();
	$names = null;
	$out = array();
	foreach ($fields as $k => $label) {
		if (!isset($report->options[$k]) || $report->options[$k] === '' || $report->options[$k] === 0) {
			continue;
		}
		$v = $report->options[$k];
		if ($k == 'host') {
			$v = host_name($v);
		} elseif (in_array($k, array('template', 'inventory', 'project'), true)) {
			$names = ($names === null ? changes_report_filter_options() : $names);
			$v = (isset($names[$k][$v]) ? $names[$k][$v] : $v);
		} elseif ($k == 'type') {
			$v = ($v == 'check' ? 'Check Mode' : 'Run Mode');
		}
		$out[$label] = $v;
	}
	return $out;
}

/*
	The facts filters of a report in words, for its header: All hosts, or each test joined by AND
*/
function report_filter_summary($filters) {
	global $compares;
	$parts = array();
	foreach ((array) $filters as $f) {
		if (isset($f['fact'], $f['compare'], $f['value']) && isset($compares[$f['compare']])) {
			$parts[] = $f['fact'] . ' ' . strtolower($compares[$f['compare']]) . ' "' . $f['value'] . '"';
		}
	}
	return (empty($parts) ? 'All hosts' : implode(' AND ', $parts));
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

/*
	A report's timespan: All Data, or one of the Timespan filter's presets.  A report has no
	custom span, as in Ascender Ledger Pro, since it is run again later (on a schedule) and a
	fixed span would go stale.
*/
define('TIMESPAN_ALL', 99);

function report_timespan_choices() {
	$choices = array(TIMESPAN_ALL => 'All Data');
	foreach (timespan_choices() as $id => $label) {
		if ($id != 0) {
			$choices[$id] = $label;
		}
	}
	return $choices;
}

// The start and end of a report's timespan, now
function report_span($id, $now = null) {
	$now = ($now === null ? time() : $now);
	$r = ($id == TIMESPAN_ALL ? null : timespan_range(intval($id), $now));
	return ($r === null ? array(0, $now) : $r);
}

// A report's timespan in words, for its header
function report_span_subtitle($id, $now = null) {
	if ($id == TIMESPAN_ALL) {
		return 'All Available Data';
	}
	$r = report_span($id, $now);
	return 'From ' . date('m/d/Y H:i', $r[0]) . ' to ' . date('m/d/Y H:i', $r[1]);
}


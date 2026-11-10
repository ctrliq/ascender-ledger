<?php
include_once('includes/global.php');

if (!check_perm('reports')) {
	Header("Location: /\n\n");
	exit;
}

$users = reindex_arr_by_id_col(db_fetch_assocs('SELECT `id`, `name` FROM `users`'), 'name');

/*
	These actions change a report but are still plain links, so the link carries the session's
	CSRF token and is refused without it.  The rest are POSTed forms, which are checked for every
	request.
*/
if (isset($_REQUEST['action']) && in_array($_REQUEST['action'], array('delete', 'removeuserperm', 'removeteamperm', 'deletefilter', 'moveup', 'movedown', 'deletefact', 'scheduledelete', 'new'), true)) {
	csrf_verify();
}

// Form actions only ever arrive as POSTs, which is where the CSRF check applies
if (isset($_REQUEST['action']) && in_array($_REQUEST['action'], array('savesort', 'savename', 'saveoptions', 'addfilter', 'addcolumn', 'adduserperm', 'addteamperm', 'schedulesave'), true) && $_SERVER['REQUEST_METHOD'] != 'POST') {
	Header("Location: /reports/\n\n");
	exit;
}

/*
	A facts report shows facts and a changes report shows changes, so each needs the same
	permission as the page it mirrors: being shared a report does not get round View Facts
	or View Changes.
*/
function report_type_allowed($type) {
	return check_perm($type == 'changes' ? 'changes' : 'facts');
}

if (isset($_REQUEST['action'])) {
	if (isset($_REQUEST['report']) && $_REQUEST['report'] == intval($_REQUEST['report']) && $_REQUEST['report']) {
		$report = new Report(intval($_REQUEST['report']));
		if ($report->id && !report_type_allowed($report->type) && $_REQUEST['action'] != 'delete') {
			Header("Location: /reports/\n\n");
			exit;
		}
		if ($report->id && isset($_REQUEST['action'])) {
			switch ($_REQUEST['action']) {
				case 'delete':
					if ($report->owner == $account['id'] || $account['super']) {
						$report->delete();
					}
					Header("Location: /reports/" . ($report->type == 'changes' ? '?type=changes' : '') . "\n\n");
					exit;
				case 'adduserperm':
					if ($report->owner == $account['id'] || $account['super']) {
						$user = intval($_POST['user']);
						// Owners are made by creating a report; sharing gives edit or view
						$role = (isset($_POST['role']) && in_array($_POST['role'], array('edit', 'view'), true) ? $_POST['role'] : 'view');
						$report->add_user($user, $role);
					}
					Header("Location: /reports/perms/" . $report->id . "\n\n");
					exit;
				case 'addteamperm':
					if ($report->owner == $account['id'] || $account['super']) {
						$role = (isset($_POST['role']) && in_array($_POST['role'], array('edit', 'view'), true) ? $_POST['role'] : 'view');
						$report->add_team(intval($_POST['team']), $role);
					}
					Header("Location: /reports/perms/" . $report->id . "\n\n");
					exit;
				case 'removeteamperm':
					if ($report->owner == $account['id'] || $account['super']) {
						$report->remove_team(intval($_GET['team']));
					}
					Header("Location: /reports/perms/" . $report->id . "\n\n");
					exit;
				case 'removeuserperm':
					if ($report->owner == $account['id'] || $account['super']) {
						$user = intval($_GET['user']);
						$report->remove_user($user);
					}
					Header("Location: /reports/perms/" . $report->id . "\n\n");
					exit;
				case 'savesort':
					if ($report->owner == $account['id'] || $report->role == 'edit' || $account['super']) {
						$sortc = intval($_POST['sortc']);
						$sortd = $_POST['sortd'];
						$report->set_sortc($sortc);
						$report->set_sortd($sortd);
						$report->save();
					}
					Header("Location: /reports/edit/" . $report->id . "\n\n");
					exit;
				case 'savename':
					if ($report->owner == $account['id']  || $report->role == 'edit'|| $account['super']) {
						$name = $_POST['name'];
						$report->set_name($name);
						if (isset($_POST['timespan'])) {
							$report->set_timespan($_POST['timespan']);
						}
						$report->save();
					}
					Header("Location: /reports/edit/" . $report->id . "\n\n");
					exit;
				case 'saveoptions':
					if (($report->owner == $account['id'] || $report->role == 'edit' || $account['super']) && $report->type == 'changes') {
						$report->set_options($_POST);
					}
					Header("Location: /reports/edit/" . $report->id . "\n\n");
					exit;
				// Both kinds of report filter on facts: a facts report's hosts, or the hosts a changes report covers.
				// Filtering on facts needs View Facts, or a changes report could be used to test fact values.
				case 'deletefilter':
					if (($report->owner == $account['id'] || $report->role == 'edit' || $account['super']) && check_perm('facts')) {
						$report->remove_filter(intval($_GET['filter']));
					}
					Header("Location: /reports/edit/" . $report->id . "\n\n");
					exit;
				case 'addfilter':
					if (($report->owner == $account['id'] || $report->role == 'edit' || $account['super']) && check_perm('facts')) {
						$report->add_filter(post_str('fact'), post_str('compare'), post_str('value'));
					}
					Header("Location: /reports/edit/" . $report->id . "\n\n");
					exit;
				case 'moveup':
					if (($report->owner == $account['id'] || $report->role == 'edit' || $account['super']) && $report->type != 'changes') {
						$fact = intval($_GET['fact']);
						$report->move_column_up($fact);
					}
					Header("Location: /reports/edit/" . $report->id . "\n\n");
					exit;
				case 'movedown':
					if (($report->owner == $account['id'] || $report->role == 'edit' || $account['super']) && $report->type != 'changes') {
						$fact = intval($_GET['fact']);
						$report->move_column_down($fact);
					}
					Header("Location: /reports/edit/" . $report->id . "\n\n");
					exit;
				case 'deletefact':
					if (($report->owner == $account['id'] || $report->role == 'edit' || $account['super']) && $report->type != 'changes') {
						$fact = intval($_GET['fact']);
						$report->remove_column($fact);
					}
					Header("Location: /reports/edit/" . $report->id . "\n\n");
					exit;
				case 'addcolumn':
					if (($report->owner == $account['id'] || $report->role == 'edit' || $account['super']) && $report->type != 'changes') {
						$display = $report->clean_column($_POST['display']);
						if ($display != '') {
							$facts = (isset($_POST['facts']) ? $_POST['facts'] : array());
							$report->add_column($display, $facts);
						}
					}
					Header("Location: /reports/edit/" . $report->id . "\n\n");
					exit;
				case 'perms':
					if ($report->owner == $account['id'] || $account['super']) {
						$perms = reindex_arr_by_col(db_fetch_assocs_prepare('SELECT * FROM `reports_perms` WHERE `report` = ?', array($report->id)), 'user');
						$tperms = reindex_arr_by_col(db_fetch_assocs_prepare('SELECT * FROM `reports_team_perms` WHERE `report` = ?', array($report->id)), 'team');
						$teams = reindex_arr_by_id_col(db_fetch_assocs('SELECT `id`, `name` FROM `teams` ORDER BY `name`'), 'name');
						$roles = array('owner' => 'Owner', 'edit' => 'Editor', 'view' => 'Viewer');
						echo $twig->render('report_perms.html', array_merge($twigarr, array('report' => $report, 'perms' => $perms, 'tperms' => $tperms, 'users' => $users, 'teams' => $teams, 'roles' => $roles)));
					}
					exit;
				case 'schedules':
					if ($account['super']) {
						$schedules = db_fetch_assocs_prepare('SELECT * FROM `reports_schedules` WHERE `report` = ?', array($report->id));
					} else {
						$schedules = db_fetch_assocs_prepare('SELECT * FROM `reports_schedules` WHERE `report` = ? AND `owner` = ?', array($report->id, $account['id']));
					}
					echo $twig->render('report_schedules.html', array_merge($twigarr, array('report' => $report, 'schedules' => $schedules, 'reoccur' => $reoccur, 'users' => $users)));
					exit;
				case 'schedulesave':
					if ($_SERVER['REQUEST_METHOD'] != 'POST' || !($report->owner == $account['id'] || $report->role == 'edit' || $account['super'])) {
						Header("Location: /reports/\n\n");
						exit;
					}
					if (isset($_GET['schedule'])) {
						$sid = intval($_GET['schedule']);
					} else {
						$sid = 0;
					}
					$subject = substr(trim(preg_replace('/[^A-Za-z0-9 \-_\.:,@\(\)\[\]]/', '', (string) $_POST['subject'])), 0, 128);
					$start = strtotime((string) $_POST['start']);
					if ($start === false) {
						$start = time();
					}
					$enabled = (isset($_POST['enabled']) ? 1 : 0);
					$repeat = (isset($_POST['repeat']) && isset($reoccur[$_POST['repeat']]) ? intval($_POST['repeat']) : 86400);
					$next = $start;
					while ($next < time()) {
						$next += $repeat;
					}
					// Whole addresses only: an address cut short could be someone else's
					$emails = '';
					foreach (array_unique(preg_split('/[\s,;]+/', (string) $_POST['emails'])) as $e) {
						if (filter_var($e, FILTER_VALIDATE_EMAIL) && strlen($emails) + strlen($e) + 2 <= 65535) {
							$emails .= ($emails === '' ? '' : ', ') . $e;
						}
					}
					if ($sid == 0) {
						db_execute_prepare('INSERT INTO `reports_schedules` (`report`, `start`, `enabled`, `repeat`, `next`, `emails`, `owner`, `subject`)
												VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
												array($report->id, $start, $enabled, $repeat, $next, $emails, $account['id'], $subject));
					} else {
						if ($account['super']) {
							$schedule = db_fetch_assoc_prepare('SELECT * FROM `reports_schedules` WHERE `report` = ? AND `id` = ?', array($report->id, $sid));
						} else {
							$schedule = db_fetch_assoc_prepare('SELECT * FROM `reports_schedules` WHERE `report` = ? AND `owner` = ? AND `id` = ?', array($report->id, $account['id'], $sid));
						}
						if (isset($schedule['id'])) {
							db_execute_prepare('UPDATE `reports_schedules` SET `start` = ?, `enabled` = ?, `repeat` = ?, `next` = ?, `emails` = ?, `subject` = ? WHERE `id` = ? AND `report` = ?',
							array($start, $enabled, $repeat, $next, $emails, $subject, $sid, $report->id));
						}
					}
					Header("Location: /reports/" . $report->id . "/schedule/\n\n");
					exit;
				case 'scheduledelete':
					if (isset($_GET['schedule'])) {
						$sid = intval($_GET['schedule']);
						if ($account['super']) {
							db_execute_prepare('DELETE FROM `reports_schedules` WHERE `id` = ? AND `report` = ?', array($sid, $report->id));
						} else {
							db_execute_prepare('DELETE FROM `reports_schedules` WHERE `id` = ? AND `report` = ? AND `owner` = ?', array($sid, $report->id, $account['id']));
						}
					}
					Header("Location: /reports/" . $report->id . "/schedule/\n\n");
					exit;
				case 'scheduleedit':
					if (isset($_GET['schedule'])) {
						$sid = intval($_GET['schedule']);
						if ($account['super']) {
							$schedule = db_fetch_assoc_prepare('SELECT * FROM `reports_schedules` WHERE `report` = ? AND `id` = ?', array($report->id, $sid));
						} else {
							$schedule = db_fetch_assoc_prepare('SELECT * FROM `reports_schedules` WHERE `report` = ? AND `id` = ? AND `owner` = ?', array($report->id, $sid, $account['id']));
						}
						if (isset($schedule['id'])) {
							echo $twig->render('report_schedule_edit.html', array_merge($twigarr, array('report' => $report, 'schedule' => $schedule, 'reoccur' => $reoccur, 'users' => $users)));
						}
					}
					exit;
				case 'schedulenew':
					if ($report->owner == $account['id'] || $report->role == 'edit' || $account['super']) {
						$schedule = array('id' => 0, 'subject' => 'New Report', 'enabled' => 1, 'start' => time(), 'repeat' => 86400, 'emails' => $account['email']);
						echo $twig->render('report_schedule_edit.html', array_merge($twigarr, array('report' => $report, 'schedule' => $schedule, 'reoccur' => $reoccur, 'users' => $users)));
					}
					exit;
				case 'view':
					// Without View Facts, a changes report's viewer is told its hosts are filtered, not on which facts
					$host_filters = (check_perm('facts') || empty($report->filters) ? report_filter_summary($report->filters) : 'Filtered by host facts');
					$header = array('subtitle' => report_span_subtitle($report->timespan), 'host_filters' => $host_filters);
					if ($report->type == 'changes') {
						/*
							A changes report is read a page at a time, like the Changes page, so it can
							cover any number of changes.  Its export buttons fetch every row, up to the
							export limit.
						*/
						if (dt_is_request()) {
							list($where, $params) = changes_report_where($report);
							dt_serve(
								'`c`.`id`, `c`.`time`, `c`.`host`, `h`.`hostname`, `c`.`playbook`, `c`.`role`, `c`.`task`, `c`.`task_action`, `j`.`job_type`',
								changes_report_from(),
								$where,
								$params,
								array(
									1 => array('`c`.`time`'),
									2 => array('`h`.`hostname`'),
									3 => array('`c`.`playbook`'),
									4 => array('`c`.`role`'),
									5 => array('`c`.`task`'),
									6 => array('`c`.`task_action`'),
								),
								'`c`.`time` DESC',
								array('`h`.`hostname`', '`c`.`playbook`', '`c`.`role`', '`c`.`task`', '`c`.`task_action`'),
								function ($r) {
									return array(
										'id'          => intval($r['id']),
										'date'        => date('m/d/Y H:i', $r['time']),
										'host_id'     => intval($r['host']),
										'hostname'    => $r['hostname'],
										'playbook'    => $r['playbook'],
										'role'        => $r['role'],
										'task'        => $r['task'],
										'task_action' => $r['task_action'],
										'check'       => ($r['job_type'] == 'check'),
									);
								},
								array('`c`.`id`')
							);
						}
						echo $twig->render('report_changes.html', array_merge($twigarr, $header, array('report' => $report, 'change_filters' => changes_report_filter_summary($report))));
						exit;
					}
					$data = build_report($report->id);
					echo $twig->render('report.html', array_merge($twigarr, $header, array('report' => $report, 'data' => $data, 'filters' => $report->filters, 'columns' => $report->columns, 'sortc' => $report->sortc, 'sortd' => $report->sortd)));
					exit;
				case 'edit':
					if ($report->owner == $account['id'] || $report->role == 'edit' || $account['super']) {
						$common = array('report' => $report, 'filters' => $report->filters, 'compares' => $compares, 'timespans' => report_timespan_choices());
						if ($report->type == 'changes') {
							$options = changes_report_filter_options();
							// A saved value the changes table no longer has (retention removed it) stays in its menu, so saving
							// keeps it.  A template, inventory or project is stored by ID, which is all there is left to show.
							foreach (array('template', 'playbook', 'role', 'module', 'inventory', 'project') as $k) {
								if (!empty($report->options[$k]) && !isset($options[$k][$report->options[$k]])) {
									$options[$k][$report->options[$k]] = (in_array($k, array('template', 'inventory', 'project'), true) ? 'ID ' : '') . $report->options[$k];
								}
							}
							echo $twig->render('report_changes_edit.html', array_merge($twigarr, $common, array('options' => $options,
									'host_name' => (!empty($report->options['host']) ? host_name($report->options['host']) : ''))));
							exit;
						}
						// The fact pickers search the server as you type, so no list of facts is sent
						echo $twig->render('report_edit.html', array_merge($twigarr, $common, array('columns' => $report->columns)));
						exit;
					}
					Header("Location: /reports/\n\n");
					exit;
			}
		}
	} else {
		switch ($_REQUEST['action']) {
			case 'new':
				$type = (isset($_REQUEST['type']) && $_REQUEST['type'] === 'changes' ? 'changes' : 'facts');
				if (!report_type_allowed($type) || !check_perm('reports_create')) {
					Header("Location: /reports/\n\n");
					exit;
				}
				$report = new Report();
				$report->set_owner($account['id']);
				$report->set_created(time());
				$report->set_type($type);
				if ($report->type == 'changes') {
					$report->set_name('New Changes Report - ' . $account['name']);
					$report->set_columns(changes_report_columns());
				} else {
					$report->set_name('New Report - ' . $account['name']);
				}
				$report->save();
				Header("Location: /reports/edit/" . $report->id . "\n\n");
				exit;
		}
		Header("Location: /reports/\n\n");
		exit;
	}
}

/*
	Reports shared with the user or with one of their enabled teams, each once with the best
	role it is shared with
*/
$rank = array('view' => 1, 'edit' => 2, 'owner' => 3);
$reports = array();
$rows = db_fetch_assocs_prepare("SELECT `r`.*, `rp`.`role` FROM `reports` AS `r`
							JOIN `reports_perms` AS `rp` ON `rp`.`report` = `r`.`id`
							WHERE `rp`.`user` = ?
						UNION ALL
						SELECT `r`.*, `rt`.`role` FROM `reports` AS `r`
							JOIN `reports_team_perms` AS `rt` ON `rt`.`report` = `r`.`id`
							JOIN `team_user` AS `tu` ON `tu`.`team` = `rt`.`team`
							JOIN `teams` AS `t` ON `t`.`id` = `rt`.`team` AND `t`.`enabled` = 1
							WHERE `tu`.`user` = ?", array($account['id'], $account['id']));
foreach ($rows as $row) {
	$id = $row['id'];
	if (!isset($reports[$id]) || $rank[$row['role']] > $rank[$reports[$id]['role']]) {
		$reports[$id] = $row;
	}
}
uasort($reports, function ($a, $b) { return strcasecmp($a['name'], $b['name']); });

/*
	One tab per kind of report, as in Ascender Ledger Pro, for the kinds the user may see
*/
$tabs = array();
if (check_perm('facts')) {
	$tabs['facts'] = 'Facts';
}
if (check_perm('changes')) {
	$tabs['changes'] = 'Changes';
}
$page = (isset($_GET['type']) && is_string($_GET['type']) && isset($tabs[$_GET['type']]) ? $_GET['type'] : (string) key($tabs));
$reports = array_values(array_filter($reports, function ($r) use ($page) {
	return ($r['type'] == 'changes' ? 'changes' : 'facts') == $page;
}));


echo $twig->render('reports.html', array_merge($twigarr, array('reports' => $reports, 'users' => $users, 'compares' => $compares, 'tabs' => $tabs, 'page' => $page)));

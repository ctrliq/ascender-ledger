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
if (isset($_REQUEST['action']) && in_array($_REQUEST['action'], array('savesort', 'savename', 'addfilter', 'addcolumn', 'adduserperm', 'addteamperm', 'schedulesave'), true) && $_SERVER['REQUEST_METHOD'] != 'POST') {
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
					Header("Location: /reports/\n\n");
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
						$report->save();
					}
					Header("Location: /reports/edit/" . $report->id . "\n\n");
					exit;
				case 'deletefilter':
					if ($report->owner == $account['id'] || $report->role == 'edit' || $account['super']) {
						if ($report->type == 'changes') {
							if (isset($_GET['field'])) {
								$report->remove_changes_filter($_GET['field']);
							}
						} else {
							$filter = intval($_GET['filter']);
							$report->remove_filter($filter);
						}
					}
					Header("Location: /reports/edit/" . $report->id . "\n\n");
					exit;
				case 'addfilter':
					if ($report->owner == $account['id'] || $report->role == 'edit' || $account['super']) {
						if ($report->type == 'changes') {
							if (isset($_POST['field']) && isset($_POST['value'])) {
								$report->add_changes_filter($_POST['field'], $_POST['value']);
							}
						} else {
							$value = post_str('value');
							$compare = post_str('compare');
							$fact = post_str('fact');
							$report->add_filter($fact, $compare, $value);
						}
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
					if ($report->type == 'changes') {
						$data = build_changes_report($report->id);
					} else {
						$data = build_report($report->id);
					}
					echo $twig->render('report.html', array_merge($twigarr, array('report' => $report, 'data' => $data, 'filters' => $report->filters, 'columns' => $report->columns, 'sortc' => $report->sortc, 'sortd' => $report->sortd)));
					exit;
				case 'edit':
					if ($report->owner == $account['id'] || $report->role == 'edit' || $account['super']) {
						if ($report->type == 'changes') {
							$current = array();
							foreach ($report->filters as $f) {
								if (isset($f['field'])) {
									$current[$f['field']] = $f['value'];
								}
							}
							echo $twig->render('report_changes_edit.html', array_merge($twigarr, array('report' => $report, 'current' => $current,
									'current_hostname' => (isset($current['host']) ? host_name($current['host']) : ''),
									'columns' => $report->columns, 'fields' => changes_report_filter_fields(), 'options' => changes_report_filter_options())));
							exit;
						}
						// The fact pickers search the server as you type, so no list of facts is sent
						echo $twig->render('report_edit.html', array_merge($twigarr, array('report' => $report, 'filters' => $report->filters,
								'columns' => $report->columns, 'compares' => $compares)));
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
$reports = array_values($reports);


echo $twig->render('reports.html', array_merge($twigarr, array('reports' => $reports, 'users' => $users, 'compares' => $compares)));

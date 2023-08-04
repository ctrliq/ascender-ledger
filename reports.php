<?php
include_once('includes/global.php');

$users = reindex_arr_by_id_col(db_fetch_assocs('SELECT `id`, `name` FROM `users`'), 'name');

if (isset($_REQUEST['action'])) {
	if (isset($_REQUEST['report']) && $_REQUEST['report'] == intval($_REQUEST['report']) && $_REQUEST['report']) {
		$report = new Report(intval($_REQUEST['report']));
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
						$role = $_POST['role'];
						$report->add_user($user, $role);
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
					if ($report->owner == $account['id'] || $account['super']) {
						$sortc = intval($_POST['sortc']);
						$sortd = $_POST['sortd'];
						$report->set_sortc($sortc);
						$report->set_sortd($sortd);
						$report->save();
					}
					Header("Location: /reports/edit/" . $report->id . "\n\n");
					exit;
				case 'savename':
					if ($report->owner == $account['id'] || $account['super']) {
						$name = $_POST['name'];
						$report->set_name($name);
						$report->save();
					}
					Header("Location: /reports/edit/" . $report->id . "\n\n");
					exit;
				case 'deletefilter':
					if ($report->owner == $account['id'] || $report->role == 'editor' || $account['super']) {
						$filter = intval($_GET['filter']);
						$report->remove_filter($filter);
					}
					Header("Location: /reports/edit/" . $report->id . "\n\n");
					exit;
				case 'addfilter':
					if ($report->owner == $account['id'] || $report->role == 'editor' || $account['super']) {
						$value = $_POST['value'];
						$compare = $_POST['compare'];
						$fact = $_POST['fact'];
						$report->add_filter($fact, $compare, $value);
					}
					Header("Location: /reports/edit/" . $report->id . "\n\n");
					exit;
				case 'moveup':
					if ($report->owner == $account['id'] || $report->role == 'editor' || $account['super']) {
						$fact = intval($_GET['fact']);
						$report->move_column_up($fact);
					}
					Header("Location: /reports/edit/" . $report->id . "\n\n");
					exit;
				case 'movedown':
					if ($report->owner == $account['id'] || $report->role == 'editor' || $account['super']) {
						$fact = intval($_GET['fact']);
						$report->move_column_down($fact);
					}
					Header("Location: /reports/edit/" . $report->id . "\n\n");
					exit;
				case 'deletefact':
					if ($report->owner == $account['id'] || $report->role == 'editor' || $account['super']) {
						$fact = intval($_GET['fact']);
						$report->remove_column($fact);
					}
					Header("Location: /reports/edit/" . $report->id . "\n\n");
					exit;
				case 'addcolumn':
					if ($report->owner == $account['id'] || $report->role == 'editor' || $account['super']) {
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
						$roles = array('owner' => 'Owner', 'edit' => 'Editor', 'view' => 'Viewer');
						echo $twig->render('report_perms.html', array_merge($twigarr, array('report' => $report, 'perms' => $perms, 'users' => $users, 'roles' => $roles)));
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
					if (isset($_GET['schedule'])) {
						$sid = intval($_GET['schedule']);
					} else {
						$sid = 0;
					}
					$subject = sql_clean_username($_POST['subject']);
					$start = strtotime($_POST['start']);
					$enabled = (isset($_POST['enabled']) ? 1 : 0);
					$repeat = (isset($reoccur[$_POST['repeat']]) ? intval($_POST['repeat']) : 86400);
					$next = $start;
					while ($next < time()) {
						$next += $repeat;
					}
					$emails = sql_clean_emails($_POST['emails']);
					if ($sid == 0) {
						db_execute_prepare('INSERT INTO `reports_schedules` (`report`, `start`, `enabled`, `repeat`, `next`, `emails`, `owner`, `subject`)
												VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
												array($report->id, $start, $enabled, $repeat, $next, $emails, $account['id'], $subject));
					} else {
						if ($account['super']) {
							$schedule = db_fetch_assoc_prepare('SELECT * FROM `reports_schedules` WHERE `id` = ?', array($sid));
						} else {
							$schedule = db_fetch_assoc_prepare('SELECT * FROM `reports_schedules` WHERE `owner` = ? AND `id` = ?', array($account['id'], $sid));
						}
						if (isset($schedule['id'])) {
							db_execute_prepare('UPDATE `reports_schedules` SET `start` = ?, `enabled` = ?, `repeat` = ?, `next` = ?, `emails` = ?, `subject` = ? WHERE `id` = ?',
							array($start, $enabled, $repeat, $next, $emails, $subject, $sid));
						}
					}
					Header("Location: /reports/" . $report->id . "/schedule/\n\n");
					exit;
				case 'scheduledelete':
					if (isset($_GET['schedule'])) {
						$sid = intval($_GET['schedule']);
						if ($account['super']) {
							db_execute_prepare('DELETE FROM `reports_schedules` WHERE `id` = ?', array($sid));
						} else {
							db_execute_prepare('DELETE FROM `reports_schedules` WHERE `id` = ? AND `owner` = ?', array($sid, $account['id']));
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
					if ($report->owner == $account['id'] || $report->role == 'editor' || $account['super']) {
						$schedule = array('id' => 0, 'subject' => 'New Report', 'enabled' => 1, 'start' => time(), 'repeat' => 86400, 'emails' => $account['email']);
						echo $twig->render('report_schedule_edit.html', array_merge($twigarr, array('report' => $report, 'schedule' => $schedule, 'reoccur' => $reoccur, 'users' => $users)));
					}
					exit;
				case 'view':
					$data = build_report ($report->id);
					echo $twig->render('report.html', array_merge($twigarr, array('report' => $report, 'data' => $data, 'filters' => $report->filters, 'columns' => $report->columns, 'sortc' => $report->sortc, 'sortd' => $report->sortd)));
					exit;
				case 'edit':
					if ($report->owner == $account['id'] || $report->role == 'editor' || $account['super']) {
						$allfacts = db_fetch_assocs('SELECT DISTINCT `fact` FROM facts');
						$facts = array();
						foreach ($allfacts as $f) {
							$facts[] = $f['fact'];
						}
						echo $twig->render('report_edit.html', array_merge($twigarr, array('report' => $report, 'facts' => $facts, 'filters' => $report->filters, 
								'columns' => $report->columns, 'compares' => $compares)));
					}
					Header("Location: /reports/\n\n");
					exit;
			}
		} else {
			switch ($_REQUEST['action']) {
				case 'new':
					$report = new Report();
					$report->set_owner($account['id']);
					$report->set_created(time());
					$report->set_name('New Report - ' . $account['name']);
					$report->save();
					Header("Location: /reports/edit/" . $report->id . "\n\n");
					exit;
			}
			Header("Location: /reports/\n\n");
			exit;
		}
	}
}

$reports = db_fetch_assocs_prepare("SELECT `reports`.*, `reports_perms`.`role` FROM `reports`
							LEFT JOIN `reports_perms` ON `reports_perms`.`report` = `reports`.`id`
							WHERE `reports_perms`.`user` = ?
							ORDER BY `reports`.`name`", array($account['id']));


echo $twig->render('reports.html', array_merge($twigarr, array('reports' => $reports, 'users' => $users, 'compares' => $compares)));

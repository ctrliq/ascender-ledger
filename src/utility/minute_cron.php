<?php
set_time_limit(59);
if (php_sapi_name() != 'cli') {
	exit;
}

chdir(dirname($_SERVER['argv'][0]));
chdir("..");

$account = array('id' => 0, 'cli' => true);

require_once('includes/version.php');
require_once('includes/sql.php');
require_once('vendor/autoload.php');
require_once('includes/arrays.php');
require_once('includes/email.php');
require_once('includes/misc.php');
require_once('includes/datatables.php');
require_once('includes/templates.php');
require_once('includes/classes/Report.php');

/*
	A schedule's report is only sent while its owner could still see it in Ledger: the owner is
	enabled, the report is still shared with them, and they may view that type of data.
*/
function schedule_owner_allowed($s, $report) {
	$u = db_fetch_assoc_prepare('SELECT `enabled`, `super`, `view_changes`, `view_facts` FROM `users` WHERE `id` = ?', array($s['owner']));
	if (!isset($u['enabled']) || !$u['enabled']) {
		return false;
	}
	if (!db_fetch_cell_prepare('SELECT 1 AS `e` FROM `reports_perms` WHERE `report` = ? AND `user` = ?', 'e', array($report->id, $s['owner']))) {
		return false;
	}
	if ($u['super']) {
		return true;
	}
	return ($report->type == 'changes' ? (bool) $u['view_changes'] : (bool) $u['view_facts']);
}

// A claim older than this belongs to a run that died part way (out of memory, killed), and is taken over
define('SCHEDULE_CLAIM_TIMEOUT', 900);

$t = time() - 1;

$done = false;

while (!$done) {
	// A new token for each claim, so a schedule left claimed after a failure is not picked up again by this run
	$r = random_int(1000, 1000000000);
	// We will grab 1 report at a time. Try to avoid race conditions in case of multiple web servers processing
	db_execute_prepare("UPDATE `reports_schedules` SET `process` = ?, `claimed` = ? WHERE `next` < ? AND (`process` = 0 OR `claimed` < ?) AND `enabled` = 1 LIMIT 1",
		array($r, time(), $t, time() - SCHEDULE_CLAIM_TIMEOUT));

	$s = db_fetch_assoc_prepare('SELECT * FROM `reports_schedules` WHERE `next` < ? AND `process` = ? AND `enabled` = 1', array($t, $r));

	if (isset($s['id'])) {
		// Next run is the first one still ahead, so a schedule missed while the cron was down runs once, not once per missed period
		$next = intval($s['next']);
		$repeat = max(3600, intval($s['repeat']));
		while ($next <= time()) {
			$next += $repeat;
		}

		try {
			$report = new Report($s['report']);
			$html = null;

			if ($report->id && !schedule_owner_allowed($s, $report)) {
				error_log('Ledger: scheduled report ' . $s['id'] . ' was not sent: its owner is disabled or can no longer view report ' . $report->id);
			} elseif ($report->id) {
				if ($report->type == 'changes') {
					$data = build_changes_report($report->id);
				} else {
					$data = build_report($report->id);
				}
				$html = $twig->render('emails/report_email.html', array_merge($twigarr, array('report' => $report, 'data' => $data, 'filters' => $report->filters, 'columns' => $report->columns, 'sortc' => $report->sortc, 'sortd' => $report->sortd)));
				unset($data);
			}

			/*
				Queueing the email for every recipient and moving the schedule on happen together
				or not at all, so a run that fails part way and is retried never sends anyone the
				report twice.  The schedule is only moved on while this run still holds its claim;
				if the claim timed out and another run took it over, that run sends the report.
			*/
			db_begin();
			if ($html !== null) {
				send_email(array_map('trim', explode(',', $s['emails'])), $s['subject'], $html);
			}
			$stmt = db_prepare_cached('UPDATE `reports_schedules` SET `next` = ?, `process` = 0, `claimed` = 0 WHERE `id` = ? AND `process` = ?');
			$stmt->execute(array($next, $s['id'], $r));
			if ($stmt->rowCount() == 1) {
				db_commit();
			} else {
				db_rollback();
				error_log('Ledger: scheduled report ' . $s['id'] . ' was not sent by this run: another run took it over');
			}
		} catch (Exception $e) {
			db_rollback();
			/*
				A failed run (the database was unavailable, say) keeps its claim, so it is tried
				again when the claim times out, until the next run is due; then it is skipped,
				so a report that always fails is not retried forever.
			*/
			if (time() < intval($s['next']) + $repeat) {
				error_log('Ledger: scheduled report ' . $s['id'] . ' failed, and will be retried in ' . (SCHEDULE_CLAIM_TIMEOUT / 60) . ' minutes: ' . $e->getMessage());
				continue;
			}
			error_log('Ledger: scheduled report ' . $s['id'] . ' failed, and is skipped until its next run: ' . $e->getMessage());
			db_execute_prepare('UPDATE `reports_schedules` SET `next` = ?, `process` = 0, `claimed` = 0 WHERE `id` = ? AND `process` = ?', array($next, $s['id'], $r));
		}
	} else {
		$done = true;
	}
}

process_mail_queue();

/*
	Every five minutes, refresh what the pages read instead of scanning the big tables
	themselves: the fact names the fact pickers search, and the dashboard totals.
*/
if (intval(date('i')) % 5 == 0 || !db_fetch_cell('SELECT 1 AS `e` FROM `fact_names` LIMIT 1', 'e')) {
	db_execute('INSERT IGNORE INTO `fact_names` (`fact`) SELECT DISTINCT `fact` FROM `facts`');
	write_setting('dashboard_counts', json_encode(dashboard_counts()));
}

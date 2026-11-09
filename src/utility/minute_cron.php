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

$r = random_int(1000, 1000000000);
$t = time() - 1;

$done = false;

while (!$done) {
	// We will grab 1 report at a time. Try to avoid race conditions in case of multiple web servers processing
	db_execute_prepare("UPDATE `reports_schedules` SET `process` = ? WHERE `next` < ? AND `process` = 0 AND `enabled` = 1 LIMIT 1", array($r, $t));

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

			if ($report->id) {
				if ($report->type == 'changes') {
					$data = build_changes_report($report->id);
				} else {
					$data = build_report($report->id);
				}
				$html = $twig->render('emails/report_email.html', array_merge($twigarr, array('report' => $report, 'data' => $data, 'filters' => $report->filters, 'columns' => $report->columns, 'sortc' => $report->sortc, 'sortd' => $report->sortd)));
				send_email(array_map('trim', explode(',', $s['emails'])), $s['subject'], $html);
			}
		} catch (Exception $e) {
			error_log('Ledger: scheduled report ' . $s['id'] . ' failed: ' . $e->getMessage());
		}

		db_execute_prepare('UPDATE `reports_schedules` SET `next` = ?, `process` = 0 WHERE `id` = ?', array($next, $s['id']));
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

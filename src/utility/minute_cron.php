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
#require_once('includes/arrays.php');
require_once('includes/email.php');
require_once('includes/misc.php');
require_once('includes/templates.php');
require_once('includes/classes/Report.php');

$r = rand(1000, 1000000);
$t = time() - 1;

$done = false;

while (!$done) {
    // We will grab 1 report at a time. Try to avoid race conditions in case of multiple web servers processing
    db_execute_prepare("UPDATE `reports_schedules` SET `process` = ? WHERE `next` < ? AND `process` = 0 AND `enabled` = 1 LIMIT 1", array($r, $t));

    $s = db_fetch_assoc_prepare('SELECT * FROM `reports_schedules` WHERE `next` < ? AND `process` = ? AND `enabled` = 1', array($t, $r));

    if (isset($s['id'])) {
        db_execute_prepare('UPDATE `reports_schedules` SET `process` = 0 WHERE `id` = ?', array($s['report']));

        $report = new Report($s['report']);

        $data = build_report ($report->id);
        $html = $twig->render('emails/report_email.html', array_merge($twigarr, array('report' => $report, 'data' => $data, 'filters' => $report->filters, 'columns' => $report->columns, 'sortc' => $report->sortc, 'sortd' => $report->sortd))); 
        send_email($s['emails'], $s['subject'], $html);
        db_execute_prepare('UPDATE `reports_schedules` SET `next` = `next` + `repeat`, `process` = 0 WHERE `id` = ?', array($s['report']));

    } else {
        $done = true;
    }
}

process_mail_queue();

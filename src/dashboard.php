<?php

include_once('includes/global.php');
$t = time() -  (86400 * 30);

$changes = reindex_arr_by_col(db_fetch_assocs_prepare('SELECT COUNT(`id`) as `count`, DATE_FORMAT(FROM_UNIXTIME(time), "%m/%d/%y") as `d` FROM `changes` WHERE `time` > ? GROUP BY DATE_FORMAT(FROM_UNIXTIME(time), "%m/%d/%y")', array($t)), 'd');

$t = time();
for ($a = 0; $a < 30; $a++) {
	$d = date('m/d/y', $t - (86400 * $a));
	$s = date('n/d', $t - (86400 * $a));
	if (!isset($changes[$d])) {
		$changes[$d] = array('d' => $s, 'count' => 0);
	} else {
		$changes[$d]['d'] = date('n/d', strtotime($changes[$d]['d']));
	}
}
ksort($changes);

/*
	Counting every row of the facts table takes seconds on a large fleet, so the minute cron
	keeps the totals and the dashboard reads them.  They are counted here only until it has.
*/
$counts = json_decode((string) read_setting('dashboard_counts', ''), true);
if (!is_array($counts) || !isset($counts['hosts'])) {
	$counts = dashboard_counts();
}

// Tell administrators when Ascender's data is being refused, which is otherwise silent
$rejected = null;
if (!empty($account['super'])) {
	// Cleared by the parser once the refused server sends a record that is accepted (see parse.php)
	$r = json_decode((string) read_setting('auth_rejected', '{}'), true);
	if (isset($r['time']) && $r['time'] > time() - 86400) {
		$rejected = $r;
	}
}

$token_off = (!empty($account['super']) && read_setting('require_auth_token', '0') != '1');

echo $twig->render('dashboard.html', array_merge($twigarr, array('changes' => $changes, 'counts' => $counts, 'rejected' => $rejected, 'token_off' => $token_off)));

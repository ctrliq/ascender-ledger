<?php
include_once('includes/global.php');

$hosts = reindex_arr_by_id(db_fetch_assocs("SELECT * FROM `hosts` ORDER BY `hostname`"));
$h = array();
foreach ($hosts as $host) {
	$h[$host['id']] = $host['hostname'];
}

if (isset($_GET['action']) && $_GET['action'] == 'view' && $_GET['change'] == intval($_GET['change']) && intval($_GET['change'])) {
	$change = db_fetch_assoc_prepare('SELECT * FROM `changes` WHERE `id` = ?', array(intval($_GET['change'])));
	if (isset($change['id']) && $change['id']) {
#		$change['res'] = unserialize(base64_decode($change['res']));
		$job = db_fetch_assoc_prepare('SELECT * FROM `jobs` WHERE `job` = ? AND `host` = ?', array($change['job'], $change['server']));
		//$res = parse_res($change['task_action'], $change['res']);
		$res = $change['res'];
		echo $twig->render('change.html', array_merge($twigarr, array('change' => $change, 'hosts' => $h, 'res' => $res, 'job' => $job)));
		exit;

	}
}

$playbooks = reindex_col(db_fetch_assocs("SELECT DISTINCT `playbook` FROM `changes` ORDER BY `playbook` ASC"), 'playbook');
$filters = array();
$host = '';
$playbook = '';
$csearch = '';

if (isset($_GET['clear'])) {
	unset($_SESSION['changes_host']);
	unset($_SESSION['changes_playbook']);
	unset($_SESSION['changes_csearch']);
	header("Location: /changes/");
	exit;
}

if (isset($_GET['host'])) {
	if (intval($_GET['host']) == $_GET['host'] && isset($hosts[$_GET['host']])) {
		$_SESSION['changes_host'] = intval($_GET['host']);
	} else {
		unset($_SESSION['changes_host']);
	}
	header("Location: /changes/");
	exit;
}

if (isset($_GET['playbook'])) {
	if (in_array($_GET['playbook'], $playbooks)) {
		$_SESSION['changes_playbook'] = $_GET['playbook'];
	} else {
		unset($_SESSION['changes_playbook']);
	}
	header("Location: /changes/");
	exit;
}

if (isset($_GET['csearch'])) {
	if ($_GET['csearch'] == '') {
		unset($_SESSION['changes_csearch']);
	} else {
		$_SESSION['changes_csearch'] = sql_clean_ans($_GET['csearch']);
	}
	header("Location: /changes/");
	exit;
}

// Create filters
if (isset($_SESSION['changes_host']) && $_SESSION['changes_host'] != '') {
	$filters[] = " `host` = " . $_SESSION['changes_host'];
	$host = $_SESSION['changes_host'];
}

if (isset($_SESSION['changes_playbook']) && $_SESSION['changes_playbook'] != '') {
	$filters[] = " `playbook` = '" . $_SESSION['changes_playbook'] . "'";
	$playbook = $_SESSION['changes_playbook'];
}

if (isset($_SESSION['changes_csearch']) && $_SESSION['changes_csearch'] != '') {
	$s = $_SESSION['changes_csearch'];
	$filters[] = " `res` LIKE '%$s%' OR `task_action` LIKE '%$s%' OR `task` LIKE '%$s%' OR `role` LIKE '%$s%' OR `play` LIKE '%$s%'";
	$csearch = $_SESSION['changes_csearch'];
}

if (!empty($filters)) {
	$filters = "WHERE " . implode(' AND ', $filters);
} else {
	$filters = '';
}

$changes = db_fetch_assocs("SELECT * FROM `changes` $filters ORDER BY `time` DESC LIMIT 0,100");




echo $twig->render('changes.html', array_merge($twigarr, array('changes' => $changes, 'hosts' => $h, 'playbooks' => $playbooks, 'host' => $host, 'playbook' => $playbook, 'csearch' => $csearch)));





// Not used yet
function parse_res($module, $r) {
	return print_r($r, true);

	switch ($module) {
		case 'win_feature':
			$t = "<br>Installed</b><br>";
			foreach ($r['feature_result'] as $f) {
				$t .= ' - ' . $f['display_name'] . '<br>';
			}
			return $t;

		case 'win_security_policy':
			return '<b>Key</b>: ' . $r['key'] . '<br><b>Section</b>: ' . $r['section'] . '<br><b>Value</b>: ' . $s['value'];
	}
	return print_r($r, true);
}

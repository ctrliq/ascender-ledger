<?php
include_once('includes/global.php');

if (!$account['view_changes'] && !$account['super']) {
    header("Location: /\n\n");
    exit;
}

$hosts = reindex_arr_by_id(db_fetch_assocs("SELECT * FROM `hosts` ORDER BY `hostname`"));
$h = array();
foreach ($hosts as $host) {
	$h[$host['id']] = $host['hostname'];
}

if (isset($_GET['action']) && $_GET['action'] == 'view' && $_GET['change'] == intval($_GET['change']) && intval($_GET['change'])) {
	$change = db_fetch_assoc_prepare('SELECT * FROM `changes` WHERE `id` = ?', array(intval($_GET['change'])));
	if (isset($change['id']) && $change['id']) {
#		$change['res'] = unserialize(base64_decode($change['res']));
		$server = db_fetch_assoc_prepare('SELECT * FROM `servers` WHERE `name` = ?', array($change['server']));
#		$job = db_fetch_assoc_prepare('SELECT * FROM `jobs` WHERE `job` = ? AND `host` = ?', array($change['job'], $change['server']));
		$job = db_fetch_assoc_prepare('SELECT * FROM `jobs` WHERE `job` = ?', array($change['job']));
		//$res = parse_res($change['task_action'], $change['res']);
		$res = $change['res'];
		echo $twig->render('change.html', array_merge($twigarr, array('change' => $change, 'server' => $server, 'hosts' => $h, 'res' => $res, 'job' => $job)));
		exit;
	}
}

$playbooks = reindex_col(db_fetch_assocs("SELECT DISTINCT `playbook` FROM `changes` ORDER BY `playbook` ASC"), 'playbook');
$templates = reindex_arr_by_col(db_fetch_assocs("SELECT DISTINCT `jobs`.`job_template_id`, `jobs`.`name` FROM `changes` LEFT JOIN `jobs` ON `jobs`.`job` = `changes`.`job` ORDER BY `jobs`.`name` ASC"), 'job_template_id');
$modules = reindex_col(db_fetch_assocs("SELECT DISTINCT `task_action` FROM `changes` ORDER BY `task_action` ASC"), 'task_action');
$types = array('run' => 'Run Mode', 'check' => 'Check Mode');

$invs = db_fetch_assocs("SELECT DISTINCT `jobs`.`inventory` FROM `changes` LEFT JOIN `jobs` ON `jobs`.`job` = `changes`.`job` ORDER BY `jobs`.`inventory` ASC");
$inventories = array();
foreach ($invs as $i) {
	$i = explode('-', $i['inventory']);
	$id = array_pop($i);
	$inventories[$id] = implode('-', $i);
}

$pros = db_fetch_assocs("SELECT DISTINCT `jobs`.`project` FROM `changes` LEFT JOIN `jobs` ON `jobs`.`job` = `changes`.`job` ORDER BY `jobs`.`project` ASC");
$projects = array();
foreach ($pros as $i) {
	$i = explode('-', $i['project']);
	$id = array_pop($i);
	$projects[$id] = implode('-', $i);
}





$filters = array();
$host = '';
$playbook = '';
$template = '';
$module = '';
$type = '';
$inventory = '';
$project = '';

$csearch = '';

if (isset($_GET['clear'])) {
	if ($_GET['clear'] == 'host') {
		unset($_SESSION['changes_host']);
	}
	if ($_GET['clear'] == 'playbook') {
		unset($_SESSION['changes_playbook']);
	}
	if ($_GET['clear'] == 'csearch') {
		unset($_SESSION['changes_csearch']);
	}
	if ($_GET['clear'] == 'template') {
		unset($_SESSION['changes_template']);
	}
	if ($_GET['clear'] == 'module') {
		unset($_SESSION['changes_module']);
	}
	if ($_GET['clear'] == 'type') {
		unset($_SESSION['changes_type']);
	}
	if ($_GET['clear'] == 'inventory') {
		unset($_SESSION['changes_inventory']);
	}
	if ($_GET['clear'] == 'project') {
		unset($_SESSION['changes_project']);
	}



	header("Location: /changes/");
	exit;
}

if (isset($_GET['action']) && $_GET['action'] == 'addfilter') {
	if (isset($_GET['host'])) {
		if (intval($_GET['host']) == $_GET['host'] && isset($hosts[intval($_GET['host'])])) {
			$_SESSION['changes_host'] = intval($_GET['host']);
		} else {
			unset($_SESSION['changes_host']);
		}
	}
	if (isset($_GET['playbook'])) {
		if (in_array($_GET['playbook'], $playbooks)) {
			$_SESSION['changes_playbook'] = sql_clean_playbook($_GET['playbook']);
		} else {
			unset($_SESSION['changes_playbook']);
		}
	}
	if (isset($_GET['template'])) {
		if (intval($_GET['template']) == $_GET['template'] && isset($templates[intval($_GET['template'])])) {
			$_SESSION['changes_template'] = intval($_GET['template']);
		} else {
			unset($_SESSION['changes_template']);
		}
	}
	if (isset($_GET['module'])) {
		if (in_array($_GET['module'], $modules)) {
			$_SESSION['changes_module'] = sql_clean_playbook($_GET['module']);
		} else {
			unset($_SESSION['changes_module']);
		}
	}
	if (isset($_GET['type'])) {
		if (isset($types[$_GET['type']])) {
			$_SESSION['changes_type'] = sql_clean_playbook($_GET['type']);
		} else {
			unset($_SESSION['changes_type']);
		}
	}
	if (isset($_GET['inventory'])) {
		if (isset($inventories[$_GET['inventory']])) {
			$_SESSION['changes_inventory'] = intval($_GET['inventory']);
		} else {
			unset($_SESSION['changes_inventory']);
		}
	}
	if (isset($_GET['project'])) {
		if (isset($projects[$_GET['project']])) {
			$_SESSION['changes_project'] = intval($_GET['project']);
		} else {
			unset($_SESSION['changes_project']);
		}
	}

	if (isset($_GET['csearch'])) {
		if ($_GET['csearch'] == '') {
			unset($_SESSION['changes_csearch']);
		} else {
			$_SESSION['changes_csearch'] = sql_clean_csearch($_GET['csearch']);
		}
	}
	header("Location: /changes/");
	exit;
}

// Create filters
if (isset($_SESSION['changes_host']) && $_SESSION['changes_host'] != '') {
	$filters[] = " `changes`.`host` = " . intval($_SESSION['changes_host']);
	$host = $_SESSION['changes_host'];
}
if (isset($_SESSION['changes_playbook']) && $_SESSION['changes_playbook'] != '') {
	$filters[] = " `changes`.`playbook` = '" . sql_clean_playbook($_SESSION['changes_playbook']) . "'";
	$playbook = $_SESSION['changes_playbook'];
}
if (isset($_SESSION['changes_template']) && $_SESSION['changes_template'] != '') {
	$filters[] = " `jobs`.`job_template_id` = " . intval($_SESSION['changes_template']);
	$template = $_SESSION['changes_template'];
}
if (isset($_SESSION['changes_module']) && $_SESSION['changes_module'] != '') {
	$filters[] = " `changes`.`task_action` = '" . sql_clean_hostname($_SESSION['changes_module']) . "'";
	$module = $_SESSION['changes_module'];
}
if (isset($_SESSION['changes_type']) && $_SESSION['changes_type'] != '') {
	$filters[] = " `jobs`.`job_type` = '" . sql_clean_code($_SESSION['changes_type']) . "'";
	$type = $_SESSION['changes_type'];
}
if (isset($_SESSION['changes_inventory']) && $_SESSION['changes_inventory'] != '') {
	$filters[] = " `jobs`.`inventory` LIKE '%-" . intval($_SESSION['changes_inventory']) . "'";
	$inventory = $_SESSION['changes_inventory'];
}
if (isset($_SESSION['changes_project']) && $_SESSION['changes_project'] != '') {
	$filters[] = " `jobs`.`project` LIKE '%-" . intval($_SESSION['changes_project']) . "'";
	$project = $_SESSION['changes_project'];
}
if (isset($_SESSION['changes_csearch']) && $_SESSION['changes_csearch'] != '') {
	$s = sql_clean_csearch($_SESSION['changes_csearch']);
	$c = explode(' ', $s);
	foreach ($c as $s) {
		$filters[] = " (`res` LIKE '%$s%' OR `task_action` LIKE '%$s%' OR `task` LIKE '%$s%' OR `role` LIKE '%$s%' OR `play` LIKE '%$s%')";
	}
	$csearch = $_SESSION['changes_csearch'];
}

if (!empty($filters)) {
	$filters = "WHERE " . implode(' AND ', $filters);
} else {
	$filters = '';
}

$changes = db_fetch_assocs("SELECT `changes`.*, `jobs`.`job_type` FROM `changes` LEFT JOIN `jobs` ON `jobs`.`job` = `changes`.`job` $filters ORDER BY `changes`.`time` DESC LIMIT 0,100");
echo $twig->render('changes.html', array_merge($twigarr, array(
		'changes' => $changes, 'hosts' => $h, 'playbooks' => $playbooks, 'templates' => $templates, 'modules' => $modules, 'types' => $types, 'inventories' => $inventories, 'projects' => $projects, 
		'host' => $host, 'playbook' => $playbook, 'template' => $template, 'module' => $module, 'type' => $type, 'inventory' => $inventory, 'project' => $project, 'csearch' => $csearch)));




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

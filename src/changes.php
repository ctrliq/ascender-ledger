<?php
include_once('includes/global.php');

if (empty($account['view_changes']) && empty($account['super'])) {
	header("Location: /\n\n");
	exit;
}

if (isset($_GET['action']) && $_GET['action'] == 'view' && isset($_GET['change']) && intval($_GET['change'])) {
	$change = db_fetch_assoc_prepare('SELECT * FROM `changes` WHERE `id` = ?', array(intval($_GET['change'])));
	if (isset($change['id']) && $change['id']) {
		// Changes record the server's install UUID now, and its name before that
		$aserver = db_fetch_assoc_prepare('SELECT * FROM `servers` WHERE `uuid` = ? OR `name` = ? ORDER BY (`uuid` = ?) DESC LIMIT 1', array($change['server'], $change['server'], $change['server']));
		// Job numbers are per Ascender server; jobs recorded before that was tracked have no server
		$job = db_fetch_assoc_prepare("SELECT * FROM `jobs` WHERE `job` = ? AND (`server` = ? OR `server` = '') ORDER BY `server` DESC LIMIT 1", array($change['job'], $change['server']));
		$res = $change['res'];
		echo $twig->render('change.html', array_merge($twigarr, array('change' => $change, 'aserver' => $aserver, 'hostname' => host_name($change['host']), 'res' => $res, 'job' => $job)));
		exit;
	}
}

$templates = $playbooks = $roles = $modules = $inventories = $projects = array();
$types = array('run' => 'Run Mode', 'check' => 'Check Mode');

// The filter menus are only needed to draw the page and check new filters, not for each page of table rows
if (!dt_is_request()) {
	/*
		The filter menus.  Templates, inventories and projects come from the jobs that recorded
		changes, found through the changes.job index rather than by joining every change.
	*/
	$templates = array();
	foreach (db_fetch_assocs("SELECT DISTINCT `j`.`job_template_id`, `j`.`name` FROM `jobs` AS `j` WHERE EXISTS (SELECT 1 FROM `changes` AS `c` WHERE `c`.`job` = `j`.`job` AND (`c`.`server` = `j`.`server` OR `j`.`server` = '')) ORDER BY `j`.`name` ASC") as $t) {
		if ($t['job_template_id']) {
			$templates[$t['job_template_id']] = $t['name'];
		}
	}
	$playbooks = array_values(array_filter(reindex_col(db_fetch_assocs("SELECT DISTINCT `playbook` FROM `changes` ORDER BY `playbook` ASC"), 'playbook'), 'strlen'));
	$roles     = array_values(array_filter(reindex_col(db_fetch_assocs("SELECT DISTINCT `role` FROM `changes` ORDER BY `role` ASC"), 'role'), 'strlen'));
	$modules   = array_values(array_filter(reindex_col(db_fetch_assocs("SELECT DISTINCT `task_action` FROM `changes` ORDER BY `task_action` ASC"), 'task_action'), 'strlen'));
	$types     = array('run' => 'Run Mode', 'check' => 'Check Mode');

	$inventories = array();
	foreach (db_fetch_assocs("SELECT DISTINCT `j`.`inventory` FROM `jobs` AS `j` WHERE EXISTS (SELECT 1 FROM `changes` AS `c` WHERE `c`.`job` = `j`.`job` AND (`c`.`server` = `j`.`server` OR `j`.`server` = '')) ORDER BY `j`.`inventory` ASC") as $i) {
		$i = explode('-', $i['inventory']);
		$id = array_pop($i);
		if (intval($id)) {
			$inventories[intval($id)] = implode('-', $i);
		}
	}

	$projects = array();
	foreach (db_fetch_assocs("SELECT DISTINCT `j`.`project` FROM `jobs` AS `j` WHERE EXISTS (SELECT 1 FROM `changes` AS `c` WHERE `c`.`job` = `j`.`job` AND (`c`.`server` = `j`.`server` OR `j`.`server` = '')) ORDER BY `j`.`project` ASC") as $i) {
		$i = explode('-', $i['project']);
		$id = array_pop($i);
		if (intval($id)) {
			$projects[intval($id)] = implode('-', $i);
		}
	}
}

$filter_names = array('host', 'csearch', 'template', 'playbook', 'role', 'module', 'type', 'inventory', 'project');

if (isset($_GET['clear'])) {
	if (in_array($_GET['clear'], $filter_names, true)) {
		unset($_SESSION['changes_' . $_GET['clear']]);
	}
	header("Location: /changes/");
	exit;
}

if (isset($_GET['action']) && $_GET['action'] == 'addfilter') {
	if (isset($_GET['host'])) {
		if (host_name($_GET['host']) != '') {
			$_SESSION['changes_host'] = intval($_GET['host']);
		} else {
			unset($_SESSION['changes_host']);
		}
	}
	if (isset($_GET['playbook'])) {
		if (in_array($_GET['playbook'], $playbooks, true)) {
			$_SESSION['changes_playbook'] = $_GET['playbook'];
		} else {
			unset($_SESSION['changes_playbook']);
		}
	}
	if (isset($_GET['template'])) {
		if (isset($templates[intval($_GET['template'])])) {
			$_SESSION['changes_template'] = intval($_GET['template']);
		} else {
			unset($_SESSION['changes_template']);
		}
	}
	if (isset($_GET['role'])) {
		if (in_array($_GET['role'], $roles, true)) {
			$_SESSION['changes_role'] = $_GET['role'];
		} else {
			unset($_SESSION['changes_role']);
		}
	}
	if (isset($_GET['module'])) {
		if (in_array($_GET['module'], $modules, true)) {
			$_SESSION['changes_module'] = $_GET['module'];
		} else {
			unset($_SESSION['changes_module']);
		}
	}
	if (isset($_GET['type']) && is_string($_GET['type'])) {
		if (isset($types[$_GET['type']])) {
			$_SESSION['changes_type'] = $_GET['type'];
		} else {
			unset($_SESSION['changes_type']);
		}
	}
	if (isset($_GET['inventory'])) {
		if (isset($inventories[intval($_GET['inventory'])])) {
			$_SESSION['changes_inventory'] = intval($_GET['inventory']);
		} else {
			unset($_SESSION['changes_inventory']);
		}
	}
	if (isset($_GET['project'])) {
		if (isset($projects[intval($_GET['project'])])) {
			$_SESSION['changes_project'] = intval($_GET['project']);
		} else {
			unset($_SESSION['changes_project']);
		}
	}
	if (isset($_GET['csearch']) && is_string($_GET['csearch'])) {
		$s = trim(substr($_GET['csearch'], 0, 128));
		if ($s == '') {
			unset($_SESSION['changes_csearch']);
		} else {
			$_SESSION['changes_csearch'] = $s;
		}
	}
	header("Location: /changes/");
	exit;
}

$current = array();
foreach ($filter_names as $f) {
	$current[$f] = (isset($_SESSION['changes_' . $f]) ? $_SESSION['changes_' . $f] : '');
}

if (dt_is_request()) {
	$where = array();
	$params = array();

	if ($current['host'] !== '') {
		$where[] = '`c`.`host` = ?';
		$params[] = intval($current['host']);
	}
	if ($current['template'] !== '') {
		$where[] = '`j`.`job_template_id` = ?';
		$params[] = intval($current['template']);
	}
	if ($current['playbook'] !== '') {
		$where[] = '`c`.`playbook` = ?';
		$params[] = $current['playbook'];
	}
	if ($current['role'] !== '') {
		$where[] = '`c`.`role` = ?';
		$params[] = $current['role'];
	}
	if ($current['module'] !== '') {
		$where[] = '`c`.`task_action` = ?';
		$params[] = $current['module'];
	}
	if ($current['type'] !== '') {
		$where[] = '`j`.`job_type` = ?';
		$params[] = $current['type'];
	}
	if ($current['inventory'] !== '') {
		$where[] = '`j`.`inventory` LIKE ?';
		$params[] = '%-' . intval($current['inventory']);
	}
	if ($current['project'] !== '') {
		$where[] = '`j`.`project` LIKE ?';
		$params[] = '%-' . intval($current['project']);
	}
	if ($current['csearch'] !== '') {
		foreach (preg_split('/\s+/', $current['csearch']) as $s) {
			if ($s == '') {
				continue;
			}
			$where[] = '(`c`.`res` LIKE ? OR `c`.`task_action` LIKE ? OR `c`.`task` LIKE ? OR `c`.`role` LIKE ? OR `c`.`play` LIKE ?)';
			for ($i = 0; $i < 5; $i++) {
				$params[] = '%' . dt_like_escape($s) . '%';
			}
		}
	}

	dt_serve(
		'`c`.`id`, `c`.`time`, `c`.`host`, `h`.`hostname`, `c`.`playbook`, `c`.`task`, `c`.`task_action`, `j`.`job_type`',
		'FROM `changes` AS `c` LEFT JOIN `hosts` AS `h` ON `h`.`id` = `c`.`host` LEFT JOIN `jobs` AS `j` ON ' . jobs_join_on('j', 'c'),
		$where,
		$params,
		array(
			1 => array('`c`.`time`'),
			2 => array('`h`.`hostname`'),
			3 => array('`c`.`playbook`'),
			4 => array('`c`.`task`'),
			5 => array('`c`.`task_action`'),
		),
		'`c`.`time` DESC',
		array('`h`.`hostname`', '`c`.`task`', '`c`.`task_action`', '`c`.`playbook`', '`c`.`role`'),
		function ($r) {
			return array(
				'id'          => intval($r['id']),
				'date'        => date('m/d/Y H:i', $r['time']),
				'host_id'     => intval($r['host']),
				'hostname'    => $r['hostname'],
				'playbook'    => $r['playbook'],
				'task'        => $r['task'],
				'task_action' => $r['task_action'],
				'check'       => ($r['job_type'] == 'check'),
			);
		},
		array('`c`.`id`')
	);
}

echo $twig->render('changes.html', array_merge($twigarr, array(
		'playbooks' => $playbooks, 'templates' => $templates, 'modules' => $modules, 'types' => $types, 'inventories' => $inventories, 'projects' => $projects,
		'roles' => $roles, 'current' => $current, 'hostname' => host_name($current['host']))));

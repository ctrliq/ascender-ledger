<?php

function stripUnwantedTagsAndAttrs($html_str){
	$xml = new DOMDocument();
  //Suppress warnings: proper error handling is needed
	libxml_use_internal_errors(false);
  //List the tags you want to allow here, NOTE you MUST allow html and body otherwise entire string will be cleared
	$allowed_tags = array("b", "br", "em", "i", "li", "ol", "u", "ul", "p");
  //List the attributes you want to allow here
	$allowed_attrs = array ();
	if (!strlen($html_str)){return false;}
	if ($xml->loadHTML($html_str, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD)){
	  foreach ($xml->getElementsByTagName("*") as $tag){
		if (!in_array($tag->tagName, $allowed_tags)){
		  $tag->parentNode->removeChild($tag);
		}else{
		  foreach ($tag->attributes as $attr){
			if (!in_array($attr->nodeName, $allowed_attrs)){
			  $tag->removeAttribute($attr->nodeName);
			}
		  }
		}
	  }
	}
	$d = $xml->saveHTML();
	return strip_tags($d);
}

function random_str($length) {
	$keyspace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
	$str = '';
	$max = mb_strlen($keyspace, '8bit') - 1;
	for ($i = 0; $i < $length; ++$i) {
		$str .= $keyspace[random_int(0, $max)];
	}
	return $str;
}

function reindex_col($a, $c) {
	$d = array();

	if (!empty($a)) {
		foreach ($a as $b) {
			$d[] = $b[$c];
		}
	}

	return $d;
}

function reindex_arr_by_id($a) {
	$d = array();

	if (!empty($a)) {
		foreach ($a as $b) {
			$d[$b['id']] = $b;
		}
	}

	return $d;
}

function reindex_arr_by_col($a, $c) {
	$d = array();

	if (!empty($a)) {
		foreach ($a as $b) {
			$d[$b[$c]] = $b;
		}
	}

	return $d;
}

function reindex_arr_by_id_col($a, $c) {
	$d = array();

	if (!empty($a)) {
		foreach ($a as $b) {
			$d[$b['id']] = $b[$c];
		}
	}

	return $d;
}

function ppre($arr) {
	print "<pre>";
	print_r($arr);
	print "</pre>";
}

function build_filter($filters) {

	$sql = "SELECT DISTINCT f.`host` FROM `facts` as f\n";
	$p = array();
	$c = 0;
	foreach ($filters as $f) {
		$c++;

		$sql .= "INNER JOIN (SELECT * FROM `facts` WHERE `fact`= ? AND ";

		$p[] = $f['fact'];
		switch ($f['compare']) {
			case 'eq':
				$sql .= '`data` = ?';
				$p[] = $f['value'];
				break;
			case 'ne':
				$sql .= '`data` != ?';
				$p[] = $f['value'];
				break;
			case 'gt':
				$sql .= 'cast(`data` as signed) > ?';
				$p[] = $f['value'];
				break;
			case 'lt':
				$sql .= '`cast(data` as signed) < ?';
				$p[] = $f['value'];
				break;
			case 'contains':
				$sql .= '`data` LIKE ?';
				$p[] = "%" . $f['value'] . "%";
				break;
			case 'starts':
				$sql .= '`data` LIKE ?';
				$p[] = "%" . $f['value'];
				break;
			case 'ends':
				$sql .= '`data` LIKE ?';
				$p[] = "%" . $f['value'];
				break;
		}
		$sql .= ') as f' . $c . ' ON f.host = f' . $c . ".host\n";
	}

	return array($sql, $p);
}

function build_report ($id) {
	$id = intval($id);
	$report = new Report($id);
	if ($report->id) {
		$w = build_filter($report->filters);
		
		$sql = $w[0];
		$pr = $w[1];

		$hosts = db_fetch_assocs_prepare($sql, $pr);
    }

    $data = array();
    foreach ($hosts as $h) {
        $facts = db_fetch_assocs_prepare('SELECT `fact`,`data` FROM `facts` WHERE `host` = ?', array($h['host']));
        $x = 0;
        $data[$h['host']] = array();
        if (count($facts)) {
            foreach ($report->columns as $d => $k) {
                $data[$h['host']][$x] = '';
                foreach ($facts as $f) {
                    if (is_array($k)) {
                        if (in_array($f['fact'], $k)) {
                            if ($data[$h['host']][$x] != '') {
                                $data[$h['host']][$x] .= ', ';
                            }
                            $data[$h['host']][$x] .= $f['data'];
                        }
                    } else {
                        if ($k == $f['fact']) {
                            $data[$h['host']][$x] = $f['data'];
                        }
                    }
                }
                $x++;
            }
        }
        $e = true;
        foreach ($data[$h['host']] as $d) {
            if ($d != '') {
                $e = false;
            }
        }
        if ($e) {
            unset($data[$h['host']]);
        }
    }
    return $data;
}

/*
	The columns a changes report shows.  Unlike a facts report, where the columns are the facts
	the user picked, a changes report always shows the same fields the changes page lists, so the
	report is that page's table with its filters saved and schedulable.
*/
function changes_report_columns() {
	return array(
		'Time'         => 'time',
		'Host'         => 'hostname',
		'Job Template' => 'template',
		'Playbook'     => 'playbook',
		'Role'         => 'role',
		'Task'         => 'task',
		'Module'       => 'task_action',
	);
}

/*
	The fields a changes report can be filtered on.  These are the filter menus of the changes
	page, plus a time window, which is what makes a scheduled report useful: "the changes of the
	last 24 hours" rather than everything ever recorded.
*/
function changes_report_filter_fields() {
	return array(
		'host'      => 'Host',
		'template'  => 'Job Template',
		'playbook'  => 'Playbook',
		'role'      => 'Role',
		'module'    => 'Module',
		'type'      => 'Job Type',
		'inventory' => 'Inventory',
		'project'   => 'Project',
		'search'    => 'Search',
		'hours'     => 'Last Hours',
	);
}

/*
	The filter fields whose value is an id or a count rather than a name.
*/
function changes_report_numeric_filter_fields() {
	return array('host', 'template', 'inventory', 'project', 'hours');
}

/*
	Turns the filters of a changes report into a WHERE clause and its parameters.  The comparisons
	are the ones the changes page makes, with the values bound rather than interpolated.
*/
function build_changes_filter($filters) {
	$where = array();
	$p = array();

	foreach ($filters as $f) {
		if (!isset($f['field']) || !isset($f['value'])) {
			continue;
		}

		switch ($f['field']) {
			case 'host':
				$where[] = '`changes`.`host` = ?';
				$p[] = intval($f['value']);
				break;
			case 'template':
				$where[] = '`jobs`.`job_template_id` = ?';
				$p[] = intval($f['value']);
				break;
			case 'playbook':
				$where[] = '`changes`.`playbook` = ?';
				$p[] = $f['value'];
				break;
			case 'role':
				$where[] = '`changes`.`role` = ?';
				$p[] = $f['value'];
				break;
			case 'module':
				$where[] = '`changes`.`task_action` = ?';
				$p[] = $f['value'];
				break;
			case 'type':
				$where[] = '`jobs`.`job_type` = ?';
				$p[] = $f['value'];
				break;
			case 'inventory':
				$where[] = '`jobs`.`inventory` LIKE ?';
				$p[] = '%-' . intval($f['value']);
				break;
			case 'project':
				$where[] = '`jobs`.`project` LIKE ?';
				$p[] = '%-' . intval($f['value']);
				break;
			case 'search':
				foreach (explode(' ', $f['value']) as $s) {
					if ($s == '') {
						continue;
					}
					$where[] = '(`changes`.`res` LIKE ? OR `changes`.`task_action` LIKE ? OR `changes`.`task` LIKE ? OR `changes`.`role` LIKE ? OR `changes`.`play` LIKE ?)';
					for ($i = 0; $i < 5; $i++) {
						$p[] = '%' . $s . '%';
					}
				}
				break;
			case 'hours':
				$where[] = '`changes`.`time` >= ?';
				$p[] = time() - (intval($f['value']) * 3600);
				break;
		}
	}

	if (empty($where)) {
		return array('', $p);
	}

	return array('WHERE ' . implode(' AND ', $where), $p);
}

/*
	Builds a changes report, returning the same shape as build_report: one row per change, each
	holding the cell values of changes_report_columns() in order, so the report and email
	templates render both kinds of report the same way.
*/
function build_changes_report($id, $limit = 1000) {
	$id = intval($id);
	$report = new Report($id);
	$data = array();

	if (!$report->id || $report->type != 'changes') {
		return $data;
	}

	$w = build_changes_filter($report->filters);

	$sql = "SELECT `changes`.`id`, `changes`.`time`, `changes`.`playbook`, `changes`.`role`, `changes`.`task`, `changes`.`task_action`,
					`hosts`.`hostname`, `jobs`.`name` as `template`
			FROM `changes`
			LEFT JOIN `jobs` ON `jobs`.`job` = `changes`.`job`
			LEFT JOIN `hosts` ON `hosts`.`id` = `changes`.`host`
			" . $w[0] . "
			ORDER BY `changes`.`time` DESC
			LIMIT " . intval($limit);

	$changes = db_fetch_assocs_prepare($sql, $w[1]);

	foreach ($changes as $c) {
		$data[$c['id']] = array(
			date('m/d/Y H:i', $c['time']),
			$c['hostname'],
			$c['template'],
			$c['playbook'],
			$c['role'],
			$c['task'],
			$c['task_action'],
		);
	}

	return $data;
}

/*
	The values the filter menus of a changes report offer, built from the changes that were
	actually recorded, the same way the changes page builds its own menus.
*/
function changes_report_filter_options() {
	$hosts = array();
	foreach (db_fetch_assocs('SELECT `id`, `hostname` FROM `hosts` ORDER BY `hostname`') as $h) {
		$hosts[$h['id']] = $h['hostname'];
	}

	$templates = array();
	foreach (db_fetch_assocs("SELECT DISTINCT `jobs`.`job_template_id`, `jobs`.`name` FROM `changes` LEFT JOIN `jobs` ON `jobs`.`job` = `changes`.`job` ORDER BY `jobs`.`name` ASC") as $t) {
		if ($t['job_template_id']) {
			$templates[$t['job_template_id']] = $t['name'];
		}
	}

	$inventories = array();
	foreach (db_fetch_assocs("SELECT DISTINCT `jobs`.`inventory` FROM `changes` LEFT JOIN `jobs` ON `jobs`.`job` = `changes`.`job` ORDER BY `jobs`.`inventory` ASC") as $i) {
		$i = explode('-', $i['inventory']);
		$id = array_pop($i);
		if (intval($id)) {
			$inventories[$id] = implode('-', $i);
		}
	}

	$projects = array();
	foreach (db_fetch_assocs("SELECT DISTINCT `jobs`.`project` FROM `changes` LEFT JOIN `jobs` ON `jobs`.`job` = `changes`.`job` ORDER BY `jobs`.`project` ASC") as $i) {
		$i = explode('-', $i['project']);
		$id = array_pop($i);
		if (intval($id)) {
			$projects[$id] = implode('-', $i);
		}
	}

	return array(
		'host'      => $hosts,
		'template'  => $templates,
		'playbook'  => changes_report_option_list("SELECT DISTINCT `playbook` FROM `changes` ORDER BY `playbook` ASC", 'playbook'),
		'role'      => changes_report_option_list("SELECT DISTINCT `role` FROM `changes` ORDER BY `role` ASC", 'role'),
		'module'    => changes_report_option_list("SELECT DISTINCT `task_action` FROM `changes` ORDER BY `task_action` ASC", 'task_action'),
		'type'      => array('run' => 'Run Mode', 'check' => 'Check Mode'),
		'inventory' => $inventories,
		'project'   => $projects,
	);
}

/*
	Reads a column of distinct values into an option list where the value and the label are the
	same, which is how the changes table stores playbooks, roles and modules.
*/
function changes_report_option_list($sql, $column) {
	$options = array();
	foreach (db_fetch_assocs($sql) as $row) {
		if ($row[$column] != '') {
			$options[$row[$column]] = $row[$column];
		}
	}
	return $options;
}

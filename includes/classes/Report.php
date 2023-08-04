<?php

class Report {
	var $id = 0;
	var $owner = 0;
	var $name = 'New Report';
	var $created = 0;
	var $filters = array();
	var $columns = array('Hostname' => 'ansible_hostname');
	var $sortc = 0;
	var $sortd = 'asc';
	var $role = '';

	function __construct($id = 0) {
		if ($id) {
			$this->retrieve($id);
		}
   	}

	function retrieve($id) {
		global $account;

		if (isset($account['cli'])) {
			$u = db_fetch_assoc_prepare("SELECT * FROM `reports` WHERE `reports`.`id` = ?", array($id));
		} else {
			$u = db_fetch_assoc_prepare("SELECT `reports`.*, `reports_perms`.`role` FROM `reports`
								LEFT JOIN `reports_perms` ON `reports_perms`.`report` = `reports`.`id`
								WHERE `reports_perms`.`user` = ? AND `reports`.`id` = ?
								ORDER BY `reports`.`name`", array($account['id'], $id));
		}
		$this->pop_class($u);
		return $u;
	}

	function set_owner($owner) {
		$this->owner = intval($owner);
	}

	function set_name($name) {
		$this->name = $this->clean_name($name);
	}

	function set_created($created) {
		$this->created = intval($created);
	}

	function set_columns($columns) {
		$this->columns = $columns;
	}

	function set_filters($filters) {
		$this->filters = $filters;
	}

	function set_sortc($sortc) {
		$this->sortc = intval($sortc);
	}

	function set_sortd($sortd) {
		if ($sortd == 'asc') {
			$this->sortd = 'asc';
		} else {
			$this->sortd = 'desc';
		}
	}

	function pop_class($u) {
		if (isset($u['id'])) {
			$this->id      = $u['id'];
			$this->owner   = $u['owner'];
			$this->name    = $u['name'];
			$this->created = $u['created'];
			$this->filters = unserialize(base64_decode($u['filters']));
			$this->columns = unserialize(base64_decode($u['columns']));
			$this->sortc   = $u['sortc'];
			$this->sortd   = $u['sortd'];
			$this->role    = (isset($u['role']) ? $u['role'] : 'view');
		}
	}

	function add_filter($fact, $compare, $value) {
		global $compares;

		$value = $this->clean_filter($value);

		$allfacts = db_fetch_assocs('SELECT DISTINCT `fact` FROM facts');
		$afs = array();
		foreach ($allfacts as $f) {
			$afs[] = $f['fact'];
		}

		if (!in_array($fact, $afs)) {
			return false;
		}

		if (!isset($compares[$compare])) {
			return false;
		}

		$this->filters[] = array('fact' => $fact, 'compare' => $compare, 'value' => $value);
		$this->save();
	}

	function remove_filter($i) {
		unset($this->filters[$i]);
		$this->save();
	}

	function add_column($display, $facts) {
		$display = $this->clean_column($display);
		$allfacts = db_fetch_assocs('SELECT DISTINCT `fact` FROM facts');
		$afs = array();
		foreach ($allfacts as $f) {
			$afs[] = $f['fact'];
		}

		for ($a = 0; $a < count($facts); $a++) {
			if (!in_array($facts[$a], $afs)) {
				unset($facts[$a]);
			}
		}

		if (count($facts) > 1) {
			$this->columns[$display] = $facts;
		} else {
			$this->columns[$display] = $facts[0];
		}
		$this->save();
	}

	function remove_column($i) {
		$a = 0;
		foreach ($this->columns as $k => $d) {
			if ($a == $i) {
				unset($this->columns[$k]);
			}
			$a++;
		}
		$this->save();
	}

	function move_column_up($i) {
		$new = array();
		$a = 0;
		$ok = '';
		$od = '';
		foreach ($this->columns as $k => $d) {
			if ($a == $i - 1) {
				$ok = $k;
				$od = $d;
			} else if ($a == $i) {
				$n[$k] = $d;
				$n[$ok] = $od;
			} else {
				$n[$k] = $d;
			}
			$a++;
		}
		$this->columns = $n;
		$this->save();
	}

	function move_column_down($i) {
		$new = array();
		$a = 0;
		$ok = '';
		$od = '';
		foreach ($this->columns as $k => $d) {
			if ($a == $i) {
				$ok = $k;
				$od = $d;
			} else if ($a == $i + 1) {
				$n[$k] = $d;
				$n[$ok] = $od;
			} else {
				$n[$k] = $d;
			}
			$a++;
		}
		$this->columns = $n;
		$this->save();
	}

	function remove_user($user) {
		$user = intval($user);
		if ($this->id && $user > 0) {
			db_execute_prepare('DELETE FROM `reports_perms` WHERE `report` = ? AND `user` = ?', array($this->id, $user));
		}
	}

	function add_user($user, $role) {
		$user = intval($user);
		$roles = array('owner', 'edit', 'view');
		if ($this->id && $user > 0 && in_array($role, $roles)) {
			db_execute_prepare('INSERT INTO `reports_perms` (`report`, `user`, `role`) VALUES (?, ?, ?)', array($this->id, $user, $role));
		}
	}

	function clean_name($text) {
		return preg_replace('/[^A-Za-z0-9 \-_\.:]/', '', $text);
	}

	function clean_filter($text) {
		return preg_replace('/[^A-Za-z0-9 \-_\.:]/', '', $text);
	}

	function clean_column($text) {
		return preg_replace('/[^A-Za-z0-9\-_\.:]/', '', $text);
	}


	function delete() {
		if ($role == "owner" || $role == "admin") {
			db_execute_prepare('DELETE FROM `reports_perms` WHERE `report` = ?', array($this->id));
			db_execute_prepare('DELETE FROM `reports` WHERE `id` = ?', array($this->id));
			return true;
		} else {
			return false;
		}
	}

	function save() {
		if ($this->id) {
			db_execute_prepare('UPDATE `reports` SET `owner` = ?, `name` = ?, `created` = ?, `filters` = ?, `columns` = ?, `sortc` = ?, `sortd` = ? WHERE `id` = ?',
						array($this->owner, $this->name, $this->created, base64_encode(serialize($this->filters)), base64_encode(serialize($this->columns)), $this->sortc, $this->sortd, $this->id));
		} else {
			$id = db_execute_prepare('INSERT INTO `reports` (`owner`, `name`, `created`, `filters`, `columns`, `sortc`, `sortd`) VALUES (?, ?, ?, ?, ?, ?, ?)',
						array($this->owner, $this->name, $this->created,  base64_encode(serialize($this->filters)),base64_encode(serialize($this->columns)), $this->sortc, $this->sortd));
			$this->id = $id;
			db_execute_prepare('INSERT INTO `reports_perms` (`report`, `user`, `role`) VALUES (?, ?, ?)',
						array($this->id, $this->owner, 'owner'));
		}
	}
}
<?php

class User {
	var $id = 0;
	var $name = '';
	var $email = '';
	var $username = '';
	var $password = '';
	var $enabled = 1;
	var $registered = 0;
	var $code = '';
	var $code_expires = 0;
	var $super = 0;
	var $perms = array();

	function __construct($id = 0) {
		if ($id) {
			$this->retrieve($id);
		}
   	}

	function retrieve($id) {
		$u = db_fetch_assoc_prepare('SELECT * FROM `users` WHERE id = ?', array(intval($id)));
		$this->pop_class($u);
		return $this->with_perms($u);
	}

	function retrieve_by_code($code) {
		$code = $this->clean_code($code);
		if ($code == '') {
			return false;
		}
		// Codes issued before they could expire (code_expires 0) keep working as they did
		$u = db_fetch_assoc_prepare('SELECT * FROM `users` WHERE `code` = ? AND (`code_expires` = 0 OR `code_expires` > ?)', array($code, time()));
		$this->pop_class($u);
		return $this->with_perms($u);
	}

	function retrieve_by_username($username) {
		$username = $this->clean_username($username);
		$u = db_fetch_assoc_prepare('SELECT * FROM `users` WHERE `username` = ?', array($username));
		$this->pop_class($u);
		return $this->with_perms($u);
	}

	/*
		A sign-in code for an invite or a password reset, valid for $ttl seconds.  '' clears it.
	*/
	function set_code($code, $ttl = 0) {
		$this->code = $this->clean_code($code);
		$this->code_expires = ($this->code != '' && $ttl > 0 ? time() + intval($ttl) : 0);
	}

	function set_name($name) {
		$this->name = $this->clean_name($name);
	}

	function set_password($password) {
		// Hash the password first to allow for longer than 72 characters
		$password = hash_hmac('sha256', $password, FIRSTKEY);
		$this->password = password_hash($password, PASSWORD_DEFAULT);
	}

	function set_username($username) {
		$this->username = $this->clean_username(strtolower($username));
	}

	function set_email($email) {
		$this->email = $this->clean_username($email);
	}

	function set_enabled($enabled) {
		$this->enabled = intval($enabled);
	}

	function set_registered($registered) {
		$this->registered = intval($registered);
	}


	// Form fields can arrive as arrays (name[]=x); those clean to nothing rather than "Array"
	function clean_name($text) {
		return (is_scalar($text) ? preg_replace('/[^A-Za-z\- ]/', '', (string) $text) : '');
	}

	function clean_username($text) {
		return (is_scalar($text) ? preg_replace('/[^A-Za-z0-9\.@\-\+]/', '', (string) $text) : '');
	}

	function clean_code($text) {
		return (is_scalar($text) ? preg_replace('/[^A-Za-z0-9]/', '', (string) $text) : '');
	}


	/*
		The row as $account uses it: with the user's permissions, and super for "is an administrator"
	*/
	function with_perms($u) {
		if (is_array($u) && isset($u['id'])) {
			$u['perms'] = $this->perms;
			$u['super'] = $this->super;
		}
		return $u;
	}

	function pop_class($u) {
		if (isset($u['id'])) {
			$this->id = $u['id'];
			$this->registered = $u['registered'];
			$this->enabled = $u['enabled'];
			$this->name = $u['name'];
			$this->email = $u['email'];
			$this->username = $u['username'];
			$this->password = $u['password'];
			$this->code = $u['code'];
			$this->code_expires = (isset($u['code_expires']) ? intval($u['code_expires']) : 0);
			$this->perms = user_permissions($this->id);
			$this->super = (int) isset($this->perms['super']);

		}
	}

	/*
		The user's own permissions (not their teams'), as a list
	*/
	function own_permissions() {
		return array_column(db_fetch_assocs_prepare('SELECT `perm` FROM `user_permission` WHERE `user` = ?', array($this->id)), 'perm');
	}

	function set_permissions($perms) {
		db_execute_prepare('DELETE FROM `user_permission` WHERE `user` = ?', array($this->id));
		foreach ($perms as $p) {
			db_execute_prepare('INSERT IGNORE INTO `user_permission` (`user`, `perm`) VALUES (?, ?)', array($this->id, $p));
		}
	}

	// Team ids, enabled or not
	function teams() {
		return array_map('intval', array_column(db_fetch_assocs_prepare('SELECT `team` FROM `team_user` WHERE `user` = ?', array($this->id)), 'team'));
	}

	function set_teams($teams) {
		db_execute_prepare('DELETE FROM `team_user` WHERE `user` = ?', array($this->id));
		foreach ($teams as $t) {
			db_execute_prepare('INSERT IGNORE INTO `team_user` (`team`, `user`) SELECT `id`, ? FROM `teams` WHERE `id` = ?', array($this->id, intval($t)));
		}
	}

	function delete() {
		db_execute_prepare('DELETE FROM `user_permission` WHERE `user` = ?', array($this->id));
		db_execute_prepare('DELETE FROM `team_user` WHERE `user` = ?', array($this->id));
		db_execute_prepare('DELETE FROM `reports_perms` WHERE `user` = ?', array($this->id));
		db_execute_prepare('DELETE FROM `reports` WHERE `owner` = ?', array($this->id));
		db_execute_prepare('DELETE FROM `users` WHERE `id` = ?', array($this->id));
		db_execute_prepare('DELETE FROM `sessions` WHERE `user` = ?', array($this->id));
	}

	function save() {
		if ($this->id) {
			db_execute_prepare('UPDATE `users` SET `name` = ?, `email` = ?, `username` = ?, `password` = ?, `enabled` = ?, `registered` = ?, `code` = ?, `code_expires` = ? WHERE `id` = ?',
						array($this->name, $this->email, $this->username, $this->password, $this->enabled, $this->registered, $this->code, $this->code_expires, $this->id));
		} else {
			db_execute_prepare('INSERT INTO `users` (`name`, `email`, `username`, `password`, `enabled`, `registered`, `code`, `code_expires`) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
						array($this->name, $this->email, $this->username, $this->password, $this->enabled, $this->registered, $this->code, $this->code_expires));
			$this->id = intval(db_fetch_cell_prepare('SELECT `id` FROM `users` WHERE `username` = ?', 'id', array($this->username)));
		}
	}
}
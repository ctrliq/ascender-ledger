<?php

class User {
	var $id = 0;
	var $name = '';
	var $email = '';
	var $username = '';
	var $password = '';
	var $enabled = 1;
	var $view_changes = 1;
	var $view_facts = 1;
	var $registered = 0;
	var $code = '';
	var $super = 0;

	function __construct($id = 0) {
		if ($id) {
			$this->retrieve($id);
		}
   	}

	function retrieve($id) {
		$u = db_fetch_assoc_prepare('SELECT * FROM `users` WHERE id = ?', array(intval($id)));
		$this->pop_class($u);
		return $u;
	}

	function retrieve_by_code($code) {
		$code = $this->clean_code($code);
		$u = db_fetch_assoc_prepare('SELECT * FROM `users` WHERE `code` = ?', array($code));
		$this->pop_class($u);
		return $u;
	}

	function retrieve_by_username($username) {
		$username = $this->clean_username($username);
		$u = db_fetch_assoc_prepare('SELECT * FROM `users` WHERE `username` = ?', array($username));
		$this->pop_class($u);
		return $u;
	}

	function set_code($code) {
		$this->code = $this->clean_username($code);
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

	function set_super($super) {
		$this->super = intval($super);
	}

	function set_enabled($enabled) {
		$this->enabled = intval($enabled);
	}

	function set_registered($registered) {
		$this->registered = intval($registered);
	}

	function set_view_changes($value) {
		$this->view_changes = intval($value);
	}

	function set_view_facts($value) {
		$this->view_facts = intval($value);
	}


	function clean_name($text) {
		return preg_replace('/[^A-Za-z\- ]/', '', $text);
	}

	function clean_username($text) {
		return preg_replace('/[^A-Za-z0-9\.@\-\+]/', '', $text);
	}

	function clean_code($text) {
		return preg_replace('/[^A-Za-z0-9]/', '', $text);
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
			$this->super = $u['super'];
			$this->view_changes = $u['view_changes'];
			$this->view_facts = $u['view_facts'];

		}
	}

	function delete() {
		db_execute_prepare('DELETE FROM `reports_perms` WHERE `user` = ?', array($this->id));
		db_execute_prepare('DELETE FROM `reports` WHERE `owner` = ?', array($this->id));
		db_execute_prepare('DELETE FROM `users` WHERE `id` = ?', array($this->id));
		db_execute_prepare('DELETE FROM `sessions` WHERE `user` = ?', array($this->id));
	}

	function save() {
		if ($this->id) {
			db_execute_prepare('UPDATE `users` SET `name` = ?, `email` = ?, `username` = ?, `password` = ?, `enabled` = ?, `registered` = ?, `code` = ?, `super` = ?, `view_changes` = ?, `view_facts` = ? WHERE `id` = ?',
						array($this->name, $this->email, $this->username, $this->password, $this->enabled, $this->registered, $this->code, $this->super, $this->view_changes, $this->view_facts, $this->id));
		} else {
			db_execute_prepare('INSERT INTO `users` (`name`, `email`, `username`, `password`, `enabled`, `registered`, `code`, `super`, `view_changes`, `view_facts`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
						array($this->name, $this->email, $this->username, $this->password, $this->enabled, $this->registered, $this->code, $this->super, $this->view_changes, $this->view_facts));
		}
	}
}
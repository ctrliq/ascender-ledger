<?php

ini_set('session.cookie_lifetime', 60 * 60 * 24 * 7);

class SessionSaveHandler {
	protected $savePath;
	protected $sessionName;
	protected $timeout = 60 * 60 * 24 * 7;

	public function __construct() {
		session_set_save_handler(
			array($this, 'open'),
			array($this, 'close'),
			array($this, 'read'),
			array($this, 'write'),
			array($this, 'destroy'),
			array($this, 'gc')
		);
	}

	public function open($savePath, $sessionName) {
		return true;
	}

	public function close() {
		$this->gc($this->timeout);

		return true;
	}

	public function read($id) {
		$s = db_fetch_assoc_prepare('SELECT `data` FROM `sessions` WHERE `id` = ?', array($id));

		if ($s) {
			return secured_decrypt($s['data']);
		}

		return '';
	}

	public function write($id, $data) {
		global $account;
		$a = (isset($account['id']) ? $account['id'] : 0);
		return db_execute_prepare('REPlACE INTO `sessions` (`id`, `access`, `user`, `data`) VALUES (?, ?, ?, ?)', array($id, time(), $a, secured_encrypt($data)));
	}

	public function destroy($id) {
		return db_execute_prepare('DELETE FROM `sessions` WHERE `id` = ?', array($id));
	}

	public function gc($max) {
		return db_execute_prepare('DELETE FROM `sessions` WHERE `access` < ?', array(intval(time() - $max)));
	}
}

new SessionSaveHandler();

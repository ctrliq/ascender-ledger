<?php

define('SESSION_TIMEOUT', 60 * 60 * 24 * 7);

/*
	The session cookie is only ever read by the server, so scripts never need it, and other
	sites have no business sending it.  Secure is set whenever the request arrived over HTTPS,
	including through a TLS-terminating proxy or ingress in front of the container.
*/
$session_https = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != '' && $_SERVER['HTTPS'] != 'off') ||
	(isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) == 'https');

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.gc_maxlifetime', (string) SESSION_TIMEOUT);
session_set_cookie_params(array(
	'lifetime' => SESSION_TIMEOUT,
	'path'     => '/',
	'secure'   => $session_https,
	'httponly' => true,
	'samesite' => 'Lax',
));

class SessionSaveHandler implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface {
	protected $timeout = SESSION_TIMEOUT;

	public function __construct() {
		session_set_save_handler($this, true);
	}

	public function open($savePath, $sessionName): bool {
		return true;
	}

	public function close(): bool {
		return true;
	}

	public function read($id): string|false {
		$s = db_fetch_assoc_prepare('SELECT `data` FROM `sessions` WHERE `id` = ? AND `access` >= ?', array($id, time() - $this->timeout));

		if ($s) {
			try {
				$data = secured_decrypt($s['data']);
				return ($data === false ? '' : $data);
			} catch (Exception $e) {
				return '';
			}
		}

		return '';
	}

	public function write($id, $data): bool {
		global $account;
		$a = (isset($account['id']) ? $account['id'] : 0);
		try {
			$data = secured_encrypt($data);
		} catch (Exception $e) {
			$data = '';
		}
		return (bool) db_execute_prepare('REPLACE INTO `sessions` (`id`, `access`, `user`, `data`) VALUES (?, ?, ?, ?)', array($id, time(), $a, $data));
	}

	public function destroy($id): bool {
		return (bool) db_execute_prepare('DELETE FROM `sessions` WHERE `id` = ?', array($id));
	}

	/*
		PHP passes session.gc_maxlifetime here, which is 24 minutes unless something changes it.
		Sessions last a week, so the handler's own timeout is what decides.
	*/
	public function gc($max): int|false {
		db_execute_prepare('DELETE FROM `sessions` WHERE `access` < ?', array(intval(time() - $this->timeout)));
		return 0;
	}

	/*
		Used by strict mode: an id the server never issued is refused and replaced, so a
		session id planted in a victim's browser cannot be logged into and then reused.
	*/
	public function validateId($id): bool {
		return (bool) db_fetch_cell_prepare('SELECT 1 AS `e` FROM `sessions` WHERE `id` = ? AND `access` >= ?', 'e', array($id, time() - $this->timeout));
	}

	public function updateTimestamp($id, $data): bool {
		return $this->write($id, $data);
	}
}

new SessionSaveHandler();

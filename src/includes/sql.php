<?php
/**
 * This file contains functions used in connecting to MySQL.
 *
 * @package    SQL
 * @author     Jimmy Conner <jimmy@sqmail.org>
 */
//error_reporting(E_ALL);

global $totalqueries, $settings_cache;
$totalqueries = 0;
$settings_cache = array();

if (file_exists('includes/config.php')) {
	require_once('includes/config.php');
} else {
	print "Missing config.php";
	exit;
}

try {
	$db = new \PDO("mysql:host=$sql_server;port=$port;dbname=$mydb;charset=utf8", $sql_user, $sql_pass, array(
		\PDO::ATTR_ERRMODE          => \PDO::ERRMODE_EXCEPTION,
		\PDO::ATTR_EMULATE_PREPARES => false,
	));
} catch(PDOException $e) {
	error_log('Ledger: could not connect to the database: ' . $e->getMessage());
	echo "Could not connect to the database\n";
	exit;
}

if (!$db) {
	echo "Could not connect to the database\n";
	exit;
}

include_once('includes/db_upgrade.php');

function sql_clean($text) {
	$text = str_replace(array("%", "\\", '/', "'", '"', '|'), '', $text);
	return $text;
}

function sql_clean_ans($text) {
	$text = htmlspecialchars($text);
	$text = preg_replace("/[^a-zA-Z0-9 \-_\.\n,@]/", "", $text);
	return $text;
}

function sql_clean_url($text) {
	$text = htmlspecialchars($text);
	$text = preg_replace("/[^a-zA-Z0-9\-_\.\/:]/", "", $text);
	return $text;
}

/**
 * True for an absolute http:// or https:// URL, the only kinds Ledger links to
 */
function valid_http_url($url) {
	if (!is_string($url) || $url == '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
		return false;
	}
	$scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
	return ($scheme == 'http' || $scheme == 'https');
}

function sql_clean_name($text) {
	return preg_replace('/[^A-Za-z\- ]/', '', $text);
}

function sql_clean_username($text) {
	return preg_replace('/[^A-Za-z0-9\.@\-\+]/', '', $text);
}

function sql_clean_code($text) {
	return preg_replace('/[^A-Za-z0-9]/', '', $text);
}

function sql_clean_csearch($text) {
	return preg_replace('/[^A-Za-z0-9\-_\. ]/', '', $text);
}

function sql_clean_email($text) {
	return preg_replace('/[^A-Za-z0-9\.@\-\+]/', '', $text);
}

function sql_clean_emails($text) {
	return preg_replace('/[^A-Za-z0-9\.@\-\+, ]/', '', $text);
}

function sql_clean_fact($text) {
	return preg_replace('/[^A-Za-z0-9\-_\.]/', '', $text);
}

function sql_clean_hostname($text) {
	return preg_replace('/[^A-Za-z0-9\-_\.]/', '', $text);
}

function sql_clean_limit($text) {
	return preg_replace('/[^A-Za-z0-9_\-: \|\[\],]/', '', $text);
}

/*
	Package names, versions and architectures.  "~" is part of Debian and Ubuntu versions
	(34~24.04 sorts before 34), so it has to survive.
*/
function sql_clean_package($text) {
	return preg_replace('/[^A-Za-z0-9\-:_\.@\+~]/', '', $text);
}

function sql_clean_play($text) {
	$text = htmlspecialchars($text);
	$text = preg_replace("/[^a-zA-Z0-9 \-_\.,@\|\[\]]/", "", $text);
	return $text;
}

function sql_clean_playbook($text) {
	return preg_replace('/[^A-Za-z0-9\-_\.\/]/', '', $text);
}

function sql_clean_project($text) {
	return preg_replace('/[^A-Za-z0-9\-_\. ]/', '', $text);
}

function sql_clean_setting($text) {
	return preg_replace('/[^A-Za-z0-9_\-]/', '', $text);
}

function sql_clean_timestamp($text) {
	return preg_replace('/[^A-Za-z0-9_\-: ]/', '', $text);
}

function sql_clean_service_name($text) {
	return preg_replace('/[^A-Za-z0-9\-_\.@\+]/', '', $text);
}

function db_table_exists ($table) {
	return (db_fetch_assoc("SHOW tables LIKE '$table'") ? true : false);
}
function db_column_exists ($table, $column) {
	return (db_fetch_cell("SHOW columns FROM `$table` LIKE '$column'", 'Field') ? true : false);
}
function db_index_exists ($table, $index, $unique_only = false) {
	$rows = db_fetch_assocs_prepare('SELECT `NON_UNIQUE` FROM `information_schema`.`STATISTICS`
		WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = ? AND `INDEX_NAME` = ?', array($table, $index));
	foreach ($rows as $r) {
		if (!$unique_only || !$r['NON_UNIQUE']) {
			return true;
		}
	}
	return false;
}

/**
 * Executes a SQL query, return last insert id
 *
 * This function will execute a SQL query.  It will return true or false if
 * it properly executed or it will return the last insert id if you used
 * a SQL statement with INSERT
 * 
 * @param string $query
 * @return int 
 */
function db_execute($query) {
	global $db;
	$stmt = $db->query($query);
	return $db->lastInsertId();
}

/**
 * Executes a SQL query (prepared statement)
 *
 * This function will execute a SQL query.  It will return true or false if
 * it properly executed or it will return the last insert id if you used
 * a SQL statement with INSERT.  This is used for prepared statements
 * 
 * @param string $query
 * @param array $data
 * @return int 
 */
function db_execute_prepare($query, $data) {
	$stmt = db_prepare_cached($query);
	$i = $stmt->execute($data);

	// lastInsertId() is per connection, so only report it for the statements that set it.
	// Otherwise an UPDATE run after any INSERT would hand back that earlier row's id.
	if (preg_match('/^\s*(INSERT|REPLACE)\b/i', $query)) {
		$id = $GLOBALS['db']->lastInsertId();
		if ($id) {
			return $id;
		}
	}
	return $i;
}

/**
 * Prepares a statement once per request and reuses it
 *
 * The parser runs the same INSERT for every fact of a host, which can be thousands of
 * statements per event.  Preparing each of them again is a round trip to the server for
 * nothing, so statements are kept for the life of the request.
 *
 * @param string $query
 * @return PDOStatement
 */
function db_prepare_cached($query) {
	global $db;
	static $cache = array();

	if (!isset($cache[$query])) {
		if (count($cache) > 200) {
			$cache = array();
		}
		$cache[$query] = $db->prepare($query);
	}
	return $cache[$query];
}

function db_begin() {
	global $db;
	if (!$db->inTransaction()) {
		$db->beginTransaction();
	}
}

function db_commit() {
	global $db;
	if ($db->inTransaction()) {
		$db->commit();
	}
}

function db_rollback() {
	global $db;
	if ($db->inTransaction()) {
		$db->rollBack();
	}
}

/**
 * Counts the rows a query would return, giving up at $cap
 *
 * An exact COUNT(*) over the facts table of a large fleet reads every row, and the pages only
 * need it to size their pagination.  Counting at most $cap + 1 rows keeps every page request
 * bounded, at the price of reporting "more than $cap" rather than the exact figure.
 *
 * @param string $from_where  everything after SELECT ... (FROM, JOINs and WHERE)
 * @param array  $params
 * @param int    $cap
 * @return array  array(count, capped)
 */
function db_count_capped($from_where, $params, $cap = 100000) {
	$cap = intval($cap);
	$count = db_fetch_cell_prepare("SELECT COUNT(*) AS `total` FROM (SELECT 1 $from_where LIMIT " . ($cap + 1) . ") AS `rs`", 'total', $params);
	$count = intval($count);
	if ($count > $cap) {
		return array($cap, true);
	}
	return array($count, false);
}

/**
 * Executes a SQL query, return associated array
 *
 * This function will execute a SQL query.  It will return an 
 * associated arrays for the executed SQL statement.  Use this if
 * you expect a single row returned.
 * 
 * @param string $query
 * @return array or false if failed
 */
function db_fetch_assoc($query) {
	global $db;
	$stmt = $db->query($query);
	if ($stmt) {
		$results = $stmt->fetch(\PDO::FETCH_ASSOC);
		return $results;
	} else {
		return false;
	}
}

function db_fetch_assoc_prepare($query, $data) {
	$stmt = db_prepare_cached($query);
	$stmt->execute($data);
	$results = $stmt->fetch(\PDO::FETCH_ASSOC);
	$stmt->closeCursor();
	return $results;
}

/**
 * Executes a SQL query, return array of associated array
 *
 * This function will execute a SQL query.  It will return an array of
 * associated arrays for the executed SQL statement.  Use this if
 * you expect multiple rows to be returns
 * 
 * @param string $query
 * @return array or false if failed
 */
function db_fetch_assocs($query) {
	global $db;
	$stmt = $db->query($query);
	if ($stmt) {
		$results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
		return $results;
	} else {
		return false;
	}
}

/**
 * Executes a SQL query, return array of associated array (prepared)
 *
 * This function will execute a SQL query.  It will return an array of
 * associated arrays for the executed SQL statement.  Use this if
 * you expect multiple rows to be returns.  This is for making prepared
 * SQL calls
 * 
 * @param string $query
 * @param array $data
 * @return array or false if failed
 */
function db_fetch_assocs_prepare($query, $data) {
	$stmt = db_prepare_cached($query);
	$stmt->execute($data);
	return $stmt->fetchAll(\PDO::FETCH_ASSOC);
}

/**
 * Executes a SQL query, return a single cell
 *
 * This function will execute a SQL query.  It will return only the result
 * for the specified cell.
 * 
 * @param string $query
 * @param string $cell
 * @return string or false if failed
 */
function db_fetch_cell($query, $cell) {
	global $db;
	$stmt = $db->query($query);
	if (!$stmt) return false;
	$results = $stmt->fetch(\PDO::FETCH_ASSOC);
	if (!isset($results[$cell])) return false;
	return $results[$cell];
}

function db_fetch_cell_prepare($query, $cell, $data) {
	$stmt = db_prepare_cached($query);
	$stmt->execute($data);
	$results = $stmt->fetch(\PDO::FETCH_ASSOC);
	$stmt->closeCursor();
	if (!isset($results[$cell])) return false;
	return $results[$cell];
}


/**
 * Pulls a setting from the database
 *
 * This function will pull the value for a specified setting
 * from the settings table
 * 
 * @param string $options
 * @return string or false if failed
 */
function read_setting($name, $default = false) {
	global $settings_cache;
	$name = sql_clean_setting($name);

	if (isset($settings_cache[$name])) {
		return $settings_cache[$name];
	}

	$value = db_fetch_cell_prepare('SELECT `value` FROM `settings` WHERE `setting` = ?', 'value', array($name));
	if ($value === false) {
		if ($default !== false) {
			db_execute_prepare('INSERT IGNORE INTO `settings` (`setting`, `value`) VALUES (?, ?)', array($name, $default));
			$value = $default;
		}
	}
	if ($value !== false) {
		$settings_cache[$name] = $value;
	}
	return $value;
}

/**
 * Writes a setting, creating it if it does not exist yet
 *
 * @param string $name
 * @param string $value
 */
function write_setting($name, $value) {
	global $settings_cache;
	$name = sql_clean_setting($name);
	db_execute_prepare('INSERT INTO `settings` (`setting`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)', array($name, $value));
	$settings_cache[$name] = $value;
}

/**
 * A new random token for the parser's Authorization header, in UUID form
 */
function generate_auth_token() {
	$b = random_bytes(16);
	$b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
	$b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
	return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
}

function secured_encrypt($data) {
	$first_key = base64_decode(FIRSTKEY);
	$second_key = base64_decode(SECONDKEY);

	$method = 'aes-256-cbc';
	$iv_length = openssl_cipher_iv_length($method);
	$iv = openssl_random_pseudo_bytes($iv_length);

	$first_encrypted = openssl_encrypt($data, $method, $first_key, OPENSSL_RAW_DATA, $iv);
	$second_encrypted = hash_hmac('sha512', $first_encrypted, $second_key, TRUE);

	$output = base64_encode($iv . $second_encrypted . $first_encrypted);
	return $output;
}


function secured_decrypt($input) {
	$first_key = base64_decode(FIRSTKEY);
	$second_key = base64_decode(SECONDKEY);
	$mix = base64_decode($input);

	$method = 'aes-256-cbc';
	$iv_length = openssl_cipher_iv_length($method);

	$iv = substr($mix, 0, $iv_length);
	$second_encrypted = substr($mix, $iv_length, 64);
	$first_encrypted = substr($mix, $iv_length + 64);
            
	$data = openssl_decrypt($first_encrypted, $method, $first_key, OPENSSL_RAW_DATA, $iv);
	$second_encrypted_new = hash_hmac('sha512', $first_encrypted, $second_key, TRUE);

	if (hash_equals($second_encrypted, $second_encrypted_new))
		return $data;
	return false;
}

if (!function_exists('hash_equals')) {
	function hash_equals($str1, $str2) {
		if (strlen($str1) != strlen($str2)) {
			return false;
		} else {
			$res = $str1 ^ $str2;
			$ret = 0;
			for ($i = strlen($res) - 1; $i >= 0; $i--)
				$ret |= ord($res[$i]);
			return !$ret;
		}
	}
}

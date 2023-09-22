<?php
/**
 * This file contains functions used in connecting to MySQL.
 *
 * @package    SQL
 * @author     Jimmy Conner <jimmy@sqmail.org>
 */
//error_reporting(E_ALL);

global $totalqueries;
$totalqueries = 0;

if (file_exists('includes/config.php')) {
	require_once('includes/config.php');
} else {
	print "Missing config.php";
	exit;
}

try {
	$db = new \PDO("mysql:host=$sql_server;port=$port;dbname=$mydb;charset=utf8", $sql_user, $sql_pass);
} catch(PDOException $e) {
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

function sql_clean_name($text) {
	return preg_replace('/[^A-Za-z\- ]/', '', $text);
}

function sql_clean_username($text) {
	return preg_replace('/[^A-Za-z0-9\.@\-\+]/', '', $text);
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

function sql_clean_project($text) {
	return preg_replace('/[^A-Za-z0-9\-_\. ]/', '', $text);
}

function sql_clean_playbook($text) {
	return preg_replace('/[^A-Za-z0-9\-_\.\/]/', '', $text);
}

function sql_clean_play($text) {
	$text = htmlspecialchars($text);
	$text = preg_replace("/[^a-zA-Z0-9 \-_\.,@\|\[\]]/", "", $text);
	return $text;
}

function sql_clean_csearch($text) {
	return preg_replace('/[^A-Za-z0-9\-_\. ]/', '', $text);
}

function sql_clean_code($text) {
	return preg_replace('/[^A-Za-z0-9]/', '', $text);
}

function sql_clean_setting($text) {
	return preg_replace('/[^A-Za-z0-9_\-]/', '', $text);
}

function sql_clean_timestamp($text) {
	return preg_replace('/[^A-Za-z0-9_\-: ]/', '', $text);
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
	global $db;
	$db->setAttribute(PDO::ATTR_EMULATE_PREPARES,false); 

	$stmt = $db->prepare($query);

	if (!$stmt) {
		echo "\nPDO::errorInfo():\n";
		print_r($db->errorInfo());
		return 0;
	} else {
		$i = $stmt->execute($data);
		if ($db->lastInsertId()) {
			return $db->lastInsertId();
		}
		return $i;
	}
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
	global $db;
	$stmt = $db->prepare($query);
	$stmt->execute($data);
	$results = $stmt->fetch(\PDO::FETCH_ASSOC);
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
	global $db;
	$stmt = $db->prepare($query);
	if ($stmt) {
		$stmt->execute($data);
		$results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
	} else {
		$results = false;
	}
	return $results;
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
	global $db;
	$stmt = $db->prepare($query);
	$stmt->execute($data);
	$results = $stmt->fetch(\PDO::FETCH_ASSOC);
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
	$name = sql_clean_setting($name);
	$value = db_fetch_cell_prepare('SELECT `value` FROM `settings` WHERE `setting` = ?', 'value', array($name));
	if ($value === false) {
		if ($default !== false) {
			db_execute_prepare('INSERT INTO `settings` (`setting`, `value`) VALUES (?, ?)', array($name, $default));
			$value = $default;
		}
	}
	return $value;
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

<?php

include_once('includes/classes/Spyc.php');

use Async\Yaml;

include_once('vendor/autoload.php');
use SebastianBergmann\Diff\Differ;
use SebastianBergmann\Diff\Output\StrictUnifiedDiffOutputBuilder;

$d = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($d)) {
	http_response_code(400);
	exit;
}

include_once('includes/sql.php');

if (!isset($_SERVER['REMOTE_ADDR'])) exit;

/*
	Ascender authenticates to Ledger with the token from Settings -> General, sent as
	"Authorization: Ledger <token>" when its Logging Aggregator Type is "ledger".  This replaces
	trusting servers by IP address, which broke whenever an Ascender pod was rescheduled and came
	back with a new address.

	Requiring the token is off by default, so that upgrading does not cut off servers that have
	not been given it yet.  The token is checked either way, and each server's result is shown
	on the Servers page, so it is safe to turn the requirement on once every server has it.

	A rejected record is still answered with 200.  Ascender ships its logs through rsyslog, which
	retries a failed delivery indefinitely and spools to disk meanwhile, so an error status
	would back up Ascender rather than tell anyone anything.  The rejection is recorded for the
	dashboard instead.
*/
$auth_failure = parser_auth_failure();
if ($auth_failure != '' && read_setting('require_auth_token', '0') == '1') {
	parser_record_rejection($auth_failure, $d);
	exit;
}

$time = time();
$server = parser_server($d, $time, ($auth_failure == '' ? 1 : 0));
if ($auth_failure == '') {
	parser_clear_rejection($d);
}

if (isset($d['logger_name'])) {
	switch ($d['logger_name']) {
		// Both names, because the platform is renaming its Python package from
		// awx to ascender and the logger name follows it. Nothing here can tell
		// which side of that a deployment is on, and a name this parser does not
		// know is not an error anywhere: it is silence.
		case 'ascender.analytics.job_events':
		case 'awx.analytics.job_events':
			// JOB EVENT DATA
			if (isset($d['event_data']['res']['ansible_facts']) && is_array($d['event_data']['res']['ansible_facts'])) {
				if (isset($d['host_name']) && is_string($d['host_name']) && strtolower($d['host_name']) != 'localhost') {
					$f = $d['event_data']['res']['ansible_facts'];
					$t = strtolower((string) (isset($d['event_data']['task_action']) ? $d['event_data']['task_action'] : ''));
					$fs = parse_facts($f);
					if (count($fs)) {
						$h = check_host($d['host_name']);
						if ($h) {
							if ($t == 'ansible.builtin.package_facts' || $t == 'package_facts') {
								if (isset($f['packages']) && is_array($f['packages'])) {
									parse_packages($h, $f['packages']);
								}
							} elseif ($t == 'ansible.builtin.service_facts' || $t == 'service_facts') {
								if (isset($f['services']) && is_array($f['services'])) {
									parse_services($h, $f['services']);
								}
							} else {
								$allowed_modules = explode(',', (string) read_setting('allowed_modules', 'gather_facts'));
								if (in_array($t, $allowed_modules)) {
									parse_store_facts($h, $fs, $t, $time);
								}
							}
						}
					}
				}
			}

			if (isset($d['changed']) && $d['changed'] && isset($d['event']) && $d['event'] == 'runner_on_ok' && isset($d['job']) && $d['job'] && isset($d['event_data']['task_action']) && $d['event_data']['task_action'] != '' && isset($d['host_name'])) {
				$remove_invocation = read_setting('remove_invocation', 0);

				// Results are in an array (this task had a loop)
				if (isset($d['event_data']['res']['results']) && is_array($d['event_data']['res']['results'])) {
					foreach ($d['event_data']['res']['results'] as $r => $result) {
						if (!is_array($result)) {
							continue;
						}
						if ($remove_invocation && isset($result['invocation'])) {
							unset($result['invocation']);
						}
						if (isset($result['invocation']['module_args']) && is_array($result['invocation']['module_args'])) {
							$result['invocation']['module_args'] = clean_null_args($result['invocation']['module_args']);
						}
						if (isset($result['diff']) && is_array($result['diff'])) {
							$result['diff'] = render_diffs($result['diff']);
						}
						$d['event_data']['res']['results'][$r] = $result;
					}
				}

				// Results are not in an array (no loop)
				if (isset($d['event_data']['res']['diff'])) {
					if ($remove_invocation && isset($d['event_data']['res']['invocation'])) {
						unset($d['event_data']['res']['invocation']);
					}
					if (isset($d['event_data']['res']['invocation']['module_args']) && is_array($d['event_data']['res']['invocation']['module_args'])) {
						$d['event_data']['res']['invocation']['module_args'] = clean_null_args($d['event_data']['res']['invocation']['module_args']);
					}
					if (is_array($d['event_data']['res']['diff'])) {
						$d['event_data']['res']['diff'] = render_diffs($d['event_data']['res']['diff']);
					}
				}

				$h = check_host($d['host_name']);
				if ($h) {
					$role = (isset($d['role']) ? ($d['role'] != '/runner/project' ? $d['role'] : '') : '');
					$res = Yaml::dumper(isset($d['event_data']['res']) ? $d['event_data']['res'] : array());
					db_execute_prepare('INSERT INTO `changes` (`server`, `host`, `time`, `job`, `playbook`, `play`, `role`, `task`, `task_action`, `res`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
						array(
							substr($server['key'], 0, 128),
							$h,
							$time,
							intval($d['job']),
							substr(sql_clean_playbook(parse_str_field($d, 'playbook')), 0, 256),
							substr(sql_clean_play(parse_str_field($d, 'play')), 0, 256),
							substr(sql_clean_playbook($role), 0, 64),
							substr(sql_clean_play(parse_str_field($d, 'task')), 0, 256),
							substr(sql_clean_play($d['event_data']['task_action']), 0, 64),
							truncate_bytes($res, 65535),
						));
				}
			}
			break;
		case 'ascender.analytics.activity_stream':
		case 'awx.analytics.activity_stream':
			// JOB DATA
			if (isset($d['operation']) && $d['operation'] == 'create' && isset($d['object1']) && $d['object1'] == 'job' && strtolower(parse_str_field($d, 'host')) != 'localhost' && isset($d['changes']) && is_array($d['changes'])) {
				$c = $d['changes'];

				if (!isset($d['summary_fields']['job_template'][0]['id'])) {
					// Older versions of Tower don't specify the job_template_id
					$t = explode('-', parse_str_field($c, 'job_template'));
					$jtid = $t[array_key_last($t)];
				} else {
					$jtid = $d['summary_fields']['job_template'][0]['id'];
				}

				// The server is recorded because job numbers are only unique within one Ascender
				db_execute_prepare('INSERT INTO `jobs` (`timestamp`, `job`, `server`, `job_template_id`, `host`, `name`, `job_type`, `inventory`, `project`, `scm_branch`, `execution_environment`, `actor`, `limit`)
									VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
									array(
										substr(sql_clean_timestamp(parse_str_field($d, '@timestamp')), 0, 32),
										intval(isset($c['id']) ? $c['id'] : 0),
										substr($server['key'], 0, 128),
										intval($jtid),
										substr(sql_clean_hostname(parse_str_field($d, 'host')), 0, 128),
										substr(sql_clean_play(parse_str_field($c, 'name')), 0, 512),
										substr(sql_clean_name(parse_str_field($c, 'job_type')), 0, 32),
										substr(sql_clean_project(parse_str_field($c, 'inventory')), 0, 128),
										substr(sql_clean_project(parse_str_field($c, 'project')), 0, 128),
										substr(sql_clean_project(parse_str_field($c, 'scm_branch')), 0, 128),
										substr(sql_clean_project(parse_str_field($c, 'execution_environment')), 0, 128),
										substr(sql_clean_email(parse_str_field($d, 'actor')), 0, 64),
										sql_clean_limit(parse_str_field($c, 'limit'))
									));
			}
			break;
	}
}

/*
	Returns why the request may not post data, or '' when it may
*/
function parser_auth_failure() {
	$expected = trim((string) read_setting('auth_token', ''));
	if ($expected == '') {
		return 'no token is configured in Ledger';
	}

	$header = '';
	if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
		$header = trim($_SERVER['HTTP_AUTHORIZATION']);
	} elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
		$header = trim($_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
	}
	if ($header == '') {
		return 'no Authorization header was sent';
	}

	$parts = preg_split('/\s+/', $header, 2);
	$scheme = strtolower($parts[0]);
	$token = (isset($parts[1]) ? trim($parts[1]) : '');

	/*
		"Ledger" is what Ascender sends for the ledger aggregator type.  Bearer and Basic (the
		token as the password, any user name) cover a platform configured with another type.
	*/
	if ($scheme == 'basic') {
		$decoded = base64_decode($token, true);
		$token = '';
		if ($decoded !== false && strpos($decoded, ':') !== false) {
			$token = substr($decoded, strpos($decoded, ':') + 1);
		}
	} elseif ($scheme != 'ledger' && $scheme != 'bearer') {
		return 'the Authorization header is not a Ledger token';
	}

	if ($token === '' || !hash_equals($expected, $token)) {
		return 'the token does not match';
	}
	return '';
}

/*
	Remembers the latest rejected request, at most once a minute, for the dashboard
*/
function parser_record_rejection($reason, $d) {
	$uuid = parser_uuid($d);
	$ip = substr($_SERVER['REMOTE_ADDR'], 0, 45);

	// The Servers page shows this server's token as not valid; a later accepted record restores it
	if ($uuid != '') {
		db_execute_prepare('UPDATE `servers` SET `auth_ok` = 0 WHERE `uuid` = ? AND `auth_ok` = 1', array($uuid));
	} else {
		db_execute_prepare('UPDATE `servers` SET `auth_ok` = 0 WHERE `uuid` IS NULL AND `ip` = ? AND `auth_ok` = 1', array($ip));
	}

	$last = json_decode((string) read_setting('auth_rejected', '{}'), true);
	if (is_array($last) && isset($last['time']) && $last['time'] > time() - 60) {
		return;
	}
	write_setting('auth_rejected', json_encode(array(
		'time'   => time(),
		'ip'     => $ip,
		'uuid'   => $uuid,
		'reason' => $reason,
	)));
}

/*
	The dashboard warns about the last refused record until the server it came from sends one
	that is accepted, or for a day.  Other servers sending data does not clear it.
*/
function parser_clear_rejection($d) {
	$last = json_decode((string) read_setting('auth_rejected', '{}'), true);
	if (!is_array($last) || !isset($last['time'])) {
		return;
	}
	$uuid = parser_uuid($d);
	$same = ((isset($last['uuid']) && $last['uuid'] != '') ? $last['uuid'] == $uuid : $last['ip'] == $_SERVER['REMOTE_ADDR']);
	if ($same) {
		write_setting('auth_rejected', '{}');
	}
}

/*
	The install UUID a record carries, or '' when it has none that looks like one
*/
function parser_uuid($d) {
	$uuid = (isset($d['tower_uuid']) && is_string($d['tower_uuid']) ? strtolower(trim($d['tower_uuid'])) : '');
	if (!preg_match('/^[0-9a-f-]{8,64}$/', $uuid) || trim($uuid, '0-') == '') {
		$uuid = '';
	}
	return $uuid;
}

/*
	Finds or registers the Ascender server a record came from.  Servers are known by the install
	UUID Ascender sends with every record, which survives pods being rescheduled; the address is
	only remembered for display.  Returns the server row, with 'key' set to what the changes
	table records for it.
*/
function parser_server($d, $time, $auth_ok) {
	$ip = substr($_SERVER['REMOTE_ADDR'], 0, 45);

	$uuid = parser_uuid($d);

	$url = (isset($d['tower_url']) && is_string($d['tower_url']) ? rtrim(trim($d['tower_url']), '/') : '');
	if (!valid_http_url($url)) {
		$url = '';
	}

	$name = ($url != '' ? (string) parse_url($url, PHP_URL_HOST) : '');
	if ($name == '') {
		$name = sql_clean_hostname(parse_str_field($d, 'host'));
	}
	if ($name == '') {
		$name = $ip;
	}
	$name = substr($name, 0, 128);

	if ($uuid != '') {
		$server = db_fetch_assoc_prepare('SELECT * FROM `servers` WHERE `uuid` = ?', array($uuid));

		if (!$server) {
			// A server recorded before servers had UUIDs: claim it rather than list it twice
			$server = db_fetch_assoc_prepare('SELECT * FROM `servers` WHERE `uuid` IS NULL AND (`ip` = ? OR `name` = ? OR `name` = ?) ORDER BY `id` LIMIT 1', array($ip, $name, sql_clean_hostname(parse_str_field($d, 'host'))));
			if ($server) {
				db_execute_prepare('UPDATE `servers` SET `uuid` = ? WHERE `id` = ? AND `uuid` IS NULL', array($uuid, $server['id']));
			} else {
				db_execute_prepare('INSERT INTO `servers` (`name`, `uuid`, `ip`, `url`, `lastseen`) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE `lastseen` = VALUES(`lastseen`)',
					array($name, $uuid, $ip, substr($url, 0, 128), $time));
			}
			$server = db_fetch_assoc_prepare('SELECT * FROM `servers` WHERE `uuid` = ?', array($uuid));
		}
	} else {
		$server = db_fetch_assoc_prepare('SELECT * FROM `servers` WHERE `uuid` IS NULL AND `name` = ? ORDER BY `id` LIMIT 1', array($name));
		if (!$server) {
			db_execute_prepare('INSERT INTO `servers` (`name`, `ip`, `url`, `lastseen`) VALUES (?, ?, ?, ?)', array($name, $ip, substr($url, 0, 128), $time));
			$server = db_fetch_assoc_prepare('SELECT * FROM `servers` WHERE `uuid` IS NULL AND `name` = ? ORDER BY `id` LIMIT 1', array($name));
		}
	}

	if (!$server) {
		return array('key' => ($uuid != '' ? $uuid : $name));
	}

	// Last seen and address are for display, so one write a minute is plenty
	if ($server['ip'] != $ip || $server['lastseen'] < $time - 60 || ($server['url'] == '' && $url != '') || $server['auth_ok'] != $auth_ok) {
		db_execute_prepare('UPDATE `servers` SET `ip` = ?, `lastseen` = ?, `auth_ok` = ?, `url` = IF(`url` = \'\', ?, `url`) WHERE `id` = ?', array($ip, $time, $auth_ok, substr($url, 0, 128), $server['id']));
	}

	$server['key'] = ($server['uuid'] != '' ? $server['uuid'] : $server['name']);
	return $server;
}

/*
	Stores a host's facts in one transaction: thousands of autocommitted inserts per host
	is most of the parser's time on a large gather.
*/
function parse_store_facts($h, $fs, $t, $time) {
	$type = substr(sql_clean_fact($t), 0, 32);
	$s = 'INSERT INTO `facts` (`host`, `fact`, `data`, `type`, `time`) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE `data` = VALUES(`data`), `type` = VALUES(`type`), `time` = VALUES(`time`)';

	db_begin();
	try {
		foreach ($fs as $k => $v) {
			$k = substr(sql_clean_fact($k), 0, 128);
			if ($k == '') {
				continue;
			}
			$v = truncate_bytes(str_replace(array("'", '"'), '', (string) $v), 65535);
			try {
				db_execute_prepare($s, array($h, $k, $v, $type, $time));
			} catch (PDOException $e) {
				error_log('Ledger parser: could not store fact ' . $k . ': ' . $e->getMessage());
			}
		}
		db_commit();
	} catch (Exception $e) {
		db_rollback();
		error_log('Ledger parser: could not store facts: ' . $e->getMessage());
	}
}

/*
	Replaces a host's package list, in one transaction so nobody sees it half written
*/
function parse_packages($h, $packages) {
	$s = 'INSERT IGNORE INTO `packages` (`host`, `name`, `version`, `release`, `epoch`, `arch`, `source`, `check`) VALUES (?, ?, ?, ?, ?, ?, ?, 1)';

	db_begin();
	try {
		db_execute_prepare('DELETE FROM `packages` WHERE `host` = ?', array($h));
		foreach ($packages as $versions) {
			if (!is_array($versions)) {
				continue;
			}
			foreach ($versions as $v) {
				if (!is_array($v) || !isset($v['name'])) {
					continue;
				}
				try {
					db_execute_prepare($s, array($h,
						substr(sql_clean_package(parse_str_field($v, 'name')), 0, 128),
						substr(sql_clean_package(parse_str_field($v, 'version')), 0, 64),
						substr(sql_clean_package(parse_str_field($v, 'release')), 0, 64),
						substr(sql_clean_package(parse_str_field($v, 'epoch')), 0, 32),
						substr(sql_clean_package(parse_str_field($v, 'arch')), 0, 32),
						substr(sql_clean_package(parse_str_field($v, 'source')), 0, 32)));
				} catch (PDOException $e) {
				}
			}
		}
		db_commit();
	} catch (Exception $e) {
		db_rollback();
		error_log('Ledger parser: could not store packages: ' . $e->getMessage());
	}
}

/*
	Replaces a host's service list, in one transaction
*/
function parse_services($h, $services) {
	$s = 'INSERT IGNORE INTO `services` (`host`, `name`, `state`, `status`, `source`) VALUES (?, ?, ?, ?, ?)';

	db_begin();
	try {
		db_execute_prepare('DELETE FROM `services` WHERE `host` = ?', array($h));
		foreach ($services as $v) {
			if (!is_array($v) || !isset($v['name'])) {
				continue;
			}
			$name = str_replace("\\x2d", '-', parse_str_field($v, 'name'));
			try {
				db_execute_prepare($s, array($h,
					substr(sql_clean_service_name($name), 0, 128),
					substr(sql_clean_fact(parse_str_field($v, 'state')), 0, 32),
					substr(sql_clean_fact(parse_str_field($v, 'status')), 0, 32),
					substr(sql_clean_fact(parse_str_field($v, 'source')), 0, 32)));
			} catch (PDOException $e) {
			}
		}
		db_commit();
	} catch (Exception $e) {
		db_rollback();
		error_log('Ledger parser: could not store services: ' . $e->getMessage());
	}
}

/*
	A string field of a record, or '' when it is missing or not a scalar
*/
function parse_str_field($a, $k) {
	if (!is_array($a) || !isset($a[$k]) || !is_scalar($a[$k])) {
		return '';
	}
	return (string) $a[$k];
}

function truncate_bytes($s, $max) {
	return (strlen($s) > $max ? substr($s, 0, $max) : $s);
}

function clean_null_args($inv) {
	$ninv = array();
	foreach ($inv as $k => $i) {
		if (!is_null($i)) {
			$ninv[$k] = $i;
		}
	}
	return $ninv;
}

/*
	Turns each before/after pair in a task's diff list into a unified diff.  Every entry is
	diffed on its own: a task can report several (a template and its mode, say).
*/
function render_diffs($diffs) {
	foreach ($diffs as $a => $entry) {
		if (is_array($entry) && isset($entry['before']) && isset($entry['after'])) {
			$diffs[$a] = create_diff($entry);
		}
	}
	return $diffs;
}

function create_diff($entry) {
	$from = $to = (isset($entry['before_header']) && is_scalar($entry['before_header']) ? (string) $entry['before_header'] : '');
	$before = (is_array($entry['before']) ? $entry['before'] : (string) $entry['before']);
	$after = (is_array($entry['after']) ? $entry['after'] : (string) $entry['after']);

	$builder = new StrictUnifiedDiffOutputBuilder([
		'collapseRanges'      => true, // ranges of length one are rendered with the trailing `,1`
		'commonLineThreshold' => 6,    // number of same lines before ending a new hunk and creating a new one (if needed)
		'contextLines'        => 3,    // like `diff:  -u, -U NUM, --unified[=NUM]`, for patch/git apply compatibility best to keep at least @ 3
		'fromFile'            => $from,
		'fromFileDate'        => null,
		'toFile'              => $to,
		'toFileDate'          => null,
	]);

	$differ = new Differ($builder);
	$diff = $differ->diff($before, $after);
	return color_diff($diff);
}

function color_diff($diff) {
	$diff = explode("\n", $diff);
	for ($a = 0; $a < count($diff); $a++) {
		if (isset($diff[$a][0])) {
			if ($diff[$a][0] == '+') {
				$diff[$a] = '<font color=green>' . $diff[$a] . '</font>';
			}
			if ($diff[$a][0] == '-') {
				$diff[$a] = '<font color=red>' . $diff[$a] . '</font>';
			}
			if ($diff[$a][0] == '@') {
				$diff[$a] = '<font color=blue>' . $diff[$a] . '</font>';
			}
		}
	}
	return implode("\n", $diff);
}

function parse_facts ($f, $fs = array(), $n = '') {
	if (!empty($f)) {
		foreach ($f as $k => $v) {
			if (is_array($v)) {
				$s = ($n != '' && substr($n, -1, 1) != '.' ? '.' : '');
				$fs = parse_facts($v, $fs, "$n$s$k.");
			} else {
				if ($v === true) $v = "true";
				if ($v === false) $v = "false";
				$fs[$n . $k] = $v;
			}
		}
	}
	return $fs;
}

/*
	Returns the id of a host, adding it if it is new.  Host names are unique, so two events for
	a new host arriving together cannot create it twice.
*/
function check_host ($host) {
	$host = substr(strtolower(trim((string) $host)), 0, 128);
	if ($host == '' || $host == 'localhost') {
		return false;
	}
	$time = time();
	$h = db_fetch_assoc_prepare('SELECT `id`, `time` FROM `hosts` WHERE `hostname` = ?', array($host));
	if (isset($h['id'])) {
		// Retention only needs the day, so the busiest table is not rewritten on every event
		if ($h['time'] < $time - 300) {
			db_execute_prepare('UPDATE `hosts` SET `time` = ? WHERE `id` = ?', array($time, $h['id']));
		}
		return $h['id'];
	}
	return db_execute_prepare('INSERT INTO `hosts` (`hostname`, `time`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `time` = VALUES(`time`), `id` = LAST_INSERT_ID(`id`)', array($host, $time));
}

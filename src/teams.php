<?php

include_once('includes/global.php');

if (!check_perm('super')) {
	header("Location: /\n\n");
	exit;
}

$posted = (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'POST');

// Letters, numbers, spaces, - and _, up to 32 characters
function clean_team_name($name) {
	return (is_string($name) ? substr(trim(preg_replace('/\s+/', ' ', preg_replace('/[^A-Za-z0-9 \-_]/', '', $name))), 0, 32) : '');
}

$team = array();
if (isset($_GET['team']) && intval($_GET['team'])) {
	$team = db_fetch_assoc_prepare('SELECT * FROM `teams` WHERE `id` = ?', array(intval($_GET['team'])));
}

if (isset($_GET['action'])) {
	$action = $_GET['action'];

	if ($action == 'create') {
		$name = clean_team_name(isset($_POST['name']) ? $_POST['name'] : '');
		if (!$posted) {
			Header("Location: /teams/\n\n");
		} elseif ($name == '') {
			Header("Location: /teams/?error=teamname\n\n");
		} elseif (db_fetch_cell_prepare('SELECT `id` FROM `teams` WHERE `name` = ?', 'id', array($name))) {
			Header("Location: /teams/?error=teamexists\n\n");
		} else {
			$id = db_execute_prepare('INSERT INTO `teams` (`name`, `enabled`) VALUES (?, 1)', array($name));
			Header("Location: /teams/edit/" . intval($id) . "?msg=created\n\n");
		}
		exit;
	}

	if (!isset($team['id'])) {
		Header("Location: /teams/\n\n");
		exit;
	}
	$id = intval($team['id']);
	$back = '/teams/' . ($action == 'save' ? 'edit' : $action) . '/' . $id;

	switch ($action) {
		case 'delete':
			csrf_verify();
			$ok = change_keeping_admin(function () use ($id) {
				db_execute_prepare('DELETE FROM `team_user` WHERE `team` = ?', array($id));
				db_execute_prepare('DELETE FROM `team_permission` WHERE `team` = ?', array($id));
				db_execute_prepare('DELETE FROM `reports_team_perms` WHERE `team` = ?', array($id));
				db_execute_prepare('DELETE FROM `teams` WHERE `id` = ?', array($id));
			});
			Header("Location: /teams/?" . ($ok ? 'msg=deleted' : 'error=lockout') . "\n\n");
			exit;

		case 'save':
			if ($posted) {
				$name = clean_team_name(isset($_POST['name']) ? $_POST['name'] : '');
				if ($name == '') {
					Header("Location: $back?error=teamname\n\n");
					exit;
				}
				if (db_fetch_cell_prepare('SELECT `id` FROM `teams` WHERE `name` = ? AND `id` != ?', 'id', array($name, $id))) {
					Header("Location: $back?error=teamexists\n\n");
					exit;
				}
				$enabled = (isset($_POST['enabled']) && intval($_POST['enabled']) ? 1 : 0);
				$ok = change_keeping_admin(function () use ($id, $name, $enabled) {
					db_execute_prepare('UPDATE `teams` SET `name` = ?, `enabled` = ? WHERE `id` = ?', array($name, $enabled, $id));
				});
				Header("Location: $back?" . ($ok ? 'msg=saved' : 'error=lockout') . "\n\n");
			}
			exit;

		case 'edit':
			echo $twig->render('team.html', array_merge($twigarr, admin_messages(), array('team' => $team, 'page' => 'details')));
			exit;

		case 'users':
			$users = db_fetch_assocs('SELECT `id`, `name`, `username`, `email`, `enabled` FROM `users` ORDER BY `username`');
			if ($posted) {
				$valid = array_flip(array_column($users, 'id'));
				$chosen = array();
				if (isset($_POST['users']) && is_array($_POST['users'])) {
					foreach ($_POST['users'] as $u) {
						if (is_scalar($u) && isset($valid[intval($u)])) {
							$chosen[intval($u)] = intval($u);
						}
					}
				}
				$ok = change_keeping_admin(function () use ($id, $chosen) {
					db_execute_prepare('DELETE FROM `team_user` WHERE `team` = ?', array($id));
					foreach ($chosen as $u) {
						db_execute_prepare('INSERT INTO `team_user` (`team`, `user`) VALUES (?, ?)', array($id, $u));
					}
				});
				Header("Location: $back?" . ($ok ? 'msg=saved' : 'error=lockout') . "\n\n");
				exit;
			}
			$member = array_flip(array_column(db_fetch_assocs_prepare('SELECT `user` FROM `team_user` WHERE `team` = ?', array($id)), 'user'));
			echo $twig->render('team_users.html', array_merge($twigarr, admin_messages(), array('team' => $team, 'page' => 'users', 'users' => $users, 'member' => $member)));
			exit;

		case 'permissions':
			if ($posted) {
				$chosen = array();
				foreach ($permissions as $p => $label) {
					if (!empty($_POST['perm'][$p])) {
						$chosen[] = $p;
					}
				}
				$ok = change_keeping_admin(function () use ($id, $chosen) {
					db_execute_prepare('DELETE FROM `team_permission` WHERE `team` = ?', array($id));
					foreach ($chosen as $p) {
						db_execute_prepare('INSERT INTO `team_permission` (`team`, `perm`) VALUES (?, ?)', array($id, $p));
					}
				});
				Header("Location: $back?" . ($ok ? 'msg=saved' : 'error=lockout') . "\n\n");
				exit;
			}
			$checked = array_flip(array_column(db_fetch_assocs_prepare('SELECT `perm` FROM `team_permission` WHERE `team` = ?', array($id)), 'perm'));
			echo $twig->render('team_permissions.html', array_merge($twigarr, admin_messages(), array('team' => $team, 'page' => 'permissions', 'permissions' => $permissions, 'checked' => $checked)));
			exit;
	}
	Header("Location: /teams/\n\n");
	exit;
}

$teams = db_fetch_assocs('SELECT `t`.*,
		(SELECT COUNT(*) FROM `team_user` AS `tu` WHERE `tu`.`team` = `t`.`id`) AS `users`,
		(SELECT GROUP_CONCAT(`perm` ORDER BY `perm`) FROM `team_permission` AS `tp` WHERE `tp`.`team` = `t`.`id`) AS `perms`
	FROM `teams` AS `t` ORDER BY `t`.`name`');
foreach ($teams as &$t) {
	$t['perms'] = array_map(function ($p) use ($permissions) { return isset($permissions[$p]) ? $permissions[$p] : $p; }, array_filter(explode(',', (string) $t['perms'])));
}
unset($t);

echo $twig->render('teams.html', array_merge($twigarr, admin_messages(), array('teams' => $teams)));

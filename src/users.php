<?php

include_once('includes/global.php');

if (!check_perm('super')) {
	header("Location: /\n\n");
	exit;
}

if (isset($_GET['user']) && $_GET['user'] == intval($_GET['user']) && intval($_GET['user'])) {
	$user = new User(intval($_GET['user']));
} else {
	$user = new User();
}

$posted = (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'POST');
$teams = reindex_arr_by_id_col(db_fetch_assocs('SELECT `id`, `name` FROM `teams` ORDER BY `name`'), 'name');

// The ids in a posted list (teams[]=1&teams[]=3) that are in $valid
function posted_ids($name, $valid) {
	$ids = array();
	if (isset($_POST[$name]) && is_array($_POST[$name])) {
		foreach ($_POST[$name] as $v) {
			if (is_scalar($v) && isset($valid[intval($v)])) {
				$ids[intval($v)] = intval($v);
			}
		}
	}
	return array_values($ids);
}

if (isset($_GET['action'])) {
	$back = '/users/' . $_GET['action'] . '/' . $user->id;

	switch ($_GET['action']) {
		case 'invite':
			if (!$posted || !isset($_POST['email'])) {
				Header("Location: /users/\n\n");
				exit;
			}
			$email = strtolower($user->clean_username(post_str('email')));
			if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
				Header("Location: /users/?error=invalidemail\n\n");
				exit;
			}
			if (db_fetch_cell_prepare('SELECT `id` FROM `users` WHERE `username` = ? OR `email` = ?', 'id', array($email, $email))) {
				Header("Location: /users/?error=exists\n\n");
				exit;
			}
			$code = random_str(32);
			$user->set_username($email);
			$user->set_email($email);
			$user->set_code($code, 7 * 86400);
			$user->save();
			// The invited user starts in the teams chosen with the invite
			$user->set_teams(posted_ids('teams', $teams));

			$html = $twig->render('emails/user_invite.html', array_merge($twigarr, array('email' => $email, 'code' => $code)));
			send_email($email, 'Invite - Ascender Ledger', $html);

			Header("Location: /users/?msg=invited\n\n");
			exit;

		case 'delete':
			csrf_verify();
			// Deleting yourself would leave the session pointing at nobody
			if ($user->id && $user->id != $account['id']) {
				$user->delete();
			}
			Header("Location: /users/?msg=deleted\n\n");
			exit;

		case 'edit':
			if ($user->id) {
				echo $twig->render('user.html', array_merge($twigarr, admin_messages(), array('user' => $user, 'page' => 'details')));
			}
			exit;

		case 'save':
			if (!$user->id || !$posted) {
				Header("Location: /users/\n\n");
				exit;
			}
			if (isset($_POST['name']) && $user->clean_name($_POST['name']) != '') {
				$user->set_name($_POST['name']);
			}

			if (isset($_POST['username']) && $user->clean_username($_POST['username']) != '') {
				$username = strtolower($user->clean_username($_POST['username']));
				if (db_fetch_cell_prepare('SELECT `id` FROM `users` WHERE `username` = ? AND `id` != ?', 'id', array($username, $user->id))) {
					Header("Location: /users/edit/" . $user->id . "?error=userexists\n\n");
					exit;
				}
				$user->set_username($username);
			}

			if (isset($_POST['email']) && $user->clean_username($_POST['email']) != '') {
				$user->set_email($_POST['email']);
			}

			// An administrator cannot disable themselves, and so lock everyone out
			if (isset($_POST['enabled']) && $user->id != $account['id']) {
				$user->set_enabled(intval($_POST['enabled']));
			}

			$newpass = (isset($_POST['newpass']) ? (string) $_POST['newpass'] : '');
			$newpass2 = (isset($_POST['newpass2']) ? (string) $_POST['newpass2'] : '');
			if ($newpass != '' && strlen($newpass) > 7 && $newpass == $newpass2) {
				$user->set_password($newpass);
				$user->set_code('');
			}
			$user->save();
			Header("Location: /users/edit/" . $user->id . "?msg=saved\n\n");
			exit;

		case 'teams':
			if (!$user->id) {
				Header("Location: /users/\n\n");
				exit;
			}
			if ($posted) {
				$chosen = posted_ids('teams', $teams);
				$ok = change_keeping_admin(function () use ($user, $chosen) {
					$user->set_teams($chosen);
				});
				Header("Location: $back?" . ($ok ? 'msg=saved' : 'error=lockout') . "\n\n");
				exit;
			}
			echo $twig->render('user_teams.html', array_merge($twigarr, admin_messages(), array('user' => $user, 'page' => 'teams',
				'teams' => $teams, 'member' => array_flip($user->teams()))));
			exit;

		case 'permissions':
			if (!$user->id) {
				Header("Location: /users/\n\n");
				exit;
			}
			if ($posted) {
				$chosen = array();
				foreach ($permissions as $p => $label) {
					if (!empty($_POST['perm'][$p])) {
						$chosen[] = $p;
					}
				}
				$ok = change_keeping_admin(function () use ($user, $chosen) {
					$user->set_permissions($chosen);
				});
				Header("Location: $back?" . ($ok ? 'msg=saved' : 'error=lockout') . "\n\n");
				exit;
			}
			/*
				Which permissions each of the user's enabled teams gives, to show beside their own
				switches.  Only their own can be changed here; a team's are changed on the team.
			*/
			$via = array();
			foreach (db_fetch_assocs_prepare('SELECT `tp`.`perm`, `t`.`name` FROM `team_permission` AS `tp`
					JOIN `team_user` AS `tu` ON `tu`.`team` = `tp`.`team`
					JOIN `teams` AS `t` ON `t`.`id` = `tp`.`team` AND `t`.`enabled` = 1
					WHERE `tu`.`user` = ? ORDER BY `t`.`name`', array($user->id)) as $r) {
				$via[$r['perm']][] = $r['name'];
			}
			echo $twig->render('user_permissions.html', array_merge($twigarr, admin_messages(), array('user' => $user, 'page' => 'permissions',
				'permissions' => $permissions, 'own' => array_flip($user->own_permissions()), 'via' => $via)));
			exit;
	}
	Header("Location: /users/\n\n");
	exit;
}

/*
	The list, with each user's teams and whether they are an administrator (by their own
	permission or a team's)
*/
$users = db_fetch_assocs('SELECT * FROM `users` ORDER BY `username`');
$member = array();
foreach (db_fetch_assocs('SELECT `team`, `user` FROM `team_user`') as $r) {
	if (isset($teams[$r['team']])) {
		$member[$r['user']][] = $teams[$r['team']];
	}
}
// Administrators by their own permission or an enabled team's, in one query rather than one per user
$admins = array_flip(array_map('intval', array_column(db_fetch_assocs("SELECT `user` FROM `user_permission` WHERE `perm` = 'super'
	UNION SELECT `tu`.`user` FROM `team_user` AS `tu`
	JOIN `team_permission` AS `tp` ON `tp`.`team` = `tu`.`team` AND `tp`.`perm` = 'super'
	JOIN `teams` AS `t` ON `t`.`id` = `tu`.`team` AND `t`.`enabled` = 1"), 'user')));
foreach ($users as &$u) {
	$u['super'] = isset($admins[intval($u['id'])]);
	$u['teams'] = (isset($member[$u['id']]) ? $member[$u['id']] : array());
	sort($u['teams']);
}
unset($u);

$default_team = array_search('Normal Users', $teams, true);
echo $twig->render('users.html', array_merge($twigarr, admin_messages(), array('users' => $users, 'teams' => $teams, 'default_team' => $default_team)));

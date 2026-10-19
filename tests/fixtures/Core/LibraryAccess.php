<?php
declare(strict_types = 1);
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

function library_access(int $graph, int $device, int $template, int $tree, int $branch): void {
	global $settings_user;
	$session  = $_SESSION;
	$settings = $settings_user;
	$auth     = read_config_option('auth_method');
	$method   = read_config_option('graph_auth_method');
	$policy   = ['policy_graphs' => 2, 'policy_hosts' => 2, 'policy_graph_templates' => 2, 'policy_trees' => 2];
	$user     = (int) sql_save($policy + ['id' => 0, 'username' => 'Access <fixture>', 'enabled' => 'on', 'password_change' => 'on'], 'user_auth');
	$group    = (int) sql_save($policy + ['id' => 0, 'name' => 'Access fixture', 'enabled' => 'on'], 'user_auth_group');
	library_expect($user > 0 && $group > 0, true, 'Access fixture owns its account and group');
	library_expect(db_execute_prepared('INSERT INTO user_auth_group_members (user_id, group_id) VALUES (?, ?)', [$user, $group]), true, 'Account is a member of the fixture group');
	set_config_option('auth_method', '1');
	set_user_setting('hide_disabled', '', $user);
	$allowed = [1 => [1, 2, 3, 4, 5, 6, 7], 2 => [1, 3, 5, 6, 7], 3 => [1, 2, 3, 5, 6, 7], 4 => [1, 3, 4, 5, 6, 7]];

	try {
		foreach (['user' => $user, 'group' => $group] as $kind => $actor) {
			$table  = $kind === 'user' ? 'user_auth_perms' : 'user_auth_group_perms';
			$column = $kind === 'user' ? 'user_id' : 'group_id';

			foreach (range(0, 7) as $mask) {
				db_execute_prepared("DELETE FROM $table WHERE $column = ?", [$actor]);

				foreach ([1 => [1, $graph], 2 => [3, $device], 4 => [4, $template]] as $bit => [$type, $id]) {
					if (($mask & $bit) !== 0) {
						library_expect(db_execute_prepared("INSERT INTO $table ($column, item_id, type) VALUES (?, ?, ?)", [$actor, $id, $type]), true, 'Access grant is stored');
					}
				}

				foreach ($allowed as $model => $masks) {
					// Each case represents a fresh request after an administrator changes policy.
					$_SESSION = [SESS_USER_ID => $user];
					auth_row_cache_purge($user);
					set_config_option('graph_auth_method', (string) $model);
					$expected = in_array($mask, $masks, true);
					$total    = 0;
					$rows     = get_allowed_graphs('', 'gl.id', '0,1', $total, $user, $graph);
					library_expect(array_map('intval', array_column($rows, 'local_graph_id')), $expected ? [$graph] : [], 'Graph listing applies the stored ' . $kind . ' grants for model ' . $model . ', mask ' . $mask);
					library_expect($total, (int) $expected, 'Graph count agrees with its filtered rows');
					$total = 0;
					$rows  = get_allowed_devices('', 'description', '0,1', $total, $user, $device);
					library_expect(array_map('intval', array_column($rows, 'id')), $expected ? [$device] : [], 'Device listing follows the permitted graph');
					library_expect($total, (int) $expected, 'Device count agrees with its filtered rows');
					$total = 0;
					$rows  = get_allowed_graph_templates('gl.id = ' . $graph, 'gt.name', '0,1', $total, $user, $template);
					library_expect(array_map('intval', array_column($rows, 'id')), $expected ? [$template] : [], 'Template listing follows the permitted graph');
					library_expect($total, (int) $expected, 'Template count agrees with its filtered rows');
					$rows = get_allowed_graph_templates_normalized('gl.id = ' . $graph, 'name', '0,1', $total, $user, $template);
					library_expect(array_column($rows, 'id'), $expected ? ['cg_' . $template] : [], 'Normalized template retains its non-query identity');
				}
			}
			db_execute_prepared("DELETE FROM $table WHERE $column = ?", [$actor]);
		}

		foreach (['user' => $user, 'group' => $group] as $kind => $actor) {
			$table  = $kind === 'user' ? 'user_auth_perms' : 'user_auth_group_perms';
			$column = $kind === 'user' ? 'user_id' : 'group_id';
			library_expect(db_execute_prepared("INSERT INTO $table ($column, item_id, type) VALUES (?, ?, 2)", [$actor, $tree]), true, 'Tree exception is stored');

			foreach ([true, false] as $enabled) {
				db_execute_prepared('UPDATE user_auth_group SET enabled = ? WHERE id = ?', [$enabled ? 'on' : '', $group]);
				$_SESSION = [SESS_USER_ID => $user];
				auth_row_cache_purge($user);
				$expected = $kind === 'user' || $enabled;
				$total    = 0;
				$trees    = get_allowed_trees(false, false, 'gt.id = ' . $tree, 'name', '0,1', $total, $user);
				library_expect(array_map('intval', array_column($trees, 'id')), $expected ? [$tree] : [], 'Only enabled group grants admit a tree');
				library_expect($total, (int) $expected, 'Tree count agrees with visible rows');
				$sql = get_allowed_trees(false, true, 'gt.id = ' . $tree, 'name', '0,1', $total, $user);
				library_expect(db_fetch_assoc($sql), $trees, 'Returned tree SQL has the same visibility as the public query');
				$branches = get_allowed_branches('gt.id = ' . $tree, 'name', '-1', $total, $user);
				library_expect(array_map('intval', array_column($branches, 'id')), $expected ? [$branch] : [], 'Branch list preserves the permitted tree header');
			}
			db_execute_prepared("DELETE FROM $table WHERE $column = ?", [$actor]);
		}

		db_execute_prepared('UPDATE user_auth_group SET enabled = ? WHERE id = ?', ['on', $group]);
		$_SESSION = [SESS_USER_ID => $user];
		set_config_option('graph_auth_method', '1');
		library_expect(is_tree_branch_empty($tree, $branch), true, 'A branch with only an inaccessible graph is empty for its viewer');
		db_execute_prepared('INSERT INTO user_auth_perms (user_id, item_id, type) VALUES (?, ?, 1)', [$user, $graph]);
		$_SESSION = [SESS_USER_ID => $user];
		library_expect(is_tree_branch_empty($tree, $branch), false, 'The same branch is populated after a graph grant');
		library_expect(is_tree_branch_empty($tree), false, 'The visible graph makes its ancestor branch nonempty');
		library_user_menu($user);
	} finally {
		db_execute_prepared('DELETE FROM user_auth_group_perms WHERE group_id = ?', [$group]);
		db_execute_prepared('DELETE FROM user_auth_group_members WHERE group_id = ?', [$group]);
		db_execute_prepared('DELETE FROM user_auth_group WHERE id = ?', [$group]);
		user_remove($user);
		set_config_option('auth_method', $auth);
		set_config_option('graph_auth_method', $method);
		$_SESSION      = $session;
		$settings_user = $settings;
	}
}

function library_user_menu(int $user): void {
	foreach ([[0, 'on', true], [1, 'on', false], [0, '', false]] as [$realm, $change, $passwordLink]) {
		db_execute_prepared('UPDATE user_auth SET realm = ?, password_change = ? WHERE id = ?', [$realm, $change, $user]);

		foreach ([true, false] as $allowed) {
			db_execute_prepared('DELETE FROM user_auth_realm WHERE user_id = ?', [$user]);

			if ($allowed) {
				db_execute_prepared('INSERT INTO user_auth_realm (user_id, realm_id) VALUES (?, 20), (?, 28)', [$user, $user]);
			}

			foreach (['1', (string) AUTH_METHOD_BASIC] as $auth) {
				set_config_option('auth_method', $auth);
				$_SESSION = [SESS_USER_ID => $user];
				$dom      = library_form_dom(static fn () => draw_login_status());
				library_expect($dom->evaluate('string(//span[@id="user"])'), 'Access <fixture>', 'User menu displays the stored username as text');
				library_expect($dom->query('//fixture')->length, 0, 'Username markup cannot become an element');
				library_expect($dom->query('//a[contains(@href,"auth_profile.php")]')->length, (int) $allowed, 'Profile action follows its realm permission');
				library_expect($dom->query('//a[contains(@href,"auth_changepassword.php")]')->length, (int) $passwordLink, 'Only eligible local accounts get the password action');
				library_expect($dom->query('//li[@id="userCommunity"]')->length, (int) $allowed, 'Community link follows its realm permission');
				library_expect($dom->query('//a[contains(@href,"logout.php")]')->length, (int) ($auth !== (string) AUTH_METHOD_BASIC), 'Basic authentication omits the local logout action');
			}
		}
	}

	foreach (['1', (string) AUTH_METHOD_BASIC] as $auth) {
		set_config_option('auth_method', $auth);
		$_SESSION = [SESS_USER_ID => get_guest_account()];
		$dom      = library_form_dom(static fn () => draw_login_status(true));
		library_expect($dom->evaluate('string(//span[@id="user"])'), 'guest', 'Guest menu identifies the guest session');
		library_expect($dom->query('//a[contains(@href,"login=true")]')->length, (int) ($auth !== (string) AUTH_METHOD_BASIC), 'Guest login action matches the authentication method');
	}
}

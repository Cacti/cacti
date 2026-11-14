<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

namespace CactiTreePermissionTypeFixture;

use TreeSqliteFixture;

/*
 * user_auth_perms and user_auth_group_perms share one item_id column across
 * permission types (1 graph, 2 tree, 3 device, 4 graph template). These tests
 * run the unchanged lib/auth.php functions against SQLite and check that a
 * permission row only counts for its own type, and that each group's tree
 * policy is decided by that group's own tree exceptions.
 */

require_once dirname(__DIR__) . '/Helpers/SourceSlice.php';
require_once dirname(__DIR__) . '/Helpers/TreeSqliteFixture.php';

TreeSqliteFixture::install(__NAMESPACE__);

function read_config_option(string $name, bool $force = false) : mixed {
	return $name == 'auth_method' ? 1 : '';
}

foreach (['auth_check_perms', 'is_tree_allowed', 'get_simple_device_perms'] as $function) {
	cacti_fixture_source_function(dirname(__DIR__, 2) . '/lib/auth.php', $function, __NAMESPACE__);
}

const USER = 7;

const TREE = 5;

function seed(int $user_tree_policy, int $user_host_policy = 1) : void {
	$pdo = TreeSqliteFixture::reset();
	$pdo->exec('INSERT INTO user_auth (id, policy_trees, policy_hosts) VALUES (' . USER . ", $user_tree_policy, $user_host_policy)");
}

function group(int $id, int $tree_policy, array $perms = [], string $enabled = 'on', int $host_policy = 1) : void {
	$pdo = TreeSqliteFixture::$pdo;
	$pdo->exec("INSERT INTO user_auth_group (id, enabled, policy_trees, policy_hosts) VALUES ($id, '$enabled', $tree_policy, $host_policy)");
	$pdo->exec("INSERT INTO user_auth_group_members (group_id, user_id) VALUES ($id, " . USER . ')');

	foreach ($perms as [$item_id, $type]) {
		$pdo->exec("INSERT INTO user_auth_group_perms (group_id, item_id, type) VALUES ($id, $item_id, $type)");
	}
}

function user_perm(int $item_id, int $type) : void {
	TreeSqliteFixture::$pdo->exec('INSERT INTO user_auth_perms (user_id, item_id, type) VALUES (' . USER . ", $item_id, $type)");
}

function tree_allowed() : bool {
	$_SESSION = ['sess_user_id' => USER];

	return is_tree_allowed(TREE);
}

afterEach(function () {
	$_SESSION = [];
});

test('a group graph permission sharing the tree id does not grant a deny-by-default tree', function () {
	seed(2);
	group(1, 2, [[TREE, 1], [TREE, 3], [TREE, 4]]);

	expect(tree_allowed())->toBeFalse();
});

test('a group tree permission grants a deny-by-default tree', function () {
	seed(2);
	group(1, 2, [[TREE, 2]]);

	expect(tree_allowed())->toBeTrue();
});

test('a group allow-by-default policy honours that group tree exception', function () {
	seed(2);
	group(1, 1, [[TREE, 2]]);

	expect(tree_allowed())->toBeFalse();
});

test('a group allow-by-default policy without a tree exception grants the tree', function () {
	seed(2);
	group(1, 1, [[TREE, 1]]);

	expect(tree_allowed())->toBeTrue();
});

test('one group exception does not hide a tree another group grants', function () {
	seed(2);
	group(1, 1, [[TREE, 2]]);
	group(2, 2, [[TREE, 2]]);

	expect(tree_allowed())->toBeTrue();
});

test('a disabled group grants nothing', function () {
	seed(2);
	group(1, 2, [[TREE, 2]], '');

	expect(tree_allowed())->toBeFalse();
});

test('user tree exceptions keep their existing meaning', function () {
	seed(2);
	user_perm(TREE, 2);
	expect(tree_allowed())->toBeTrue();

	seed(1);
	user_perm(TREE, 2);
	expect(tree_allowed())->toBeFalse();

	seed(1);
	user_perm(TREE, 1);
	expect(tree_allowed())->toBeTrue();
});

test('simple device permissions count device exceptions, not tree exceptions', function () {
	seed(1, 1);
	user_perm(9, 3);
	expect(get_simple_device_perms(USER))->toBeFalse();

	seed(1, 1);
	user_perm(9, 2);
	expect(get_simple_device_perms(USER))->toBeTrue();

	seed(2, 2);
	group(1, 1, [[9, 3]], 'on', 1);
	expect(get_simple_device_perms(USER))->toBeFalse();
});

/**
 * The tree policy written out directly: the user's own policy with the
 * user's tree exceptions, or any enabled group's policy with that group's
 * tree exceptions, grants the tree.  Policy 1 allows unless excepted,
 * policy 2 denies unless excepted.  Only type 2 rows for this tree count.
 */
function reference_tree_allowed(int $user_policy, array $user_perms, array $groups) : bool {
	$decides = function (int $policy, array $perms) : bool {
		$excepted = in_array([TREE, 2], $perms, true);

		return $policy == 1 ? !$excepted : $excepted;
	};

	if ($decides($user_policy, $user_perms)) {
		return true;
	}

	foreach ($groups as $g) {
		if ($g['enabled'] == 'on' && $decides($g['policy'], $g['perms'])) {
			return true;
		}
	}

	return false;
}

test('is_tree_allowed matches the reference policy for random users and groups', function () {
	// A fixed-seed generator keeps the 2000 cases reproducible; this is test data, not a secret.
	$state = 8672;
	$next  = function (int $min, int $max) use (&$state) : int {
		$state = ($state * 1103515245 + 12345) & 0x7fffffff;

		return $min + (($state >> 8) % ($max - $min + 1));
	};

	$random_perms = function () use ($next) : array {
		$perms = [];

		foreach ([TREE, TREE + 1] as $item_id) {
			foreach ([1, 2, 3, 4] as $type) {
				if ($next(0, 2) == 0) {
					$perms[] = [$item_id, $type];
				}
			}
		}

		return $perms;
	};

	$mismatches = [];

	for ($case = 0; $case < 2000; $case++) {
		$user_policy = $next(1, 2);
		$user_perms  = $random_perms();
		$groups      = [];

		seed($user_policy);

		foreach ($user_perms as [$item_id, $type]) {
			user_perm($item_id, $type);
		}

		for ($id = 1, $count = $next(0, 4); $id <= $count; $id++) {
			$groups[] = ['policy' => $next(1, 2), 'perms' => $random_perms(), 'enabled' => $next(0, 4) ? 'on' : ''];
			$g        = end($groups);

			group($id, $g['policy'], $g['perms'], $g['enabled']);
		}

		if (tree_allowed() !== reference_tree_allowed($user_policy, $user_perms, $groups)) {
			$mismatches[] = $case;
		}
	}

	expect($mismatches)->toBe([]);
});

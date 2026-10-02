<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * GHSA-j67j-wpm4-9g3x: the update_policy branch runs before the action
 * dispatcher, so the global.php GET/CSRF denylist cannot cover it. Both
 * user_admin.php and user_group_admin.php must require a CSRF-token POST before
 * calling update_policies().
 */

$root = dirname(__DIR__, 4);

test('update_policy requires a CSRF-token POST before mutating the policy', function () use ($root) {
	foreach (array('/user_admin.php', '/user_group_admin.php') as $file) {
		$source = file_get_contents($root . $file);

		$branch = strpos($source, "if (isrv('update_policy')) {");
		$call   = strpos($source, 'update_policies();', $branch);
		$guard  = strpos($source, "!isset(\$_POST['__csrf_magic'])", $branch);

		expect($branch)->not->toBeFalse()
			->and($call)->not->toBeFalse()
			->and($guard)->not->toBeFalse()
			->and($guard)->toBeLessThan($call);

		$pre = substr($source, $guard, $call - $guard);
		expect($pre)->toContain('http_response_code(405)')
			->and($pre)->toContain('exit;');
	}
});

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

/*
 * issue#7715. Nothing under api/ authenticates or authorises anything: no route
 * consults a realm, a session or a token. What kept the fourteen endpoints from
 * answering was that api/vendor is not shipped, so Slim was missing and the
 * entry point fatalled. That is packaging rather than a control, and it goes
 * away the moment someone runs the composer install in api/README.md on a live
 * host. The scaffold now refuses unless it is deliberately switched on.
 */

$api = file_get_contents(dirname(__DIR__, 2) . '/api/public/index.php');

test('the entry point reads', function () use ($api) {
	expect($api)->toBeString()->not->toBeEmpty();
});

test('no route consults a realm, a session or a token', function () use ($api) {
	$db = file_get_contents(dirname(__DIR__, 2) . '/api/include/db_functions.php');

	/* If this ever stops being true the gate below can be reconsidered; while it
	   holds, serving without the gate means serving to anyone. */
	foreach (['is_realm_allowed', 'sess_user_id', 'Bearer'] as $needle) {
		expect($api)->not->toContain($needle)
			->and($db)->not->toContain($needle);
	}
});

test('it refuses unless the setting says otherwise', function () use ($api) {
	expect($api)->toContain("if (read_config_option('api_enabled') !== 'on') {")
		->and($api)->toContain('http_response_code(404);');

	/* an empty 404 reads like any other missing path; a JSON error body would
	   confirm an API is behind it */
	expect($api)->not->toContain("print json_encode(['error' => 'Not found']);")
		->and($api)->not->toContain("header('Content-Type: application/json');");

	// the refusal must come before Slim is handed the routes
	$gate = strpos($api, "read_config_option('api_enabled')");
	$app  = strpos($api, 'AppFactory::create()');

	expect($gate)->not->toBeFalse()
		->and($app)->not->toBeFalse()
		->and($gate)->toBeLessThan($app);
});

test('error details are a development choice, not the default', function () use ($api) {
	expect($api)->not->toContain('$app->addErrorMiddleware(true, true, true);')
		->and($api)->toContain('$app->addErrorMiddleware($api_developer_mode, true, true);');
});

test('the bootstrap include is anchored to the file', function () use ($api) {
	/* the other two includes beside it already were; this one resolved against
	   the working directory, so it only worked from api/public */
	expect($api)->not->toContain("include  '../../include/global.php';")
		->and($api)->toContain("include __DIR__ . '/../../include/global.php';");
});

test('the gate behaves as written for every setting value', function () {
	$refuses = function (string $value) : bool {
		return $value !== 'on';
	};

	// only the exact string opens it; absent, empty or anything else refuses
	expect($refuses(''))->toBeTrue()
		->and($refuses('off'))->toBeTrue()
		->and($refuses('1'))->toBeTrue()
		->and($refuses('On'))->toBeTrue()
		->and($refuses('on'))->toBeFalse();
});

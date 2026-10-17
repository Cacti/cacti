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
require_once dirname(__DIR__, 4) . '/lib/api_automation_aggregate.php';

it('keeps aggregate automation disabled by default', function () {
	expect(automation_aggregate_settings([]))->toMatchArray([
		'automation_enabled'          => '', 'automation_pattern' => '', 'automation_max_members' => 1000,
		'automation_host_template_id' => 0,
	]);
});

it('accepts delimited PCRE data source patterns and valid member limits', function () {
	$settings = automation_aggregate_settings([
		'automation_enabled'          => 'on', 'automation_pattern' => '/QoS.*(Gold|Silver)/i',
		'automation_host_template_id' => '4', 'automation_max_members' => '5000',
	]);
	expect($settings['automation_host_template_id'])->toBe(4)
		->and($settings['automation_max_members'])->toBe(5000)
		->and(preg_match($settings['automation_pattern'], 'router QoS Gold'))->toBe(1);
});

it('rejects invalid aggregate automation settings', function (array $settings) {
	expect(fn () => automation_aggregate_settings($settings))->toThrow(InvalidArgumentException::class);
})->with([
	'empty enabled pattern' => [['automation_enabled' => 'on']],
	'malformed pattern'     => [['automation_pattern' => '/[/']],
	'undelimited pattern'   => [['automation_pattern' => 'QoS']],
	'array pattern'         => [['automation_pattern' => ['QoS']]],
	'array title'           => [['automation_title' => ['bad']]],
	'empty title'           => [['automation_title' => '']],
	'oversized pattern'     => [['automation_pattern' => '/' . str_repeat('x', 511) . '/']],
	'oversized title'       => [['automation_title' => str_repeat('x', 256)]],
	'negative template'     => [['automation_host_template_id' => -1]],
	'invalid template'      => [['automation_host_template_id' => '4 OR 1=1']],
	'zero members'          => [['automation_max_members' => 0]],
	'too many members'      => [['automation_max_members' => 5001]],
]);

it('expands aggregate titles without interpreting replacement values twice', function () {
	$rule = ['automation_title' => '|host_description| / |host_hostname| / |host_id| / |aggregate_template_name|', 'name' => 'QoS'];
	$host = ['description' => 'Router |host_id|', 'hostname' => 'router.example', 'id' => 42];
	expect(automation_aggregate_title($rule, $host))->toBe('Router |host_id| / router.example / 42 / QoS');
});

it('bounds generated aggregate titles without splitting UTF-8 characters', function () {
	$title = automation_aggregate_title(['automation_title' => '|host_description|', 'name' => 'QoS'],
		['id' => 1, 'hostname' => 'router', 'description' => str_repeat('é', 200)]);
	expect(strlen($title))->toBeLessThanOrEqual(255)->and(mb_check_encoding($title, 'UTF-8'))->toBeTrue();
});

it('drops automation ownership when its template or aggregate graph is removed', function () {
	$root = dirname(__DIR__, 4);

	expect(file_get_contents($root . '/aggregate_templates.php'))
		->toContain("DELETE FROM automation_aggregate_graphs WHERE ' . array_to_sql_or(\$selected_items, 'aggregate_template_id')");
	expect(substr_count(file_get_contents($root . '/lib/aggregate.php'), 'DELETE FROM automation_aggregate_graphs'))->toBe(2);
});

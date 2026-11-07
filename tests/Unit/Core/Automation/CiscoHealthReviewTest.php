<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
require_once dirname(__DIR__, 3) . '/Helpers/SourceSlice.php';
require_once dirname(__DIR__, 3) . '/Helpers/CiscoHealthReviewFixture.php';

foreach (['cisco_health_graph_kinds', 'cisco_health_graph_kind', 'cisco_health_graph_is_managed'] as $name) {
	cacti_fixture_source_function(dirname(__DIR__, 4) . '/lib/cisco_health_graphs.php', $name, 'CactiTests\\CiscoHealthReview');
}
cacti_fixture_source_function(dirname(__DIR__, 4) . '/lib/api_graph.php', 'api_graph_change_device', 'CactiTests\\CiscoHealthReview');

foreach (['cisco_health_number', 'cisco_health_qfp_memory', 'cisco_health_query', 'ss_cisco_health'] as $name) {
	cacti_fixture_source_function(dirname(__DIR__, 4) . '/scripts/ss_cisco_health.php', $name, 'CactiTests\\CiscoHealthReview');
}

test('ordinary templates are classified once including negative matches', function () {
	$state             = \CactiTests\CiscoHealthReview\State::class;
	$state::$hashReads = 0;

	for ($i = 0; $i < 10; $i++) {
		expect(\CactiTests\CiscoHealthReview\cisco_health_graph_kind(900))->toBeNull();
		expect(\CactiTests\CiscoHealthReview\cisco_health_graph_is_managed(44, 900))->toBeFalse();
	}
	expect($state::$hashReads)->toBe(1);
});

test('managed graph moves are rejected before graph or data source mutation', function () {
	\CactiTests\CiscoHealthReview\State::$hashes[7] = 'd0e843dee0462653833556d6eafa87d3';
	expect(\CactiTests\CiscoHealthReview\api_graph_change_device(55, 2))->toBeFalse();
});

test('adjacent used free reads share metadata but repeats and delays refresh it', function () {
	$state         = \CactiTests\CiscoHealthReview\State::class;
	$state::$reads = 0;
	$state::$oids  = [];
	$state::$clock = 0;
	$validity      = '.1.3.6.1.4.1.9.9.221.1.1.1.1.6.1.1';

	foreach (['used', 'free'] as $field) {
		expect(\CactiTests\CiscoHealthReview\ss_cisco_health(1, 'memory', 'get', $field, '1.1'))->toBe('100');
	}
	expect($state::$reads)->toBe(1);
	expect($state::$oids[$validity])->toBe(1);

	foreach (['used', 'used'] as $field) {
		\CactiTests\CiscoHealthReview\ss_cisco_health(1, 'memory', 'get', $field, '1.1');
	}
	expect($state::$reads)->toBe(3);
	$state::$clock = 6000000000;
	\CactiTests\CiscoHealthReview\ss_cisco_health(1, 'memory', 'get', 'free', '1.1');
	expect($state::$reads)->toBe(4);
	\CactiTests\CiscoHealthReview\ss_cisco_health(2, 'memory', 'get', 'used', '1.1');
	expect($state::$reads)->toBe(5);
});

cacti_fixture_source_function(dirname(__DIR__, 4) . '/lib/cisco_health_graphs.php', 'cisco_health_graph_host_change_allowed', 'CactiTests\\CiscoHealthReview');
cacti_fixture_source_function(dirname(__DIR__, 4) . '/graphs.php', 'form_save', 'CactiTests\\CiscoHealthReview');

test('editor rejects a device change using stored ownership before any write', function () {
	$state           = \CactiTests\CiscoHealthReview\State::class;
	$state::$request = ['save_component_graph'=> '1', 'local_graph_id'=>55, 'host_id'=>2,
		'host_id_prev'                           => 2, 'graph_template_id'=>0, 'graph_template_id_prev'=>0];
	$state::$hashes[7] = 'd0e843dee0462653833556d6eafa87d3';
	$state::$events    = [];
	$state::$messages  = [];

	try {
		\CactiTests\CiscoHealthReview\form_save();
	} catch (RuntimeException $e) {
		expect($e->getMessage())->toBe('Test redirect');
	}
	expect($state::$messages[0][0])->toBe('combined_health_move');
	expect($state::$redirect)->toContain('graphs.php');
	expect($state::$events)->toBe([]);
	expect(\CactiTests\CiscoHealthReview\cisco_health_graph_host_change_allowed(55, 1))->toBeTrue();
});

cacti_fixture_source_function(dirname(__DIR__, 4) . '/lib/api_graph.php', 'api_graph_remove', 'CactiTests\\CiscoHealthReview');

test('internal graph cleanup retains hooks without consuming enclosing counter snapshots', function () {
	$state          = \CactiTests\CiscoHealthReview\State::class;
	$state::$events = [];
	\CactiTests\CiscoHealthReview\api_graph_remove(55, false);
	expect($state::$events)->toBe(['hook', 'aggregate', 'delete', 'delete', 'delete', 'delete', 'delete', 'invalidate']);
	$state::$events = [];
	\CactiTests\CiscoHealthReview\api_graph_remove(55);
	expect($state::$events)->toBe(['snapshot', 'hook', 'aggregate', 'delete', 'delete', 'delete', 'delete', 'delete', 'counters', 'invalidate']);
});

test('copies and ordinary graphs sharing managed data cannot move through API or editor', function () {
	$state = \CactiTests\CiscoHealthReview\State::class;

	try {
		foreach ([7, 0] as $template) {
			$state::$template = $template;
			$state::$shared   = true;
			$state::$events   = [];
			expect(\CactiTests\CiscoHealthReview\api_graph_change_device(56, 2))->toBeFalse();
			expect($state::$events)->toBe([]);
			$state::$request = ['save_component_graph'=> '1', 'local_graph_id'=>56, 'host_id'=>2,
				'host_id_prev'                           => 2, 'graph_template_id'=>0, 'graph_template_id_prev'=>0];
			$state::$messages = [];

			try {
				\CactiTests\CiscoHealthReview\form_save();
			} catch (RuntimeException $e) {
				expect($e->getMessage())->toBe('Test redirect');
			}
			expect($state::$messages[0][0])->toBe('combined_health_move');
			expect($state::$events)->toBe([]);
			expect(\CactiTests\CiscoHealthReview\cisco_health_graph_host_change_allowed(56, 1))->toBeTrue();
			$state::$shared = false;
			expect(\CactiTests\CiscoHealthReview\cisco_health_graph_host_change_allowed(56, 2))->toBeTrue();
		}
	} finally {
		$state::$shared   = false;
		$state::$template = 7;
	}
});

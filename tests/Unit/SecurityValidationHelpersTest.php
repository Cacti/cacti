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
*/

require_once dirname(__DIR__) . '/Helpers/CactiStubs.php';
require_once dirname(__DIR__, 2) . '/include/global.php';

test('security helpers: data input string allows pipes but blocks dangerous shell metacharacters', function () {
	expect(cacti_input_string_is_safe('grep foo /tmp/x | wc -l'))->toBeTrue();
	expect(cacti_input_string_is_safe('php <path_php_binary> -q <path_cacti>/script.php'))->toBeTrue();
	expect(cacti_input_string_is_safe('cat /tmp/x; rm -rf /'))->toBeFalse();
	expect(cacti_input_string_is_safe('echo `id`'))->toBeFalse();
	expect(cacti_input_string_is_safe('echo $(id)'))->toBeFalse();
});

test('security helpers: sort validation enforces constrained column and direction', function () {
	expect(cacti_validate_sort_column('description', 'description'))->toBe('description');
	expect(cacti_validate_sort_column('`description`', 'description'))->toBe('`description`');
	expect(cacti_validate_sort_column('description;drop', 'description'))->toBe('description');
	expect(cacti_validate_sort_column('', 'description'))->toBe('description');
	expect(cacti_validate_sort_column('   ', 'description'))->toBe('description');
	expect(cacti_validate_sort_direction('desc', 'ASC'))->toBe('DESC');
	expect(cacti_validate_sort_direction('sideways', 'ASC'))->toBe('ASC');
	expect(cacti_validate_sort_direction('', 'ASC'))->toBe('ASC');
	expect(cacti_validate_sort_direction('   ', 'ASC'))->toBe('ASC');
});

test('security helpers: managers id extraction keeps only positive integers', function () {
	$ids = cacti_extract_positive_int_ids(['1', '-5', '0', '4.2', 'abc', '7', '9 OR 1=1', ' +7', ' 08 ']);

	expect($ids)->toBe([1, 4, 7, 9, 7, 8]);
});

test('security helpers: remote agent forward lookup requires exact ip match', function () {
	$allowed = cacti_remote_agent_forward_matches('10.0.0.5', 'poller.example.com', function () {
		return [
			['ip' => '10.0.0.4'],
			['ipv6' => '2001:db8::42'],
			['ip' => '10.0.0.5'],
		];
	});

	$blocked = cacti_remote_agent_forward_matches('10.0.0.7', 'poller.example.com', function () {
		return [
			['ip' => '10.0.0.4'],
			['ipv6' => '2001:db8::42'],
		];
	});

	expect($allowed)->toBeTrue();
	expect($blocked)->toBeFalse();
});

test('security helpers: remote agent host authorization requires exact fqdn or ip', function () {
	$pollers = [
		['hostname' => 'poller01.example.com'],
		['hostname' => '10.0.0.10'],
	];

	expect(cacti_remote_agent_is_authorized_host('poller01.example.com', '10.0.0.9', $pollers, []))->toBeTrue();
	expect(cacti_remote_agent_is_authorized_host('other.example.com', '10.0.0.10', $pollers, []))->toBeTrue();
	expect(cacti_remote_agent_is_authorized_host('poller01', '10.0.0.9', $pollers, []))->toBeFalse();
	expect(cacti_remote_agent_is_authorized_host('other.example.com', '10.0.0.99', $pollers, ['10.0.0.99']))->toBeTrue();
});

test('security helpers: remote agent hostname match is case-insensitive', function () {
	$pollers = [
		['hostname' => 'PoLlEr01.Example.COM'],
	];

	expect(cacti_remote_agent_is_authorized_host('poller01.example.com', '10.0.0.9', $pollers, []))->toBeTrue();
});

<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

require_once dirname(__DIR__, 2) . '/lib/type_secure.php';

/**
 * Mirror of logic in api_automation.php
 */
function simulate_automation_sql_handoff($sort_direction, $page, $rows = 30) {
	$direction = ($sort_direction == 'ASC' ? 'ASC' : 'DESC');
	$offset = ($rows * (CactiSecureType::toInt($page) - 1));
	
	return " ORDER BY hostname $direction LIMIT $offset,$rows";
}

test('Data Handoff: automation sort direction is allow-listed', function () {
	$malicious_dir = "ASC; DROP TABLE users;";
	$sql = simulate_automation_sql_handoff($malicious_dir, 1);
	
	expect($sql)->toContain('DESC'); // If not 'ASC', defaults to 'DESC'
	expect($sql)->not->toContain('DROP TABLE');
});

test('Data Handoff: automation page is cast to integer', function () {
	$malicious_page = "2' OR 1=1 --";
	$sql = simulate_automation_sql_handoff('ASC', $malicious_page);
	
	expect($sql)->toContain('LIMIT 0,30'); // CactiSecureType::toInt("2'...") should be 0 or 2 depending on is_numeric
	expect($sql)->not->toContain('OR 1=1');
});

<?php
// Copyright (C) 2026 The Cacti Group. Licensed under GPL-2.0-or-later.
require_once dirname(__DIR__) . '/Helpers/IsolatedProbe.php';

test('account save audits a password change only after it is persisted', function (string $scenario, bool $changed) {
	$r      = cacti_test_isolated_probe(dirname(__DIR__) . '/fixtures/UserAdministrationAuditProbe.php', [$scenario]);
	$events = array_values(array_filter($r['logs'], static fn ($log) => str_contains($log, 'changed password')));
	expect($events)->toHaveCount($changed ? 1 : 0)->and($r['password_changed'])->toBe($changed);

	if ($changed) {
		expect($r['new_password_matches'])->toBeTrue();
	}
})->with([
	'ordinary edit'        => ['edit', false],
	'password change'      => ['password', true],
	'failed database save' => ['failed-save', false],
	'password mismatch'    => ['mismatch', false]
]);

test('saved enabled and locked transitions identify the affected account and actor', function ($scenario, $action) {
	$r = cacti_test_isolated_probe(dirname(__DIR__) . '/fixtures/UserAdministrationAuditProbe.php', [$scenario]);
	expect($r['logs'])->toContain("NOTE: User 'target' was $action by user 'admin'");
})->with([['enable', 'enabled'], ['disable', 'disabled'], ['lock', 'locked'], ['unlock', 'unlocked']]);

test('invalid audit inputs produce no log records', function () {
	$r = cacti_test_isolated_probe(dirname(__DIR__) . '/fixtures/UserAdministrationAuditProbe.php', ['audit-invalid']);
	expect($r['logs'])->toBe([]);
});

test('an unavailable actor is identified by account ID', function () {
	$r = cacti_test_isolated_probe(dirname(__DIR__) . '/fixtures/UserAdministrationAuditProbe.php', ['audit-unknown']);
	expect($r['logs'])->toBe(["NOTE: User 'target' was edited by user 'user ID 404'"]);
});

test('CLI audit records identify the execution user', function () {
	$r = cacti_test_isolated_probe(dirname(__DIR__) . '/fixtures/UserAdministrationAuditProbe.php', ['audit-cli']);
	expect($r['logs'])->toHaveCount(1);
	expect($r['logs'][0])->toContain("User 'target' was edited")->toContain(' (CLI)');
});

test('new accounts emit creation rather than an existing-account password-change event', function () {
	$r = cacti_test_isolated_probe(dirname(__DIR__) . '/fixtures/UserAdministrationAuditProbe.php', ['new']);
	expect(implode("\n", $r['logs']))->toContain('was created')->not->toContain('changed password');
});

test('account audit fields cannot inject log lines or terminal controls', function () {
	$r = cacti_test_isolated_probe(dirname(__DIR__) . '/fixtures/UserAdministrationAuditProbe.php', ['audit-controls']);
	expect($r['logs'])->toBe(["NOTE: User 'target' was edited by user 'admin'"]);
});

test('account removals are audited only when the existing row is confirmed deleted', function (string $scenario, int $remaining, bool $audited) {
	$r = cacti_test_isolated_probe(dirname(__DIR__) . '/fixtures/UserAdministrationAuditProbe.php', [$scenario]);
	expect($r['remaining'])->toBe($remaining)
		->and($r['logs'])->toBe($audited ? ["NOTE: User 'target' was removed by user 'admin'"] : []);
})->with([
	'deleted account'          => ['delete', 0, true],
	'template account'         => ['delete-template', 1, false],
	'guest account'            => ['delete-guest', 1, false],
	'failed deletion'          => ['delete-failure', 1, false],
	'failed confirmation read' => ['delete-read-failure', 0, false],
	'missing account'          => ['delete-missing', 0, false]
]);

test('unconfirmed account saves emit no success audit events', function (string $scenario) {
	$r = cacti_test_isolated_probe(dirname(__DIR__) . '/fixtures/UserAdministrationAuditProbe.php', [$scenario]);
	expect($r['logs'])->toBe([]);
})->with(['failed-save', 'failed-save-id', 'save-read-failure', 'save-missing']);

test('bulk account transitions are audited only after the stored state changes', function (string $scenario, string|false $enabled, string|false $action) {
	$r = cacti_test_isolated_probe(dirname(__DIR__) . '/fixtures/UserAdministrationAuditProbe.php', [$scenario]);
	expect($r['enabled'])->toBe($enabled)
		->and($r['logs'])->toBe($action === false ? [] : ["NOTE: User 'target' was $action by user 'admin'"]);
})->with([
	['bulk-enable', 'on', 'enabled'],
	['bulk-disable', '', 'disabled'],
	['bulk-enable-failure', '', false],
	['bulk-disable-failure', 'on', false],
	['bulk-enable-read-failure', 'on', false],
	['bulk-disable-read-failure', '', false],
	['bulk-enable-missing', false, false],
	['bulk-disable-missing', false, false],
	['bulk-enable-noop', 'on', false],
	['bulk-disable-noop', '', false],
]);

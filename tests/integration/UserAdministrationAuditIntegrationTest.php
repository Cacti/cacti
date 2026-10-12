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

test('new accounts emit creation rather than an existing-account password-change event', function () {
	$r = cacti_test_isolated_probe(dirname(__DIR__) . '/fixtures/UserAdministrationAuditProbe.php', ['new']);
	expect(implode("\n", $r['logs']))->toContain('was created')->not->toContain('changed password');
});

<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

require_once dirname(__DIR__, 2) . '/Helpers/IsolatedProbe.php';
test('mysqladmin polling keeps credentials private and removes option files after success and failure (#7925)', function () {
    $result = cacti_test_isolated_probe(dirname(__DIR__, 2) . '/fixtures/Core/MysqlPollCredentials.php');
    expect($result['requests'])->toBe(14)->and($result['client_calls'])->toBe(8)->and($result['credential_files_removed'])->toBeTrue();
});

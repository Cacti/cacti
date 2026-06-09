<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

$authSource = file_get_contents(dirname(__DIR__, 2) . '/lib/auth.php');

// --- GHSA-9ffc-rr2g-c8hh: Remote-User header gate ---

test('GHSA-9ffc-rr2g-c8hh: get_basic_auth_username checks auth_method before reading headers', function () use ($authSource) {
    // The fix gates all header reads behind a read_config_option('auth_method') == 2
    // check; without it any proxy can spoof arbitrary usernames.
    $start = strpos($authSource, 'function get_basic_auth_username(');
    expect($start)->not->toBeFalse();

    $body = substr($authSource, $start, 600);
    expect($body)->toContain("read_config_option('auth_method')");
});

test('GHSA-9ffc-rr2g-c8hh: auth_method guard compares against 2', function () use ($authSource) {
    $start = strpos($authSource, 'function get_basic_auth_username(');
    expect($start)->not->toBeFalse();

    $body = substr($authSource, $start, 600);
    // Must use != 2 (or == 2) to gate the Basic-Auth method specifically.
    expect($body)->toContain("read_config_option('auth_method') != 2");
});

test('GHSA-9ffc-rr2g-c8hh: function returns false when auth_method is not 2', function () use ($authSource) {
    $start = strpos($authSource, 'function get_basic_auth_username(');
    expect($start)->not->toBeFalse();

    // The guard block: if (read_config_option('auth_method') != 2) { return false; }
    // returnfalse is at ~336 chars into the function; use 400 to be safe.
    $body = substr($authSource, $start, 400);
    expect($body)->toContain('return false;');
});

test('GHSA-9ffc-rr2g-c8hh: header reads only appear after the auth_method gate', function () use ($authSource) {
    $start = strpos($authSource, 'function get_basic_auth_username(');
    expect($start)->not->toBeFalse();

    $body = substr($authSource, $start, 1200);

    $guardPos = strpos($body, "read_config_option('auth_method') != 2");

    // Use $_SERVER['...'] forms to skip the mention in the docblock comment.
    $remoteUserPos = strpos($body, "\$_SERVER['REMOTE_USER']");
    $httpRemotePos = strpos($body, "\$_SERVER['HTTP_REMOTE_USER']");
    $phpAuthPos    = strpos($body, "\$_SERVER['PHP_AUTH_USER']");

    // Guard must exist and must come before every actual server-variable access.
    expect($guardPos)->not->toBeFalse();
    expect($remoteUserPos)->toBeGreaterThan($guardPos);
    expect($httpRemotePos)->toBeGreaterThan($guardPos);
    expect($phpAuthPos)->toBeGreaterThan($guardPos);
});

// --- GHSA-3jj2-v5ch-wmq5: LDAP realm boundary ---

test('GHSA-3jj2-v5ch-wmq5: domains_login_process uses >= 3 realm boundary', function () use ($authSource) {
    // realm=3 is a valid domain realm; > 3 would allow it to bypass the LDAP bind.
    // The guard and comment sit ~605-640 chars into the function body.
    $start = strpos($authSource, 'function domains_login_process(');
    expect($start)->not->toBeFalse();

    $body = substr($authSource, $start, 800);
    expect($body)->toContain('$realm >= 3');
});

test('GHSA-3jj2-v5ch-wmq5: realm boundary comment cites the advisory', function () use ($authSource) {
    $start = strpos($authSource, 'function domains_login_process(');
    expect($start)->not->toBeFalse();

    $body = substr($authSource, $start, 800);
    expect($body)->toContain('GHSA-3jj2-v5ch-wmq5');
});

// --- GHSA-2px8-gvmq-85f3: LDAP lockout call-site ---

test('GHSA-2px8-gvmq-85f3: lockout condition uses error_num not error_text', function () use ($authSource) {
    // error_text is a human-readable string; using it in a numeric comparison
    // always evaluates to zero (false), silently skipping the lockout call.
    // The condition sits ~4865 chars into the function; use 5200 to be safe.
    $start = strpos($authSource, 'function domains_login_process(');
    expect($start)->not->toBeFalse();

    $body = substr($authSource, $start, 5200);
    expect($body)->toContain('$ldap_auth_response[\'error_num\'] == 1');
});

test('GHSA-2px8-gvmq-85f3: error_num == 1 appears adjacent to auth_process_lockout', function () use ($authSource) {
    $start = strpos($authSource, 'function domains_login_process(');
    expect($start)->not->toBeFalse();

    $body = substr($authSource, $start, 5200);

    $errorNumPos = strpos($body, "'error_num'] == 1");
    $lockoutPos  = strpos($body, 'auth_process_lockout(');

    expect($errorNumPos)->not->toBeFalse();
    expect($lockoutPos)->not->toBeFalse();
    // The lockout call must follow closely (within 150 chars) after the condition.
    expect($lockoutPos - $errorNumPos)->toBeLessThan(150);
});

test('GHSA-2px8-gvmq-85f3: error_text is not used in a numeric comparison inside domains_login_process', function () use ($authSource) {
    $start = strpos($authSource, 'function domains_login_process(');
    expect($start)->not->toBeFalse();

    $body = substr($authSource, $start, 5200);
    // The pre-fix bug was 'error_text' == 1; that pattern must not exist.
    expect($body)->not->toContain("'error_text'] == 1");
});

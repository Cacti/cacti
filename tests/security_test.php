<?php
/**
 * Cacti Security Fix Verification Tests
 *
 * Covers PRs: #6625, #6626, #6627, #6631, #6632, #6633, #6634, #6635, #6636
 *
 * Usage: php tests/security_test.php [--base-dir /path/to/cacti]
 * Exit:  0 = all passed, 1 = one or more failed
 *
 * No PHPUnit required. Source-analysis only — no Cacti runtime needed.
 */

declare(strict_types=1);

// ---------------------------------------------------------------------------
// Configuration
// ---------------------------------------------------------------------------

$BASE_DIR = __DIR__ . '/..';

// Allow overriding base dir from CLI: php security_test.php --base-dir /opt/cacti
for ($i = 1; $i < $argc; $i++) {
    if ($argv[$i] === '--base-dir' && isset($argv[$i + 1])) {
        $BASE_DIR = rtrim($argv[$i + 1], '/');
        break;
    }
}

$BASE_DIR = realpath($BASE_DIR);
if ($BASE_DIR === false) {
    fwrite(STDERR, "ERROR: base directory not found\n");
    exit(1);
}

// ---------------------------------------------------------------------------
// Test harness
// ---------------------------------------------------------------------------

$TESTS_RUN    = 0;
$TESTS_PASSED = 0;
$TESTS_FAILED = 0;
$FAILURES     = [];

function assert_true(bool $condition, string $message): void {
    global $TESTS_RUN, $TESTS_PASSED, $TESTS_FAILED, $FAILURES;
    $TESTS_RUN++;
    if ($condition) {
        $TESTS_PASSED++;
        echo "  PASS  $message\n";
    } else {
        $TESTS_FAILED++;
        $FAILURES[] = $message;
        echo "  FAIL  $message\n";
    }
}

function assert_false(bool $condition, string $message): void {
    assert_true(!$condition, $message);
}

function assert_match(string $pattern, string $subject, string $message): void {
    assert_true((bool) preg_match($pattern, $subject), $message);
}

function assert_no_match(string $pattern, string $subject, string $message): void {
    assert_false((bool) preg_match($pattern, $subject), $message);
}

/**
 * Read a file relative to $BASE_DIR. Returns the content or '' on failure.
 */
function read_file(string $rel_path): string {
    global $BASE_DIR;
    $full = $BASE_DIR . '/' . ltrim($rel_path, '/');
    if (!is_readable($full)) {
        echo "  WARN  Cannot read $full\n";
        return '';
    }
    return file_get_contents($full);
}

/**
 * Scan all .php files under $rel_dir for $pattern.
 * Returns list of matching lines as "file:line: content".
 */
function grep_dir(string $rel_dir, string $pattern): array {
    global $BASE_DIR;
    $dir  = $BASE_DIR . '/' . ltrim($rel_dir, '/');
    $hits = [];

    if (!is_dir($dir)) {
        return $hits;
    }

    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iter as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $path  = $file->getPathname();
        $lines = file($path, FILE_IGNORE_NEW_LINES);
        foreach ($lines as $n => $line) {
            if (preg_match($pattern, $line)) {
                $hits[] = sprintf('%s:%d: %s', $path, $n + 1, trim($line));
            }
        }
    }

    return $hits;
}

function run_test(string $name, callable $fn): void {
    echo "\n[$name]\n";
    $fn();
}

// ---------------------------------------------------------------------------
// PR #6625 — CSPRNG auth token (fix/auth-token-csprng)
// ---------------------------------------------------------------------------

run_test('PR #6625 — CSPRNG auth token', function () {
    $src = read_file('lib/functions.php');
    if ($src === '') {
        return;
    }

    // Extract the generate_hash function body
    if (preg_match('/function\s+generate_hash\s*\([^)]*\)\s*[^{]*\{([^}]+)\}/s', $src, $m)) {
        $body = $m[1];

        assert_no_match(
            '/\bmd5\s*\(\s*session_id\s*\(\s*\)/',
            $body,
            'generate_hash() must not use md5(session_id()...) pattern'
        );

        assert_no_match(
            '/\bmt_rand\s*\(/',
            $body,
            'generate_hash() must not use mt_rand()'
        );

        assert_match(
            '/\brandom_bytes\s*\(/',
            $body,
            'generate_hash() must use random_bytes()'
        );

        assert_match(
            '/\bbin2hex\s*\(/',
            $body,
            'generate_hash() must use bin2hex()'
        );
    } else {
        // Function may have been renamed or refactored; check for any token generation
        assert_match(
            '/\brandom_bytes\s*\(/',
            $src,
            'lib/functions.php must contain random_bytes() for token generation'
        );
        assert_no_match(
            '/function\s+generate_hash[^}]+md5\s*\(\s*session_id/s',
            $src,
            'generate_hash() must not combine md5 with session_id'
        );
    }

    // Functional: verify random_bytes/bin2hex produces valid hex tokens
    $token1 = bin2hex(random_bytes(32));
    $token2 = bin2hex(random_bytes(32));

    assert_true(
        strlen($token1) === 64,
        'random_bytes(32)+bin2hex produces 64-char hex string'
    );

    assert_match(
        '/^[0-9a-f]+$/',
        $token1,
        'Token is lowercase hex only'
    );

    assert_true(
        $token1 !== $token2,
        'Consecutive tokens are unique (random_bytes entropy)'
    );
});

// ---------------------------------------------------------------------------
// PR #6626 — Strict password comparison (fix/password-verify-strict)
// ---------------------------------------------------------------------------

run_test('PR #6626 — Strict password comparison', function () {
    $src = read_file('lib/auth.php');
    if ($src === '') {
        return;
    }

    // The compat_password_verify wrapper must use === not == when calling password_verify
    if (preg_match('/function\s+compat_password_verify[^}]+\}/s', $src, $m)) {
        $body = $m[0];

        assert_no_match(
            '/password_verify\s*\([^)]+\)\s*==\s*(?!==)/',
            $body,
            'compat_password_verify() must not use loose == with password_verify()'
        );

        // Correct patterns: bare if(password_verify(...)) or strict === true
        $uses_strict_or_bare = (bool) preg_match(
            '/if\s*\(\s*password_verify\s*\(/',
            $body
        ) || (bool) preg_match(
            '/password_verify\s*\([^)]+\)\s*===/',
            $body
        );

        assert_true(
            $uses_strict_or_bare,
            'compat_password_verify() uses if(password_verify(...)) or strict === comparison'
        );
    }

    // Scan entire file: no loose == comparison directly after password_verify()
    assert_no_match(
        '/password_verify\s*\([^)]+\)\s*==\s*true/',
        $src,
        'No loose password_verify(...) == true in lib/auth.php'
    );

    // Functional check: PHP itself always returns bool from password_verify
    $hash = password_hash('secret', PASSWORD_DEFAULT);
    assert_true(
        password_verify('secret', $hash) === true,
        'password_verify() returns strict bool true (PHP sanity check)'
    );
    assert_true(
        password_verify('wrong', $hash) === false,
        'password_verify() returns strict bool false (PHP sanity check)'
    );
});

// ---------------------------------------------------------------------------
// PR #6627 — PDO error handling (fix/pdo-error-handling)
// ---------------------------------------------------------------------------

run_test('PR #6627 — PDO error handling', function () {
    $src = read_file('lib/database.php');
    if ($src === '') {
        return;
    }

    // Must have ERRMODE_EXCEPTION set (preferably in $flags array, not setAttribute)
    assert_match(
        '/ERRMODE_EXCEPTION/',
        $src,
        'lib/database.php sets PDO::ERRMODE_EXCEPTION'
    );

    // Count setAttribute(ATTR_ERRMODE ...) calls — there must be at most one
    preg_match_all(
        '/setAttribute\s*\(\s*PDO::ATTR_ERRMODE/',
        $src,
        $setattr_matches
    );
    $setattr_count = count($setattr_matches[0]);

    assert_true(
        $setattr_count <= 1,
        "At most one setAttribute(PDO::ATTR_ERRMODE) call (found $setattr_count)"
    );

    // After the fix there must be no setAttribute that overrides to ERRMODE_SILENT
    assert_no_match(
        '/setAttribute\s*\(\s*PDO::ATTR_ERRMODE\s*,\s*PDO::ERRMODE_SILENT\s*\)/',
        $src,
        'No setAttribute overriding error mode to ERRMODE_SILENT after connection'
    );
});

// ---------------------------------------------------------------------------
// PR #6631 — Float cast (fix/double-to-float-cast)
// ---------------------------------------------------------------------------

run_test('PR #6631 — Float cast: (double) replaced with (float)', function () {
    // Scan entire codebase for (double) casts
    $double_hits = grep_dir('.', '/\(double\)/');

    // Filter out this test file itself and any non-Cacti files
    $double_hits = array_filter($double_hits, function (string $line): bool {
        return strpos($line, 'security_test.php') === false
            && strpos($line, 'vendor/') === false
            && strpos($line, '.omc/') === false;
    });
    $double_hits = array_values($double_hits);

    assert_true(
        count($double_hits) === 0,
        sprintf(
            'Zero (double) casts in PHP sources (found %d: %s)',
            count($double_hits),
            implode(', ', array_slice($double_hits, 0, 3))
        )
    );

    // Confirm (float) casts exist to verify replacement happened
    $float_hits = grep_dir('.', '/\(float\)/');
    $float_hits = array_filter($float_hits, function (string $line): bool {
        return strpos($line, 'security_test.php') === false
            && strpos($line, 'vendor/') === false;
    });

    assert_true(
        count($float_hits) > 0,
        '(float) casts exist in PHP sources (replacements present)'
    );
});

// ---------------------------------------------------------------------------
// PR #6632 — Unsafe deserialization (fix/unsafe-deserialization)
// ---------------------------------------------------------------------------

run_test('PR #6632 — Unsafe deserialization', function () {
    $src = read_file('lib/functions.php');
    if ($src === '') {
        return;
    }

    // Every unserialize() call must include allowed_classes
    preg_match_all('/unserialize\s*\(([^;]+);/', $src, $calls);

    $unsafe = [];
    foreach ($calls[0] as $call) {
        // Skip the cacti_unserialize wrapper definition line itself —
        // it is expected to be the canonical safe wrapper.
        if (preg_match('/allowed_classes/', $call)) {
            continue;
        }
        // Calls without allowed_classes that lack a preceding regex guard
        // (sanitize_unserialize_selected_* do their own structural validation
        // before calling unserialize, which is an acceptable pattern, but they
        // should still pass allowed_classes).
        $unsafe[] = trim($call);
    }

    assert_true(
        count($unsafe) === 0,
        sprintf(
            'All unserialize() calls in lib/functions.php include allowed_classes (%d unsafe found)',
            count($unsafe)
        )
    );

    // Functional: allowed_classes => false must reject object injection
    $payload = 'O:8:"stdClass":1:{s:3:"pwn";s:3:"yes";}';
    $result  = unserialize($payload, ['allowed_classes' => false]);

    assert_true(
        $result === false || $result instanceof __PHP_Incomplete_Class,
        'unserialize with allowed_classes=false rejects object payload'
    );

    // Safe scalar array must still deserialize fine
    $safe   = serialize([1, 2, 3]);
    $result = unserialize($safe, ['allowed_classes' => false]);
    assert_true(
        $result === [1, 2, 3],
        'unserialize with allowed_classes=false accepts safe scalar array'
    );
});

// ---------------------------------------------------------------------------
// PR #6633 — Proxy header default (fix/proxy-header-default)
// ---------------------------------------------------------------------------

run_test('PR #6633 — get_client_addr() proxy_headers default', function () {
    $src = read_file('lib/functions.php');
    if ($src === '') {
        return;
    }

    // The function must no longer unconditionally default to true.
    // The fix changes the fallback so that proxy_headers === null resolves
    // to false (or requires explicit opt-in) rather than defaulting to true.
    if (preg_match('/function\s+get_client_addr\s*\([^)]*\)[^{]*\{(.+?)(?=\nfunction\s)/s', $src, $m)) {
        $body = $m[1];

        // Old bad pattern: $proxy_headers = true; unconditionally as the fallback
        assert_no_match(
            '/proxy_headers\s+===\s+null[^}]+\$proxy_headers\s*=\s*true\s*;/s',
            $body,
            'get_client_addr() must not fall back to $proxy_headers = true when config is null'
        );
    }

    // Regardless of refactor shape: the string literal "proxy_headers" must not
    // appear in a default-parameter context set to true.
    assert_no_match(
        '/function\s+get_client_addr\s*\(\s*[^)]*=\s*true\s*\)/',
        $src,
        'get_client_addr() must not have a default parameter value of true'
    );

    // The log line acknowledges that proxy headers are disabled by default
    assert_match(
        '/proxy.headers.*disabled.*default|default.*disabled.*proxy.headers/i',
        $src,
        'Log message states proxy headers are disabled by default'
    );
});

// ---------------------------------------------------------------------------
// PR #6634 — SQL injection in automation (fix/automation-sqli)
// ---------------------------------------------------------------------------

run_test('PR #6634 — automation SQL injection: prepared statement for $pattern', function () {
    $src = read_file('poller_automation.php');
    if ($src === '') {
        return;
    }

    // Old vulnerable pattern: db_fetch_cell("SELECT '$pattern'") — string interpolation
    assert_no_match(
        '/db_fetch_cell\s*\(\s*["\']SELECT\s+[\'"][^,)]*\$pattern/',
        $src,
        'poller_automation.php must not pass $pattern via string interpolation to db_fetch_cell()'
    );

    // The specific known-bad line: db_fetch_cell("SELECT '$pattern'")
    assert_no_match(
        '/db_fetch_cell\s*\(\s*"SELECT\s+\'\$pattern\'"/',
        $src,
        'Exact vulnerable db_fetch_cell("SELECT \'$pattern\'") must not exist'
    );

    // Must use a prepared variant or sanitize $pattern before the query
    $has_prepared = (bool) preg_match('/db_fetch_cell_prepared/', $src)
        || (bool) preg_match('/db_execute_prepared/', $src);

    assert_true(
        $has_prepared,
        'poller_automation.php uses prepared statement functions'
    );
});

// ---------------------------------------------------------------------------
// PR #6635 — XSS in auth pages (fix/reflected-xss)
// ---------------------------------------------------------------------------

run_test('PR #6635 — XSS escaping in auth_resetpassword.php and auth_profile.php', function () {
    // auth_resetpassword.php: hash from query string must be escaped before output
    $reset_src = read_file('auth_resetpassword.php');
    if ($reset_src !== '') {
        // Check that hash input goes through a validation/filter function.
        // gfrv() with FILTER_VALIDATE_REGEXP is the correct sanitisation path.
        $uses_gfrv_hash = (bool) preg_match(
            '/gfrv\s*\(\s*[\'"]hash[\'"]\s*,\s*FILTER_VALIDATE_REGEXP/',
            $reset_src
        );

        $uses_html_escape_hash = (bool) preg_match(
            '/html_escape\s*\(\s*gnrv\s*\(\s*[\'"]hash[\'"]/',
            $reset_src
        );

        assert_true(
            $uses_gfrv_hash || $uses_html_escape_hash,
            'auth_resetpassword.php validates or escapes user-supplied hash before use'
        );

        // Must not echo/print the raw $_GET['hash'] or $_REQUEST['hash']
        assert_no_match(
            '/(?:echo|print)\s+[^;]*\$_(?:GET|REQUEST)\s*\[\s*[\'"]hash[\'"]\s*\]/',
            $reset_src,
            'auth_resetpassword.php must not echo raw $_GET["hash"]'
        );
    }

    // auth_profile.php: tab parameter must be escaped when reflected into output
    $profile_src = read_file('auth_profile.php');
    if ($profile_src !== '') {
        // Either json_encode (safe for JS context) or html_escape for HTML context
        $tab_output_safe = (bool) preg_match(
            '/json_encode\s*\(\s*(?:gnrv|gfrv)\s*\(\s*[\'"]tab[\'"]/',
            $profile_src
        ) || (bool) preg_match(
            '/html_escape\s*\(\s*(?:gnrv|gfrv)\s*\(\s*[\'"]tab[\'"]/',
            $profile_src
        );

        // Also accept gfrv with FILTER_VALIDATE_REGEXP as sanitisation
        $tab_validated = (bool) preg_match(
            '/gfrv\s*\(\s*[\'"]tab[\'"]\s*,\s*FILTER_VALIDATE_REGEXP/',
            $profile_src
        );

        assert_true(
            $tab_output_safe || $tab_validated,
            'auth_profile.php escapes or validates tab parameter before output'
        );

        // Must not reflect tab directly into inline HTML/JS without encoding
        assert_no_match(
            '/(?:echo|print)\s+[^;]*\$_(?:GET|REQUEST)\s*\[\s*[\'"]tab[\'"]\s*\]/',
            $profile_src,
            'auth_profile.php must not echo raw $_GET["tab"]'
        );
    }
});

// ---------------------------------------------------------------------------
// PR #6636 — XSS in oauth2 (fix/semgrep-xss)
// ---------------------------------------------------------------------------

run_test('PR #6636 — XSS in oauth2.php: token output escaped', function () {
    $src = read_file('oauth2.php');
    if ($src === '') {
        return;
    }

    // getRefreshToken() result must be wrapped in htmlspecialchars / html_escape / htmle
    // Check that any line with getRefreshToken also has an escape function
    $lines = explode("\n", $src);
    $unescaped = false;
    foreach ($lines as $line) {
        if (preg_match('/getRefreshToken\s*\(/', $line)
            && preg_match('/(?:print|echo)/', $line)
            && !preg_match('/htmle|html_escape|htmlspecialchars/', $line)) {
            $unescaped = true;
            break;
        }
    }
    assert_false(
        $unescaped,
        'oauth2.php must not print getRefreshToken() without HTML escaping'
    );

    // Positive: one of the escape functions must appear near getRefreshToken
    if (preg_match('/getRefreshToken/', $src)) {
        $context_start = strpos($src, 'getRefreshToken');
        // Grab 200 chars around the call to check for escaping
        $context = substr($src, max(0, $context_start - 100), 300);

        $is_escaped = (bool) preg_match(
            '/html_escape|htmlspecialchars|htmle\s*\(/',
            $context
        );

        assert_true(
            $is_escaped,
            'getRefreshToken() output wrapped with html_escape/htmlspecialchars/htmle'
        );
    }

    // Must not have a bare print/echo of the refresh token
    assert_no_match(
        '/(?:print|echo)\s+[^;]*\.\s*\$token->getRefreshToken\s*\(\s*\)\s*;/',
        $src,
        'oauth2.php must not concatenate bare getRefreshToken() into print/echo'
    );
});

// ---------------------------------------------------------------------------
// Summary
// ---------------------------------------------------------------------------

echo "\n";
echo str_repeat('-', 60) . "\n";
echo sprintf("Results: %d/%d passed", $TESTS_PASSED, $TESTS_RUN) . "\n";

if ($TESTS_FAILED > 0) {
    echo sprintf("FAILED  (%d):\n", $TESTS_FAILED);
    foreach ($FAILURES as $f) {
        echo "  - $f\n";
    }
    echo str_repeat('-', 60) . "\n";
    exit(1);
}

echo "All tests passed.\n";
echo str_repeat('-', 60) . "\n";
exit(0);

<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
namespace Cacti\Test\SpikeRequestGate;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}
require_once dirname(__DIR__, 2) . '/Helpers/FixtureCoverage.php';
require_once dirname(__DIR__, 2) . '/Helpers/SourceSlice.php';
$root = dirname(__DIR__, 3);
require_once $root . '/lib/html_utility.php';
// CLI has no HTTP input stream; adapt only that PHP transport primitive.
// Token issuance, token validation and the request guard remain production code.
function filter_input(int $type, string $name, int $filter): mixed {
	return $_POST[$name] ?? null;
}
require_once $root . '/include/vendor/autoload.php';
define('CACTI_WEB', true);
define('CACTI_PATH_LIBRARY', $root . '/lib');
define('CACTI_PATH_INCLUDE', $root . '/include');
define('CACTI_PATH_URL', '/');
require_once $root . '/include/csrf.php';
class_alias(\CactiCsrfGuard::class, __NAMESPACE__ . '\\CactiCsrfGuard');
\cacti_fixture_source_function($root . '/include/csrf.php', 'csrf_check', __NAMESPACE__);
\cacti_fixture_source_function($root . '/include/csrf.php', 'csrf_require_post', __NAMESPACE__);
$tokenMode = $argv[4] ?? 'valid';
$_POST     = $tokenMode === 'missing' ? [] : ['__csrf_magic' => $tokenMode === 'invalid' ? 'invalid-token' : \csrf_guard()->token()];

$_SERVER['REQUEST_METHOD'] = $argv[1] ?? 'GET';
$_REQUEST                  = ($argv[2] ?? '') === 'dryrun' ? ['dryrun' => '1'] : [];
$route                     = $argv[3] ?? 'mutation';
$file                      = $route === 'settings' ? '/lib/html.php' : '/spikekill.php';
$source                    = file_get_contents($root . $file);
$pattern                   = $route === 'settings'
	? "/case 'spikesave':\s*(csrf_require_post\(\);)/"
	: "/(if \(!(?:isset_request_var|isrv)\('dryrun'\)\) \{\s*csrf_require_post\(\);\s*\})/";

if (!preg_match($pattern, $source, $match, PREG_OFFSET_CAPTURE)) {
	throw new \RuntimeException('SpikeKill request guard is missing');
}
$line      = substr_count(substr($source, 0, $match[1][1]), "\n") + 1;
$temporary = tempnam(sys_get_temp_dir(), 'cacti-spike-request-');
file_put_contents($temporary, '<?php namespace ' . __NAMESPACE__ . ';' . str_repeat("\n", $line - 1) . $match[1][0]);
$GLOBALS['cacti_fixture_source_map'][$temporary] = realpath($root . $file);
$continued                                       = false;
http_response_code(200);
register_shutdown_function(static function () use ($temporary, &$continued): void {
	unlink($temporary);
	print json_encode(['status' => http_response_code(), 'continued' => $continued], JSON_THROW_ON_ERROR) . "\n";
});
require $temporary;
$continued = true;

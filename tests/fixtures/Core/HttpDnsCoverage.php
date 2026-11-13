<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
namespace CactiHttpDnsFixture;

use React\Dns\RecordNotFoundException;
use React\Promise\Deferred;
use RuntimeException;
use stdClass;

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

$root = dirname(__DIR__, 3);
require $root . '/tests/Helpers/FixtureCoverage.php';
require $root . '/tests/Helpers/SourceSlice.php';
require $root . '/include/vendor/autoload.php';
require $root . '/lib/functions.php';
require $root . '/tests/Helpers/HttpTestServer.php';

// Only resolver I/O and a curl failure are controlled. Production promises,
// timers, address validation and HTTP connections execute unchanged.
function cacti_http_dns_resolver($loop) {
	return new class($loop) implements \React\Dns\Resolver\ResolverInterface {
		public function __construct(private $loop) {
		}
		public function resolve($domain) {
			return $this->resolveAll($domain, 1);
		}
		public function resolveAll($domain, $type) {
			if ($domain === 'throw.test' && $type === 28) {
				throw new RuntimeException('resolver unavailable');
			}
			$timer    = null;
			$deferred = new Deferred(function () use (&$timer) {
				$GLOBALS['dns_canceled']++;

				if ($timer !== null) {
					$this->loop->cancelTimer($timer);
				}
			});

			if (in_array($domain, ['slow.test', 'throw.test'], true)) {
				return $deferred->promise();
			}
			$timer = $this->loop->addTimer(0.02, static function () use ($deferred, $domain, $type) {
				if ($domain === 'fallback.hospital.test') {
					$deferred->reject(new RecordNotFoundException('no record'));
				} elseif ($domain === 'error.test') {
					$deferred->reject(new RuntimeException('DNS server failure'));
				} else {
					$deferred->resolve($domain === 'mixed.test' && $type === 28 ? ['::ffff:169.254.169.254'] : ($type === 1 ? ['127.0.0.1'] : []));
				}
			});

			return $deferred->promise();
		}
	};
}

function cacti_http_dns_search_config() {
	return ['domains' => ['hospital.test'], 'ndots' => 1, 'hosts' => null];
}

function curl_multi_exec($multi, &$running) {
	if ($GLOBALS['dns_throw_curl'] ?? false) {
		throw new RuntimeException('curl failure');
	}

	return \curl_multi_exec($multi, $running);
}

function check($condition, $message) {
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

class_alias(stdClass::class, __NAMESPACE__ . '\\stdClass');
\cacti_fixture_source_function($root . '/lib/functions.php', 'cacti_http_multi', __NAMESPACE__);
$base                    = \cacti_http_test_server()['url'];
$port                    = parse_url($base, PHP_URL_PORT);
$opts                    = ['blocked_subnets' => ['169.254.0.0/16', '::ffff:169.254.0.0/112'], 'timeout' => 0.2];
$GLOBALS['dns_canceled'] = 0;
$start                   = microtime(true);
$results                 = cacti_http_multi([
	'slow' => ['method' => 'GET', 'url' => 'http://slow.test/', 'options' => $opts],
	'fast' => ['method' => 'GET', 'url' => "$base/stats", 'options' => $opts],
], 2);
check($results['slow']['error'] === 'Timed out' && $results['fast']['success'], 'DNS blocked another transfer');
check(microtime(true) - $start < 0.6 && $GLOBALS['dns_canceled'] === 2, 'DNS deadline or cancellation failed');

$results = cacti_http_multi([
	'normal'   => ['method' => 'GET', 'url' => "http://switch1:$port/stats", 'options' => $opts],
	'fallback' => ['method' => 'GET', 'url' => "http://fallback:$port/stats", 'options' => $opts],
	'mixed'    => ['method' => 'GET', 'url' => "http://mixed.test:$port/stats", 'options' => $opts],
	'error'    => ['method' => 'GET', 'url' => "http://error.test:$port/stats", 'options' => $opts],
	'throw'    => ['method' => 'GET', 'url' => "http://throw.test:$port/stats", 'options' => $opts],
	'redirect' => ['method' => 'GET', 'url' => "$base/redirect?to=other-headers", 'options' => $opts + ['max_redirects' => 1]],
], 3);
check($results['normal']['success'] && $results['fallback']['success'] && $results['redirect']['success'], 'name, search fallback or redirected DNS failed');

foreach (['mixed', 'error', 'throw'] as $key) {
	check($results[$key]['error'] === 'Target host resolves to a disallowed address', 'failed DNS or mixed address families accepted');
}
check($GLOBALS['dns_canceled'] === 3, 'partially started DNS query not canceled');

$GLOBALS['dns_throw_curl'] = true;

try {
	cacti_http_multi([
		['method' => 'GET', 'url' => 'http://slow.test/', 'options' => $opts],
		['method' => 'GET', 'url' => "$base/stats", 'options' => $opts],
	], 2);

	throw new RuntimeException('curl failure swallowed');
} catch (RuntimeException $error) {
	check($error->getMessage() === 'curl failure' && $GLOBALS['dns_canceled'] === 5, 'pending DNS not canceled on exception');
}

// Hosts-file aliases must work even when no DNS server is configured.
$search   = ['domains' => ['hospital.test'], 'ndots' => 1, 'hosts' => new \React\Dns\Config\HostsFile("127.0.0.1 switch1\n")];
$transfer = (object) ['dnsCandidates' => ['switch1'], 'dnsCandidate' => 0, 'dns' => (object) ['search' => $search, 'resolver' => null]];
\cacti_http_resolve_async($transfer);
check($transfer->dnsIps === ['127.0.0.1'], 'hosts alias required a nameserver');
$transfer->dnsCandidates = ['unlisted'];
\cacti_http_resolve_async($transfer);
check($transfer->dnsIps === [], 'missing resolver did not fail closed');
check(\cacti_http_dns_candidates('switch1', $search) === ['switch1'], 'hosts alias searched DNS');
check(\cacti_http_dns_candidates('switch2.', $search) === ['switch2.'], 'absolute name searched suffixes');
check(\cacti_http_dns_candidates('switch2.lab', $search) === ['switch2.lab', 'switch2.lab.hospital.test'], 'ndots precedence changed');

namespace CactiHttpDnsFixture\Config;

// Supply resolver-file contents at the filesystem boundary, without changing
// the machine's DNS configuration or the production parser.
function file_get_contents($path) {
	return "domain old.test\nsearch hospital.test lab.test # comment\noptions ndots:3\n";
}

class_alias(\RuntimeException::class, __NAMESPACE__ . '\\RuntimeException');
\cacti_fixture_source_function($root . '/lib/functions.php', 'cacti_http_dns_search_config', __NAMESPACE__);
$search = cacti_http_dns_search_config();
\CactiHttpDnsFixture\check($search['domains'] === ['hospital.test', 'lab.test'] && $search['ndots'] === 3, 'resolver search configuration not parsed');

// Restrict this disposable process to its fixture and output paths. This
// exercises real missing-config behavior without touching /etc or global PHP
// settings. The restrictions end when this process exits.
$loop  = new \React\EventLoop\StreamSelectLoop();
$paths = [$root, dirname(realpath($root . '/include/vendor/autoload.php')), sys_get_temp_dir()];

foreach (['CACTI_COVERAGE_FILE', 'CACTI_PR_RAW_COVERAGE'] as $variable) {
	$value = getenv($variable);

	if ($value !== false && $value !== '') {
		$paths[] = $variable === 'CACTI_COVERAGE_FILE' ? dirname($value) : $value;
	}
}
ini_set('open_basedir', implode(PATH_SEPARATOR, $paths));
\CactiHttpDnsFixture\check(\cacti_http_dns_resolver($loop) === null, 'missing resolver configuration selected an external server');
$search = \cacti_http_dns_search_config();
\CactiHttpDnsFixture\check($search === ['domains' => [], 'ndots' => 1, 'hosts' => null], 'missing hosts or resolver file did not preserve defaults');
print json_encode(['http_dns_scenarios' => 13], JSON_THROW_ON_ERROR) . "\n";

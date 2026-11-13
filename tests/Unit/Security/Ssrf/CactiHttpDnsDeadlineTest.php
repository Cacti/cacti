<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

namespace CactiHttpDnsDeadlineTest;

require_once dirname(__DIR__, 3) . '/Helpers/SourceSlice.php';
require_once dirname(__DIR__, 3) . '/Helpers/HttpTestServer.php';

foreach (['stdClass', 'CurlMultiHandle', 'Throwable'] as $type) {
	class_alias($type, __NAMESPACE__ . '\\' . $type);
}

foreach (['cacti_http_multi', 'cacti_http_start', 'cacti_http_finish', 'cacti_http_resolve_async'] as $function) {
	\cacti_fixture_source_function(dirname(__DIR__, 4) . '/lib/functions.php', $function, __NAMESPACE__);
}

function cacti_http_dns_search_config() {
	return ['domains' => ['hospital.test'], 'ndots' => 1, 'hosts' => null];
}

// Only the DNS transport is replaced; real promises, timers, HTTP and address policy run.
function cacti_http_dns_resolver(\React\EventLoop\LoopInterface $loop) {
	return new class($loop) implements \React\Dns\Resolver\ResolverInterface {
		public function __construct(private \React\EventLoop\LoopInterface $loop) {
		}
		public function resolve($domain) {
			return $this->resolveAll($domain, 1);
		}
		public function resolveAll($domain, $type) {
			$GLOBALS['dns_test_queries'][] = $domain;

			if ($domain === 'fallback.hospital.test') {
				return \React\Promise\resolve([]);
			}

			if ($domain === 'slow.test') {
				$deferred = new \React\Promise\Deferred(function () {
					$GLOBALS['dns_test_canceled']++;
				});

				return $deferred->promise();
			}

			if ($domain === 'mixed.test' && $type === 28) {
				return \React\Promise\resolve(['::ffff:169.254.169.254']);
			}

			return \React\Promise\resolve($type === 1 ? ['127.0.0.1'] : []);
		}
	};
}

test('stalled DNS expires without blocking another transfer and cancels both queries', function () {
	$base                         = \cacti_http_test_server()['url'];
	$GLOBALS['dns_test_canceled'] = 0;
	$start                        = microtime(true);
	$result                       = cacti_http_multi([
		'slow' => ['method' => 'GET', 'url' => 'http://slow.test/', 'options' => ['timeout' => 0.15]],
		'fast' => ['method' => 'GET', 'url' => "$base/stats", 'options' => ['timeout' => 0.08, 'blocked_subnets' => []]],
	], 2);
	expect($result['slow']['error'])->toBe('Timed out')
		->and($result['fast']['success'])->toBeTrue()
		->and(microtime(true) - $start)->toBeLessThan(0.4)
		->and($GLOBALS['dns_test_canceled'])->toBe(2);
});

test('resolved names are pinned and every address family is checked', function () {
	$port   = parse_url(\cacti_http_test_server()['url'], PHP_URL_PORT);
	$result = cacti_http_multi([
		['method' => 'GET', 'url' => "http://fast.test:$port/stats", 'options' => ['blocked_subnets' => ['169.254.0.0/16', '::ffff:169.254.0.0/112']]],
		['method' => 'GET', 'url' => "http://mixed.test:$port/stats", 'options' => ['blocked_subnets' => ['169.254.0.0/16', '::ffff:169.254.0.0/112']]],
	], 2);
	expect($result[0]['success'])->toBeTrue()
		->and($result[1]['error'])->toBe('Target host resolves to a disallowed address');
});

test('short names use search domains while absolute and hosts-file names stay exact', function () {
	$port                        = parse_url(\cacti_http_test_server()['url'], PHP_URL_PORT);
	$GLOBALS['dns_test_queries'] = [];
	$result                      = cacti_http_multi([
		['method' => 'GET', 'url' => "http://switch1:$port/stats", 'options' => ['blocked_subnets' => []]],
		['method' => 'GET', 'url' => "http://fallback:$port/stats", 'options' => ['blocked_subnets' => []]],
	], 2);
	expect($result[0]['success'])->toBeTrue()
		->and($result[1]['success'])->toBeTrue()
		->and($GLOBALS['dns_test_queries'])->toContain('switch1.hospital.test', 'fallback.hospital.test', 'fallback');
	$config = ['domains' => ['hospital.test'], 'ndots' => 1, 'hosts' => new \React\Dns\Config\HostsFile("127.0.0.1 switch1\n")];
	expect(\cacti_http_dns_candidates('switch1', $config))->toBe(['switch1'])
		->and(\cacti_http_dns_candidates('switch2.', $config))->toBe(['switch2.'])
		->and(\cacti_http_dns_candidates('switch2.lab', $config))->toBe(['switch2.lab', 'switch2.lab.hospital.test']);
});

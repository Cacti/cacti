<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 */

require_once __DIR__ . '/CactiHttpPollItem.php';

/**
 * Fetches the JSON documents behind a set of HTTP poller items.
 *
 * Items are grouped by device and endpoint so one request serves every data
 * source that reads from the same document, the same way Spine packs the
 * OIDs of one device into a single SNMP get.  At most $concurrency requests
 * are in flight to any one device, and MAX_CONCURRENCY across all of them,
 * so one slow device does not hold up the rest.  Transport, redirect and
 * destination checks are cacti_http_multi()'s, so lib/functions.php must be
 * loaded.
 */
final class CactiHttpPoller {
	public const int DEFAULT_CONCURRENCY = 8;
	public const int MAX_CONCURRENCY     = 64;
	public const int MAX_RESPONSE_BYTES  = 4194304;
	public const int MAX_REDIRECTS       = 3;

	/**
	 * Destinations no item may reach, on the first request or after a redirect.
	 *
	 * Monitored devices live on private and loopback networks, so those stay
	 * reachable.  That includes services on the Cacti host itself: anyone who
	 * may edit a Device hostname or an HTTP Data Source can point the poller,
	 * and the Data Source test in the web server, at them and read back any
	 * numeric field they return.  Grant those permissions with that in mind.
	 * The cloud instance metadata services hand out credentials
	 * and no device is managed at their addresses.  blockedSubnets() adds the
	 * IPv4-mapped and NAT64 spelling of every IPv4 entry.
	 */
	public const array BLOCKED_SUBNETS = [
		'169.254.0.0/16',
		'100.100.100.200/32',
		'192.0.0.192/32',
		'fe80::/10',
		'fd00:ec2::254/128',
		'fd20:ce::254/128',
	];

	/**
	 * @param int $concurrency upper bound on requests in flight to one device
	 * @param int $maxBytes    responses larger than this are abandoned
	 */
	public function __construct(
		private readonly int $concurrency = self::DEFAULT_CONCURRENCY,
		private readonly int $maxBytes = self::MAX_RESPONSE_BYTES,
	) {
		if ($concurrency < 1 || $concurrency > self::MAX_CONCURRENCY) {
			throw new InvalidArgumentException(sprintf('HTTP concurrency must be between 1 and %d', self::MAX_CONCURRENCY));
		}
	}

	/**
	 * Build a poller from the configured concurrency.
	 *
	 * A configured value of 0 or less means the default.  Values above the
	 * maximum are clamped rather than rejected so a typo in Settings cannot
	 * stop collection.
	 */
	public static function create(int $concurrency) : self {
		return new self($concurrency < 1 ? self::DEFAULT_CONCURRENCY : min($concurrency, self::MAX_CONCURRENCY));
	}

	/**
	 * BLOCKED_SUBNETS with each IPv4 range also written as an IPv4-mapped, a
	 * well-known-prefix NAT64 and a local-use NAT64 (RFC 8215) range, which
	 * reach the same host over IPv6.
	 *
	 * @return list<string>
	 */
	public static function blockedSubnets() : array {
		$subnets = [];

		foreach (self::BLOCKED_SUBNETS as $subnet) {
			$subnets[] = $subnet;

			[$address, $bits] = explode('/', $subnet);

			if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
				$subnets[] = '::ffff:' . $address . '/' . (96 + (int) $bits);
				$subnets[] = '64:ff9b::' . $address . '/' . (96 + (int) $bits);
				$subnets[] = '64:ff9b:1::' . $address . '/' . (96 + (int) $bits);
			}
		}

		return $subnets;
	}

	/**
	 * Fetch and decode the document for every distinct endpoint in $items.
	 *
	 * When several items share an endpoint the request uses the longest of
	 * their timeouts, so no item is cut off before its own limit.
	 *
	 * @param list<CactiHttpPollItem> $items
	 * @param array<string, string>   $errors receives a reason per failed endpoint key
	 *
	 * @param-out array<string, string> $errors
	 *
	 * @return array<string, mixed> decoded documents keyed by CactiHttpPollItem::$endpointKey
	 */
	public function fetch(array $items, array &$errors = []) : array {
		$requests = [];
		$options  = [
			'max_redirects'   => self::MAX_REDIRECTS,
			'max_bytes'       => $this->maxBytes,
			'blocked_subnets' => self::blockedSubnets(),
			'headers'         => [
				'Accept: application/json',
				'User-Agent: Cacti/' . (defined('CACTI_VERSION') ? CACTI_VERSION : 'unknown'),
			],
		];

		foreach ($items as $item) {
			$key = $item->endpointKey;

			if (!isset($requests[$key]) || $requests[$key]['options']['timeout'] < $item->timeout) {
				$requests[$key] = ['method' => 'GET', 'url' => $item->url, 'group' => $item->hostId, 'options' => ['timeout' => $item->timeout] + $options];
			}
		}

		$documents = [];

		foreach (cacti_http_multi($requests, self::MAX_CONCURRENCY, $this->concurrency) as $key => $result) {
			$key = (string) $key;

			if (!$result['success']) {
				$errors[$key] = self::scrub($result['error'] !== '' ? $result['error'] : 'Request failed', $requests[$key]['url']);

				continue;
			}

			try {
				$documents[$key] = json_decode($result['body'], true, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
			} catch (JsonException $e) {
				$errors[$key] = 'Invalid JSON: ' . $e->getMessage();
			}
		}

		return $documents;
	}

	/**
	 * Poll a set of poller_item rows and return one output per row.
	 *
	 * Rows that hold a broken spec, whose endpoint failed, or whose pointer
	 * does not select a number come back as 'U' with the reason in $reasons.
	 *
	 * @param array<array<string, mixed>> $rows    HTTP poller_item rows
	 * @param array<string, string>       $reasons receives a reason per failed row
	 *
	 * @param-out array<string, string> $reasons
	 *
	 * @return array<string, string> outputs keyed by self::rowKey()
	 */
	public function collect(array $rows, array &$reasons = []) : array {
		$outputs = [];
		$specs   = [];

		foreach ($rows as $row) {
			$key = self::rowKey($row);

			try {
				$specs[$key] = CactiHttpPollItem::fromPollerItem($row);
			} catch (InvalidArgumentException $e) {
				$outputs[$key] = 'U';
				$reasons[$key] = $e->getMessage();
			}
		}

		$errors    = [];
		$documents = $this->fetch(array_values($specs), $errors);

		foreach ($specs as $key => $spec) {
			if (!array_key_exists($spec->endpointKey, $documents)) {
				$outputs[$key] = 'U';
				$reasons[$key] = $errors[$spec->endpointKey] ?? 'No response';
			} else {
				$outputs[$key] = $spec->resolve($documents[$spec->endpointKey]) ?? 'U';

				if ($outputs[$key] === 'U') {
					$reasons[$key] = 'JSON Pointer did not select a number';
				}
			}
		}

		return $outputs;
	}

	/**
	 * The key collect() files a poller_item row under.
	 *
	 * @param array<string, mixed> $row
	 */
	public static function rowKey(array $row) : string {
		return $row['local_data_id'] . ':' . $row['rrd_name'];
	}

	/**
	 * Redact every URL quoted in a transport error.
	 *
	 * Messages quote the URL that failed, which after a redirect is not the
	 * configured one, and either may carry an API key in its query string.
	 * The configured URL is replaced verbatim first because credentials that
	 * hold a quote or a space would end the pattern match early.
	 */
	private static function scrub(string $message, string $url) : string {
		$message = str_replace($url, CactiHttpPollItem::redact($url), $message);

		return preg_replace_callback('~https?://[^\s"\'<>]+~i', static fn (array $match) : string => CactiHttpPollItem::redact($match[0]), $message) ?? 'Request failed';
	}
}

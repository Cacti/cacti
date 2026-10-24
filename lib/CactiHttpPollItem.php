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

/**
 * One HTTP poller item as stored in poller_item.
 *
 * arg1 holds the endpoint URL, arg2 an RFC 6901 JSON Pointer into the
 * response body, and arg3 a JSON object carrying the scale factor and the
 * per-item timeout.  Items that share a device and an endpoint are fetched
 * with a single request, see CactiHttpPoller.
 */
final readonly class CactiHttpPollItem {
	public const float DEFAULT_TIMEOUT = 5.0;
	public const float MAX_TIMEOUT     = 60.0;

	public string $endpointKey;

	/**
	 * @param int    $hostId      the device that owns the endpoint
	 * @param int    $localDataId the data source fed by this item
	 * @param string $rrdName     the data source item name
	 * @param string $url         absolute http or https endpoint
	 * @param string $pointer     JSON Pointer, '' selects the whole document
	 * @param float  $scale       multiplier applied to the selected value
	 * @param float  $timeout     seconds allowed for the whole request
	 *
	 * @throws InvalidArgumentException when any part of the spec is unusable
	 */
	public function __construct(
		public int $hostId,
		public int $localDataId,
		public string $rrdName,
		public string $url,
		public string $pointer = '',
		public float $scale = 1.0,
		public float $timeout = self::DEFAULT_TIMEOUT,
	) {
		$parts = parse_url($url);

		if ($parts === false || !isset($parts['scheme'], $parts['host']) || !in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
			throw new InvalidArgumentException('HTTP endpoint must be an absolute http or https URL');
		}

		if ($pointer !== '' && $pointer[0] !== '/') {
			throw new InvalidArgumentException('JSON Pointer must be empty or start with /');
		}

		if (!is_finite($scale)) {
			throw new InvalidArgumentException('Scale factor must be a finite number');
		}

		if (!($timeout > 0) || $timeout > self::MAX_TIMEOUT) {
			throw new InvalidArgumentException(sprintf('Timeout must be greater than 0 and at most %d seconds', self::MAX_TIMEOUT));
		}

		$this->endpointKey = $hostId . ' ' . $url;
	}

	/**
	 * Build an item from a poller_item row.
	 *
	 * @param array<string, mixed> $item
	 *
	 * @throws InvalidArgumentException when the row does not hold a valid spec
	 */
	public static function fromPollerItem(array $item) : self {
		$options = [];

		if (($item['arg3'] ?? '') !== '') {
			try {
				$options = json_decode((string) $item['arg3'], true, 2, JSON_THROW_ON_ERROR);
			} catch (JsonException $e) {
				throw new InvalidArgumentException('HTTP item options are not valid JSON', 0, $e);
			}

			if (!is_array($options) || ($options !== [] && array_is_list($options))) {
				throw new InvalidArgumentException('HTTP item options must be a JSON object');
			}
		}

		foreach (['scale', 'timeout'] as $key) {
			if (isset($options[$key]) && !is_int($options[$key]) && !is_float($options[$key])) {
				throw new InvalidArgumentException("HTTP item option '$key' must be a number");
			}
		}

		return new self(
			(int) $item['host_id'],
			(int) $item['local_data_id'],
			(string) $item['rrd_name'],
			trim((string) $item['arg1']),
			(string) ($item['arg2'] ?? ''),
			(float) ($options['scale'] ?? 1.0),
			(float) ($options['timeout'] ?? self::DEFAULT_TIMEOUT),
		);
	}

	/**
	 * Encode the scale and timeout data input values for poller_item.arg3.
	 *
	 * Blank values fall back to the defaults.  Values that are not numeric are
	 * kept verbatim so the poller reports the broken item instead of silently
	 * polling it with a default.
	 */
	public static function encodeOptions(string $scale, string $timeout) : string {
		$options = [];

		foreach (['scale' => trim($scale), 'timeout' => trim($timeout)] as $key => $value) {
			if ($value !== '') {
				$options[$key] = is_numeric($value) ? $value + 0 : $value;
			}
		}

		return $options === [] ? '' : json_encode($options, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION | JSON_INVALID_UTF8_SUBSTITUTE);
	}

	/**
	 * Select and scale this item's value from a decoded response.
	 *
	 * Returns the poller output for the value, or null when the pointer does
	 * not resolve to a number.  An unscaled integer is passed through as a
	 * string so 64 bit counters keep every digit.
	 */
	public function resolve(mixed $document) : ?string {
		$value = self::pointer($document, $this->pointer);

		if (is_bool($value)) {
			$value = (int) $value;
		}

		if ((!is_int($value) && !is_float($value) && !is_string($value)) || !is_numeric($value)) {
			return null;
		}

		if ($this->scale == 1.0) {
			return is_float($value) ? var_export($value, true) : trim((string) $value);
		}

		$scaled = (float) $value * $this->scale;

		return is_finite($scaled) ? var_export($scaled, true) : null;
	}

	/**
	 * The endpoint without credentials or query string, for log messages.
	 */
	public function redactedUrl() : string {
		return self::redact($this->url);
	}

	/**
	 * Strip credentials, query string and fragment from a URL for logging.
	 */
	public static function redact(string $url) : string {
		$parts = parse_url(trim($url));

		if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
			return 'invalid URL';
		}

		return $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '') . ($parts['path'] ?? '');
	}

	/**
	 * Evaluate an RFC 6901 JSON Pointer against a document decoded to arrays.
	 *
	 * Returns null when the pointer does not resolve.
	 */
	public static function pointer(mixed $document, string $pointer) : mixed {
		if ($pointer === '') {
			return $document;
		}

		foreach (explode('/', substr($pointer, 1)) as $token) {
			$token = strtr($token, ['~1' => '/', '~0' => '~']);

			if (!is_array($document)) {
				return null;
			}

			// a list index is decimal without leading zeros; '-' never exists
			if (array_is_list($document) && $document !== []) {
				if (!preg_match('/^(0|[1-9][0-9]*)$/', $token)) {
					return null;
				}

				$token = (int) $token;
			}

			if (!array_key_exists($token, $document)) {
				return null;
			}

			$document = $document[$token];
		}

		return $document;
	}
}

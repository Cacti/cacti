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
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

namespace Cacti\Cache;

/**
 * A storage tier for SharedCache. Backends persist one opaque wire payload and
 * a short plaintext checksum per store name. The checksum lets a producer
 * decide whether a rebuild is needed without decoding (or decrypting) the body.
 */
interface CacheBackend {
	/** Whether this tier can be used in the current runtime/host. */
	public function isAvailable(): bool;

	/** Persist $payload (+ $checksum sidecar). Returns success. */
	public function write(string $name, string $payload, string $checksum): bool;

	/** Return the stored payload, or null when absent. */
	public function read(string $name): ?string;

	/** Return the stored checksum without decoding the payload, or null. */
	public function readChecksum(string $name): ?string;

	/** Remove any stored data for $name. */
	public function clear(string $name): void;

	/** True when this tier is only visible to the current process. */
	public function isProcessLocal(): bool;
}

/**
 * Process-local tier. Never shared; used as the terminal fallback and as the
 * in-process memo so repeated fetches do not re-touch a shared tier.
 */
class StaticBackend implements CacheBackend {
	/** @var array<string,array{p:string,c:string}> */
	private static array $store = array();

	public function isAvailable(): bool {
		return true;
	}

	public function write(string $name, string $payload, string $checksum): bool {
		self::$store[$name] = array('p' => $payload, 'c' => $checksum);

		return true;
	}

	public function read(string $name): ?string {
		return self::$store[$name]['p'] ?? null;
	}

	public function readChecksum(string $name): ?string {
		return self::$store[$name]['c'] ?? null;
	}

	public function clear(string $name): void {
		unset(self::$store[$name]);
	}

	public function isProcessLocal(): bool {
		return true;
	}
}

/**
 * Durable file tier. The payload is written as a PHP file that returns the
 * data, so once OPcache has compiled it the hot path is an opcode-cached
 * include rather than a disk read + unserialize. Shared across processes
 * because the file persists on disk.
 */
class OpcacheFileBackend implements CacheBackend {
	public function __construct(private string $dir) {
	}

	public function isAvailable(): bool {
		if ($this->dir === '') {
			return false;
		}

		if (!is_dir($this->dir)) {
			@mkdir($this->dir, 0770, true);
		}

		return is_dir($this->dir) && is_writable($this->dir);
	}

	private function file(string $name): string {
		return $this->dir . '/' . $name . '.cache.php';
	}

	public function write(string $name, string $payload, string $checksum): bool {
		$file = $this->file($name);
		$tmp  = $file . '.' . getmypid() . '.tmp';

		$code = "<?php\n\nreturn array(\n"
			. "\t'checksum' => " . var_export($checksum, true) . ",\n"
			. "\t'payload'  => " . var_export(base64_encode($payload), true) . ",\n"
			. ");\n";

		if (@file_put_contents($tmp, $code, LOCK_EX) === false) {
			return false;
		}

		@chmod($tmp, 0600);

		if (!@rename($tmp, $file)) {
			@unlink($tmp);

			return false;
		}

		if (function_exists('opcache_invalidate')) {
			@opcache_invalidate($file, true);
		}

		return true;
	}

	/** @return array{checksum?:string,payload?:string}|null */
	private function load(string $name): ?array {
		$file = $this->file($name);

		if (!is_file($file)) {
			return null;
		}

		$data = @include $file;

		return is_array($data) ? $data : null;
	}

	public function read(string $name): ?string {
		$data = $this->load($name);

		if ($data === null || !isset($data['payload'])) {
			return null;
		}

		$raw = base64_decode((string) $data['payload'], true);

		return $raw === false ? null : $raw;
	}

	public function readChecksum(string $name): ?string {
		$data = $this->load($name);

		return $data['checksum'] ?? null;
	}

	public function clear(string $name): void {
		$file = $this->file($name);

		if (is_file($file)) {
			@unlink($file);

			if (function_exists('opcache_invalidate')) {
				@opcache_invalidate($file, true);
			}
		}
	}

	public function isProcessLocal(): bool {
		return false;
	}
}

/**
 * Shared-memory tier (System V) for POSIX hosts. The segment holds a small
 * header (payload length + checksum) followed by the wire payload, so a reader
 * can validate the checksum without pulling the whole body. Unavailable on
 * Windows, where ftok()/shmop semantics differ; callers fall back to the file
 * tier there.
 */
class ShmopBackend implements CacheBackend {
	private const HEADER_LEN   = 68; // 4-byte length + 64-byte checksum
	private const CHECKSUM_LEN = 64;

	public function __construct(private string $dir, private string $osType) {
	}

	public function isAvailable(): bool {
		if ($this->osType === 'win32' || $this->dir === '') {
			return false;
		}

		if (!function_exists('shmop_open') || !function_exists('ftok')) {
			return false;
		}

		if (!is_dir($this->dir)) {
			@mkdir($this->dir, 0770, true);
		}

		return is_dir($this->dir) && is_writable($this->dir);
	}

	private function tokenFile(string $name): string {
		return $this->dir . '/' . $name . '.shmtoken';
	}

	private function key(string $name): int {
		$token = $this->tokenFile($name);

		if (!is_file($token)) {
			@touch($token);
			@chmod($token, 0600);
		}

		return @ftok($token, 'C');
	}

	public function write(string $name, string $payload, string $checksum): bool {
		$key = $this->key($name);

		if ($key === -1) {
			return false;
		}

		$header = pack('N', strlen($payload)) . str_pad(substr($checksum, 0, self::CHECKSUM_LEN), self::CHECKSUM_LEN, "\0");
		$blob   = $header . $payload;
		$size   = strlen($blob);

		// A segment is fixed-size at creation, so drop any prior one first.
		$existing = @shmop_open($key, 'a', 0, 0);

		if ($existing !== false) {
			@shmop_delete($existing);
			$this->closeSegment($existing);
		}

		$segment = @shmop_open($key, 'c', 0600, $size);

		if ($segment === false) {
			return false;
		}

		$written = @shmop_write($segment, $blob, 0);
		$this->closeSegment($segment);

		return $written === $size;
	}

	/** @return \Shmop|resource|false */
	private function openForRead(string $name) {
		$key = $this->key($name);

		if ($key === -1) {
			return false;
		}

		return @shmop_open($key, 'a', 0, 0);
	}

	public function read(string $name): ?string {
		$segment = $this->openForRead($name);

		if ($segment === false) {
			return null;
		}

		$size = shmop_size($segment);

		if ($size <= self::HEADER_LEN) {
			$this->closeSegment($segment);

			return null;
		}

		$blob = shmop_read($segment, 0, $size);
		$this->closeSegment($segment);

		if ($blob === false || strlen($blob) < self::HEADER_LEN) {
			return null;
		}

		$length  = unpack('N', substr($blob, 0, 4))[1];
		$payload = substr($blob, self::HEADER_LEN, $length);

		return strlen($payload) === $length ? $payload : null;
	}

	public function readChecksum(string $name): ?string {
		$segment = $this->openForRead($name);

		if ($segment === false) {
			return null;
		}

		$size = shmop_size($segment);

		if ($size < self::HEADER_LEN) {
			$this->closeSegment($segment);

			return null;
		}

		$header = shmop_read($segment, 0, self::HEADER_LEN);
		$this->closeSegment($segment);

		if ($header === false || strlen($header) < self::HEADER_LEN) {
			return null;
		}

		return rtrim(substr($header, 4, self::CHECKSUM_LEN), "\0");
	}

	public function clear(string $name): void {
		$segment = $this->openForRead($name);

		if ($segment !== false) {
			@shmop_delete($segment);
			$this->closeSegment($segment);
		}

		$token = $this->tokenFile($name);

		if (is_file($token)) {
			@unlink($token);
		}
	}

	public function isProcessLocal(): bool {
		return false;
	}

	/** @param \Shmop|resource $segment */
	private function closeSegment($segment): void {
		// shmop_close() was removed in PHP 8.0 (segments close automatically).
		if (function_exists('shmop_close') && is_resource($segment)) {
			@shmop_close($segment);
		}
	}
}

/**
 * A generalized, cross-process object cache for Cacti utilities.
 *
 * One SharedCache instance manages one named store (one file / one shared
 * memory segment), so different subsystems keep separate cache files. A
 * producer process builds the object once and store()s it to the highest
 * priority shared tier; every consumer process fetch()es and decodes it a
 * single time into process memory.
 *
 * Tiers are tried in priority order; the default is the durable OPcache file,
 * then POSIX shared memory, then a process-local static fallback. When only
 * the process-local tier is available there is no cross-process sharing and
 * each consumer must build its own copy.
 *
 * With 'encrypted' => true the payload is sealed with Cacti's authenticated
 * per-installation secret key (cacti_encrypt_secret()/cacti_decrypt_secret())
 * before it touches disk or shared memory.
 */
class SharedCache {
	private string $name;
	private bool $encrypted;

	/** @var CacheBackend[] */
	private array $backends;

	private bool $memoLoaded = false;

	/** @var mixed */
	private $memo = null;

	/**
	 * @param string $name    Store name; also the cache file stem.
	 * @param array  $options encrypted(bool), cache_dir(string), os_type(string),
	 *                        backends(string[] priority list).
	 */
	public function __construct(string $name, array $options = array()) {
		$this->name      = preg_replace('/[^A-Za-z0-9_\-]/', '_', $name);
		$this->encrypted = (bool) ($options['encrypted'] ?? false);

		$dir   = (string) ($options['cache_dir'] ?? self::defaultCacheDir());
		$os    = (string) ($options['os_type'] ?? self::osType());
		$order = $options['backends'] ?? array('opcache', 'shmop', 'static');

		$this->backends = array();
		$hasLocal       = false;

		foreach ($order as $id) {
			$backend = self::makeBackend($id, $dir, $os);

			if ($backend !== null && $backend->isAvailable()) {
				$this->backends[] = $backend;

				if ($backend->isProcessLocal()) {
					$hasLocal = true;
				}
			}
		}

		if (!$hasLocal) {
			$this->backends[] = new StaticBackend();
		}
	}

	/**
	 * Encode $data and write it to the highest priority shared tier. The
	 * in-process memo is always updated. Returns false when no shared tier
	 * accepted the write (callers should then treat the data as unshared).
	 *
	 * @param mixed       $data
	 * @param string|null $checksum Optional change token for checksum().
	 */
	public function store($data, ?string $checksum = null): bool {
		$wire = $this->encode($data);

		if ($wire === false) {
			return false;
		}

		$sum              = $checksum ?? sha1($wire);
		$this->memo       = $data;
		$this->memoLoaded = true;

		foreach ($this->backends as $backend) {
			if ($backend->isProcessLocal()) {
				$backend->write($this->name, $wire, $sum);

				continue;
			}

			if ($backend->write($this->name, $wire, $sum)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Decode and return the cached object, or false when nothing is cached (or
	 * the payload cannot be decoded/decrypted). Memoized per process.
	 *
	 * @return mixed
	 */
	public function fetch() {
		if ($this->memoLoaded) {
			return $this->memo;
		}

		foreach ($this->backends as $backend) {
			$wire = $backend->read($this->name);

			if ($wire === null) {
				continue;
			}

			$data = $this->decode($wire);

			if ($data !== false) {
				$this->memo       = $data;
				$this->memoLoaded = true;

				return $data;
			}
		}

		$this->memoLoaded = true;
		$this->memo       = false;

		return false;
	}

	/**
	 * Return the stored change token from the highest priority shared tier
	 * without decoding the body, or null when nothing is stored.
	 */
	public function checksum(): ?string {
		foreach ($this->backends as $backend) {
			if ($backend->isProcessLocal()) {
				continue;
			}

			$checksum = $backend->readChecksum($this->name);

			if ($checksum !== null) {
				return $checksum;
			}
		}

		return null;
	}

	/** Drop the store from every tier. */
	public function invalidate(): void {
		foreach ($this->backends as $backend) {
			$backend->clear($this->name);
		}

		$this->memo       = null;
		$this->memoLoaded = false;
	}

	/** True when at least one cross-process tier is active. */
	public function isShared(): bool {
		foreach ($this->backends as $backend) {
			if (!$backend->isProcessLocal()) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param mixed $data
	 * @return string|false
	 */
	private function encode($data) {
		$serialized = serialize($data);

		if (!$this->encrypted) {
			return $serialized;
		}

		if (!function_exists('cacti_encrypt_secret')) {
			return false;
		}

		try {
			$wire = \cacti_encrypt_secret($serialized);
		} catch (\Throwable $e) {
			return false;
		}

		return ($wire === '' || $wire === false) ? false : $wire;
	}

	/**
	 * @return mixed false on failure
	 */
	private function decode(string $wire) {
		if ($this->encrypted) {
			if (!function_exists('cacti_decrypt_secret')) {
				return false;
			}

			$plain = \cacti_decrypt_secret($wire);

			if ($plain === false) {
				return false;
			}

			$wire = $plain;
		}

		$data = @unserialize($wire, array('allowed_classes' => false));

		// Our stored payloads are always arrays; a false result means failure.
		return is_array($data) ? $data : false;
	}

	private static function defaultCacheDir(): string {
		global $config;

		$base = $config['base_path'] ?? getcwd();

		return $base . '/cache';
	}

	private static function osType(): string {
		global $config;

		if (!empty($config['cacti_server_os'])) {
			return $config['cacti_server_os'];
		}

		return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? 'win32' : 'unix';
	}

	private static function makeBackend(string $id, string $dir, string $os): ?CacheBackend {
		switch ($id) {
			case 'opcache':
			case 'file':
				return new OpcacheFileBackend($dir);
			case 'shmop':
				return new ShmopBackend($dir, $os);
			case 'static':
				return new StaticBackend();
			default:
				return null;
		}
	}
}

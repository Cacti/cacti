<?php
declare(strict_types = 1);
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

namespace Cacti\Http;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

class CactiRequest {
	private static ?Request $currentRequest = null;

	/**
	 * Reset the current request object (mainly for testing).
	 */
	public static function reset(): void {
		self::$currentRequest = null;
	}

	/**
	 * Get the current Symfony Request object.
	 *
	 * @throws \Symfony\Component\HttpFoundation\Exception\SessionNotFoundException
	 *         When no session is bound and the test bootstrap is not active.
	 */
	public static function current(): Request {
		if (self::$currentRequest === null) {
			self::$currentRequest = Request::createFromGlobals();

			// Only fall back to a mock session when the test bootstrap
			// is explicitly active; otherwise the missing-session error
			// must propagate so production callers see real failures.
			try {
				self::$currentRequest->getSession();
			} catch (\Exception $e) {
				if (getenv('CACTI_TEST_BOOTSTRAP') !== '1') {
					throw $e;
				}
				$session = new Session(new MockArraySessionStorage());
				self::$currentRequest->setSession($session);
			}
		}
		return self::$currentRequest;
	}

	/**
	 * Get a session variable.
	 */
	public static function getSession(string $key, mixed $default = null): mixed {
		return self::current()->getSession()->get($key, $default);
	}

	/**
	 * Set a session variable.
	 */
	public static function setSession(string $key, mixed $value): void {
		self::current()->getSession()->set($key, $value);
		// Update global for legacy compatibility
		$_SESSION[$key] = $value;
	}

	/**
	 * Check if a session variable exists.
	 */
	public static function hasSession(string $key): bool {
		return self::current()->getSession()->has($key);
	}

	/**
	 * Get a server variable.
	 */
	public static function getServer(string $key, mixed $default = null): mixed {
		return self::current()->server->get($key, $default);
	}

	/**
	 * Check if the current request is an AJAX request.
	 *
	 * The result is non-authoritative: the X-Requested-With header and
	 * the `header` query parameter are both client-supplied and trivially
	 * forgeable. Do not use this as a security boundary.
	 */
	public static function isAjax(): bool {
		return self::current()->isXmlHttpRequest() || self::get('header') === 'false';
	}

	/**
	 * Get a request parameter (GET/POST/COOKIE).
	 */
	public static function get(string $key, mixed $default = null): mixed {
		return self::current()->get($key, $default);
	}

	/**
	 * Check if a request parameter exists.
	 */
	public static function has(string $key): bool {
		return self::current()->query->has($key)
			|| self::current()->request->has($key)
			|| self::current()->attributes->has($key);
	}

	/**
	 * Set a request parameter on the attributes bag.
	 *
	 * $_REQUEST is also updated for legacy callers that read globals;
	 * $_POST is intentionally not touched, since writing to $_POST blurs
	 * the GET/POST boundary and lets attribute writes appear as
	 * client-supplied form data to downstream code.
	 */
	public static function set(string $key, mixed $value): void {
		self::current()->attributes->set($key, $value);
		$_REQUEST[$key] = $value;
	}

	/**
	 * Get a query parameter (GET) as an integer.
	 */
	public static function getInt(string $key, int $default = 0): int {
		return self::current()->query->getInt($key, $default);
	}

	/**
	 * Get a request parameter (POST) as an integer.
	 */
	public static function postInt(string $key, int $default = 0): int {
		return self::current()->request->getInt($key, $default);
	}

	/**
	 * Get an alphanumeric string from the request (safe from injection).
	 */
	public static function getAlnum(string $key, string $default = ''): string {
		return (string)preg_replace('/[^a-zA-Z0-9]/', '', (string)self::current()->query->get($key, $default));
	}
}

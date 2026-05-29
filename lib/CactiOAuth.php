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
*/

use Greew\OAuth2\Client\Provider\Azure;
use Hayageek\OAuth2\Client\Provider\Yahoo;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Google;
use Stevenmaguire\OAuth2\Client\Provider\Keycloak;
use Stevenmaguire\OAuth2\Client\Provider\Microsoft;

class CactiOAuth {
	/**
	 * Get an OAuth2 provider based on configuration.
	 *
	 * @param string $providerName The provider name (google, microsoft, azure, yahoo, keycloak).
	 * @param array  $params       The configuration parameters (clientId, clientSecret, etc).
	 *
	 * @return AbstractProvider|null
	 */
	public static function getProvider(string $providerName, array $params): ?AbstractProvider {
		switch ($providerName) {
			case 'google':
				return new Google($params);
			case 'microsoft':
				return new Microsoft($params);
			case 'azure':
				return new Azure($params);
			case 'yahoo':
				return new Yahoo($params);
			case 'keycloak':
				return new Keycloak($params);
			default:
				return null;
		}
	}

	/**
	 * Get the default options for a provider.
	 *
	 * keycloak: scopes are realm-specific; caller must supply via params, not defaults.
	 *
	 * @param string $providerName The provider name.
	 *
	 * @return array
	 */
	public static function getDefaultOptions(string $providerName): array {
		switch ($providerName) {
			case 'google':
				return [
					'scope' => ['https://mail.google.com/']
				];
			case 'microsoft':
				return [
					'scope' => ['wl.imap', 'wl.offline_access']
				];
			case 'azure':
				return [
					'scope' => ['https://outlook.office.com/SMTP.Send', 'offline_access']
				];
			default:
				return [];
		}
	}
}

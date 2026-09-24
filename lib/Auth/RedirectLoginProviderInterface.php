<?php
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

declare(strict_types = 1);

namespace Cacti\Auth;

/**
 * A provider that authenticates by redirecting the browser to an external
 * Identity Provider and validating a callback (SAML2 ACS / OpenID Connect
 * redirect_uri). Implemented by SamlLoginProvider and OpenIdLoginProvider.
 */
interface RedirectLoginProviderInterface extends LoginProviderInterface {
	/** The label shown on the login page button, e.g. "Login with Azure AD". */
	public function getButtonLabel(): string;

	/** Send the browser to the IdP. Never returns. */
	public function initiate(): never;

	/** Validate the IdP's callback against the current request and resolve the user. */
	public function complete(): LoginResult;
}

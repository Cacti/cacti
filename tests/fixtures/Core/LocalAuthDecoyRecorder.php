<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

namespace Cacti\Auth;

/*
 * LocalAuthLoginProvider calls cacti_password_verify_login() unqualified from the
 * Cacti\Auth namespace, so this definition takes precedence over the global
 * one for the provider only. It records the hash each verification used and
 * then runs the production function sliced from lib/auth.php.
 */
function cacti_password_verify_login(string $password, string $hash) : bool {
	$GLOBALS['verified'][] = $hash;

	return \cacti_password_verify_login($password, $hash);
}

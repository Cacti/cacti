<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

namespace Cacti\Auth;

/*
 * LocalAuthLoginProvider calls compat_password_verify() unqualified from the
 * Cacti\Auth namespace, so this definition takes precedence over the global
 * one for the provider only. It records the hash each verification used and
 * then runs the production function sliced from lib/auth.php.
 */
function compat_password_verify(string $password, string $hash) : bool {
	$GLOBALS['verified'][] = $hash;

	return \compat_password_verify($password, $hash);
}

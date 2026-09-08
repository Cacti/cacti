<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

$repoRoot     = dirname(__DIR__, 4);
$ldapSource   = file_get_contents($repoRoot . '/lib/ldap.php');
$authSource   = file_get_contents($repoRoot . '/lib/auth.php');
$domainSource = file_get_contents($repoRoot . '/user_domains.php');

test('domains_login_process does not interpolate LDAP error_text into the login page', function () use ($authSource) {
	$start = strpos($authSource, 'function domains_login_process(');
	$body  = substr($authSource, $start, 9000);

	expect($body)->not->toContain("__('LDAP Search Error: %s'");
	expect($body)->not->toContain("__('Access Denied!  LDAP Error: %s'");
});

test('domains_login_process locks out on error_num not error_text', function () use ($authSource) {
	$start = strpos($authSource, 'function domains_login_process(');
	$body  = substr($authSource, $start, 9000);

	expect($body)->toContain("error_num'] == 1");
	expect($body)->not->toContain("error_text'] == 1");
});

test('domains_login_process fails when LDAP succeeded but the domain has no template and no user', function () use ($authSource) {
	$start = strpos($authSource, 'function domains_login_process(');
	$body  = substr($authSource, $start, 9000);

	expect($body)->toContain('Domain template is not configured');
});

test('Authenticate restores the Cacti handler on an empty password', function () use ($ldapSource) {
	$start = strpos($ldapSource, 'function Authenticate()');
	$body  = substr($ldapSource, $start, 8000);
	$empty = strpos($body, "password == ''");

	expect($empty)->not->toBeFalse();
	expect(substr($body, $empty, 400))->toContain('RestoreCactiHandler');
});

test('Getcn restores the Cacti handler on bind-only mode', function () use ($ldapSource) {
	$start = strpos($ldapSource, 'function Getcn()');
	$body  = substr($ldapSource, $start, 4000);
	$mode  = strpos($body, 'mode == 0');

	expect($mode)->not->toBeFalse();
	expect(substr($body, $mode, 400))->toContain('RestoreCactiHandler');
});

test('Getcn distinguishes no user from many users', function () use ($ldapSource) {
	$start = strpos($ldapSource, 'function Getcn()');
	$body  = substr($ldapSource, $start, 5000);

	expect($body)->toContain('LdapError::SearchFoundNoUser)');
	expect($body)->toContain('LdapError::SearchFoundMultiUser)');
});

test('username group membership searches uid cn and UPN with the login name', function () use ($ldapSource) {
	$start = strpos($ldapSource, 'function Authenticate()');
	$body  = substr($ldapSource, $start, 9000);

	expect($body)->toContain("ldap_escape(\$this->username, '', LDAP_ESCAPE_FILTER)");
	expect($body)->not->toContain('userPrincipalName=\' . $filter_dn');
});

test('isUserInLDAPGroup routes the nested-group filter through cacti_ldap_filter', function () use ($ldapSource) {
	$start = strpos($ldapSource, 'function isUserInLDAPGroup(');
	$body  = substr($ldapSource, $start, 800);

	expect($body)->toContain('cacti_ldap_filter(');
	expect($body)->not->toContain('distinguishedName=$ldapUser');
});

test('bind timeout is gated on LDAP_OPT_TIMEOUT', function () use ($ldapSource) {
	expect($ldapSource)->toContain("defined('LDAP_OPT_TIMEOUT')");
});

test('new LDAP domains demand a valid TLS certificate', function () use ($domainSource) {
	expect($domainSource)->toContain("'default'       => LDAP_OPT_X_TLS_DEMAND");
	expect($domainSource)->not->toContain("'default'       => LDAP_OPT_X_TLS_NEVER");
});

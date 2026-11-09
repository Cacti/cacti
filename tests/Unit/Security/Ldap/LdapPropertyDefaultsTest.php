<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * The legacy cacti_ldap_*() wrappers build an Ldap object and set only the
 * options they are given, so every transport option the class reads must
 * have a default.  The defaults match LdapLoginProvider::buildLdap().
 *
 * The class loads in a child process so the constants it needs do not leak
 * into the rest of the suite.
 */

test('a new Ldap object has the LdapLoginProvider defaults for every option', function () {
	$stub = <<<'PHP'
<?php
define('POLLER_VERBOSITY_HIGH', 4);

if (!defined('LDAP_OPT_X_TLS_DEMAND')) {
	define('LDAP_OPT_X_TLS_DEMAND', 2);
}

require $argv[1];

$ldap   = new Ldap();
$values = [];

foreach (['dn', 'cn', 'port', 'port_ssl', 'version', 'encryption', 'referrals', 'tls_certificate',
	'network_timeout', 'bind_timeout', 'group_require', 'group_dn', 'group_attrib', 'group_member_type',
	'mode', 'search_base', 'search_filter', 'specific_dn', 'specific_password', 'cn_full_name', 'cn_email'] as $option) {
	$values[$option] = $ldap->$option;
}

print json_encode($values);
PHP;

	$script = tempnam(sys_get_temp_dir(), 'cacti_ld_');
	file_put_contents($script, $stub);

	$cmd = escapeshellarg(PHP_BINARY) . ' ' .
		escapeshellarg($script) . ' ' .
		escapeshellarg(dirname(__DIR__, 4) . '/lib/ldap.php') . ' 2>&1';

	$output = [];
	$status = 0;
	exec($cmd, $output, $status);

	unlink($script);

	expect($status)->toBe(0, 'reading the options must not error: ' . implode("\n", $output));

	expect(json_decode(implode("\n", $output), true))->toBe([
		'dn'                => '',
		'cn'                => [],
		'port'              => 389,
		'port_ssl'          => 636,
		'version'           => 3,
		'encryption'        => 0,
		'referrals'         => 0,
		'tls_certificate'   => 2,
		'network_timeout'   => 2,
		'bind_timeout'      => 2,
		'group_require'     => false,
		'group_dn'          => '',
		'group_attrib'      => '',
		'group_member_type' => 1,
		'mode'              => 0,
		'search_base'       => '',
		'search_filter'     => '',
		'specific_dn'       => '',
		'specific_password' => '',
		'cn_full_name'      => '',
		'cn_email'          => '',
	]);
});

<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * GHSA-fxfg-j995-j69j: the packaged docker/apache.conf granted only
 * AllowOverride FileInfo Options=FollowSymLinks for the Cacti document root.
 * Cacti's shipped .htaccess deny rules (log/, cache/*, scripts/, cli/, bin/,
 * mibs/, rra/, contrib/, tests/) rely on "Require all denied" (Apache 2.4,
 * override type AuthConfig) and/or "Order/Deny" (override type Limit), neither
 * of which FileInfo/Options covers, so those directories were served over
 * HTTP unauthenticated to any client -- most notably log/cacti.log, which can
 * contain usernames, IPs, SQL backtraces, and secrets. AllowOverride All lets
 * every shipped .htaccess take effect as intended.
 */

$conf = file_get_contents(__DIR__ . '/../../../../docker/apache.conf');

test('GHSA-fxfg: docker/apache.conf grants AllowOverride All for the Cacti document root', function () use ($conf) {
	expect($conf)->toContain('AllowOverride All')
		->and($conf)->not->toContain('AllowOverride FileInfo Options=FollowSymLinks');
});

test('GHSA-fxfg: the Cacti directory block still explicitly grants web access', function () use ($conf) {
	$start = strpos($conf, '<Directory /var/www/html/cacti>');
	expect($start)->not->toBeFalse();

	$end  = strpos($conf, '</Directory>', $start);
	$body = substr($conf, $start, $end - $start);

	expect($body)->toContain('Require all granted')
		->and($body)->toContain('AllowOverride All');
});

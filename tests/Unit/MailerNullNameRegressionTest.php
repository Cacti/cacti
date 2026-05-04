<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

$functionsSource = file_get_contents(__DIR__ . '/../../lib/functions.php');

// Locate the mailer() body once; all tests below slice from it.
$mailerStart = strpos($functionsSource, 'function mailer(');
$mailerEnd   = strpos($functionsSource, "\nfunction ", $mailerStart + 1);
$mailerBody  = substr($functionsSource, $mailerStart, $mailerEnd - $mailerStart);

// Locate the add_email_details() body once.
$addEmailStart = strpos($functionsSource, 'function add_email_details(');
$addEmailEnd   = strpos($functionsSource, "\nfunction ", $addEmailStart + 1);
$addEmailBody  = substr($functionsSource, $addEmailStart, $addEmailEnd - $addEmailStart);

test('mailer-null-name: null from_name falls back to Cacti literal before PHPMailer call', function () use ($mailerBody) {
	// The fallback guard must appear after the settings lookup so that a null
	// settings_from_name value never reaches PHPMailer as null.
	$settingsPos  = strpos($mailerBody, "read_config_option('settings_from_name')");
	$fallbackPos  = strpos($mailerBody, "\$from['name'] = 'Cacti'");

	expect($settingsPos)->not->toBeFalse();
	expect($fallbackPos)->not->toBeFalse();
	// Guard comes after the settings read, not before.
	expect($fallbackPos)->toBeGreaterThan($settingsPos);
});

test('mailer-null-name: from name fallback is inside an empty() guard, not an unconditional assignment', function () use ($mailerBody) {
	// An unconditional assignment would overwrite a valid configured name.
	// Confirm the 'Cacti' literal only appears inside a conditional block.
	$pos = strpos($mailerBody, "\$from['name'] = 'Cacti'");
	expect($pos)->not->toBeFalse();

	// The nearest preceding control keyword must be 'if' (via empty()).
	$preceding = substr($mailerBody, 0, $pos);
	$lastIf    = strrpos($preceding, 'if (empty($from[\'name\'])');
	expect($lastIf)->not->toBeFalse();
});

test('mailer-null-name: add_email_details casts name to string before the PHPMailer call', function () use ($addEmailBody) {
	// PHPMailer passes the name argument to preg_replace(); passing null is a
	// deprecated TypeError in PHP 8.x.  The cast must be present at the call site.
	expect($addEmailBody)->toContain('(string) $e[\'name\']');
});

test('mailer-null-name: add_email_details does not pass $e[name] uncast to the address method', function () use ($addEmailBody) {
	// Ensure no second call site passes the name without the cast.
	// Count occurrences of the cast vs. bare references passed as arguments.
	$castCount = substr_count($addEmailBody, "(string) \$e['name']");
	$bareCount  = substr_count($addEmailBody, "\$addFunc(\$e['email'], \$e['name']");

	expect($castCount)->toBeGreaterThanOrEqual(1);
	expect($bareCount)->toBe(0);
});

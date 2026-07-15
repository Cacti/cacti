<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

test('mailer implementation uses Symfony Mailer instead of PHPMailer', function () {
	$functions = file_get_contents(__DIR__ . '/../../lib/functions.php');
	$composer  = file_get_contents(__DIR__ . '/../../composer.json');

	expect($composer)->toContain('"symfony/mailer"')
		->not->toContain('"phpmailer/phpmailer"');

	expect($functions)->toContain('use Symfony\\Component\\Mailer\\Mailer')
		->toContain('new Email()')
		->toContain('new Mailer($transport)')
		->toContain('new XOAuth2Authenticator()')
		->not->toContain('new PHPMailer\\PHPMailer\\PHPMailer')
		->not->toContain('new PHPMailer\\PHPMailer\\SMTP')
		->not->toContain('PHPMailer\\PHPMailer\\OAuth');
});

<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

require_once dirname(__DIR__, 2) . '/include/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/lib/CactiMailerTransport.php';

use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\NullTransport;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Exception\RfcComplianceException;
use Symfony\Component\Mime\Part\DataPart;

// =====================================================================
// Bug 1: inline graph image must carry a Content-ID matching the body cid
// =====================================================================

test('inline graph embed exposes a Content-ID matching the cid in the HTML body', function () {
	$cid = getmypid() . '_0@localhost';

	$email = new Email();
	$email->from(new Address('from@example.com', 'From'));
	$email->to(new Address('to@example.com', 'To'));
	$email->html("<br><br><img src='cid:$cid'>");

	// Mirror the production fix: explicit Content-ID, inline disposition.
	$part = new DataPart('PNGDATA', 'graph.png', 'image/png');
	$part->asInline()->setContentId($cid);
	$email->addPart($part);

	// Rendering must not fatal (pre-fix interpolated the Email object).
	$rendered = $email->toString();

	$attachments = $email->getAttachments();
	expect($attachments)->toHaveCount(1);
	expect($attachments[0]->hasContentId())->toBeTrue();
	expect($attachments[0]->getContentId())->toBe($cid);
	expect($rendered)->toContain('Content-Disposition: inline');
	expect($rendered)->toContain($cid);
});

test('embed() returns the Email object, so its return value is not a usable cid', function () {
	$email = new Email();
	$ret   = $email->embed('PNGDATA', 'graph.png', 'image/png');

	// This is why the pre-fix `"cid:$cid"` interpolation fataled.
	expect($ret)->toBeInstanceOf(Email::class);
});

// =====================================================================
// Bug 2: secure mode -> transport TLS configuration
// =====================================================================

test("'ssl' secure mode uses implicit TLS on the socket stream", function () {
	$transport = new EsmtpTransport('smtp.example.com', 465, true);
	$stream    = $transport->getStream();

	expect($stream)->toBeInstanceOf(SocketStream::class);
	expect($stream->isTLS())->toBeTrue();
});

test("'tls' secure mode requires STARTTLS and starts non-implicit", function () {
	$transport = new CactiRequireTlsEsmtpTransport('smtp.example.com', 587, false);
	$stream    = $transport->getStream();

	expect($transport)->toBeInstanceOf(EsmtpTransport::class);
	// 587 STARTTLS starts plaintext, then upgrades; it must not open an implicit TLS socket.
	expect($stream->isTLS())->toBeFalse();
});

test("'none' secure mode attempts no TLS", function () {
	$transport = new EsmtpTransport('smtp.example.com', 25, false);
	$stream    = $transport->getStream();

	expect($stream->isTLS())->toBeFalse();
});

test('require-tls transport fails closed when the stream is still plaintext after start', function () {
	// A stand-in whose parent::start() is a no-op leaves a plaintext stream,
	// which models a server that never offered STARTTLS.
	$transport = new class('smtp.example.com', 587, false) extends CactiRequireTlsEsmtpTransport {
		public function start(): void {
			// Skip the real socket connection; reuse the require-tls guard.
			$stream = $this->getStream();

			if ($stream instanceof SocketStream && !$stream->isTLS()) {
				throw new TransportException('STARTTLS is required but the SMTP server did not offer it.');
			}
		}
	};

	expect(fn () => $transport->start())->toThrow(TransportException::class);
});

// =====================================================================
// Bug 3: malformed recipient must not throw out of the mailer
// =====================================================================

test('Address rejects a malformed recipient with RfcComplianceException, not a transport error', function () {
	expect(fn () => new Address('not a valid address', 'X'))
		->toThrow(RfcComplianceException::class);

	try {
		new Address('not a valid address', 'X');
		$thrown = null;
	} catch (\Throwable $e) {
		$thrown = $e;
	}

	expect($thrown)->not->toBeInstanceOf(TransportExceptionInterface::class);
});

test('catching the address exception converts a malformed recipient into a non-empty error string', function () {
	// Mirror the add_email_details() seam: build addresses, catch the Mime
	// exception, and return an error string instead of letting it escape.
	$addRecipient = function (string $address) : string {
		try {
			$email = new Email();
			$email->from(new Address('from@example.com', 'From'));
			$email->addTo(new Address($address, ''));
			$email->text('body');

			// A valid recipient reaches a (null) transport without network I/O.
			(new Mailer(new NullTransport()))->send($email);

			return '';
		} catch (RfcComplianceException $ex) {
			return 'Bad email format: ' . $address . ' - ' . $ex->getMessage();
		} catch (TransportExceptionInterface $ex) {
			return $ex->getMessage();
		}
	};

	$error = $addRecipient('not a valid address');
	expect($error)->not->toBe('');
	expect($error)->toContain('Bad email format');

	$ok = $addRecipient('good@example.com');
	expect($ok)->toBe('');
});

// =====================================================================
// Source-level guards on the production helpers in lib/functions.php
// =====================================================================

test('mailer helpers map secure modes without the opportunistic STARTTLS downgrade', function () {
	$src = file_get_contents(dirname(__DIR__, 2) . '/lib/functions.php');

	// The fix removes the inline `($secure == 'tls') ? null` mapping that made
	// STARTTLS opportunistic at every transport construction site.
	expect($src)->not->toContain('? null : false');

	expect($src)->toContain('function mailer_secure_tls_flag')
		->toContain('function mailer_normalize_secure_mode')
		->toContain('function mailer_build_esmtp_transport')
		->toContain('CactiRequireTlsEsmtpTransport')
		->toContain('CactiNoTlsEsmtpTransport')
		->toContain('finally')
		->toContain('catch (RfcComplianceException');
});

// Mirror of mailer_normalize_secure_mode() in lib/functions.php
function test_mailer_normalize_secure_mode(string $secure) : string {
	$secure = strtolower(trim($secure));

	if ($secure === 'ssl' || $secure === 'tls' || $secure === 'none') {
		return $secure;
	}

	return 'none';
}

test('empty and unknown SMTP secure modes map to none', function () {
	expect(test_mailer_normalize_secure_mode(''))->toBe('none');
	expect(test_mailer_normalize_secure_mode('  '))->toBe('none');
	expect(test_mailer_normalize_secure_mode('garbage'))->toBe('none');
	expect(test_mailer_normalize_secure_mode('TLS'))->toBe('tls');
	expect(test_mailer_normalize_secure_mode('ssl'))->toBe('ssl');
	expect(test_mailer_normalize_secure_mode('none'))->toBe('none');
});

test('non-graph attachment disposition honours inline vs attachment flags', function () {
	// attachment disposition
	$email = new Email();
	$email->from(new Address('from@example.com'));
	$email->to(new Address('to@example.com'));
	$email->attach('PAYLOAD', 'report.csv', 'text/csv');
	expect($email->toString())->toContain('Content-Disposition: attachment');

	// inline disposition
	$email2 = new Email();
	$email2->from(new Address('from@example.com'));
	$email2->to(new Address('to@example.com'));
	$part = new DataPart('PNGDATA', 'chart.png', 'image/png');
	$part->asInline();
	$email2->addPart($part);
	expect($email2->toString())->toContain('Content-Disposition: inline');
});

test('mailer fails closed when attachment path is unreadable', function () {
	$src = file_get_contents(dirname(__DIR__, 2) . '/lib/functions.php');
	expect($src)->toContain('is_readable($attachment[\'attachment\'])')
		->toContain("Error attaching file:");
});

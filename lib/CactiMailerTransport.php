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

use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;

/**
 * EsmtpTransport that requires STARTTLS.
 *
 * The base EsmtpTransport only upgrades to STARTTLS when the server advertises
 * it, so a server that omits the capability is talked to in plaintext. This
 * matches PHPMailer's mandatory-STARTTLS behaviour: after the connection is
 * established the stream must be encrypted, otherwise the send fails closed.
 * This Symfony release has no setRequireTls(), so the check is done here.
 */
class CactiRequireTlsEsmtpTransport extends EsmtpTransport {
	public function start(): void {
		parent::start();

		$stream = $this->getStream();

		// After EHLO, an advertised STARTTLS leaves the stream encrypted. A
		// plaintext stream here means the server never offered STARTTLS.
		if ($stream instanceof SocketStream && !$stream->isTLS()) {
			$this->stop();

			throw new TransportException('STARTTLS is required but the SMTP server did not offer it.');
		}
	}
}

/**
 * EsmtpTransport that never upgrades to STARTTLS.
 *
 * The base EsmtpTransport upgrades to STARTTLS whenever the server advertises
 * it, even when no encryption was requested. This matches PHPMailer's
 * SMTPAutoTLS = false behaviour for the 'none' security mode: the STARTTLS
 * capability is stripped from the EHLO response before the base class can act
 * on it. This Symfony release has no setAutoTls(), so the filtering is done
 * here.
 */
class CactiNoTlsEsmtpTransport extends EsmtpTransport {
	/**
	 * @param int[] $codes
	 */
	public function executeCommand(string $command, array $codes): string {
		$response = parent::executeCommand($command, $codes);

		// Hide the STARTTLS capability so the base class never upgrades.
		if (str_starts_with($command, 'EHLO ')) {
			$response = preg_replace('/^\d{3}[ -]STARTTLS\r\n/mi', '', $response) ?? $response;
		}

		return $response;
	}
}

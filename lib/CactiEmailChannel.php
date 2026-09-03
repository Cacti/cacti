<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 */

use Symfony\Component\Notifier\Channel\ChannelInterface;
use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Exception\RuntimeException;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Recipient\EmailRecipientInterface;
use Symfony\Component\Notifier\Recipient\RecipientInterface;

/**
 * Delivers Symfony notifications through Cacti's configured mailer.
 */
final class CactiEmailChannel implements ChannelInterface {
	private Closure $mailer;

	public function __construct(?callable $mailer = null) {
		if ($mailer === null) {
			$mailer = static fn (...$arguments) : string => (string) call_user_func_array('mailer', $arguments);
		}

		$this->mailer = Closure::fromCallable($mailer);
	}

	public function notify(Notification $notification, RecipientInterface $recipient, ?string $transportName = null) : void {
		if (!$recipient instanceof EmailRecipientInterface || trim($recipient->getEmail()) === '') {
			throw new LogicException('The Cacti email channel needs an email recipient.');
		}

		if ($transportName !== null) {
			throw new LogicException('The Cacti email channel uses the transport selected in Cacti settings.');
		}

		$options     = $notification instanceof CactiNotification ? $notification->getOptions('email') : [];
		$name        = $recipient instanceof CactiRecipient ? $recipient->getName() : '';
		$to          = $this->recipients($recipient, $name, $options['to'] ?? null);
		$html        = (bool) ($options['html'] ?? false);
		$text        = (string) ($options['text'] ?? ($html ? '' : $notification->getContent()));
		$attachments = $options['attachments'] ?? [];
		$headers     = $options['headers'] ?? [];

		if (!is_array($attachments)) {
			throw new LogicException('The Cacti email attachments option must be an array.');
		}

		if (!is_array($headers)) {
			throw new LogicException('The Cacti email headers option must be an array.');
		}

		$error = ($this->mailer)(
			$options['from'] ?? '',
			$to,
			$options['cc'] ?? null,
			$options['bcc'] ?? null,
			$options['reply_to'] ?? null,
			$notification->getSubject(),
			$notification->getContent(),
			$text,
			$attachments,
			$headers,
			$html,
			(bool) ($options['expand_ids'] ?? false)
		);

		if ($error !== '') {
			throw new RuntimeException(trim(strip_tags((string) $error)));
		}
	}

	/**
	 * Build the To list for a single message.
	 *
	 * Symfony calls notify() once per recipient, so a caller that needs one
	 * message addressed to several people cannot express that through
	 * recipients alone.  The 'to' option carries the additional addresses and
	 * they are folded into the same mailer() call.
	 *
	 * @param array<string, string>|string|null $additional
	 *
	 * @return list<array{email: string, name: string}>
	 */
	private function recipients(EmailRecipientInterface $recipient, string $name, array|string|null $additional) : array {
		$primary = trim($recipient->getEmail());
		$to      = [['email' => $primary, 'name' => $name]];

		if ($additional === null || $additional === '' || $additional === []) {
			return $to;
		}

		$seen = [$this->addressKey($primary) => true];

		foreach (parse_email_details($additional) as $extra) {
			$address = trim((string) ($extra['email'] ?? ''));
			$key     = $this->addressKey($address);

			if ($address === '' || isset($seen[$key])) {
				continue;
			}

			$seen[$key] = true;
			$to[]       = ['email' => $address, 'name' => trim((string) ($extra['name'] ?? ''))];
		}

		return $to;
	}

	/**
	 * Build the key that decides whether two addresses are the same mailbox.
	 *
	 * RFC 5321 leaves the local part to the receiving host, so only the domain
	 * folds to lower case.  NETNIV@cacti.org and netniv@cacti.org may be two
	 * mailboxes and both have to survive the fold.
	 */
	private function addressKey(string $address) : string {
		$at = strrpos($address, '@');

		if ($at === false) {
			return $address;
		}

		return substr($address, 0, $at) . '@' . mb_strtolower(substr($address, $at + 1));
	}

	public function supports(Notification $notification, RecipientInterface $recipient) : bool {
		if (!$recipient instanceof EmailRecipientInterface) {
			return false;
		}

		return trim($recipient->getEmail()) !== '';
	}
}

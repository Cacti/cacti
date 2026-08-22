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
		$to          = [['email' => $recipient->getEmail(), 'name' => $name]];
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

	public function supports(Notification $notification, RecipientInterface $recipient) : bool {
		if (!$recipient instanceof EmailRecipientInterface) {
			return false;
		}

		return trim($recipient->getEmail()) !== '';
	}
}

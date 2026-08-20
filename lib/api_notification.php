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
use Symfony\Component\Notifier\Notifier;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Recipient\EmailRecipientInterface;
use Symfony\Component\Notifier\Recipient\RecipientInterface;

function api_notification_dependencies_available() : bool {
	return interface_exists(ChannelInterface::class)
		&& class_exists(Notifier::class)
		&& interface_exists(NotifierInterface::class)
		&& class_exists(Notification::class)
		&& interface_exists(EmailRecipientInterface::class)
		&& interface_exists(RecipientInterface::class);
}

if (api_notification_dependencies_available()) {
	require_once __DIR__ . '/CactiNotification.php';
	require_once __DIR__ . '/CactiRecipient.php';
	require_once __DIR__ . '/CactiEmailChannel.php';
}

function api_notification_assert_dependencies() : void {
	if (!api_notification_dependencies_available()) {
		throw new RuntimeException('Symfony Notifier dependencies are unavailable. Run Composer install to restore the vendor tree.');
	}
}

/**
 * Returns Cacti's notification channels, extended by installed plugins.
 *
 * Plugins may register the `notification_channels` hook and return the channel
 * map with additional Symfony ChannelInterface instances.
 *
 * @return array<string, ChannelInterface>
 */
function api_notification_channels() : array {
	api_notification_assert_dependencies();

	$channels = ['email' => new CactiEmailChannel()];

	if (function_exists('api_plugin_hook_function')) {
		$channels = api_plugin_hook_function('notification_channels', $channels);
	}

	return api_notification_validate_channels($channels);
}

/**
 * @return array<string, ChannelInterface>
 */
function api_notification_validate_channels(mixed $channels) : array {
	api_notification_assert_dependencies();

	if (!is_array($channels)) {
		throw new InvalidArgumentException('The notification_channels hook must return an array.');
	}

	foreach ($channels as $name => $channel) {
		if (!is_string($name) || $name === '' || !$channel instanceof ChannelInterface) {
			throw new InvalidArgumentException('Notification channels must be named Symfony ChannelInterface instances.');
		}
	}

	return $channels;
}

/**
 * Normalizes Cacti email values and Symfony recipients for notification use.
 *
 * @return list<RecipientInterface>
 */
function api_notification_recipients(array|string|RecipientInterface $recipients) : array {
	api_notification_assert_dependencies();

	if ($recipients instanceof RecipientInterface) {
		return [$recipients];
	}

	if (is_array($recipients) && array_filter($recipients, fn (mixed $recipient) : bool => $recipient instanceof RecipientInterface) !== []) {
		foreach ($recipients as $recipient) {
			if (!$recipient instanceof RecipientInterface) {
				throw new InvalidArgumentException('Symfony recipients cannot be mixed with email address values.');
			}
		}

		return array_values($recipients);
	}

	$normalized = [];
	$emails     = parse_email_details($recipients);

	foreach ($emails as $email) {
		if (($email['email'] ?? '') !== '') {
			$normalized[] = new CactiRecipient(trim($email['email']), '', trim($email['name'] ?? ''));
		}
	}

	return $normalized;
}

/**
 * Sends a notification through Symfony Notifier and Cacti delivery channels.
 *
 * The options array accepts `importance` plus channel-specific arrays. Email
 * options are: from, cc, bcc, reply_to, html, text, attachments, headers and
 * expand_ids.
 *
 * @param list<string>         $channels
 * @param array<string, mixed> $options
 */
function api_notification_send(string $subject, string $content, array|string|RecipientInterface $recipients,
	array $channels = ['email'], array $options = [], ?NotifierInterface $notifier = null) : bool {
	try {
		api_notification_assert_dependencies();

		$normalized = api_notification_recipients($recipients);

		if ($normalized === []) {
			throw new InvalidArgumentException('At least one notification recipient is required.');
		}

		$notification = new CactiNotification($subject, $content, $channels, $options);

		$notifier ??= new Notifier(api_notification_channels());
		$notifier->send($notification, ...$normalized);

		return true;
	} catch (Throwable $e) {
		$logSubject = str_replace(["\r", "\n"], ' ', $subject);
		$logError   = str_replace(["\r", "\n"], ' ', $e->getMessage());
		cacti_log(sprintf("ERROR: Notification '%s' failed: %s", $logSubject, $logError), false, 'NOTIFIER');

		return false;
	}
}

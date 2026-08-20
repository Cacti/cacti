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

use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Exception\RuntimeException;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\NoRecipient;
use Symfony\Component\Notifier\Recipient\Recipient;
use Symfony\Component\Notifier\Recipient\RecipientInterface;

require_once dirname(__DIR__, 3) . '/lib/api_notification.php';

final class RecordingNotifier implements NotifierInterface {
	public ?Notification $notification = null;

	/** @var list<RecipientInterface> */
	public array $recipients = [];

	public function send(Notification $notification, RecipientInterface ...$recipients) : void {
		$this->notification = $notification;
		$this->recipients   = $recipients;
	}
}

test('Cacti notifications retain content and channel options', function () {
	$notification = new CactiNotification('Disk warning', 'Disk is full', ['email', 'chat'], [
		'email' => ['html' => true],
		'chat'  => 'invalid options',
	]);

	expect($notification->getSubject())->toBe('Disk warning')
		->and($notification->getContent())->toBe('Disk is full')
		->and($notification->getChannels(new Recipient('ops@example.com')))->toBe(['email', 'chat'])
		->and($notification->getOptions('email'))->toBe(['html' => true])
		->and($notification->getOptions('chat'))->toBe([])
		->and($notification->getOptions('missing'))->toBe([]);
});

test('Cacti notifications cannot be mutated after construction', function () {
	$notification = new CactiNotification('Original', 'Body', ['email'], [
		'importance' => Notification::IMPORTANCE_URGENT,
	]);

	expect($notification->getImportance())->toBe(Notification::IMPORTANCE_URGENT)
		->and(fn () => $notification->subject('Changed'))->toThrow(\LogicException::class, 'immutable')
		->and(fn () => $notification->content('Changed'))->toThrow(\LogicException::class, 'immutable')
		->and(fn () => $notification->importance(Notification::IMPORTANCE_LOW))->toThrow(\LogicException::class, 'immutable')
		->and(fn () => $notification->channels(['chat']))->toThrow(\LogicException::class, 'immutable');
});

test('Cacti recipients retain their display name', function () {
	$recipient = new CactiRecipient('ops@example.com', '+15551234567', 'Operations');

	expect($recipient->getEmail())->toBe('ops@example.com')
		->and($recipient->getPhone())->toBe('+15551234567')
		->and($recipient->getName())->toBe('Operations');
});

test('the email channel delegates all Cacti mail options', function () {
	$args    = [];
	$channel = new CactiEmailChannel(function (...$received) use (&$args) : string {
		$args = $received;

		return '';
	});
	$recipient    = new CactiRecipient('ops@example.com', '', 'Operations');
	$notification = new CactiNotification('Alert', '<b>Down</b>', ['email'], [
		'email' => [
			'from'        => 'cacti@example.com',
			'cc'          => 'cc@example.com',
			'bcc'         => 'bcc@example.com',
			'reply_to'    => 'reply@example.com',
			'html'        => true,
			'text'        => 'Down',
			'attachments' => ['report.txt'],
			'headers'     => ['X-Cacti' => 'yes'],
			'expand_ids'  => true,
		],
	]);

	$channel->notify($notification, $recipient);

	expect($channel->supports($notification, $recipient))->toBeTrue()
		->and($channel->supports($notification, new NoRecipient()))->toBeFalse()
		->and($channel->supports($notification, new Recipient('', '+15551234567')))->toBeFalse()
		->and($args)->toBe([
			'cacti@example.com',
			[['email' => 'ops@example.com', 'name' => 'Operations']],
			'cc@example.com',
			'bcc@example.com',
			'reply@example.com',
			'Alert',
			'<b>Down</b>',
			'Down',
			['report.txt'],
			['X-Cacti' => 'yes'],
			true,
			true,
		]);
});

test('the email channel supplies safe defaults for standard notifications', function () {
	$args    = [];
	$channel = new CactiEmailChannel(function (...$received) use (&$args) : string {
		$args = $received;

		return '';
	});
	$notification = (new Notification('Alert', ['email']))->content('Plain text');

	$channel->notify($notification, new Recipient('ops@example.com'));

	expect($args[0])->toBe('')
		->and($args[6])->toBe('Plain text')
		->and($args[7])->toBe('Plain text')
		->and($args[10])->toBeFalse()
		->and($args[11])->toBeFalse();
});

test('the email channel rejects unsupported recipients and transport overrides', function () {
	$channel      = new CactiEmailChannel(fn () : string => '');
	$notification = new Notification('Alert', ['email']);

	expect(fn () => $channel->notify($notification, new NoRecipient()))
		->toThrow(LogicException::class, 'needs an email recipient')
		->and(fn () => $channel->notify($notification, new Recipient('ops@example.com'), 'backup'))
		->toThrow(LogicException::class, 'transport selected in Cacti settings');
});

test('the email channel rejects malformed mailer collection options', function () {
	$channel   = new CactiEmailChannel(fn () : string => '');
	$recipient = new Recipient('ops@example.com');

	$badAttachments = new CactiNotification('Alert', 'Body', ['email'], [
		'email' => ['attachments' => 'report.txt'],
	]);
	$badHeaders = new CactiNotification('Alert', 'Body', ['email'], [
		'email' => ['headers' => 'X-Cacti: yes'],
	]);

	expect(fn () => $channel->notify($badAttachments, $recipient))
		->toThrow(LogicException::class, 'attachments option must be an array')
		->and(fn () => $channel->notify($badHeaders, $recipient))
		->toThrow(LogicException::class, 'headers option must be an array');
});

test('the email channel surfaces sanitized mailer failures', function () {
	$channel = new CactiEmailChannel(fn () : string => '<b>SMTP unavailable</b>');

	expect(fn () => $channel->notify(new Notification('Alert'), new Recipient('ops@example.com')))
		->toThrow(RuntimeException::class, 'SMTP unavailable');
});

test('notification recipients accept Cacti email forms and Symfony recipients', function () {
	$direct = new Recipient('direct@example.com');
	$list   = [new Recipient('one@example.com'), new Recipient('two@example.com')];

	expect(api_notification_recipients($direct))->toBe([$direct])
		->and(api_notification_recipients($list))->toBe($list);

	$emails = api_notification_recipients('Operations <ops@example.com>,admin@example.com');

	expect($emails)->toHaveCount(2)
		->and($emails[0])->toBeInstanceOf(CactiRecipient::class)
		->and($emails[0]->getEmail())->toBe('ops@example.com')
		->and($emails[0]->getName())->toBe('Operations')
		->and($emails[1]->getEmail())->toBe('admin@example.com');

	expect(fn () => api_notification_recipients([$direct, 'mixed@example.com']))
		->toThrow(InvalidArgumentException::class, 'cannot be mixed');
});

test('the public API dispatches through an injected Symfony notifier', function () {
	$notifier = new RecordingNotifier();

	expect(api_notification_send(
		'Disk warning',
		'Disk is full',
		'Operations <ops@example.com>',
		['email'],
		['importance' => Notification::IMPORTANCE_URGENT],
		$notifier
	))->toBeTrue()
		->and($notifier->notification)->toBeInstanceOf(CactiNotification::class)
		->and($notifier->notification?->getImportance())->toBe(Notification::IMPORTANCE_URGENT)
		->and($notifier->recipients)->toHaveCount(1)
		->and($notifier->recipients[0])->toBeInstanceOf(CactiRecipient::class);
});

test('the default channel map exposes Cacti email delivery', function () {
	$channels = api_notification_channels();

	expect($channels)->toHaveKey('email')
		->and($channels['email'])->toBeInstanceOf(CactiEmailChannel::class);
});

test('plugin channel maps must contain named Symfony channels', function () {
	$channel = new CactiEmailChannel(fn () : string => '');

	expect(api_notification_validate_channels(['email' => $channel]))->toBe(['email' => $channel])
		->and(fn () => api_notification_validate_channels(null))
		->toThrow(InvalidArgumentException::class, 'must return an array')
		->and(fn () => api_notification_validate_channels(['invalid']))
		->toThrow(InvalidArgumentException::class, 'must be named');
});

test('the public API fails closed for an unknown default channel', function () {
	expect(api_notification_send('Alert', 'Unknown channel', 'ops@example.com', ['missing']))->toBeFalse();
});

test('the public API fails closed when no recipient is usable', function () {
	expect(api_notification_send('Alert', 'No recipient', [], notifier: new RecordingNotifier()))->toBeFalse();
});

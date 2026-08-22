# Notification API

Cacti provides a plugin-extensible notification API backed by Symfony
Notifier. The API uses Cacti's existing mail settings for the built-in email
channel, including native mail, sendmail, SMTP, and OAuth2 delivery.

This is an internal PHP API for Cacti core and plugins. It is not an HTTP
endpoint.

## Send a notification

Load the API and pass a subject, content, and one or more recipients:

```php
require_once(CACTI_PATH_LIBRARY . '/api_notification.php');

$sent = api_notification_send(
	'Device unavailable',
	'The core router is not responding.',
	'Operations <ops@example.com>'
);
```

The recipient argument accepts the same email forms as Cacti's mailer, a
Symfony `RecipientInterface`, or a list of Symfony recipients. The function
returns `true` when every selected channel accepts every recipient. Delivery
errors are logged to the `NOTIFIER` facility and return `false`.

Email-specific options preserve the capabilities of Cacti's mailer:

```php
use Symfony\Component\Notifier\Notification\Notification;

api_notification_send(
	'Nightly report',
	'<h1>Report complete</h1>',
	'reports@example.com',
	options: [
		'importance' => Notification::IMPORTANCE_LOW,
		'email' => [
			'html'        => true,
			'text'        => 'Report complete',
			'cc'          => 'audit@example.com',
			'reply_to'    => 'noreply@example.com',
			'attachments' => $attachments,
			'headers'     => ['X-Cacti-Source' => 'reports'],
		],
	]
);
```

Supported email options are `from`, `cc`, `bcc`, `reply_to`, `html`, `text`,
`attachments`, `headers`, and `expand_ids`.

## Add a plugin channel

Plugins can register the `notification_channels` hook. The hook receives and
must return an array keyed by channel name. Each value must implement Symfony's
`ChannelInterface`:

```php
function plugin_name_notification_channels(array $channels) : array {
	$channels['chat'] = new PluginChatChannel();

	return $channels;
}
```

Call the API with the registered channel name and an appropriate Symfony
recipient:

```php
api_notification_send(
	'Device unavailable',
	'The core router is not responding.',
	$recipient,
	['email', 'chat']
);
```

Channel implementations control their own credentials and configuration.
Secrets must remain in Cacti settings or another protected runtime store, not
in notification content or source files.

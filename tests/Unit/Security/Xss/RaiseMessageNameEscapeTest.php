<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * GHSA-9737-rqh3-h7fh: names interpolated into raise_message() reached the
 * session message dialog as HTML. raise_message() now escapes a custom message
 * itself, so callers pass plain text and a missed call site cannot inject
 * markup. raise_message_html() is the explicit opt-out for trusted markup.
 */

$root = dirname(__DIR__, 4);

require_once $root . '/tests/Helpers/SourceSlice.php';

if (!defined('MESSAGE_LEVEL_NONE')) {
	define('MESSAGE_LEVEL_NONE', 0);
	define('MESSAGE_LEVEL_INFO', 1);
	define('MESSAGE_LEVEL_WARN', 2);
	define('MESSAGE_LEVEL_ERROR', 3);
}

foreach (['raise_message', 'raise_message_html', 'get_message_level'] as $function) {
	if (!function_exists($function)) {
		cacti_fixture_source_function($root . '/lib/functions.php', $function, '');
	}
}

if (!function_exists('html_escape')) {
	cacti_fixture_source_function($root . '/lib/html.php', 'html_escape', '');
}

/* Every raise_message() call in the tree with the source of its message argument. */
function raise_message_9737_calls(string $root, string $function = 'raise_message') : array {
	$calls    = [];
	$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));

	foreach ($iterator as $file) {
		$path = $file->getPathname();
		$rel  = substr($path, strlen($root) + 1);

		if (substr($path, -4) !== '.php' || preg_match('#^(include/vendor|tests|plugins|\.git)/#', $rel)) {
			continue;
		}

		$tokens = token_get_all(file_get_contents($path));
		$count  = count($tokens);

		for ($i = 0; $i < $count; $i++) {
			if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_STRING || $tokens[$i][1] !== $function) {
				continue;
			}

			$j = $i - 1;
			while ($j >= 0 && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
				$j--;
			}

			if ($j >= 0 && is_array($tokens[$j]) && $tokens[$j][0] === T_FUNCTION) {
				continue;
			}

			$k = $i + 1;
			while ($k < $count && is_array($tokens[$k]) && $tokens[$k][0] === T_WHITESPACE) {
				$k++;
			}

			if ($k >= $count || $tokens[$k] !== '(') {
				continue;
			}

			$depth   = 0;
			$arg     = 0;
			$id      = '';
			$message = '';

			for ($m = $k; $m < $count; $m++) {
				$text = is_array($tokens[$m]) ? $tokens[$m][1] : $tokens[$m];

				if ($text === '(' || $text === '[' || $text === '{') {
					$depth++;
				} elseif ($text === ')' || $text === ']' || $text === '}') {
					$depth--;

					if ($depth === 0) {
						break;
					}
				} elseif ($text === ',' && $depth === 1) {
					$arg++;

					continue;
				}

				if ($arg === 0 && $depth >= 1 && $text !== '(') {
					$id .= $text;
				} elseif ($arg === 1) {
					$message .= $text;
				}
			}

			$calls[] = ['where' => $rel . ':' . $tokens[$i][2], 'file' => $rel, 'id' => trim($id), 'message' => trim($message)];
		}
	}

	return $calls;
}

beforeEach(function () {
	global $messages, $no_http_headers;

	/* Store into $_SESSION directly instead of opening a PHP session. */
	$no_http_headers = true;
	$messages        = [1 =>['message' => 'Save <b>Successful</b>.', 'level' => MESSAGE_LEVEL_INFO]];
	$_SESSION = [];
});

test('raise_message escapes a custom message', function () {
	raise_message('device', 'Device <img src=x onerror=alert(1)> & \'quoted\'', MESSAGE_LEVEL_INFO);

	expect($_SESSION['sess_messages']['device']['message'])
		->toBe('Device &lt;img src=x onerror=alert(1)&gt; &amp; &apos;quoted&apos;')
		->and($_SESSION['sess_messages']['device']['level'])->toBe(MESSAGE_LEVEL_INFO);
});

test('raise_message keeps a message with invalid UTF-8 instead of blanking it', function () {
	raise_message('device', "Device \xff <b>", MESSAGE_LEVEL_INFO);

	expect($_SESSION['sess_messages']['device']['message'])->toBe('Device ? &lt;b&gt;');
});

test('raise_message_html keeps trusted markup unchanged', function () {
	raise_message_html('report', 'Created <i>' . html_escape('<b>x</b>') . '</i><br>', MESSAGE_LEVEL_INFO);

	expect($_SESSION['sess_messages']['report']['message'])
		->toBe('Created <i>&lt;b&gt;x&lt;/b&gt;</i><br>');
});

test('raise_message leaves predefined messages unchanged', function () {
	raise_message(1);

	expect($_SESSION['sess_messages'][1])
		->toBe(['message' => 'Save <b>Successful</b>.', 'level' => MESSAGE_LEVEL_INFO]);
});

test('no raise_message call pre-escapes its message', function () use ($root) {
	$calls = raise_message_9737_calls($root);

	expect(count($calls))->toBeGreaterThan(300);

	$preescaped = array_values(array_filter($calls, function ($call) {
		return preg_match('/\b(__esc(_n|_x|_xn)?|html_escape(_request_var)?|htmlspecialchars)\s*\(/', $call['message']) === 1;
	}));

	expect($preescaped)->toBe([]);
});

test('no raise_message call passes markup that would now render as text', function () use ($root) {
	$markup = array_values(array_filter(raise_message_9737_calls($root), function ($call) {
		return preg_match('/<\/?[a-z][a-z0-9]*\b|&nbsp;/i', $call['message']) === 1;
	}));

	expect($markup)->toBe([]);
});

/* raise_message_html() trusts its caller, so each caller is reviewed and listed here. */
test('raise_message_html callers are the reviewed set', function () use ($root) {
	$callers = array_map(function ($call) {
		return $call['file'] . ' ' . $call['id'];
	}, raise_message_9737_calls($root, 'raise_message_html'));

	sort($callers);

	expect($callers)->toBe([
		"data_source_profiles.php 'heartbeat_change'",
		'lib/functions.php $message_id',
		"lib/plugins.php 'dependency_check'",
		"lib/reports.php 'report_send'",
	]);

	expect(substr_count(file_get_contents($root . '/lib/reports.php'), 'html_escape(get_graph_title($local_graph_id))'))->toBe(3)
		->and(file_get_contents($root . '/lib/plugins.php'))->toContain('$message = html_escape($message) . ');
});

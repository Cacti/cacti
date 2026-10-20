<?php
declare(strict_types=1);
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

$root = dirname(__DIR__, 2);
$_SESSION = [];
$config = [
	'is_web' => false,
	'config_options_array' => ['i18n_language_support' => '0'],
];
require $root . '/include/global_constants.php';
require $root . '/lib/functions.php';
require $root . '/lib/html.php';
require $root . '/lib/html_form.php';
require $root . '/lib/headers_secure.php';
require $root . '/include/global_languages.php';

$mode = $argv[1];
$action = $argv[2];
$ids = [];
ob_start();
try {
	if ($mode === 'edit') {
		draw_edit_form([
			'config' => ['post_to' => $action] + (isset($argv[3]) ? json_decode($argv[3], true, 512, JSON_THROW_ON_ERROR) : []),
			'fields' => ['id' => ['method' => 'hidden', 'value' => '7']],
		]);
		print '</form>';
	} elseif ($mode === 'ajax') {
		form_start($action, 'edit_form', true);
		form_end();
	} elseif ($mode === 'plain') {
		for ($i = 0; $i < 2; $i++) {
			form_start($action);
			$ids[] = $form_id;
			form_end(false);
		}
	} else {
		throw new InvalidArgumentException('Unknown fixture mode');
	}
	$html = ob_get_contents();
} finally {
	ob_end_clean();
}

print json_encode([
	'html' => $html,
	'action' => $form_action ?? null,
	'ids' => $ids,
], JSON_THROW_ON_ERROR);

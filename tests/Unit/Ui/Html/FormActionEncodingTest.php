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

require_once dirname(__DIR__, 3) . '/Helpers/CactiStubs.php';
require_once CACTI_PATH_INCLUDE . '/global.php';

dataset('form action URLs', [
	'plain'       => ['graphs.php'],
	'query'       => ["/cacti/graphs.php?filter=Bob's switch&rows=20#results"],
	'entities'    => ['/cacti/graphs.php?filter=&copy;&quot;&amp;'],
	'Unicode'     => ["/cacti/graphs.php?filter=東京 \"switch\"\u{2028}next\u{2029}last"],
	'script text' => ["/cacti/graphs.php?filter=</script><p>notes</p>\\'"],
	'empty'       => [''],
	'zero'        => ['0'],
]);

beforeEach(function () {
	$this->savedFormGlobals = [];

	foreach (['form_id', 'form_action', '_SESSION'] as $name) {
		$this->savedFormGlobals[$name] = [
			'present' => array_key_exists($name, $GLOBALS),
			'value'   => $GLOBALS[$name] ?? null,
		];
	}
});

afterEach(function () {
	foreach ($this->savedFormGlobals as $name => $saved) {
		if ($saved['present']) {
			$GLOBALS[$name] = $saved['value'];
		} else {
			unset($GLOBALS[$name]);
		}
	}
});

it('preserves form action URLs in HTML and AJAX initialization', function (string $action) {
	ob_start();

	try {
		form_start($action, 'edit_form', true);
		form_end();
		$html = ob_get_contents();
	} finally {
		ob_end_clean();
	}

	$document = new DOMDocument();
	$previous = libxml_use_internal_errors(true);

	try {
		expect($document->loadHTML('<?xml encoding="UTF-8">' . $html))->toBeTrue();
	} finally {
		libxml_clear_errors();
		libxml_use_internal_errors($previous);
	}

	$forms = $document->getElementsByTagName('form');
	expect($forms->length)->toBe(1);
	$form = $forms->item(0);
	expect($form->getAttribute('action'))->toBe($action)
		->and($form->getAttribute('id'))->toBe('edit_form')
		->and($form->getAttribute('name'))->toBe('edit_form')
		->and($form->getAttribute('method'))->toBe('post')
		->and($form->getAttribute('enctype'))->toBe('multipart/form-data')
		->and($GLOBALS['form_action'])->toBe($action);

	$scripts = $document->getElementsByTagName('script');
	expect($scripts->length)->toBe(1);
	$script = $scripts->item(0)->textContent;
	expect(preg_match('/formValidate\(\'#edit_form\', (.*)\);/', $script, $matches))->toBe(1);
	expect(json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR))->toBe($action)
		->and($document->getElementsByTagName('p')->length)->toBe(0);
})->with('form action URLs');

it('preserves configured edit form action URLs', function (string $action) {
	ob_start();

	try {
		draw_edit_form([
			'config' => ['post_to' => $action],
			'fields' => ['id' => ['method' => 'hidden', 'value' => 7]],
		]);
		print '</form>';
		$html = ob_get_contents();
	} finally {
		ob_end_clean();
	}

	$document = new DOMDocument();
	$previous = libxml_use_internal_errors(true);

	try {
		expect($document->loadHTML('<?xml encoding="UTF-8">' . $html))->toBeTrue();
	} finally {
		libxml_clear_errors();
		libxml_use_internal_errors($previous);
	}

	$forms = $document->getElementsByTagName('form');
	expect($forms->length)->toBe(1)
		->and($forms->item(0)->getAttribute('action'))->toBe($action)
		->and($forms->item(0)->getAttribute('method'))->toBe('post')
		->and($document->getElementsByTagName('input')->item(0)->getAttribute('value'))->toBe('7')
		->and($document->getElementsByTagName('script')->length)->toBe(0);
})->with('form action URLs');

it('preserves generated form IDs and the non-AJAX form mode', function () {
	$ids = [];

	for ($i = 0; $i < 2; $i++) {
		ob_start();

		try {
			form_start('edit.php');
			$ids[] = $GLOBALS['form_id'];
			form_end(false);
			$html = ob_get_contents();
		} finally {
			ob_end_clean();
		}

		expect($html)->not->toContain('<script')
			->not->toContain('multipart/form-data')
			->toContain('</form>');
	}

	expect($ids[0])->toMatch('/^form[0-9]+$/')
		->and($ids[1])->toMatch('/^form[0-9]+$/')
		->not->toBe($ids[0]);
});

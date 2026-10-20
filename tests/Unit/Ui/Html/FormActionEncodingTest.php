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

require_once dirname(__DIR__, 3) . '/Helpers/IsolatedProbe.php';

dataset('form action URLs', [
	'plain' => ['graphs.php'],
	'query' => ["/cacti/graphs.php?filter=Bob's switch&rows=20#results"],
	'entities' => ['/cacti/graphs.php?filter=&copy;&quot;&amp;'],
	'Unicode' => ["/cacti/graphs.php?filter=東京 \"switch\"\u{2028}next\u{2029}last"],
	'script text' => ["/cacti/graphs.php?filter=</script><p>notes</p>\\'"],
	'empty' => [''],
	'zero' => ['0'],
]);

it('preserves form action URLs in HTML and AJAX submission', function (string $action) {
	$result = cacti_test_isolated_probe(
		dirname(__DIR__, 3) . '/fixtures/form_action_render.php',
		['ajax', $action]
	);
	$document = new DOMDocument();
	$previous = libxml_use_internal_errors(true);

	try {
		expect($document->loadHTML('<?xml encoding="UTF-8">' . $result['html']))->toBeTrue();
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
		->and($result['action'])->toBe($action);

	$scripts = $document->getElementsByTagName('script');
	expect($scripts->length)->toBe(1);
	$script = $scripts->item(0)->textContent;
	expect(preg_match("/strURL  = '(.*)';/", $script, $matches))->toBe(1);
	expect(json_decode('"' . $matches[1] . '"', true, 512, JSON_THROW_ON_ERROR))->toBe($action)
		->and($document->getElementsByTagName('p')->length)->toBe(0)
		->and($scripts->item(0)->getAttribute('nonce'))->not->toBe('');
})->with('form action URLs');

it('preserves configured edit form action URLs', function (string $action) {
	$result = cacti_test_isolated_probe(
		dirname(__DIR__, 3) . '/fixtures/form_action_render.php',
		['edit', $action]
	);
	$document = new DOMDocument();
	$previous = libxml_use_internal_errors(true);

	try {
		expect($document->loadHTML('<?xml encoding="UTF-8">' . $result['html']))->toBeTrue();
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
	$result = cacti_test_isolated_probe(
		dirname(__DIR__, 3) . '/fixtures/form_action_render.php',
		['plain', 'edit.php']
	);
	expect($result['html'])->not->toContain('<script')
		->not->toContain('multipart/form-data')
		->toContain('</form>')
		->and($result['ids'][0])->toMatch('/^form[0-9]+$/')
		->and($result['ids'][1])->toMatch('/^form[0-9]+$/')
		->not->toBe($result['ids'][0]);
});

it('keeps configured form attributes as literal values', function (string $name, string $enctype) {
	$result = cacti_test_isolated_probe(
		dirname(__DIR__, 3) . '/fixtures/form_action_render.php',
		['edit', 'edit.php', json_encode(['form_name' => $name, 'enctype' => $enctype], JSON_THROW_ON_ERROR)]
	);
	$document = new DOMDocument();
	expect($document->loadHTML($result['html']))->toBeTrue();
	$form = $document->getElementsByTagName('form')->item(0);
	expect($form->getAttribute('name'))->toBe($name)
		->and($form->getAttribute('enctype'))->toBe($enctype)
		->and($form->attributes->length)->toBe(6);
})->with([
	'normal' => ['edit_form', 'multipart/form-data'],
	'name quotes and entities' => ["Bob's &quot;form", 'multipart/form-data'],
	'enctype quoting' => ['edit_form', "multipart/form-data' data-extra='value"],
]);

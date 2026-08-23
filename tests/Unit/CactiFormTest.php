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

require_once CACTI_PATH_LIBRARY . '/CactiForm.php';

function cacti_form_test_fields() : array {
	return [
		'name' => [
			'method'        => 'textbox',
			'friendly_name' => 'Name',
			'value'         => '|arg1:name|',
		],
	];
}

test('CactiForm builds the legacy renderer contract immutably', function () {
	$form = (new CactiForm(cacti_form_test_fields()))
		->withoutFormTag()
		->postTo('sites.php')
		->named('site')
		->multipart();

	expect($form->definition())->toBe([
		'config' => [
			'no_form_tag' => true,
			'post_to'     => 'sites.php',
			'form_name'   => 'site',
			'enctype'     => 'multipart/form-data',
		],
		'fields' => cacti_form_test_fields(),
	]);
});

test('CactiForm hydrates values through an injected Cacti adapter', function () {
	$hydrator = function (array $fields, array $values) : array {
		$fields['name']['value'] = $values['name'];

		return $fields;
	};

	$original = new CactiForm(cacti_form_test_fields(), hydrator: $hydrator);
	$hydrated = $original->withValues(['name' => 'Datacenter']);

	expect($original->definition()['fields']['name']['value'])->toBe('|arg1:name|')
		->and($hydrated->definition()['fields']['name']['value'])->toBe('Datacenter');
});

test('CactiForm delegates rendering through its Cacti contract', function () {
	$rendered = null;
	$form     = new CactiForm(cacti_form_test_fields(), function (array $definition) use (&$rendered) : void {
		$rendered = $definition;
	});

	$form->withoutFormTag()->render();

	expect($rendered)->toBe([
		'config' => ['no_form_tag' => true],
		'fields' => cacti_form_test_fields(),
	]);
});

test('CactiForm rejects malformed definitions and empty identifiers', function () {
	expect(fn () => new CactiForm(['name' => []]))
		->toThrow(InvalidArgumentException::class, "field 'name' must define a method")
		->and(fn () => new CactiForm([0 => ['method' => 'textbox']]))
		->toThrow(InvalidArgumentException::class, 'non-empty string names')
		->and(fn () => (new CactiForm(cacti_form_test_fields()))->postTo(''))
		->toThrow(InvalidArgumentException::class, 'action cannot be empty')
		->and(fn () => (new CactiForm(cacti_form_test_fields()))->named(''))
		->toThrow(InvalidArgumentException::class, 'name cannot be empty');
});

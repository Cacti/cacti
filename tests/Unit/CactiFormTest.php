<?php
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

require_once dirname(__DIR__, 2) . '/include/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/lib/CactiForm.php';

test('CactiForm creates injectable Symfony form builders', function () {
	$form = CactiForm::createNamedBuilder('settings')
		->add('name')
		->getForm();

	expect($form->getName())->toBe('settings');
	expect((new CactiForm())->formFactory())->toBeInstanceOf(Symfony\Component\Form\FormFactoryInterface::class);
});

test('CactiForm retains an injected form factory', function () {
	$injected = Symfony\Component\Form\Forms::createFormFactory();

	expect((new CactiForm($injected))->formFactory())->toBe($injected);
});

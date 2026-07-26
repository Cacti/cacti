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
		->add('name', Symfony\Component\Form\Extension\Core\Type\TextType::class)
		->getForm();

	expect($form->getName())->toBe('settings');
	expect((new CactiForm())->formFactory())->toBeInstanceOf(Symfony\Component\Form\FormFactoryInterface::class);
});

test('CactiForm retains an injected form factory', function () {
	$injected = Symfony\Component\Form\Forms::createFormFactory();

	expect((new CactiForm($injected))->formFactory())->toBe($injected);
});

test('CactiForm exposes the shared factory statically', function () {
	expect(CactiForm::factory())->toBeInstanceOf(Symfony\Component\Form\FormFactoryInterface::class);
});

test('CactiForm creates an unnamed builder from the default form type', function () {
	$builder = CactiForm::createBuilder();

	expect($builder)->toBeInstanceOf(Symfony\Component\Form\FormBuilderInterface::class);
});

test('CactiForm instance builder honours an injected factory', function () {
	$injected = Symfony\Component\Form\Forms::createFormFactory();
	$form     = new CactiForm($injected);

	expect($form->builder())->toBeInstanceOf(Symfony\Component\Form\FormBuilderInterface::class)
		->and($form->namedBuilder('inner'))->toBeInstanceOf(Symfony\Component\Form\FormBuilderInterface::class);
});

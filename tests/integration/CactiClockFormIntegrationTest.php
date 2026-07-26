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

require_once dirname(__DIR__, 2) . '/lib/CactiClock.php';
require_once dirname(__DIR__, 2) . '/lib/CactiForm.php';

use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormFactoryInterface;

it('reads real wall-clock time through the default NativeClock', function () {
	$before = time();
	$now    = CactiClock::currentTime();
	$after  = time();

	expect($now)->toBeInstanceOf(DateTimeImmutable::class)
		->and($now->getTimestamp())->toBeGreaterThanOrEqual($before)
		->and($now->getTimestamp())->toBeLessThanOrEqual($after + 1);

	// sleepFor(0) crosses the real NativeClock sleep boundary without stalling.
	CactiClock::sleepFor(0);
});

it('builds and submits a real Symfony form through the shared factory', function () {
	$form = CactiForm::createBuilder()
		->add('device', TextType::class)
		->getForm();

	$form->submit(['device' => 'router-1']);

	expect($form->isSubmitted())->toBeTrue()
		->and($form->getData())->toBe(['device' => 'router-1']);

	expect(CactiForm::factory())->toBeInstanceOf(FormFactoryInterface::class)
		->and(CactiForm::factory())->toBe(CactiForm::factory());
});

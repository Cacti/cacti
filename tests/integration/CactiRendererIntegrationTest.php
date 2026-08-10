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

require_once dirname(__DIR__, 2) . '/lib/renderer.php';

if (!defined('CACTI_PATH_INCLUDE')) {
	define('CACTI_PATH_INCLUDE', dirname(__DIR__, 2) . '/include');
}

beforeEach(function () {
	$this->dir = sys_get_temp_dir() . '/cacti-renderer-int-' . getmypid() . '-' . bin2hex(random_bytes(8));
	mkdir($this->dir, 0700, true);
	file_put_contents($this->dir . '/hello.php', 'Hello <?php print $name; ?>');
});

afterEach(function () {
	foreach (glob($this->dir . '/*') ?: [] as $file) {
		unlink($file);
	}

	if (is_dir($this->dir)) {
		rmdir($this->dir);
	}
});

it('renders a real template through the cacti_render helper', function () {
	expect(cacti_render('hello.php', ['name' => 'Cacti'], $this->dir))->toBe('Hello Cacti');
});

it('renders a real explicit file through the cacti_render_file helper', function () {
	expect(cacti_render_file($this->dir . '/hello.php', ['name' => 'World'], $this->dir))->toBe('Hello World');
});

it('caches one renderer instance per resolved template path', function () {
	$first  = cacti_renderer($this->dir);
	$second = cacti_renderer($this->dir);

	expect($first)->toBeInstanceOf(CactiRenderer::class)
		->and($first)->toBe($second);
});

it('defaults to the bundled include/views template path', function () {
	// Exercises the null-path branch that resolves CACTI_PATH_INCLUDE/views and
	// renders the side-effect-free index template shipped with the abstraction.
	$default = cacti_renderer();

	expect($default)->toBeInstanceOf(CactiRenderer::class)
		->and(cacti_render('index.php'))->toBeString();
});

it('rejects a non-existent path through cacti_renderer', function () {
	cacti_renderer($this->dir . '/missing-subtree');
})->throws(InvalidArgumentException::class);

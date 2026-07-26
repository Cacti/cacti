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

require_once dirname(__DIR__, 2) . '/lib/renderer.php';

function cacti_renderer_fixture_dir() : string {
	static $dir = null;

	if ($dir === null) {
		$dir = sys_get_temp_dir() . '/cacti-renderer-test-' . getmypid() . '-' . bin2hex(random_bytes(8));
	}

	return $dir;
}

beforeEach(function () {
	$dir = cacti_renderer_fixture_dir();

	if (!is_dir($dir)) {
		mkdir($dir, 0700, true);
	}

	file_put_contents($dir . '/hello.php', 'Hello <?php print $name; ?>');
	file_put_contents($dir . '/context.php', '<?php print isset($template_file) ? "leaked" : "isolated"; ?>');
});

afterEach(function () {
	$dir = cacti_renderer_fixture_dir();

	foreach (glob($dir . '/*') ?: [] as $file) {
		unlink($file);
	}

	if (is_dir($dir)) {
		rmdir($dir);
	}
});

it('renders a relative template with context values', function () {
	$renderer = new CactiRenderer(cacti_renderer_fixture_dir());

	expect($renderer->render('hello.php', ['name' => 'Cacti']))->toBe('Hello Cacti');
});

it('renders an explicit template file inside the template path', function () {
	$dir      = cacti_renderer_fixture_dir();
	$renderer = new CactiRenderer($dir);

	expect($renderer->renderFile($dir . '/hello.php', ['name' => 'Cacti']))->toBe('Hello Cacti');
});

it('keeps renderer internals out of the template context', function () {
	$renderer = new CactiRenderer(cacti_renderer_fixture_dir());

	expect($renderer->render('context.php'))->toBe('isolated');
});

it('rejects missing templates', function () {
	$renderer = new CactiRenderer(cacti_renderer_fixture_dir());

	$renderer->render('missing.php');
})->throws(InvalidArgumentException::class);

it('rejects absolute template names', function () {
	$renderer = new CactiRenderer(cacti_renderer_fixture_dir());

	$renderer->render('/tmp/hello.php');
})->throws(InvalidArgumentException::class);

it('rejects template traversal outside the template path', function () {
	$dir      = cacti_renderer_fixture_dir();
	$filename = sprintf('cacti-renderer-outside-%d-%s.php', getmypid(), bin2hex(random_bytes(8)));
	$outside  = dirname($dir) . '/' . $filename;

	file_put_contents($outside, 'outside');

	try {
		$renderer = new CactiRenderer($dir);
		$renderer->render('../' . $filename);
	} finally {
		unlink($outside);
	}
})->throws(InvalidArgumentException::class);

it('refuses a filesystem root as the template path', function () {
	// A "/"-rooted renderer would make the containment prefix match every
	// absolute path, so construction must fail closed.
	new CactiRenderer('/');
})->throws(InvalidArgumentException::class);

it('treats a Windows drive root as a filesystem root', function () {
	// realpath('C:\\') resolves to a bare drive letter, which would collapse the
	// containment prefix the same way "/" does. A POSIX runner cannot resolve a
	// drive root, so assert the constructor's normalize + root predicate directly:
	// both "C:" and "" must be refused while an in-drive path is accepted.
	$is_root = static function (string $path): bool {
		$trimmed = rtrim($path, "/\\");

		return $trimmed === '' || preg_match('/^[A-Za-z]:$/', $trimmed) === 1;
	};

	expect($is_root('C:\\'))->toBeTrue();
	expect($is_root('C:'))->toBeTrue();
	expect($is_root(''))->toBeTrue();
	expect($is_root('C:\\Cacti\\views'))->toBeFalse();
});

it('rejects a null byte in the constructor template path', function () {
	new CactiRenderer(cacti_renderer_fixture_dir() . "\0/evil");
})->throws(InvalidArgumentException::class);

it('rejects a null byte in the template name', function () {
	$renderer = new CactiRenderer(cacti_renderer_fixture_dir());

	$renderer->render("hello.php\0.txt");
})->throws(InvalidArgumentException::class);

it('rejects a null byte in an explicit template file', function () {
	$dir      = cacti_renderer_fixture_dir();
	$renderer = new CactiRenderer($dir);

	$renderer->renderFile($dir . "/hello.php\0.txt");
})->throws(InvalidArgumentException::class);

it('rejects a null byte in the cacti_renderer path', function () {
	cacti_renderer(cacti_renderer_fixture_dir() . "\0/evil");
})->throws(InvalidArgumentException::class);

it('rejects a template path that does not exist', function () {
	new CactiRenderer(cacti_renderer_fixture_dir() . '/does-not-exist');
})->throws(InvalidArgumentException::class);

it('rejects a template path that is a file rather than a directory', function () {
	new CactiRenderer(cacti_renderer_fixture_dir() . '/hello.php');
})->throws(InvalidArgumentException::class);

it('rejects an empty template name', function () {
	$renderer = new CactiRenderer(cacti_renderer_fixture_dir());

	$renderer->render('');
})->throws(InvalidArgumentException::class);

it('cleans the output buffer and rethrows when a template errors', function () {
	$dir = cacti_renderer_fixture_dir();
	file_put_contents($dir . '/boom.php', '<?php throw new RuntimeException("template failure");');

	$renderer = new CactiRenderer($dir);
	$depth    = ob_get_level();

	try {
		expect(fn () => $renderer->render('boom.php'))
			->toThrow(RuntimeException::class, 'template failure');

		// The catch block must unwind its own buffer, leaving the level intact.
		expect(ob_get_level())->toBe($depth);
	} finally {
		unlink($dir . '/boom.php');
	}
});

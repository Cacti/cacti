<?php

require_once dirname(__DIR__, 2) . '/lib/renderer.php';

function cacti_renderer_fixture_dir() : string {
	return sys_get_temp_dir() . '/cacti-renderer-test-' . getmypid();
}

beforeEach(function () {
	$dir = cacti_renderer_fixture_dir();

	if (!is_dir($dir)) {
		mkdir($dir, 0777, true);
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
	$dir = cacti_renderer_fixture_dir();
	$filename = sprintf('cacti-renderer-outside-%d-%s.php', getmypid(), bin2hex(random_bytes(8)));
	$outside = dirname($dir) . '/' . $filename;

	file_put_contents($outside, 'outside');

	try {
		$renderer = new CactiRenderer($dir);
		$renderer->render('../' . $filename);
	} finally {
		unlink($outside);
	}
})->throws(InvalidArgumentException::class);

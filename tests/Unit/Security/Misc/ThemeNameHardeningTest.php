<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * The selected theme becomes part of file paths and of page output. It can
 * come from the session and from a user's stored setting. These tests pin the
 * check on the name, the values the profile page stores, and the places the
 * name is used.
 */

$root = dirname(__DIR__, 4);

beforeEach(function () {
	$this->savedConfig  = $GLOBALS['config'];
	$this->savedSession = $_SESSION ?? null;
	$this->savedThemes  = $GLOBALS['themes'] ?? null;
});

afterEach(function () {
	$GLOBALS['config'] = $this->savedConfig;
	$GLOBALS['themes'] = $this->savedThemes;

	if ($this->savedSession === null) {
		unset($_SESSION);
	} else {
		$_SESSION = $this->savedSession;
	}
});

test('every shipped theme directory name is accepted', function () use ($root) {
	$names = [];

	foreach (glob($root . '/include/themes/*/main.css') as $file) {
		$names[] = basename(dirname($file));
	}

	expect(count($names))->toBeGreaterThan(5);

	foreach ($names as $name) {
		expect(cacti_is_theme_name($name))->toBeTrue("$name should be accepted");
	}
});

test('a name is rejected unless it is a plain directory name with a main.css file', function (mixed $value) {
	expect(cacti_is_theme_name($value))->toBeFalse();
})->with([
	'equivalent spelling'    => ['modern/../modern'],
	'current directory'      => ['./modern'],
	'detour out of the tree' => ['modern/../../themes/modern'],
	'backslash separator'    => ['modern\\..\\modern'],
	'leading separator'      => ['/modern'],
	'trailing separator'     => ['modern/'],
	'nested directory'       => ['midwinter/css'],
	'quote'                  => ["modern'"],
	'markup'                 => ['<b>modern'],
	'trailing newline'       => ["modern\n"],
	'leading dot'            => ['.modern'],
	'leading hyphen'         => ['-modern'],
	'flag value'             => ['1'],
	'empty string'           => [''],
	'not installed'          => ['absent'],
	'array'                  => [['modern']],
	'null'                   => [null],
	'integer'                => [7],
]);

test('is_valid_theme replaces a name that fails the check with the configured theme', function (mixed $value) {
	$GLOBALS['config']['config_options_array']['selected_theme'] = 'modern';

	$GLOBALS['themes'] = ['modern' => 'Modern'];

	$theme = $value;

	expect(is_valid_theme($theme))->toBeTrue()
		->and($theme)->toBe('modern');
})->with([
	'equivalent spelling' => ['dark/../dark'],
	'flag value'          => ['1'],
	'null'                => [null],
]);

test('is_valid_theme keeps an installed theme name', function () {
	$theme = 'dark';

	expect(is_valid_theme($theme))->toBeTrue()
		->and($theme)->toBe('dark');
});

test('a session theme that fails the check is not returned', function () {
	$GLOBALS['config']['config_options_array']['selected_theme'] = 'modern';

	$GLOBALS['themes'] = ['modern' => 'Modern'];

	$_SESSION = ['selected_theme' => 'dark/../dark'];

	expect(get_selected_theme())->toBe('modern');
});

test('a configured theme that fails the check is not returned without a user session', function () {
	$GLOBALS['config']['config_options_array']['selected_theme'] = 'dark/../dark';

	$GLOBALS['themes'] = ['modern' => 'Modern'];

	$_SESSION = [];

	expect(get_selected_theme())->toBe('modern');
});

test('the newtheme request flag only clears the cached theme', function () use ($root) {
	$global = file_get_contents($root . '/include/global.php');

	expect($global)->toMatch("/if \\(isrv\\('newtheme'\\)\\) \\{\\s+kill_session_var\\('selected_theme'\\);\\s+\\}/")
		->and($global)->not->toContain("gnrv('newtheme')")
		->and($global)->not->toContain('$newtheme');
});

test('a fixed dropdown accepts only its declared keys', function () use ($root) {
	require_once $root . '/lib/user_setting_validation.php';

	$field = ['method' => 'drop_array', 'array' => ['modern' => 'Modern', 5 => 'Five']];

	foreach (['modern', '5', 5] as $value) {
		expect(cacti_validate_user_setting_value('selected_theme', $value, $field))->toBe((string) $value);
	}

	foreach (['Modern', '05', ['modern'], null, 5.0, true] as $value) {
		expect(cacti_validate_user_setting_value('selected_theme', $value, $field))->toBeFalse();
	}
});

test('the theme name is encoded where pages print it into script', function () use ($root) {
	$session = file_get_contents($root . '/include/global_session.php');
	$profile = file_get_contents($root . '/auth_profile.php');

	expect($session)->toContain('var theme=<?php print cacti_js_encode(get_selected_theme()); ?>;')
		->and($profile)->toContain('var currentTheme = <?php print cacti_js_encode(get_selected_theme()); ?>;')
		->and($profile)->toContain("var currentLang = <?php print cacti_js_encode((string) read_config_option('user_language')); ?>;")
		->and($profile)->toContain("var authMethod = <?php print cacti_js_encode((string) read_config_option('auth_method')); ?>;")
		->and($session . $profile)->not->toContain("'<?php print get_selected_theme(); ?>'");
});

test('cacti_js_encode keeps a hostile value inside its string', function () {
	$encoded = cacti_js_encode("x';</script><b>");

	expect($encoded)->not->toContain('</script>')
		->and($encoded)->not->toContain('<b>')
		->and(json_decode($encoded))->toBe("x';</script><b>");
});

test('the graph cache key separates themes and color modes', function () use ($root) {
	$boost = file_get_contents($root . '/lib/boost.php');

	expect($boost)->toContain("'theme'          => cacti_validate_theme((string) (\$graph_data_array['graph_theme'] ?? get_selected_theme())),")
		->and($boost)->toContain("'color_mode'     => \$color_mode,")
		->and($boost)->toContain("in_array(\$_COOKIE['CactiColorMode'], ['dark', 'light', 'dark-dimmed'], true)")
		->and($boost)->not->toContain("'theme'          => get_selected_theme(),");
});

test('a validated theme name never leaves the themes directory', function () {
	$GLOBALS['config']['config_options_array']['selected_theme'] = 'modern';

	expect(cacti_validate_theme('../themes/dark'))->toBe('dark')
		->and(cacti_validate_theme('../../lib'))->toBe('modern')
		->and(cacti_validate_theme('dark'))->toBe('dark');
});

test('the theme stylesheet is linked once and the deferred link uses onload', function () use ($root) {
	$html      = file_get_contents($root . '/lib/html.php');
	$functions = file_get_contents($root . '/lib/functions.php');

	expect($html)->not->toContain("theme: \$selectedTheme, file: 'main.css'")
		->and(substr_count($html, "get_md5_include_css('include/themes/' . \$selectedTheme . '/main.css')"))->toBe(1)
		->and($functions)->toContain('onload="this.media=')
		->and($functions)->not->toContain('online="this.media=');
});

test('the Midwinter hash script runs from the command line only and calls defined functions', function () use ($root) {
	$source = file_get_contents($root . '/include/themes/midwinter/update_hash.php');
	$guard  = strpos($source, "if (PHP_SAPI !== 'cli') {");
	$work   = strpos($source, 'function file_search(');

	expect($guard)->not->toBeFalse()
		->and($guard)->toBeLessThan($work)
		->and($source)->toMatch("/if \\(PHP_SAPI !== 'cli'\\) \\{\\s+http_response_code\\(404\\);\\s+exit;/")
		->and($source)->not->toContain('cacti_strtolower(')
		->and($source)->not->toContain('function update_hash(');
});

test('every theme directory has an index guard', function () use ($root) {
	$missing  = [];
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($root . '/include/themes', FilesystemIterator::SKIP_DOTS),
		RecursiveIteratorIterator::SELF_FIRST
	);

	foreach ($iterator as $entry) {
		if ($entry->isDir() && !is_file($entry->getPathname() . '/index.php')) {
			$missing[] = substr($entry->getPathname(), strlen($root) + 1);
		}
	}

	expect($missing)->toBe([]);
});

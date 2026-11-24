<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * The selected theme becomes part of file paths and of page output, and a
 * user can store it for their own account. These tests pin the checks on the
 * name, on the values the profile page stores, and on the places the name is
 * used.
 */

$root = dirname(__DIR__, 4);

function themeNameTestTree(): string {
	$base = sys_get_temp_dir() . '/cacti-theme-' . bin2hex(random_bytes(6));

	mkdir($base . '/include/themes/modern', 0700, true);
	mkdir($base . '/include/themes/night-sky_2', 0700, true);
	mkdir($base . '/include/themes/nocss', 0700, true);
	mkdir($base . '/include/other', 0700, true);
	mkdir($base . '/include/themes/cssdir/main.css', 0700, true);

	file_put_contents($base . '/include/themes/modern/main.css', '');
	file_put_contents($base . '/include/themes/modern/rrdtheme.php', '<?php');
	file_put_contents($base . '/include/themes/night-sky_2/main.css', '');
	file_put_contents($base . '/include/other/main.css', '');
	file_put_contents($base . '/include/other/rrdtheme.php', '<?php');

	return $base;
}

function themeNameTestRemove(string $path): void {
	foreach (scandir($path) as $entry) {
		if ($entry === '.' || $entry === '..') {
			continue;
		}

		is_dir($path . '/' . $entry) ? themeNameTestRemove($path . '/' . $entry) : unlink($path . '/' . $entry);
	}

	rmdir($path);
}

beforeEach(function () {
	$this->savedConfig  = $GLOBALS['config'];
	$this->savedSession = $_SESSION ?? null;
	$this->tree         = themeNameTestTree();

	$GLOBALS['config']['base_path'] = $this->tree;
});

afterEach(function () {
	$GLOBALS['config'] = $this->savedConfig;

	if ($this->savedSession === null) {
		unset($_SESSION);
	} else {
		$_SESSION = $this->savedSession;
	}

	themeNameTestRemove($this->tree);
});

test('an installed theme directory name is accepted', function () {
	expect(cacti_is_theme_name('modern'))->toBeTrue()
		->and(cacti_is_theme_name('night-sky_2'))->toBeTrue();
});

test('a name is rejected unless it is a plain directory name with a main.css file', function (mixed $value) {
	expect(cacti_is_theme_name($value))->toBeFalse();
})->with([
	'parent reference'        => ['../other'],
	'detour through a theme'  => ['modern/../modern'],
	'detour out of the tree'  => ['modern/../../other'],
	'backslash separator'     => ['modern\\..\\modern'],
	'leading separator'       => ['/modern'],
	'trailing separator'      => ['modern/'],
	'quote'                   => ["modern'"],
	'markup'                  => ['<b>modern'],
	'trailing newline'        => ["modern\n"],
	'leading dot'             => ['.modern'],
	'leading hyphen'          => ['-modern'],
	'empty string'            => [''],
	'no main.css'             => ['nocss'],
	'main.css is a directory' => ['cssdir'],
	'not installed'           => ['absent'],
	'array'                   => [['modern']],
	'null'                    => [null],
	'integer'                 => [7],
]);

test('a session theme that fails the check is not returned', function () {
	$GLOBALS['config']['config_options_array']['selected_theme'] = 'modern';
	$GLOBALS['themes'] = array('modern' => 'Modern');

	$_SESSION = array('selected_theme' => 'modern/../../other');

	expect(get_selected_theme())->toBe('modern')
		->and($_SESSION['selected_theme'])->toBe('modern');
});

test('a session theme that passes the check is returned unchanged', function () {
	$_SESSION = array('selected_theme' => 'night-sky_2');

	expect(get_selected_theme())->toBe('night-sky_2');
});

test('a default theme that fails the check gives way to an installed theme without a user session', function () {
	$GLOBALS['config']['config_options_array']['selected_theme'] = '../other';
	$GLOBALS['themes'] = array('classic' => 'Classic', 'modern' => 'Modern');

	$_SESSION = array();

	expect(get_selected_theme())->toBe('modern');
});

test('a failing default falls back to a fixed name when no theme is installed', function () {
	$GLOBALS['config']['config_options_array']['selected_theme'] = '../other';
	$GLOBALS['themes'] = array();

	$_SESSION = array();

	expect(get_selected_theme())->toBe('modern');
});

test('a setting chosen from a fixed list accepts only values from that list', function () {
	$setting = array('method' => 'drop_array', 'array' => array('modern' => 'Modern', 'dark' => 'Dark', 5 => 'Five'));

	expect(cacti_user_setting_value_allowed($setting, 'modern'))->toBeTrue()
		->and(cacti_user_setting_value_allowed($setting, '5'))->toBeTrue()
		->and(cacti_user_setting_value_allowed($setting, 5))->toBeTrue()
		->and(cacti_user_setting_value_allowed($setting, '../other'))->toBeFalse()
		->and(cacti_user_setting_value_allowed($setting, 'Modern'))->toBeFalse()
		->and(cacti_user_setting_value_allowed($setting, ''))->toBeFalse()
		->and(cacti_user_setting_value_allowed($setting, array('modern')))->toBeFalse()
		->and(cacti_user_setting_value_allowed($setting, null))->toBeFalse()
		->and(cacti_user_setting_value_allowed($setting, 1.5))->toBeFalse()
		->and(cacti_user_setting_value_allowed($setting, true))->toBeFalse();
});

test('settings without a fixed list are not restricted by the value check', function () {
	expect(cacti_user_setting_value_allowed(array('method' => 'textbox'), 'anything'))->toBeTrue()
		->and(cacti_user_setting_value_allowed(array('method' => 'checkbox'), 'on'))->toBeTrue()
		->and(cacti_user_setting_value_allowed(array('method' => 'drop_array'), 'x'))->toBeTrue()
		->and(cacti_user_setting_value_allowed(array('method' => 'drop_array', 'array' => array()), 'x'))->toBeTrue()
		->and(cacti_user_setting_value_allowed(array('method' => 'drop_sql', 'array' => array('a' => 'A')), 'x'))->toBeTrue();
});

test('the profile form save and the single-setting update both use the value check', function () use ($root) {
	$functions = file_get_contents($root . '/lib/functions.php');
	$profile   = file_get_contents($root . '/auth_profile.php');

	expect($functions)->toContain('if (!cacti_user_setting_value_allowed($field_array, $field_value)) {')
		->and($profile)->toContain('return cacti_user_setting_value_allowed($settings[$name], $value);')
		->and($profile)->toContain('if (!api_auth_user_setting_allowed($name, $value)) {');
});

test('the single-setting update needs a POST request and the settings permission', function () use ($root) {
	$profile = file_get_contents($root . '/auth_profile.php');

	expect($profile)->toMatch("/case 'update_data':\\s+csrf_require_post\\(\\);/")
		->and($profile)->toContain("if (\$name !== 'enable_hscroll' && !is_view_allowed('graph_settings')) {");
});

test('every graph theme include path is built from a validated name', function () use ($root) {
	$rrd = file_get_contents($root . '/lib/rrd.php');

	expect($rrd)->not->toMatch('/\$theme\s*=\s*get_selected_theme\(\);/')
		->and(substr_count($rrd, 'cacti_validate_theme(get_selected_theme())'))->toBe(2);
});

test('a validated theme name never leaves the themes directory', function () {
	$GLOBALS['config']['config_options_array']['selected_theme'] = 'modern';

	expect(cacti_validate_theme('../other'))->toBe('modern')
		->and(cacti_validate_theme('modern/../../other'))->toBe('modern')
		->and(cacti_validate_theme('modern'))->toBe('modern');
});

test('the theme name is encoded where pages print it into script', function () use ($root) {
	$session = file_get_contents($root . '/include/global_session.php');
	$profile = file_get_contents($root . '/auth_profile.php');

	expect($session)->toContain('var theme=<?php print json_encode((string) get_selected_theme());?>;')
		->and($session)->not->toContain("'<?php print get_selected_theme();?>'")
		->and($profile)->not->toContain("'<?php print get_selected_theme();?>'");
});

test('the graph cache key separates themes and color modes', function () use ($root) {
	$boost = file_get_contents($root . '/lib/boost.php');

	expect($boost)->toContain("'theme'          => cacti_validate_theme(isset(\$graph_data_array['graph_theme']) ? \$graph_data_array['graph_theme'] : get_selected_theme()),")
		->and($boost)->toContain("'color_mode'     => \$color_mode,")
		->and($boost)->toContain("in_array(\$_COOKIE['CactiColorMode'], array('dark', 'light', 'dark-dimmed'), true)");
});

test('the custom stylesheet is looked up inside the installation', function () use ($root) {
	$html = file_get_contents($root . '/lib/html.php');

	expect($html)->toContain("file_exists(\$config['base_path'] . '/include/themes/custom.css')")
		->and($html)->not->toContain("file_exists('include/themes/custom.css')");
});

test('every theme directory has an index guard', function () use ($root) {
	$missing  = array();
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($root . '/include/themes', FilesystemIterator::SKIP_DOTS),
		RecursiveIteratorIterator::SELF_FIRST
	);

	foreach ($iterator as $entry) {
		if ($entry->isDir() && !is_file($entry->getPathname() . '/index.php')) {
			$missing[] = substr($entry->getPathname(), strlen($root) + 1);
		}
	}

	expect($missing)->toBe(array());
});

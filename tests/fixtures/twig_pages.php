<?php

// Offline browser fixture. Never expose the test bootstrap through HTTP.
if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

require dirname(__DIR__) . '/bootstrap-unit.php';

use Cacti\Controller\AboutController;
use Cacti\Controller\ChangelogController;
use Cacti\Controller\CdefController;
use Cacti\Controller\VdefController;
use Cacti\Controller\GprintPresetsController;
use Cacti\Controller\SitesController;
use Cacti\Controller\PackageRepositoriesController;
use Cacti\Controller\PublicKeysController;
use Cacti\Controller\RrdCheckController;
use Cacti\Controller\UserLogController;
use Cacti\View\TemplateRenderer;

$renderer = TemplateRenderer::create();
$attack   = '<img data-twig-attack src=x onerror="window.__twigExecuted=1">';
$render   = static function (callable $show): string {
	form_get_table_id(true);
	$table                                                     = form_get_table_id();
	$GLOBALS['tableCount'][$table]                             = 1;
	$_SESSION[OPTIONS_USER]['visible_columns_' . $table . '1'] = '[]';

	return '<table><tbody>' . $show()->getContent() . '</tbody></table>';
};

$fixtures = [
	'about' => '<table>' . (new AboutController($renderer))->show()->getContent() . '</table>',
	'keys'  => $render(static fn () => (new PublicKeysController($renderer))->show([[
		'id' => 7, 'author' => $attack, 'homepage' => 'example.com', 'email_address' => 'mail@example.com', 'public_key' => 'key',
	]], 'img')),
	'repositories' => $render(static fn () => (new PackageRepositoriesController($renderer))->show([[
		'id' => 8, 'name' => $attack, 'repo_type' => 0, 'repo_location' => 'example.com', 'repo_branch' => 'develop', 'enabled' => 'on', 'default' => '',
	]], 'img', ['Git'])),
	'rrdcheck' => $render(static fn () => (new RrdCheckController($renderer))->show([[
		'local_data_id' => 9, 'description' => '', 'name_cache' => '', 'message' => $attack, 'test_date' => '2026-10-27',
	]], 'img')),
	'userlog' => $render(static fn () => (new UserLogController($renderer))->show([[
		'username' => $attack, 'full_name' => null, 'realm' => null, 'time' => '2026-10-27', 'result' => 0, 'ip' => '127.0.0.1',
	]], 'img', [])),
];

foreach (['cdef' => CdefController::class, 'vdef' => VdefController::class, 'gprint' => GprintPresetsController::class] as $name => $controller) {
	$fixtures[$name] = $render(static fn () => (new $controller($renderer))->show([[
		'id' => 7, 'name' => $attack, 'graphs' => 0, 'templates' => 0, 'gprint_text' => '%8.2lf',
	]], 'img'));
}
$fixtures['sites'] = $render(static fn () => (new SitesController($renderer))->show([[
	'id' => 7, 'name' => $attack, 'disabled' => '', 'hosts' => 2, 'city' => 'Test', 'state' => '', 'country' => '',
]], 'img'));
$changelog = tempnam(sys_get_temp_dir(), 'cacti-twig-changelog-');

try {
	file_put_contents($changelog, "Cacti CHANGELOG\n===============\n1.3.0-dev\n-feature#12: " . $attack . "\n");
	$fixtures['changelog'] = (new ChangelogController($renderer, $changelog))->show(true)->getContent();
} finally {
	unlink($changelog);
}

print json_encode($fixtures, JSON_THROW_ON_ERROR);

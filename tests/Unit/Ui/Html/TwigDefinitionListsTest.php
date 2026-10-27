<?php

use Cacti\Controller\CdefController;
use Cacti\Controller\GprintPresetsController;
use Cacti\Controller\SitesController;
use Cacti\Controller\VdefController;
use Cacti\View\TemplateRenderer;

beforeEach(function () {
	form_get_table_id(true);
	$table                                                     = form_get_table_id();
	$GLOBALS['tableCount'][$table]                             = 1;
	$_SESSION[OPTIONS_USER]['visible_columns_' . $table . '1'] = '[]';
});

it('disables deletion only for definitions that are in use', function (string $controllerClass) {
	$controller = new $controllerClass(TemplateRenderer::create());
	$html       = $controller->show([
		['id' => 1, 'name' => 'unused', 'graphs' => 0, 'templates' => 0, 'gprint_text' => '%8.2lf'],
		['id' => 2, 'name' => 'graph used', 'graphs' => 1, 'templates' => 0, 'gprint_text' => '%8.2lf'],
		['id' => 3, 'name' => 'template used', 'graphs' => 0, 'templates' => 1, 'gprint_text' => '%8.2lf'],
	], '')->getContent();

	$dom = new DOMDocument();
	@$dom->loadHTML('<table>' . $html . '</table>');
	$xpath = new DOMXPath($dom);

	expect($xpath->query('//input[@disabled]')->length)->toBe(2);
	expect($xpath->query('//tr[contains(@class,"selectable")]')->length)->toBe(1);
	expect($xpath->query('//input[@id="chk_1"][@disabled]')->length)->toBe(0);
})->with([CdefController::class, VdefController::class, GprintPresetsController::class]);

it('keeps CDEF and VDEF usage links scoped to their own definition', function (string $controllerClass, string $kind) {
	$html = (new $controllerClass(TemplateRenderer::create()))->show([
		['id' => 7, 'name' => '<script>bad()</script>', 'graphs' => 2, 'templates' => 3],
	], '')->getContent();

	expect($html)->toContain($kind . '.php?action=edit&amp;id=7')
		->toContain('graphs.php?reset=1&amp;' . $kind . '_id=7')
		->toContain('graph_templates.php?reset=1&amp;' . $kind . '_id=7')
		->toContain('&lt;script&gt;')->not->toContain('<script>');
})->with([[CdefController::class, 'cdef'], [VdefController::class, 'vdef']]);

it('escapes GPRINT formats as text', function () {
	$html = (new GprintPresetsController(TemplateRenderer::create()))->show([
		['id' => 7, 'name' => 'format', 'graphs' => 0, 'templates' => 0, 'gprint_text' => '<img src=x onerror=alert(1)>'],
	], '')->getContent();

	expect($html)->toContain('&lt;img')->not->toContain('<img');
});

it('keeps all site columns and the device drilldown link', function () {
	$html = (new SitesController(TemplateRenderer::create()))->show([
		['id' => 7, 'name' => 'Site', 'disabled' => 'on', 'hosts' => 4, 'city' => '<svg onload=alert(1)>', 'state' => null, 'country' => 'US'],
	], '')->getContent();

	expect(substr_count($html, '<td '))->toBe(8);
	expect($html)->toContain('host.php?reset=1&amp;site_id=7')->toContain('>Disabled<')
		->toContain('&lt;svg')->not->toContain('<svg');
});

it('retains the empty list labels and column spans', function () {
	$renderer = TemplateRenderer::create();

	expect((new CdefController($renderer))->show([], '')->getContent())->toContain('No CDEFs')->toContain('colspan="5"');
	expect((new VdefController($renderer))->show([], '')->getContent())->toContain('No VDEFs')->toContain('colspan="5"');
	expect((new GprintPresetsController($renderer))->show([], '')->getContent())->toContain('No GPRINT Presets')->toContain('colspan="6"');
	expect((new SitesController($renderer))->show([], '')->getContent())->toContain('No Sites Found')->toContain('colspan="8"');
});

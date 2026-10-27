<?php

use Cacti\Controller\PackageRepositoriesController;
use Cacti\Controller\PublicKeysController;
use Cacti\Controller\RrdCheckController;
use Cacti\Controller\UserLogController;
use Cacti\View\TemplateRenderer;

beforeEach(function () {
	form_get_table_id(true);
	$table                                                     = form_get_table_id();
	$GLOBALS['tableCount'][$table]                             = 1;
	$_SESSION[OPTIONS_USER]['visible_columns_' . $table . '1'] = '[]';
});

it('renders package key rows without interpreting stored author markup', function () {
	$controller = new PublicKeysController(TemplateRenderer::create());
	$html       = $controller->show([[
		'id'       => 7, 'author' => '<img src=x onerror=alert(1)>',
		'homepage' => 'https://example.com', 'email_address' => 'test@example.com', 'public_key' => str_repeat('a', 220),
	]], 'img')->getContent();

	expect($html)->toContain('chk_7')->toContain('SHA256')->toContain('filteredValue')->not->toContain('<img');
});

it('preserves the repository branch column and edit link', function () {
	$controller = new PackageRepositoriesController(TemplateRenderer::create());
	$html       = $controller->show([[
		'id'          => 8, 'name' => 'test', 'repo_type' => 0, 'repo_location' => 'example.com',
		'repo_branch' => '<script>bad()</script>', 'enabled' => 'on', 'default' => '',
	]], '', ['Git'])->getContent();

	expect(substr_count($html, '<td '))->toBe(7);
	expect($html)->toContain('package_repos.php?action=edit&amp;id=8')
		->toContain('&lt;script&gt;bad()&lt;/script&gt;')->toContain('>Yes<')->toContain('>No<');
});

it('keeps deleted data source labels and escapes diagnostic messages', function () {
	$html = (new RrdCheckController(TemplateRenderer::create()))->show([[
		'local_data_id' => 9, 'description' => '', 'name_cache' => '',
		'message'       => '<svg onload=alert(1)>', 'test_date' => '2026-10-27 12:00:00',
	]], '')->getContent();

	expect(substr_count($html, '>Deleted<'))->toBe(2);
	expect($html)->toContain('&lt;svg')->not->toContain('<svg');
});

it('renders removed users and every authentication result', function () {
	$rows = [];

	foreach ([0, 1, 2, 3] as $result) {
		$rows[] = ['username' => 'test', 'full_name' => null, 'realm' => null,
			'time'               => '2026-10-27', 'result' => $result, 'ip' => '127.0.0.1'];
	}
	$html = (new UserLogController(TemplateRenderer::create()))->show($rows, '', [])->getContent();

	expect($html)->toContain('(User Removed)')->toContain('N/A')->toContain('Failed')
		->toContain('Success - Password<')->toContain('Success - Password Change')->toContain('Success - Token');
});

it('uses the existing saved column visibility when rendering Twig rows', function () {
	$table                                                     = form_get_table_id();
	$_SESSION[OPTIONS_USER]['visible_columns_' . $table . '1'] = json_encode(['author' => false, 'id' => true, 'homepage' => true, 'email' => true, 'type' => true]);
	$html                                                      = (new PublicKeysController(TemplateRenderer::create()))->show([[
		'id' => 7, 'author' => 'hidden-author', 'homepage' => 'homepage', 'email_address' => 'email', 'public_key' => 'key',
	]], '')->getContent();

	// The checkbox title still identifies the row, while the author cell is hidden.
	expect($html)->not->toContain('>hidden-author<');
	expect(substr_count($html, '<td '))->toBe(5);
});

it('renders empty lists with their original column spans', function () {
	$renderer = TemplateRenderer::create();

	expect((new PublicKeysController($renderer))->show([], '')->getContent())->toContain('colspan="6"')->toContain('No Package Public Keys Found');
	expect((new PackageRepositoriesController($renderer))->show([], '', [])->getContent())->toContain('colspan="7"');
	expect((new RrdCheckController($renderer))->show([], '')->getContent())->toContain('colspan="5"');
	expect(trim((new UserLogController($renderer))->show([], '', [])->getContent()))->toBe('');
});

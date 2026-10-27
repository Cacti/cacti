<?php

use Cacti\Controller\AboutController;
use Cacti\Controller\ChangelogController;
use Cacti\View\CactiTableExtension;
use Cacti\View\TemplateRenderer;
use Twig\Error\RuntimeError;

it('escapes changelog text and retains numeric issue links', function () {
	$path = tempnam(sys_get_temp_dir(), 'cacti-changelog-');
	file_put_contents($path, "Cacti CHANGELOG\n===============\n1.3.0-dev\n-feature#12: <img src=x onerror=alert(1)> & test\n-issue#13: ordinary fix\n1.2.30\n-issue#14: older\n");

	try {
		$controller = new ChangelogController(TemplateRenderer::create(), $path);
		$full       = $controller->show(true)->getContent();
		$highlights = $controller->show(false)->getContent();

		expect($full)->toContain('&lt;img src=x onerror=alert(1)&gt; &amp; test')
			->toContain('https://github.com/cacti/cacti/issues/12')
			->toContain('ordinary fix')->toContain('older')->not->toContain('<img');
		expect($highlights)->not->toContain('ordinary fix')->not->toContain('older');
	} finally {
		unlink($path);
	}
});

it('renders about links and developer credits through the controller response', function () {
	$response = (new AboutController(TemplateRenderer::create()))->show();

	expect($response->getStatusCode())->toBe(200);
	expect($response->getContent())->toContain('Thomas Vincent')->toContain('<a href="http://www.cacti.net/?version=')
		->toContain('<strong>Tobi Oetiker')->not->toContain('&lt;a href');
});

it('fails closed when template data is missing', function () {
	TemplateRenderer::create()->render('pages/about.html.twig', []);
})->throws(RuntimeError::class);

it('rejects executable and remote URLs at the table link boundary', function (string $url) {
	(new CactiTableExtension())->filter('text', '', $url);
})->with(['javascript:alert(1)', '//example.com', 'https://example.com', '\\example.com'])->throws(InvalidArgumentException::class);

it('escapes text before adding Cacti search highlights', function () {
	$html = (string) (new CactiTableExtension())->filter('<script>alert(1)</script>', 'alert', 'sites.php?action=edit&id=1');

	expect($html)->toContain('&lt;script&gt;')->toContain("<span class='filteredValue'>alert</span>")
		->not->toContain('<script>');
});

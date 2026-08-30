<?php
declare(strict_types = 1);
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 */

/*
 * The bundled GoogleAuthenticator classes are patched for the PHP 8.4+ implicit
 * nullable deprecation, but that is not asserted here: include/vendor is restored
 * from the Actions cache (keyed on composer.lock) before the suite runs, so the
 * on-disk copy is whatever a prior develop run cached, not the checkout. A file
 * assertion would test the cache, not this branch. The patch still ships in the
 * committed tree for anyone running the sources directly.
 */

test('composer retains PHP 8.1 test support while allowing modern Pest releases', function () {
	$composer = json_decode(
		file_get_contents(CACTI_PATH_BASE . '/composer.json'),
		true,
		512,
		JSON_THROW_ON_ERROR
	);

	expect($composer['require-dev']['pestphp/pest'])->toContain('^2')
		->toContain('^3')
		->and($composer['require-dev']['pestphp/pest-plugin-drift'])->toContain('^2')
		->toContain('^3');
});

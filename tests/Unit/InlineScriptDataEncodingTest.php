<?php
declare(strict_types = 1);
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/**
 * Values emitted into an inline <script> need the script-context encoder.
 *
 * A bare json_encode() escapes forward slashes, so the direct </script>
 * breakout is blocked, but it leaves <, >, & and ' intact. cacti_js_encode()
 * applies the JSON_HEX_* flags and neutralises the U+2028/U+2029 JavaScript
 * line terminators as well.
 *
 * @group regression
 */

$root = dirname(__DIR__, 2);

test('session notices are encoded for a script context', function () use ($root) {
	$source = file_get_contents($root . '/lib/functions.php');

	expect($source)->not->toContain('return json_encode($final_messages);');
	expect($source)->toContain('return cacti_js_encode($final_messages);');
});

test('replayed new-graph form data is encoded for a script context', function () use ($root) {
	$source = file_get_contents($root . '/graphs_new.php');

	expect($source)->not->toContain('json_encode($form_data)');
	expect($source)->toContain('cacti_js_encode($form_data)');
});

test('numeric scalars printed into script blocks are cast', function () use ($root) {
	foreach (['user_group_admin.php', 'user_admin.php'] as $file) {
		$source = file_get_contents($root . '/' . $file);

		expect($source)->not->toContain("print read_config_option('font_method');");
		expect($source)->toContain("print (int) read_config_option('font_method');");
	}

	expect(file_get_contents($root . '/data_queries.php'))
		->not->toContain('var graph_template_id_prev=<?php print $item; ?>;');
	expect(file_get_contents($root . '/pollers.php'))
		->not->toContain('pt = <?php print $pt; ?>;');
});

test('the encoder neutralises a script breakout and JS line terminators', function () use ($root) {
	require_once $root . '/lib/functions.php';

	$encoded = cacti_js_encode(['m' => "</script><img src=x onerror='alert(1)'>"]);

	expect($encoded)->not->toContain('<')
		->and($encoded)->not->toContain('>')
		->and($encoded)->not->toContain("'");

	expect(cacti_js_encode("a\u{2028}b"))->toContain('\\u2028');
});

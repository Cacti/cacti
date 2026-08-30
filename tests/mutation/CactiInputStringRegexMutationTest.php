<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Mutation protection for the cacti_input_string_is_safe() regex fix in
 * lib/functions.php. Each test here pins a single behaviour that would
 * survive a one-character mutation against the strip pattern or the
 * blocklist. Targets the GHSA-c4qp guarantee plus the issue #7121
 * regression class (quoted and digit-suffixed placeholders).
 */

$functionsSource = file_get_contents(__DIR__ . '/../../lib/functions.php');

if (!function_exists('_mutation_input_string_is_safe')) {
	preg_match('/^function cacti_input_string_is_safe\([^)]*\)\s*\{.*?^\}/sm', $functionsSource, $m);
	if (!$m) {
		throw new RuntimeException('cacti_input_string_is_safe definition not found');
	}
	$src = preg_replace('/^function cacti_input_string_is_safe\(/m', 'function _mutation_input_string_is_safe(', $m[0]);
	eval($src); // nosemgrep: php.lang.security.eval-use.eval-use
}

test('strip pattern accepts double-quoted placeholders (Mutation Protection)', function () {
	/* If a mutation drops the `"<x>"` alternation from the strip regex,
	 * the literal "" residue trips the blocklist's `"` entry and this
	 * fails. */
	expect(_mutation_input_string_is_safe('<path_cacti>/x.php "<reason>"'))->toBeTrue();
});

test('strip pattern accepts single-quoted placeholders (Mutation Protection)', function () {
	/* Same idea for the `'<x>'` alternation. */
	expect(_mutation_input_string_is_safe("<path_cacti>/x.php '<reason>'"))->toBeTrue();
});

test('strip pattern accepts digit-suffixed placeholder names (Mutation Protection)', function () {
	/* If a mutation narrows `[a-zA-Z0-9_]+` back to `[a-zA-Z_]+`, the
	 * `<arg1>` token does not match, the bare `<` `>` survive, and the
	 * blocklist rejects them. */
	expect(_mutation_input_string_is_safe('<path_cacti>/x.php <arg1> <host_id2>'))->toBeTrue();
});

test('blocklist still rejects bare double-quote (Mutation Protection)', function () {
	/* If a mutation drops `"` from the blocklist character class, the
	 * GHSA-c4qp protection regresses. The strip pattern only consumes
	 * paired quotes around a placeholder, so a bare quote outside any
	 * placeholder must remain rejected. */
	expect(_mutation_input_string_is_safe('echo "hello"'))->toBeFalse();
});

test('blocklist still rejects bare single-quote (Mutation Protection)', function () {
	expect(_mutation_input_string_is_safe("/bin/sh -c 'id'"))->toBeFalse();
});

test('blocklist still rejects redirect operator (Mutation Protection)', function () {
	expect(_mutation_input_string_is_safe('cmd > /tmp/out'))->toBeFalse();
});

test('blocklist still rejects subshell parens (Mutation Protection)', function () {
	expect(_mutation_input_string_is_safe('/bin/sh -c (id)'))->toBeFalse();
});

test('blocklist still rejects classic shell metacharacters (Mutation Protection)', function () {
	expect(_mutation_input_string_is_safe('cmd; rm -rf /'))->toBeFalse();
	expect(_mutation_input_string_is_safe('cmd && bad'))->toBeFalse();
	expect(_mutation_input_string_is_safe('cmd | nc evil 1'))->toBeFalse();
	expect(_mutation_input_string_is_safe('echo `id`'))->toBeFalse();
	expect(_mutation_input_string_is_safe('echo $(id)'))->toBeFalse();
});

test('return polarity is preserved (Mutation Protection)', function () {
	/* If a mutation drops the `!` on the preg_match return, true and
	 * false flip and every other test in the suite would fail. Pin it
	 * explicitly with the simplest known-safe input so the polarity
	 * regression is unambiguous. */
	expect(_mutation_input_string_is_safe(''))->toBeTrue();
	expect(_mutation_input_string_is_safe('<host>'))->toBeTrue();
	expect(_mutation_input_string_is_safe('cmd ;'))->toBeFalse();
});

test('strip is anchored on the placeholder name, not greedy across content (Mutation Protection)', function () {
	/* If a mutation makes the strip pattern greedy (e.g. `<.+>` instead
	 * of `<[a-zA-Z0-9_]+>`) it would consume `<;rm -rf>` as a placeholder
	 * and the residue would no longer trip the blocklist. Pin the
	 * non-greedy semantics. */
	expect(_mutation_input_string_is_safe('<path_cacti>/x.php <;rm -rf /;>'))->toBeFalse();
});

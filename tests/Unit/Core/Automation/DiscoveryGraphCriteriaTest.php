<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
declare(strict_types = 1);

use Cacti\Tests\Helpers\PhpSource;

require_once dirname(__DIR__, 3) . '/Helpers/PhpSource.php';
require_once dirname(__DIR__, 3) . '/Helpers/IsolatedProbe.php';

test('graph criteria save the validated identity and preserve existing hashes', function (string $kind, int $id): void {
	$root        = dirname(__DIR__, 4);
	$definitions = [];

	foreach (['automation_graph_rules.php' => 'form_save', 'lib/functions.php' => 'get_hash_automation'] as $file => $name) {
		$source = file_get_contents($root . '/' . $file);
		expect($source)->toBeString();
		$start = PhpSource::position($source, 'function ' . $name . '(');
		expect($start)->not->toBeFalse();
		$definition = '';
		$depth      = 0;
		$opened     = false;

		foreach (token_get_all('<?php ' . substr($source, $start)) as $token) {
			if (is_array($token) && $token[0] === T_OPEN_TAG) {
				continue;
			}
			$text = is_array($token) ? $token[1] : $token;
			$definition .= $text;

			if ($token === '{' || (is_array($token) && in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
				$depth++;
				$opened = true;
			} elseif ($token === '}') {
				$depth--;

				if ($opened && $depth === 0) {
					break;
				}
			}
		}
		expect($opened)->toBeTrue()->and($depth)->toBe(0);
		$definitions[$file] = ['hash' => hash('sha256', $source), 'definition' => base64_encode($definition)];
	}
	$result = cacti_test_isolated_probe(dirname(__DIR__, 3) . '/fixtures/discovery_graph_criteria_probe.php', [json_encode(compact('kind', 'id', 'definitions'), JSON_THROW_ON_ERROR)]);
	expect($result['errors'])->toBe([])
		->and($result['saved']['id'])->toBe($id)
		->and($result['lookup_id'])->toBe($id)
		->and($result['table'])->toBe($kind === 'match' ? 'automation_match_rule_items' : 'automation_graph_rule_items')
		->and($result['saved']['hash'])->toBe(str_repeat($id === 0 ? 'a' : 'b', 32));
})->with([
	'match creation' => ['match', 0],
	'match update'   => ['match', 17],
	'graph creation' => ['graph', 0],
	'graph update'   => ['graph', 17],
]);

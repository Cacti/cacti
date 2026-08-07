<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/**
 * Creates a private-advisory proof matrix containing one test-backed row.
 *
 * @param int $changelog_hits Number of exact GHSA references found in CHANGELOG.
 *
 * @return string Absolute path to the temporary matrix fixture.
 */
function advisory_matrix_fixture(int $changelog_hits) : string {
	$path = tempnam(sys_get_temp_dir(), 'cacti_advisory_matrix_');

	if ($path === false) {
		throw new RuntimeException('Unable to create a temporary advisory matrix path.');
	}

	$header = "branch\tadvisory_key_hash\tstate\tseverity\tsummary\tcommit_count\ttest_hits\tchangelog_hits\tsecurity_hits\tcode_hits\tproof_status\n";
	$row    = "1.2.x\tabc123\tdraft\thigh\tprivate finding\t1\t1\t$changelog_hits\t0\t1\tPROVEN_TEST_BACKED\n";

	if (file_put_contents($path, $header . $row) === false) {
		unlink($path);

		throw new RuntimeException('Unable to write the temporary advisory matrix.');
	}

	return $path;
}

/**
 * Runs the strict private-advisory verification gate for a matrix fixture.
 *
 * @param string $matrix Absolute path to the proof matrix fixture.
 *
 * @return array{0: int, 1: string} Exit status and combined command output.
 */
function run_advisory_matrix_gate(string $matrix) : array {
	$root   = dirname(__DIR__, 2);
	$output = [];
	$status = 0;

	exec('bash ' . escapeshellarg($root . '/tests/security/verify_private_advisory_matrix.sh') . ' ' . escapeshellarg($matrix) . ' 2>&1', $output, $status);

	return [$status, implode("\n", $output)];
}

test('the strict advisory gate requires a CHANGELOG GHSA reference', function () {
	$missing = advisory_matrix_fixture(0);
	$linked  = advisory_matrix_fixture(1);
	$invalid = advisory_matrix_fixture(1);
	$contents = file_get_contents($invalid);

	expect($contents)->not->toBeFalse();
	file_put_contents($invalid, str_replace('changelog_hits', 'renamed_column', $contents));

	try {
		[$missing_status, $missing_output] = run_advisory_matrix_gate($missing);
		[$linked_status, $linked_output]   = run_advisory_matrix_gate($linked);
		[$invalid_status, $invalid_output] = run_advisory_matrix_gate($invalid);
	} finally {
		unlink($missing);
		unlink($linked);
		unlink($invalid);
	}

	expect($missing_status)->toBe(1)
		->and($missing_output)->toContain('matrix_missing_changelog=1')
		->and($missing_output)->toContain('without a GHSA reference in CHANGELOG')
		->and($linked_status)->toBe(0)
		->and($linked_output)->toContain('matrix_missing_changelog=0')
		->and($linked_output)->toContain('matrix closure criteria satisfied')
		->and($invalid_status)->toBe(1)
		->and($invalid_output)->toContain('matrix header is missing changelog_hits');
});

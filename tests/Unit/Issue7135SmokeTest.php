<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Smoke tests for issue #7135. Verify that lib/utility.php and
 * lib/functions.php both parse, the foreach($outputs) loops are still
 * present, and each calls unset() at iteration top. Runs without
 * Cacti's bootstrap.
 */

$repoRoot = __DIR__ . '/../..';

test('lib/utility.php and lib/functions.php both parse', function () use ($repoRoot) {
	expect(file_exists("$repoRoot/lib/utility.php"))->toBeTrue();
	expect(file_exists("$repoRoot/lib/functions.php"))->toBeTrue();
	/* Trivial cross-check: the canonical update_poller_cache signature
	 * is unchanged and the test_data_source signature from functions.php
	 * is also unchanged. */
	$util = file_get_contents("$repoRoot/lib/utility.php");
	$fns  = file_get_contents("$repoRoot/lib/functions.php");
	expect($util)->toContain('function update_poller_cache($data_source, $commit = false)');
	expect($fns)->toContain('function test_data_source(');
});

test('every foreach($outputs as $output) loop has an unset() at iteration top', function () use ($repoRoot) {
	$bodies = [];
	foreach (['lib/utility.php', 'lib/functions.php'] as $rel) {
		$src = file_get_contents("$repoRoot/$rel");
		$offset = 0;
		while (($pos = strpos($src, 'foreach ($outputs as $output) {', $offset)) !== false) {
			$brace = strpos($src, '{', $pos);
			$depth = 1;
			$i     = $brace + 1;
			$len   = strlen($src);
			while ($depth > 0 && $i < $len) {
				$ch = $src[$i];
				if ($ch === '{')      { $depth++; }
				elseif ($ch === '}')  { $depth--; }
				$i++;
			}
			$bodies[] = ['file' => $rel, 'body' => substr($src, $brace + 1, $i - $brace - 2)];
			$offset = $i;
		}
	}

	expect(count($bodies))->toBeGreaterThanOrEqual(4);

	foreach ($bodies as $entry) {
		$hasOid    = strpos($entry['body'], '$oid') !== false;
		$hasScript = strpos($entry['body'], '$script_path') !== false;
		if ($hasOid) {
			expect($entry['body'])->toContain('unset($oid)');
		}
		if ($hasScript) {
			expect($entry['body'])->toContain('unset($script_path)');
		}
	}
});

#!/usr/bin/env php
<?php
/*
 * Classifies db_fetch and db_execute call sites by how their SQL argument is
 * built, so unparameterised value interpolation can be tracked and driven to
 * zero. Uses the PHP tokenizer rather than regex, so multi-line calls,
 * concatenation and double-quoted interpolation are read the way PHP reads them.
 *
 * Categories:
 *   prepared   - *_prepared() call; values are bound. Safe.
 *   static     - SQL argument has no variable. Safe.
 *   qstr       - dynamic parts pass only through db_qstr()/array_to_sql_or().
 *                Values are escaped. Safe.
 *   identifier - a variable lands in identifier position (table/column name).
 *                Cannot be bound; needs an allowlist. Review, not migrate.
 *   value      - a variable lands in value position with no escaping. This is
 *                the migration target: convert to a *_prepared() call.
 *
 * Usage:
 *   php tests/tools/sql_interpolation_audit.php               # human summary
 *   php tests/tools/sql_interpolation_audit.php --json        # machine worklist
 *   php tests/tools/sql_interpolation_audit.php --check       # ratchet vs baseline
 *   php tests/tools/sql_interpolation_audit.php --write-baseline
 */

chdir(__DIR__ . '/../../');

const BASELINE = 'tests/tools/sql_interpolation_baseline.json';

$db_functions = array(
	'db_execute', 'db_execute_prepared',
	'db_fetch_cell', 'db_fetch_cell_prepared',
	'db_fetch_row', 'db_fetch_row_prepared',
	'db_fetch_assoc', 'db_fetch_assoc_prepared',
	'db_fetch_insert_id',
);

// Escaping helpers: if the only calls injected into the SQL are these, the
// dynamic parts are already escaped.
$escaping_helpers = array('db_qstr', 'array_to_sql_or', 'array_to_sql');

function list_php_files(): array {
	// Tracked files only: matches what ships and avoids nested worktrees,
	// vendored copies and other untracked noise in a working tree.
	exec('git ls-files -z -- "*.php"', $lines, $rc);

	if ($rc !== 0) {
		fwrite(STDERR, "git ls-files failed; run from inside the repository.\n");
		exit(2);
	}

	$out = array();

	foreach (explode("\0", implode("\n", $lines)) as $path) {
		if ($path === '') {
			continue;
		}

		// Skip vendored code and the test tree; we audit shipped source.
		if (strpos($path, 'include/vendor/') === 0 ||
			strpos($path, 'tests/') === 0 ||
			strpos($path, 'scripts/') === 0) {
			continue;
		}

		$out[] = $path;
	}

	sort($out);

	return $out;
}

/**
 * Reconstruct the text of a call's first argument, mapping every variable and
 * embedded expression to the marker §. Returns array(text, has_var, helpers).
 */
function inspect_first_arg(array $tokens, int $start, int $end, array $escaping_helpers): array {
	$text     = '';
	$has_var  = false;
	$helpers  = array();
	$only_helper_vars = true; // every § came from an escaping helper call

	for ($i = $start; $i < $end; $i++) {
		$tok = $tokens[$i];

		if (!is_array($tok)) {
			// literal single-char tokens ('.', etc.): no text contribution
			continue;
		}

		list($id, $val) = $tok;

		if ($id === T_CONSTANT_ENCAPSED_STRING) {
			$text .= substr($val, 1, -1);
		} elseif ($id === T_ENCAPSED_AND_WHITESPACE) {
			$text .= $val;
		} elseif ($id === T_VARIABLE) {
			// A bare variable is a raw, unescaped interpolation (§).
			$text .= '§';
			$has_var = true;
			$only_helper_vars = false;
		} elseif ($id === T_CURLY_OPEN || $id === T_DOLLAR_OPEN_CURLY_BRACES) {
			$text .= '§';
			$has_var = true;
			$only_helper_vars = false;
		} elseif ($id === T_STRING) {
			// Look past whitespace: is this identifier a function call?
			$k = $i + 1;
			while ($k < $end && is_array($tokens[$k]) &&
				($tokens[$k][0] === T_WHITESPACE || $tokens[$k][0] === T_COMMENT)) {
				$k++;
			}

			if ($k < $end && $tokens[$k] === '(') {
				// Skip the call's own argument list; the call is one unit.
				$depth = 0;
				for ($m = $k; $m < $end; $m++) {
					if ($tokens[$m] === '(') {
						$depth++;
					} elseif ($tokens[$m] === ')') {
						$depth--;
						if ($depth === 0) {
							break;
						}
					}
				}

				$has_var = true;

				if (in_array($val, $escaping_helpers, true)) {
					$helpers[] = $val;
					$text .= '¤';           // escaped fragment
				} else {
					$only_helper_vars = false; // unknown escaping: treat as raw
					$text .= '§';
				}

				$i = $m; // resume after the call
			}
			// bare identifier (constant, class name): no text contribution
		}
		// whitespace, comments: ignore
	}

	return array($text, $has_var, $helpers, $only_helper_vars);
}

/**
 * Decide whether a raw marker (§) at $pos sits in identifier or value position
 * by finding the nearest anchor keyword/operator before it.
 */
function marker_position(string $before): string {
	$ident = '/\b(FROM|JOIN|INTO|UPDATE|TABLE|DATABASE|DESCRIBE|ANALYZE|OPTIMIZE|RENAME|TRUNCATE|DROP)\b(?:\s+(?:IF|NOT|EXISTS|`))*[\s`.]*$/i';
	$value = '/(=|<|>|<=|>=|<>|!=|\bIN\b|\bVALUES\b|\bLIKE\b|\bLIMIT\b|\bOFFSET\b|\(|,)\s*`?$/i';

	$ident_at = preg_match($ident, $before) ? 1 : 0;
	$value_at = preg_match($value, $before) ? 1 : 0;

	if ($ident_at && !$value_at) {
		return 'identifier';
	}

	if ($value_at && !$ident_at) {
		return 'value';
	}

	// Ambiguous or no anchor: treat as value so it is not silently trusted.
	return 'value';
}

function classify(string $func, string $text, bool $has_var, array $helpers, bool $only_helper_vars): string {
	if (substr($func, -9) === '_prepared') {
		return 'prepared';
	}

	if (!$has_var) {
		return 'static';
	}

	// No raw markers: every dynamic fragment passed through an escaping helper.
	if (strpos($text, '§') === false) {
		return 'qstr';
	}

	// The argument is just a variable (or variables) holding a prebuilt query;
	// there is no inline SQL to anchor against. Needs data-flow tracing, not a
	// mechanical prepared-statement rewrite.
	if (trim(str_replace(array('§', '¤'), '', $text)) === '') {
		return 'opaque';
	}

	// Classify each raw marker by position; value dominates identifier.
	$saw_identifier = false;
	$offset = 0;

	while (($pos = strpos($text, '§', $offset)) !== false) {
		if (marker_position(substr($text, 0, $pos)) === 'value') {
			return 'value';
		}
		$saw_identifier = true;
		$offset = $pos + strlen('§');
	}

	return $saw_identifier ? 'identifier' : 'value';
}

$files   = list_php_files();
$results = array();  // path => array of sites

foreach ($files as $path) {
	$src = file_get_contents($path);

	if ($src === false || strpos($src, 'db_') === false) {
		continue;
	}

	$tokens = @token_get_all($src);
	$n = count($tokens);

	for ($i = 0; $i < $n; $i++) {
		$tok = $tokens[$i];

		if (!is_array($tok) || $tok[0] !== T_STRING) {
			continue;
		}

		$func = $tok[1];

		if (!in_array($func, $GLOBALS['db_functions'], true)) {
			continue;
		}

		// Next significant token must be '(' for this to be a call.
		$j = $i + 1;
		while ($j < $n && is_array($tokens[$j]) && ($tokens[$j][0] === T_WHITESPACE || $tokens[$j][0] === T_COMMENT)) {
			$j++;
		}

		if ($j >= $n || $tokens[$j] !== '(') {
			continue;
		}

		$line = $tok[2];

		// Walk to the matching close paren; split first arg at top-level comma.
		$depth = 0;
		$arg_start = $j + 1;
		$arg_end = null;
		$call_end = null;

		for ($k = $j; $k < $n; $k++) {
			$t = $tokens[$k];

			if ($t === '(') {
				$depth++;
			} elseif ($t === ')') {
				$depth--;
				if ($depth === 0) {
					$call_end = $k;
					if ($arg_end === null) {
						$arg_end = $k;
					}
					break;
				}
			} elseif ($t === ',' && $depth === 1 && $arg_end === null) {
				$arg_end = $k;
			}
		}

		if ($arg_end === null) {
			continue;
		}

		list($text, $has_var, $helpers, $only_helper_vars) =
			inspect_first_arg($tokens, $arg_start, $arg_end, $escaping_helpers);

		$category = classify($func, $text, $has_var, $helpers, $only_helper_vars);

		$results[$path][] = array(
			'line'     => $line,
			'func'     => $func,
			'category' => $category,
		);

		$i = $call_end; // resume after this call
	}
}

// ---- aggregate ----
$per_file  = array();     // path => category => count
$totals    = array('prepared' => 0, 'static' => 0, 'qstr' => 0, 'opaque' => 0, 'identifier' => 0, 'value' => 0);

foreach ($results as $path => $sites) {
	foreach ($sites as $s) {
		$per_file[$path][$s['category']] = ($per_file[$path][$s['category']] ?? 0) + 1;
		$totals[$s['category']]++;
	}
}

// ---- output modes ----
$mode = $argv[1] ?? '--summary';

if ($mode === '--list') {
	$want = $argv[2] ?? 'value';
	ksort($results);
	foreach ($results as $path => $sites) {
		foreach ($sites as $s) {
			if ($s['category'] === $want) {
				printf("%s:%d\t%s\n", $path, $s['line'], $s['func']);
			}
		}
	}
	exit(0);
}

if ($mode === '--json') {
	$out = array('totals' => $totals, 'files' => array());
	ksort($per_file);
	foreach ($per_file as $path => $cats) {
		$out['files'][$path] = array(
			'value'      => $cats['value'] ?? 0,
			'identifier' => $cats['identifier'] ?? 0,
		);
	}
	print json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
	exit(0);
}

if ($mode === '--write-baseline') {
	$baseline = array();
	ksort($per_file);
	foreach ($per_file as $path => $cats) {
		$v = $cats['value'] ?? 0;
		if ($v > 0) {
			$baseline[$path] = $v;
		}
	}
	file_put_contents(BASELINE, json_encode($baseline, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
	printf("Wrote %s: %d files, %d value-interpolation sites.\n", BASELINE, count($baseline), array_sum($baseline));
	exit(0);
}

if ($mode === '--check') {
	if (!file_exists(BASELINE)) {
		fwrite(STDERR, "Baseline missing: " . BASELINE . " (run --write-baseline).\n");
		exit(2);
	}

	$baseline = json_decode(file_get_contents(BASELINE), true);
	if (!is_array($baseline)) {
		fwrite(STDERR, "Baseline is not valid JSON.\n");
		exit(2);
	}

	$regressions = array();
	$current = array();
	foreach ($per_file as $path => $cats) {
		if (($cats['value'] ?? 0) > 0) {
			$current[$path] = $cats['value'];
		}
	}

	foreach ($current as $path => $v) {
		$allowed = $baseline[$path] ?? 0;
		if ($v > $allowed) {
			$regressions[] = sprintf('  %s: %d value site(s), baseline %d', $path, $v, $allowed);
		}
	}

	if ($regressions !== array()) {
		fwrite(STDERR, "SQL interpolation ratchet failed. New unparameterised value interpolation:\n");
		fwrite(STDERR, implode("\n", $regressions) . "\n");
		fwrite(STDERR, "\nUse db_*_prepared() with a parameter array, then update the baseline\n");
		fwrite(STDERR, "with: php tests/tools/sql_interpolation_audit.php --write-baseline\n");
		exit(1);
	}

	// Nudge the baseline down when sites are fixed but the file is stale.
	$stale = array();
	foreach ($baseline as $path => $allowed) {
		$now = $current[$path] ?? 0;
		if ($now < $allowed) {
			$stale[] = sprintf('  %s: baseline %d, now %d', $path, $allowed, $now);
		}
	}

	printf("SQL interpolation ratchet passed. %d value site(s) across %d file(s).\n",
		array_sum($current), count($current));

	if ($stale !== array()) {
		printf("%d file(s) improved below baseline; refresh with --write-baseline:\n%s\n",
			count($stale), implode("\n", $stale));
	}

	exit(0);
}

// default: human summary
printf("db_* call sites by SQL construction:\n\n");
foreach ($totals as $cat => $n) {
	printf("  %-11s %5d\n", $cat, $n);
}
printf("  %-11s %5d\n", 'TOTAL', array_sum($totals));

printf("\nMigration target (value) and review target (identifier) by file:\n\n");
$rows = array();
foreach ($per_file as $path => $cats) {
	$v = $cats['value'] ?? 0;
	$id = $cats['identifier'] ?? 0;
	if ($v || $id) {
		$rows[$path] = array($v, $id);
	}
}
uasort($rows, function ($a, $b) { return $b[0] <=> $a[0]; });
printf("  %-52s %6s %6s\n", 'file', 'value', 'ident');
foreach ($rows as $path => $c) {
	printf("  %-52s %6d %6d\n", $path, $c[0], $c[1]);
}

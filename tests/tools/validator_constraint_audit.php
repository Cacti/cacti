#!/usr/bin/env php
<?php
/*
 * Classifies CactiValidator::validateInput() and form_input_validate() call
 * sites by whether they actually validate anything, so undocumented no-op
 * validation (empty Symfony constraints / empty legacy regex) can be tracked
 * and driven to zero. Uses the PHP tokenizer rather than regex, so multi-line
 * calls are read the way PHP reads them.
 *
 * Both functions accept an empty constraint ([] / '') as a legitimate way to
 * say "this field is genuinely free text, nothing to validate" -- the ratchet
 * doesn't forbid that, it forbids it being SILENT. A call is exempt from the
 * ratchet when it's paired with a same-line or immediately-preceding comment
 * documenting why, e.g.:
 *     // no-validation: free-text device notes field, no sink reads this raw
 *     $save['notes'] = CactiValidator::validateInput($v, 'notes', [], 3);
 *
 * Categories:
 *   constrained          - non-empty constraints/regex. Validated.
 *   documented-empty     - empty constraints/regex with a no-validation comment. Reviewed once, allowed.
 *   empty                - empty constraints/regex with no comment. This is the migration target.
 *   dynamic              - constraints/regex is not a literal (a variable/expression).
 *                          Can't be judged statically; reported separately, never penalized.
 *
 * Usage:
 *   php tests/tools/validator_constraint_audit.php               # human summary
 *   php tests/tools/validator_constraint_audit.php --json        # machine worklist
 *   php tests/tools/validator_constraint_audit.php --check       # ratchet vs baseline
 *   php tests/tools/validator_constraint_audit.php --write-baseline
 */

// Relocate to the repo root for CLI runs only; a library include (the unit
// tests) must not inherit a working-directory side effect.
if (!defined('VALIDATOR_AUDIT_LIB_ONLY')) {
	chdir(__DIR__ . '/../../');
}

const BASELINE = 'tests/tools/validator_constraint_baseline.json';

const NO_VALIDATION_COMMENT = '/no-validation\s*:/i';

function validator_list_php_files(): array {
	// Tracked files only: matches what ships and avoids nested worktrees,
	// vendored copies and other untracked noise in a working tree.
	exec('git ls-files -z -- "*.php"', $lines, $rc);

	if ($rc !== 0) {
		fwrite(STDERR, "git ls-files failed; run from inside the repository.\n");
		exit(2);
	}

	$out = [];

	foreach (explode("\0", implode("\n", $lines)) as $path) {
		if ($path === '') {
			continue;
		}

		// Skip vendored code and the test tree; we audit shipped source.
		if (str_starts_with($path, 'include/vendor/') ||
			str_starts_with($path, 'tests/') ||
			str_starts_with($path, 'scripts/')) {
			continue;
		}

		$out[] = $path;
	}

	sort($out);

	return $out;
}

/**
 * Walk from token index $i to the matching close-paren of the call whose
 * open-paren is at $i, returning [argStarts, argEnds] pairs (one per
 * top-level comma-separated argument) and the index of the close-paren.
 *
 * $depth counts ()/[]/{} together, not just parens: a comma is only a
 * top-level separator when it sits outside every bracket kind, e.g. a
 * constraints array argument like [new Assert\NotBlank(), new Assert\Regex(...)]
 * has a comma between its elements that must NOT split the outer call.
 * Only ')' can end the call itself -- ']' and '}' just pop the shared depth.
 */
function split_call_args(array $tokens, int $open): array {
	$n         = count($tokens);
	$depth     = 0;
	$arg_start = $open + 1;
	$args      = [];
	$close     = null;

	for ($k = $open; $k < $n; $k++) {
		$t = $tokens[$k];

		if ($t === '(' || $t === '[' || $t === '{') {
			$depth++;
		} elseif ($t === ')') {
			$depth--;

			if ($depth === 0) {
				$args[] = [$arg_start, $k];
				$close  = $k;

				break;
			}
		} elseif ($t === ']' || $t === '}') {
			$depth--;
		} elseif ($t === ',' && $depth === 1) {
			$args[]    = [$arg_start, $k];
			$arg_start = $k + 1;
		}
	}

	return [$args, $close];
}

/**
 * Is the token range [start, end) a literal empty array ([] or array())?
 * Whitespace/comments are ignored; anything else (even a single nested
 * element) means "not empty" for our purposes.
 */
function is_empty_array_literal(array $tokens, int $start, int $end): bool {
	$meaningful = [];

	for ($i = $start; $i < $end; $i++) {
		$tok = $tokens[$i];

		if (is_array($tok)) {
			if ($tok[0] === T_WHITESPACE || $tok[0] === T_COMMENT || $tok[0] === T_DOC_COMMENT) {
				continue;
			}

			if ($tok[0] === T_ARRAY) {
				continue; // the `array` keyword itself, not a value
			}

			return false; // any other token (T_STRING, T_VARIABLE, ...) means non-empty/dynamic
		}

		if ($tok === '[' || $tok === ']' || $tok === '(' || $tok === ')') {
			continue;
		}

		$meaningful[] = $tok;
	}

	return $meaningful === [];
}

/**
 * Is the token range [start, end) a non-empty literal array? (as opposed to
 * empty, or not a literal array at all -- e.g. a bare variable)
 */
function is_array_literal(array $tokens, int $start, int $end): bool {
	for ($i = $start; $i < $end; $i++) {
		$tok = $tokens[$i];

		if ($tok === '[') {
			return true;
		}

		if (is_array($tok) && $tok[0] === T_ARRAY) {
			return true;
		}

		if (is_array($tok) && ($tok[0] === T_WHITESPACE || $tok[0] === T_COMMENT || $tok[0] === T_DOC_COMMENT)) {
			continue;
		}

		return false; // something else came first: not an array literal
	}

	return false;
}

/**
 * Is the token range [start, end) a single string literal, and if so, is it
 * empty ('' or "")? Returns [isStringLiteral, isEmpty].
 */
function string_literal_emptiness(array $tokens, int $start, int $end): array {
	$saw_string = null;
	$extra      = false;

	for ($i = $start; $i < $end; $i++) {
		$tok = $tokens[$i];

		if (is_array($tok) && ($tok[0] === T_WHITESPACE || $tok[0] === T_COMMENT || $tok[0] === T_DOC_COMMENT)) {
			continue;
		}

		if (is_array($tok) && $tok[0] === T_CONSTANT_ENCAPSED_STRING) {
			if ($saw_string !== null) {
				$extra = true;
			}
			$saw_string = $tok[1];

			continue;
		}

		$extra = true; // anything else: not a plain single-string literal
	}

	if ($saw_string === null || $extra) {
		return [false, false];
	}

	$inner = substr($saw_string, 1, -1);

	return [true, $inner === ''];
}

/**
 * True when the comment is the first thing on its source line, i.e. it
 * isn't a trailing comment tacked onto a preceding code line. The token
 * immediately before it (T_WHITESPACE, T_OPEN_TAG, another comment, ...)
 * carries a newline in its text whenever a line break separates the two.
 */
function comment_starts_its_line(array $tokens, int $comment_token_index): bool {
	if ($comment_token_index === 0) {
		return true;
	}

	$prev      = $tokens[$comment_token_index - 1];
	$prev_text = is_array($prev) ? $prev[1] : $prev;

	return str_contains($prev_text, "\n");
}

/**
 * A same-line trailing comment or an immediately-preceding standalone
 * comment line, matching NO_VALIDATION_COMMENT, exempts a call from the
 * ratchet. $call_line is the 1-based line the call's function name token
 * sits on.
 */
function has_no_validation_comment(array $tokens, int $call_token_index, int $call_line): bool {
	$n = count($tokens);

	// Look backward for the nearest comment token, tracking whether we're
	// still on the call's own line or the one immediately before it.
	for ($i = $call_token_index - 1; $i >= 0; $i--) {
		$tok = $tokens[$i];

		if (!is_array($tok)) {
			continue;
		}

		if ($tok[0] === T_COMMENT || $tok[0] === T_DOC_COMMENT) {
			$comment_line = $tok[2];

			// A comment on the line immediately before the call only
			// counts as a standalone exemption if it starts that line;
			// a trailing comment after code (e.g. "$x = 1; // no-validation: ...")
			// must not exempt the following call.
			$is_previous_line_trailing_comment = $comment_line === $call_line - 1
				&& !comment_starts_its_line($tokens, $i);

			if (!$is_previous_line_trailing_comment
				&& $comment_line >= $call_line - 1
				&& preg_match(NO_VALIDATION_COMMENT, $tok[1])) {
				return true;
			}

			if ($comment_line < $call_line - 1) {
				break;
			}

			continue;
		}

		if ($tok[0] === T_WHITESPACE) {
			// A blank line between the call and a candidate comment breaks
			// the "immediately preceding" requirement.
			if (substr_count($tok[1], "\n") > 1) {
				break;
			}

			continue;
		}

		// Any other real token before the call, on an earlier statement:
		// stop looking once we've walked past the previous statement.
		if ($tok[2] < $call_line - 1) {
			break;
		}
	}

	// Forward: a same-line trailing comment after the call's closing `;`.
	for ($i = $call_token_index; $i < $n; $i++) {
		$tok = $tokens[$i];

		if (!is_array($tok)) {
			continue;
		}

		if ($tok[0] === T_COMMENT || $tok[0] === T_DOC_COMMENT) {
			if ($tok[2] === $call_line && preg_match(NO_VALIDATION_COMMENT, $tok[1])) {
				return true;
			}
		}

		if (is_array($tok) && $tok[2] > $call_line) {
			break;
		}
	}

	return false;
}

/**
 * Scan one PHP source string and return every validateInput()/
 * form_input_validate() call site as ['line'=>int, 'func'=>string, 'category'=>string].
 */
function validator_scan_source(string $src): array {
	if (!str_contains($src, 'validateInput') && !str_contains($src, 'form_input_validate')) {
		return [];
	}

	$tokens = @token_get_all($src);
	$n      = count($tokens);
	$sites  = [];

	for ($i = 0; $i < $n; $i++) {
		$tok = $tokens[$i];

		if (!is_array($tok) || $tok[0] !== T_STRING) {
			continue;
		}

		$name         = $tok[1];
		$is_validator = ($name === 'validateInput');
		$is_legacy    = ($name === 'form_input_validate');

		if (!$is_validator && !$is_legacy) {
			continue;
		}

		// The token before the name decides whether this is a call site at all.
		$p = $i - 1;

		while ($p >= 0 && is_array($tokens[$p]) && $tokens[$p][0] === T_WHITESPACE) {
			$p--;
		}
		$double_colon = defined('T_DOUBLE_COLON') ? T_DOUBLE_COLON : T_PAAMAYIM_NEKUDOTAYIM;
		$prev         = ($p >= 0) ? $tokens[$p] : null;

		// `function form_input_validate(` is the declaration in lib/functions.php,
		// not a call of it; counting it inflates the reported totals.
		if (is_array($prev) && $prev[0] === T_FUNCTION) {
			continue;
		}

		if ($is_validator) {
			// Must be preceded by `CactiValidator::` -- '::' tokenizes as
			// T_DOUBLE_COLON (or T_PAAMAYIM_NEKUDOTAYIM on older PHP), not a
			// bare '::' string, so it has to be matched by token id.
			if ($p < 1 || !is_array($prev) || $prev[0] !== $double_colon ||
				!is_array($tokens[$p - 1]) || $tokens[$p - 1][1] !== 'CactiValidator') {
				continue;
			}
		} elseif (is_array($prev) && ($prev[0] === T_OBJECT_OPERATOR ||
			$prev[0]                              === T_NULLSAFE_OBJECT_OPERATOR || $prev[0] === $double_colon)) {
			// A method that happens to share the name, not the global helper.
			continue;
		}

		$j = $i + 1;

		while ($j < $n && is_array($tokens[$j]) && ($tokens[$j][0] === T_WHITESPACE || $tokens[$j][0] === T_COMMENT || $tokens[$j][0] === T_DOC_COMMENT)) {
			$j++;
		}

		if ($j >= $n || $tokens[$j] !== '(') {
			continue;
		}

		[$args, $close] = split_call_args($tokens, $j);

		$target_arg_index = $is_validator ? 2 : 2; // 3rd argument for both signatures

		if (!isset($args[$target_arg_index])) {
			continue;
		}

		[$arg_start, $arg_end] = $args[$target_arg_index];
		$line                  = $tok[2];

		if ($is_validator) {
			if (is_empty_array_literal($tokens, $arg_start, $arg_end)) {
				$category = has_no_validation_comment($tokens, $i, $line) ? 'documented-empty' : 'empty';
			} elseif (is_array_literal($tokens, $arg_start, $arg_end)) {
				$category = 'constrained';
			} else {
				$category = 'dynamic';
			}
		} else {
			[$is_string, $is_empty] = string_literal_emptiness($tokens, $arg_start, $arg_end);

			if (!$is_string) {
				$category = 'dynamic';
			} elseif ($is_empty) {
				$category = has_no_validation_comment($tokens, $i, $line) ? 'documented-empty' : 'empty';
			} else {
				$category = 'constrained';
			}
		}

		$sites[] = [
			'line'     => $line,
			'func'     => $is_validator ? 'CactiValidator::validateInput' : 'form_input_validate',
			'category' => $category,
		];

		if ($close !== null) {
			$i = $close; // resume after this call
		}
	}

	return $sites;
}

// Stop here when included as a library (tests require the functions only).
if (defined('VALIDATOR_AUDIT_LIB_ONLY')) {
	return;
}

$files   = validator_list_php_files();
$results = [];  // path => array of sites

foreach ($files as $path) {
	$src = file_get_contents($path);

	if ($src === false) {
		continue;
	}

	$sites = validator_scan_source($src);

	if ($sites !== []) {
		$results[$path] = $sites;
	}
}

// ---- aggregate ----
$per_file = [];
$totals   = ['constrained' => 0, 'documented-empty' => 0, 'empty' => 0, 'dynamic' => 0];

foreach ($results as $path => $sites) {
	foreach ($sites as $s) {
		$per_file[$path][$s['category']] = ($per_file[$path][$s['category']] ?? 0) + 1;
		$totals[$s['category']]++;
	}
}

// ---- output modes ----
$mode = $argv[1] ?? '--summary';

if ($mode === '--list') {
	$want = $argv[2] ?? 'empty';
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
	$out = ['totals' => $totals, 'files' => []];
	ksort($per_file);

	foreach ($per_file as $path => $cats) {
		$out['files'][$path] = [
			'empty' => $cats['empty'] ?? 0,
		];
	}
	print json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
	exit(0);
}

if ($mode === '--write-baseline') {
	$baseline = [];
	ksort($per_file);

	foreach ($per_file as $path => $cats) {
		$e = $cats['empty'] ?? 0;

		if ($e > 0) {
			$baseline[$path] = $e;
		}
	}
	file_put_contents(BASELINE, json_encode($baseline, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
	printf("Wrote %s: %d files, %d undocumented-empty-validation site(s).\n", BASELINE, count($baseline), array_sum($baseline));
	exit(0);
}

if ($mode === '--check') {
	if (!file_exists(BASELINE)) {
		fwrite(STDERR, 'Baseline missing: ' . BASELINE . " (run --write-baseline).\n");
		exit(2);
	}

	$baseline = json_decode(file_get_contents(BASELINE), true);

	if (!is_array($baseline)) {
		fwrite(STDERR, "Baseline is not valid JSON.\n");
		exit(2);
	}

	$regressions = [];
	$current     = [];

	foreach ($per_file as $path => $cats) {
		if (($cats['empty'] ?? 0) > 0) {
			$current[$path] = $cats['empty'];
		}
	}

	foreach ($current as $path => $v) {
		$allowed = $baseline[$path] ?? 0;

		if ($v > $allowed) {
			$regressions[] = sprintf('  %s: %d undocumented-empty site(s), baseline %d', $path, $v, $allowed);
		}
	}

	if ($regressions !== []) {
		fwrite(STDERR, "Validator constraint ratchet failed. New undocumented no-op validation:\n");
		fwrite(STDERR, implode("\n", $regressions) . "\n");
		fwrite(STDERR, "\nEither add a real constraint/regex, or document why none is needed with a\n");
		fwrite(STDERR, "'// no-validation: <reason>' comment on or immediately before the call.\n");
		fwrite(STDERR, "Then update the baseline with: php tests/tools/validator_constraint_audit.php --write-baseline\n");
		exit(1);
	}

	$stale = [];

	foreach ($baseline as $path => $allowed) {
		$now = $current[$path] ?? 0;

		if ($now < $allowed) {
			$stale[] = sprintf('  %s: baseline %d, now %d', $path, $allowed, $now);
		}
	}

	printf("Validator constraint ratchet passed. %d undocumented-empty site(s) across %d file(s).\n",
		array_sum($current), count($current));

	if ($stale !== []) {
		printf("%d file(s) improved below baseline; refresh with --write-baseline:\n%s\n",
			count($stale), implode("\n", $stale));
	}

	exit(0);
}

// default: human summary
printf("CactiValidator::validateInput() / form_input_validate() call sites:\n\n");

foreach ($totals as $cat => $n) {
	printf("  %-18s %5d\n", $cat, $n);
}
printf("  %-18s %5d\n", 'TOTAL', array_sum($totals));

printf("\nUndocumented no-op validation (migration target) by file:\n\n");
$rows = [];

foreach ($per_file as $path => $cats) {
	$e = $cats['empty'] ?? 0;

	if ($e) {
		$rows[$path] = $e;
	}
}
arsort($rows);
printf("  %-52s %6s\n", 'file', 'empty');

foreach ($rows as $path => $c) {
	printf("  %-52s %6d\n", $path, $c);
}

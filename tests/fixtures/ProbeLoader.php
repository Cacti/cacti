<?php
// Copyright (C) 2026 The Cacti Group. Licensed under GPL-2.0-or-later.
// Declares one production function from a source file into a probe process.
// The function keeps its original line numbers, and the copy is mapped back to
// its source file, so Xdebug evidence from a probe is credited to that file.
function probe_load(string $path, string $name) : void {
	$tokens  = token_get_all(file_get_contents($path));
	$code    = '';
	$capture = false;
	$depth   = 0;
	$started = false;
	$line    = 1;

	foreach ($tokens as $i => $token) {
		if (!$capture && is_array($token) && $token[0] === T_FUNCTION) {
			$j = $i + 1;

			while (is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
				$j++;
			}
			$capture = is_array($tokens[$j]) && $tokens[$j][1] === $name;
			$line    = $token[2];
		}

		if (!$capture) {
			continue;
		}
		$code .= is_array($token) ? $token[1] : $token;

		if ($token === '{' || (is_array($token) && in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
			$depth++;
			$started = true;
		}

		if ($token === '}' && --$depth === 0 && $started) {
			$file = tempnam(sys_get_temp_dir(), 'probe');

			try {
				file_put_contents($file, '<?php ' . str_repeat("\n", $line - 1) . $code);
				$GLOBALS['cacti_fixture_source_map'][$file] = realpath($path);
				include $file;
			} finally {
				unlink($file);
			}

			return;
		}
	}

	throw new RuntimeException('Missing production function: ' . $name);
}

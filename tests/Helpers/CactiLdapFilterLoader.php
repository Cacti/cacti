<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

function cacti_test_load_cacti_ldap_filter(string $src) : void {
	if (function_exists('cacti_ldap_filter')) {
		return;
	}

	$start = strpos($src, 'function cacti_ldap_filter(');

	if ($start === false) {
		throw new RuntimeException('cacti_ldap_filter() not found');
	}

	$depth = 0;
	$len   = strlen($src);

	for ($i = strpos($src, '{', $start); $i < $len; $i++) {
		if ($src[$i] === '{') {
			$depth++;
		} elseif ($src[$i] === '}') {
			$depth--;

			if ($depth === 0) {
				// nosemgrep: php.lang.security.eval-use.eval-use -- test-only evaluation of a repository-owned function
				eval(substr($src, $start, $i - $start + 1));

				return;
			}
		}
	}

	throw new RuntimeException('cacti_ldap_filter() is unbalanced');
}

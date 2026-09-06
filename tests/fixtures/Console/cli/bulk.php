<?php

require_once dirname(__DIR__, 4) . '/include/cli_only.php';

$lines = (int) ($argv[1] ?? 1);

for ($i = 0; $i < $lines; $i++) {
	fwrite(STDOUT, str_repeat('x', 99) . "\n");
}

exit(0);

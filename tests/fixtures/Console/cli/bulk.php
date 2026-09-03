<?php

$lines = (int) ($argv[1] ?? 1);

for ($i = 0; $i < $lines; $i++) {
	fwrite(STDOUT, str_repeat('x', 99) . "\n");
}

exit(0);

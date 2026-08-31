<?php

$arguments = array_slice($argv, 1);
$exitCode  = 0;

foreach ($arguments as $argument) {
	if (str_starts_with($argument, '--exit=')) {
		$exitCode = (int) substr($argument, strlen('--exit='));
	}
}

fwrite(STDOUT, implode('|', $arguments) . PHP_EOL);
fwrite(STDERR, 'probe-error' . PHP_EOL);

exit($exitCode);

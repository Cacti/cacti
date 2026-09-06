<?php

require_once dirname(__DIR__, 4) . '/include/cli_only.php';

/* Publish the pid so the runner can signal exactly this process, then wait
 * long enough to be killed but not long enough to stall a suite if it is not. */
if (isset($argv[1])) {
	file_put_contents($argv[1], (string) getmypid());
}

fwrite(STDOUT, "ready\n");
sleep(5);

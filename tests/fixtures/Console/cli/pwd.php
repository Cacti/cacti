<?php

require_once dirname(__DIR__, 4) . '/include/cli_only.php';

fwrite(STDOUT, (string) getcwd());

exit(0);

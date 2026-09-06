<?php

require_once dirname(__DIR__, 4) . '/include/cli_only.php';

fwrite(STDOUT, json_encode(array_slice($argv, 1), JSON_THROW_ON_ERROR));
fwrite(STDERR, 'probe-error');

exit(23);

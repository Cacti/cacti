<?php

fwrite(STDOUT, json_encode(array_slice($argv, 1), JSON_THROW_ON_ERROR));
fwrite(STDERR, 'probe-error');

exit(23);

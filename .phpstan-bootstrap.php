<?php

define('PHP_TESTING', true);

// PHP_TESTING alone no longer arms the DB short-circuit in include/global.php;
// the CACTI_TEST_BOOTSTRAP env var must also be set.
putenv('CACTI_TEST_BOOTSTRAP=1');
$_ENV['CACTI_TEST_BOOTSTRAP'] = '1';

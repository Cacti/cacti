<?php

declare(strict_types = 1);

include(__DIR__ . '/include/vendor/autoload.php');

use Rector\Config\RectorConfig;
use Rector\Php54\Rector\Array_\LongArrayToShortArrayRector;

return RectorConfig::configure()
	->withRootFiles()
	->withPaths([
		__DIR__ . '/include',
		__DIR__ . '/lib',
		__DIR__ . '/scripts',
	])
	->withSkip([
		__DIR__ . '/include/vendor',
		__DIR__ . '/myadmin*',
		LongArrayToShortArrayRector::class
	])
	->withPhpSets()
	->withTypeCoverageLevel(0)
	->withDeadCodeLevel(0)
	->withCodeQualityLevel(0);

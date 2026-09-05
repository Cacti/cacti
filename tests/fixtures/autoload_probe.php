<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Resolves the shipped Cacti classes through nothing but the committed
 * autoloader, the way a deployment does. A fresh process is the point: the
 * test runner has its own autoloader registered, which would answer for the
 * committed maps even when they carry no Cacti prefix at all.
 */

declare(strict_types = 1);

require_once dirname(__DIR__, 2) . '/include/vendor/autoload.php';

/*
 * Both Cacti classes import a Symfony component, and Cacti ships only part of
 * include/vendor, so an uninstalled tree cannot resolve either one. Report
 * that separately: it is not the same failure as a map with no Cacti prefix.
 */
$resolved = [
	'installed' => class_exists('Symfony\\Component\\Filesystem\\Path')
		&& class_exists('Symfony\\Component\\Console\\Application'),
];

foreach (['Cacti\\Filesystem\\CactiPath', 'Cacti\\Console\\CactiApplication'] as $class) {
	$resolved[$class] = $resolved['installed'] && class_exists($class);
}

print json_encode($resolved, JSON_THROW_ON_ERROR);

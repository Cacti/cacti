<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 | Licensed under the GNU General Public License, version 2 or later.     |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

require_once dirname(__DIR__, 3) . '/lib/ping.php';
require_once dirname(__DIR__, 3) . '/lib/ldap.php';
require_once dirname(__DIR__, 3) . '/lib/mib_cache.php';
require_once dirname(__DIR__, 3) . '/lib/mib_parser.php';

$contracts = array();

foreach (array('Net_Ping', 'Ldap', 'MibParser') as $class) {
	foreach ((new ReflectionClass($class))->getMethods() as $method) {
		if ($method->getDeclaringClass()->getName() !== $class) {
			continue;
		}

		$parameters = array();

		foreach ($method->getParameters() as $parameter) {
			$parameters[] = array(
				'name'      => $parameter->getName(),
				'reference' => $parameter->isPassedByReference(),
				'variadic'  => $parameter->isVariadic(),
				'type'      => $parameter->hasType() ? (string) $parameter->getType() : null,
				'optional'  => $parameter->isOptional(),
				'default'   => !$parameter->isDefaultValueAvailable() ? null :
					($parameter->isDefaultValueConstant() ?
						array('constant' => $parameter->getDefaultValueConstantName()) :
						array('value' => $parameter->getDefaultValue())),
			);
		}

		$contracts[$class . '::' . $method->getName()] = array(
			'public'     => $method->isPublic(),
			'static'     => $method->isStatic(),
			'parameters' => $parameters,
			'return'     => $method->hasReturnType() ? (string) $method->getReturnType() : null,
		);
	}
}

echo json_encode($contracts, JSON_THROW_ON_ERROR);

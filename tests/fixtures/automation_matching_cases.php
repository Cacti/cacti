<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

// Rules: id, sysDescr, sysOid, sysName, sequence; devices: description, OID, name.
return [
	'missing required description'                             => ['rules' => [[1, 'Router', '', '', 1]], 'device' => ['', '1.3.6.1.4.1.9', 'edge'], 'expected' => false],
	'missing required object ID'                               => ['rules' => [[1, '', '1.3.6.1.4.1.9', '', 1]], 'device' => ['Router', '', 'edge'], 'expected' => false],
	'missing required name'                                    => ['rules' => [[1, '', '', 'edge', 1]], 'device' => ['Router', '1.3.6.1.4.1.9', ''], 'expected' => false],
	'all fields missing'                                       => ['rules' => [[1, 'Router', '1.3.6.1.4.1.9', 'edge', 1]], 'device' => ['', '', ''], 'expected' => false],
	'partial evidence must satisfy all configured fields'      => ['rules' => [[1, 'Router', '1.3.6.1.4.1.9', '', 1]], 'device' => ['Router', '', ''], 'expected' => false],
	'present mismatched field'                                 => ['rules' => [[1, 'Router', '', '', 1]], 'device' => ['Switch', '', ''], 'expected' => false],
	'existing substring behavior'                              => ['rules' => [[1, 'Router', '1.3.6.1.4.1.9', 'edge', 1]], 'device' => ['Vendor Router OS', '1.3.6.1.4.1.9.1', 'dc-edge-01'], 'expected' => 1],
	'existing LIKE wildcard behavior'                          => ['rules' => [[1, 'Route_', '', '', 1]], 'device' => ['Router', '', ''], 'expected' => 1],
	'existing SQL regular expression behavior'                 => ['rules' => [[1, 'Rou.ter', '', '', 1]], 'device' => ['/RouXter/', '', ''], 'expected' => 1],
	'lowest sequence wins'                                     => ['rules' => [[1, 'Router', '', '', 50], [2, 'Router', '', '', 10]], 'device' => ['Router', '', ''], 'expected' => 2],
	'skip unsupported specific rule for eligible generic rule' => ['rules' => [[1, '', '1.3.6.1.4.1.9', '', 1], [2, 'Router', '', '', 10]], 'device' => ['Router', '', ''], 'expected' => 2],
	'explicit unrestricted rule remains usable'                => ['rules' => [[1, '', '', '', 1]], 'device' => ['', '', ''], 'expected' => 1],
	'NULL rule fields are unconfigured for missing evidence'   => ['rules' => [[1, null, null, null, 1]], 'device' => ['', '', ''], 'expected' => 1],
	'present evidence preserves existing NULL rule behavior'   => ['rules' => [[1, null, '', '', 1]], 'device' => ['Router', '', ''], 'expected' => false],
	'unknown device without rules'                             => ['rules' => [], 'device' => ['', '', ''], 'expected' => false],
	'device values remain SQL parameters'                      => ['rules' => [[1, 'Router', '', '', 1]], 'device' => ["' OR 1=1 --", '', ''], 'expected' => false],
];

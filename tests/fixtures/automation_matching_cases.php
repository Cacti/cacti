<?php
/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */

/* Rules: id, sysDescr, sysOid, sysName, sequence; devices: description, OID, name. */
return array(
	'unavailable description does not restrict rules' => array('rules' => array(array(1, 'Router', '', '', 1)), 'device' => array('', '1.3.6.1.4.1.9', 'edge'), 'expected' => 1),
	'unavailable object ID does not restrict rules' => array('rules' => array(array(1, '', '1.3.6.1.4.1.9', '', 1)), 'device' => array('Router', '', 'edge'), 'expected' => 1),
	'unavailable name does not restrict rules' => array('rules' => array(array(1, '', '', 'edge', 1)), 'device' => array('Router', '1.3.6.1.4.1.9', ''), 'expected' => 1),
	'all fields missing preserves sequence selection' => array('rules' => array(array(1, 'Router', '1.3.6.1.4.1.9', 'edge', 1)), 'device' => array('', '', ''), 'expected' => 1),
	'partial evidence matches only available fields' => array('rules' => array(array(1, 'Router', '1.3.6.1.4.1.9', '', 1)), 'device' => array('Router', '', ''), 'expected' => 1),
	'present mismatched field' => array('rules' => array(array(1, 'Router', '', '', 1)), 'device' => array('Switch', '', ''), 'expected' => false),
	'existing substring behavior' => array('rules' => array(array(1, 'Router', '1.3.6.1.4.1.9', 'edge', 1)), 'device' => array('Vendor Router OS', '1.3.6.1.4.1.9.1', 'dc-edge-01'), 'expected' => 1),
	'existing LIKE wildcard behavior' => array('rules' => array(array(1, 'Route_', '', '', 1)), 'device' => array('Router', '', ''), 'expected' => 1),
	'existing SQL regular expression behavior' => array('rules' => array(array(1, 'Rou.ter', '', '', 1)), 'device' => array('/RouXter/', '', ''), 'expected' => 1),
	'lowest sequence wins' => array('rules' => array(array(1, 'Router', '', '', 50), array(2, 'Router', '', '', 10)), 'device' => array('Router', '', ''), 'expected' => 2),
	'partial evidence retains configured rule precedence' => array('rules' => array(array(1, '', '1.3.6.1.4.1.9', '', 1), array(2, 'Router', '', '', 10)), 'device' => array('Router', '', ''), 'expected' => 1),
	'explicit unrestricted rule remains usable' => array('rules' => array(array(1, '', '', '', 1)), 'device' => array('', '', ''), 'expected' => 1),
	'NULL rule fields are unconfigured for missing evidence' => array('rules' => array(array(1, null, null, null, 1)), 'device' => array('', '', ''), 'expected' => 1),
	'present evidence preserves existing NULL rule behavior' => array('rules' => array(array(1, null, '', '', 1)), 'device' => array('Router', '', ''), 'expected' => false),
	'unknown device without rules' => array('rules' => array(), 'device' => array('', '', ''), 'expected' => false),
	'device values remain SQL parameters' => array('rules' => array(array(1, 'Router', '', '', 1)), 'device' => array("' OR 1=1 --", '', ''), 'expected' => false),
);

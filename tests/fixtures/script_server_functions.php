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

// Script Server functions used by tests/Unit/Security/Shell/ScriptServerContainmentTest.php

function ss_fixture_echo(string $value = 'none') : string {
	return ' value:' . $value . ' ';
}

function ss_fixture_throws() : string {
	throw new RuntimeException('fixture failure');
}

function ss_fixture_typed(int $value) : string {
	return (string) $value;
}

function ss_fixture_array() : array {
	return ['not', 'scalar'];
}

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

// Pest bootstrap: bind PHPUnit\Framework\TestCase to all suites under tests/Unit
// and tests/integration so test files can use Pest's it()/test() syntax or plain
// PHPUnit class style interchangeably.
uses(PHPUnit\Framework\TestCase::class)->in('Unit', 'integration');

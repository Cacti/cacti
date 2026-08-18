<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

// Mirror include/global_constants.php so the module can be loaded on its own.
foreach ([
	'RRD_ALIGN_LEFT'      => 'left',
	'RRD_ALIGN_RIGHT'     => 'right',
	'RRD_ALIGN_JUSTIFIED' => 'justified',
	'RRD_ALIGN_CENTER'    => 'center'] as $name => $value) {

	if (!defined($name)) {
		define($name, $value);
	}
}

if (!defined('CACTI_PATH_LIBRARY')) {
	define('CACTI_PATH_LIBRARY', dirname(__DIR__, 2) . '/lib');
}

require_once CACTI_PATH_LIBRARY . '/rrd_graph_item.php';

test('alpha accepts a hex pair', function () : void {
	expect(rrd_graph_item_alpha('FF'))->toBe('FF');
	expect(rrd_graph_item_alpha('00'))->toBe('00');
	expect(rrd_graph_item_alpha(' 7f '))->toBe('7f');
});

test('alpha rejects anything that is not two hex digits', function () : void {
	expect(rrd_graph_item_alpha('F'))->toBe('');
	expect(rrd_graph_item_alpha('FFF'))->toBe('');
	expect(rrd_graph_item_alpha('GG'))->toBe('');
	expect(rrd_graph_item_alpha(''))->toBe('');
	expect(rrd_graph_item_alpha(null))->toBe('');
});

test('alpha cannot carry a shell payload', function () : void {
	expect(rrd_graph_item_alpha('FF;id'))->toBe('');
	expect(rrd_graph_item_alpha('`id`'))->toBe('');
});

test('number list accepts one or more numbers', function () : void {
	expect(rrd_graph_item_number_list('5'))->toBe('5');
	expect(rrd_graph_item_number_list('5,10'))->toBe('5,10');
	expect(rrd_graph_item_number_list('1.5,2.25,3'))->toBe('1.5,2.25,3');
	expect(rrd_graph_item_number_list(' 4,8 '))->toBe('4,8');
});

test('number list treats an empty value as unset', function () : void {
	expect(rrd_graph_item_number_list(''))->toBe('');
	expect(rrd_graph_item_number_list('   '))->toBe('');
	expect(rrd_graph_item_number_list(null))->toBe('');
});

test('number list rejects a non numeric part', function () : void {
	expect(rrd_graph_item_number_list('5,abc'))->toBe('');
	expect(rrd_graph_item_number_list('5,'))->toBe('');
	expect(rrd_graph_item_number_list('abc'))->toBe('');
});

test('number list cannot carry a shell payload', function () : void {
	// GHSA-vq9v-6ggh-phhr: dashes is varchar(20) and reached shell_exec unescaped
	expect(rrd_graph_item_number_list('1;touch /tmp/pwn;#'))->toBe('');
	expect(rrd_graph_item_number_list('5,10;id'))->toBe('');
	expect(rrd_graph_item_number_list('$(id)'))->toBe('');
});

test('textalign accepts the RRDtool keywords', function () : void {
	expect(rrd_graph_item_textalign('left'))->toBe('left');
	expect(rrd_graph_item_textalign('right'))->toBe('right');
	expect(rrd_graph_item_textalign('center'))->toBe('center');
	// the editor stores RRD_ALIGN_JUSTIFIED, which is 'justified' not 'justify'
	expect(rrd_graph_item_textalign(' justified '))->toBe('justified');
});

test('textalign rejects anything outside the keyword set', function () : void {
	expect(rrd_graph_item_textalign('LEFT'))->toBe('');
	expect(rrd_graph_item_textalign('justify'))->toBe('');
	expect(rrd_graph_item_textalign('left;id'))->toBe('');
	expect(rrd_graph_item_textalign(''))->toBe('');
	expect(rrd_graph_item_textalign(null))->toBe('');
});

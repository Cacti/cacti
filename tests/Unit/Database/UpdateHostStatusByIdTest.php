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
*/

/*
 * update_host_status() reads its row with "WHERE id = ?" but the closing
 * UPDATE previously matched "WHERE hostname = ?" using $host['hostname'].
 * Hostnames are not unique in Cacti, so that write landed on every device
 * sharing the polled device's hostname. The fix keys the write on the same
 * id the read used.
 *
 * This is the structural half: the shipped statement must select and bind
 * on id. Issue7427UpdateHostStatusByIdIntegrationTest.php runs the function
 * against a real connection and proves the sibling row survives.
 */

$source = file_get_contents(__DIR__ . '/../../../lib/functions.php');

$statement = static function () use ($source) : string {
	$pos = strpos($source, "db_execute_prepared('UPDATE host SET");

	expect($pos)->not->toBeFalse();

	return substr($source, $pos, 900);
};

test('the host status UPDATE is scoped by id', function () use ($statement) {
	expect($statement())
		->toContain('WHERE id = ?')
		->not->toContain('WHERE hostname = ?');
});

test('the host status UPDATE binds the id it read the row with', function () use ($statement) {
	expect($statement())
		->toContain('$host[\'id\']')
		->not->toContain('$host[\'hostname\']');
});

test('the host status UPDATE still excludes soft-deleted devices', function () use ($statement) {
	expect($statement())->toContain('AND deleted = ""');
});

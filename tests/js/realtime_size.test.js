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

'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const root = path.join(__dirname, '..', '..');
const clientSource = fs.readFileSync(path.join(root, 'include', 'realtime.js'), 'utf8');

function extractFunction(name) {
	const start = clientSource.indexOf(`function ${name}(`);
	const bodyStart = clientSource.indexOf('{', start);
	let depth = 0;

	assert.notEqual(start, -1, `${name}() must exist`);

	for (let offset = bodyStart; offset < clientSource.length; offset++) {
		if (clientSource[offset] === '{') {
			depth++;
		} else if (clientSource[offset] === '}') {
			depth--;

			if (depth === 0) {
				return clientSource.slice(start, offset + 1);
			}
		}
	}

	throw new Error(`Unable to extract ${name}()`);
}

function readRealtimeSize(value) {
	const context = vm.createContext({
		$: () => ({ val: () => value }),
		parseInt,
	});

	vm.runInContext(extractFunction('getRealtimeSize'), context);

	return context.getRealtimeSize();
}

test('inline realtime requests preserve the stored size when no control exists', () => {
	assert.equal(readRealtimeSize(null), null);
	assert.match(clientSource, /sizeOption\s*=\s*size == null \? '' : '&size='\+size/);
	assert.doesNotMatch(clientSource, /size = 100;/);
});

test('inline realtime requests use the selected percentage', () => {
	assert.equal(readRealtimeSize('50'), 50);
	assert.equal(readRealtimeSize('100'), 100);
});

test('both inline views render the shared persisted size control', () => {
	for (const file of ['lib/html_graph.php', 'lib/html_tree.php']) {
		const source = fs.readFileSync(path.join(root, file), 'utf8');

		assert.match(source, /<select(?: name='size')? id='size'>/);
		assert.match(source, /foreach \(\$realtime_sizes as \$size => \$text\)/);
		assert.match(source, /#graph_start, #ds_step, #size/);
	}
});

test('the tree size control falls back to the persisted user preference', () => {
	const source = fs.readFileSync(path.join(root, 'lib', 'html_tree.php'), 'utf8');

	assert.match(source, /isset\(\$_SESSION\['sess_realtime_size'\]\)/);
	assert.match(source, /read_user_setting\('realtime_size', \$realtime_default_size\)/);
	assert.match(source, /\$size == \$selected_size/);
});

test('session and tree sizes are clamped to the shared allowlist', () => {
	const graph = fs.readFileSync(path.join(root, 'lib', 'html_graph.php'), 'utf8');
	const tree = fs.readFileSync(path.join(root, 'lib', 'html_tree.php'), 'utf8');

	assert.match(graph, /array_key_exists\(\$realtime_size, \$realtime_sizes\)/);
	assert.match(graph, /\$realtime_size = \$realtime_default_size/);
	assert.match(tree, /array_key_exists\(\$selected_size, \$realtime_sizes\)/);
	assert.match(tree, /\$selected_size = \$realtime_default_size/);
});

test('server rendering applies the shared 50 percent default', () => {
	const arrays = fs.readFileSync(path.join(root, 'include/global_arrays.php'), 'utf8');
	const endpoint = fs.readFileSync(path.join(root, 'graph_realtime.php'), 'utf8');

	assert.match(arrays, /\$realtime_default_size = 50;/);
	assert.match(arrays, /\$realtime_sizes = array\([\s\S]*100 => '100%'[\s\S]*40\s+=> '40%'/);
	assert.doesNotMatch(endpoint, /read_user_setting\('realtime_size', 100\)/);
	assert.match(endpoint, /if \(\$size < 100\)/);
	assert.match(endpoint, /foreach \(\$realtime_sizes as \$key => \$value\)/);
	assert.equal((endpoint.match(/array_key_exists\(\$size, \$realtime_sizes\)/g) || []).length, 2);
	assert.equal((endpoint.match(/set_request_var\('size', \$size\)/g) || []).length, 2);
	assert.equal((endpoint.match(/\$_SESSION\['sess_realtime_size'\] = \$size/g) || []).length, 2);
});

import assert from 'node:assert/strict';
import {readFileSync, readdirSync} from 'node:fs';
import {test} from 'node:test';
import {assetMap} from '../../build/sync-js.mjs';

const inventory = JSON.parse(readFileSync(new URL('../../build/legacy-js.json', import.meta.url), 'utf8'));
const managed = new Set(Object.values(assetMap));
const known = new Set([...managed, ...Object.keys(inventory.files), ...inventory.applicationFiles]);

test('every root browser script is generated or has an explicit maintenance owner', () => {
	for (const file of readdirSync('include/js').filter(name => name.endsWith('.js'))) {
		assert.ok(known.has('include/js/' + file), 'Unclassified browser script: ' + file);
	}
});

test('the shared header loads only inventoried assets', () => {
	const header = readFileSync('lib/html.php', 'utf8');
	for (const match of header.matchAll(/get_md5_include_js\('(include\/js\/[^']+\.js)'/g)) {
		assert.ok(known.has(match[1]), 'Unclassified header dependency: ' + match[1]);
	}
});

test('retained adapters do not overlap generated libraries and explain their compatibility requirement', () => {
	for (const [file, details] of Object.entries(inventory.files)) {
		assert.equal(managed.has(file), false, file);
		assert.ok(details.reason.length > 0, file);
		readFileSync(file);
	}
});

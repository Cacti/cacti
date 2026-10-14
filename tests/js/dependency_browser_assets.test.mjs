import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { test } from 'node:test';
import vm from 'node:vm';

const root = resolve(import.meta.dirname, '../..');
const dependencies = JSON.parse(readFileSync(resolve(root, 'package.json'), 'utf8')).dependencies;

test('the pinned Chart build loads through Cacti\'s classic script tag', () => {
	const context = { window: {}, self: {} };
	const source = readFileSync(resolve(root, 'include/js/Chart.js'), 'utf8');
	vm.runInNewContext(source, context);
	assert.equal(typeof context.Chart, 'function');
	assert.ok(source.includes('Chart.js v' + dependencies['chart.js']));
	if (context.Chart.version !== undefined) assert.equal(context.Chart.version, dependencies['chart.js']);
});

for (const name of ['hotkeys.js', 'hotkeys.min.js']) {
	test(`${name} preserves shortcut registration, triggering, and removal`, () => {
		const target = { addEventListener() {}, removeEventListener() {} };
		const context = { window: { ...target }, document: { ...target } };
		vm.runInNewContext(readFileSync(resolve(root, 'include/js/vendor/hotkeys', name), 'utf8'), context);
		const hotkeys = context.window.hotkeys;
		assert.equal(typeof hotkeys, 'function');
		let calls = 0;
		hotkeys('ctrl+g', () => { calls++; });
		hotkeys.trigger('ctrl+g');
		hotkeys.unbind('ctrl+g');
		hotkeys.trigger('ctrl+g');
		assert.equal(calls, 1);
	});
}

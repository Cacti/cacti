/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */

import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
import {copyFileSync, mkdirSync, mkdtempSync, readFileSync, rmSync, writeFileSync} from 'node:fs';
import {tmpdir} from 'node:os';
import {join} from 'node:path';
import {test} from 'node:test';

test('accessibility summaries preserve selectors without breaking Markdown table cells', () => {
	const directory = mkdtempSync(join(tmpdir(), 'cacti-a11y-summary-'));
	const selectors = [
		String.raw`div\:item[data-label="a\|b"]`,
		'[data-label="`*_x_*`<em>x</em>&"]',
		'div\r\n[data-label="line break"]',
	];
	try {
		const results = join(directory, 'test-results/a11y');
		mkdirSync(join(results, 'cases'), {recursive: true});
		copyFileSync(new URL('../e2e/a11y-summary.mjs', import.meta.url), join(directory, 'a11y-summary.mjs'));
		writeFileSync(join(results, 'cases/selector.json'), JSON.stringify({
			theme: 'classic', mode: 'light', dir: 'ltr', page: '/index.php',
			focusFailures: 0, focus: [], layoutPass: true,
			violations: [{id: 'selector-test', impact: 'moderate', help: 'Synthetic selector check', nodes: 3, targets: selectors}],
		}));
		execFileSync(process.execPath, [join(directory, 'a11y-summary.mjs')]);
		const markdown = readFileSync(join(results, 'summary.md'), 'utf8');
		const cells = [...markdown.matchAll(/<code>(.*?)<\/code>/g)].map(match => match[1]);
		assert.equal(cells.length, selectors.length);
		assert.deepEqual(cells.map(cell => cell.replace(/&#(\d+);/g, (_, code) => String.fromCodePoint(Number(code)))), selectors);
		for (const cell of cells) {
			assert.doesNotMatch(cell.replace(/&#\d+;/g, ''), /[\\`|&<>\r\n]/);
		}
		assert.equal(markdown.split('\n').filter(line => line.startsWith('| selector-test |')).length, 1);
		assert.deepEqual(JSON.parse(readFileSync(join(results, 'summary.json'), 'utf8')).rules[0].selectors, selectors);
	} finally {
		rmSync(directory, {recursive: true, force: true});
	}
});

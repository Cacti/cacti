import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { mkdirSync, mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join, resolve } from 'node:path';
import { test } from 'node:test';
import { assetMap, syncAssets } from '../../build/sync-js.mjs';

function fixture() {
	const root = mkdtempSync(join(tmpdir(), 'cacti-js-assets-'));

	for (const [source] of Object.entries(assetMap)) {
		const path = join(root, source);
		mkdirSync(dirname(path), { recursive: true });
		writeFileSync(path, fixtureContent(source));
	}

	return root;
}

function fixtureContent(source) {
	if (source === 'node_modules/screenfull/index.js') {
		return `// fixture:${source}\nlet screenfull = {};\nexport default screenfull;\n`;
	}

	return `fixture:${source}\n`;
}

function assertSynced(root) {
	for (const [source, destination] of Object.entries(assetMap)) {
		const actual = readFileSync(join(root, destination), 'utf8');

		if (source === 'node_modules/screenfull/index.js') {
			assert.equal(actual, `// fixture:${source}\nlet screenfull = {};\nwindow.screenfull = screenfull;\n`);
		} else {
			assert.equal(actual, fixtureContent(source));
		}
	}
}

test('syncAssets copies every managed asset and reports each destination', () => {
	const root = fixture();
	const messages = [];

	syncAssets(root, message => messages.push(message));

	assertSynced(root);
	assert.deepEqual(
		messages,
		Object.values(assetMap).map(destination => `synced ${destination}`),
	);
});

test('the command-line entry point builds a release-tree checkout', () => {
	const root = fixture();
	const script = resolve('build/sync-js.mjs');

	execFileSync(process.execPath, [script], { cwd: root });

	assertSynced(root);
});

test('DOMPurify is pinned and generated from the matching npm release', () => {
	const packageJson = JSON.parse(readFileSync('package.json', 'utf8'));
	const source = 'node_modules/dompurify/dist/purify.js';

	assert.equal(packageJson.dependencies.dompurify, '3.4.15');
	assert.equal(assetMap[source], 'include/js/purify.js');
	assert.match(readFileSync(source, 'utf8'), /DOMPurify 3\.4\.15/);
});

test("screenfull's ESM export is rewritten to a global assignment", () => {
	const root = fixture();

	syncAssets(root, () => {});

	const rewritten = readFileSync(join(root, 'include/js/screenfull.js'), 'utf8');

	assert.match(rewritten, /window\.screenfull = screenfull;/);
	assert.doesNotMatch(rewritten, /export default/);
});

test('syncAssets fails loudly if screenfull stops shipping the expected ESM export', () => {
	const root = fixture();

	writeFileSync(join(root, 'node_modules/screenfull/index.js'), 'const screenfull = {};\nexport {screenfull as default};\n');

	assert.throws(
		() => syncAssets(root, () => {}),
		/expected 'export default screenfull;'/,
	);
});

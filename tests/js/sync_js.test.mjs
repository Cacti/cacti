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

	assert.equal(packageJson.dependencies.dompurify, '3.4.16');
	assert.equal(assetMap[source], 'include/js/purify.js');
	assert.match(readFileSync(source, 'utf8'), /DOMPurify 3\.4\.16/);
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

test('the tablesorter transform replaces every removed jQuery API with native equivalents', () => {
	const root = fixture();

	const transformed = [
		'node_modules/tablesorter/dist/js/jquery.tablesorter.js',
		'node_modules/tablesorter/dist/js/jquery.tablesorter.widgets.js',
	];

	const source = [
		'if ( $.isFunction( fn ) ) { call(); }',
		'var trimmed = $.trim( raw );',
		"if ( $.type( val ) === 'string' ) { ok(); }",
		"if ( $.type( obj ) === 'object' ) { ok(); }",
		'var scoped = $.isWindow( scope );',
		'var arr = $.isArray( list );',
		'var parsed = $.parseJSON( text );',
		'if ($.parseJSON) { load(); }',
		'return str ? ( str && table.config.ignoreCase ? str.toLocaleLowerCase() : str ).trim() : str;',
		'',
	].join('\n');

	for (const asset of transformed) {
		writeFileSync(join(root, asset), source);
	}

	syncAssets(root, () => {});

	for (const asset of transformed) {
		const output = readFileSync(join(root, assetMap[asset]), 'utf8');

		// every removed jQuery 4 utility is rewritten to its native equivalent
		assert.match(output, /typeof fn === 'function'/);
		assert.match(output, /String\(\( raw \) \?\? ''\)\.trim\(\)/);
		assert.match(output, /typeof val === 'string'/);
		assert.match(output, /\$\.isPlainObject\(obj\)/);
		assert.match(output, /scope != null && scope === scope\.window/);
		assert.match(output, /Array\.isArray\(/);
		assert.match(output, /JSON\.parse\(/);
		assert.match(output, /if \(window\.JSON && window\.JSON\.parse\)/);
		// the CodeQL useless-conditional simplification is applied too
		assert.match(output, /\( table\.config\.ignoreCase \?/);

		// none of the removed APIs (nor the redundant guard) survive
		assert.doesNotMatch(output, /\$\.isFunction/);
		assert.doesNotMatch(output, /\$\.trim\(/);
		assert.doesNotMatch(output, /\$\.type\(/);
		assert.doesNotMatch(output, /\$\.isWindow/);
		assert.doesNotMatch(output, /\$\.isArray/);
		assert.doesNotMatch(output, /\$\.parseJSON/);
		assert.doesNotMatch(output, /str && table\.config\.ignoreCase/);
	}
});

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
		writeFileSync(path, `fixture:${source}\n`);
	}

	return root;
}

function assertSynced(root) {
	for (const [source, destination] of Object.entries(assetMap)) {
		assert.equal(
			readFileSync(join(root, destination), 'utf8'),
			`fixture:${source}\n`,
		);
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

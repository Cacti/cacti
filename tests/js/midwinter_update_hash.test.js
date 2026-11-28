/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
'use strict';

// update_hash.php is a PHP command line script, so these checks read its source.

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const source = fs.readFileSync(path.join(__dirname, '..', '..', 'include', 'themes', 'midwinter', 'update_hash.php'), 'utf8');

function body(name) {
	const start = source.indexOf(`function ${name}(`);
	assert.notEqual(start, -1, `${name}() must exist`);
	let depth = 0;

	for (let offset = source.indexOf('{', start); offset < source.length; offset++) {
		if (source[offset] === '{') depth++;
		if (source[offset] === '}' && --depth === 0) return source.slice(start, offset + 1);
	}

	throw new Error(`Unable to extract ${name}()`);
}

test('the script still refuses to run outside the command line before doing anything else', () => {
	const guard = source.indexOf("if (PHP_SAPI !== 'cli')");

	assert.notEqual(guard, -1);
	assert.ok(guard < source.indexOf('function '), 'guard precedes the first function');
});

test('the unused empty update_hash() stub is gone', () => {
	assert.doesNotMatch(source, /function update_hash\(/);
});

test('a stylesheet import that cannot be hashed stops the run instead of writing an empty hash', () => {
	const hash = body('file_hash');

	assert.match(hash, /\$md5Hash === false/);
	assert.match(hash, /exit\(1\)/);
	assert.ok(hash.indexOf('$md5Hash === false') < hash.indexOf('$result'), 'checked before the import is rewritten');
	assert.doesNotMatch(hash, /\$md5Real/);
});

test('stylesheets are replaced through a temporary file and a rename', () => {
	const update = body('file_update');

	assert.doesNotMatch(update, /file_put_contents\(\$cssFile,/);
	assert.match(update, /file_put_contents\(\$tempFile, \$fileUpdated\) === false/);
	assert.match(update, /chmod\(\$tempFile, fileperms\(\$cssFile\) & 0777\)/);
	assert.match(update, /rename\(\$tempFile, \$cssFile\)/);
	assert.match(update, /exit\(1\)/);
});

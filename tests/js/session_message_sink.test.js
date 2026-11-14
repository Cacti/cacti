/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const root = path.resolve(__dirname, '../..');
const layout = fs.readFileSync(path.join(root, 'include/layout.js'), 'utf8');

const start = layout.indexOf('function displayMessages()');
const end = layout.indexOf('\nfunction ', start + 1);
const displayMessages = layout.slice(start, end);

/* GHSA-9737: the session message reaches the dialog as HTML. It must be
 * sanitized at the sink and inserted as a node, not concatenated into a
 * markup string that is parsed again. */
test('displayMessages sanitizes the session message at the sink', () => {
	assert.notEqual(start, -1, 'displayMessages() not found');
	assert.match(displayMessages, /\.html\(' ' \+ DOMPurify\.sanitize\(sessionMessage\.message\)\)/);
});

test('displayMessages does not concatenate the session message into markup', () => {
	assert.doesNotMatch(displayMessages, /['"]\s*\+\s*sessionMessage\.message\s*\+\s*['"]/);
	assert.doesNotMatch(displayMessages, /\$\('body'\)\.append\(returnStr\)/);
});

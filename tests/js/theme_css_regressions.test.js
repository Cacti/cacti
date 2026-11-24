/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
'use strict';

const assert = require('node:assert/strict');
const crypto = require('node:crypto');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const root = path.resolve(__dirname, '../..');
const themes = path.resolve(process.env.CACTI_THEMES_DIR || path.join(root, 'include/themes'));

function walk(dir, out = []) {
	for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
		const full = path.join(dir, entry.name);

		if (entry.isDirectory()) {
			walk(full, out);
		} else if (entry.name.endsWith('.css')) {
			out.push(full);
		}
	}

	return out.sort();
}

function read(rel) {
	return fs.readFileSync(path.join(themes, rel), 'utf8');
}

/* Blank out comments but keep newlines so reported line numbers stay right. */
function stripComments(css) {
	return css.replace(/\/\*[\s\S]*?\*\//g, (m) => m.replace(/[^\n]/g, ' '));
}

/*
 * Report declarations that a browser drops or misreads: stray comment
 * terminators, "#prop: value" lines, "; !important", "#word" colour keywords
 * and a declaration that runs into the next one because ";" is missing.
 */
function lint(css) {
	const text = stripComments(css);
	const problems = [];
	const lineOf = (index) => text.slice(0, index).split('\n').length;
	const stray = text.indexOf('*/');

	if (stray !== -1) {
		problems.push(`line ${lineOf(stray)}: stray comment terminator`);
	}

	const body = /\{([^{}]*)\}/g;
	let block;

	while ((block = body.exec(text)) !== null) {
		let depth = 0;
		let start = 0;
		const base = block.index + 1;
		const chunks = [];

		for (let i = 0; i <= block[1].length; i++) {
			const ch = block[1][i];

			if (ch === '(') {
				depth++;
			} else if (ch === ')') {
				depth--;
			} else if ((ch === ';' && depth === 0) || i === block[1].length) {
				chunks.push([block[1].slice(start, i), start]);
				start = i + 1;
			}
		}

		for (const [raw, offset] of chunks) {
			const decl = raw.trim();

			if (decl === '') {
				continue;
			}

			const line = lineOf(base + offset + raw.length - raw.trimStart().length);
			const m = /^(-{0,2}[A-Za-z_][\w-]*)\s*:\s*([\s\S]*)$/.exec(decl);

			if (!m) {
				problems.push(`line ${line}: malformed declaration "${decl.split('\n')[0]}"`);
				continue;
			}

			const value = m[2].replace(/\([^()]*\)/g, '()').replace(/"[^"]*"|'[^']*'/g, '""');

			if (/^\s*$/.test(value)) {
				problems.push(`line ${line}: empty value for ${m[1]}`);
			} else if (/\n\s*-{0,2}[A-Za-z_][\w-]*\s*:/.test(value)) {
				problems.push(`line ${line}: ${m[1]} runs into the next declaration (missing semicolon)`);
			} else if (/(^|[\s,])#[A-Za-z]*[g-zG-Z][A-Za-z]*(\s|,|$)/.test(value)) {
				problems.push(`line ${line}: ${m[1]} has an invalid hex colour "${m[2].trim()}"`);
			}
		}
	}

	return problems;
}

test('lint flags the kinds of syntax error the theme fixes remove', () => {
	assert.equal(lint('a { color: red; }').length, 0);
	assert.equal(lint('a { color: red;\n*/\nbox-shadow: none; }').length > 0, true);
	assert.equal(lint('a { box-shadow: 0 0 1px red\nopacity: 1; }').length, 1);
	assert.equal(lint('a { color: #white; }').length, 1);
	assert.equal(lint('a { width: 1px; !important; }').length, 1);
	assert.equal(lint('a { color: #fff; background: url(x.png?a=b:c); }').length, 0);
});

test('theme stylesheets have no malformed declarations', () => {
	const failures = [];

	for (const file of walk(themes)) {
		const rel = path.relative(themes, file).split(path.sep).join('/');
		// "#property: value" lines are the themes' way of disabling a declaration; they are dropped on purpose.
		const problems = lint(fs.readFileSync(file, 'utf8')).filter((p) => !/malformed declaration "#[\w-]+\s*:/.test(p));

		for (const p of problems) {
			failures.push(`${rel}: ${p}`);
		}
	}

	assert.deepEqual(failures, []);
});

test('midwinter @import cache keys match the md5 of the imported file', () => {
	const main = read('midwinter/main.css');
	const re = /@import\s+url\(\s*(['"]?)([^'")?]+)\?([0-9a-f]+)\1\s*\)/g;
	let match;
	let count = 0;
	const mismatches = [];

	while ((match = re.exec(main)) !== null) {
		const file = path.join(themes, 'midwinter', match[2]);
		const actual = crypto.createHash('md5').update(fs.readFileSync(file)).digest('hex');

		count++;

		if (actual !== match[3]) {
			mismatches.push(`${match[2]}: has ${match[3]}, file is ${actual}`);
		}
	}

	assert.ok(count >= 15, `expected the @import list to be found, saw ${count}`);
	assert.deepEqual(mismatches, []);
});

/* WCAG 2.x contrast. */
function parseColor(value) {
	const hex = /^#?([0-9a-f]{6})$/i.exec(value);

	assert.ok(hex, `not a 6-digit colour: ${value}`);

	return [0, 2, 4].map((i) => parseInt(hex[1].slice(i, i + 2), 16));
}

function luminance(value) {
	const [r, g, b] = parseColor(value).map((c) => {
		const s = c / 255;

		return s <= 0.03928 ? s / 12.92 : Math.pow((s + 0.055) / 1.055, 2.4);
	});

	return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

function contrast(a, b) {
	const [hi, lo] = [luminance(a), luminance(b)].sort((x, y) => y - x);

	return (hi + 0.05) / (lo + 0.05);
}

function declared(css, selector, prop) {
	const esc = selector.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
	const m = new RegExp(`(?:^|\\n)${esc}\\s*\\{([^}]*)\\}`).exec(css);

	assert.ok(m, `rule ${selector} not found`);

	const d = new RegExp(`(?:^|[;\\s])${prop}\\s*:\\s*(#[0-9a-fA-F]{6})`).exec(m[1]);

	assert.ok(d, `${prop} not found in ${selector}`);

	return d[1];
}

function phpColors(file) {
	const out = {};
	const re = /\$(rrdcolors\w*)\['(\w+)'\]\s*=\s*'([0-9a-fA-F]+)'/g;
	const src = read(file);
	let m;

	while ((m = re.exec(src)) !== null) {
		out[m[1]] = out[m[1]] || {};
		out[m[1]][m[2]] = m[3];
	}

	return { src, colors: out };
}

test('midwinter default graph palette is readable (font on background)', () => {
	const { src, colors } = phpColors('midwinter/rrdtheme.php');

	assert.match(src, /\$rrdcolors\s*=\s*\$rrdcolors_light;/);
	assert.equal(colors.rrdcolors, undefined, 'the default palette is copied, not hand-written');
	assert.ok(contrast(colors.rrdcolors_light.font, colors.rrdcolors_light.back.slice(0, 6)) >= 7);
});

test('sunrise graph background is opaque and the font is readable on it', () => {
	const { colors } = phpColors('sunrise/rrdtheme.php');
	const back = colors.rrdcolors.back;

	assert.equal(back.length, 6, 'no alpha channel');
	assert.ok(contrast(colors.rrdcolors.font, back) >= 4.5);
});

test('device status text colours are readable on the dark surfaces', () => {
	const cases = [
		// [file, selector, background, minimum]
		['dark/main.css', '.deviceUnknown', '#161616', 4.5],
		['dark/main.css', '.deviceError', '#161616', 4.5],
		['sunrise/main.css', '.deviceUnknown', '#04335b', 4.5],
		['midwinter/css/media/core.css', "html:not([data-theme-color='light']) .deviceUnknown", '#1a1c29', 4.5],
	];

	for (const [file, selector, bg, min] of cases) {
		const css = stripComments(read(file));
		const ratio = contrast(declared(css, selector, 'color'), bg);

		assert.ok(ratio >= min, `${file} ${selector}: ${ratio.toFixed(2)}:1 on ${bg}`);
	}
});

test('midwinter shows a focus ring for keyboard users on checkboxes', () => {
	const css = stripComments(read('midwinter/css/media/core.css'));
	const m = /input\[type="checkbox"\]:focus-visible \+ label:before\s*\{([^}]*)\}/.exec(css);

	assert.ok(m, 'focus-visible rule present');
	assert.match(m[1], /outline:\s*2px solid/);
	assert.doesNotMatch(css, /input\[type="checkbox"\]:focus \+ label:before/);
});

test('midwinter page fade-in has no hidden hold and a no-script fallback', () => {
	const keyframes = read('midwinter/css/pre/keyframes.css');
	const core = read('midwinter/css/media/core.css');

	assert.doesNotMatch(keyframes, /50%/);
	assert.match(core, /\nbody\s*\{\s*animation:\s*fadeInAnimation 0s linear 3s forwards;/);
	assert.match(core, /html\[data-theme-state='ready'\] body\s*\{\s*animation:\s*fadeInAnimation ease \.2s;/);
});

test('midwinter print sheet releases the viewport-height layout', () => {
	const css = stripComments(read('midwinter/css/media/print.css'));

	assert.match(css, /html, body, \.cactiContent, #cactiContent, #navigation_right, #main\s*\{[^}]*height:\s*auto !important;[^}]*overflow:\s*visible !important;/);
	assert.match(css, /#cactiPageHead[^{]*\{[^}]*display:\s*none !important;/);
});

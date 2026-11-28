/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

// CACTI_ROOT lets the suite be pointed at another checkout of the tree.
const root = process.env.CACTI_ROOT || path.join(__dirname, '..', '..');
const themesDir = path.join(root, 'include', 'themes');

const themes = fs.readdirSync(themesDir, { withFileTypes: true })
	.filter((entry) => entry.isDirectory())
	.map((entry) => entry.name)
	.sort();

function read(...parts) {
	return fs.readFileSync(path.join(themesDir, ...parts), 'utf8');
}

function stripComments(css) {
	return css.replace(/\/\*[\s\S]*?\*\//g, '');
}

function walk(dir, found = []) {
	for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
		const full = path.join(dir, entry.name);

		if (entry.isDirectory()) {
			walk(full, found);
		} else if (entry.name.endsWith('.css')) {
			found.push(full);
		}
	}

	return found;
}

function escapeRegExp(text) {
	return text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

// Return the declaration block of the first rule whose selector list is
// exactly `selector`, or null.
function ruleBody(css, selector) {
	const match = new RegExp('(?:^|\\})\\s*' + escapeRegExp(selector) + '\\s*\\{([^}]*)\\}').exec(stripComments(css));

	return match ? match[1] : null;
}

function hasSelector(css, selector) {
	return new RegExp('(?:^|[}\\s,])' + escapeRegExp(selector) + '(?=\\s*[,{:\\s>.#\\[])', 'm').test(stripComments(css));
}

// WCAG 2.x relative luminance and contrast ratio.
function channel(value) {
	const c = value / 255;

	return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
}

function luminance([r, g, b]) {
	return 0.2126 * channel(r) + 0.7152 * channel(g) + 0.0722 * channel(b);
}

function contrast(a, b) {
	const [hi, lo] = [luminance(a), luminance(b)].sort((x, y) => y - x);

	return (hi + 0.05) / (lo + 0.05);
}

function over(rgba, backdrop) {
	const [r, g, b, a] = rgba;

	return [r, g, b].map((v, i) => v * a + backdrop[i] * (1 - a));
}

function parseRgba(text) {
	const match = /rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*(?:,\s*([\d.]+)\s*)?\)/.exec(text);

	assert.ok(match, 'expected an rgb()/rgba() value in: ' + text);

	return [Number(match[1]), Number(match[2]), Number(match[3]), match[4] === undefined ? 1 : Number(match[4])];
}

function hex(text) {
	return [0, 2, 4].map((i) => parseInt(text.slice(i, i + 2), 16));
}

const WHITE = [255, 255, 255];

test('no theme stylesheet carries the malformed declarations that browsers drop', () => {
	const problems = [];

	for (const file of walk(themesDir)) {
		const css = stripComments(fs.readFileSync(file, 'utf8'));
		const name = path.relative(themesDir, file);

		if (/\*\//.test(css)) {
			problems.push(`${name}: stray comment terminator`);
		}

		if (/:\s*;/.test(css.replace(/:\s*;\s*\}/g, ''))) {
			problems.push(`${name}: empty or misplaced ':;'`);
		}

		if (/;\s*;/.test(css)) {
			problems.push(`${name}: doubled semicolon`);
		}

		if (/display\s*:\s*hidden/.test(css)) {
			problems.push(`${name}: display: hidden is not a display value`);
		}

		for (const [, alpha] of css.matchAll(/rgba\([^)]*,\s*(\d[\d.]*)\s*\)/g)) {
			if (Number(alpha) > 1) {
				problems.push(`${name}: alpha ${alpha} is greater than 1`);
			}
		}
	}

	assert.deepEqual(problems, []);
});

test('raspberry keeps its purple accent instead of the olive recolor mistake', () => {
	assert.ok(!/rgba\(119,\s*119,\s*0,/.test(read('raspberry', 'main.css')));
});

test('every theme hides jsTree nodes that tree search filters out', () => {
	for (const theme of themes) {
		const body = ruleBody(read(theme, 'default', 'style.css'), '.jstree-hidden,\n.jstree-node.jstree-hidden');

		assert.ok(body && /display\s*:\s*none/.test(body), `${theme}: .jstree-hidden must set display: none`);
	}
});

test('every classic theme styles the graph grid, flex containers and confirm dialogs', () => {
	const needed = [
		'.graphPage', '.cols1', '.cols2', '.cols3', '.cols4', '.cols5', '.cols6',
		'.prewrap', '.wrap', '.flexContainer', '.flexChild',
		'.settingsFilter', '.pingRow', '.formConfirmRadio', '.formConfirmRow',
		'.tableHeaderGraph', '.ui-selectmenu-search input',
		'.confirm_actions .formColumnLeft', '.confirm_actions .formColumnRight', '.confirm_actions label',
	];
	const classic = themes.filter((theme) => theme !== 'midwinter');

	for (const theme of classic) {
		const css = read(theme, 'main.css');

		for (const selector of needed) {
			assert.ok(hasSelector(css, selector), `${theme}: missing ${selector}`);
		}
	}
});

test('graph grid rules give each column count a width and let graphPage wrap', () => {
	const css = read('deepness', 'main.css');

	assert.match(ruleBody(css, '.graphPage'), /display\s*:\s*flex/);
	assert.match(ruleBody(css, '.graphPage'), /flex-wrap\s*:\s*wrap/);
	assert.match(ruleBody(css, '.cols3'), /width\s*:\s*33%/);
	assert.match(ruleBody(css, '.flexContainer'), /display\s*:\s*flex/);
});

test('every theme styles the markup that jquery.zoom.js creates', () => {
	const script = fs.readFileSync(path.join(root, 'include', 'js', 'jquery.zoom.js'), 'utf8');
	const selectors = [
		'.zoom-crosshair', '.zoom-livedata', '.zoom-livedata-header', '.zoom-livedata-item',
		'.zoom-livedata-color', '.zoom-livedata-value', '.zoom-livedata-title', '.zoom-livedata-unit',
		'.zoom-marker', '.zoom-marker-tooltip', '.zoom-marker-tooltip-value',
		'.zoom-menu', '.zoom-hidden', '.zoom-area-excluded', '#zoom-area', '#zoom-box', '#zoom-container',
	];

	for (const selector of selectors) {
		assert.ok(script.includes(selector.slice(1)), `jquery.zoom.js no longer uses ${selector}`);
	}

	for (const theme of themes) {
		const css = read(theme, 'jquery.zoom.css');

		for (const selector of selectors) {
			assert.ok(hasSelector(css, selector), `${theme}: jquery.zoom.css lacks ${selector}`);
		}

		assert.ok(!/zoom-marker-arrow-|zoom-marker-tooltip-arrow/.test(css), `${theme}: obsolete pre-rewrite zoom rules`);
		// The live-data box is positioned by a rule shared with .zoom-menu.
		assert.match(stripComments(css), /\.zoom-livedata\s*\{[^}]*position\s*:\s*absolute/);
	}
});

test('every theme ships the multiselect stylesheet that matches the 3.x widget', () => {
	const needed = ['.ui-multiselect-open', '.ui-multiselect-collapsed > ul', '.ui-multiselect-listbox', '.ui-multiselect-columns', '.ui-multiselect-nowrap', '.ui-multiselect-measure'];

	for (const theme of themes) {
		const css = read(theme, 'jquery.multiselect.css');

		for (const selector of needed) {
			assert.ok(css.includes(selector), `${theme}: jquery.multiselect.css lacks ${selector}`);
		}

		assert.ok(!css.includes('li.ui-multiselect-optgroup-label'), `${theme}: 1.x multiselect selectors remain`);
	}
});

test('every custom property midwinter uses is defined for both colour modes', () => {
	const dir = path.join(themesDir, 'midwinter');
	const files = walk(dir);
	const palettes = stripComments(read('midwinter', 'css', 'pre', 'colors.css')).split(/\n(?=\[data-theme-color)/);

	assert.equal(palettes.length, 2);

	const definedIn = (text) => new Set([...text.matchAll(/(--[\w-]+)\s*:/g)].map((m) => m[1]));
	const [dark, light] = palettes.map(definedIn);
	const elsewhere = new Set();

	for (const file of files) {
		if (!file.endsWith(path.join('pre', 'colors.css'))) {
			definedIn(stripComments(fs.readFileSync(file, 'utf8'))).forEach((name) => elsewhere.add(name));
		}
	}

	const problems = [];

	for (const file of files) {
		const isPalette = file.endsWith(path.join('pre', 'colors.css'));
		const css = stripComments(fs.readFileSync(file, 'utf8'));

		for (const [, name, fallback] of css.matchAll(/var\(\s*(--[\w-]+)\s*(,)?/g)) {
			if (fallback || isPalette || elsewhere.has(name)) {
				continue;
			}

			if (!(dark.has(name) && light.has(name))) {
				problems.push(`${path.relative(dir, file)}: ${name} is not defined in both palettes`);
			}
		}
	}

	assert.deepEqual([...new Set(problems)], []);
});

test('cacti header and selected-tab text meets WCAG AA contrast on the white page', () => {
	const css = read('cacti', 'main.css');
	const pairs = [
		['.tableHeader', 'background'],
		['.formHeader', 'background'],
		['.tabs nav a.selected', 'background'],
	];

	for (const [selector, property] of pairs) {
		const body = ruleBody(css, selector);

		assert.ok(body, `${selector} rule must exist`);
		assert.match(body, /color\s*:\s*white/);

		const value = new RegExp(property + '\\s*:\\s*(rgba?\\([^)]*\\))').exec(body);

		assert.ok(value, `${selector} must set a ${property} colour`);

		const ratio = contrast(WHITE, over(parseRgba(value[1]), WHITE));

		assert.ok(ratio >= 4.5, `${selector} contrast ${ratio.toFixed(2)}:1 is below 4.5:1`);
	}
});

test('midwinter base graph palette text is readable on its background and canvas', () => {
	const php = read('midwinter', 'rrdtheme.php');
	const color = (name) => {
		const match = new RegExp("\\$rrdcolors\\['" + name + "'\\]\\s*=\\s*'([0-9A-Fa-f]{6})").exec(php);

		assert.ok(match, `$rrdcolors['${name}'] must exist`);

		return hex(match[1]);
	};

	for (const surface of ['back', 'canvas']) {
		const ratio = contrast(color('font'), color(surface));

		assert.ok(ratio >= 4.5, `font on ${surface} contrast ${ratio.toFixed(2)}:1 is below 4.5:1`);
	}
});

test('auth page text colour in the midwinter light palette contrasts with the form background', () => {
	const palettes = stripComments(read('midwinter', 'css', 'pre', 'colors.css')).split(/\n(?=\[data-theme-color)/);
	const light = palettes[1];

	assert.match(light, /--text-color-highlight\s*:/);
	assert.match(light, /--text-color-white\s*:\s*hsl\(0,\s*0%,\s*100%\)/);
	assert.match(light, /--background-filter\s*:\s*hsl\(var\(--hue\),\s*17%,\s*18%\)/);

	// hsl(180, 17%, 18%) is roughly rgb(38, 54, 54); white text on it is far above AA.
	assert.ok(contrast(WHITE, [38, 54, 54]) >= 4.5);
});

/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const layout = fs.readFileSync(path.join(__dirname, '..', '..', 'include', 'layout.js'), 'utf8');

function extractFunction(name) {
	const start = layout.indexOf(`function ${name}(`);
	assert.notEqual(start, -1, `${name}() must exist`);
	let depth = 0;

	for (let offset = layout.indexOf('{', start); offset < layout.length; offset++) {
		if (layout[offset] === '{') depth++;
		if (layout[offset] === '}' && --depth === 0) return layout.slice(start, offset + 1);
	}

	throw new Error(`Unable to extract ${name}()`);
}

/* A recording stand-in for jQuery. There is no DOM here, so these tests prove
 * which API receives each value, not how a browser renders the result. */
function fakeJquery() {
	const nodes = new Map();

	function $(selector) {
		if (!nodes.has(selector)) {
			const node = {selector, calls: []};

			for (const method of ['attr', 'css', 'html', 'text', 'append', 'empty', 'addClass', 'remove', 'dialog']) {
				node[method] = (...args) => {
					node.calls.push([method, ...args]);

					return method === 'attr' && args.length === 1 && typeof args[0] === 'string' ? '1' : node;
				};
			}

			nodes.set(selector, node);
		}

		return nodes.get(selector);
	}

	$.calls = (selector, method) => $(selector).calls.filter((call) => call[0] === method);

	return $;
}

const sanitizer = {sanitize: (value) => `sanitized(${value})`};
const decodeHtmlEntities = (value) => value.replace(/&#039;/g, "'").replace(/&amp;/g, '&');
const markup = `0' data-fixture='<b>literal</b>`;

test('graph JSON values become attributes of one image element', () => {
	const $ = fakeJquery();
	const context = {$};
	vm.runInNewContext(extractFunction('cactiGraphImage'), context);

	const image = context.cactiGraphImage({
		local_graph_id: 7, rra_id: 2, type: 'png', image: 'AAAA', graph_start: 10, graph_end: 20,
		graph_left: 3, graph_top: 4, graph_width: 200, graph_height: 100,
		image_width: 230, image_height: 130, value_min: markup, value_max: 100,
	});

	assert.equal(image.selector, '<img>');
	assert.equal(image.calls.length, 1);

	const attributes = image.calls[0][1];
	assert.equal(attributes.value_min, markup);
	assert.equal(attributes.id, 'graph_7');
	assert.equal(attributes.src, 'data:image/png;base64,AAAA');
	assert.equal(attributes.class, 'graphimage');
	assert.equal(attributes.canvas_width, 200);
	assert.equal(attributes.width, 230);
	assert.deepEqual(Object.keys(attributes).sort(), [
		'canvas_height', 'canvas_left', 'canvas_top', 'canvas_width', 'class', 'graph_end', 'graph_height',
		'graph_id', 'graph_left', 'graph_start', 'graph_top', 'graph_type', 'graph_width', 'height', 'id',
		'image_height', 'image_width', 'rra_id', 'src', 'value_max', 'value_min', 'width',
	]);
});

test('redrawGraph and initializeGraphs append the image element instead of parsing a string', () => {
	assert.doesNotMatch(layout, /<img class='graphimage'/);

	for (const name of ['redrawGraph', 'initializeGraphs']) {
		const body = extractFunction(name);

		assert.match(body, /\.empty\(\)\.append\(cactiGraphImage\(data\)\)/, name);
		assert.doesNotMatch(body, /\.html\(\s*["']<img/, name);
	}
});

test('only a URL on the current origin counts as a Cacti page request', () => {
	const context = {URL, window: {location: {href: 'https://cacti.example/cacti/host.php', origin: 'https://cacti.example'}}};
	vm.runInNewContext(extractFunction('cactiIsSameOriginUrl'), context);

	assert.equal(context.cactiIsSameOriginUrl('graph_view.php?action=tree'), true);
	assert.equal(context.cactiIsSameOriginUrl('/cacti/host.php?headercontent=true'), true);
	assert.equal(context.cactiIsSameOriginUrl('https://cacti.example/cacti/index.php'), true);
	assert.equal(context.cactiIsSameOriginUrl('https://other.example/cacti/index.php'), false);
	assert.equal(context.cactiIsSameOriginUrl('//other.example/cacti/index.php'), false);
	assert.equal(context.cactiIsSameOriginUrl('http://cacti.example/cacti/index.php'), false);
	assert.equal(context.cactiIsSameOriginUrl('https://['), false);
});

test('each page loader checks the request origin before parsing the response', () => {
	for (const [name, variable] of [['loadTopTab', 'url'], ['loadPage', 'href'], ['loadPageNoHeader', 'href']]) {
		const body = extractFunction(name);
		const check = body.indexOf(`if (!cactiIsSameOriginUrl(${variable})) {`);
		const parse = body.indexOf('$(html)');

		assert.notEqual(check, -1, `${name}() must check the origin of ${variable}`);
		assert.ok(check < parse, `${name}() must check the origin before parsing`);
		assert.match(body, /cactiSetBreadcrumbs\(breadCrumbs\);/, name);
		assert.doesNotMatch(body, /\$\('#breadcrumbs'\)\.html\(/, name);
	}
});

test('the breadcrumb trail is sanitized and a missing trail leaves the current one', () => {
	const $ = fakeJquery();
	const context = {$, DOMPurify: sanitizer};
	vm.runInNewContext(extractFunction('cactiSetBreadcrumbs'), context);

	context.cactiSetBreadcrumbs(undefined);
	assert.equal($('#breadcrumbs').calls.length, 0);

	context.cactiSetBreadcrumbs(`<li><a id='nav_0' href='index.php'>Console</a></li>`);
	assert.deepEqual($.calls('#breadcrumbs', 'html'), [['html', `sanitized(<li><a id='nav_0' href='index.php'>Console</a></li>)`]]);
});

test('SpikeKill results are sanitized and the dialog title is set as an attribute', () => {
	const $ = fakeJquery();
	const request = {
		done(callback) {
			callback({results: markup});

			return this;
		},
		fail() {
			return this;
		},
	};
	$.post = () => request;

	const context = {
		$, DOMPurify: sanitizer, decodeHtmlEntities, urlPath: '/cacti/', csrfMagicToken: 'token', href: '',
		spikeKillResults: 'R&#039;sultats "SpikeKill"',
		closeDateFilters() {}, checkForRedirects() {}, redrawGraph() {}, getPresentHTTPError() {},
	};
	vm.runInNewContext(extractFunction('removeSpikes'), context);
	context.removeSpikes('stddev', true, 7);

	assert.deepEqual($.calls('#spikeresults', 'html'), [['html', `sanitized(${markup})`]]);
	assert.equal($.calls('<div>', 'attr')[0][1].title, `R'sultats "SpikeKill"`);
	assert.equal($.calls('<div>', 'attr')[0][1].id, 'spikeresults');
	assert.equal($('body').calls[0][1], $('<div>'));
	assert.doesNotMatch(extractFunction('removeSpikes'), /title="' \+ spikeKillResults/);
});

test('the session message header is sanitized and the body stays sanitized', () => {
	const body = extractFunction('displayMessages');

	assert.match(body, /\$\('<h4>'\)\.html\(DOMPurify\.sanitize\(header\)\)/);
	assert.match(body, /\.html\(' ' \+ DOMPurify\.sanitize\(sessionMessage\.message\)\)/);
	assert.doesNotMatch(body, /\$\('<h4>'\)\.html\(header\)/);
	assert.doesNotMatch(body, /\.html\('<span class="ui-button-text">'/);
	assert.match(body, /\.text\(decodeHtmlEntities\(sessionMessageOk\)\)/);
});

test('the countdown writes the Ok label as text', () => {
	const $ = fakeJquery();
	const context = {$, decodeHtmlEntities, sessionMessageOk: '<em>D&#039;accord</em>', sessionMessageTimer: null, setInterval: () => 1};
	vm.runInNewContext(extractFunction('sessionMessageCountdown'), context);
	context.sessionMessageCountdown(5000);

	assert.deepEqual($.calls('<span>', 'text'), [['text', `<em>D'accord</em> (5)`]]);
	assert.equal($.calls('#btnSessionMessageOk', 'html').length, 0);
	assert.equal($.calls('#btnSessionMessageOk', 'append')[0][1], $('<span>'));
	assert.doesNotMatch(extractFunction('sessionMessageCountdown'), /\.html\(/);
});

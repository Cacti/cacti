/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 | Licensed under the GNU General Public License, version 2 or later.     |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

/*
 * Exercise the shared Card API controller's replaceCard() state handling without
 * a browser: a compact DOM shim sufficient for replaceCard(), bindCard() and
 * normalizeState(). The focus is that a card refresh/resize swap preserves a
 * user's live expand/collapse toggle made while the request was in flight, yet
 * still lets host markup drive expansion when the user did not toggle.
 */

class ClassList {
	constructor() { this.set = new Set(); }
	add(...names) { names.forEach((name) => this.set.add(name)); }
	remove(...names) { names.forEach((name) => this.set.delete(name)); }
	contains(name) { return this.set.has(name); }
}

class El {
	constructor(tag) {
		this.tagName = String(tag).toUpperCase();
		this.nodeType = 1;
		this.attrs = new Map();
		this.children = [];
		this.parentNode = null;
		this.classList = new ClassList();
		this.draggable = false;
	}
	setAttribute(name, value) {
		if (name === 'class') { this.classList.set = new Set(String(value).split(/\s+/).filter(Boolean)); return; }
		this.attrs.set(name, String(value));
	}
	getAttribute(name) {
		if (name === 'class') { return [...this.classList.set].join(' '); }
		return this.attrs.has(name) ? this.attrs.get(name) : null;
	}
	removeAttribute(name) { this.attrs.delete(name); }
	hasAttribute(name) { return name === 'class' ? this.classList.set.size > 0 : this.attrs.has(name); }
	addEventListener() { /* events are never dispatched in this shim */ }
	focus() { /* no-op */ }
	closest() { return null; }
	get firstElementChild() { return this.children[0] || null; }
	appendChild(child) { child.parentNode = this; this.children.push(child); return child; }
	replaceChild(fresh, old) {
		const index = this.children.indexOf(old);
		if (index >= 0) { this.children[index] = fresh; fresh.parentNode = this; old.parentNode = null; }
		return old;
	}
	contains(node) { return node === this || this.children.some((child) => child.contains(node)); }
	querySelectorAll(selector) {
		const match = (el) => selector.startsWith('.') ? el.classList.contains(selector.slice(1)) : el.tagName === selector.toUpperCase();
		const out = [];
		const walk = (el) => el.children.forEach((child) => { if (match(child)) out.push(child); walk(child); });
		walk(this);
		return out;
	}
	querySelector(selector) { return this.querySelectorAll(selector)[0] || null; }
	set innerHTML(html) { this.children = []; parseFragment(html).forEach((child) => this.appendChild(child)); }
	get innerHTML() { return ''; }
}

/* Parse the single root element the tests feed as refreshed markup: its tag and
 * attributes are all replaceCard()/normalizeState() read; nested body content is
 * irrelevant to the swap, so it is ignored. */
function parseFragment(html) {
	const open = /^<([a-zA-Z0-9]+)((?:\s+[a-zA-Z0-9:_-]+(?:="[^"]*")?)*)\s*\/?>/.exec(String(html).trim());
	if (!open) { return []; }
	const el = new El(open[1]);
	const attr = /([a-zA-Z0-9:_-]+)(?:="([^"]*)")?/g;
	let match;
	while ((match = attr.exec(open[2])) !== null) {
		if (match[1]) { el.setAttribute(match[1], match[2] == null ? '' : match[2]); }
	}
	return [el];
}

function loadController() {
	const source = fs.readFileSync(path.join(__dirname, '..', '..', 'include', 'js', 'cards.js'), 'utf8');
	const context = {
		window: {}, Promise, setTimeout, clearTimeout, Number, console,
		document: { createElement: (tag) => new El(tag), addEventListener() {} }
	};
	vm.createContext(context);
	vm.runInContext(source, context, { filename: 'include/js/cards.js' });
	return context.window.CactiCards;
}

function makeCard(expanded) {
	const card = new El('section');
	card.setAttribute('class', 'ccCard ccCardExpandable');
	card.setAttribute('data-card', 'a');
	card.setAttribute('data-expandable', '1');
	card.setAttribute('data-expanded', expanded ? '1' : '0');
	if (expanded) { card.classList.add('ccCardExpanded'); }
	return card;
}

function gridWith(card) {
	const grid = new El('div');
	grid.appendChild(card);
	return grid;
}

const CactiCards = loadController();
const adapter = {};
const CARD = (expandable, expanded, body) =>
	'<section class="ccCard' + (expandable ? ' ccCardExpandable' : '') + '" data-card="a"'
	+ ' data-expandable="' + (expandable ? '1' : '0') + '" data-expanded="' + (expanded ? '1' : '0') + '">' + (body || '') + '</section>';

test('a refresh swap keeps an expand toggled while the request was in flight', () => {
	const card = makeCard(false);                 // collapsed when the refresh started
	const grid = gridWith(card);
	card.classList.add('ccCardExpanded');          // user expands while the fetch is pending
	card.setAttribute('data-expanded', '1');

	// Response reflects the stale collapsed state captured at request start.
	CactiCards.replaceCard(grid, adapter, card, CARD(true, false, 'new'), false);

	const swapped = grid.querySelector('.ccCard');
	assert.equal(swapped.classList.contains('ccCardExpanded'), true, 'toggle preserved');
	assert.equal(swapped.getAttribute('data-expanded'), '1');
});

test('a refresh swap keeps a collapse toggled while the request was in flight', () => {
	const card = makeCard(true);                   // expanded when the refresh started
	const grid = gridWith(card);
	card.classList.remove('ccCardExpanded');       // user collapses while the fetch is pending
	card.setAttribute('data-expanded', '0');

	CactiCards.replaceCard(grid, adapter, card, CARD(true, true, 'new'), true);

	const swapped = grid.querySelector('.ccCard');
	assert.equal(swapped.classList.contains('ccCardExpanded'), false, 'collapse preserved');
	assert.equal(swapped.getAttribute('data-expanded'), '0');
});

test('host markup drives expansion when the user did not toggle', () => {
	const card = makeCard(false);                  // collapsed, no user toggle
	const grid = gridWith(card);

	// Host returns an expanded card; with no toggle the markup is authoritative.
	CactiCards.replaceCard(grid, adapter, card, CARD(true, true), false);

	const swapped = grid.querySelector('.ccCard');
	assert.equal(swapped.classList.contains('ccCardExpanded'), true, 'host state honored');
	assert.equal(swapped.getAttribute('data-expanded'), '1');
});

test('a direct replaceCard() with no captured state leaves host markup authoritative', () => {
	const card = makeCard(false);                  // collapsed
	const grid = gridWith(card);

	// Four-arg call (no captured state): the host's data-expanded must stand.
	CactiCards.replaceCard(grid, adapter, card, CARD(true, true));

	const swapped = grid.querySelector('.ccCard');
	assert.equal(swapped.getAttribute('data-expanded'), '1', 'host state not clobbered');
});

test('a preserved toggle is never forced onto a non-expandable fresh card', () => {
	const card = makeCard(false);                  // collapsed at request start
	const grid = gridWith(card);
	card.classList.add('ccCardExpanded');          // user expands while pending
	card.setAttribute('data-expanded', '1');

	// The refreshed card is no longer expandable; the toggle must not be forced.
	CactiCards.replaceCard(grid, adapter, card, CARD(false, false), false);

	const swapped = grid.querySelector('.ccCard');
	assert.equal(swapped.classList.contains('ccCardExpanded'), false, 'not forced expanded');
	assert.equal(swapped.getAttribute('data-expanded'), '0');
});

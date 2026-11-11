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
const vm = require('node:vm');

const cardsSource = fs.readFileSync(
	path.join(__dirname, '..', '..', 'include', 'js', 'cards.js'),
	'utf8'
);

/*
 * A minimal DOM stand-in: just enough tree, selector, event and markup support
 * for the Card API controller, so its ordering logic runs under node --test
 * without a browser or a DOM library.
 */
function parseSelector(sel) {
	const m = /^\.([\w-]+)(?::not\(\.([\w-]+)\))?(?:\[([\w-]+)="([^"]*)"\])?$/.exec(sel);
	assert.ok(m, 'unsupported selector ' + sel);

	return { cls: m[1], not: m[2], attr: m[3], value: m[4] };
}

class Element {
	constructor(doc, attrs) {
		this.ownerDocument = doc;
		this.attrs = Object.assign({}, attrs || {});
		this.children = [];
		this.parentNode = null;
		this.listeners = {};
		this.rect = { top: 0, bottom: 0, left: 0, width: 0 };
		const classes = new Set((this.attrs.class || '').split(/\s+/).filter(Boolean));
		this.classList = {
			add: (c) => classes.add(c),
			remove: (c) => classes.delete(c),
			contains: (c) => classes.has(c),
		};
	}

	getAttribute(name) {
		return Object.prototype.hasOwnProperty.call(this.attrs, name) ? this.attrs[name] : null;
	}

	setAttribute(name, value) {
		this.attrs[name] = String(value);
	}

	get firstElementChild() {
		return this.children[0] || null;
	}

	sibling(offset) {
		if (!this.parentNode) {
			return null;
		}
		const list = this.parentNode.children;

		return list[list.indexOf(this) + offset] || null;
	}

	get previousElementSibling() {
		return this.sibling(-1);
	}

	get nextElementSibling() {
		return this.sibling(1);
	}

	detach(child) {
		if (child.parentNode) {
			const list = child.parentNode.children;
			list.splice(list.indexOf(child), 1);
			child.parentNode = null;
		}
	}

	appendChild(child) {
		this.detach(child);
		child.parentNode = this;
		this.children.push(child);

		return child;
	}

	insertBefore(child, ref) {
		if (ref === null) {
			return this.appendChild(child);
		}
		this.detach(child);
		child.parentNode = this;
		this.children.splice(this.children.indexOf(ref), 0, child);

		return child;
	}

	replaceChild(fresh, old) {
		this.insertBefore(fresh, old);
		this.detach(old);

		return old;
	}

	removeChild(child) {
		this.detach(child);

		return child;
	}

	contains(node) {
		for (let n = node; n; n = n.parentNode) {
			if (n === this) {
				return true;
			}
		}

		return false;
	}

	matches(sel) {
		const s = parseSelector(sel);

		return this.classList.contains(s.cls)
			&& !(s.not && this.classList.contains(s.not))
			&& !(s.attr && this.getAttribute(s.attr) !== s.value);
	}

	closest(sel) {
		for (let n = this; n instanceof Element; n = n.parentNode) {
			if (n.matches(sel)) {
				return n;
			}
		}

		return null;
	}

	querySelectorAll(sel) {
		const out = [];
		const walk = (node) => node.children.forEach((child) => {
			if (child.matches(sel)) {
				out.push(child);
			}
			walk(child);
		});
		walk(this);

		return out;
	}

	querySelector(sel) {
		return this.querySelectorAll(sel)[0] || null;
	}

	// Parses one opening tag with double-quoted attributes: all the markup the
	// tests hand to the controller.
	set innerHTML(html) {
		this.children = [];
		const tag = /^<\w+((?:\s+[\w-]+="[^"]*")*)\s*>/.exec(html);
		if (tag) {
			const attrs = {};
			tag[1].replace(/([\w-]+)="([^"]*)"/g, (all, name, value) => {
				attrs[name] = value;
			});
			this.appendChild(new Element(this.ownerDocument, attrs));
		}
	}

	getBoundingClientRect() {
		return this.rect;
	}

	addEventListener(type, fn) {
		(this.listeners[type] = this.listeners[type] || []).push(fn);
	}

	dispatch(type, event) {
		const ev = Object.assign({ type: type, target: this, preventDefault() {} }, event || {});
		for (let n = this; n; n = n.parentNode) {
			(n.listeners[type] || []).forEach((fn) => fn.call(n, ev));
		}
	}

	focus() {
		this.ownerDocument.activeElement = this;
	}
}

function loadCards() {
	const timers = [];
	const doc = {
		activeElement: null,
		byId: {},
		createElement() {
			return new Element(doc);
		},
		getElementById(id) {
			return doc.byId[id] || null;
		},
		addEventListener() {},
	};
	const context = {
		window: {},
		document: doc,
		setTimeout(fn) {
			timers.push(fn);

			return timers.length;
		},
		clearTimeout(handle) {
			timers[handle - 1] = null;
		},
	};
	vm.runInNewContext(cardsSource, context);

	const flushTimers = () => timers.splice(0).forEach((fn) => fn && fn());

	return { cards: context.window.CactiCards, doc, flushTimers };
}

function cardMarkup(id, height) {
	return '<section class="ccCard" data-card="' + id + '" data-height="' + height + '">';
}

function makeCard(doc, id, height) {
	const card = new Element(doc, { class: 'ccCard', 'data-card': id, 'data-height': String(height) });
	for (const tool of ['grow', 'refresh']) {
		card.appendChild(new Element(doc, { class: 'ccTool', 'data-tool': tool }));
	}

	return card;
}

function deferred() {
	let resolve;
	const promise = new Promise((r) => { resolve = r; });

	return { promise, resolve };
}

const settle = () => new Promise((resolve) => setImmediate(resolve));

function clickTool(grid, tool) {
	grid.querySelector('.ccCard').querySelector('.ccTool[data-tool="' + tool + '"]').dispatch('click');
}

// Drives one grow and one refresh of a single card through the click handler,
// letting the test choose the click order and the response order.
async function runResizeAndRefresh(clickOrder, responseOrder) {
	const { cards, doc, flushTimers } = loadCards();
	const grid = new Element(doc, { class: 'ccGrid' });
	grid.appendChild(makeCard(doc, 'a', 1));

	const pending = { grow: [], refresh: [] };
	const fetchStates = [];
	const layouts = [];

	cards.init(grid, {
		onResize(id, dir) {
			const d = deferred();
			pending.grow.push(d);

			return d.promise;
		},
		fetchCard(id, state) {
			fetchStates.push(state.height);
			const d = deferred();
			pending.refresh.push({ d, height: state.height });

			return d.promise;
		},
		onLayoutChange(state) {
			layouts.push(state.cards.a.height);
		},
	});

	clickOrder.forEach((tool) => clickTool(grid, tool));

	for (const which of responseOrder) {
		await settle();
		if (which === 'grow') {
			// The host persisted height 2 and returns the re-rendered card.
			pending.grow.shift().resolve(cardMarkup('a', 2));
		} else {
			const req = pending.refresh.shift();
			req.d.resolve(cardMarkup('a', req.height));
		}
		await settle();
	}

	// Drain a refresh the controller deferred until the resize settled.
	while (pending.refresh.length) {
		const req = pending.refresh.shift();
		req.d.resolve(cardMarkup('a', req.height));
		await settle();
	}
	flushTimers();

	return { grid, fetchStates, layouts };
}

test('a refresh clicked during a grow is deferred and renders the grown height', async () => {
	const { grid, fetchStates, layouts } = await runResizeAndRefresh(['grow', 'refresh'], ['grow']);

	assert.deepEqual(fetchStates, [2], 'refresh must read the post-resize height');
	assert.equal(grid.querySelector('.ccCard').getAttribute('data-height'), '2');
	assert.deepEqual(layouts, [2]);
});

test('a refresh clicked before a grow cannot revert it, in either response order', async () => {
	for (const responses of [['refresh', 'grow'], ['grow', 'refresh']]) {
		const { grid, fetchStates, layouts } = await runResizeAndRefresh(['refresh', 'grow'], responses);

		assert.deepEqual(fetchStates, [1]);
		assert.equal(grid.querySelector('.ccCard').getAttribute('data-height'), '2', responses.join(' then '));
		assert.deepEqual(layouts, [2], responses.join(' then '));
	}
});

test('re-initialising a new grid with the same add control fires onAddCard once, into the latest grid', async () => {
	const { cards, doc } = loadCards();
	const add = new Element(doc, {});
	doc.byId.add_card = add;
	const calls = [];
	const adapterFor = (name) => ({
		addControl: 'add_card',
		onAddCard(id) {
			calls.push(name + ':' + id);

			return cardMarkup(id, 1);
		},
	});

	const first = new Element(doc, { class: 'ccGrid' });
	const second = new Element(doc, { class: 'ccGrid' });
	cards.init(first, adapterFor('first'));
	cards.init(second, adapterFor('second'));

	add.value = 'b';
	add.dispatch('change');
	await settle();

	assert.deepEqual(calls, ['second:b']);
	assert.equal(add.value, '');
	assert.equal(first.querySelectorAll('.ccCard').length, 0);
	assert.equal(second.querySelectorAll('.ccCard').length, 1);
});

// Lay out cards on a 3-column grid of 100px cells with 10px gaps.
function place(card, col, row, rows) {
	card.rect = {
		left: col * 110,
		width: 100,
		top: row * 110,
		bottom: row * 110 + rows * 110 - 10,
	};
}

test('drop targeting ignores a tall card from an earlier row and skips repeated moves', () => {
	const { cards, doc } = loadCards();
	const grid = new Element(doc, { class: 'ccGrid' });
	// Row 0: A (col 0), B (col 1, spans rows 0-1), D (col 2).
	// Row 1: C (col 0), E (col 2). Dragged: X, appended last.
	const ids = ['A', 'B', 'D', 'C', 'E', 'X'];
	const card = {};
	ids.forEach((id) => {
		card[id] = grid.appendChild(makeCard(doc, id, id === 'B' ? 2 : 1));
	});
	place(card.A, 0, 0, 1);
	place(card.B, 1, 0, 2);
	place(card.D, 2, 0, 1);
	place(card.C, 0, 1, 1);
	place(card.E, 2, 1, 1);
	cards.init(grid, {});

	let moves = 0;
	const insertBefore = grid.insertBefore.bind(grid);
	grid.insertBefore = (child, ref) => {
		moves++;

		return insertBefore(child, ref);
	};

	card.X.draggable = true;
	card.X.dispatch('dragstart');

	// Pointer in row 1, left of C's centre: B spans this band but begins in
	// row 0, so the drop belongs before C, not before B.
	grid.dispatch('dragover', { clientX: 20, clientY: 150 });
	assert.deepEqual(grid.children.map((c) => c.getAttribute('data-card')), ['A', 'B', 'D', 'X', 'C', 'E']);
	assert.equal(moves, 1);

	// The same reference on the next dragover must not move the node again.
	grid.dispatch('dragover', { clientX: 25, clientY: 155 });
	assert.equal(moves, 1);

	// Below every card: append.
	grid.dispatch('dragover', { clientX: 20, clientY: 400 });
	assert.equal(grid.children[grid.children.length - 1], card.X);

	card.X.dispatch('dragend');
});

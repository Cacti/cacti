/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
'use strict';

// Shared support for the theme tests: a recording stand-in for jQuery and a loader that
// evaluates a theme's main.js in a fresh context. Not a test file itself.

const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const root = path.join(__dirname, '..', '..');

const GETTERS = new Set(['attr', 'data', 'prop', 'css']);
const READERS = new Set(['val', 'text', 'html', 'width', 'height', 'scrollLeft', 'scrollTop', 'outerHeight', 'outerWidth']);
const EVENTS = new Set(['on', 'click', 'change', 'scroll', 'resize', 'dblclick', 'hover', 'mouseenter', 'mouseleave']);

function deferred() {
	const state = {done: [], fail: []};
	const promise = {
		done(fn) { state.done.push(fn); return promise; },
		fail(fn) { state.fail.push(fn); return promise; },
		resolve(...args) { state.done.forEach((fn) => fn(...args)); },
		reject(...args) { state.fail.forEach((fn) => fn(...args)); }
	};

	return promise;
}

/**
 * options.length(chain), options.items(chain) and options.value(chain, method, args) let a
 * test answer what the page would contain. A chain is {target, calls}.
 */
function fakeJQuery(options = {}) {
	const chains = [];
	const handlers = [];
	const requests = [];

	function wrap(target) {
		const chain = {target, calls: []};
		chains.push(chain);

		const proxy = new Proxy({}, {
			get(object, property) {
				if (property === 'length') return options.length ? options.length(chain) : 0;
				if (typeof property === 'symbol' || property === 'then') return undefined;

				return function(...args) {
					chain.calls.push({method: property, args});

					if (property === 'each') {
						const items = options.items ? options.items(chain) : [];
						items.forEach((item, index) => args[0].call(item, index, item));
						return proxy;
					}

					if (EVENTS.has(property)) {
						for (const fn of args.filter((arg) => typeof arg === 'function')) {
							handlers.push({chain, method: property, event: typeof args[0] === 'string' ? args[0] : property, fn});
						}
					}

					const reads = (GETTERS.has(property) && args.length === 1 && typeof args[0] === 'string') ||
						(READERS.has(property) && args.length === 0) ||
						property === 'is' || property === 'hasClass';

					if (reads) {
						return options.value ? options.value(chain, property, args) : undefined;
					}

					return proxy;
				};
			}
		});

		return proxy;
	}

	const $ = (target) => wrap(target);

	$.chains = chains;
	$.handlers = handlers;
	$.requests = requests;
	$.fn = {};
	$.trim = (value) => (value === undefined || value === null ? '' : String(value).trim());
	$.extend = (target, ...sources) => Object.assign(target, ...sources);
	$.cookie = () => undefined;
	$.ajax = (settings) => {
		const request = deferred();
		requests.push({settings, request});
		return request;
	};

	return $;
}

const builtins = new Set(Object.getOwnPropertyNames(globalThis));

function placeholder(name) {
	const fn = function() { return ''; };
	fn.toString = () => name;
	return fn;
}

/**
 * Evaluate include/themes/<theme>/main.js in a new context backed by `globals`. A global the
 * theme reads that the test did not provide resolves to a callable placeholder that returns ''.
 * Assignments to undeclared names land in `globals`, so a test can see what leaked.
 */
function loadTheme(theme, globals = {}) {
	const source = fs.readFileSync(path.join(root, 'include', 'themes', theme, 'main.js'), 'utf8');
	const context = vm.createContext(new Proxy(globals, {
		has: () => true,
		get(target, name) {
			if (name in target) return target[name];
			if (typeof name === 'symbol') return undefined;
			if (builtins.has(name)) return globalThis[name];
			return placeholder(name);
		}
	}));

	vm.runInContext(source, context);

	return context;
}

/* Names a theme defines or assigns at the top level of the file. */
function declaredNames(theme) {
	const globals = {};
	loadTheme(theme, Object.assign(globals, {$: fakeJQuery(), jQuery: fakeJQuery(), document: fakeDocument().document}));
	return new Set(Object.keys(globals));
}

function fakeDocument() {
	const attributes = {};
	const listeners = [];
	const documentElement = {
		setAttribute(name, value) { attributes[name] = String(value); },
		getAttribute(name) { return Object.prototype.hasOwnProperty.call(attributes, name) ? attributes[name] : null; }
	};

	return {
		attributes,
		listeners,
		document: {
			documentElement,
			addEventListener(type, fn) { listeners.push({type, fn}); },
			getElementById() { return null; }
		}
	};
}

module.exports = {root, fakeJQuery, fakeDocument, deferred, loadTheme, declaredNames};

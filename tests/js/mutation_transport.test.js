'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');
const root = path.join(__dirname, '../..');
const source = fs.readFileSync(path.join(root, 'include/layout.js'), 'utf8');
const start = source.indexOf('function cactiIsPostActionUrl(');
const end = source.indexOf('function loadUrl(', start);
const context = {URL, urlPath:'/cacti/', window:{location:{href:'https://cacti.example/cacti/host.php', origin:'https://cacti.example'}}};
vm.runInNewContext(source.slice(start, end), context);
const actions = JSON.parse(source.slice(start, end).match(/var actions = (\{[\s\S]*?\});/)[1]);

for (const [file, names] of Object.entries(actions)) {
	test(`${file} browser transport matches its server-side guard`, () => {
		const php = fs.readFileSync(path.join(root, file), 'utf8');
		const guard = php.match(/if \(in_array\(grv\('action'\), \[([\s\S]*?)\], true\)\) \{\s*csrf_require_post\(\);/);
		assert.ok(guard, file);
		const serverActions = Array.from(guard[1].matchAll(/'([^']+)'/g), match => match[1]);
		assert.deepEqual(names, serverActions);
		for (const action of names) assert.equal(context.cactiIsPostActionUrl(`/cacti/${file}?action=${action}&id=11`), true, action);
	});
}
test('read-only, external and plugin endpoints retain GET transport', () => {
	for (const href of ['/cacti/host.php?action=edit', '/cacti/plugins.php?action=readme', '/cacti/tree.php?action=get_node', '/cacti/utilities.php?action=view_logfile', '/cacti/plugins/fixture/tree.php?action=sortasc', 'https://external.example/cacti/links.php?action=delete_page', 'http://[invalid']) {
		assert.equal(context.cactiIsPostActionUrl(href), false, href);
	}
});
test('loadUrl converts mutation query fields to a token-bearing POST after the unsaved-form check', () => {
	const body = source.slice(source.indexOf('function loadUrl('), source.indexOf('function postUrl('));
	assert.ok(body.indexOf('checkFormStatus(') < body.indexOf('cactiIsPostActionUrl('));
	assert.match(body, /cactiPreparePostRequestFromUrl\(options\.url\)/);
	assert.match(body, /\$\.post\(options\.url, request\.data/);
});

function transportFixture(allow = true) {
	const calls = [];
	const chain = {done() {return this;}, fail() {return this;}};
	const $ = () => ({empty() {return this;}, remove() {return this;}});
	$.toast = () => ({reset() {}});
	$.ajaxQ = {abortAll() {}};
	$.extend = (target, fields) => Object.assign(target, fields);
	$.param = fields => new URLSearchParams(fields.map(({name, value}) => [name, value])).toString();
	$.post = (url, data, callback, type) => {calls.push({method:'POST', url, data, type}); return chain;};
	$.get = url => {calls.push({method:'GET', url}); return chain;};
	$.getJSON = $.get;
	const env = {...context, $, URLSearchParams, csrfMagicToken:'trusted-token', setCactiTabCookie() {}, clearAllTimeouts() {}, closeDateFilters() {}, handleAjaxResponse() {}, getPresentHTTPError() {}, checkFormStatus:() => allow, sanitizeAjaxOptions:options => ({funcStart:'', loadType:'noheader', ...options})};
	const prepare = source.slice(source.indexOf('function cactiPreparePostRequest('), source.indexOf('function submitPageUsingPost('));
	const transport = source.slice(source.indexOf('function loadUrl('), source.indexOf('function postUrl('));
	vm.runInNewContext(prepare + source.slice(start, end) + transport, env);
	return {env, calls};
}

test('the production loadUrl sends action fields and the trusted token in a POST body', () => {
	const {env, calls} = transportFixture();
	env.loadUrl({url:'/cacti/plugins.php?action=uninstall&plugin=fixture&__csrf_magic=stale'});
	assert.equal(calls.length, 1);
	assert.equal(calls[0].method, 'POST');
	assert.equal(calls[0].url, '/cacti/plugins.php');
	const fields = new URLSearchParams(calls[0].data);
	assert.equal(fields.get('action'), 'uninstall');
	assert.equal(fields.get('plugin'), 'fixture');
	assert.equal(fields.get('__csrf_magic'), 'trusted-token');
});

test('cancelled navigation sends nothing and read-only navigation still uses GET', () => {
	const cancelled = transportFixture(false);
	cancelled.env.loadUrl({url:'/cacti/utilities.php?action=clear_logfile'});
	assert.equal(cancelled.calls.length, 0);
	const read = transportFixture();
	read.env.loadUrl({url:'/cacti/utilities.php?action=view_logfile'});
	assert.equal(read.calls[0].method, 'GET');
});

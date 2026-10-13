'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');
const source = fs.readFileSync(path.join(__dirname, '../../include/js/csrf.js'), 'utf8');

function fixture(fallback = false) {
	class Xhr {
		constructor() { this.calls = []; this.headers = {}; }
		open(...args) { this.calls.push(args); }
		send(body) { this.body = body; }
		setRequestHeader(name, value) { this.headers[name.toLowerCase()] = value; }
	}
	const context = {
		URL, URLSearchParams, FormData, Blob, XMLHttpRequest: Xhr,
		csrfMagicName: '__csrf_magic', csrfMagicToken: 'trusted+token',
		window: {location: {href: 'https://cacti.example/cacti/', origin: 'https://cacti.example'}, FormData, URLSearchParams},
	};
	if (fallback) {
		const jquery = {ajaxSettings: {type: 'GET'}, extend: (_deep, target, ...rest) => Object.assign(target, ...rest), ajax: settings => settings};
		context.window.jQuery = context.jQuery = jquery;
	} else context.window.XMLHttpRequest = Xhr;
	vm.runInNewContext(source, context);
	return {context, xhr: new Xhr()};
}

for (const url of ['/cacti/update.php', 'update.php', 'https://cacti.example/update.php', '//cacti.example/update.php']) {
	test(`same-origin POST includes the token: ${url}`, () => {
		const {xhr} = fixture(); xhr.open('post', url); xhr.send('value=1');
		assert.equal(new URLSearchParams(xhr.body).get('__csrf_magic'), 'trusted+token');
		assert.equal(xhr.calls[0][2], true);
		assert.match(xhr.headers['content-type'], /application\/x-www-form-urlencoded/);
	});
}
for (const url of ['https://external.example/', '//external.example/', 'http://cacti.example/', 'https://cacti.example:8443/', 'https://[invalid']) {
	test(`external or invalid target receives the original body: ${url}`, () => {
		const {xhr} = fixture(); xhr.open('POST', url); xhr.send('value=1');
		assert.equal(xhr.body, 'value=1'); assert.deepEqual(xhr.headers, {});
	});
}
test('GET stays unchanged and reopening an XHR resets origin and method state', () => {
	const {xhr} = fixture(); xhr.open('POST', '/save'); xhr.open('GET', '/read'); xhr.send(null);
	assert.equal(xhr.body, null);
	xhr.open('POST', '/save'); xhr.open('POST', 'https://external.example/'); xhr.send('original');
	assert.equal(xhr.body, 'original');
});
test('explicit synchronous requests and caller content types are preserved', () => {
	const {xhr} = fixture(); xhr.open('POST', '/save', false); xhr.setRequestHeader('content-type', 'application/custom'); xhr.send('value=1');
	assert.equal(xhr.calls[0][2], false); assert.equal(xhr.headers['content-type'], 'application/custom');
});
test('FormData sends exactly one serialized token without forcing a multipart header', () => {
	const {xhr} = fixture(); const body = new FormData(); body.append('__csrf_magic', 'stale'); body.append('__csrf_magic', 'stale-again'); body.append('file', new Blob(['fixture']), 'fixture.txt');
	xhr.open('POST', '/upload'); xhr.send(body);
	assert.equal(xhr.body, body); assert.deepEqual(body.getAll('__csrf_magic'), ['trusted+token']); assert.deepEqual(xhr.headers, {});
});
test('external FormData is untouched', () => {
	const {xhr} = fixture(); const body = new FormData(); body.append('value', '1'); xhr.open('POST', 'https://external.example/'); xhr.send(body);
	assert.equal(body.has('__csrf_magic'), false);
});
test('URLSearchParams, null and omitted bodies receive usable token fields', () => {
	for (const body of [new URLSearchParams('value=1'), null, undefined]) {
		const {xhr} = fixture(); xhr.open('POST', '/save'); xhr.send(body);
		assert.equal(new URLSearchParams(xhr.body).get('__csrf_magic'), 'trusted+token');
	}
});
test('binary bodies are preserved and process retains the plain-object compatibility API', () => {
	const {context, xhr} = fixture(); const binary = new Blob(['fixture']); xhr.open('POST', '/save'); xhr.send(binary); assert.equal(xhr.body, binary);
	const fields = {value: 1}; assert.equal(context.CsrfMagic.process(fields), fields); assert.equal(fields.__csrf_magic, 'trusted+token');
});
test('legacy jQuery transport also restricts token injection to the same origin', () => {
	const {context} = fixture(true);
	assert.equal(context.jQuery.ajax({type:'POST', url:'https://external.example/', data:'value=1'}).data, 'value=1');
	assert.equal(new URLSearchParams(context.jQuery.ajax({method:'POST', url:'/save', data:null}).data).get('__csrf_magic'), 'trusted+token');
});

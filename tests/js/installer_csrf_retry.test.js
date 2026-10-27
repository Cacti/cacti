'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');
const source = fs.readFileSync(path.join(__dirname, '../../install/install.js'), 'utf8');
function fixture() {
	const posts = [], errors = [], httpErrors = [];
	const control = new Proxy({length: 0}, {get: (target, key) => key in target ? target[key] : () => control});
	const jquery = () => control;
	jquery.ajaxQ = {abortAll() {}};
	jquery.post = (url, body) => {
		const request = {url, body, done(cb) {this.onDone = cb; return this;}, fail(cb) {this.onFail = cb; return this;}};
		posts.push(request);
		return request;
	};
	const context = {$: jquery, csrfMagicToken: 'stale', PopupError: (...args) => errors.push(args), getPresentHTTPError: error => httpErrors.push(error), window: {}, document: {}};
	vm.createContext(context);
	vm.runInContext(source, context);
	context.prepareInstallData = step => JSON.stringify({step, AutomationMode: false});
	return {context, posts, errors, httpErrors};
}
const timeout = token => ({responseJSON: {error: 'csrf_timeout', csrfMagicToken: token, title: 'Expired', message: 'Reload'}});

test('installer retries the same step once and preserves disabled automation', () => {
	const {context, posts, errors} = fixture();
	context.performStep(7, true, false);
	assert.equal(posts[0].body.__csrf_magic, 'stale');
	posts[0].onFail(timeout('fresh'));
	assert.equal(posts.length, 2);
	assert.equal(posts[1].body.__csrf_magic, 'fresh');
	assert.equal(posts[1].body.data.step, 7);
	assert.equal(posts[1].body.data.AutomationMode, false);
	posts[1].onFail(timeout('newer'));
	assert.equal(posts.length, 2);
	assert.equal(errors.length, 1);
});
test('a timeout without a replacement token is not retried', () => {
	const {context, posts, errors} = fixture();
	context.performStep(7, true, false);
	posts[0].onFail(timeout(''));
	assert.equal(posts.length, 1);
	assert.equal(errors.length, 1);
});
test('ordinary installer failures retain HTTP error handling', () => {
	const {context, posts, httpErrors} = fixture();
	context.performStep(7, true, false);
	posts[0].onFail({status: 500});
	assert.equal(posts.length, 1);
	assert.equal(httpErrors.length, 1);
});
test('remote database connection tests retry a stale token only once', () => {
	const {context, posts, errors} = fixture();
	context.performTestConnection();
	posts[0].onFail(timeout('fresh'));
	assert.equal(posts.length, 2);
	assert.equal(posts[1].body.__csrf_magic, 'fresh');
	posts[1].onFail(timeout('newer'));
	assert.equal(posts.length, 2);
	assert.equal(errors.length, 1);
});

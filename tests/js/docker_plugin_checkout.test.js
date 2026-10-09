'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { spawnSync } = require('node:child_process');
const { test } = require('node:test');

const checkout = path.resolve(__dirname, '../e2e/checkout-plugin.sh');
// Synthetic repositories must not inherit a developer's signing, hooks, or
// URL rewrites. Production repository checks still use normal Git settings.
const fixtureEnvironment = { ...process.env, GIT_CONFIG_GLOBAL: '/dev/null', GIT_CONFIG_NOSYSTEM: '1' };

function fixture(t) {
	const root = fs.mkdtempSync(path.join(os.tmpdir(), 'cacti-plugin-checkout-'));
	t.after(() => fs.rmSync(root, { recursive: true, force: true }));
	const repository = path.join(root, 'plugin repository');
	fs.mkdirSync(repository);
	function git(...args) {
		const result = spawnSync('git', ['-C', repository, ...args], { encoding: 'utf8', env: fixtureEnvironment });
		assert.equal(result.status, 0, result.stderr);
		return result.stdout.trim();
	}
	git('init', '--quiet', '--initial-branch=develop');
	git('config', 'user.name', 'Cacti test');
	git('config', 'user.email', 'tests@example.invalid');
	fs.writeFileSync(path.join(repository, 'setup.php'), '<?php /* pinned plugin */\n');
	git('add', 'setup.php');
	git('-c', 'commit.gpgsign=false', 'commit', '--quiet', '-m', 'Initial fixture');
	const pinned = git('rev-parse', 'HEAD');
	git('tag', 'fixture-v1');
	fs.writeFileSync(path.join(repository, 'setup.php'), '<?php /* branch advanced */\n');
	git('add', 'setup.php');
	git('-c', 'commit.gpgsign=false', 'commit', '--quiet', '-m', 'Advance fixture');
	return { root, repository, pinned };
}

test('immutable plugin commit stays pinned after its branch advances', t => {
	const { root, repository, pinned } = fixture(t);
	const destination = path.join(root, 'pinned plugin');
	const result = spawnSync('sh', [checkout, repository, pinned, destination], { encoding: 'utf8', env: fixtureEnvironment });
	assert.equal(result.status, 0, result.stderr);
	assert.equal(fs.readFileSync(path.join(destination, 'setup.php'), 'utf8'), '<?php /* pinned plugin */\n');
	assert.equal(fs.existsSync(path.join(destination, '.git')), false);
});

for (const reference of ['develop', 'fixture-v1']) {
	test(`custom ${reference} reference uses a real detached checkout without Git metadata`, t => {
		const { root, repository } = fixture(t);
		const destination = path.join(root, 'custom plugin');
		const result = spawnSync('sh', [checkout, repository, reference, destination], { encoding: 'utf8', env: fixtureEnvironment });
		assert.equal(result.status, 0, result.stderr);
		const expected = reference === 'develop' ? 'branch advanced' : 'pinned plugin';
		assert.match(fs.readFileSync(path.join(destination, 'setup.php'), 'utf8'), new RegExp(expected));
		assert.equal(fs.existsSync(path.join(destination, '.git')), false);
	});
}

for (const reference of ['', '--upload-pack=touch injected', 'missing-reference']) {
	test(`invalid plugin reference ${JSON.stringify(reference)} fails closed`, t => {
		const { root, repository } = fixture(t);
		const destination = path.join(root, 'invalid plugin');
		const result = spawnSync('sh', [checkout, repository, reference, destination], { encoding: 'utf8', env: fixtureEnvironment });
		assert.notEqual(result.status, 0);
		assert.equal(fs.existsSync(path.join(destination, 'setup.php')), false);
		assert.equal(fs.existsSync(path.join(root, 'injected')), false);
	});
}

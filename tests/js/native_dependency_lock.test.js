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
const os = require('node:os');
const path = require('node:path');
const { spawnSync } = require('node:child_process');
const test = require('node:test');

const workflow = fs.readFileSync(path.join(__dirname, '..', '..', '.github', 'workflows', 'dependency-compatibility.yml'), 'utf8');

function command(stepName) {
	const step = workflow.split(`      - name: ${stepName}\n`)[1];
	assert.ok(step);
	assert.match(step.split('        run:')[0], /COMPOSER: composer\.compat\.json/);
	return step.match(/^        run: (.+)$/m)[1];
}

test('native resolution produces a lock before installation and executes no project scripts', () => {
	const root = fs.mkdtempSync(path.join(os.tmpdir(), 'cacti-native-lock-'));
	try {
		fs.writeFileSync(path.join(root, 'composer.compat.json'), JSON.stringify({
			name: 'cacti/native-lock-regression', type: 'project', license: 'GPL-2.0-or-later',
			require: {}, repositories: [{ 'packagist.org': false }],
			scripts: { 'post-update-cmd': 'php unexpected.php', 'post-install-cmd': 'php unexpected.php' },
		}));
		fs.writeFileSync(path.join(root, 'unexpected.php'), "<?php file_put_contents('unexpected-script', 'executed');\n");
		const env = {
			...process.env,
			COMPOSER: 'composer.compat.json',
			COMPOSER_HOME: path.join(root, 'composer-home'),
			COMPOSER_CACHE_DIR: path.join(root, 'composer-cache'),
			COMPOSER_DISABLE_NETWORK: '1',
		};
		const resolve = spawnSync('bash', ['-c', command('Resolve native dependencies')], { cwd: root, env, encoding: 'utf8' });
		assert.equal(resolve.status, 0, resolve.stderr);
		assert.equal(fs.existsSync(path.join(root, 'composer.compat.lock')), true);
		assert.equal(fs.existsSync(path.join(root, 'vendor')), false, 'resolution must not install code');
		assert.equal(fs.existsSync(path.join(root, 'unexpected-script')), false);

		const install = spawnSync('bash', ['-c', command('Install the native compatibility lock')], { cwd: root, env, encoding: 'utf8' });
		assert.equal(install.status, 0, install.stderr);
		assert.equal(fs.existsSync(path.join(root, 'vendor', 'autoload.php')), true);
		assert.equal(fs.existsSync(path.join(root, 'unexpected-script')), false);
		const smoke = spawnSync('php', ['-r', "require 'vendor/autoload.php';"], { cwd: root, env, encoding: 'utf8' });
		assert.equal(smoke.status, 0, smoke.stderr);
	} finally {
		fs.rmSync(root, { recursive: true, force: true });
	}
});

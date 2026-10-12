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

const root = path.join(__dirname, '..', '..');

function workflow(name) {
	return fs.readFileSync(path.join(root, '.github', 'workflows', name), 'utf8');
}

function runCommand(source, stepName) {
	const step = source.split(`      - name: ${stepName}\n`)[1];
	assert.ok(step, `${stepName} must exist`);
	return step.match(/^        run: (.+)$/m)[1];
}

for (const [file, step] of [
	['theme-e2e.yml', 'Install E2E dependencies'],
	['csp-e2e.yml', 'Install Playwright dependencies'],
]) {
	test(`${file} installs the lock without executing lifecycle scripts`, () => {
		const temp = fs.mkdtempSync(path.join(os.tmpdir(), 'cacti-browser-install-'));
		try {
			fs.writeFileSync(path.join(temp, 'package.json'), JSON.stringify({
				name: 'cacti-lifecycle-regression', version: '1.0.0',
				scripts: { postinstall: 'node postinstall.js' },
			}));
			fs.writeFileSync(path.join(temp, 'package-lock.json'), JSON.stringify({
				name: 'cacti-lifecycle-regression', version: '1.0.0', lockfileVersion: 3,
				packages: { '': { name: 'cacti-lifecycle-regression', version: '1.0.0', hasInstallScript: true } },
			}));
			fs.writeFileSync(path.join(temp, 'postinstall.js'), "require('node:fs').writeFileSync('unexpected-script', 'executed');\n");
			const command = runCommand(workflow(file), step);
			assert.equal(command, 'npm ci --ignore-scripts');
			const result = spawnSync('bash', ['-c', `${command} --offline`], {
				cwd: temp,
				env: { ...process.env, npm_config_cache: path.join(temp, 'cache') },
				encoding: 'utf8',
			});
			assert.equal(result.status, 0, result.stderr);
			assert.equal(fs.existsSync(path.join(temp, 'unexpected-script')), false);
		} finally {
			fs.rmSync(temp, { recursive: true, force: true });
		}
	});
}

for (const [file, step, args] of [
	['theme-e2e.yml', 'Install Playwright Chromium', ['install', '--with-deps', 'chromium']],
	['csp-e2e.yml', 'Install Playwright browser (chromium)', ['install', '--with-deps', 'chromium']],
	['csp-e2e.yml', 'Run Playwright suite (core CSP)', ['test', 'tests/csp.spec.ts']],
	['csp-e2e.yml', 'Run Playwright suite (plugin CSP -- thold + monitor)', ['test', 'tests/csp-plugins.spec.ts']],
]) {
	test(`${file}: ${step} executes only the installed local CLI`, () => {
		const temp = fs.mkdtempSync(path.join(os.tmpdir(), 'cacti-browser-cli-'));
		try {
			const cli = path.join(temp, 'node_modules', '@playwright', 'test');
			fs.mkdirSync(cli, { recursive: true });
			fs.writeFileSync(path.join(cli, 'cli.js'),
				"require('node:fs').writeFileSync('args.json', JSON.stringify(process.argv.slice(2)));\n");
			const result = spawnSync('bash', ['-c', runCommand(workflow(file), step)], { cwd: temp, encoding: 'utf8' });
			assert.equal(result.status, 0, result.stderr);
			assert.deepEqual(JSON.parse(fs.readFileSync(path.join(temp, 'args.json'), 'utf8')), args);
		} finally {
			fs.rmSync(temp, { recursive: true, force: true });
		}
	});
}

test('browser test packages are exact versions shared with the lockfile', () => {
	const manifest = JSON.parse(fs.readFileSync(path.join(root, 'tests', 'e2e', 'package.json'), 'utf8'));
	const lock = JSON.parse(fs.readFileSync(path.join(root, 'tests', 'e2e', 'package-lock.json'), 'utf8'));
	for (const name of ['@playwright/test', 'playwright']) {
		assert.match(manifest.devDependencies[name], /^\d+\.\d+\.\d+$/);
		assert.equal(lock.packages[`node_modules/${name}`].version, manifest.devDependencies[name]);
	}
});

test('PHP setup and thread-locking actions use immutable revisions', () => {
	for (const file of ['syntax.yml', 'theme-e2e.yml', 'oldissues.yml']) {
		const uses = [...workflow(file).matchAll(/uses: (shivammathur\/setup-php|dessant\/lock-threads)@(\S+)/g)];
		assert.ok(uses.length > 0);
		for (const [, name, ref] of uses) {
			assert.match(ref, /^[a-f0-9]{40}$/, `${name} must be pinned`);
		}
	}
});

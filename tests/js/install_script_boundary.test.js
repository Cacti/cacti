/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
'use strict';

const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');
const { mkdtempSync, mkdirSync, writeFileSync, existsSync, rmSync } = require('node:fs');
const { tmpdir } = require('node:os');
const path = require('node:path');
const test = require('node:test');

test('locked installs block dependency hooks while an explicit repository build runs', () => {
	const directory = mkdtempSync(path.join(tmpdir(), 'cacti-install-boundary-'));
	try {
		const dependency = path.join(directory, 'dependency');
		const app = path.join(directory, 'app');
		mkdirSync(dependency);
		mkdirSync(app);
		const marker = path.join(directory, 'dependency-hook-ran');
		const hook = "node -e \"require('node:fs').writeFileSync(process.env.CACTI_INSTALL_HOOK_MARKER, 'executed')\"";
		writeFileSync(path.join(dependency, 'package.json'), JSON.stringify({
			name: 'cacti-install-fixture', version: '1.0.0', scripts: { install: hook, postinstall: hook },
		}));
		writeFileSync(path.join(app, 'package.json'), JSON.stringify({
			name: 'cacti-root-fixture', version: '1.0.0', private: true,
			dependencies: { 'cacti-install-fixture': 'file:../dependency' },
			scripts: { postinstall: "node -e \"require('node:fs').writeFileSync('root-assets-built', 'built')\"" },
		}));
		const options = {
			cwd: app, env: { ...process.env, CACTI_INSTALL_HOOK_MARKER: marker },
			stdio: 'pipe', timeout: 30_000,
		};
		execFileSync('npm', ['install', '--package-lock-only', '--ignore-scripts', '--offline', '--no-audit', '--no-fund'], options);
		execFileSync('npm', ['ci', '--ignore-scripts', '--offline', '--no-audit', '--no-fund'], options);
		assert.equal(existsSync(marker), false);
		assert.equal(existsSync(path.join(app, 'root-assets-built')), false);
		execFileSync('npm', ['run', 'postinstall', '--ignore-scripts'], options);
		assert.equal(existsSync(path.join(app, 'root-assets-built')), true);
		assert.equal(existsSync(marker), false);
	} finally {
		rmSync(directory, { recursive: true, force: true });
	}
});

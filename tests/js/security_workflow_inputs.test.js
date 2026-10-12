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

const workflow = fs.readFileSync(path.join(__dirname, '..', '..', '.github', 'workflows', 'security-proof.yml'), 'utf8');
const step = workflow.split('      - name: Build proof matrix\n')[1].split('      - name: Enforce closure gate\n')[0];
const run = step.split('        run: |\n')[1].split('\n').map((line) => line.replace(/^          /, '')).join('\n');

function executeMatrix(repoName, branchList) {
	const root = fs.mkdtempSync(path.join(os.tmpdir(), 'cacti-workflow-inputs-'));
	const argsLog = path.join(root, 'args.json');
	const sentinel = path.join(root, 'unexpected-command');
	try {
		fs.mkdirSync(path.join(root, 'tests', 'security'), { recursive: true });
		fs.writeFileSync(path.join(root, 'tests', 'security', 'build_private_advisory_matrix.sh'),
			'#!/usr/bin/env node\n' +
			"require('node:fs').writeFileSync(process.env.ARGS_LOG, JSON.stringify(process.argv.slice(2)));\n",
			{ mode: 0o700 });
		for (const helper of ['build_sink_inventory.sh', 'build_architectural_helper_report.sh']) {
			fs.writeFileSync(path.join(root, 'tests', 'security', helper), '#!/bin/sh\nprintf "fixture\\n"\n', { mode: 0o700 });
		}

		const result = spawnSync('bash', ['-c', run], {
			cwd: root,
			env: {
				...process.env,
				GITHUB_RUN_ID: '42',
				ARGS_LOG: argsLog,
				REPO_NAME: repoName.replaceAll('{sentinel}', sentinel),
				BRANCH_LIST: branchList.replaceAll('{sentinel}', sentinel),
			},
			encoding: 'utf8',
		});

		assert.equal(result.status, 0, result.stderr);
		assert.equal(fs.existsSync(sentinel), false, 'input must never execute a second command');
		assert.deepEqual(JSON.parse(fs.readFileSync(argsLog, 'utf8')), [
			repoName.replaceAll('{sentinel}', sentinel),
			branchList.replaceAll('{sentinel}', sentinel),
			'security/proof-run/42',
		]);
		for (const output of ['sink_inventory.current.tsv', 'architectural_helper.summary.tsv', 'architectural_helper.hotspots.tsv']) {
			assert.equal(fs.readFileSync(path.join(root, 'security', 'proof-run', '42', output), 'utf8'), 'fixture\n');
		}
	} finally {
		fs.rmSync(root, { recursive: true, force: true });
	}
}

test('dispatch inputs enter the shell through environment variables', () => {
	assert.match(step, /REPO_NAME: \$\{\{ inputs\.repo_name \}\}/);
	assert.match(step, /BRANCH_LIST: \$\{\{ inputs\.branch_list \}\}/);
	assert.doesNotMatch(run, /\$\{\{\s*inputs\./);
});

test('the normal 1.2.x and develop proof matrix retains its arguments and artifacts', () => {
	executeMatrix('Cacti/cacti', '1.2.x develop');
});

test('a quoted repository input remains one literal argument', () => {
	executeMatrix('Cacti/cacti"; touch "{sentinel}"; #', '1.2.x develop');
});

test('branch command substitutions and quotes remain literal data', () => {
	executeMatrix('Cacti/cacti', '1.2.x $(touch "{sentinel}") `touch "{sentinel}"` "');
});

test('multiline inputs cannot append shell commands', () => {
	executeMatrix('Cacti/cacti\n touch "{sentinel}"', '1.2.x\n develop');
});

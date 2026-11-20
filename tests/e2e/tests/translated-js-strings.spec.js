'use strict';

const { test, expect } = require('@playwright/test');
const fs = require('node:fs');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const root = path.resolve(__dirname, '../../..');

const cases = [
	['aggregate_graphs.php', 'Hide Item Details', 'text'],
	['aggregate_graphs.php', 'Show Item Details', 'text'],
	['cdef.php', 'Delete CDEF Item', 'title'],
	['data_source_profiles.php', 'Delete Data Source Profile Item', 'title'],
	['host_templates.php', 'Delete Item from Device Template', 'title'],
	['package_import.php', 'Key Generation Required to Use Plugin', 'variable'],
];
const translated = `Détails d'élément "réseau" & chemin \\test\n</script><b>texte</b>`;

function renderedExpression(file, message, context) {
	const source = fs.readFileSync(path.join(root, file), 'utf8');
	const line = source.split('\n').find(line => line.includes(`__('${message}')`) && line.includes('<?php'));
	expect(line, `${file}: ${message}`).toBeTruthy();
	const expression = context === 'text' ? line.match(/\.text\((.*)\);/)[1]
		: context === 'title' ? line.match(/title:\s*(.*),\s*$/)[1]
		: line.match(/mixedReasonTitle\s*=\s*(.*);\s*$/)[1];
	const encoded = Buffer.from(translated).toString('base64');
	return execFileSync(process.env.CACTI_TEST_PHP || 'php', ['-r',
		`function __($message) { return base64_decode('${encoded}'); } ?>${expression}`], { encoding: 'utf8' });
}

for (const [file, message, context] of cases) {
	test(`${file}: ${message} survives PHP rendering and browser parsing`, async ({ page }) => {
		const errors = [];
		page.on('pageerror', error => errors.push(error.message));
		const expression = renderedExpression(file, message, context);
		await page.setContent(`<div id="label"></div><div id="dialog"></div><script>window.translatedLabel = ${expression};</script>`);
		await page.addScriptTag({ path: path.join(root, 'include/js/jquery.js') });
		await page.addScriptTag({ path: path.join(root, 'include/js/jquery-ui.js') });
		await page.evaluate(context => {
			if (context === 'title') {
				$('#dialog').dialog({ title: window.translatedLabel });
			} else {
				$('#label').text(window.translatedLabel);
			}
		}, context);
		const target = context === 'title' ? '.ui-dialog-title' : '#label';
		expect(await page.locator(target).textContent()).toBe(translated);
		await expect(page.locator('b')).toHaveCount(0);
		expect(errors).toEqual([]);
	});
}

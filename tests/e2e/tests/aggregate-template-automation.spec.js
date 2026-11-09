/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
'use strict';

const { test, expect } = require('@playwright/test');
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '../../..');
require('./fixture-coverage').collectCoverage(test);

const automationRows = ['automation_spacer', 'automation_enabled', 'automation_pattern', 'automation_host_template_id', 'automation_title', 'automation_max_members'];
const templateRows = ['name', 'spacer1', 'gprint_prefix', 'gprint_format', 'graph_type', 'total', 'total_type', 'total_prefix', 'order_type', ...automationRows];

// The aggregate template editor's inline script, padded to its line numbers
// in aggregate_templates.php so browser coverage maps back to that file. Its
// PHP print tags are replaced with the values the server would render.
function editorScript() {
	const source = fs.readFileSync(path.join(root, 'aggregate_templates.php'), 'utf8');
	const constants = fs.readFileSync(path.join(root, 'include/global_constants.php'), 'utf8');
	const anchor = source.indexOf("form_save_button('aggregate_templates.php', 'return', 'id');");
	const open = source.indexOf("<script type='text/javascript'>", anchor);
	const start = open + "<script type='text/javascript'>".length;
	const end = source.indexOf('</script>', start);
	expect(anchor).toBeGreaterThan(0);
	expect(open).toBeGreaterThan(anchor);
	expect(end).toBeGreaterThan(start);
	const body = source.slice(start, end)
		.replace(/<\?php print ([A-Z_]+); \?>/g, (tag, name) => {
			const value = constants.match(new RegExp(`define\\('${name}',\\s*(\\d+)\\)`));
			expect(value, name).not.toBeNull();
			return value[1];
		})
		.replace(/<\?php print __\('([^']+)'\); \?>/g, '$1');
	expect(body).not.toContain('<?');
	return '\n'.repeat(source.slice(0, open).split('\n').length - 1) + body + '\n//# sourceURL=http://cacti-fixture/aggregate_templates.php';
}

// toggleFields() comes from include/layout.js unchanged.
function toggleFieldsScript() {
	const source = fs.readFileSync(path.join(root, 'include/layout.js'), 'utf8');
	const start = source.indexOf('function toggleFields(fields');
	const end = source.indexOf('\n}\n', start);
	expect(start).toBeGreaterThan(-1);
	expect(end).toBeGreaterThan(start);
	return source.slice(start, end + 2);
}

async function openEditor(page, graphTemplateId, total) {
	await page.setContent('<!DOCTYPE html><form id="template_edit"><input id="id" value="' + (graphTemplateId ? 5 : 0) + '">' +
		'<select id="graph_template_id"><option value="0">None</option><option value="3">Traffic</option></select>' +
		'<div><span id="save_component_template"></span></div><table id="items"><tr><td>' +
		'<input type="checkbox" id="agg_total_1"><input type="checkbox" id="agg_skip_1"></td></tr></table>' +
		'<table id="settings">' + templateRows.map(name => `<tr id="row_${name}"><td>${name}</td></tr>`).join('') + '</table>' +
		'<select id="total"><option value="1">None</option><option value="2">All</option></select>' +
		'<select id="total_type"><option value="1">Similar</option><option value="2">All</option></select>' +
		'<input id="total_prefix" value=""><input type="submit" id="submit"></form>');
	await page.evaluate(([id, value]) => {
		document.getElementById('graph_template_id').value = id;
		document.getElementById('total').value = value;
	}, [String(graphTemplateId), String(total)]);
	await page.addScriptTag({ path: path.join(root, 'include/js/jquery.js') });
	await page.addScriptTag({ path: path.join(root, 'include/js/jquery-ui.js') });
	await page.addScriptTag({ content: toggleFieldsScript() });
	await page.addScriptTag({ content: editorScript() });
}

test('an aggregate template shows its automation fields with the template settings', async ({ page }) => {
	await openEditor(page, 3, 1);
	for (const name of automationRows) {
		await expect(page.locator(`#row_${name}`), name).toBeVisible();
	}
	await expect(page.locator('#graph_template_id')).toBeDisabled();
	// A saved template with no totalled item cannot be saved until one is chosen.
	await expect(page.locator('#submit')).toBeDisabled();
	// No total keeps the total type and prefix visible, and the prefix keeps its saved value.
	await expect(page.locator('#row_total_type')).toBeVisible();
	await expect(page.locator('#total_prefix')).toHaveValue('');
});

test('a new aggregate template hides the automation fields until a graph template is chosen', async ({ page }) => {
	await openEditor(page, 0, 2);
	for (const name of automationRows) {
		await expect(page.locator(`#row_${name}`), name).toBeHidden();
	}
	await expect(page.locator('#row_total_type')).toBeHidden();
	await expect(page.locator('#agg_total_1')).toBeChecked();
	await expect(page.locator('#submit')).toBeEnabled();
	await expect(page.locator('#total_prefix')).toHaveValue('Total');
});

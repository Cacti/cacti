/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
'use strict';

const { test, expect } = require('@playwright/test');
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '../../..');
require('./fixture-coverage').collectCoverage(test);

// The aggregate item editor's inline script, padded to its line numbers in
// aggregate_graphs.php so browser coverage maps back to that file.
function editorScript() {
	const source = fs.readFileSync(path.join(root, 'aggregate_graphs.php'), 'utf8');
	const anchor = source.indexOf("form_save_button(cacti_url($page_name");
	const open = source.indexOf("<script type='text/javascript'>", anchor);
	const start = open + "<script type='text/javascript'>".length;
	const end = source.indexOf('</script>', start);
	expect(anchor).toBeGreaterThan(0);
	expect(open).toBeGreaterThan(anchor);
	expect(end).toBeGreaterThan(start);
	const body = source.slice(start, end);
	expect(body).not.toContain('<?');
	return '\n'.repeat(source.slice(0, open).split('\n').length - 1) + body + '\n//# sourceURL=http://cacti-fixture/aggregate_graphs.php';
}

async function openEditor(page) {
	await page.setContent('<!DOCTYPE html><form>' +
		'<input type="checkbox" id="t_text_format"><input id="text_format" value="Traffic">' +
		'<input type="checkbox" id="t_legend" checked><input id="legend" value="">' +
		'<input type="checkbox" id="t_graph_type_id"><select id="graph_type_id"><option value="4">AREA</option><option value="5">LINE1</option></select>' +
		'<input type="checkbox" id="t_cdef_id" checked><select id="cdef_id"><option value="0">None</option></select>' +
		'</form>');
	await page.addScriptTag({ path: path.join(root, 'include/js/jquery.js') });
	await page.addScriptTag({ path: path.join(root, 'include/js/jquery-ui.js') });
	// A themed select is a selectmenu widget, which the editor must disable and enable too.
	await page.evaluate(() => { $('#graph_type_id').selectmenu(); });
	await page.addScriptTag({ content: editorScript() });
}

test('only the overrides that are switched on start editable', async ({ page }) => {
	await openEditor(page);
	await expect(page.locator('#text_format')).toBeDisabled();
	await expect(page.locator('#graph_type_id')).toBeDisabled();
	await expect(page.locator('#legend')).toBeEnabled();
	await expect(page.locator('#cdef_id')).toBeEnabled();
});

test('switching an override on or off enables or disables its field', async ({ page }) => {
	await openEditor(page);
	await page.evaluate(() => { toggleFieldEnabled('t_text_format'); toggleFieldEnabled('t_graph_type_id'); });
	await expect(page.locator('#text_format')).toBeEnabled();
	await expect(page.locator('#graph_type_id')).toBeEnabled();
	await page.evaluate(() => { toggleFieldEnabled('t_text_format'); toggleFieldEnabled('t_graph_type_id'); });
	await expect(page.locator('#text_format')).toBeDisabled();
	await expect(page.locator('#graph_type_id')).toBeDisabled();
});

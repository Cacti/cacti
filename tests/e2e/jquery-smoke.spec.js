const { test, expect } = require('@playwright/test');

const BASE = process.env.CACTI_BASE_URL || 'http://localhost:8080/cacti';

test('1 login page loads', async ({ page }) => {
	const r = await page.goto(`${BASE}/`, { waitUntil: 'domcontentloaded' });
	expect(r && r.status()).toBe(200);
});

test('2 login form fields present', async ({ page }) => {
	await page.goto(`${BASE}/`);
	await expect(page.locator('#login_username')).toBeVisible();
	await expect(page.locator('#login_password')).toBeVisible();
});

test.describe('JS bundle smoke', () => {
	test.beforeEach(async ({ page }) => {
		await page.goto(`${BASE}/`, { waitUntil: 'domcontentloaded' });
		await page.evaluate(() => {
			document.body.innerHTML = '';
		});
		await page.addScriptTag({ url: '/cacti/include/js/jquery.js' });
		await page.addScriptTag({ url: '/cacti/include/js/jquery-ui.js' });
	});

	test('3 jquery ui controlgroup initializes', async ({ page }) => {
		const state = await page.evaluate(() => {
			const $ = window.jQuery;
			document.body.insertAdjacentHTML('beforeend', '<fieldset id="cg"><input type="checkbox" id="a"><label for="a">A</label><input type="checkbox" id="b"><label for="b">B</label></fieldset>');
			$('#cg').controlgroup();

			return {
				version: $.ui.version,
				controlgroup: typeof $.fn.controlgroup,
				initialized: $('#cg').hasClass('ui-controlgroup')
			};
		});

		expect(state.version).toBe('1.14.2');
		expect(state.controlgroup).toBe('function');
		expect(state.initialized).toBeTruthy();
	});

	test('4 tablesorter plugin loads', async ({ page }) => {
		await page.addScriptTag({ url: '/cacti/include/js/jquery.tablesorter.js' });
		const ok = await page.evaluate(() => typeof window.jQuery?.fn?.tablesorter === 'function');
		expect(ok).toBeTruthy();
	});

	test('5 tablesorter basic sort init works', async ({ page }) => {
		await page.addScriptTag({ url: '/cacti/include/js/jquery.tablesorter.js' });
		const first = await page.evaluate(() => {
			const $ = window.jQuery;
			document.body.insertAdjacentHTML('beforeend', '<table id="t"><thead><tr><th>A</th></tr></thead><tbody><tr><td>2</td></tr><tr><td>1</td></tr></tbody></table>');
			$('#t').tablesorter();
			$('#t').trigger('sorton', [[[0, 0]]]);

			return $('#t tbody tr:first td').text();
		});
		expect(first).toBe('1');
	});

	test('6 tablesorter pager plugin loads', async ({ page }) => {
		await page.addScriptTag({ url: '/cacti/include/js/jquery.tablesorter.js' });
		await page.addScriptTag({ url: '/cacti/include/js/jquery.tablesorter.pager.js' });
		const ok = await page.evaluate(() => !!window.jQuery?.tablesorterPager && typeof window.jQuery.tablesorterPager.construct === 'function');
		expect(ok).toBeTruthy();
	});

	test('7 tablesorter pager init works', async ({ page }) => {
		await page.addScriptTag({ url: '/cacti/include/js/jquery.tablesorter.js' });
		await page.addScriptTag({ url: '/cacti/include/js/jquery.tablesorter.pager.js' });
		const ok = await page.evaluate(() => {
			const $ = window.jQuery;
			document.body.insertAdjacentHTML('beforeend', '<div id="pager" class="pager"><select class="pagesize"><option value="2">2</option></select><span class="pagedisplay"></span></div><table id="tp"><thead><tr><th>A</th></tr></thead><tbody><tr><td>1</td></tr><tr><td>2</td></tr><tr><td>3</td></tr></tbody></table>');
			$('#tp').tablesorter();
			$('#tp').tablesorterPager({ container: $('#pager'), size: 2 });

			return $('#pager .pagedisplay').length > 0;
		});
		expect(ok).toBeTruthy();
	});

	test('8 jquery validation plugin loads', async ({ page }) => {
		await page.addScriptTag({ url: '/cacti/include/js/jquery.validate/jquery.validate.js' });
		const ok = await page.evaluate(() => typeof window.jQuery?.fn?.validate === 'function');
		expect(ok).toBeTruthy();
	});

	test('9 jquery validation behavior works', async ({ page }) => {
		await page.addScriptTag({ url: '/cacti/include/js/jquery.validate/jquery.validate.js' });
		const values = await page.evaluate(() => {
			const $ = window.jQuery;
			document.body.insertAdjacentHTML('beforeend', '<form id="vf"><input name="x" required></form>');
			const v = $('#vf').validate();
			const empty = v.form();
			$('[name="x"]').val('ok');
			const full = v.form();

			return { empty, full };
		});
		expect(values.empty).toBeFalsy();
		expect(values.full).toBeTruthy();
	});

	test('10 dropdown plugin initializes', async ({ page }) => {
		await page.addScriptTag({ url: '/cacti/include/js/jquery.dropdown.js' });
		const ok = await page.evaluate(() => {
			const $ = window.jQuery;
			if (typeof $.fn.DropDownMenu !== 'function') {
				return false;
			}

			document.body.insertAdjacentHTML('beforeend', '<div id="dd">menu</div>');
			$('#dd').DropDownMenu({ html: '<h6>x</h6>' });

			return true;
		});
		expect(ok).toBeTruthy();
	});

	test('11 touch punch patch loads', async ({ page }) => {
		await page.addScriptTag({ url: '/cacti/include/js/jquery.ui.touch.punch.js' });
		const state = await page.evaluate(() => {
			const touchCapable = 'ontouchend' in document;
			const m = window.jQuery?.ui?.mouse?.prototype;
			const patched = !!(m && typeof m._touchStart === 'function' && typeof m._touchEnd === 'function');

			return { touchCapable, patched };
		});

		if (state.touchCapable) {
			expect(state.patched).toBeTruthy();
		} else {
			expect(state.patched).toBeFalsy();
		}
	});
});


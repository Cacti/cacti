import { expect, test } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import path from 'node:path';

// Exercises actual PHP/Twig output in Chromium without a database or web server.
const root = path.resolve(__dirname, '../../..');
const fixtures: Record<string, string> = JSON.parse(
	execFileSync('php', ['tests/fixtures/twig_pages.php'], { cwd: root, encoding: 'utf8' }),
);

for (const [name, html] of Object.entries(fixtures)) {
	test(`Twig ${name} preserves formatting and escapes stored markup`, async ({ page }) => {
		await page.setContent(html);
		await expect(page.locator('[data-twig-attack]')).toHaveCount(0);
		expect(await page.evaluate(() => Reflect.get(window, '__twigExecuted'))).toBeUndefined();

		if (name === 'about') {
			await expect(page.getByRole('link', { name: 'Tobi Oetiker' })).toHaveAttribute('href', 'http://tobi.oetiker.ch/');
		} else if (name === 'changelog') {
			await expect(page.getByRole('link', { name: '12', exact: true })).toHaveAttribute('href', 'https://github.com/cacti/cacti/issues/12');
		} else {
			await expect(page.locator('.filteredValue').first()).toHaveText('img');
			await expect(page.locator('tr')).toHaveCount(1);
		}

		if (name === 'keys' || name === 'repositories') {
			await expect(page.getByRole('checkbox')).toHaveCount(1);
			await page.getByRole('checkbox').check();
			await expect(page.getByRole('checkbox')).toBeChecked();
		}
	});
}

import { test, expect, type Page } from '@playwright/test';

/*
 * E2E regression for issue #7121: saving a data input method whose
 * input_string template contains a shell-quoted placeholder (the
 * "<reason>" / "<param>" pattern) must not raise the
 * "Input string contains dangerous shell characters" validation error.
 *
 * Cascade this guards against:
 *   data_input.php form_save -> cacti_input_string_is_safe() ->
 *     reject (raise_message "validation_error", redirect, exit) ->
 *   user perceives data input method as not saved ->
 *   later data source creation (advanced mode) finds no
 *     data_template_data linkage ->
 *   update_poller_cache() returns empty $poller_items ->
 *   poller_item table stays empty for that data source.
 *
 * The unit and integration suites pin the regex behavior directly. This
 * spec exercises the form path against a real Cacti instance so a
 * regression in the GUI plumbing (or a future tightening of the
 * validator that re-introduces the bug) is caught end-to-end.
 *
 * Skipped unless E2E_CACTI_FULL=1 because creating a Data Input Method
 * requires the full Cacti DB schema and a writable installation, which
 * the lighter csp/plugin specs do not exercise.
 */

const FULL = process.env.E2E_CACTI_FULL === '1';

async function loginAsAdmin(page: Page): Promise<void> {
    await page.goto('/');
    await page.locator('input[name="login_username"]').fill('admin');
    await page.locator('input[name="login_password"]').fill('admin');
    await Promise.all([
        page.waitForLoadState('networkidle'),
        page.locator('form#login input[type="submit"]').click(),
    ]);
}

test.describe('issue #7121 data input method save', () => {
    test.skip(!FULL, 'set E2E_CACTI_FULL=1 to exercise the data input method save path');

    test('saves a data input method whose template wraps a placeholder in double quotes', async ({ page }) => {
        await loginAsAdmin(page);

        await page.goto('/data_input.php?action=edit');

        const uniqueName = `e2e-7121-${Date.now()}`;
        await page.locator('input[name="name"]').fill(uniqueName);
        await page.locator('select[name="type_id"]').selectOption({ label: 'Script/Command' });

        /* The template that triggered #7121 in the wild. The literal
         * double-quote pair around <param> is the standard pattern for
         * passing a value that may contain whitespace (e.g.
         * "this is a test") to a script argument. */
        const template = '<path_php_binary> <path_cacti>/scripts/test.php "<param>"';
        await page.locator('textarea[name="input_string"]').fill(template);

        await Promise.all([
            page.waitForLoadState('networkidle'),
            page.locator('input[name="save_component_data_input"]').click(),
        ]);

        /* Assert the validation_error message did NOT fire. Cacti renders
         * raise_message() output in #message_container; a successful save
         * either redirects to ?action=edit&id=N or shows the success
         * banner. */
        const errorBanner = page.locator('text=Input string contains dangerous shell characters');
        await expect(errorBanner).toHaveCount(0);

        /* The URL after save should carry an integer id, confirming
         * sql_save() ran. */
        await expect(page).toHaveURL(/data_input\.php\?.*action=edit.*id=\d+/);
    });

    test('saves a data input method with a digit-suffixed placeholder name', async ({ page }) => {
        await loginAsAdmin(page);

        await page.goto('/data_input.php?action=edit');

        const uniqueName = `e2e-7121-digit-${Date.now()}`;
        await page.locator('input[name="name"]').fill(uniqueName);
        await page.locator('select[name="type_id"]').selectOption({ label: 'Script/Command' });

        /* Digit-suffixed placeholder names like <arg1> appear in many
         * vendor packages where multiple arguments share a base name.
         * The pre-fix strip pattern <[a-zA-Z_]+> would not consume them,
         * leaving stray <> in the residue that the new blocklist
         * rejected. */
        const template = '<path_cacti>/scripts/test.php <arg1> <arg2>';
        await page.locator('textarea[name="input_string"]').fill(template);

        await Promise.all([
            page.waitForLoadState('networkidle'),
            page.locator('input[name="save_component_data_input"]').click(),
        ]);

        const errorBanner = page.locator('text=Input string contains dangerous shell characters');
        await expect(errorBanner).toHaveCount(0);
        await expect(page).toHaveURL(/data_input\.php\?.*action=edit.*id=\d+/);
    });

    test('still rejects a template that smuggles a shell metacharacter outside placeholders', async ({ page }) => {
        await loginAsAdmin(page);

        await page.goto('/data_input.php?action=edit');

        await page.locator('input[name="name"]').fill(`e2e-7121-attack-${Date.now()}`);
        await page.locator('select[name="type_id"]').selectOption({ label: 'Script/Command' });

        /* Negative case: the GHSA-c4qp protection must remain. A
         * template carrying a literal shell command separator outside of
         * any placeholder must still be refused. */
        const malicious = '<path_cacti>/scripts/test.php; rm -rf /';
        await page.locator('textarea[name="input_string"]').fill(malicious);

        await Promise.all([
            page.waitForLoadState('networkidle'),
            page.locator('input[name="save_component_data_input"]').click(),
        ]);

        const errorBanner = page.locator('text=Input string contains dangerous shell characters');
        await expect(errorBanner).toHaveCount(1);
    });
});

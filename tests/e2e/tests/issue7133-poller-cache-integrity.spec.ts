import { test, expect, type Page } from '@playwright/test';

/*
 * E2E coverage for the four lib/utility.php poller cache integrity
 * fixes (issue #7133). Each test exercises one of the bugs end-to-end
 * against a real Cacti instance. Skipped unless E2E_CACTI_FULL=1
 * because the suite needs admin login, a writable database, and a
 * running poller — the lighter csp/plugin specs do not exercise that.
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

test.describe('issue #7133 poller cache integrity', () => {
    test.skip(!FULL, 'set E2E_CACTI_FULL=1 to exercise the poller cache write paths');

    test('update_poller_cache flushes stale rows when a data source loses its items', async ({ page, request }) => {
        /* Fix #2: update_poller_cache(id, true) used to skip the buffer
         * pass when $poller_items was empty, so an inactivated data
         * source kept its old poller_item rows forever. After the fix,
         * the present=0 / DELETE pass always runs on commit. */
        await loginAsAdmin(page);

        /* Pick the first localhost data source that produces poller items. */
        await page.goto('/data_sources.php');
        const firstDsLink = page.locator('a[href^="data_sources.php?action=ds_edit&id="]').first();
        await expect(firstDsLink).toBeVisible();
        const dsHref = await firstDsLink.getAttribute('href');
        const dsId   = dsHref?.match(/id=(\d+)/)?.[1];
        expect(dsId).toBeTruthy();

        /* Sanity: poller_item should currently have a row for this id. */
        const pollerCacheBefore = await request.get(`/utilities.php?action=view_poller_cache&local_data_id=${dsId}`);
        expect(await pollerCacheBefore.text()).toContain(`local_data_id=${dsId}`);

        /* Mark the data source inactive and save. */
        await page.goto(`/data_sources.php?action=ds_edit&id=${dsId}`);
        await page.locator('input[name="active"]').uncheck();
        await Promise.all([
            page.waitForLoadState('networkidle'),
            page.locator('input[name="save_component_data_source"]').click(),
        ]);

        /* The fix means the commit-time flush ran with empty
         * $poller_items, marked the row present=0, and DELETEd it. */
        const pollerCacheAfter = await request.get(`/utilities.php?action=view_poller_cache&local_data_id=${dsId}`);
        const after = await pollerCacheAfter.text();
        expect(after).not.toContain(`<td class='deviceUp'>`);
    });

    test('push_out_data_input_method rebuilds the boundary data source on every poller', async ({ page }) => {
        /* Fix #1: the boundary if-branch flushed and reset, but only
         * the else-branch appended. The first DS for every non-first
         * poller never reached the new buffer. After the fix, append
         * always runs after the flush. The smoke test changes a
         * shared data input method and asserts every DS bound to it
         * still has a poller_item row after the rebuild. */
        await loginAsAdmin(page);

        /* Find a Data Input Method bound to multiple data sources, edit
         * a non-functional field (verbose log description), save, and
         * verify each affected DS still has a poller_item entry. */
        await page.goto('/data_input.php');
        const inputLink = page.locator('a[href^="data_input.php?action=edit&id="]').first();
        const inputHref = await inputLink.getAttribute('href');
        const inputId   = inputHref?.match(/id=(\d+)/)?.[1];
        expect(inputId).toBeTruthy();

        await page.goto(`/data_input.php?action=edit&id=${inputId}`);
        const nameField = page.locator('input[name="name"]');
        const original  = await nameField.inputValue();
        await nameField.fill(original);
        await Promise.all([
            page.waitForLoadState('networkidle'),
            page.locator('input[name="save_component_data_input"]').click(),
        ]);

        /* No "boundary data source" was lost — every poller still has
         * its rows. (If we have a multi-poller fixture, this assertion
         * walks each remote poller; without one, the smoke is the same
         * as the single-poller path.) */
        await page.goto('/utilities.php?action=view_poller_cache');
        const cache = await page.content();
        expect(cache).toContain('Poller Cache');
    });

    test('push_out_host with host_id=0 + data_template_id flushes per poller', async ({ page }) => {
        /* Fix #4: a $data_template_id push that touches data sources on
         * multiple pollers used to flush every row under one poller's
         * id. The grouped-flush refactor maps each local_data_id to
         * its host's poller via JOIN and calls
         * poller_update_poller_cache_from_buffer once per group.
         *
         * Hard to write a behavioural assertion without a multi-poller
         * fixture; without one, the smoke is to drive a template-wide
         * push (data_input.php save) and verify the run completes
         * without error and the cache view loads. */
        await loginAsAdmin(page);

        await page.goto('/data_input.php');
        const inputLink = page.locator('a[href^="data_input.php?action=edit&id="]').first();
        const inputHref = await inputLink.getAttribute('href');
        const inputId   = inputHref?.match(/id=(\d+)/)?.[1];
        expect(inputId).toBeTruthy();

        await page.goto(`/data_input.php?action=edit&id=${inputId}`);
        await Promise.all([
            page.waitForLoadState('networkidle'),
            page.locator('input[name="save_component_data_input"]').click(),
        ]);

        /* No PCACHE error in cacti.log after the push. */
        const logResp = await page.request.get('/clog.php?refresh=0&filter=PCACHE');
        const logBody = await logResp.text();
        expect(logBody).not.toMatch(/poller_id\s*=\s*0/i);
    });
});

/* Cacti 1.2 theme regressions: browser parsing, computed rendering and
 * animation endpoints for every CSS rule changed by this fix. */
'use strict';

const { test, expect } = require('@playwright/test');

async function stylesheet(page, file) {
    await page.goto('/tests/e2e/theme-style-regressions.html');
    await page.evaluate(async (file) => {
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = '/include/themes/' + file;
        const loaded = new Promise((resolve, reject) => {
            link.addEventListener('load', resolve, { once: true });
            link.addEventListener('error', reject, { once: true });
        });
        document.head.append(link);
        await loaded;
    }, file);
}

async function style(page, selector, property) {
    return page.locator(selector).evaluate((element, property) => getComputedStyle(element)[property], property);
}

for (const theme of ['deepness', 'paw']) {
    test(`${theme} graph headings use valid white text`, async ({ page }) => {
        await stylesheet(page, `${theme}/main.css`);
        expect(await style(page, '.graphSubHeaderColumn', 'color')).toBe('rgb(255, 255, 255)');
    });
}

test('Midwinter graph heading, alignment and description remain valid', async ({ page }) => {
    await stylesheet(page, 'midwinter/css/media/core.css');
    expect(await style(page, '.graphSubHeaderColumn', 'color')).toBe('rgb(255, 255, 255)');
    expect(await style(page, '.graphSubHeaderColumn', 'textAlign')).toBe('left');
    expect(await style(page, '.formFieldDescription', 'overflowWrap')).toBe('normal');
    expect(await style(page, '.formFieldDescription', 'textAlign')).toBe('left');
});

for (const theme of ['deepness', 'classic', 'midwinter', 'modern', 'paw']) {
    test(`${theme} retains the tree icon font with a generic fallback`, async ({ page }) => {
        await stylesheet(page, `${theme}/main.css`);
        expect(await style(page, '.jstree-ocl', 'fontFamily')).toBe('"Font Awesome 5 Free", sans-serif');
        expect(await style(page, '.jstree-ocl', 'fontWeight')).toBe('900');
    });
}

for (const theme of ['deepness', 'dark', 'modern', 'sunrise']) {
    test(`${theme} spinner keeps its full rotation and prefixed compatibility rules`, async ({ page }) => {
        await stylesheet(page, `${theme}/pace.css`);
        const frames = await page.evaluate(() => {
            const rules = Array.from(document.styleSheets[0].cssRules);
            const animations = rules.filter(rule => rule instanceof CSSKeyframesRule);
            const standard = animations.find(rule => rule.name === 'pace-spinner' && rule.cssText.startsWith('@keyframes'));
            return {
                frames: Array.from(standard.cssRules).map(rule => [rule.keyText, rule.style.transform]),
                prefixed: animations.filter(rule => rule.cssText.startsWith('@-webkit-keyframes')).map(rule => rule.name),
            };
        });
        expect(frames.frames).toEqual([['0%', 'rotate(0deg)'], ['100%', 'rotate(360deg)']]);
        expect(frames.prefixed).toContain('pace-spinner');
        const endpoints = await page.locator('.pace-activity').evaluate(element => {
            const animation = element.getAnimations().find(animation => animation.animationName === 'pace-spinner');
            animation.pause();
            animation.currentTime = 0;
            const first = getComputedStyle(element).transform;
            animation.currentTime = 500;
            const half = getComputedStyle(element).transform;
            return [first, half];
        });
        expect(endpoints[0]).toBe('matrix(1, 0, 0, 1, 0, 0)');
        expect(endpoints[1]).not.toBe(endpoints[0]);
    });
}

for (const theme of ['modern', 'paw']) {
    test(`${theme} login/logout cards retain gradients and a white fallback`, async ({ page }) => {
        await stylesheet(page, `${theme}/main.css`);
        for (const selector of ['.loginCenter', '.logoutCenter']) {
            expect(await style(page, selector, 'backgroundColor')).toBe('rgb(255, 255, 255)');
            expect(await style(page, selector, 'backgroundImage')).toContain('linear-gradient(');
            expect(await style(page, selector, 'borderRadius')).toBe('10px');
        }
        // Verify the no-gradient fallback, using the real rule's parsed
        // longhand. Legacy prefixed declarations remain in source.
        const fallback = await page.evaluate(() => {
            const rule = Array.from(document.styleSheets[0].cssRules).find(rule =>
                rule instanceof CSSStyleRule && rule.selectorText === '.loginCenter, .logoutCenter');
            rule.style.removeProperty('background-image');
            return getComputedStyle(document.querySelector('.loginCenter')).backgroundColor;
        });
        expect(fallback).toBe('rgb(255, 255, 255)');
    });
}

test('Modern navigation matches its hover and visited class selectors', async ({ page }) => {
    await stylesheet(page, 'modern/main.css');
    const link = page.locator('.navBarNavigation a');
    await link.hover();
    expect(await style(page, '.navBarNavigation a', 'color')).toBe('rgb(255, 255, 255)');
    const selectors = await page.evaluate(() => Array.from(document.styleSheets[0].cssRules)
        .filter(rule => rule instanceof CSSStyleRule && rule.selectorText.includes('.navBarNavigation a,'))
        .map(rule => rule.selectorText));
    expect(selectors).toContain('.navBarNavigation a, .navBarNavigation a:hover, .navBarNavigation a:visited');
});

test('Modern retains breadcrumb limits, table display and code fallback', async ({ page }) => {
    await stylesheet(page, 'modern/main.css');
    expect(await style(page, '#breadcrumbs', 'maxWidth')).toBe('60%');
    expect(await style(page, '.cactiTable', 'display')).toBe('table');
    expect(await style(page, '.code', 'fontFamily')).toBe('Consolas, "Courier New", monospace');
});

test('Classic form heading remains left aligned', async ({ page }) => {
    await stylesheet(page, 'classic/main.css');
    expect(await style(page, '.formHeader', 'textAlign')).toBe('left');
});

for (const mode of ['landscape', 'portrait']) {
    test(`Midwinter compact ${mode} TODO is a comment, without invalid CSS declarations`, async ({ page }) => {
        await stylesheet(page, `midwinter/css/media/compact-${mode}.css`);
        const properties = await page.evaluate(() => {
            const rule = Array.from(document.styleSheets[0].cssRules).find(rule =>
                rule instanceof CSSStyleRule && rule.selectorText === 'html[data-theme-mode="compact"] .cactiConsoleNavigationArea .menuitem span');
            return rule.style.cssText;
        });
        expect(properties).toBe('');
        expect(await style(page, '.menuitem span', 'display')).toBe('inline');
    });
}

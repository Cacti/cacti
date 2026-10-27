/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
// Self-contained so Playwright can evaluate it in the browser.
export function textContrast(element: Element): number {
	const rgba = (value: string) => {
		const parts = value.match(/[\d.]+/g)!.map(Number);
		return [parts[0], parts[1], parts[2], parts[3] ?? 1];
	};
	let bg = [255, 255, 255];
	const ancestors: Element[] = [];
	for (let e: Element | null = element; e; e = e.parentElement) ancestors.unshift(e);
	for (const e of ancestors) {
		const c = rgba(getComputedStyle(e).backgroundColor);
		bg = bg.map((v, i) => c[i] * c[3] + v * (1 - c[3]));
	}
	const lum = (c: number[]) => c.slice(0, 3).map(v => {
		v /= 255;
		return v <= .04045 ? v / 12.92 : ((v + .055) / 1.055) ** 2.4;
	}).reduce((sum, v, i) => sum + v * [.2126, .7152, .0722][i], 0);
	const fg = lum(rgba(getComputedStyle(element).color)), surface = lum(bg);
	return (Math.max(fg, surface) + .05) / (Math.min(fg, surface) + .05);
}

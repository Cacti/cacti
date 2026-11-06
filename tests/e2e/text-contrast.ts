/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
// Self-contained so Playwright can evaluate it in the browser.
export function textContrast(element: Element): number {
	const rgba = (value: string) => {
		const parts = value.match(/[\d.]+/g)!.map(Number);
		return [parts[0], parts[1], parts[2], parts[3] ?? 1];
	};
	const over = (fg: number[], bg: number[]) => bg.map((v, i) => fg[i] * fg[3] + v * (1 - fg[3]));
	let surfaces = [[255, 255, 255]];
	const ancestors: Element[] = [];
	for (let e: Element | null = element; e; e = e.parentElement) ancestors.unshift(e);
	for (const e of ancestors) {
		const c = rgba(getComputedStyle(e).backgroundColor);
		surfaces = surfaces.map(bg => over(c, bg));
		// Check every linear-gradient stop; raster textures use their fallback color.
		const image = getComputedStyle(e).backgroundImage;
		if (image.startsWith('linear-gradient(')) {
			const stops = (image.match(/rgba?\([^)]+\)/g) || []).map(rgba);
			surfaces = surfaces.flatMap(bg => stops.map(stop => over(stop, bg)));
		}
	}
	const lum = (c: number[]) => c.slice(0, 3).map(v => {
		v /= 255;
		return v <= .04045 ? v / 12.92 : ((v + .055) / 1.055) ** 2.4;
	}).reduce((sum, v, i) => sum + v * [.2126, .7152, .0722][i], 0);
	const color = rgba(getComputedStyle(element).color);
	return Math.min(...surfaces.map(bg => {
		const fg = lum(over(color, bg)), surface = lum(bg);
		return (Math.max(fg, surface) + .05) / (Math.min(fg, surface) + .05);
	}));
}

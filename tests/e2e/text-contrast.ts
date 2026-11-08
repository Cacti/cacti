/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
// Self-contained so Playwright can evaluate it in the browser.
export function textContrast(element: Element): number {
	// Canvas parsing normalizes every computed form, including the color(srgb ...)
	// that color-mix() produces, which a numeric regex would read as 0-1 channels.
	const canvas = document.createElement('canvas');
	canvas.width = canvas.height = 1;
	const context = canvas.getContext('2d', { willReadFrequently: true })!;
	const rgba = (value: string) => {
		// Canvas ignores an invalid fillStyle, so a value that does not replace
		// both sentinels would otherwise be measured as the previous color.
		context.fillStyle = '#000';
		context.fillStyle = value;
		const first = context.fillStyle;
		context.fillStyle = '#fff';
		context.fillStyle = value;
		if (context.fillStyle !== first) throw new Error(`textContrast cannot parse color: ${value}`);
		context.clearRect(0, 0, 1, 1);
		context.fillRect(0, 0, 1, 1);
		const pixel = context.getImageData(0, 0, 1, 1).data;
		return [pixel[0], pixel[1], pixel[2], pixel[3] / 255];
	};
	const over = (fg: number[], bg: number[]) => bg.map((v, i) => fg[i] * fg[3] + v * (1 - fg[3]));
	let surfaces = [[255, 255, 255]];
	const ancestors: Element[] = [];
	for (let e: Element | null = element; e; e = e.parentElement) ancestors.unshift(e);
	for (const e of ancestors) {
		const c = rgba(getComputedStyle(e).backgroundColor);
		surfaces = surfaces.map(bg => over(c, bg));
		// Sample stops and intermediate sRGB colors; raster textures use their fallback color.
		const image = getComputedStyle(e).backgroundImage;
		if (image.startsWith('linear-gradient(')) {
			const stops = (image.match(/rgba?\([^)]+\)/g) || []).map(rgba);
			const samples = stops.flatMap((stop, index) => index === 0 ? [stop] :
				[.25, .5, .75, 1].map(t => stop.map((v, channel) => stops[index - 1][channel] * (1 - t) + v * t)));
			surfaces = surfaces.flatMap(bg => samples.map(stop => over(stop, bg)));
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

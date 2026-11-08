/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
// Self-contained so Playwright can evaluate it in the browser.
// Returns the lowest WCAG contrast ratio between the element's text and any
// color painted behind it. Paint it cannot model throws instead, because an
// optimistic ratio would pass text that is unreadable.
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
	const unmeasurable = (what: string) => new Error(`textContrast cannot measure ${what}`);
	// Splits a computed value on commas outside parentheses.
	const split = (value: string) => {
		const parts = [''];
		let depth = 0;
		for (const char of value) {
			if (char === '(') depth++;
			if (char === ')') depth--;
			if (char === ',' && depth === 0) parts.push('');
			else parts[parts.length - 1] += char;
		}
		return parts.map(part => part.trim());
	};
	const over = (fg: number[], bg: number[]) => bg.map((v, i) => fg[i] * fg[3] + v * (1 - fg[3]));
	const lerp = (from: number[], to: number[], t: number) => from.map((v, i) => v + (to[i] - v) * t);
	// Each surface is an opaque segment [from, to]; every color between the two
	// is painted somewhere. A solid surface has from equal to to.
	type Segment = [number[], number[]];
	const white = [255, 255, 255];
	let surfaces: Segment[] = [[white, white]];
	const color = /(?:color-mix|color|rgba?|hsla?|hwb|lab|lch|oklab|oklch)\((?:[^()]|\([^()]*\))*\)/;
	const gradient = /^(?:-webkit-)?(?:repeating-)?(?:linear|radial|conic)-gradient\((.*)\)$/;
	const hint = /^-?[\d.]+(?:%|[a-z]+)?$/;
	const ancestors: Element[] = [];
	for (let e: Element | null = element; e; e = e.parentElement) ancestors.unshift(e);
	for (const e of ancestors) {
		const style = getComputedStyle(e);
		// Group opacity blends text and surfaces with whatever is behind them.
		if (Number(style.opacity) < 1) throw unmeasurable(`opacity ${style.opacity} on ${e.tagName}`);
		const c = rgba(style.backgroundColor);
		surfaces = surfaces.map(([from, to]) => [over(c, from), over(c, to)]);
		if (style.backgroundImage === 'none') continue;
		// The first listed layer paints on top; raster textures use their fallback color.
		for (const layer of split(style.backgroundImage).reverse()) {
			if (layer.startsWith('url(')) continue;
			const match = gradient.exec(layer);
			if (!match) throw unmeasurable(`background-image ${layer}`);
			const args = split(match[1]);
			// Interpolating in another color space leaves the sRGB segment between stops.
			if (/^in\s|\sin\s/.test(args[0])) throw unmeasurable(`gradient color space ${args[0]}`);
			const stops: number[][] = [];
			args.forEach((arg, index) => {
				const stop = color.exec(arg);
				if (stop) stops.push(rgba(stop[0]));
				// Only the leading geometry and position-only hints may omit a color.
				else if (index > 0 && !hint.test(arg)) throw unmeasurable(`gradient stop ${arg}`);
			});
			if (stops.length === 0) throw unmeasurable(`gradient without color stops ${layer}`);
			if (stops.length === 1) stops.push(stops[0]);
			// Browsers interpolate premultiplied colors, so each segment composited
			// over a fixed backdrop is exactly the line between its composited stops.
			surfaces = surfaces.flatMap(([from, to]) => {
				const backdrops = from.every((v, i) => v === to[i]) ? [from] :
					Array.from({ length: 17 }, (_, i) => lerp(from, to, i / 16));
				return backdrops.flatMap(bg => stops.slice(1).map((stop, i): Segment => [over(stops[i], bg), over(stop, bg)]));
			});
		}
	}
	const lum = (c: number[]) => c.slice(0, 3).map(v => {
		v /= 255;
		return v <= .04045 ? v / 12.92 : ((v + .055) / 1.055) ** 2.4;
	}).reduce((sum, v, i) => sum + v * [.2126, .7152, .0722][i], 0);
	const ratio = (a: number, b: number) => (Math.max(a, b) + .05) / (Math.min(a, b) + .05);
	const text = rgba(getComputedStyle(element).color);
	return Math.min(...surfaces.map(([from, to]) => {
		const at = (t: number) => lerp(from, to, t);
		if (text[3] === 1) {
			// Channels are linear in t and luminance is convex in each channel, so
			// luminance is convex along the segment: its maximum is an endpoint and a
			// ternary search finds its minimum. Every luminance between is painted.
			let low = 0, high = 1;
			for (let i = 0; i < 60; i++) {
				const a = low + (high - low) / 3, b = high - (high - low) / 3;
				if (lum(at(a)) < lum(at(b))) high = b;
				else low = a;
			}
			const darkest = Math.min(lum(from), lum(to), lum(at((low + high) / 2)));
			const lightest = Math.max(lum(from), lum(to));
			const fg = lum(text);
			return ratio(fg, Math.min(Math.max(fg, darkest), lightest));
		}
		// Translucent text moves with the surface, so search a dense grid and
		// refine around its lowest sample.
		const contrast = (t: number) => ratio(lum(over(text, at(t))), lum(at(t)));
		const steps = from.every((v, i) => v === to[i]) ? 1 : 64;
		let best = 0;
		for (let i = 1; i <= steps; i++) if (contrast(i / steps) < contrast(best / steps)) best = i;
		let low = Math.max(0, best - 1) / steps, high = Math.min(steps, best + 1) / steps;
		for (let i = 0; i < 60; i++) {
			const a = low + (high - low) / 3, b = high - (high - low) / 3;
			if (contrast(a) < contrast(b)) high = b;
			else low = a;
		}
		return Math.min(contrast(best / steps), contrast((low + high) / 2));
	}));
}

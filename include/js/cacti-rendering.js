/* Copyright (C) 2026 The Cacti Group. SPDX-License-Identifier: GPL-2.0-or-later */

// One synchronous interface for legacy widgets that intentionally accept menu
// markup. Labels use text APIs instead. This policy is not a general-purpose
// template renderer: application attributes, IDs, styles and embeds are removed.
(() => {
	'use strict';

	function menuFragment(value) {
		const content = typeof value === 'string' ? value : '';
		if (!window.DOMPurify || !window.DOMPurify.isSupported) {
			return document.createTextNode(content);
		}
		const fragment = window.DOMPurify.sanitize(content, {
			ALLOWED_TAGS: ['h6', 'div', 'a', 'span', 'p', 'br', 'ul', 'ol', 'li', 'strong', 'em', 'b', 'i'],
			ALLOWED_ATTR: ['href', 'title'],
			ALLOW_DATA_ATTR: false,
			ALLOW_ARIA_ATTR: false,
			RETURN_DOM_FRAGMENT: true
		});
		fragment.querySelectorAll('a[href]').forEach(link => {
			try {
				const url = new URL(link.getAttribute('href'), document.baseURI);
				if (!['http:', 'https:', 'mailto:', 'tel:'].includes(url.protocol)) {
					link.removeAttribute('href');
				}
			} catch (error) {
				link.removeAttribute('href');
			}
		});
		return fragment;
	}

	// Legacy classic-script loaders need a shared synchronous interface. An own,
	// immutable value also prevents HTML named properties from replacing it.
	Object.defineProperty(window, 'CactiRenderer', {
		value: Object.freeze({ menuFragment }),
		configurable: true
	});
})();

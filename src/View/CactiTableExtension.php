<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

namespace Cacti\View;

use Closure;
use InvalidArgumentException;
use Twig\Extension\AbstractExtension;
use Twig\Markup;
use Twig\TwigFunction;

/** Bridges only Cacti's existing table markup, including saved column visibility. */
final class CactiTableExtension extends AbstractExtension {
	public function getFunctions(): array {
		return [
			new TwigFunction('cacti_box', $this->box(...)),
			new TwigFunction('cacti_end_box', $this->endBox(...)),
			new TwigFunction('cacti_section', $this->section(...)),
			new TwigFunction('cacti_row', $this->row(...)),
			new TwigFunction('cacti_cell', $this->cell(...)),
			new TwigFunction('cacti_checkbox', $this->checkbox(...)),
			new TwigFunction('cacti_filter', $this->filter(...)),
			new TwigFunction('cacti_number', 'number_format_i18n'),
		];
	}

	public function box(string $title): Markup {
		return $this->capture(static fn () => html_start_box(html_escape($title), '100%', false, 3, 'center', ''));
	}

	public function endBox(): Markup {
		return $this->capture(static fn () => html_end_box());
	}

	public function section(string $title, int $columns): Markup {
		return $this->capture(static fn () => html_section_header(html_escape($title), $columns));
	}

	public function row(?int $id = null, bool $light = false, bool $disabled = false): Markup {
		return $this->capture(static fn () => form_alternate_row($id === null ? '' : 'line' . $id, $light, $disabled));
	}

	public function cell(string|int|float|null|Markup $text, int $id, string $class = ''): Markup {
		if (!in_array($class, ['', 'right', 'left', 'center', 'nowrap'], true)) {
			throw new InvalidArgumentException('Invalid table cell class');
		}

		// Only markup produced by this adapter bypasses text escaping in our templates.
		$html = $text instanceof Markup ? (string) $text : html_escape((string) $text);

		return $this->capture(static fn () => form_selectable_cell($html, $id, '', $class));
	}

	public function checkbox(string $title, int $id, bool $disabled = false): Markup {
		return $this->capture(static fn () => form_checkbox_cell($title, $id, $disabled));
	}

	public function filter(string|int|float|null $text, string $search = '', string $url = ''): Markup {
		// Links in these views are local, built by their PHP presenter or fixed templates.
		if ($url !== '' && (str_starts_with($url, '//') || str_contains($url, ':') || str_contains($url, '\\'))) {
			throw new InvalidArgumentException('Expected a local Cacti URL');
		}

		// filter_value escapes text before adding its own highlight span.
		$html = filter_value($text, $search);

		if ($url !== '' && $html !== '') {
			$html = '<a class="linkEditMain" href="' . html_escape_attr($url) . '" title="">' . $html . '</a>';
		}

		return new Markup($html, 'UTF-8');
	}

	private function capture(Closure $render): Markup {
		ob_start();

		try {
			$render();

			return new Markup((string) ob_get_contents(), 'UTF-8');
		} finally {
			ob_end_clean();
		}
	}
}

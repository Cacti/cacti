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

namespace Cacti\Controller;

use Cacti\View\TemplateRenderer;
use Symfony\Component\HttpFoundation\Response;

/** Keeps request/session access at the legacy entry point during list migration. */
abstract class TableController {
	public function __construct(private TemplateRenderer $renderer) {
	}

	/** @param list<array<string, mixed>> $rows
	 * @param array<string, string> $labels
	 * @param array<string, mixed>  $options
	 */
	protected function render(string $template, array $rows, string $search, array $labels, array $options = []): Response {
		return new Response($this->renderer->render($template, [
			'rows'   => $rows,
			'search' => $search,
			'labels' => $labels,
		] + $options));
	}
}

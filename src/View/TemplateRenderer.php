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

use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/** Renders repository-owned templates with explicit per-view data. */
final class TemplateRenderer {
	private Environment $twig;

	public function __construct(string $templateDirectory) {
		$this->twig = new Environment(new FilesystemLoader($templateDirectory), [
			'autoescape'       => 'html',
			'strict_variables' => true,
			'cache'            => false,
		]);
		$this->twig->addExtension(new CactiTableExtension());
	}

	public static function create(): self {
		return new self(dirname(__DIR__, 2) . '/templates');
	}

	/** @param array<string, mixed> $context */
	public function render(string $template, array $context): string {
		return $this->twig->render($template, $context);
	}
}

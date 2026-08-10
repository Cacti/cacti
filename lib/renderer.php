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

final class CactiRenderer {
	private readonly string $template_path;

	public function __construct(string $template_path) {
		// realpath() raises a ValueError on a null byte, so reject it up front
		// and fail through the same "does not exist" path.
		if (str_contains($template_path, "\0")) {
			throw new InvalidArgumentException('Renderer template path does not exist');
		}

		$real_path = realpath($template_path);

		if ($real_path === false || !is_dir($real_path)) {
			throw new InvalidArgumentException('Renderer template path does not exist');
		}

		// A renderer rooted at a filesystem root makes the containment prefix
		// collapse to that root, which str_starts_with() then matches for every
		// absolute path. Trim both separators so the test holds regardless of the
		// OS running it, and refuse a POSIX root ("/" -> "") or a Windows drive
		// root ("C:\" -> "C:") so the check stays fail-closed on either platform.
		$trimmed = rtrim($real_path, '/\\');

		if ($trimmed === '' || preg_match('/^[A-Za-z]:$/', $trimmed) === 1) {
			throw new InvalidArgumentException('Renderer template path does not exist');
		}

		$this->template_path = $trimmed;
	}

	public function render(string $template, array $context = []) : string {
		return $this->renderFile($this->resolveTemplate($template), $context);
	}

	public function renderFile(string $template_file, array $context = []) : string {
		$template_file = $this->validateTemplateFile($template_file);

		$start_level = ob_get_level();

		ob_start();

		try {
			$this->includeTemplate($template_file, $context);

			return $this->drainOutputBuffers($start_level);
		} catch (Throwable $e) {
			$this->drainOutputBuffers($start_level);

			throw $e;
		}
	}

	// A template should close any buffer it opens, but a stray ob_start() must
	// not leak a level or scramble output order. Pop innermost-first (the only
	// order ob_get_clean() allows), then replay outermost-first so the result
	// matches the order the template actually wrote it in.
	private function drainOutputBuffers(int $start_level) : string {
		$chunks = [];

		while (ob_get_level() > $start_level) {
			$chunks[] = (string) ob_get_clean();
		}

		return implode('', array_reverse($chunks));
	}

	private function resolveTemplate(string $template) : string {
		if ($template === '' || str_contains($template, "\0")) {
			throw new InvalidArgumentException('Renderer template name is invalid');
		}

		if (str_starts_with($template, '/') || str_starts_with($template, '\\') || preg_match('/^[A-Za-z]:[\/\\\\]/', $template) === 1) {
			throw new InvalidArgumentException('Renderer template name must be relative');
		}

		return $this->validateTemplateFile($this->template_path . DIRECTORY_SEPARATOR . $template);
	}

	private function validateTemplateFile(string $template_file) : string {
		// renderFile() reaches here with a caller-supplied path that never passed
		// through resolveTemplate()'s null-byte check, so guard realpath() here too.
		if (str_contains($template_file, "\0")) {
			throw new InvalidArgumentException('Renderer template file does not exist');
		}

		$real_path = realpath($template_file);

		if ($real_path === false || !is_file($real_path)) {
			throw new InvalidArgumentException('Renderer template file does not exist');
		}

		// Anchor containment with a trailing separator so a sibling directory
		// sharing the prefix (".../tmpl-evil" against ".../tmpl") cannot match.
		// str_starts_with() is case-sensitive, which on a case-insensitive
		// Windows volume only ever over-rejects (fail-closed), so leave it.
		//
		// (No separate $real_path !== $this->template_path check: the is_file()
		// check above and the is_dir() check in the constructor already make
		// that comparison unreachable, since the same real path can't be both.)
		$base_path = rtrim($this->template_path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

		if (!str_starts_with($real_path, $base_path)) {
			throw new InvalidArgumentException('Renderer template file is outside the template path');
		}

		return $real_path;
	}

	private function includeTemplate(string $template_file, array $context) : void {
		(static function (string $__cacti_template_file, array $__cacti_template_context) : void {
			extract($__cacti_template_context, EXTR_SKIP);
			unset($__cacti_template_context);

			include $__cacti_template_file;
		})($template_file, $context);
	}
}

function cacti_renderer(?string $template_path = null) : CactiRenderer {
	static $renderers = [];

	if ($template_path === null) {
		$template_path = CACTI_PATH_INCLUDE . '/views';
	}

	if (str_contains($template_path, "\0")) {
		throw new InvalidArgumentException('Renderer template path does not exist');
	}

	$key = realpath($template_path);

	if ($key === false) {
		throw new InvalidArgumentException('Renderer template path does not exist');
	}

	if (!isset($renderers[$key])) {
		$renderers[$key] = new CactiRenderer($key);
	}

	return $renderers[$key];
}

function cacti_render(string $template, array $context = [], ?string $template_path = null) : string {
	return cacti_renderer($template_path)->render($template, $context);
}

function cacti_render_file(string $template_file, array $context = [], ?string $template_path = null) : string {
	return cacti_renderer($template_path)->renderFile($template_file, $context);
}

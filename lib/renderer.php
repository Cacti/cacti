<?php
/*
 +-------------------------------------------------------------------------
 | Copyright (C) 2004-2026 The Cacti Group
 |
 | This program is free software; you can redistribute it and/or
 | modify it under the terms of the GNU General Public License
 | as published by the Free Software Foundation; either version 2
 | of the License, or (at your option) any later version.
 |
 | This program is distributed in the hope that it will be useful,
 | but WITHOUT ANY WARRANTY; without even the implied warranty of
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 | GNU General Public License for more details.
 +-------------------------------------------------------------------------
 | Cacti: The Complete RRDtool-based Graphing Solution
 +-------------------------------------------------------------------------
*/

final class CactiRenderer {
	private string $template_path;

	public function __construct(string $template_path) {
		$real_path = realpath($template_path);

		if ($real_path === false || !is_dir($real_path)) {
			throw new InvalidArgumentException('Renderer template path does not exist');
		}

		$this->template_path = rtrim($real_path, DIRECTORY_SEPARATOR);
	}

	public function render(string $template, array $context = []) : string {
		return $this->renderFile($this->resolveTemplate($template), $context);
	}

	public function renderFile(string $template_file, array $context = []) : string {
		$template_file = $this->validateTemplateFile($template_file);

		ob_start();

		try {
			$this->includeTemplate($template_file, $context);

			return (string) ob_get_clean();
		} catch (Throwable $e) {
			ob_end_clean();

			throw $e;
		}
	}

	private function resolveTemplate(string $template) : string {
		if ($template === '' || str_contains($template, "\0")) {
			throw new InvalidArgumentException('Renderer template name is invalid');
		}

		if (str_starts_with($template, '/') || preg_match('/^[A-Za-z]:[\/\\\\]/', $template) === 1) {
			throw new InvalidArgumentException('Renderer template name must be relative');
		}

		return $this->validateTemplateFile($this->template_path . DIRECTORY_SEPARATOR . $template);
	}

	private function validateTemplateFile(string $template_file) : string {
		$real_path = realpath($template_file);

		if ($real_path === false || !is_file($real_path)) {
			throw new InvalidArgumentException('Renderer template file does not exist');
		}

		$base_path = $this->template_path . DIRECTORY_SEPARATOR;

		if ($real_path !== $this->template_path && !str_starts_with($real_path, $base_path)) {
			throw new InvalidArgumentException('Renderer template file is outside the template path');
		}

		return $real_path;
	}

	private function includeTemplate(string $template_file, array $context) : void {
		(static function (string $__cacti_template_file, array $__cacti_template_context) : void {
			extract($__cacti_template_context, EXTR_SKIP);

			include $__cacti_template_file;
		})($template_file, $context);
	}
}

function cacti_renderer(?string $template_path = null) : CactiRenderer {
	static $renderers = [];

	if ($template_path === null) {
		$template_path = CACTI_PATH_INCLUDE . '/views';
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

<?php

declare(strict_types=1);

namespace Cacti\Installer\Domain;

use InvalidArgumentException;

/** @phpstan-type TemplateName non-empty-string */
final class TemplateSelection {
	/** @var list<TemplateName> */
	private readonly array $templates;

	/** @param list<string> $templates */
	public function __construct(array $templates) {
		$normalised = [];
		foreach ($templates as $template) {
			$template = trim($template);
			if ($template === '') {
				throw new InvalidArgumentException('A template name must not be empty.');
			}
			$normalised[$template] = $template;
		}

		$this->templates = array_values($normalised);
	}

	/** @return list<TemplateName> */
	public function all(): array {
		return $this->templates;
	}
}

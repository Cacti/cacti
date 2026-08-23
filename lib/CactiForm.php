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
 */

/**
 * Immutable entry point for Cacti edit forms.
 *
 * The field schema intentionally remains compatible with draw_edit_form().
 * Core and plugins get a Cacti-owned contract while existing forms can
 * migrate incrementally without changing markup or theme integration.
 */
final class CactiForm {
	/** @var array<string, mixed> */
	private array $config = [];

	/** @var array<string, array<string, mixed>> */
	private array $fields;

	private Closure $renderer;
	private Closure $hydrator;

	/**
	 * @param array<string, array<string, mixed>> $fields
	 */
	public function __construct(array $fields, ?callable $renderer = null, ?callable $hydrator = null) {
		$this->fields = self::validateFields($fields);

		$this->renderer = Closure::fromCallable($renderer ?? static function (array $definition) : void {
			draw_edit_form($definition);
		});

		$this->hydrator = Closure::fromCallable($hydrator ?? static function (array $fields, array $values) : array {
			return inject_form_variables($fields, $values);
		});
	}

	/**
	 * @param array<string, mixed> $values
	 */
	public function withValues(array $values) : self {
		$form         = clone $this;
		$form->fields = self::validateFields(($this->hydrator)($this->fields, $values));

		return $form;
	}

	public function withoutFormTag() : self {
		return $this->withConfig('no_form_tag', true);
	}

	public function postTo(string $action) : self {
		if ($action === '') {
			throw new InvalidArgumentException('A form action cannot be empty.');
		}

		return $this->withConfig('post_to', $action);
	}

	public function named(string $name) : self {
		if ($name === '') {
			throw new InvalidArgumentException('A form name cannot be empty.');
		}

		return $this->withConfig('form_name', $name);
	}

	public function multipart() : self {
		return $this->withConfig('enctype', 'multipart/form-data');
	}

	/**
	 * @return array{config: array<string, mixed>, fields: array<string, array<string, mixed>>}
	 */
	public function definition() : array {
		return [
			'config' => $this->config,
			'fields' => $this->fields,
		];
	}

	public function render() : void {
		($this->renderer)($this->definition());
	}

	private function withConfig(string $name, mixed $value) : self {
		$form                = clone $this;
		$form->config[$name] = $value;

		return $form;
	}

	/**
	 * @param array<mixed> $fields
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function validateFields(array $fields) : array {
		foreach ($fields as $name => $field) {
			if (!is_string($name) || $name === '') {
				throw new InvalidArgumentException('Cacti form fields must have non-empty string names.');
			}

			if (!is_array($field) || !isset($field['method']) || !is_string($field['method']) || $field['method'] === '') {
				throw new InvalidArgumentException(sprintf("Cacti form field '%s' must define a method.", $name));
			}
		}

		return $fields;
	}
}

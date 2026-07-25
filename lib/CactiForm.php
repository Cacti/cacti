<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;

/**
 * Small standalone Symfony Form entry point for Cacti pilots.
 *
 * Symfony's docs recommend using a FormFactoryInterface; in a full Symfony
 * app that comes from the service container. Cacti does not have that
 * container, so this wrapper gives us one DI-friendly place to construct and
 * inject a form factory without spreading Forms::createFormFactory() calls.
 */
class CactiForm {
	private const DEFAULT_FORM_TYPE = FormType::class;

	private static ?self $instance = null;

	private FormFactoryInterface $factory;

	public function __construct(?FormFactoryInterface $factory = null) {
		$this->factory = $factory ?? Forms::createFormFactory();
	}

	public static function factory(): FormFactoryInterface {
		return self::instance()->formFactory();
	}

	public static function createBuilder(string $type = self::DEFAULT_FORM_TYPE, mixed $data = null, array $options = []): FormBuilderInterface {
		return self::instance()->builder($type, $data, $options);
	}

	public static function createNamedBuilder(string $name, string $type = self::DEFAULT_FORM_TYPE, mixed $data = null, array $options = []): FormBuilderInterface {
		return self::instance()->namedBuilder($name, $type, $data, $options);
	}

	private static function instance(): self {
		return self::$instance ??= new self();
	}

	public function formFactory(): FormFactoryInterface {
		return $this->factory;
	}

	public function builder(string $type = self::DEFAULT_FORM_TYPE, mixed $data = null, array $options = []): FormBuilderInterface {
		return $this->factory->createBuilder($type, $data, $options);
	}

	public function namedBuilder(string $name, string $type = self::DEFAULT_FORM_TYPE, mixed $data = null, array $options = []): FormBuilderInterface {
		return $this->factory->createNamedBuilder($name, $type, $data, $options);
	}
}

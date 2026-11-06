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

use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * form_validate.php
 *
 * An OPT-IN, symfony/validator-backed validation layer for Cacti's legacy
 * draw_edit_form() $fields model.  It gives existing $fields-defined forms
 * declarative, constraint-based validation with per-field error messages
 * WITHOUT rewriting the renderer.
 *
 * For each field it DERIVES constraints from the metadata Cacti already
 * carries in the $fields array (max_length, the drop_array/radio allowed
 * values, a 'required'/'optional' flag), and also honours an explicit
 * 'constraints' entry (an array of Symfony\Component\Validator\Constraint
 * objects) for anything the metadata cannot express (Regex, Email, Range,
 * Ip, ...).  A form opts in to richer validation field-by-field; legacy
 * forms are never touched.
 *
 * Nothing in this file is reached unless a save path explicitly calls
 * form_validate_fields()/form_validate_stash(), and every entry point
 * no-ops gracefully when symfony/validator is not autoloaded, so an
 * un-provisioned vendor tree can never turn these helpers into a fatal
 * on a running site.
 */

/** Field 'method' values that never carry a user value to validate. */
if (!defined('FORM_VALIDATE_NON_VALUE_METHODS')) {
	define('FORM_VALIDATE_NON_VALUE_METHODS', 'spacer,hidden,hidden_zero,submit,button,return,custom,other');
}

/**
 * form_validate_available - reports whether the symfony/validator component is
 * autoloadable.  Uses class_exists() with autoload enabled but never throws, so
 * callers can branch safely.  When this returns false every other helper in
 * this file degrades to a no-op.
 *
 * @return bool true when Symfony\Component\Validator is available
 */
function form_validate_available(): bool {
	return class_exists('Symfony\Component\Validator\Validation');
}

/**
 * form_validate_get_validator - lazily constructs and memoises a single
 * ValidatorInterface instance.  Returns null when the component is unavailable.
 *
 * @return ValidatorInterface|null
 */
function form_validate_get_validator(): ?ValidatorInterface {
	static $validator = null;

	if ($validator === null) {
		if (!form_validate_available()) {
			return null;
		}

		$validator = Validation::createValidator();
	}

	return $validator;
}

/**
 * form_validate_constraints_for - derive the Symfony constraint list for a
 * single Cacti field definition.
 *
 * Derivation rules (all opt-in via metadata the field already carries):
 *   - a field that is not 'optional' and has no non-empty 'default' => NotBlank
 *   - 'max_length'                                                  => Length(max)
 *   - 'drop_array'/'radio' with an 'array' of choices               => Choice
 *   - an explicit 'constraints' array (Constraint objects)          => appended verbatim
 *
 * @param array $def A single Cacti $fields[...] definition.
 *
 * @return array A list of Symfony\Component\Validator\Constraint objects.
 */
function form_validate_constraints_for(array $def): array {
	$constraints = array();
	$label       = (string) ($def['friendly_name'] ?? __('This value'));

	// Required: Cacti marks optional fields with 'optional' => true.  A field
	// that carries a non-empty 'default' is effectively optional too, because
	// the renderer substitutes the default for an empty submission.
	$optional = !empty($def['optional']) || (isset($def['default']) && $def['default'] !== '');

	if (!$optional) {
		$constraints[] = new Assert\NotBlank(array('message' => __('%s is required.', $label)));
	}

	// max_length -> Assert\Length(max)
	if (!empty($def['max_length'])) {
		$constraints[] = new Assert\Length(array(
			'max'        => (int) $def['max_length'],
			'maxMessage' => __('%s must be {{ limit }} characters or fewer.', $label),
		));
	}

	// drop_array / radio -> Assert\Choice against the declared keys
	$method = (string) ($def['method'] ?? '');

	if (in_array($method, array('drop_array', 'radio'), true) && !empty($def['array']) && is_array($def['array'])) {
		$constraints[] = new Assert\Choice(array(
			'choices' => array_map('strval', array_keys($def['array'])),
			'message' => __('%s is not a valid selection.', $label),
		));
	}

	// Explicit, caller-supplied constraints for anything richer (Regex, Email,
	// Range, Ip, ...).  Two forms are accepted per entry:
	//   - an already-constructed Symfony\...\Constraint object (for call sites
	//     that build $fields after the Composer autoloader is registered); or
	//   - a lazy spec [$name, $options] (e.g. ['Regex', ['pattern' => ...]])
	//     that is resolved to a Constraint object here, at validation time.
	// The lazy spec lets a $fields array that is parsed before the autoloader
	// (include/global_form.php) declare constraints without a fatal.
	if (!empty($def['constraints']) && is_array($def['constraints'])) {
		foreach ($def['constraints'] as $c) {
			$constraint = form_validate_build_constraint($c);

			if ($constraint !== null) {
				$constraints[] = $constraint;
			}
		}
	}

	return $constraints;
}

/**
 * form_validate_build_constraint - resolve a single explicit constraint entry
 * to a Symfony Constraint object.
 *
 * Accepts an already-constructed Constraint object (returned as-is) or a lazy
 * spec [$name, $options] where $name is a short constraint name ('Regex',
 * 'Email', ...) or a fully qualified class name.  Returns null when the entry
 * cannot be resolved (unknown/unavailable class), so a bad spec is skipped
 * rather than fatal.
 *
 * @param mixed $spec A Constraint object or an [name, options] array.
 *
 * @return \Symfony\Component\Validator\Constraint|null
 */
function form_validate_build_constraint($spec) {
	if (is_object($spec)) {
		return ($spec instanceof \Symfony\Component\Validator\Constraint) ? $spec : null;
	}

	if (!is_array($spec) || !isset($spec[0]) || !is_string($spec[0])) {
		return null;
	}

	$name    = $spec[0];
	$options = $spec[1] ?? array();

	$class = (strpos($name, '\\') !== false) ? $name : 'Symfony\\Component\\Validator\\Constraints\\' . $name;

	if (!class_exists($class) || !is_subclass_of($class, '\Symfony\Component\Validator\Constraint')) {
		return null;
	}

	return new $class($options);
}

/**
 * form_validate_fields - validate submitted values against a Cacti $fields
 * definition and return per-field error messages.
 *
 * This performs NO session or message side effects; it is pure.  Call
 * form_validate_stash() with its return value to round-trip the errors back to
 * the edit form.  When symfony/validator is unavailable it returns an empty
 * array (treat-as-valid), so an opt-in form never fatals for want of the
 * component.
 *
 * @param array $fields The Cacti $fields definition (the same array passed to
 *                      draw_edit_form()).
 * @param array $input  Submitted values keyed by field name.
 *
 * @return array Per-field error messages: array<string, string[]>.  An empty
 *               array means the submission is valid (or validation was skipped).
 */
function form_validate_fields(array $fields, array $input): array {
	$validator = form_validate_get_validator();

	if ($validator === null) {
		return array();
	}

	$skip   = explode(',', FORM_VALIDATE_NON_VALUE_METHODS);
	$errors = array();

	foreach ($fields as $name => $def) {
		if (!is_array($def)) {
			continue;
		}

		$method = (string) ($def['method'] ?? 'textbox');

		if (in_array($method, $skip, true)) {
			continue;
		}

		$constraints = form_validate_constraints_for($def);

		if ($constraints === array()) {
			continue;
		}

		$value      = array_key_exists($name, $input) ? $input[$name] : null;
		$violations = $validator->validate($value, $constraints);

		foreach ($violations as $violation) {
			$errors[$name][] = (string) $violation->getMessage();
		}
	}

	return $errors;
}

/**
 * form_validate_stash - round-trip validation errors back to the edit form
 * using Cacti's existing session conventions, plus a per-field message store
 * that draw_edit_control() renders inline.
 *
 * Mirrors form_input_validate(): it populates SESS_FIELD_VALUES so the form
 * repopulates with what the user typed, SESS_ERROR_FIELDS so the existing
 * renderers mark the offending controls red (and is_error_message() reports an
 * error, which the save path already checks before sql_save()), and the new
 * SESS_FIELD_ERRORS so the exact validator messages can be shown beneath each
 * field.  Finally it raises the standard "field input errors" message.
 *
 * @param array $errors Per-field messages from form_validate_fields().
 * @param array $input  The submitted values to retain (keyed by field name).
 *
 * @return bool true when at least one error was stashed, false otherwise.
 */
function form_validate_stash(array $errors, array $input): bool {
	if ($errors === array()) {
		return false;
	}

	// retain the user's submitted values (same store form_input_validate uses)
	foreach ($input as $name => $value) {
		$_SESSION[SESS_FIELD_VALUES][$name] = $value;
	}

	foreach ($errors as $name => $messages) {
		// native red-field highlight + is_error_message() signal
		$_SESSION[SESS_ERROR_FIELDS][$name] = 3;

		// per-field inline messages rendered by draw_edit_control()
		$_SESSION[SESS_FIELD_ERRORS][$name] = array_values($messages);
	}

	// standard "Save Failed due to field input errors (Check red fields)." message
	raise_message(3);

	return true;
}

/**
 * form_validate_field_block - return the inline error block for a single field
 * and clear it from the session so it is shown exactly once.
 *
 * Returns an empty string unless the current request carries stashed validator
 * messages for $field_name, which only happens for a form that opted in via
 * form_validate_stash().  This is what makes the draw_edit_control() hook inert
 * for every legacy form.
 *
 * @param string $field_name The field whose errors to render.
 *
 * @return string HTML for the inline error block, or '' when there is none.
 */
function form_validate_field_block(string $field_name): string {
	if (!isset($_SESSION[SESS_FIELD_ERRORS]) || empty($_SESSION[SESS_FIELD_ERRORS][$field_name])) {
		return '';
	}

	$messages = $_SESSION[SESS_FIELD_ERRORS][$field_name];

	unset($_SESSION[SESS_FIELD_ERRORS][$field_name]);

	if (!is_array($messages) || $messages === array()) {
		return '';
	}

	$items = '';

	foreach ($messages as $message) {
		$items .= '<li>' . html_escape((string) $message) . '</li>';
	}

	return "<div class='formError txtErrorTextBox' role='alert'><ul>" . $items . '</ul></div>';
}

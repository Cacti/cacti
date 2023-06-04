<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2023 The Cacti Group                                 |
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

/* inject_form_variables - replaces all variables contained in $form_array with
	 their actual values
   @arg $form_array - an array that contains all of the information needed to draw
	 the html form. see the arrays contained in include/global_settings.php
	 for the exact syntax of this array
   @arg $arg1 - an array that represents the |arg1:| variable (see
	 include/global_form.php for more details)
   @arg $arg2 - an array that represents the |arg2:| variable (see
	 include/global_form.php for more details)
   @arg $arg3 - an array that represents the |arg3:| variable (see
	 include/global_form.php for more details)
   @arg $arg4 - an array that represents the |arg4:| variable (see
	 include/global_form.php for more details)
   @returns - $form_array with all available variables substituted with their
	 proper values */
function inject_form_variables(&$form_array, $arg1 = array(), $arg2 = array(), $arg3 = array(), $arg4 = array()) {
	$check_fields = array('id', 'value', 'array', 'friendly_name', 'description', 'sql', 'sql_print', 'form_id', 'items', 'tree_id');

	/* loop through each available field */
	if (cacti_sizeof($form_array)) {
		foreach ($form_array as $field_name => $field_array) {
			/* loop through each sub-field that we are going to check for variables */
			foreach ($check_fields as $field_to_check) {
				if (isset($field_array[$field_to_check]) && is_array($form_array[$field_name][$field_to_check])) {
					/* if the field/sub-field combination is an array, resolve it recursively */
					$form_array[$field_name][$field_to_check] = inject_form_variables($form_array[$field_name][$field_to_check], $arg1);
				} elseif (isset($field_array[$field_to_check]) && !is_array($field_array[$field_to_check])) {
					$count = 0;

					/* loop through the $field_to_check and replace up to three times
					 * for each arg1:arg2:arg3 variables.
					 */
					while (true) {
						$matches = array();

						//if (preg_match('/\|(arg[123]):([a-zA-Z0-9_]*)\|/', $form_array[$field_name][$field_to_check], $matches)) {
						if (preg_match('/\|(arg[123]):([a-zA-Z0-9_]*)\|/', $field_array[$field_to_check], $matches)) {
							$string   = $field_array[$field_to_check];

							$matches0 = $matches[0];
							$matches1 = $matches[1];
							$matches2 = $matches[2];

							/* an empty field name in the variable means don't treat this as an array */
							if ($matches2 == '') {
								if (is_array($$matches1)) {
									/* the existing value is already an array, leave it alone */
									$form_array[$field_name][$field_to_check] = $$matches1;
								} else {
									/* the existing value is probably a single variable */
									$form_array[$field_name][$field_to_check] = str_replace($matches0, $$matches1, $field_array[$field_to_check]);
								}
							} else {
								/* copy the value down from the array/key specified in the variable
								 * replace up to three times for arg1:arg2:arg3 variables
								 */
								if (isset($$matches1)) {
									if (is_array($$matches1)) {
										$array = $$matches1;

										if (is_array($array) && isset($array[$matches2]) && $array[$matches2] != '') {
											$string = str_replace($matches0, $array[$matches2], $string);
										} else {
											$string = str_replace($matches0, '', $string);
										}
									}
								}

								// Double check to see if the replacement went as planned
								$matches = array();
								preg_match('/\|(arg[123]):([a-zA-Z0-9_]*)\|/', $string, $matches);

								if (!cacti_sizeof($matches)) {
									$form_array[$field_name][$field_to_check] = $string;

									// No more arg[123]
									break;
								} else {
									if ($field_to_check == 'sql') {
										// Update the form value with the modified string
										// Then update the field array string value to recheck

										$form_array[$field_name][$field_to_check] = $string;
										$field_array[$field_to_check]             = $string;
									} elseif (isset($form_array[$field_name]['default'])) {
										// We did not find a match for this field value, use the default

										$form_array[$field_name][$field_to_check] = $form_array[$field_name]['default'];
									} else {
										// We found no value, found no default, set to empty string

										$form_array[$field_name][$field_to_check] = '';
									}
								}
							}

							/* if there are no more arg's, break.  Since some arg's
							 * might not ever be replaced, continue counting till 3 as
							 * in the special case of 'sql' for example.
							 */
							$additional = preg_match('/\|(arg[123]):([a-zA-Z0-9_]*)\|/', $string);

							if (empty($additional)) {
								break;
							}

							if ($count >= 3) {
								break;
							} else {
								$count++;
							}
						} else {
							// No arg[123] found
							break;
						}
					}
				}
			}
		}
	}

	return $form_array;
}

/* form_alternate_row_color - starts an HTML row with an alternating color scheme
   @arg $row_color1 - the first color to use
   @arg $row_color2 - the second color to use
   @arg $row_value - the value of the row which will be used to evaluate which color
	 to display for this particular row. must be an integer
   @arg $row_id - used to allow js and ajax actions on this object
   @returns - the background color used for this particular row */
function form_alternate_row_color($row_color1, $row_color2, $row_value, $row_id = '') {
	if ($row_value % 2 == 1) {
		$class         ='odd';
		$current_color = $row_color1;
	} else {
		if ($row_color2 == '' || $row_color2 == 'E5E5E5') {
			$class = 'even';
		} else {
			$class = 'even-alternate';
		}
		$current_color = $row_color1;
	}

	if ($row_id != '') {
		print "<tr class='$class selectable tableRow' id='$row_id'>\n";
	} else {
		print "<tr class='$class tableRow'>\n";
	}

	return $current_color;
}

/* form_alternate_row - starts an HTML row with an alternating color scheme
   @arg $light - Alternate odd style
   @arg $row_id - The id of the row
   @arg $reset - Reset to top of table */
function form_alternate_row($row_id = '', $light = false, $disabled = false) {
	static $i = 1;

	if ($i % 2 == 1) {
		$class = 'odd';
	} elseif ($light) {
		$class = 'even-alternate';
	} else {
		$class = 'even';
	}

	$i++;

	if ($row_id != '' && !$disabled && substr($row_id, 0, 4) != 'row_') {
		print "<tr class='$class selectable tableRow' id='$row_id'>\n";
	} elseif (substr($row_id, 0, 4) == 'row_') {
		print "<tr class='$class tableRow' id='$row_id'>\n";
	} elseif ($row_id != '') {
		print "<tr class='$class tableRow' id='$row_id'>\n";
	} else {
		print "<tr class='$class tableRow'>\n";
	}
}

/* form_selectable_ecell - a wrapper to form_selectable_cell that escapes the contents
   @arg $contents - the readable portion of the
   @arg $id - the id of the object that will be highlighted
   @arg $width - the width of the table element
   @arg $style_or_class - the style or class to apply to the table element
   @arg $title - optional title for the column */
function form_selectable_ecell($contents, $id, $width = '', $style_or_class = '', $title = '') {
	form_selectable_cell(html_escape($contents), $id, $width, $style_or_class, $title);
}

/* form_selectable_cell - format's a table row such that it can be highlighted using cacti's js actions
   @arg $contents - the readable portion of the
   @arg $id - the id of the object that will be highlighted
   @arg $width - the width of the table element
   @arg $style_or_class - the style or class to apply to the table element
   @arg $title - optional title for the column */
function form_selectable_cell($contents, $id, $width = '', $style_or_class = '', $title = '') {
	$output = '';

	if ($style_or_class != '') {
		if (strpos($style_or_class, ':') === false) {
			$output = "class='nowrap " . $style_or_class . "'";

			if ($width != '') {
				$output .= " style='width:$width;'";
			}
		} else {
			$output = "class='nowrap' style='" . $style_or_class;

			if ($width != '') {
				$output .= ";width:$width;";
			}
			$output .= "'";
		}
	} else {
		$output = 'class="nowrap"';

		if ($width != '') {
			$output .= " style='width:$width;'";
		}
	}

	if ($title != '') {
		$wrapper = "<span class='cactiTooltipHint' style='padding:0px;margin:0px;' title='" . str_replace(array('"', "'"), '', $title) . "'>" . $contents . '</span>';
	} else {
		$wrapper = $contents;
	}

	print "\t<td " . $output . '>' . $wrapper . "</td>\n";
}

/* form_checkbox_cell - format's a tables checkbox form element so that the cacti js actions work on it
   @arg $title - the text that will be displayed if your hover over the checkbox */
function form_checkbox_cell($title, $id, $disabled = false, $checked = false) {
	print "\t<td class='checkbox' style='width:1%;'>\n";
	print "\t\t<input type='checkbox' title='" . html_escape($title) . "' class='checkbox" . ($disabled ? ' disabled':'') . "' " . ($disabled ? " disabled='disabled'":'') . ($checked ? " checked='checked'":'') . " id='chk_" . $id . "' name='chk_" . $id . "'><label class='formCheckboxLabel' for='chk_" . $id . "'></label>\n";
	print "\t</td>\n";
}

/* form_end_row - ends a table row that is started with form_alternate_row */
function form_end_row() {
	print "</tr>\n";
}

/* html_boolean - returns the boolean equivalent of an HTML checkbox value
   @arg $html_boolean - the value of the HTML checkbox
   @returns - true or false based on the value of the HTML checkbox */
function html_boolean($html_boolean) {
	return ($html_boolean == 'on');
}

/* html_boolean_friendly - returns the natural language equivalent of an HTML
	 checkbox value
   @arg $html_boolean - the value of the HTML checkbox
   @returns - 'Selected' or 'Not Selected' based on the value of the HTML
	 checkbox */
function html_boolean_friendly($html_boolean) {
	if ($html_boolean == 'on') {
		return __('Selected');
	} else {
		return __('Not Selected');
	}
}

/* get_checkbox_style - finds the proper CSS padding to apply based on the
	 current client browser in use
   @returns - a CSS style string which should be used with an HTML checkbox
	 control */
function get_checkbox_style() {
	return '';
}

/**
 * Sets the required 'action' request variable
 *
 * @param  string $default The default action is not set
 *
 * @return void
 */
function set_default_action(string $default = ''):void {
	if (!isset_request_var('action')) {
		set_request_var('action', $default);
	} elseif (is_array(get_nfilter_request_var('action'))) {
		if (read_config_option('log_validation') == 'on') {
			cacti_log('WARNING: Request variable \'action\' was passed as array in ' . $_SERVER['SCRIPT_NAME'] . '.', false, 'WEBUI');
		}

		set_request_var('action', $_REQUEST['action'][0]);
	} else {
		set_request_var('action', $_REQUEST['action']);
	}
}

/**
 * Unsets the request variable.
 *
 * @param  string  $variable
 *
 * @return void
 */
function unset_request_var(string $variable): void {
	global $_CACTI_REQUEST;

	if (isset($_CACTI_REQUEST[$variable])) {
		unset($_CACTI_REQUEST[$variable]);
	}

	if (isset($_REQUEST[$variable])) {
		unset($_REQUEST[$variable]);
	}
}

/**
 *  checks to see if the $_REQUEST variable is set.
 *
 * @param  string  $variable
 *
 * @return boolean
 */
function isset_request_var(string $variable): bool {
	return isset($_REQUEST[$variable]);
}

/**
 *  checks to see if the $_REQUEST variableis empty.
 *
 * @param  string  $variable
 *
 * @return boolean
 */
function isempty_request_var(string $variable): bool {
	if (isset_request_var($variable)) {
		$value = $_REQUEST[$variable];

		if (!empty($value)) {
			return false;
		}
	}

	return true;
}

/**
 * Sets a given $_REQUEST variable and Cacti global.
 *
 * @param  string $variable The variable to set
 * @param  mixed  $value    The value to set the variable to
 *
 * @return void
 */
function set_request_var(string $variable, mixed $value):void {
	global $_CACTI_REQUEST;

	$_CACTI_REQUEST[$variable] = $value;
	$_REQUEST[$variable]       = $value;
	$_POST[$variable]          = $value;
	$_GET[$variable]           = $value;
}

/**
 * returns the current value of a PHP $_REQUEST variable, optionally
 * returning a default value if the request variable does not exist.  When Cacti
 * has 'log_validation' set on, it will log all instances where a request variable
 * has not first been filtered.
 *
 * @param  string $name     the name of the request variable. this should be a
 *                          valid key in the $_REQUEST array
 * @param  mixed  $default  the value to return if the specified name does not
 *                          exist in the $_REQUEST array
 *
 * @return mixed
 */
function get_request_var(string $name, mixed $default = ''): mixed {
	global $_CACTI_REQUEST;

	$log_validation = read_config_option('log_validation');

	if (isset($_CACTI_REQUEST[$name])) {
		return $_CACTI_REQUEST[$name];
	}

	if (isset_request_var($name)) {
		if ($log_validation == 'on') {
			html_log_input_error($name);
		}

		set_request_var($name, $_REQUEST[$name]);

		return $_REQUEST[$name];
	} else {
		return $default;
	}
}

/**
 * stub call for get_request_var
 *
 * @deprecated v1.0
 *
 * @param  string $name     the name of the request variable. this should be a
 *                          valid key in the $_REQUEST array
 * @param  mixed  $default  the value to return if the specified name does not
 *                          exist in the $_REQUEST array
 *
 * @return mixed
 */
function get_request_var_request(string $name, mixed $default = ''): mixed {
	return get_request_var($name, $default);
}

/**
 * returns the current value of a PHP $_REQUEST variable and also
 * sanitizing the value using the filter. It will also optionally
 * return a default value if the request variable does not exist
 *
 * @param  string $name     the name of the request variable. this should be a
 *                          valid key in the $_REQUEST array
 * @param  int    $filter   the filter mode to use
 * @param  array  $options  used to pass to filter_var function or to hold the
 *                          default value to be returned
 *
 * @return mixed
 */
function get_filter_request_var(string $name, int $filter = FILTER_VALIDATE_INT, array $options = array()):mixed {
	if (isset_request_var($name)) {
		if (isempty_request_var($name)) {
			set_request_var($name, get_nfilter_request_var($name));

			return get_request_var($name);
		}

		if (get_nfilter_request_var($name) == 'undefined') {
			if (isset($options['default'])) {
				set_request_var($name, $options['default']);

				return $options['default'];
			} else {
				set_request_var($name, '');

				return '';
			}
		} else {
			if (get_nfilter_request_var($name) == '0') {
				$value = '0';
			} elseif (get_nfilter_request_var($name) == 'undefined') {
				if (isset($options['default'])) {
					$value = $options['default'];
				} else {
					$value = '';
				}
			} elseif (isempty_request_var($name)) {
				$value = '';
			} elseif ($filter == FILTER_VALIDATE_IS_REGEX) {
				if (is_base64_encoded($_REQUEST[$name])) {
					$_REQUEST[$name] = base64_decode($_REQUEST[$name], true);
				}

				$valid = validate_is_regex($_REQUEST[$name]);

				if ($valid === true) {
					$value = $_REQUEST[$name];
				} else {
					$value        = false;
					$custom_error = $valid;
				}
			} elseif ($filter == FILTER_VALIDATE_IS_NUMERIC_ARRAY) {
				$valid = true;

				if (is_array($_REQUEST[$name])) {
					foreach ($_REQUEST[$name] as $number) {
						if (!is_numeric($number)) {
							$valid = false;

							break;
						}
					}
				} else {
					$valid = false;
				}

				if ($valid == true) {
					$value = $_REQUEST[$name];
				} else {
					$value = false;
				}
			} elseif ($filter == FILTER_VALIDATE_IS_NUMERIC_LIST) {
				$valid  = true;
				$values = preg_split('/,/', $_REQUEST[$name], -1, PREG_SPLIT_NO_EMPTY);

				foreach ($values as $number) {
					if (!is_numeric($number)) {
						$valid = false;

						break;
					}
				}

				if ($valid == true) {
					$value = $_REQUEST[$name];
				} else {
					$value = false;
				}
			} elseif (!cacti_sizeof($options)) {
				$value = filter_var($_REQUEST[$name], $filter);
			} else {
				$value = filter_var($_REQUEST[$name], $filter, $options);
			}
		}

		if ($value === false) {
			if ($filter == FILTER_VALIDATE_IS_REGEX) {
				raise_message('custom', __('The regular expression "%s" is not valid. Error is %s', html_escape(get_nfilter_request_var($name)), html_escape($custom_error)), MESSAGE_LEVEL_ERROR);
				set_request_var($name, '');
			} else {
				die_html_input_error($name, get_nfilter_request_var($name));
			}
		} else {
			set_request_var($name, $value);

			return $value;
		}
	} else {
		if (isset($options['default'])) {
			set_request_var($name, $options['default']);

			return $options['default'];
		} else {
			return null;
		}
	}
}

/**
 * returns the current value of a PHP $_REQUEST variable, optionally
 * returning a default value if the request variable does not exist,
 * but without using any of the filtering checks.
 *
 * This should only be used when the request variable has already been
 * vetted via other filer request var functoins
 *
 * @param  string $name     the name of the request variable. this should be a
 *                          valid key in the $_REQUEST array
 * @param  mixed  $default  the value to return if the specified name does not
 *                          exist in the $_REQUEST array
 *
 * @return mixed
 */
function get_nfilter_request_var(string $name, mixed $default = ''):mixed {
	global $_CACTI_REQUEST;

	if (isset($_CACTI_REQUEST[$name])) {
		return $_CACTI_REQUEST[$name];
	}

	if (isset($_REQUEST[$name])) {
		return $_REQUEST[$name];
	} else {
		return $default;
	}
}

/**
 * stub call for get_nfilteR_request_var
 *
 * @deprecated v1.0
 *
 * @param  string $name     the name of the request variable. this should be a
 *                          valid key in the $_REQUEST array
 * @param  mixed  $default  the value to return if the specified name does not
 *                          exist in the $_REQUEST array
 *
 * @return mixed
 */
function get_request_var_post($name, $default = '') {
	return get_nfilter_request_var($name, $default);
}

/* validate_store_request_vars - validate, sanitize, and store
   request variables into the custom $_CACTI_REQUEST and desired
   session variables for Cacti filtering.


   @arg $filters - an array keyed with the filter methods.
   @arg $session_prefix - the prefix for the session variable


   @returns - the $_REQUEST variable validated and sanitized. */

/**
 * validate, sanitize, and store request variables into the
 * custom $_CACTI_REQUEST and desired session variables for
 * Cacti filtering.
 *
 * @param  array  $filters      an array keyed with the filter methods.
 *
 *    Valid filter include those from PHP filter_var() function syntax.
 *    The format of the array is:
 *      array(
 *        'varA' => array(
 *           'filter' => value,
 *           'pageset' => true,      (optional)
 *           'session' => sess_name, (optional)
 *           'options' => mixed,
 *           'default' => value),
 *        'varB' => array(
 *           'filter' => value,
 *           'pageset' => true,      (optional)
 *           'session' => sess_name, (optional)
 *           'options' => mixed,
 *           'default' => value),
 *        ...
 *      );
 *
 *    The 'pageset' attribute is optional, and when set, any changes
 *    between what the page returns and what is set in the session
 *    result in the page number being returned to 1.
 *
 *    The 'session' attribute is also optional, and when set, all
 *    changes will be stored to the session variable defined and
 *    not to session_prefix . '_' . $variable as the default.  This
 *    allows for the concept of global session variables such as
 *    'sess_default_rows'.
 *
 *    Validation 'filter' follow PHP conventions including:
 *
 *      FILTER_VALIDATE_BOOLEAN          - Validate that the variable is boolean
 *      FILTER_VALIDATE_EMAIL            - Validate that the variable is an email
 *      FILTER_VALIDATE_FLOAT            - Validate that the variable is a float
 *      FILTER_VALIDATE_INT              - Validate that the variable is an integer
 *      FILTER_VALIDATE_IP               - Validate that the variable is an IP address
 *      FILTER_VALIDATE_MAC              - Validate that the variable is a MAC Address
 *      FILTER_VALIDATE_REGEXP           - Validate against a REGEX
 *      FILTER_VALIDATE_URL              - Validate that the variable is a valid URL
 *      FILTER_VALIDATE_IS_REGEX         - Validate if a filter variable is a valid regex
 *      FILTER_VALIDATE_IS_NUMERIC_ARRAY - Validate if a filter variable is a numeric array
 *      FILTER_VALIDATE_IS_NUMERIC_LIST  - Validate if a filter variable is a comma delimited list of numbers
 *
 *    Sanitization 'filters' follow PHP conventions including:
 *
 *      FILTER_SANITIZE_EMAIL              - Sanitize the email address
 *      FILTER_SANITIZE_ENCODED            - URL-encode string
 *      FILTER_SANITIZE_MAGIC_QUOTES       - Apply addslashes()
 *      FILTER_SANITIZE_NUMBER_FLOAT       - Remove all non float values
 *      FILTER_SANITIZE_NUMBER_INT         - Remove everything non int
 *      FILTER_SANITIZE_SPECIAL_CHARS      - Escape special chars
 *      FILTER_SANITIZE_FULL_SPECIAL_CHARS - Equivalent to htmlspecialchars adding ENT_QUOTES
 *      FILTER_SANITIZE_STRING             - Strip tags, optionally strip or encode special chars
 *      FILTER_SANITIZE_URL                - Remove all characters except letters, digits, etc.
 *      FILTER_UNSAFE_RAW                  - Nothing and optional strip or encode
 *
 * @param  string $sess_prefix  the prefix for the session variable
 *
 * @return void
 */
function validate_store_request_vars(array $filters, string $sess_prefix = ''):void {
	$changed      = 0;
	$custom_error = '';

	if (cacti_sizeof($filters)) {
		foreach ($filters as $variable => $options) {
			// Establish the session variable first
			if ($sess_prefix != '') {
				if (isset($options['session'])) {
					$session_variable = $options['session'];
				} elseif ($variable != 'rows') {
					$session_variable = $sess_prefix . '_' . $variable;
				} else {
					$session_variable = 'sess_default_rows';
				}

				// Check for special cases 'clear' and 'reset'
				if (isset_request_var('clear')) {
					kill_session_var($session_variable);
					unset_request_var($variable);
				} elseif (isset_request_var('reset')) {
					kill_session_var($session_variable);
				} elseif (isset($options['pageset']) && $options['pageset'] == true) {
					$changed += check_changed($variable, $session_variable);
				}
			}

			if (!isset_request_var($variable)) {
				if ($sess_prefix != '' && isset($_SESSION[$session_variable])) {
					set_request_var($variable, $_SESSION[$session_variable]);
				} elseif (isset($options['default'])) {
					set_request_var($variable, $options['default']);
				} else {
					cacti_log("Filter Variable: $variable, Must have a default and none is set", false);
					set_request_var($variable, '');
				}
			} else {
				if (get_nfilter_request_var($variable) == '0') {
					$value = '0';
				} elseif (get_nfilter_request_var($variable) == 'undefined') {
					if (isset($options['default'])) {
						$value = $options['default'];
					} else {
						$value = '';
					}
				} elseif (isempty_request_var($variable)) {
					$value = '';
				} elseif ($options['filter'] == FILTER_VALIDATE_IS_REGEX) {
					if (is_base64_encoded($_REQUEST[$variable])) {
						$_REQUEST[$variable] = base64_decode($_REQUEST[$variable], true);
					}

					$valid = validate_is_regex($_REQUEST[$variable]);

					if ($valid === true) {
						$value = $_REQUEST[$variable];
					} else {
						$value        = false;
						$custom_error = $valid;
					}
				} elseif ($options['filter'] == FILTER_VALIDATE_IS_NUMERIC_ARRAY) {
					$valid = true;

					if (is_array($_REQUEST[$variable])) {
						foreach ($_REQUEST[$variable] as $number) {
							if (!is_numeric($number)) {
								$valid = false;

								break;
							}
						}
					} else {
						$valid = false;
					}

					if ($valid == true) {
						$value = $_REQUEST[$variable];
					} else {
						$value = false;
					}
				} elseif ($options['filter'] == FILTER_VALIDATE_IS_NUMERIC_LIST) {
					$valid  = true;
					$values = preg_split('/,/', $_REQUEST[$variable], -1, PREG_SPLIT_NO_EMPTY);

					foreach ($values as $number) {
						if (!is_numeric($number)) {
							$valid = false;

							break;
						}
					}

					if ($valid == true) {
						$value = $_REQUEST[$variable];
					} else {
						$value = false;
					}
				} elseif (!isset($options['options'])) {
					$value = filter_var($_REQUEST[$variable], $options['filter']);
				} else {
					$value = filter_var($_REQUEST[$variable], $options['filter'], $options['options']);
				}

				if ($value === false) {
					if ($options['filter'] == FILTER_VALIDATE_IS_REGEX) {
						raise_message('custom', __('The regular expression "%s" is not valid. Error is %s', html_escape(get_nfilter_request_var($variable)), html_escape($custom_error)), MESSAGE_LEVEL_ERROR);
						set_request_var($variable, '');
					} else {
						die_html_input_error($variable, get_nfilter_request_var($variable), html_escape($custom_error));
					}
				} else {
					set_request_var($variable, $value);
				}
			}

			if ($sess_prefix != '') {
				if (isset_request_var($variable)) {
					$_SESSION[$session_variable] = get_request_var($variable);
				} elseif (isset($_SESSION[$session_variable])) {
					set_request_var($variable, $_SESSION[$session_variable]);
				}
			}
		}

		update_order_string();
	}

	if ($changed) {
		set_request_var('page', 1);
		set_request_var('changed', 1);
		$_SESSION[$sess_prefix . '_page'] = 1;
	} elseif (!isset_request_var('page') && isset($_SESSION[$sess_prefix . '_page'])) {
		set_request_var('page', $_SESSION[$sess_prefix . '_page']);
	}
}

/* update_order_string - creates a sort string for standard Cacti tables
   @returns - null */
function update_order_string($inplace = false) {
	$page = get_order_string_page();

	$order = '';

	if (strpos(get_request_var('sort_column'), '(') === false && strpos(get_request_var('sort_column'), '`') === false) {
		$del = '`';
	} else {
		$del = '';
	}

	if ($inplace) {
		$_SESSION['sort_string'][$page] = 'ORDER BY ';

		foreach ($_SESSION['sort_data'][$page] as $column => $direction) {
			if ($column == 'hostname' || $column == 'ip' || $column == 'ip_address') {
				$order .= ($order != '' ? ', ':'') . 'INET_ATON(' . $column . ') ' . $direction;
			} else {
				$order .= ($order != '' ? ', ':'') . $column . ' ' . $direction;
			}
		}
		$_SESSION['sort_string'][$page] .= $order;
	} else {
		if (isset_request_var('clear')) {
			unset($_SESSION['sort_data'][$page]);
			unset($_SESSION['sort_string'][$page]);
		} elseif (isset_request_var('add') && get_nfilter_request_var('add') == 'reset') {
			unset($_SESSION['sort_data'][$page]);
			unset($_SESSION['sort_string'][$page]);

			$_SESSION['sort_data'][$page][get_request_var('sort_column')] = get_request_var('sort_direction');

			$column    = get_request_var('sort_column');
			$direction = get_request_var('sort_direction');

			if ($column == 'hostname' || $column == 'ip' || $column == 'ip_address') {
				$_SESSION['sort_string'][$page] ='ORDER BY INET_ATON(' . $column . ') ' . $direction;
			} else {
				$_SESSION['sort_string'][$page] = 'ORDER BY ' . $del . implode($del . '.'. $del, explode('.', get_request_var('sort_column'))) . $del . ' ' . get_request_var('sort_direction');
			}
		} elseif (isset_request_var('sort_column')) {
			if (isset_request_var('reset')) {
				unset($_SESSION['sort_data'][$page]);
				unset($_SESSION['sort_string'][$page]);
			}

			$_SESSION['sort_data'][$page][get_request_var('sort_column')] = get_nfilter_request_var('sort_direction');
			$_SESSION['sort_string'][$page]                               = 'ORDER BY ';

			foreach ($_SESSION['sort_data'][$page] as $column => $direction) {
				if (strpos($column, '(') === false && strpos($column, '`') === false) {
					$del = '`';
				} else {
					$del = '';

					break;
				}
			}

			foreach ($_SESSION['sort_data'][$page] as $column => $direction) {
				if ($column == 'hostname' || $column == 'ip' || $column == 'ip_address') {
					$order .= ($order != '' ? ', ':'') . 'INET_ATON(' . $column . ') ' . $direction;
				} else {
					$order .= ($order != '' ? ', ' . $del:$del) . implode($del . '.' . $del, explode('.', $column)) . $del . ' ' . $direction;
				}
			}
			$_SESSION['sort_string'][$page] .= $order;
		} else {
			unset($_SESSION['sort_data'][$page]);
			unset($_SESSION['sort_string'][$page]);
		}
	}
}

/* get_order_string - returns a valid order string for a table
   @returns - the order string */
function get_order_string() {
	$page = get_order_string_page();

	if (strpos(get_request_var('sort_column'), '(') === false && strpos(get_request_var('sort_column'), '`') === false) {
		$del = '`';
	} else {
		$del = '';
	}

	if (isset($_SESSION['sort_string'][$page])) {
		return $_SESSION['sort_string'][$page];
	} else {
		return 'ORDER BY ' . $del . implode($del . '.' . $del, explode('.', get_request_var('sort_column'))) . $del . ' ' . get_request_var('sort_direction');
	}
}

function remove_column_from_order_string($column) {
	$page = get_order_string_page();

	if (isset($_SESSION['sort_data'][$page][$column])) {
		unset($_SESSION['sort_data'][$page][$column]);
		update_order_string(true);
	}
}

function get_order_string_page() {
	$page = str_replace('.php', '', get_current_page());

	if (isset_request_var('action')) {
		$page .= '_' . get_nfilter_request_var('action');
	}

	if (isset_request_var('tab')) {
		$page .= '_' . get_nfilter_request_var('tab');
	}

	return $page;
}

function validate_is_regex($regex) {
	if ($regex == '') {
		return true;
	}

	/**
	 * Prevent exploits from encoded Regular expressions that can cause
	 * injections in MariaDB and MySQL.  We do this by limiting the
	 * length of the regular expression to 50 bytes or less.
	 */
	if (strlen($regex) > 50) {
		return __('Cacti regular expressions are limited to 50 characters only for security reasons.');
	}

	if (strpos($regex, ';') !== false) {
		return __('Cacti regular expressions can not includes the semi-color character.');
	}

	restore_error_handler();

	$track_errors = ini_get('track_errors');
	ini_set('track_errors', 1);

	if (@preg_match("'" . $regex . "'", '') !== false) {
		ini_set('track_errors', $track_errors);

		return true;
	}

	$last_error = error_get_last();

	$php_error = trim(str_replace('preg_match():', '', $last_error['message']));

	ini_set('track_errors', $track_errors);

	$errors = array(
		PREG_INTERNAL_ERROR         => __('There was an internal error!'),
		PREG_BACKTRACK_LIMIT_ERROR  => __('Backtrack limit was exhausted!'),
		PREG_RECURSION_LIMIT_ERROR  => __('Recursion limit was exhausted!'),
		PREG_BAD_UTF8_ERROR         => __('Bad UTF-8 error!'),
		PREG_BAD_UTF8_OFFSET_ERROR  => __('Bad UTF-8 offset error!'),
	);

	$error = preg_last_error();

	if (!defined('IN_CACTI_INSTALL')) {
		set_error_handler('CactiErrorHandler');
	}

	if (empty($error)) {
		return $php_error;
	} else {
		return $errors[$error];
	}
}

/* load_current_session_value - finds the correct value of a variable that is being
	 cached as a session variable on an HTML form
   @arg $request_var_name - the array index name for the request variable
   @arg $session_var_name - the array index name for the session variable
   @arg $default_value - the default value to use if values cannot be obtained using
	 the session or request array */
function load_current_session_value($request_var_name, $session_var_name, $default_value) {
	if (isset_request_var($request_var_name)) {
		$_SESSION[$session_var_name] = get_request_var($request_var_name);
	} elseif (isset($_SESSION[$session_var_name])) {
		set_request_var($request_var_name, $_SESSION[$session_var_name]);
	} else {
		set_request_var($request_var_name, $default_value);
	}
}

/* get_colored_device_status - given a device's status, return the colored text in HTML
	 format suitable for display
   @arg $disabled (bool) - true if the device is disabled, false is it is not
   @arg $status - the status type of the device as defined in global_constants.php
   @returns - a string containing html that represents the device's current status */
function get_colored_device_status($disabled, $status) {
	if ($disabled) {
		return "<span class='deviceDisabled'>" . __('Disabled') . '</span>';
	} else {
		switch ($status) {
			case HOST_DOWN:
				return "<span class='deviceDown'>" . __('Down') . '</span>';

				break;
			case HOST_RECOVERING:
				return "<span class='deviceRecovering'>" . __('Recovering') . '</span>';

				break;
			case HOST_UP:
				return "<span class='deviceUp'>" . __('Up') . '</span>';

				break;
			case HOST_ERROR:
				return "<span class='deviceError'>" . __('Error') . '</span>';

				break;

			default:
				return "<span class='deviceUnknown'>" . __('Unknown') . '</span>';

				break;
		}
	}
}

/**
 * Given a device's status, return the colored text in HTML format suitable for display
 *
 * @param bool   $disabled    When true, the device is disabled, false is it is not
 * @param string $site_name   The name of the site to display
 *
 * @return string  Returns a string containing html that represents the site's current
 *                 status and name
 */
function get_colored_site_status(bool $disabled, ?string $site_name) {
	$class = '';
	if ($disabled) {
		$class = 'deviceDown';
	}

	return "<span class='$class'>" . __esc($site_name) . '</span>';
}

/* get_current_graph_start - determine the correct graph start time selected using
	 the timespan selector
   @returns - the number of seconds relative to now where the graph should begin */
function get_current_graph_start() {
	if (isset($_SESSION['sess_current_timespan_begin_now']) && is_numeric($_SESSION['sess_current_timespan_begin_now'])) {
		return $_SESSION['sess_current_timespan_begin_now'];
	} else {
		return '-' . DEFAULT_TIMESPAN;
	}
}

/* get_current_graph_end - determine the correct graph end time selected using
	 the timespan selector
   @returns - the number of seconds relative to now where the graph should end */
function get_current_graph_end() {
	if (isset($_SESSION['sess_current_timespan_end_now']) && is_numeric($_SESSION['sess_current_timespan_end_now'])) {
		return $_SESSION['sess_current_timespan_end_now'];
	} else {
		return '0';
	}
}

/* display_tooltip - display the text passed to the function as a tooltip
   @arg $text - the text to display in the tooltip
   @returns - null */
function display_tooltip($text) {
	if ($text != '') {
		return '<div class="cactiTooltipHint fa fa-question-circle"><span style="display:none;">' . $text . "</span></div>\n";
	} else {
		return '';
	}
}

/* get_page_list - generates the html necessary to present the user with a list of pages limited
	 in length and number of rows per page
   @arg $current_page - the current page number
   @arg $pages_per_screen - the maximum number of pages allowed on a single screen. odd numbered
	 values for this argument are prefered for equality reasons
   @arg $current_page - the current page number
   @arg $total_rows - the total number of available rows
   @arg $url - the url string to prepend to each page click
   @returns - a string containing html that represents the a page list */
function get_page_list($current_page, $pages_per_screen, $rows_per_page, $total_rows, $url, $page_var = 'page', $return_to = '') {
	// By current design, $pages_per_screen means number of page no in mid of nav bar
	// when $total_pages is larger than $pages_per_screen + 2(first and last)
	// So actual $pages_per_screen should be $pages_per_screen+2
	$pages_per_screen += 2;
	$url_page_select = "<ul class='pagination'>";

	if (strpos($url, '?') !== false) {
		$url . '&';
	} else {
		$url . '?';
	}

	$url_ellipsis = '<li><span>...</span></li>';

	$total_pages = ceil($total_rows / $rows_per_page);

	if ($rows_per_page <= 0) {
		$total_pages = 0;
	} else {
		$total_pages = ceil($total_rows / $rows_per_page);
	}

	$start_page = max(1, ($current_page - floor(($pages_per_screen - 1) / 2)));
	$end_page   = min($total_pages, ($current_page + floor(($pages_per_screen - 1) / 2)));

	if ($total_pages <= $pages_per_screen) {
		$start_page = 2;
		$end_page   = $total_pages - 1;
	} else {
		$start_page = max(2, ($current_page - floor(($pages_per_screen - 3) / 2)));
		/*When current_page > (pages_per_screen - 1) / 2*/
		$end_page = min($total_pages - 1, ($current_page + floor(($pages_per_screen - 3) / 2)));

		/* adjust if we are close to the beginning of the page list */
		if ($current_page <= ceil(($pages_per_screen) / 2)) {
			$end_page += ($pages_per_screen - $end_page - 1);
		}

		/* adjust if we are close to the end of the page list */
		if (($total_pages - $current_page) < ceil(($pages_per_screen) / 2)) {
			$start_page -= (($pages_per_screen - ($end_page - $start_page)) - 3);
		}

		/* stay within limits */
		$start_page = max(2, $start_page);
		$end_page   = min($total_pages - 1, $end_page);
	}

	if ($total_pages > 0) {
		if ($current_page == 1) {
			$url_page_select .= "<li><a href='#' class='active' onClick='goto$page_var(1);return false'>1</a></li>";
		} else {
			$url_page_select .= "<li><a href='#' onClick='goto$page_var(1);return false'>1</a></li>";
		}
	}

	for ($page_number=0; (($page_number + $start_page) <= $end_page); $page_number++) {
		$page = $page_number + $start_page;

		if ($page_number < $pages_per_screen) {
			if ($page_number == 0 && $start_page > 2) {
				$url_page_select .= $url_ellipsis;
			}

			if ($current_page == $page) {
				$url_page_select .= "<li><a href='#' class='active' onClick='goto$page_var($page);return false'>$page</a></li>";
			} else {
				$url_page_select .= "<li><a href='#' onClick='goto$page_var($page);return false'>$page</a></li>";
			}
		}
	}

	if ($total_pages - 1 > $end_page) {
		$url_page_select .= $url_ellipsis;
	}

	if ($total_pages > 1) {
		if ($current_page == $total_pages) {
			$url_page_select .= "<li><a href='#' class='active' onClick='goto$page_var($total_pages);return false'>$total_pages</a></li>";
		} else {
			$url_page_select .= "<li><a href='#' onClick='goto$page_var($total_pages);return false'>$total_pages</a></li>";
		}
	}

	$url_page_select .= '</ul>';

	if ($return_to == '') {
		$return_to = 'main';
	}

	$url = $url . $page_var;
	$url_page_select .= "<script type='text/javascript'>
	function goto$page_var(pageNo) {
		if (typeof url_graph === 'function') {
			var url_add=url_graph('')
		} else {
			var url_add='';
		};

		strURL = '$url='+pageNo+url_add;

		loadUrl({
			url: strURL,
			elementId: '$return_to',
		});
	}</script>";

	return $url_page_select;
}

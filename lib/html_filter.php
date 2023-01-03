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

/*
 * Create a consistent responsive filter
 */

class CactiTableFilter {
	public $form_header    = '';
	public $form_action    = '';
	public $form_id        = '';
	public $action_url     = '';
	public $action_label   = '';
	public $session_var    = 'sess_default';
	private $item_rows     = array();
	private $filter_array  = array();
	public $default_filter = array();


	public function __construct($form_header = '', $form_action = '', $form_id = '',
		$form_width = '', $session_var = '', $action_url = '', $action_label = '') {
		global $item_rows;

		$this->form_header   = $form_header;
		$this->form_action   = $form_action;
		$this->form_id       = $form_id;
		$this->form_width    = $form_width;
		$this->action_url    = $action_url;
		$this->action_label  = $action_label;
		$this->session_var   = $session_var;
		$this->item_rows     = $item_rows;

		if ($this->action_url != '' && $this->action_label == '') {
			$this->action_label = __('Add');
		}

		/* default filter */
		$this->default_filter = array(
			'rows' => array(
				'row1' => array(
					'filter' => array(
						'friendly_name'  => __('Search'),
						'filter'         => FILTER_DEFAULT,
						'placeholder'    => __('Enter a search term'),
						'size'           => '30',
						'default'        => '',
						'pageset'        => true,
						'max_length'     => '120'
					),
					'rows' => array(
						'friendly_name' => __('Rows'),
						'filter'        => FILTER_VALIDATE_INT,
						'method'        => 'drop_array',
						'default'       => '-1',
						'pageset'       => true,
						'array'         => $this->item_rows
					),
					'go' => array(
						'display' => __('Go'),
						'title'   => __('Apply filter to table'),
						'method'  => 'submit',
					),
					'clear' => array(
						'display' => __('Clear'),
						'title'   => __('Reset filter to default values'),
						'method'  => 'button',
					)
				)
			),
			'sort' => array(
				'sort_column' => 'name',
				'sort_direction' => 'ASC'
			)
		);
	}

	public function __destruct() {
		return true;
	}

	public function set_filter_row($array, $index = false) {
		if ($index === false) {
			$this->filter_array['rows'][] = $array;
		} else {
			$this->filter_array['rows'][$index] = $array;
		}
	}

	public function get_filter_row($index) {
		if ($index === false ) {
			return false;
		} elseif (array_key_exists($index, $this->filter_array['rows'])) {
			return $this->filter_array['rows'][$index];
		} else {
			return false;
		}
	}

	public function set_filter_array($array) {
		$this->filter_array = $array;
	}

	public function get_filter() {
		return $this->filter_array;
	}

	public function set_sort_array($sort_column, $sort_direction) {
		$this->filter_array['sort'] = array(
			'sort_column' => $sort_column,
			'sort_direction' => $sort_direction
		);
	}

	public function filter_render() {
		/* setup filter variables */
		sanitize_filter_variables();

		/* render the filter in the page */
		create_filter();

		/* create javascript to operate of the filter */
		create_javascript();

		return true;
	}

	private function create_filter() {
		if (!cacti_sizeof($this->filter_array)) {
			$this->filter_array = $this->default_filter;
		}

		html_start_box($this->form_header, $this->form_width, true, '3', 'center', $this->action_url, $this->action_label);

		if (isset($this->form_array['rows'])) {
			print "<form id='" . $this->filter_id . "' action='" . $this->filter_action . "'>\n";

			foreach($this->form_array['rows'] as $index => $row) {
				print "<div class='filterTable'>\n";
				print "<div class='formRow'>\n";

				foreach($row as $field_name => $field_array) {
					switch($field_array['method']) {
					case 'button':
						print "<div class='formColumnButton'>\n";
						print "<input type='button' class='ui-button ui-corner-all ui-widget' id='" . $field_name . "' value='" . html_escape_request_var($field_name) . "'" . (isset($field_array->title) ? " title='" . html_escape($field_array->title, ENT_QUOTES, 'UTF-8'):'') . "'>";
						print "</div>\n";

						break;
					case 'submit':
						print "<div class='formColumnButton'>\n";
						print "<input type='submit' class='ui-button ui-corner-all ui-widget' id='" . $field_name . "' value='" . html_escape_request_var($field_name) . "'" . (isset($field_array->title) ? " title='" . html_escape($field_array->title):'') . "'>";
						print "</div>\n";

						break;
					case 'timespan':
						print "<div class='formColumn'><div class='formFieldName'>" . __('Presets') . "</div></div>\n";

						break;
					default:
						if (isset($field_array['friendly_name'])) {
							print "<div class='formColumn'><div class='formFieldName'>" . $field_array['friendly_name'] . "</div></div>\n";
						}

						print "<div class='formColumn'>\n";

						draw_edit_control($field_name, $field_array);

						print "</div>\n";
					}
				}

				print "</div>\n";
				print "</div>\n";
			}

			print "</form>\n";
		}

		html_end_box(true, true);
	}

	private function create_javascript() {
		$applyFilter = '"' . $this->form_action;
		$clearFilter = $applyFilter;

		if (strpos('?', $applyFilter) === false) {
			$separator = '?';
		} else {
			$separator = '&';
		}

		$applyFilter .= $separator . 'header=false';
		$clearFilter .= $separator . 'header=false&clear=true"';
		$changeChain  = '';

		$separator = "\"+\"&";

		if (isset($this->form_array['rows'])) {
			foreach($this->form_array['rows'] as $index => $row) {
				foreach($row as $field_name => $field_array) {
					switch($field_array['method']) {
					case 'button':
					case 'submit':
						break;
					case 'checkbox':
						$applyFilter .= $separator . $field_name . "=\"+\"$(\'#" . $field_name . "').is(':checked')";
						break;
					case 'textbox':
					case 'drop_array':
					case 'drop_files':
					case 'drop_sql':
					case 'drop_callback':
					case 'drop_multi':
					case 'drop_color':
					case 'drop_tree':
						if ($field_array['method'] != 'textbox') {
							$changeChain .= ($changeChain != '' ? ', ':'') . '#' . $field_name;
						}
						$applyFilter .= $separator . $field_name . "=\"+\"$(\'#" . $field_name . "').val()";
						break;
					default:
					}
				}
			}

			$applyFilter .= '";';
		}

		?>
		<script type='text/javascript'>

		function applyFilter() {
			strURL = <?php print $applyFilter;?>
			loadPageNoHeader(strURL);
		}

		function clearFilter() {
			loadPageNoHeader(<?php print $clearFilter;?>);
		}

		$(function() {
			$('#<?php print $this->form_id;?>').submit(function(event) {
				event.preventDefault();
				applyFilter();
			});

			$('<?php print $changeChain;?>').change(function() {
				applyFilter();
			});

			$('#clear').click(function() {
				clearFilter();
			})
		});
		</script>

		<?php
	}

	private function sanitize_filter_variables() {
		$filters = array();

		if (isset($this->form_array['rows'])) {
			foreach($this->form_array['rows'] as $index => $row) {
				foreach($row as $field_name => $field_array) {
					switch($field_array['method']) {
					case 'button':
					case 'submit':
						break;
					default:
						$filters[$field_name]['filter'] = $field_array['filter'];

						if (isset($field_array['filter_options'])) {
							$filters[$field_name]['options'] = $field_array['filter_options'];
						}

						if (isset($field_array['pageset'])) {
							$filters[$field_name]['pageset'] = $field_array['pageset'];
						}

						if (isset($field_array['default'])) {
							$filters[$field_name]['default'] = $field_array['default'];
						} else {
							$filters[$field_name]['default'] = '';
						}

						break;
					}
				}
			}
		}

		if (isset($this->form_array['sort'])) {
			$filters['sort_column']['filter']     = FILTER_CALLBACK;
			$filters['sort_column']['options']    = array('options' => 'sanitize_search_string');
			$filters['sort_column']['default']    = $this->form_array['sort']['sort_column'];

			$filters['sort_direction']['filter']  = FILTER_CALLBACK;
			$filters['sort_direction']['options'] = array('options' => 'sanitize_search_string');
			$filters['sort_direction']['default'] = $this->form_array['sort']['sort_direction'];
		}

		validate_store_request_vars($filters, $this->session_var);
	}
}

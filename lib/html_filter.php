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

// Create a consistent responsive filter

class CactiTableFilter {
	// constructor variables
	public string $form_header      = '';
	public string $form_action      = '';
	public string $form_id          = '';
	public string $form_method      = 'get';
	public string $session_var      = 'sess_';
	public string $action_url       = '';
	public string $action_label     = '';
	public bool $show_columns       = true;
	public array $default_filter    = [];
	public string $rows_label       = '';
	public string $associated_label = '';
	public string $js_extra         = '';
	public bool $dynamic            = true;
	public int $def_refresh         = 300;

	/**
	 * Custom hooks for common functionality.
	 * These hooks will reduce the number of
	 * pages that will require a full stack replacement
	 * filter.
	 */
	public bool   $has_graphs      = false;
	public bool   $has_data        = false;
	public bool   $has_save        = false;
	public bool   $has_import      = false;
	public bool   $has_export      = false;
	public bool   $has_purge       = false;
	public bool   $has_named       = false;
	public bool   $has_associated  = false;
	public bool   $has_refresh     = false;
	public bool   $render_layouts  = true;
	public string $filter_format   = 'modern';
	public mixed  $inject_content  = false;
	private bool  $initialized     = false;
	private array $sort_array      = [];
	private array $button_array    = [];
	private array $link_array      = [];
	private array $append_array    = [];
	private array $item_rows       = [];
	private array $timespans       = [];
	private array $timeshifts      = [];
	private array $filter_array    = [];
	private array $frequencies     = [];

	public function __construct(string $form_header = '', string $form_action = '', string $form_id = '',
		string $session_var = '', string $action_url = '', mixed $action_label = '', bool $show_columns = true) {
		global $item_rows, $graph_timespans, $graph_timeshifts;

		$this->form_header   = $form_header;
		$this->form_action   = $form_action;
		$this->form_id       = $form_id;
		$this->session_var   = $session_var;
		$this->action_url    = $action_url;
		$this->action_label  = $action_label;
		$this->show_columns  = $show_columns;

		$this->item_rows     = $item_rows;
		$this->timespans     = $graph_timespans;
		$this->timeshifts    = $graph_timeshifts;
		$this->rows_label    = __('Rows');

		$this->frequencies = [
			5   => __('%d Seconds', 5),
			10  => __('%d Seconds', 10),
			20  => __('%d Seconds', 20),
			30  => __('%d Seconds', 30),
			45  => __('%d Seconds', 45),
			60  => __('%d Minute', 1),
			120 => __('%d Minutes', 2),
			300 => __('%d Minutes', 5)
		];

		if ($this->session_var == '') {
			$action = gnrv('action');
			$tab    = gnrv('tab');

			if ($action != '') {
				$this->session_var .= basename(get_current_page(), '.php') . '_' . $action;
			} elseif ($tab != '') {
				$this->session_var .= basename(get_current_page(), '.php') . '_' . $tab;
			} else {
				$this->session_var .= basename(get_current_page(), '.php');
			}
		}

		if ($this->action_url != '' && $this->action_label == '') {
			$this->action_label = __('Add');
		}

		// Preset pages (CDEFs, VDEFs, Colors, ...) manage system presets, not
		// per-user record lists, so they do not offer saved filter layouts.
		$page = basename($this->form_action != '' ? $this->form_action : get_current_page());

		if (in_array($page, filter_layouts_preset_pages(), true)) {
			$this->render_layouts = false;
		}

		$this->filter_format = filter_layouts_user_format();

		$this->filter_array = $this->create_default();
	}

	private function create_default() : array {
		// default filter
		return [
			'rows' => [
				[
					'filter' => [
						'method'         => 'textbox',
						'friendly_name'  => __('Search'),
						'filter'         => FILTER_CALLBACK,
						'filter_options' => ['options' => 'sanitize_search_string'],
						'placeholder'    => __('Enter a search term'),
						'size'           => '30',
						'default'        => '',
						'pageset'        => true,
						'max_length'     => '120',
						'value'          => ''
					],
					'rows' => [
						'method'        => 'drop_array',
						'friendly_name' => $this->rows_label,
						'filter'        => FILTER_VALIDATE_INT,
						'default'       => '-1',
						'pageset'       => true,
						'array'         => $this->item_rows,
						'value'         => '-1'
					]
				]
			],
			'buttons' => [
				'go' => [
					'method'  => 'submit',
					'display' => __('Go'),
					'title'   => __('Apply filter to table'),
				],
				'clear' => [
					'method'  => 'button',
					'display' => __('Clear'),
					'title'   => __('Reset filter to default values'),
				]
			],
			'sort' => [
				'sort_column'    => 'name',
				'sort_direction' => 'ASC'
			]
		];
	}

	public function __destruct() {
	}

	public function set_filter_row(array $array, bool $index = false) : void {
		if ($index === false) {
			$this->filter_array['rows'][] = $array;
		} else {
			$this->filter_array['rows'][$index] = $array;
		}
	}

	public function get_filter_row(string $index) : bool {
		if ($index === false) {
			return false;
		}

		if (array_key_exists($index, $this->filter_array['rows'])) {
			return $this->filter_array['rows'][$index];
		} else {
			return false;
		}
	}

	public function set_filter_array(array $array) : void {
		$this->filter_array = $array;
	}

	public function get_filter() : array {
		return $this->filter_array;
	}

	public function set_sort_array(string $sort_column, string $sort_direction) : void {
		$this->sort_array = [
			'sort_column'    => $sort_column,
			'sort_direction' => $sort_direction
		];
	}

	public function add_button(string $id, array $button) : void {
		$this->button_array[$id] = $button;
	}

	public function add_link(string $id, string $link) : void {
		$this->link_array[$id] = $link;
	}

	public function add_row_element(int $row, string $id, array $filter) : void {
		$this->append_array[$row][$id] = $filter;
	}

	public function render() : bool {
		if (!$this->initialized) {
			$this->initialize_filter();
		}

		// validate filter variables
		$this->sanitize_filter_variables();

		// create the filter for the page
		$filter = $this->create_filter();

		// if validation succeeds, print output the data
		print $filter;

		// create javascript to operate of the filter
		print $this->create_javascript();

		return true;
	}

	public function sanitize() : void {
		if (!$this->initialized) {
			$this->initialize_filter();
		}

		// validate filter variables
		$this->sanitize_filter_variables();
	}

	private function initialize_filter() : void {
		if (!cacti_sizeof($this->filter_array)) {
			$this->filter_array = $this->create_default();
		}

		if (cacti_sizeof($this->sort_array)) {
			$this->filter_array['sort'] = $this->sort_array;
		}

		if (cacti_sizeof($this->button_array)) {
			if (cacti_sizeof($this->filter_array['buttons'])) {
				$this->filter_array['buttons'] += $this->button_array;
			} else {
				$this->filter_array['buttons']  = $this->button_array;
			}
		}

		if (cacti_sizeof($this->link_array)) {
			if (cacti_sizeof($this->filter_array['links'])) {
				$this->filter_array['links'] += $this->link_array;
			} else {
				$this->filter_array['links']  = $this->link_array;
			}
		}

		if (cacti_sizeof($this->append_array)) {
			foreach ($this->append_array as $row => $data) {
				foreach ($data as $id => $filter) {
					$this->filter_array['rows'][$row][$id] = $filter;
				}
			}
		}

		// Make common adjustments
		if ($this->has_refresh) {
			if (isrv('refresh')) {
				$value = gnrv('refresh');
			} else {
				$value = $this->def_refresh;
			}

			$this->filter_array['rows'][0] += [
				'refresh' => [
					'method'        => 'drop_array',
					'friendly_name' => __('Refresh'),
					'filter'        => FILTER_VALIDATE_INT,
					'default'       => $this->def_refresh,
					'array'         => $this->frequencies,
					'value'         => $value
				]
			];
		}

		if ($this->has_graphs) {
			if (isrv('has_graphs')) {
				$value = gnrv('has_graphs');
			} else {
				$value = read_config_option('default_has') == 'on' ? 'true' : 'false';
			}

			$this->filter_array['rows'][0] += [
				'has_graphs' => [
					'method'         => 'filter_checkbox',
					'friendly_name'  => __('Has Graphs'),
					'filter'         => FILTER_VALIDATE_REGEXP,
					'filter_options' => ['options' => ['regexp' => '/^(true|false)$/']],
					'default'        => read_config_option('default_has') == 'on' ? 'true' : 'false',
					'pageset'        => true,
					'value'          => $value
				]
			];
		}

		if ($this->has_data) {
			if (isrv('has_data')) {
				$value = gnrv('has_data');
			} else {
				$value = read_config_option('default_has') == 'on' ? 'true' : 'false';
			}

			$this->filter_array['rows'][0] += [
				'has_data' => [
					'method'         => 'filter_checkbox',
					'friendly_name'  => __('Has Data Sources'),
					'filter'         => FILTER_VALIDATE_REGEXP,
					'filter_options' => ['options' => ['regexp' => '/^(true|false)$/']],
					'default'        => read_config_option('default_has') == 'on' ? 'true' : 'false',
					'pageset'        => true,
					'value'          => $value
				]
			];
		}

		if ($this->has_named) {
			if (isrv('named')) {
				$value = gnrv('named');
			} else {
				$value = read_config_option('default_has') == 'on' ? 'true' : 'false';
			}

			$this->filter_array['rows'][0] += [
				'named' => [
					'method'         => 'filter_checkbox',
					'friendly_name'  => __('Named Colors'),
					'filter'         => FILTER_VALIDATE_REGEXP,
					'filter_options' => ['options' => ['regexp' => '/^(true|false)$/']],
					'default'        => read_config_option('default_has') == 'on' ? 'true' : 'false',
					'pageset'        => true,
					'value'          => $value
				]
			];
		}

		if ($this->has_associated) {
			if (isrv('associated')) {
				$value = gnrv('associated');
			} else {
				$value = read_config_option('default_has') == 'on' ? 'true' : 'false';
			}

			$this->filter_array['rows'][0] += [
				'associated' => [
					'method'         => 'filter_checkbox',
					'friendly_name'  => ($this->associated_label != '' ? $this->associated_label : __('Associated')),
					'filter'         => FILTER_VALIDATE_REGEXP,
					'filter_options' => ['options' => ['regexp' => '/^(true|false)$/']],
					'default'        => read_config_option('default_has') == 'on' ? 'true' : 'false',
					'pageset'        => true,
					'value'          => $value
				]
			];
		}

		if ($this->has_save) {
			$this->filter_array['buttons']['save'] = [
				'method'  => 'button',
				'display' => __('Save'),
				'title'   => __('Save Filter Defaults'),
				'status'  => __('Saving Filter')
			];
		}

		if ($this->has_import) {
			$this->filter_array['buttons']['import'] = [
				'method'  => 'button',
				'display' => __('Import'),
				'title'   => __('Import Data'),
			];
		}

		if ($this->has_export) {
			$this->filter_array['buttons']['export'] = [
				'method'   => 'button',
				'display'  => __('Export'),
				'title'    => __('Export Data'),
				'callback' => 'document.location = \'' . get_current_page() . '?action=export\'',
			];
		}

		if ($this->has_purge) {
			$this->filter_array['buttons']['purge'] = [
				'method'  => 'button',
				'display' => __('Purge'),
				'title'   => __('Purge Data'),
				'status'  => __('Purging Data')
			];
		}

		if (isset($this->filter_array['buttons']) && cacti_sizeof($this->filter_array['buttons'])) {
			$this->filter_array['rows'][0] += $this->filter_array['buttons'];
		}

		$this->initialized = true;
	}

	/**
	 * Whether this filter should render in the modern (Layouts + Edit dialog)
	 * layout. Modern requires saved layouts to be active on the page; preset
	 * pages and an explicit legacy user preference fall back to the inline
	 * filter.
	 *
	 * @return bool
	 */
	private function use_modern_filter() : bool {
		return $this->render_layouts && $this->filter_format === 'modern';
	}

	/**
	 * Whether a field stays on the modern filter bar rather than moving into the
	 * Edit dialog. Time controls and the auto-refresh selector remain visible.
	 *
	 * @param string              $field_name  The field key.
	 * @param array<string,mixed> $field_array The field definition.
	 *
	 * @return bool
	 */
	private function field_is_bar(string $field_name, array $field_array) : bool {
		return ($field_array['method'] ?? '') === 'timespan' || $field_name === 'refresh' || $field_name === 'rows';
	}

	/**
	 * Render a single filter field (label + control) to HTML. Shared by the
	 * legacy inline layout and the modern Edit dialog so field rendering lives
	 * in one place.
	 *
	 * @param string              $field_name  The field key.
	 * @param array<string,mixed> $field_array The field definition.
	 *
	 * @return string
	 */
	private function emit_field(string $field_name, array $field_array) : string {
		ob_start();

		// The row-count selector is always labeled "Rows", regardless of any
		// page-specific name (Devices, Trees, ...).
		if ($field_name === 'rows' && isset($field_array['friendly_name'])) {
			$field_array['friendly_name'] = __('Rows');
		}

		// Every page's Go submit action renders as a compact refresh glyph button.
		if ($field_name === 'go' && ($field_array['method'] ?? '') === 'submit') {
			$field_array['glyph'] = 'ti ti-refresh';
			$field_array['title'] = __('Refresh page');
		}

		if (isset($field_array['class'])) {
			$class = ' ' . $field_array['class'];
		} else {
			$class = '';
		}

		if (!isset($field_array['value']) &&
			$field_array['method'] != 'validate' &&
			$field_array['method'] != 'submit' &&
			$field_array['method'] != 'content' &&
			$field_array['method'] != 'button' &&
			$field_array['method'] != 'timespan') {
			cacti_log("WARNING: The Filter Class value field $field_name is missing");

			$field_array['value'] = '';
		}

		switch($field_array['method']) {
			case 'content':
				print '<div class="filterColumn">' . $field_array['content'] . '</div>';

				break;
			case 'validate':
				// Just for validating other request variables

				break;
			case 'button':
				print '<div class="filterColumnButton">' . PHP_EOL;

				if (isset($field_array['display'])) {
					print '<button type="button" class="ui-button ui-corner-all ui-widget" id="' . $field_name . '"' . (isset($field_array['title']) ? ' title="' . $field_array['title'] : '') . '"><span class="button-text">' . $field_array['display'] . '</span></button>';
				} else {
					print '<button type="button" class="ui-button ui-corner-all ui-widget" id="' . $field_name . '"' . (isset($field_array['title']) ? ' title="' . $field_array['title'] : '') . '"><i class="' . $field_array['class'] . '"></i></button>';
				}

				print '</div>' . PHP_EOL;

				break;
			case 'submit':
				print '<div class="filterColumnButton">' . PHP_EOL;
				print '<button type="submit" class="ui-button ui-corner-all ui-widget ui-state-active ' . $class . '" id="' . $field_name . '" ' . (isset($field_array['title']) ? ' title="' . $field_array['title'] : '') . '">' . (!empty($field_array['glyph']) ? '<i class="' . $field_array['glyph'] . '"></i>' : '<span class="button-text">' . $field_array['display'] . '</span>') . '</button>';
				print '</div>' . PHP_EOL;

				break;
			case 'filter_checkbox':
				$cb_title = html_escape_attr($field_array['title'] ?? $field_array['friendly_name']);

				print '<div class="filterColumn"><div class="filterFieldName">' . $field_array['friendly_name'] . '</div></div>' . PHP_EOL;
				print '<div class="filterColumn"><label class="checkboxSwitch" title="' . $cb_title . '"><input type="checkbox" class="formCheckbox' . $class . '" id="' . $field_name . '" title="' . $cb_title . '"' . ($field_array['value'] == 'on' || $field_array['value'] == 'true' ? ' checked' : '') . '><span class="checkboxSlider checkboxRound"></span></label></div>' . PHP_EOL;

				break;
			case 'timespan':
				print '<div class="filterColumn"><div class="filterFieldName">' . __('Presets') . '</div></div>' . PHP_EOL;

				print '<div class="filterColumn">';
				print '<select id="predefined_timespan" class="' . $class . '">';

				$this->timespans = array_merge([GT_CUSTOM => __('Custom')], $this->timespans);

				$start_val = 0;
				$end_val   = cacti_sizeof($this->timespans);

				if (cacti_sizeof($this->timespans)) {
					foreach ($this->timespans as $value => $text) {
						print "<option value='$value'" . ($_SESSION['sess_current_timespan'] == $value ? ' selected' : '') . '>' . htmle($text) . '</option>';
					}
				}
				print '</select>';
				print '</div>';

				// From data
				print '<div class="filterColumn">';
				print __('From');
				print '</div>';
				print '<div class="filterColumn">';
				print '<span>';
				print '<input type="text" class="ui-state-default ui-corner-all' . $class . '" id="date1" size="18" value="' . ($_SESSION['sess_current_date1'] ?? '') . '">';
				print '<i id="startDate" class="calendar ti ti-calendar-clock" title="' . __esc('Start Date Selector') . '"></i>';
				print '</span>';
				print '</div>';

				// To Data
				print '<div class="filterColumn">';
				print __('From');
				print '</div>';
				print '<div class="filterColumn">';
				print '<span>';
				print '<input type="text" class="ui-state-default ui-corner-all' . $class . '" id="date2" size="18" value="' . ($_SESSION['sess_current_date2'] ?? '') . '">';
				print '<i id="endDate" class="calendar ti ti-calendar-clock" title="' . __esc('End Date Selector') . '"></i>';
				print '</span>';
				print '</div>';

				if (isset($field_array['shifter']) && $field_array['shifter'] === true) {
					print '<div class="filterColumn">';
					print '<span>';

					print '<i id="shift_left" class="shiftArrow ti ti-player-track-prev" title="' . __esc('Shift Time Backward') . '"></i>';
					print '<select id="predefined_timeshift" title="' . __esc('Define Shifting Interval') . '" class="' . $class . '">';

					$start_val  = 1;
					$end_val    = cacti_sizeof($this->timeshifts) + 1;

					if (cacti_sizeof($this->timeshifts)) {
						for ($shift_value = $start_val; $shift_value < $end_val; $shift_value++) {
							print "<option value='$shift_value'" . ($_SESSION['sess_current_timeshift'] == $shift_value ? ' selected' : '') . '>' . htmle($this->timeshifts[$shift_value]) . '</option>';
						}
					}

					print '</select>';
					print '<i id="shift_right" class="shiftArrow ti ti-player-track-next" title="' . __esc('Shift Time Forward') . '"></i>';

					print '</span>';
					print '</div>';
				}

				if ((isset($field_array['refresh']) && $field_array['refresh'] === true) || (isset($field_array['clear']) && $field_array['clear'] === true)) {
					print '<div class="filterColumn">';
					print '<span>';

					if (isset($field_array['refresh'])) {
						print '<button type="button" class="ui-button ui-corner-all ui-widget" id="tsrefresh"' . ' title="' . __esc('Refresh Selected Timespan') . '"><span class="button-text">' . __esc('Refresh') . '</span></button>';
					}

					if (isset($field_array['clear'])) {
						print '<button type="button" class="ui-button ui-corner-all ui-widget" id="tsclear"' . ' title="' . __esc('Clear Selected Timespan') . '"><span class="button-text">' . __esc('Clear') . '</span></span></button>';
					}

					print '</span>';
					print '</div>';
				}

				break;
			case 'hidden':
				print '<div class="filterColumn" style="display:none">' . PHP_EOL;

				draw_edit_control($field_name, $field_array);

				print '</div>' . PHP_EOL;

				break;
			default:
				if (isset($field_array['friendly_name'])) {
					print '<div class="filterColumn"><div class="filterFieldName"><label for="' . $field_name . '">' . $field_array['friendly_name'] . '</label></div></div>' . PHP_EOL;
				}

				if (isrv($field_name) && !str_contains($field_array['method'], 'callback')) {
					$field_array['value'] = gnrv($field_name);
				}

				print '<div class="filterColumn">' . PHP_EOL;

				draw_edit_control($field_name, $field_array);

				print '</div>' . PHP_EOL;
		}

		return (string) ob_get_clean();
	}

	private function create_filter() : string {
		// Buffer output
		ob_start();

		$text_appended = false;

		if (isset($this->filter_array['links']) && cacti_sizeof($this->filter_array['links'])) {
			$linkButtons = [];

			if ($this->action_url != '') {
				$linkButtons[] = [
					'id'       => 'add',
					'href'     => $this->action_url,
					'title'    => $this->action_label,
					'callback' => true,
					'class'    => 'ti ti-plus plusAdd'
				];
			}

			foreach ($this->filter_array['links'] as $index => $link) {
				$linkButtons[] = [
					'id'       => 'dynamic' . $index,
					'href'     => $link['url'],
					'title'    => $link['display'],
					'callback' => true,
					'class'    => $link['class']
				];
			}

			html_filter_start_box($this->form_header, $linkButtons, true, $this->show_columns, $this->action_label);
		} else {
			html_filter_start_box($this->form_header, $this->action_url, true, $this->show_columns, $this->action_label);
		}

		if ($this->use_modern_filter()) {
			print $this->create_modern_filter();

			html_filter_end_box();

			return (string) ob_get_clean();
		}

		if (isset($this->filter_array['rows'])) {
			print "<form id='" . $this->form_id . "' action='" . $this->form_action . "' method='" . $this->form_method . "' class='cactiFilter'>";

			foreach ($this->filter_array['rows'] as $index => $row) {
				if ($index > 0 && !$text_appended) {
					print "<div class='filterColumnButton' id='text'></div>";
					$text_appended = true;
				}

				print "<div class='filterTable even'>";
				print "<div class='filterRow'>";

				foreach ($row as $field_name => $field_array) {
					print $this->emit_field($field_name, $field_array);
				}

				if ($index == 0) {
					print "<div class='filterColumnButton' id='text'></div>";
				}

				print '</div>' . PHP_EOL;
				print '</div>' . PHP_EOL;
			}

			if ($this->inject_content !== false) {
				print $this->inject_content;
			}

			print '</form>' . PHP_EOL;
		}

		if ($this->render_layouts) {
			print $this->create_layouts();
		}

		html_filter_end_box();

		return (string) ob_get_clean();
	}

	/**
	 * Render the modern filter: a compact bar (Layouts selector, time controls,
	 * and layout action buttons) plus a hidden Edit dialog that holds the filter
	 * name and all filterable fields. The dialog is activated by create_modern_
	 * javascript().
	 *
	 * @return string
	 */
	private function create_modern_filter() : string {
		$page     = filter_layouts_page_key($this->form_action != '' ? $this->form_action : get_current_page());
		$layouts  = filter_layouts_get_available($page);
		$can_glob = filter_layouts_can_manage_global();

		// Split the configured fields: time controls, the refresh selector, and
		// page action buttons (Import, Export, Purge, Save, sort, ...) stay on the
		// bar; the filter inputs move into the Edit dialog. go/clear are replaced
		// by the dialog's Search/Clear footer actions.
		$bar_fields      = [];
		$bar_actions     = [];
		$amalgam_fields  = [];
		$timespan_fields = [];
		$dialog_fields   = [];

		if (isset($this->filter_array['rows'])) {
			foreach ($this->filter_array['rows'] as $row) {
				foreach ($row as $field_name => $field_array) {
					$method = $field_array['method'] ?? '';

					if (!empty($field_array['bar'])) {
						$amalgam_fields[$field_name] = $field_array;
					} elseif ($method === 'timespan') {
						$timespan_fields[$field_name] = $field_array;
					} elseif ($this->field_is_bar($field_name, $field_array)) {
						$bar_fields[$field_name] = $field_array;
					} elseif ($method === 'submit' || $method === 'button') {
						if ($field_name !== 'go' && $field_name !== 'clear') {
							$bar_actions[$field_name] = $field_array;
						}
					} else {
						$dialog_fields[$field_name] = $field_array;
					}
				}
			}
		}

		// Keep the bar selectors in a stable order: Rows first, Refresh second.
		if (cacti_sizeof($bar_fields)) {
			$ordered = [];

			foreach (['rows', 'refresh'] as $pref) {
				if (isset($bar_fields[$pref])) {
					$ordered[$pref] = $bar_fields[$pref];
					unset($bar_fields[$pref]);
				}
			}

			$bar_fields = $ordered + $bar_fields;
		}

		ob_start();

		// Filter bar.
		print "<div class='filterTable even cactiFilterModernBar'>";
		print "<div class='filterRow'>";

		print "<div class='filterColumn'><div class='filterFieldName'>" . __('Layouts') . '</div></div>';
		print "<div class='filterColumn'>" . $this->layout_select($layouts, $can_glob) . '</div>';

		foreach ($bar_fields as $field_name => $field_array) {
			print $this->emit_field($field_name, $field_array);
		}

		// Separate the always-present Layouts selector (plus any Rows/Refresh) from the layout buttons.
		print "<div class='filterColumnButton'><span class='barSep'></span></div>";

		print $this->layout_button('layout_edit',   __('Edit'),    __('Edit the current filter'));
		print $this->layout_button('layout_rename', __('Rename'),  __('Rename the selected layout'), true);
		print $this->layout_button('layout_delete', __('Delete'),  __('Delete the selected layout'), true);
		print $this->layout_button('layout_saveas', __('Save As'), __('Save this layout as a new personal layout'), true);

		// Refresh (reload the current view, keeping the selected layout) as a glyph button, right of the Edit group.
		print "<div class='filterColumnButton'><button type='button' class='ui-button ui-corner-all ui-widget' id='layout_refresh' title='" . __esc('Refresh page') . "'><i class='ti ti-refresh'></i></button></div>" . PHP_EOL;

		// Page actions (Import, Export, Purge, Save, sort asc/desc, ...) belong on
		// the bar beside the Layouts selector, not inside the Edit dialog.
		if (cacti_sizeof($bar_actions)) {
			print "<div class='filterColumnButton'><span class='barSep'></span></div>";

			foreach ($bar_actions as $field_name => $field_array) {
				print $this->emit_field($field_name, $field_array);
			}
		}

		print '</div>';
		print '</div>' . PHP_EOL;

		// The Graph Template amalgam (Template + Source/Order/CF/Measure) gets its
		// own second filter line.
		if (cacti_sizeof($amalgam_fields)) {
			print "<div class='filterTable even cactiFilterModernBar'>";
			print "<div class='filterRow'>";

			foreach ($amalgam_fields as $field_name => $field_array) {
				print $this->emit_field($field_name, $field_array);
			}

			print '</div>';
			print '</div>' . PHP_EOL;
		}

		// Timespan controls get their own third filter line.
		if (cacti_sizeof($timespan_fields)) {
			print "<div class='filterTable even cactiFilterModernBar'>";
			print "<div class='filterRow'>";

			foreach ($timespan_fields as $field_name => $field_array) {
				print $this->emit_field($field_name, $field_array);
			}

			print '</div>';
			print '</div>' . PHP_EOL;
		}

		// Edit dialog.
		$title = $this->form_header != '' ? $this->form_header : __('Edit Filter');

		print "<div id='" . $this->form_id . "_dialog' class='cactiFilterEditDialog' title='" . html_escape_attr($title) . "' style='display:none;'>";
		print "<form id='" . $this->form_id . "' action='" . $this->form_action . "' method='" . $this->form_method . "' class='cactiFilter'>";

		// One filter variable per row in a single table so the label column
		// aligns across rows via the existing table-cell layout, in every theme.
		print "<div class='filterTable even cactiFilterEditTable'>";

		print "<div class='filterRow cactiFilterEditRow'>";
		print "<div class='filterColumn'><div class='filterFieldName'><label for='layout_name'>" . __('Filter Name') . '</label></div></div>';
		print "<div class='filterColumn'><input type='text' id='layout_name' size='40' maxlength='128' class='ui-state-default ui-corner-all'></div>";
		print '</div>' . PHP_EOL;

		$hidden = '';

		foreach ($dialog_fields as $field_name => $field_array) {
			$method = $field_array['method'] ?? '';

			if ($method === 'hidden' || $method === 'validate') {
				$hidden .= $this->emit_field($field_name, $field_array);

				continue;
			}

			print "<div class='filterRow cactiFilterEditRow'>";
			print $this->emit_field($field_name, $field_array);
			print '</div>' . PHP_EOL;
		}

		print '</div>' . PHP_EOL;

		// Hidden/validate fields stay in the form but outside the visible table.
		print $hidden;

		if ($this->inject_content !== false) {
			print $this->inject_content;
		}

		print '</form>';
		print '</div>' . PHP_EOL;

		return (string) ob_get_clean();
	}

	/**
	 * Render the Layouts <select>. Each option carries the rebuilt navigation
	 * url and the metadata the layout buttons use to decide what a user may do
	 * with the row (own/global, editable).
	 *
	 * @param array<int, array<string,mixed>> $layouts  Available layout rows.
	 * @param bool                            $can_glob Whether global layouts are manageable.
	 *
	 * @return string
	 */
	private function layout_select(array $layouts, bool $can_glob) : string {
		$selected = isset_request_var('filter_layout') ? (int) get_nfilter_request_var('filter_layout') : 0;

		ob_start();

		print "<select id='filter_layout' class='ui-state-default ui-corner-all'>";
		print "<option value='0' data-url='' data-editable='1' data-global='0'>" . html_escape(__('(New Layout)')) . '</option>';

		if (cacti_sizeof($layouts)) {
			foreach ($layouts as $layout) {
				$document = filter_layouts_decode($layout['data']);

				if ($document === false) {
					continue;
				}

				$url = filter_layouts_document_url($document);

				if ($url === '') {
					continue;
				}

				$sep   = (strpos($url, '?') !== false) ? '&' : '?';
				$nav   = $url . $sep . 'filter_layout=' . $layout['id'];
				$label = $layout['name'] . ($layout['user_id'] == 0 ? ' (' . __('Global') . ')' : '');

				// A user may overwrite/rename/delete their own rows; global rows
				// (user_id 0) only when they can manage global layouts.
				$editable = ($layout['user_id'] != 0 || $can_glob) ? '1' : '0';
				$global   = ($layout['user_id'] == 0) ? '1' : '0';

				print "<option value='" . $layout['id'] . "' data-url='" . html_escape_url($nav) . "' data-name='" . html_escape_attr($layout['name']) . "' data-editable='" . $editable . "' data-global='" . $global . "'" . ($selected == $layout['id'] ? ' selected' : '') . '>' . html_escape($label) . '</option>';
			}
		}

		print '</select>';

		return (string) ob_get_clean();
	}

	private function make_function(string $buttonId, array $buttonArray, string $buttonAction) : string {
		$func_nl        = "\n\t\t\t";
		$func_el        = "\n\t\t";
		$buttonFunction = '';

		if (isset($buttonArray['url'])) {
			if (!isset($buttonArray['status'])) {
				$buttonFunction .= PHP_EOL . "\t\tfunction {$buttonId}Function () {" . $func_nl .
					'clearTimeout(myRefresh);' . $func_nl .
					"loadUrl({ url: '{$buttonArray['url']}' });" . $func_el .
				'};' . PHP_EOL;
			} else {
				$buttonFunction .= PHP_EOL . "\t\tfunction {$buttonId}Function () {" . $func_nl .
					'clearTimeout(myRefresh);' . $func_nl .
					"$('#text').text('{$buttonArray['status']}');" . $func_nl .
					"pulsate('#text');" . $func_nl .
					"loadUrl({ url: '{$buttonArray['url']}', funcEnd: 'finishFinalize' });" . $func_el .
				'};' . PHP_EOL;
			}
		} else {
			if (!isset($buttonArray['status'])) {
				if (isset($buttonArray['callback'])) {
					$callbackFunction = $buttonArray['callback'];
				} else {
					$callbackFunction = "loadUrl({ url: $buttonAction })";
				}

				$buttonFunction .= PHP_EOL . "\t\tfunction {$buttonId}Function () {" . $func_nl .
					'clearTimeout(myRefresh);' . $func_nl .
					$callbackFunction . ';' . $func_el .
					'Pace.stop();' . $func_el .
				'};' . PHP_EOL;
			} else {
				if (isset($buttonArray['callback'])) {
					$callbackFunction = $buttonArray['callback'];
				} else {
					$callbackFunction = "loadUrl({ url: $buttonAction, funcEnd: 'finishFinalize' })";
				}

				$buttonFunction .= PHP_EOL . "\t\tfunction {$buttonId}Function () {" . $func_nl .
					'clearTimeout(myRefresh);' . $func_nl .
					"$('#text').text('{$buttonArray['status']}');" . $func_nl .
					"pulsate('#text');" . $func_nl .
					$callbackFunction . ';' . $func_el .
					'Pace.stop();' . $func_el .
					'};' . PHP_EOL;
			}
		}

		return $buttonFunction;
	}

	private function create_javascript() : string {
		$applyFilter   = "'" . $this->form_action;
		$clearFilter   = $applyFilter;
		$defaultFilter = $applyFilter;

		if (!str_contains($applyFilter, '?')) {
			$separator = '?';
		} else {
			$separator = '&';
		}

		$applyFilter .= $separator;

		$clearFilter .= $separator . "clear=true'";
		$defaultFilter .= $separator . "action=noaction'";

		$changeChain   = '';
		$clickChain    = '';

		if (isset($this->filter_array['buttons']['go']['callback'])) {
			$changeFunction = $this->filter_array['buttons']['go']['callback'];
		} else {
			$changeFunction = 'applyFilter()';
		}

		if (isset($this->filter_array['buttons']['clear']['callback'])) {
			$clearFunction = $this->filter_array['buttons']['clear']['callback'];
		} else {
			$clearFunction = 'clearFilter()';
		}

		$filterLength    = 0;
		$refreshMSeconds = 9999999;
		$buttonFunctions = '';
		$buttonReady     = '';
		$readyAdd        = '';
		$globalAdd       = '';

		if (isset($this->filter_array['rows'])) {
			foreach ($this->filter_array['rows'] as $row) {
				foreach ($row as $field_name => $field_array) {
					switch($field_array['method']) {
						case 'content':
						case 'validate':
						case 'hidden':
							// Just for validating other request variables

							break;
						case 'button':
							switch($field_name) {
								case 'go':
								case 'clear':
									break;
								default:
									$buttonAction = str_replace('noaction', $field_name, $defaultFilter);

									$buttonFunctions .= $this->make_function($field_name, $field_array, $buttonAction);

									$buttonReady .= PHP_EOL . "\t\t\t$('#{$field_name}').click(function() { {$field_name}Function(); });";
							}

							break;
						case 'filter_checkbox':
							if ($this->dynamic && !$this->use_modern_filter()) {
								$clickChain .= ($clickChain != '' ? ', ' : '') . '#' . $field_name;
							}

							$applyFilter .= ($filterLength == 0 ? '' : "+'&") . $field_name . "='+$('#" . $field_name . "').is(':checked')";
							$filterLength++;

							break;
						case 'timespan':
							if (!isset($field_array['span_function'])) {
								$readyAdd .= "$('#predefined_timespan').change( function() { applyGraphTimespan(); });" . PHP_EOL;
							} else {
								$readyAdd .= "$('#predefined_timespan').change( function() { " . $field_array['span_function'] . '; });' . PHP_EOL;
							}

							if (isset($field_array['shifter']) && $field_array['shifter'] === true) {
								if (!isset($field_array['lshift_function'])) {
									$readyAdd .= "$('#shift_left').click( function() { timeshiftGraphFilterLeft(); });" . PHP_EOL;
								} else {
									$readyAdd .= "$('#shift_left').click( function() { " . $field_array['lshift_function'] . '; });' . PHP_EOL;
								}

								if (!isset($field_array['rshift_function'])) {
									$readyAdd .= "$('#shift_right').click( function() { timeshiftGraphFilterRight(); });" . PHP_EOL;
								} else {
									$readyAdd .= "$('#shift_right').click( function() { " . $field_array['rshift_function'] . '; });' . PHP_EOL;
								}
							}

							if (!isset($field_array['refresh_function'])) {
								$readyAdd .= "$('#tsrefresh').click( function() { refreshGraphTimespanFilter(); });" . PHP_EOL;
							} else {
								$readyAdd .= "$('#tsrefresh').click( function() { " . $field_array['refresh_function'] . '; });' . PHP_EOL;
							}

							if (!isset($field_array['clear_function'])) {
								$readyAdd .= "$('#tsclear').click( function() { clearGraphTimespanFilter(); });" . PHP_EOL;
							} else {
								$readyAdd .= "$('#tsclear').click( function() { " . $field_array['clear_function'] . '; });' . PHP_EOL;
							}

							break;
						case 'textbox':
						case 'drop_array':
						case 'drop_files':
						case 'drop_sql':
						case 'drop_callback':
						case 'drop_multi':
						case 'drop_color':
						case 'drop_tree':
							if ($field_array['method'] != 'textbox' && $this->dynamic) {
								if (!isset($field_array['dynamic']) || $field_array['dynamic'] === true) {
									if (!$this->use_modern_filter() || $this->field_is_bar($field_name, $field_array) || !empty($field_array['bar'])) {
										$changeChain .= ($changeChain != '' ? ', ' : '') . '#' . $field_name;
									}
								}
							}

							if ($field_name != 'rfilter') {
								$applyFilter .= ($filterLength == 0 ? '' : "+'&") . $field_name . "='+$('#" . $field_name . "').val()";
							} else {
								$applyFilter .= ($filterLength == 0 ? '' : "+'&") . $field_name . "='+base64_encode($('#" . $field_name . "').val())";
							}
							$filterLength++;

							break;
						case 'submit':
							break;
						default:
							break;
					}

					if ($this->has_refresh && $field_name == 'refresh') {
						$refreshMSeconds = $field_array['value'] * 1000;
					}
				}
			}

			if ($filterLength == 0) {
				$applyFilter .= "';";
			} else {
				$applyFilter .= ';';
			}
		}

		if (isset($this->filter_array['javascript']['ready']) && $this->filter_array['javascript']['ready'] != '') {
			$readyAdd .= "\t\t" . trim($this->filter_array['javascript']['ready']) . PHP_EOL;
		}

		if (isset($this->filter_array['javascript']['global']) && $this->filter_array['javascript']['global'] != '') {
			$globalAdd .= "\t\t" . trim($this->filter_array['javascript']['global']) . PHP_EOL;
		}

		if (!$this->has_refresh && isrv('refresh') && grv('refresh') > 0) {
			$refreshMSeconds = grv('refresh') * 1000;
		}

		if ($clickChain != '') {
			$clickReady = "$('" . $clickChain . "').click(function() {\n\t\t\t\t" .
				"$changeFunction;\n\t\t\t" .
			'});' . PHP_EOL;
		} else {
			$clickReady = '';
		}

		if ($changeChain != '') {
			$changeReady = "$('" . $changeChain . "').change(function() {\n\t\t\t\t" .
				"$changeFunction;\n\t\t\t" .
			'});' . PHP_EOL;
		} else {
			$changeReady = '';
		}

		$script = PHP_EOL . "<script type='text/javascript'>
		$globalAdd
		function applyFilter() {
			strURL = $applyFilter
			loadUrl({ url: strURL });
		}

		function clearFilter() {
			strURL = $clearFilter
			loadUrl({ url: strURL });
		}

		function finishFinalize(options, data) {
			$('#text').text('Finished').fadeOut(2000);
		}
		$buttonFunctions

		$(function() {
			if ($('#refresh').length) {
				refreshFunction = '$changeFunction';
				refreshMSeconds = $refreshMSeconds;
				refreshIsLogout = false;
				setupPageTimeout();

				$('#refresh').on('selectmenuopen', function() {
					if (myRefresh > 0) {
						clearTimeout(myRefresh);
					}
				});
			} else if (myRefresh > 0) {
				clearTimeout(myRefresh);
			}

			$('#" . $this->form_id . "').submit(function(event) {
				event.preventDefault();
				$changeFunction;
			});

			$('#clear').click(function() {
				$clearFunction;
			});

			$readyAdd
			$changeReady
			$clickReady
			$buttonReady
		});
	</script>" . PHP_EOL;

		if ($this->render_layouts) {
			if ($this->use_modern_filter()) {
				$script .= $this->create_modern_javascript($applyFilter, $changeFunction, $clearFunction);
			} else {
				$script .= $this->create_layouts_javascript($applyFilter);
			}
		}

		return $script;
	}

	private function sanitize_filter_variables() : void {
		$filters = [];

		if (isset($this->filter_array['rows'])) {
			foreach ($this->filter_array['rows'] as $row) {
				foreach ($row as $field_name => $field_array) {
					switch($field_array['method']) {
						case 'timespan':
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

		$filters['page']['filter']  = FILTER_VALIDATE_INT;
		$filters['page']['default'] = 1;

		$filters['layout']['filter']  = FILTER_VALIDATE_INT;
		$filters['layout']['default'] = 0;

		if (!isrv('page')) {
			srv('page', 1);
		}

		if (isset($this->filter_array['sort'])) {
			$filters['sort_column']['filter']     = FILTER_CALLBACK;
			$filters['sort_column']['options']    = ['options' => 'sanitize_search_string'];
			$filters['sort_column']['default']    = $this->filter_array['sort']['sort_column'];

			$filters['sort_direction']['filter']  = FILTER_CALLBACK;
			$filters['sort_direction']['options'] = ['options' => 'sanitize_search_string'];
			$filters['sort_direction']['default'] = $this->filter_array['sort']['sort_direction'];
		}

		validate_store_request_vars($filters, $this->session_var);
	}

	private function layout_button(string $id, string $display, string $title, bool $hidden = false) : string {
		return '<div class="filterColumnButton"' . ($hidden ? ' style="display:none"' : '') . '>' .
			'<button type="button" class="ui-button ui-corner-all ui-widget" id="' . $id . '" title="' . html_escape($title) . '"><span class="button-text">' . html_escape($display) . '</span></button>' .
			'</div>' . PHP_EOL;
	}

	private function create_layouts() : string {
		$page     = filter_layouts_page_key($this->form_action != '' ? $this->form_action : get_current_page());
		$layouts  = filter_layouts_get_available($page);
		$can_glob = filter_layouts_can_manage_global();

		ob_start();

		print "<div class='filterTable even cactiFilterLayouts'>";
		print "<div class='filterRow'>";

		print "<div class='filterColumn'><div class='filterFieldName'>" . __('Layouts') . '</div></div>';

		print "<div class='filterColumn'>";
		print $this->layout_select($layouts, $can_glob);
		print '</div>';

		print $this->layout_button('layout_save',   __('Save'),   __('Overwrite the selected layout with the current filter'));
		print $this->layout_button('layout_new',    __('New'),    __('Save the current filter as a new layout'));
		print $this->layout_button('layout_rename', __('Rename'), __('Rename the selected layout'));
		print $this->layout_button('layout_delete', __('Delete'), __('Delete the selected layout'));

		if ($can_glob) {
			print $this->layout_button('layout_publish', __('Publish'), __('Publish the selected layout so all users can see it'));
		}

		print '</div>';
		print '</div>' . PHP_EOL;

		// Hidden name-entry dialog reused by the New and Rename actions.
		print "<div id='layoutNameDialog' title='" . __esc('Layout Name') . "' style='display:none;'>";
		print "<form id='layoutNameForm' onsubmit='return false;'>";
		print "<label for='layout_name'>" . __('Name') . '</label> ';
		print "<input type='text' id='layout_name' size='40' maxlength='128' class='ui-state-default ui-corner-all'>";
		print '</form></div>' . PHP_EOL;

		return (string) ob_get_clean();
	}

	private function create_layouts_javascript(string $applyFilter) : string {
		$page = filter_layouts_page_key($this->form_action != '' ? $this->form_action : get_current_page());

		$js  = PHP_EOL . "<script type='text/javascript'>" . PHP_EOL;
		$js .= 'function layoutFilterUrl() {' . PHP_EOL;
		$js .= "\treturn " . $applyFilter . PHP_EOL;
		$js .= '}' . PHP_EOL;

		$js .= 'function layoutSelectedId() {' . PHP_EOL;
		$js .= "\treturn parseInt($('#filter_layout').val()) || 0;" . PHP_EOL;
		$js .= '}' . PHP_EOL;

		$js .= 'function layoutPost(layoutAction, extra, onDone) {' . PHP_EOL;
		$js .= "\tvar data = $.extend({ action: layoutAction, page: " . json_encode($page) . ', __csrf_magic: csrfMagicToken }, extra);' . PHP_EOL;
		$js .= "\t$.post(" . json_encode($page) . ", data, function(result) { if (onDone) { onDone(result); } }, 'json').fail(function() { alert(" . json_encode(__('The layout operation failed.')) . '); });' . PHP_EOL;
		$js .= '}' . PHP_EOL;

		$js .= 'function layoutNameDialog(title, value, onAccept) {' . PHP_EOL;
		$js .= "\t$('#layout_name').val(value);" . PHP_EOL;
		$js .= "\t$('#layoutNameDialog').dialog({ title: title, modal: true, width: 400, resizable: false, buttons: [" . PHP_EOL;
		$js .= "\t\t{ text: " . json_encode(__('OK')) . ", click: function() { var n = $('#layout_name').val(); $(this).dialog('close'); if (n != '') { onAccept(n); } } }," . PHP_EOL;
		$js .= "\t\t{ text: " . json_encode(__('Cancel')) . ", click: function() { $(this).dialog('close'); } }" . PHP_EOL;
		$js .= "\t] });" . PHP_EOL;
		$js .= '}' . PHP_EOL;

		$js .= 'function layoutEditable() {' . PHP_EOL;
		$js .= "\treturn $('#filter_layout option:selected').attr('data-editable') != '0';" . PHP_EOL;
		$js .= '}' . PHP_EOL;

		$js .= 'function layoutResultOk(r) {' . PHP_EOL;
		$js .= "\tif (r && r.ok) { return true; } alert(" . json_encode(__('The layout operation failed.')) . '); return false;' . PHP_EOL;
		$js .= '}' . PHP_EOL;

		$js .= '$(function() {' . PHP_EOL;
		$js .= "\t$('#filter_layout').change(function() { var u = $('#filter_layout option:selected').attr('data-url'); if (u != undefined && u != '') { document.location = u; } });" . PHP_EOL;

		$js .= "\t$('#layout_new').click(function() { layoutNameDialog(" . json_encode(__('New Layout')) . ", '', function(name) { layoutPost('layout_save', { name: name, url: layoutFilterUrl() }, function(r) { if (layoutResultOk(r) && r.url) { document.location = r.url; } }); }); });" . PHP_EOL;

		$js .= "\t$('#layout_save').click(function() { var id = layoutSelectedId(); if (id == 0) { $('#layout_new').click(); return; } if (!layoutEditable()) { alert(" . json_encode(__('You are not permitted to modify this layout.')) . "); return; } layoutPost('layout_save', { id: id, url: layoutFilterUrl() }, function(r) { if (layoutResultOk(r)) { if (r.url) { document.location = r.url; } else { window.location.reload(); } } }); });" . PHP_EOL;

		$js .= "\t$('#layout_rename').click(function() { var id = layoutSelectedId(); if (id == 0) { alert(" . json_encode(__('Please select a layout to rename.')) . '); return; } if (!layoutEditable()) { alert(' . json_encode(__('You are not permitted to modify this layout.')) . "); return; } var cur = $('#filter_layout option:selected').attr('data-name'); layoutNameDialog(" . json_encode(__('Rename Layout')) . ", cur, function(name) { layoutPost('layout_rename', { id: id, name: name }, function(r) { if (layoutResultOk(r)) { window.location.reload(); } }); }); });" . PHP_EOL;

		$js .= "\t$('#layout_delete').click(function() { var id = layoutSelectedId(); if (id == 0) { alert(" . json_encode(__('Please select a layout to delete.')) . '); return; } if (!layoutEditable()) { alert(' . json_encode(__('You are not permitted to modify this layout.')) . "); return; } var d = $('<div>').append($('<p>').text(" . json_encode(__('Delete the selected layout?')) . ")); $('body').append(d); d.dialog({ title: " . json_encode(__('Delete Layout')) . ', modal: true, width: 420, resizable: false, buttons: [ { text: ' . json_encode(__('Delete')) . ", click: function() { $(this).dialog('close'); layoutPost('layout_delete', { id: id }, function(r) { if (layoutResultOk(r)) { document.location = " . json_encode($page) . '; } }); } }, { text: ' . json_encode(__('Cancel')) . ", click: function() { $(this).dialog('close'); } } ], close: function() { $(this).dialog('destroy').remove(); } }); });" . PHP_EOL;

		$js .= "\t$('#layout_publish').click(function() { var id = layoutSelectedId(); if (id == 0) { alert(" . json_encode(__('Please select a layout to publish.')) . "); return; } layoutPost('layout_publish', { id: id }, function(r) { if (layoutResultOk(r)) { window.location.reload(); } }); });" . PHP_EOL;

		$js .= '});' . PHP_EOL;
		$js .= '</script>' . PHP_EOL;

		return $js;
	}

	/**
	 * Emit the modern filter JavaScript: the Layouts selector navigation, the
	 * bar action buttons, and the Edit dialog. The dialog's apply and clear
	 * actions reuse the page's configured go/clear button labels (e.g. Go), so
	 * they match the inline filter and do not collide with a filter field.
	 *
	 * @param string $applyFilter    The JS expression that builds the filter url.
	 * @param string $changeFunction The JS apply callback (e.g. applyFilter()).
	 * @param string $clearFunction  The JS clear callback (e.g. clearFilter()).
	 *
	 * @return string
	 */
	private function create_modern_javascript(string $applyFilter, string $changeFunction, string $clearFunction) : string {
		$page     = filter_layouts_page_key($this->form_action != '' ? $this->form_action : get_current_page());
		$can_glob = filter_layouts_can_manage_global();
		$title    = $this->form_header != '' ? $this->form_header : __('Edit Filter');

		$applyLabel = __('Apply');

		$js  = PHP_EOL . "<script type='text/javascript'>" . PHP_EOL;

		$js .= 'var layoutDirty = false;' . PHP_EOL;

		$js .= 'function layoutFilterUrl() {' . PHP_EOL;
		$js .= "\treturn " . $applyFilter . PHP_EOL;
		$js .= '}' . PHP_EOL;

		$js .= 'function layoutSelectedOption() {' . PHP_EOL;
		$js .= "\treturn $('#filter_layout option:selected');" . PHP_EOL;
		$js .= '}' . PHP_EOL;

		$js .= 'function layoutSelectedId() {' . PHP_EOL;
		$js .= "\treturn parseInt($('#filter_layout').val()) || 0;" . PHP_EOL;
		$js .= '}' . PHP_EOL;

		$js .= 'function layoutEditable() {' . PHP_EOL;
		$js .= "\treturn layoutSelectedOption().attr('data-editable') != '0';" . PHP_EOL;
		$js .= '}' . PHP_EOL;

		$js .= 'function layoutPost(layoutAction, extra, onDone) {' . PHP_EOL;
		$js .= "\tvar data = $.extend({ action: layoutAction, page: " . json_encode($page) . ', __csrf_magic: csrfMagicToken }, extra);' . PHP_EOL;
		$js .= "\t$.post(" . json_encode($page) . ", data, function(result) { if (onDone) { onDone(result); } }, 'json').fail(function() { alert(" . json_encode(__('The layout operation failed.')) . '); });' . PHP_EOL;
		$js .= '}' . PHP_EOL;

		$js .= 'function layoutResultOk(r) {' . PHP_EOL;
		$js .= "\tif (r && r.ok) { return true; } alert(" . json_encode(__('The layout operation failed.')) . '); return false;' . PHP_EOL;
		$js .= '}' . PHP_EOL;

		$js .= 'function layoutUpdateButtons() {' . PHP_EOL;
		$js .= "\tvar id = layoutSelectedId();" . PHP_EOL;
		$js .= "\tvar editable = layoutEditable();" . PHP_EOL;
		$js .= "\t$('#layout_rename').closest('.filterColumnButton').toggle(id != 0 && editable);" . PHP_EOL;
		$js .= "\t$('#layout_delete').closest('.filterColumnButton').toggle(id != 0 && editable);" . PHP_EOL;
		$js .= "\t$('#layout_saveas').closest('.filterColumnButton').toggle(id != 0 && !editable);" . PHP_EOL;
		$js .= '}' . PHP_EOL;

		$js .= 'function layoutSetDirty(dirty) {' . PHP_EOL;
		$js .= "\tlayoutDirty = dirty;" . PHP_EOL;
		$js .= "\tvar b = $('#" . $this->form_id . "_dialog').dialog('widget').find('.ui-dialog-buttonpane button.layoutApplyBtn');" . PHP_EOL;
		$js .= "\tif (b.length) { b.button(dirty ? 'disable' : 'enable'); }" . PHP_EOL;
		$js .= '}' . PHP_EOL;

		$js .= 'function layoutDialogSave(forceNew) {' . PHP_EOL;
		$js .= "\tvar name = $('#layout_name').val();" . PHP_EOL;
		$js .= "\tif (name == '') { alert(" . json_encode(__('Please enter a filter name.')) . '); return; }' . PHP_EOL;
		$js .= "\tvar id = (forceNew || !layoutEditable()) ? 0 : layoutSelectedId();" . PHP_EOL;
		$js .= "\tlayoutPost('layout_save', { id: id, name: name, url: layoutFilterUrl() }, function(r) {" . PHP_EOL;
		$js .= "\t\tif (!layoutResultOk(r)) { return; }" . PHP_EOL;
		$js .= "\t\tif (r.id) {" . PHP_EOL;
		$js .= "\t\t\tvar opt = $('#filter_layout option[value=\"' + r.id + '\"]');" . PHP_EOL;
		$js .= "\t\t\tif (opt.length == 0) { opt = $('<option>').appendTo('#filter_layout'); }" . PHP_EOL;
		$js .= "\t\t\topt.val(r.id).text(r.name).attr('data-url', r.url || '').attr('data-name', r.name).attr('data-editable', '1');" . PHP_EOL;
		$js .= "\t\t\t$('#filter_layout').val(r.id);" . PHP_EOL;
		$js .= "\t\t\tlayoutUpdateButtons();" . PHP_EOL;
		$js .= "\t\t}" . PHP_EOL;
		$js .= "\t\tlayoutSetDirty(false);" . PHP_EOL;
		$js .= "\t});" . PHP_EOL;
		$js .= '}' . PHP_EOL;

		$js .= 'function layoutOpenDialog(forceNew) {' . PHP_EOL;
		$js .= "\t$('#layout_name').val(forceNew ? '' : (layoutSelectedOption().attr('data-name') || ''));" . PHP_EOL;
		$js .= "\tvar buttons = [" . PHP_EOL;
		// when a layout is selected (incl. one just saved) apply its own url so filter_layout carries through, otherwise run the plain filter apply
		$js .= "\t\t{ text: " . json_encode($applyLabel) . ", click: function() { if (!layoutDirty) { $(this).dialog('close'); var lu = layoutSelectedOption().attr('data-url'); if (layoutSelectedId() != 0 && lu != undefined && lu != '') { loadUrl({ url: lu }); } else { " . $changeFunction . '; } } } },' . PHP_EOL;
		$js .= "\t\t{ text: " . json_encode(__('Save')) . ', click: function() { layoutDialogSave(forceNew); } },' . PHP_EOL;

		if ($can_glob) {
			$js .= "\t\t{ text: " . json_encode(__('Publish')) . ', click: function() { var id = layoutSelectedId(); if (id == 0 || !layoutEditable()) { alert(' . json_encode(__('Save the layout before publishing it.')) . "); return; } layoutPost('layout_publish', { id: id }, function(r) { if (layoutResultOk(r)) { window.location.reload(); } }); } }," . PHP_EOL;
		}

		$js .= "\t\t{ text: " . json_encode(__('Close')) . ", click: function() { $(this).dialog('close'); } }" . PHP_EOL;
		$js .= "\t];" . PHP_EOL;
		$js .= "\t$('#" . $this->form_id . "_dialog').dialog({ title: " . json_encode($title) . ", modal: false, width: 'auto', minWidth: 500, resizable: false, buttons: buttons });" . PHP_EOL;
		$js .= "\tvar dw = $('#" . $this->form_id . "_dialog').dialog('widget');" . PHP_EOL;
		$js .= "\tdw.addClass('cactiFilterDialog');" . PHP_EOL;
		$js .= "\tdw.find('.ui-dialog-buttonpane button').first().addClass('layoutApplyBtn');" . PHP_EOL;
		$js .= "\tlayoutSetDirty(forceNew || layoutSelectedId() == 0);" . PHP_EOL;
		$js .= '}' . PHP_EOL;

		$js .= 'function layoutRenameDialog(id, cur) {' . PHP_EOL;
		$js .= "\tvar d = $('<div>').append($('<p>').text(" . json_encode(__('Enter a new name for the layout.')) . ")).append($('<input type=\"text\" id=\"layoutRenameInput\" class=\"ui-state-default ui-corner-all\" size=\"40\" maxlength=\"128\">').val(cur));" . PHP_EOL;
		$js .= "\t$('body').append(d);" . PHP_EOL;
		$js .= "\td.dialog({ title: " . json_encode(__('Rename Layout')) . ', modal: true, width: 420, resizable: false, buttons: [' . PHP_EOL;
		$js .= "\t\t{ text: " . json_encode(__('OK')) . ", click: function() { var n = $('#layoutRenameInput').val(); if (n == '') { return; } $(this).dialog('close'); layoutPost('layout_rename', { id: id, name: n }, function(r) { if (layoutResultOk(r)) { window.location.reload(); } }); } }," . PHP_EOL;
		$js .= "\t\t{ text: " . json_encode(__('Cancel')) . ", click: function() { $(this).dialog('close'); } }" . PHP_EOL;
		$js .= "\t], close: function() { $(this).dialog('destroy').remove(); } });" . PHP_EOL;
		$js .= "\t$('#layoutRenameInput').focus().select().keydown(function(e) { if (e.keyCode == 13) { e.preventDefault(); d.dialog('widget').find('.ui-dialog-buttonpane button').first().trigger('click'); } });" . PHP_EOL;
		$js .= '}' . PHP_EOL;

		$js .= 'function layoutDeleteDialog(id) {' . PHP_EOL;
		$js .= "\tvar d = $('<div>').append($('<p>').text(" . json_encode(__('Delete the selected layout?')) . '));' . PHP_EOL;
		$js .= "\t$('body').append(d);" . PHP_EOL;
		$js .= "\td.dialog({ title: " . json_encode(__('Delete Layout')) . ', modal: true, width: 420, resizable: false, buttons: [' . PHP_EOL;
		$js .= "\t\t{ text: " . json_encode(__('Delete')) . ", click: function() { $(this).dialog('close'); layoutPost('layout_delete', { id: id }, function(r) { if (layoutResultOk(r)) { document.location = " . json_encode($page) . '; } }); } },' . PHP_EOL;
		$js .= "\t\t{ text: " . json_encode(__('Cancel')) . ", click: function() { $(this).dialog('close'); } }" . PHP_EOL;
		$js .= "\t], close: function() { $(this).dialog('destroy').remove(); } });" . PHP_EOL;
		$js .= '}' . PHP_EOL;

		$js .= '$(function() {' . PHP_EOL;
		$js .= "\tlayoutUpdateButtons();" . PHP_EOL;
		$js .= "\t$('#filter_layout').change(function() { layoutUpdateButtons(); var u = layoutSelectedOption().attr('data-url'); if (u != undefined && u != '') { loadUrl({ url: u }); } });" . PHP_EOL;
		$js .= "\t$('#" . $this->form_id . "_dialog').on('change keyup', 'input, select, textarea', function() { layoutSetDirty(true); });" . PHP_EOL;
		$js .= "\t$('#layout_edit').click(function() { layoutOpenDialog(false); });" . PHP_EOL;
		$js .= "\t$('#layout_saveas').click(function() { layoutOpenDialog(true); });" . PHP_EOL;
		$js .= "\t$('#layout_refresh').click(function() { $(this).find('i').addClass('icon-rotate'); var u = layoutSelectedOption().attr('data-url'); if (layoutSelectedId() != 0 && u != undefined && u != '') { loadUrl({ url: u }); } else { " . $changeFunction . '; } });' . PHP_EOL;
		$js .= "\t$('#layout_rename').click(function() { var id = layoutSelectedId(); if (id == 0 || !layoutEditable()) { return; } layoutRenameDialog(id, layoutSelectedOption().attr('data-name') || ''); });" . PHP_EOL;
		$js .= "\t$('#layout_delete').click(function() { var id = layoutSelectedId(); if (id == 0 || !layoutEditable()) { return; } layoutDeleteDialog(id); });" . PHP_EOL;
		$js .= "\t$('#layout_name').keydown(function(e) { if (e.keyCode == 13) { e.preventDefault(); layoutDialogSave(false); } });" . PHP_EOL;
		$js .= '});' . PHP_EOL;

		$js .= '</script>' . PHP_EOL;

		return $js;
	}
}

/**
 * Page basenames (the Console > Presets menu) that manage system presets rather
 * than per-user record lists. Saved filter layouts default to off on these.
 *
 * @return array<int, string>
 */
function filter_layouts_preset_pages() : array {
	return [
		'data_source_profiles.php',
		'automation_snmp.php',
		'cdef.php',
		'vdef.php',
		'color.php',
		'gprint_presets.php',
		'layouts.php',
	];
}

/**
 * Reduce a page filename or stored layout url to its bare page basename.
 *
 * @param string $page A page filename or a 'page.php?query' style url.
 *
 * @return string The page basename, e.g. 'host.php'.
 */
function filter_layouts_page_key(string $page) : string {
	$page = trim($page);
	$qpos = strpos($page, '?');

	if ($qpos !== false) {
		$page = substr($page, 0, $qpos);
	}

	return basename($page);
}

/**
 * Whether the current user may publish or manage global (user_id = 0) layouts.
 * This is gated on the Settings/Utilities realm.
 *
 * @return bool
 */
function filter_layouts_can_manage_global() : bool {
	return is_realm_allowed(15);
}

/**
 * The current user's preferred page filter format. Modern renders the Layouts
 * selector with an Edit dialog; legacy renders the classic inline filter.
 * Defaults to modern for CLI and not-yet-authenticated contexts.
 *
 * @return string Either 'modern' or 'legacy'.
 */
function filter_layouts_user_format() : string {
	if (function_exists('read_user_setting') && isset($_SESSION['sess_user_id'])) {
		if (read_user_setting('page_filter_format', 'modern') === 'legacy') {
			return 'legacy';
		}
	}

	return 'modern';
}

/**
 * Whether a bare page basename is a safe, same-site page reference (no scheme,
 * host, query, or path traversal) before a layout url is rebuilt from it.
 *
 * @param string $page Candidate page basename.
 *
 * @return bool
 */
function filter_layouts_valid_page(string $page) : bool {
	return preg_match('/^[a-z0-9_]+\.php$/i', trim($page)) === 1;
}

/**
 * Parse a 'page.php?query' filter url into a versioned layout document. Only the
 * page basename and flat scalar (or scalar-list) request vars are kept; the
 * navigable url is rebuilt server side from this document, so no raw url is ever
 * stored or trusted. The filter_layout re-selection marker is never persisted.
 *
 * @param string $url Full 'page.php?query' filter url.
 *
 * @return array{version:int,page:string,filter:array<string,mixed>}|false
 */
function filter_layouts_build_document(string $url) {
	$url  = trim($url);
	$qpos = strpos($url, '?');
	$page = $qpos !== false ? substr($url, 0, $qpos) : $url;

	// The path must already be a bare same-site page; a scheme, host, or
	// traversal is rejected outright rather than normalized away.
	if (!filter_layouts_valid_page($page)) {
		return false;
	}

	$filter = [];

	if ($qpos !== false) {
		parse_str(substr($url, $qpos + 1), $parsed);

		foreach ($parsed as $key => $value) {
			if ($key === 'filter_layout' || !is_string($key) || preg_match('/^[a-zA-Z0-9_]+$/', $key) !== 1) {
				continue;
			}

			if (is_array($value)) {
				$flat = [];

				foreach ($value as $sub_key => $sub_value) {
					if (is_scalar($sub_value)) {
						$flat[$sub_key] = (string) $sub_value;
					}
				}

				$filter[$key] = $flat;
			} elseif (is_scalar($value)) {
				$filter[$key] = (string) $value;
			}
		}
	}

	if (cacti_sizeof($filter) > 128) {
		return false;
	}

	return [
		'version' => 1,
		'page'    => $page,
		'filter'  => $filter,
	];
}

/**
 * Rebuild the navigable 'page.php?query' url for a layout document.
 *
 * @param array<string,mixed> $document A decoded layout document.
 *
 * @return string The rebuilt url, or '' when the document is invalid.
 */
function filter_layouts_document_url(array $document) : string {
	$page = (isset($document['page']) && is_string($document['page'])) ? $document['page'] : '';

	if (!filter_layouts_valid_page($page)) {
		return '';
	}

	$filter = (isset($document['filter']) && is_array($document['filter'])) ? $document['filter'] : [];
	$query  = http_build_query($filter);

	return $query !== '' ? $page . '?' . $query : $page;
}

/**
 * Decode and validate a stored layout document. Rejects anything that is not a
 * version 1 document naming a safe same-site page, bounding the byte size the
 * same way the plugin query builder bounds its own filter documents.
 *
 * @param mixed $json The stored data column.
 *
 * @return array<string,mixed>|false The decoded document, or false when invalid.
 */
function filter_layouts_decode($json) {
	if (!is_string($json) || $json === '' || strlen($json) > 8192) {
		return false;
	}

	$document = json_decode($json, true);

	if (!is_array($document) || ($document['version'] ?? null) !== 1) {
		return false;
	}

	if (!isset($document['page']) || !is_string($document['page']) || !filter_layouts_valid_page($document['page'])) {
		return false;
	}

	if (isset($document['filter']) && !is_array($document['filter'])) {
		return false;
	}

	return $document;
}

/**
 * Fetch a single layout row by id.
 *
 * @param int $id Layout id.
 *
 * @return array<string,mixed>|false The row, or false when not found.
 */
function filter_layouts_get(int $id) {
	if ($id <= 0) {
		return false;
	}

	$row = db_fetch_row_prepared('SELECT id, user_id, page, name, data FROM user_layouts WHERE id = ?', [$id]);

	return is_array($row) ? $row : false;
}

/**
 * The layouts a user may see on a page: their own plus any published ones.
 *
 * @param string $page    Page filename or url.
 * @param int    $user_id User id, or -1 for the current session user.
 *
 * @return array<int, array<string,mixed>>
 */
function filter_layouts_get_available(string $page, int $user_id = -1) : array {
	if (!db_table_exists('user_layouts')) {
		return [];
	}

	if ($user_id < 0) {
		$user_id = isset($_SESSION['sess_user_id']) ? (int) $_SESSION['sess_user_id'] : 0;
	}

	$page = filter_layouts_page_key($page);

	return db_fetch_assoc_prepared('SELECT id, user_id, page, name, data
		FROM user_layouts
		WHERE page = ?
		AND (user_id = ? OR user_id = 0)
		ORDER BY (user_id = 0), name', [$page, $user_id]);
}

/**
 * Whether the current user may modify a given layout row. Users own their own
 * layouts; global layouts and other users' layouts require the Settings/
 * Utilities realm.
 *
 * @param array<string,mixed>|false $layout Layout row.
 *
 * @return bool
 */
function filter_layouts_user_can_edit($layout) : bool {
	if (!is_array($layout)) {
		return false;
	}

	$user_id = isset($_SESSION['sess_user_id']) ? (int) $_SESSION['sess_user_id'] : 0;

	if ($layout['user_id'] == 0) {
		return filter_layouts_can_manage_global();
	}

	return ($layout['user_id'] == $user_id) || filter_layouts_can_manage_global();
}

/**
 * Create or update a layout. New layouts are owned by the current user; updates
 * are permitted only where filter_layouts_user_can_edit() allows. The posted
 * url is normalized into a stored JSON document rather than persisted verbatim.
 *
 * @param string $name Layout name.
 * @param string $url  Full 'page.php?query' filter url.
 * @param int    $id   Existing layout id to overwrite, or 0 to create.
 *
 * @return array<string,mixed>|false The saved row, or false on error.
 */
function filter_layouts_save(string $name, string $url, int $id = 0) {
	$user_id = isset($_SESSION['sess_user_id']) ? (int) $_SESSION['sess_user_id'] : 0;
	$name    = trim($name);

	$document = filter_layouts_build_document($url);

	if ($document === false) {
		return false;
	}

	$json = json_encode($document);

	if ($json === false || strlen($json) > 8192) {
		return false;
	}

	$save = [];

	if ($id > 0) {
		$existing = filter_layouts_get($id);

		if ($existing === false || !filter_layouts_user_can_edit($existing)) {
			return false;
		}

		$save['id']      = $id;
		$save['user_id'] = $existing['user_id'];

		if ($name == '') {
			$name = $existing['name'];
		}
	} else {
		if ($name == '') {
			return false;
		}

		$save['user_id'] = $user_id;
	}

	$save['page'] = $document['page'];
	$save['name'] = mb_substr($name, 0, 128);
	$save['data'] = $json;

	$saved_id = sql_save($save, 'user_layouts');

	if ($saved_id === false) {
		return false;
	}

	return filter_layouts_get($saved_id);
}

/**
 * Rename a layout.
 *
 * @param int    $id   Layout id.
 * @param string $name New name.
 *
 * @return bool
 */
function filter_layouts_rename(int $id, string $name) : bool {
	$layout = filter_layouts_get($id);
	$name   = trim($name);

	if ($layout === false || !filter_layouts_user_can_edit($layout) || $name == '') {
		return false;
	}

	db_execute_prepared('UPDATE user_layouts SET name = ? WHERE id = ?', [mb_substr($name, 0, 128), $id]);

	return true;
}

/**
 * Delete a layout.
 *
 * @param int $id Layout id.
 *
 * @return bool
 */
function filter_layouts_delete(int $id) : bool {
	$layout = filter_layouts_get($id);

	if ($layout === false || !filter_layouts_user_can_edit($layout)) {
		return false;
	}

	db_execute_prepared('DELETE FROM user_layouts WHERE id = ?', [$id]);

	return true;
}

/**
 * Publish a layout to all users by moving it to user_id = 0. Requires the
 * Settings/Utilities realm.
 *
 * @param int $id Layout id.
 *
 * @return bool
 */
function filter_layouts_publish(int $id) : bool {
	if (!filter_layouts_can_manage_global()) {
		return false;
	}

	$layout = filter_layouts_get($id);

	if ($layout === false) {
		return false;
	}

	db_execute_prepared('UPDATE user_layouts SET user_id = 0 WHERE id = ?', [$id]);

	return true;
}

/**
 * Unpublish a global layout by assigning it to a specific user. Requires the
 * Settings/Utilities realm.
 *
 * @param int $id      Layout id.
 * @param int $user_id Target owner user id.
 *
 * @return bool
 */
function filter_layouts_unpublish(int $id, int $user_id) : bool {
	if (!filter_layouts_can_manage_global()) {
		return false;
	}

	$layout = filter_layouts_get($id);

	if ($layout === false || $user_id <= 0) {
		return false;
	}

	db_execute_prepared('UPDATE user_layouts SET user_id = ? WHERE id = ?', [$user_id, $id]);

	return true;
}

/**
 * Emit a JSON response for a layout request and end the request.
 *
 * @param array<string,mixed> $data Response payload.
 *
 * @return never
 */
function filter_layouts_json(array $data) {
	header('Content-type: application/json');

	print json_encode($data);

	exit;
}

/**
 * Handle a saved-filter-layout AJAX request posted to any page. Invoked once
 * per request from include/auth.php after the user has been authenticated and
 * authorized for the current page, so every user can manage their own layouts
 * from any filter while publishing stays gated on the Settings/Utilities realm.
 *
 * Only POST requests are acted on (Cacti validates the CSRF token on POST).
 * Non-layout requests return immediately so normal page loads are unaffected.
 *
 * @return void
 */
function filter_layouts_handle_request() : void {
	if (!isset($_SERVER['REQUEST_METHOD']) || strtoupper($_SERVER['REQUEST_METHOD']) !== 'POST') {
		return;
	}

	if (!isset_request_var('action')) {
		return;
	}

	$action = get_nfilter_request_var('action');

	$id = isset_request_var('id') ? (int) get_nfilter_request_var('id') : 0;

	switch ($action) {
		case 'layout_save':
			$name = isset_request_var('name') ? get_nfilter_request_var('name') : '';
			$url  = isset_request_var('url') ? get_nfilter_request_var('url') : '';

			$row = filter_layouts_save($name, $url, $id);

			if ($row === false) {
				$result = ['ok' => false];
			} else {
				$document = filter_layouts_decode($row['data']);
				$nav      = $document !== false ? filter_layouts_document_url($document) : '';
				$sep      = (strpos($nav, '?') !== false) ? '&' : '?';

				$result = [
					'ok'   => true,
					'id'   => (int) $row['id'],
					'name' => $row['name'],
					'url'  => $nav . $sep . 'filter_layout=' . $row['id'],
				];
			}

			break;
		case 'layout_rename':
			$name = isset_request_var('name') ? get_nfilter_request_var('name') : '';

			$result = ['ok' => filter_layouts_rename($id, $name)];

			break;
		case 'layout_delete':
			$result = ['ok' => filter_layouts_delete($id)];

			break;
		case 'layout_publish':
			$result = ['ok' => filter_layouts_publish($id)];

			break;
		case 'layout_unpublish':
			$user_id = isset_request_var('user_id') ? (int) get_nfilter_request_var('user_id') : 0;

			$result = ['ok' => filter_layouts_unpublish($id, $user_id)];

			break;
		default:
			return;
	}

	filter_layouts_json($result);
}

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
  | Cacti: The Complete RRDTool-based Graphing Solution                     |
  +-------------------------------------------------------------------------+
  | This code is designed, written, and maintained by the Cacti Group. See  |
  | about.php and/or the AUTHORS file for specific developer information.   |
  +-------------------------------------------------------------------------+
  | http://www.cacti.net/                                                   |
  +-------------------------------------------------------------------------+
*/

// Host Autocomplete Magic
var pageName = basename($(location).attr('pathname'));

function themeReady() {
	$('body').css('height', $(window).height());

	// Setup the navigation menu
	setMenuVisibility();

	// Wait until filter inputs have been made visible.
	setTimeout(addFilterSearchIcons, 0);

	$('input#filter, input#rfilter').addClass('ui-state-default ui-corner-all');

	$('input[type="text"], input[type="password"], input[type="checkbox"], textarea').not('image').addClass('ui-state-default ui-corner-all');

	$('.checkboxgroup').children('br').remove();
	$('.checkboxgroup').buttonset();

	// Turn file buttons into jQueryUI buttons
	$('.import_label').button();
	$('.import_button').change(function() {
		var text = this.value;
		setImportFile(text);
	});
	setImportFile(noFileSelected);

	function setImportFile(fileText) {
		$('.import_text').text(fileText);
	}

	var maxWidth = 300;

	$('select.colordropdown').dropcolor();

	/* jQuery UI selectmenu is superseded by select2, applied later in applySkin();
	 * don't double-widgetize every select here, just keep multi-selects' outline */
	$('select').not('.colordropdown').filter(function() {
		return $(this).prop('multiple') === true;
	}).addClass('ui-state-default ui-corner-all');

	$('#drp_action').change(function() {
		if ($(this).val() != '0') {
			$('#submit').button('enable');
		} else {
			$('#submit').button('disable');
		}
	});

	$('#graph_type_id').change(function() {
		switch($(this).val()) {
		case '4':
		case '5':
		case '6':
		case '7':
		case '8':
			$('#alpha').prop('disabled', false);
		}
	});

	setHostAutocomplete();

	setNavigationScroll();
}

/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2013 The Cacti Group                                 |
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

/* graph template stuff */
function gt_update_selection_indicators() {
	there_are_any_unchecked_ones = false;

	for (var j = 0; j < document.chk.elements.length; j++) {
		if (document.chk.elements[j].name.substr(0,3) == 'cg_') {
			if (document.chk.elements[j].checked == false) {
				there_are_any_unchecked_ones = true;
			}

			if (!isNaN(document.chk.elements[j].name.substr(3))) {
				lineid = document.getElementById('gt_line' + document.chk.elements[j].name.substr(3));

				if (document.chk.elements[j].checked) {
					lineid.style.backgroundColor = 'khaki';
				}else{
					lineid.style.backgroundColor = '';
				}
			}
		}
	}
}

function gt_select_line(graph_template_id, update) {
	if (gt_is_disabled(graph_template_id)) { return; }

	msgid = document.getElementById('cg_' + graph_template_id);
	if (!update) msgid.checked = !msgid.checked;
	gt_update_selection_indicators();
}

function gt_is_disabled(graph_template_id) {
	for (var i = 0; i < gt_created_graphs.length; i++) {
		if (gt_created_graphs[i] == graph_template_id) {
			return true;
		}
	}

	return false;
}

function gt_update_deps(num_columns) {
	gt_reset_deps(num_columns);

	for (var i = 0; i < gt_created_graphs.length; i++) {
		for (var j = 0; j < num_columns; j++) {
			lineid = document.getElementById('gt_text' + gt_created_graphs[i] + '_' + j);
			if (lineid) {
				lineid.style.color = '#999999';
			}
		}

		chkbx = document.getElementById('cg_' + gt_created_graphs[i]);
		chkbx.style.visibility = 'hidden';
		chkbx.checked = false;

		lineid = document.getElementById('gt_line' + gt_created_graphs[i]);
		if (lineid) {
			lineid.style.backgroundColor = '';
		}
	}
}

function gt_reset_deps(num_columns) {
	var prefix = 'cg_'

	for (var i = 0; i < document.chk.elements.length; i++) {
		if (document.chk.elements[i].name.substr( 0, prefix.length ) == prefix) {
			for (var j = 0; j < num_columns; j++) {
				lineid = document.getElementById('gt_text' + document.chk.elements[i].name.substr(prefix.length) + '_' + j);
				if (lineid) {
					lineid.style.color = '#000000';
				}
			}

			chkbx = document.getElementById('cg_' + document.chk.elements[i].name.substr(prefix.length));
			chkbx.style.visibility = 'visible';
		}
	}
}

/* general id based selects */
function update_selection_indicators() {
	there_are_any_unchecked_ones = false;

	for (var j = 0; j < document.chk.elements.length; j++) {
		if( document.chk.elements[j].name.substr( 0, 4 ) == 'chk_') {
			if (document.chk.elements[j].checked == false) {
				there_are_any_unchecked_ones = true;
			}

			lineid = document.getElementById('line'+ document.chk.elements[j].name.substr(4));
			if (lineid) {
				if (document.chk.elements[j].checked) {
					lineid.style.backgroundColor = 'khaki';
				}else{
					lineid.style.backgroundColor = '';
				}
			}
		}
	}
}

function select_line(id, update) {
	msgid  = document.getElementById('chk_' + id);
	if (!update) msgid.checked = !msgid.checked;
	update_selection_indicators();
}

/* data query stuff */
function dq_update_selection_indicators() {
	there_are_any_unchecked_ones = false;

	for (var j = 0; j < document.chk.elements.length; j++) {
		if( document.chk.elements[j].name.substr( 0, 3 ) == 'sg_') {
			if (document.chk.elements[j].checked == false) {
				there_are_any_unchecked_ones = true;
			}

			lineid = document.getElementById('line'+ document.chk.elements[j].name.substr(3));
			if (lineid) {
				if (document.chk.elements[j].checked) {
					lineid.style.backgroundColor = 'khaki';
				}else{
					lineid.style.backgroundColor = '';
				}
			}
		}
	}
}

function dq_select_line(snmp_query_id, snmp_index, update) {
	if (dq_is_disabled(snmp_query_id, snmp_index)) { return; }

	msgid  = document.getElementById('sg_' + snmp_query_id + '_' + snmp_index);
	lineid = document.getElementById('line'+ snmp_query_id + '_' + snmp_index);

	if (!update) msgid.checked = !msgid.checked;

	dq_update_selection_indicators();
}

function dq_is_disabled(snmp_query_id, snmp_index) {
	dropdown = document.getElementById('sgg_' + snmp_query_id);
	if(dropdown == null){
		return false;
	}
	var snmp_query_graph_id = dropdown.value

	for (var i = 0; i < created_graphs[snmp_query_graph_id].length; i++) {
		if (created_graphs[snmp_query_graph_id][i] == snmp_index) {
			return true;
		}
	}

	return false;
}

function dq_update_deps(snmp_query_id, num_columns) {
	dq_reset_deps(snmp_query_id, num_columns);

	dropdown = document.getElementById('sgg_' + snmp_query_id);
	if(dropdown == null){
		return;
	}
	var snmp_query_graph_id = dropdown.value

	for (var i = 0; i < created_graphs[snmp_query_graph_id].length; i++) {
		for (var j = 0; j < num_columns; j++) {
			lineid = document.getElementById('text' + snmp_query_id + '_' + created_graphs[snmp_query_graph_id][i] + '_' + j);
			if ( lineid ) { lineid.style.color = '#999999' };
		}

		chkbx = document.getElementById('sg_' + snmp_query_id + '_' + created_graphs[snmp_query_graph_id][i]);
		if ( chkbx ) {
			chkbx.style.visibility = 'hidden';
			chkbx.checked          = false;
		}

		lineid = document.getElementById('line' + snmp_query_id + '_' + created_graphs[snmp_query_graph_id][i]);
		if ( lineid ) { lineid.style.backgroundColor = '' };
	}
}

function dq_reset_deps(snmp_query_id, num_columns) {
	var prefix = 'sg_' + snmp_query_id + '_'

	for (var i = 0; i < document.chk.elements.length; i++) {
		if (document.chk.elements[i].name.substr( 0, prefix.length ) == prefix) {
			for (var j = 0; j < num_columns; j++) {
				lineid = document.getElementById('text' + snmp_query_id + '_' + document.chk.elements[i].name.substr(prefix.length) + '_' + j);
				lineid.style.color = '#000000';
			}

			chkbx = document.getElementById('sg_' + snmp_query_id + '_' + document.chk.elements[i].name.substr(prefix.length));
			chkbx.style.visibility = 'visible';
		}
	}
}

function SelectAll(prefix, checkbox_state) {
	for (var i = 0; i < document.chk.elements.length; i++) {
		if ((document.chk.elements[i].name.substr(0, prefix.length) == prefix) && (document.chk.elements[i].style.visibility != 'hidden')) {
			document.chk.elements[i].checked = checkbox_state;
		}

		if (prefix == "chk_") {
			lineid = document.getElementById('line'+ document.chk.elements[i].name.substr(4));
			if (lineid) {
				if (document.chk.elements[i].checked) {
					if ( lineid ) { lineid.style.backgroundColor = 'khaki'; }
				}else{
					if ( lineid ) { lineid.style.backgroundColor = ''; }
				}
			}
		}
	}

}

function SelectAllGraphs(prefix, checkbox_state) {
	for (var i = 0; i < document.graphs.elements.length; i++) {
		if ((document.graphs.elements[i].name.substr(0, prefix.length) == prefix) && (document.graphs.elements[i].style.visibility != 'hidden')) {
			document.graphs.elements[i].checked = checkbox_state;
		}
	}
}

/* calendar stuff */
// Initialize the calendar
var calendar=null;

// This function displays the calendar associated to the input field 'id'
function showCalendar(id) {
	var el = document.getElementById(id);
	if (calendar != null) {
		// we already have some calendar created
		calendar.hide();  // so we hide it first.
	} else {
		// first-time call, create the calendar.
		var cal = new Calendar(true, null, selected, closeHandler);
		cal.weekNumbers = false;  // Do not display the week number
		cal.showsTime = true;     // Display the time
		cal.time24 = true;        // Hours have a 24 hours format
		cal.showsOtherMonths = false;    // Just the current month is displayed
		calendar = cal;                  // remember it in the global var
		cal.setRange(1900, 2070);        // min/max year allowed.
		cal.create();
	}

	calendar.setDateFormat('%Y-%m-%d %H:%M');    // set the specified date format
	calendar.parseDate(el.value);                // try to parse the text in field
	calendar.sel = el;                           // inform it what input field we use

	// Display the calendar below the input field
	calendar.showAtElement(el, "Br");        // show the calendar

	return false;
}

// This function update the date in the input field when selected
function selected(cal, date) {
	cal.sel.value = date;      // just update the date in the input field.
}

// This function gets called when the end-user clicks on the 'Close' button.
// It just hides the calendar without destroying it.
function closeHandler(cal) {
	cal.hide();                        // hide the calendar
	calendar = null;
}

/* graph filtering */
function applyTimespanFilterChange(objForm) {
	strURL = '?predefined_timespan=' + objForm.predefined_timespan.value;
	strURL = strURL + '&predefined_timeshift=' + objForm.predefined_timeshift.value;
	document.location = strURL;
}

function applyGraphPreviewFilterChange(objForm) {
	strURL = '?action=preview';
	strURL = strURL + '&host_id=' + objForm.host_id.value;
	strURL = strURL + '&rows=' + objForm.rows.value;
	strURL = strURL + '&graph_template_id=' + objForm.graph_template_id.value;
	strURL = strURL + '&filter=' + objForm.filter.value;
	document.location = strURL;
}

function applyGraphListFilterChange(objForm) {
	strURL = 'graph_view.php?action=list&page=1';
	strURL = strURL + '&host_id=' + objForm.host_id.value;
	strURL = strURL + '&rows=' + objForm.rows.value;
	strURL = strURL + '&graph_template_id=' + objForm.graph_template_id.value;
	strURL = strURL + '&filter=' + objForm.filter.value;
	strURL = strURL + url_graph('');
	document.location = strURL;
	return false;
}

function cactiReturnTo(location) {
	if (location != "") {
		document.location = location;
	}else{
		document.history.back();
	}
}

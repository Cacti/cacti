/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2007 The Cacti Group                                 |
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
	if (document.getElementById) {
		there_are_any_unchecked_ones = false;

		for (var j = 0; j < document.chk.elements.length; j++) {
			if (document.chk.elements[j].name.substr(0,3) == 'cg_') {
				if (document.chk.elements[j].checked == false) {
					there_are_any_unchecked_ones = true;
				}

				if (!isNaN(document.chk.elements[j].name.substr(3))) {
					lineid = document.getElementById('gt_line' + document.chk.elements[j].name.substr(3));

					if (document.chk.elements[j].checked) {
						lineid.style.backgroundColor = 'gold';
					}else{
						lineid.style.backgroundColor = '';
					}
				}
			}
		}
	}
}

function gt_select_line(graph_template_id, update) {
	if (gt_is_disabled(graph_template_id)) { return; }

	if (document.getElementById) {
		msgid = document.getElementById('cg_' + graph_template_id);
		lineid = document.getElementById('gt_line'+ graph_template_id);

		if (!update) msgid.checked = !msgid.checked;

		gt_update_selection_indicators();
	}
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
			lineid.style.color = '999999';
		}

		chkbx = document.getElementById('cg_' + gt_created_graphs[i]);
		chkbx.style.visibility = 'hidden';
		chkbx.checked = false;

		lineid = document.getElementById('gt_line' + gt_created_graphs[i]);
		lineid.style.backgroundColor = '';
	}
}

function gt_reset_deps(num_columns) {
	var prefix = 'cg_'

	for (var i = 0; i < document.chk.elements.length; i++) {
		if (document.chk.elements[i].name.substr( 0, prefix.length ) == prefix) {
			for (var j = 0; j < num_columns; j++) {
				lineid = document.getElementById('gt_text' + document.chk.elements[i].name.substr(prefix.length) + '_' + j);
				lineid.style.color = '000000';
			}

			chkbx = document.getElementById('cg_' + document.chk.elements[i].name.substr(prefix.length));
			chkbx.style.visibility = 'visible';
		}
	}
}

/* data query stuff */
function dq_update_selection_indicators() {
	if (document.getElementById) {
		there_are_any_unchecked_ones = false;

		for (var j = 0; j < document.chk.elements.length; j++) {
			if( document.chk.elements[j].name.substr( 0, 3 ) == 'sg_') {
				if (document.chk.elements[j].checked == false) {
					there_are_any_unchecked_ones = true;
				}

				lineid = document.getElementById('line'+ document.chk.elements[j].name.substr(3));

				if (document.chk.elements[j].checked) {
					lineid.style.backgroundColor = 'gold';
				}else{
					lineid.style.backgroundColor = '';
				}
			}
		}
	}
}

function dq_select_line(snmp_query_id, snmp_index, update) {
	if (dq_is_disabled(snmp_query_id, snmp_index)) { return; }

	if (document.getElementById) {
		msgid = document.getElementById('sg_' + snmp_query_id + '_' + snmp_index);
		lineid = document.getElementById('line'+ snmp_query_id + '_' + snmp_index);

		if (!update) msgid.checked = !msgid.checked;

		dq_update_selection_indicators();
	}
}

function dq_is_disabled(snmp_query_id, snmp_index) {
	dropdown = document.getElementById('sgg_' + snmp_query_id);
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
	var snmp_query_graph_id = dropdown.value

	for (var i = 0; i < created_graphs[snmp_query_graph_id].length; i++) {
		for (var j = 0; j < num_columns; j++) {
			lineid = document.getElementById('text' + snmp_query_id + '_' + created_graphs[snmp_query_graph_id][i] + '_' + j);
			if (lineid) { lineid.style.color = '999999' };
		}

		chkbx = document.getElementById('sg_' + snmp_query_id + '_' + created_graphs[snmp_query_graph_id][i]);
		if ( chkbx ) {
			chkbx.style.visibility = 'hidden';
			chkbx.checked = false;
		}

		lineid = document.getElementById('line' + snmp_query_id + '_' + created_graphs[snmp_query_graph_id][i]);
		if (lineid) { lineid.style.backgroundColor = '' };
	}
}

function dq_reset_deps(snmp_query_id, num_columns) {
	var prefix = 'sg_' + snmp_query_id + '_'

	for (var i = 0; i < document.chk.elements.length; i++) {
		if (document.chk.elements[i].name.substr( 0, prefix.length ) == prefix) {
			for (var j = 0; j < num_columns; j++) {
				lineid = document.getElementById('text' + snmp_query_id + '_' + document.chk.elements[i].name.substr(prefix.length) + '_' + j);
				lineid.style.color = '000000';
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
	}
}

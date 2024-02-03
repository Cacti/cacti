/*
  +-------------------------------------------------------------------------+
  | Copyright (C) 2004-2024 The Cacti Group                                 |
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
	height = get_height();
	$('#navigation, .cactiConsoleNavigationArea').css('height', height);
	$('#navigation, #navigation_right').show();

	keepWindowSize();

	$(window).unbind().resize(function(event) {
		if (pageName == 'graph_view.php') {
			treeWidth    = $('#navigation').width();
			totalWidth   = $('body').width();
			contentWidth = totalWidth - treeWidth - 25;
			$('#navigation').css('width', treeWidth);
			$('#navigation_right').css('width', contentWidth);
		}

		if (!$(event.target).hasClass('ui-resizable')) {
			height = get_height();
			$('#navigation, .cactiConsoleNavigationArea').css('height', height);
		}
	});
}

function get_height() {
	nsh  = parseInt($('#navigation').prop('scrollHeight'));
	nrsh = parseInt($('#navigation_right').prop('scrollHeight'));
	nh   = parseInt($('#navigation').height());
	nrh  = parseInt($('#navigation_right').height());
	wht  = $(window).height();
	return Math.max(nsh, nrsh, nh, nrh, wht);
}

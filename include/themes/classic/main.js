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
	var height = get_height();
	$('#navigation, .cactiConsoleNavigationArea').css('height', height);
	$('#navigation, #navigation_right').show();

	// applySkin() adds the core resize handler itself; only replace this theme's own handler
	$(window).off('resize.classic').on('resize.classic', function(event) {
		var resizable = $(event.target).hasClass('ui-resizable');

		waitForFinalEvent(function() {
			if (pageName == 'graph_view.php') {
				var treeWidth    = $('#navigation').width();
				var totalWidth   = $('body').width();
				var contentWidth = totalWidth - treeWidth - 25;
				$('#navigation').css('width', treeWidth);
				$('#navigation_right').css('width', contentWidth);
			}

			if (!resizable) {
				var height = get_height();
				$('#navigation, .cactiConsoleNavigationArea').css('height', height);
			}
		}, 50, 'classic_resize');
	});
}

function get_height() {
	// measure the content, not the height set by the previous call, so the panes can shrink again
	$('#navigation, .cactiConsoleNavigationArea').css('height', '');

	var nsh  = parseInt($('#navigation').prop('scrollHeight'));
	var nrsh = parseInt($('#navigation_right').prop('scrollHeight'));
	var nh   = parseInt($('#navigation').height());
	var nrh  = parseInt($('#navigation_right').height());
	var wht  = $(window).height();
	return Math.max(nsh, nrsh, nh, nrh, wht);
}

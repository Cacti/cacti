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
	var hostTimer = false;
	var clickTimeout = false;
	var hostOpen = false;

	$('body').css('height', $(window).height());
	$('.formItemDescription').hide();

	keepWindowSize();

	// Setup the navigation menu
	setMenuVisibility();

	// Add nice search filter to filters
	if ($('input[id="filter"]').length > 0 && $('input[id="filter"] > i[class="fa fa-search filter"]').length < 1) {
		$('input[id="filter"]').after("<i class='fa fa-search filter'/>").attr('autocomplete', 'off').attr('placeholder', searchFilter).parent('td').css('white-space', 'nowrap');
	}

	if ($('input[id="filterd"]').length > 0 && $('input[id="filterd"] > i[class="fa fa-search filter"]').length < 1) {
		$('input[id="filterd"]').after("<i class='fa fa-search filter'/>").attr('autocomplete', 'off').attr('placeholder', searchFilter).parent('td').css('white-space', 'nowrap');
	}

	if ($('input[id="rfilter"]').length > 0 && $('input[id="rfilter"] > i[class="fa fa-search filter"]').length < 1) {
		$('input[id="rfilter"]').after("<i class='fa fa-search filter'/>").attr('autocomplete', 'off').attr('placeholder', searchRFilter).parent('td').css('white-space', 'nowrap');
	}

	$('input#filter, input#rfilter').addClass('ui-state-default ui-corner-all');

	$('input[type="text"], input[type="password"], input[type="checkbox"], textarea').not('image').addClass('ui-state-default ui-corner-all');

	$('.colordropdown').change(function() {
		id=$(this).attr('id');
		color=$('#'+id+' option:selected').attr('data-color');
		$('<span>', {
			style: 'background-color:#'+color+',width:16px;height:16px;',
			'class': 'color-icon'
		}).appendTo($('#'+id+'-button'));
	});

	$('.checkboxgroup').children('br').remove();
	$('.checkboxgroup').buttonset();

	// Turn file buttons into jQueryUI buttons
	$('.import_label').button();
	$('.import_button').change(function() {
		text=this.value;
		setImportFile(text);
	});
	setImportFile(noFileSelected);

	function setImportFile(fileText) {
		$('.import_text').text(fileText);
	}

	maxWidth = 300;

	$('select.colordropdown').dropcolor();

	$('select').not('.colordropdown').each(function() {
		if ($(this).prop('multiple') != true) {
			$(this).each(function() {
				id = $(this).attr('id');

				$(this).selectmenu({
					open: function(event, ui) {
						var instance = $(this).selectmenu('instance');
						instance.menuInstance.focus(null, instance._getSelectedItem());
					},
					change: function(event, ui) {
						$(this).val(ui.item.value).change();
					},
					position: {
						my: "left top",
						at: "left bottom",
						collision: "flip"
					},
					width: 'auto'
				});

				$('#'+id+'-menu').css('max-height', '250px');
			});
		} else {
			$(this).addClass('ui-state-default ui-corner-all');
		}
	});

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
			$('#alpha').selectmenu('enable');
		}
	});

	$('#host').unbind().autocomplete({
		source: pageName+'?action=ajax_hosts',
		autoFocus: true,
		minLength: 0,
		select: function(event,ui) {
			$('#host_id').val(ui.item.id);

			var callBack = $('#call_back').val();

			if (callBack != 'undefined') {
				callBack = callBack.replace('(', '').replace(')', '');

				executeFunctionByName(callBack, window);
			} else if (typeof applyGraphFilter === 'function') {
				applyGraphFilter();
			} else {
				applyFilter();
			}
		}
	}).addClass('ui-state-default ui-selectmenu-text').css('border', 'none').css('background-color', 'transparent');

	$('#host_click').css('z-index', '4');
	$('#host_wrapper').unbind().dblclick(function() {
		hostOpen = false;
		clearTimeout(hostTimer);
		clearTimeout(clickTimeout);
		$('#host').autocomplete('close').select();
	}).click(function() {
		if (hostOpen) {
			$('#host').autocomplete('close');
			clearTimeout(hostTimer);
			hostOpen = false;
		} else {
			clickTimeout = setTimeout(function() {
				$('#host').autocomplete('search', '');
				clearTimeout(hostTimer);
				hostOpen = true;
			}, 200);
		}
		$('#host').select();
	}).on('mouseenter', function() {
		$(this).addClass('ui-state-hover');
		$('input#host').addClass('ui-state-hover');
	}).on('mouseleave', function() {
		$(this).removeClass('ui-state-hover');
		$('#host').removeClass('ui-state-hover');
		hostTimer = setTimeout(function() { $('#host').autocomplete('close'); }, 800);
		hostOpen = false;
	});

	var hostPrefix = '';
	$('#host').autocomplete('widget').each(function() {
		hostPrefix=$(this).attr('id');

		if (hostPrefix != '') {
			$('ul[id="'+hostPrefix+'"]').on('mouseenter', function() {
				clearTimeout(hostTimer);
			}).on('mouseleave', function() {
				hostTimer = setTimeout(function() { $('#host').autocomplete('close'); }, 800);
				$(this).removeClass('ui-state-hover');
				$('input#host').removeClass('ui-state-hover');
			});
		}
	});

	// Hide the graph icons until you hover
	$('.graphDrillDown').hover(
	function() {
		element = $(this);

		// hide the previously shown element
		if (element.attr('id').replace('dd', '') != graphMenuElement && graphMenuElement > 0) {
			$('#dd'+graphMenuElement).find('.iconWrapper:first').hide(300);
		}

		clearTimeout(graphMenuTimer);
		graphMenuTimer = setTimeout(function() { showGraphMenu(element); }, 400);
	},
	function() {
		element = $(this);
		clearTimeout(graphMenuTimer);
		graphMenuTimer = setTimeout(function() { hideGraphMenu(element); }, 400);
	});

	function showGraphMenu(element) {
		element.find('.spikekillMenu').menu('disable');
		element.find('.iconWrapper').show(300, function() {
			graphMenuElement = element.attr('id').replace('dd', '');;
			$(this).find('.spikekillMenu').menu('enable');
		});
	}

	function hideGraphMenu(element) {
		element.find('.spikekillMenu').menu('disable');
		element.find('.iconWrapper').hide(300, function() {
			$(this).find('.spikekillMenu').menu('enable');
		});
	}

	setNavigationScroll();
}

function setMenuVisibility() {
	storage=Storages.localStorage;

	// Initialize the navigation settings
	// This will setup the initial visibility of the menu
	$('li.menuitem').each(function() {
		var id = $(this).attr('id');

		if (storage.isSet(id)) {
			var active = storage.get(id);
		} else {
			var active = null;
		}

		if (active != null && active == 'active') {
			$(this).find('ul').attr('aria-hidden', 'false').attr('aria-expanded', 'true').show();
			$(this).next('a').show();
		} else {
			$(this).find('ul').attr('aria-hidden', 'true').attr('aria-expanded', 'false').hide();
			$(this).next('a').hide();
		}

		if ($(this).find('a.selected').length == 0) {
			$(this).find('ul').attr('aria-hidden', 'true').attr('aria-expanded', 'false').hide();
			$(this).next('a').hide();
			storage.set($(this).closest('.menuitem').attr('id'), 'collapsed');
		} else {
			$(this).find('ul').attr('aria-hidden', 'false').attr('aria-expanded', 'true').show();
			$(this).next('a').show();
			storage.set($(this).closest('.menuitem').attr('id'), 'active');
		}
	});

	// Function to give life to the Navigation pane
	$('#nav li:has(ul) a.active').unbind().click(function(event) {
		event.preventDefault();

		id = $(this).closest('.menuitem').attr('id');

		if ($(this).next().is(':visible')) {
			$(this).next('ul').attr('aria-hidden', 'true').attr('aria-expanded', 'false');
			$(this).next().slideUp( { duration: 200, easing: 'swing' } );
			storage.set(id, 'collapsed');
		} else {
			$(this).next('ul').attr('aria-hidden', 'false').attr('aria-expanded', 'true');
			$(this).next().slideToggle( { duration: 200, easing: 'swing' } );
			if ($(this).next().is(':visible')) {
				storage.set($(this).closest('.menuitem').attr('id'), 'active');
			} else {
				storage.set(id, 'collapsed');
			}
		}

		$('li.menuitem').not('#'+id).each(function() {
			text = $(this).attr('id');
			id   = $(this).attr('id');

			$(this).find('ul').attr('aria-hidden', 'true').attr('aria-expanded', 'false');
			$(this).find('ul').slideUp( { duration: 200, easing: 'swing' } );
			storage.set($(this).attr('id'), 'collapsed');
		});
	});
}

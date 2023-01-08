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

function themeReady() {
	// Setup the navigation menu
	setMenuVisibility();

	$('#navigation_right').unbind().scroll(function (event) {
        var scroll_position_x = $('#navigation_right').scrollLeft();
        var scroll_position_y = $('#navigation_right').scrollTop();
        $('.bottom_scroll_up').css({'color': ((scroll_position_x == 0 & scroll_position_y == 0) ? '' : '#93CEFF') });
    });

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

	/* Start clean up */

	//login page
	$('.cactiLoginLogo').html("<i class='fa fa-sun-o'/>");
	$('.cactiLogoutLogo').html("<i class='fa fa-sun-o'/>");

	/* clean up the navigation menu */
	$('.cactiConsoleNavigationArea').find('#menu').appendTo($('.cactiConsoleNavigationArea').find('#navigation'));
	$('.cactiConsoleNavigationArea').find('#navigation > table').remove();

	/* Hey - No footer available ? */
	if ($('#cactiPageBottom').length == 0) {
		$('<div id="cactiPageBottom" class="cactiPageBottom"><span class="cactiVersion">Version '+ cactiVersion +'</span><a class="bottom_scroll_up action-icon-user" href="#"><i class="fa fa-arrow-circle-up"></i></a></div>').insertAfter('#cactiContent');
	}

	$('.maintabs nav ul li a.lefttab').each( function() {
		id = $(this).attr('id');
		if (id == 'tab-graphs' && $(this).parent().hasClass('maintabs-has-submenu') == 0) {
			$(this).parent().addClass('maintabs-has-submenu');
			$('<div class="dropdownMenu">'
				+'<ul id="submenu-tab-graphs" class="submenuoptions" style="display:none;">'
					+'<li><a id="tab-graphs-tree-view" href="'+urlPath+'graph_view.php?action=tree"><span>'+treeView+'</span></a></li>'
					+'<li><a id="tab-graphs-list-view" href="'+urlPath+'graph_view.php?action=list"><span>'+listView+'</span></a></li>'
					+'<li><a id="tab-graphs-pre-view" href="'+urlPath+'graph_view.php?action=preview"><span>'+previewView+'</span></a></li>'
				+'</ul>'
			+'</div>').appendTo('body');
		}
	});

	/* user menu on the right ... */
	if ($('.usertabs').length == 0) {
		$('.loggedInAs').show();
		$('#userDocumentation').remove();
		$('#userCommunity').remove();
		$('.menuHr').remove();
		$('<div class="maintabs usertabs">'
			+'<nav><ul>'
				+'<li><a id="menu-user-help" class="usertabs-submenu" href="#"><i class="fa fa-question"></i></a></li>'
				+'<li class="action-icon-user"><a class="pic" href="#"><i class="fa fa-user"></i></a></li>'
			+'</ul></nav>'
		+'</div>').insertAfter('.maintabs');

		$('<div class="dropdownMenu">'
			+'<ul id="submenu-user-help" class="submenuoptions right" style="display:none;">'
				+'<li><a href="https://www.cacti.net" target="_blank" rel="noopener"><span>'+cactiHome+'</span></a></li>'
				+'<li><a href="https://github.com/cacti" target="_blank" rel="noopener"><span>'+cactiProjectPage+'</span></a></li>'
				+'<li><hr class="menu"></li>'
				+'<li><a href="https://forums.cacti.net/" target="_blank" rel="noopener"><span>'+cactiCommunityForum+'</span></a></li>'
				+'<li><a href="https://github.com/Cacti/documentation/blob/develop/README.md" target="_blank" rel="noopener"><span>'+cactiDocumentation+'</span></a></li>'
				+'<li><hr class="menu"></li>'
				+'<li><a href="https://github.com/Cacti/cacti/issues/new" target="_blank" rel="noopener"><span>'+reportABug+'</span></a></li>'
				+'<li><a href="'+urlPath+'about.php"><span>'+aboutCacti+'</span></a></li>'
			+'</ul>'
		+'</div>').appendTo('body');
	}

	ajaxAnchors();

	/* User Menu */
	$('.menuoptions').parent().appendTo('body');

	$('.action-icon-user').unbind().click(function(event) {
		event.preventDefault();

		if ($('.menuoptions').is(':visible') === false) {
			$('.submenuoptions').stop().slideUp(120);
			$('.menuoptions').stop().slideDown(120);
		} else {
			$('.menuoptions').stop().slideUp(120);
		}

		return false;
	});

	$('.bottom_scroll_up').unbind().click(function(event) {
		event.preventDefault();
		$('#navigation_right').animate({ scrollLeft:0, scrollTop: 0 }, 1000, 'easeInOutQuart');
	});

	/* Highlight sortable table columns */
	$('.tableHeader th').has('i.fa-sort').removeClass('tableHeaderColumnHover tableHeaderColumnSelected');
	$('.tableHeader th').has('i.fa-sort-up').addClass('tableHeaderColumnSelected');
	$('.tableHeader th').has('i.fa-sort-down').addClass('tableHeaderColumnSelected');
	$('.tableHeader th').has('i.fa-sort').hover(
		function() {
			$(this).addClass("tableHeaderColumnHover");
		}, function() {
			$(this).removeClass("tableHeaderColumnHover");
		}
	);

	$('input#filter, input#rfilter').addClass('ui-state-default ui-corner-all');

	$('input[type="text"], input[type="password"], input[type="checkbox"], textarea').not('image').addClass('ui-state-default ui-corner-all');

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

	$('select.colordropdown').dropcolor();

	$('select').not('.colordropdown').each(function() {
		if ($(this).prop('multiple') != true) {
			$(this).each(function() {
				id = $(this).attr('id');

				$(this).selectmenu({
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

	/* Replace icons */
	$('.fa-arrow-down').addClass('fa-chevron-down').removeClass('fa-arrow-down');
	$('.fa-arrow-up').addClass('fa-chevron-up').removeClass('fa-arrow-up');

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
			//console.log('hiding1:'+$(this).closest('.menuitem').attr('id'));
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

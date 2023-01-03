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
	var pageName = basename($(location).attr('pathname'));
	var hostTimer = false;
	var clickTimeout = false;
	var hostOpen = false;

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

	/* Start clean up */

	//login page
	$('.cactiLoginLogo').html("<i class='fa fa-paper-plane'/>");
	$('.cactiLogoutLogo').html("<i class='fa fa-paper-plane'/>");

	/* clean up the navigation menu */
	$('.cactiConsoleNavigationArea').find('#menu').appendTo($('.cactiConsoleNavigationArea').find('#navigation'));
	$('.cactiConsoleNavigationArea').find('#navigation > table').remove();

	if ($('#cactiPageBottom').length == 0) {
		$('<div id="cactiPageBottom" class="cactiPageBottom"><a class="bottom_scroll_up action-icon-user" href="#"><i class="fa fa-arrow-circle-o-up"></i></a></div>').insertAfter('#cactiContent');
	}

	$('.maintabs nav ul li a.lefttab').each( function() {
		id = $(this).attr('id');

		if (id == 'tab-graphs' && $(this).parent().hasClass('maintabs-has-submenu') == 0) {
			$(this).parent().addClass('maintabs-has-submenu');
			$('<div class="dropdownMenu">'
				+'<ul id="submenu-tab-graphs" class="submenuoptions" style="display:none;">'
					+'<li><a id="tab-graphs-tree-view" href="'+urlPath+'graph_view.php?action=tree"><span>'+treeView+'</span></a></li>'
					+'<li><a id="tab-graphs-list-view" href="'+urlPath+'graph_view.php?action=list"><span>'+listView+'</span></a></li>'
					+'<li><a id="tab-graphs-pre_view" href="'+urlPath+'graph_view.php?action=preview"><span>'+previewView+'</span></a></li>'
				+'</ul>'
			+'</div>').appendTo('body');
		} else {
			/* plugin stuff here ? */
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
			$(this).removeClass( "tableHeaderColumnHover");
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
		} else{
			$(this).addClass('ui-state-default ui-corner-all');
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

	/* Replace icons */
	$('.fa-arrow-down').addClass('fa-chevron-down').removeClass('fa-arrow-down');
	$('.fa-arrow-up').addClass('fa-chevron-up').removeClass('fa-arrow-up');
	$('.fa-remove').addClass('fa-trash-o').removeClass('fa-remove');

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

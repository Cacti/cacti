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

var midWinter_classes = new Array();
loadScript('navigationBox', 'include/themes/midwinter/midwinter.js');
loadScript('navigationTree', 'include/themes/midwinter/midwinter.jstree.js');
loadScript('hotkeys', 'include/themes/midwinter/vendor/hotkeys/hotkeys.min.js');
loadScript('moment', 'include/themes/midwinter/vendor/moment/moment.min.js');
loadScript('daterangepicker', 'include/themes/midwinter/vendor/daterangepicker/daterangepicker.js');

function handleUserMenu() {};

function themeReady() {

	/* load default values */
	initStorageItem('midWinter_Color_Mode', 'dark', 'theme-color');
	initStorageItem('midWinter_Color_Mode_Auto', 'off', 'theme-color-auto');
	initStorageItem('midWinter_Font_Size', '82.5', 'zoom-level');
	initStorageItem('midWinter_Animations', 'on', 'animations');
	initStorageItem('midWinter_widthNavigationBox_settings', 'three');

	setupTheme();
	setupDefaultElements();
	setHotKeys();
	updateNavigation();
	updateAjaxAnchors();
	setThemeColor();

	hideConsoleNavigation();
	setupTree();
	setupThemeActions();

	themeLoader('off');
}

function hideConsoleNavigation() {
	$('[class^="mdw-ConsoleNavigationBox"][data-helper!="tree"]').removeClass('visible');
	$('.compact_nav_icon[data-helper!="tree"]').removeClass('active');
}

function updateAjaxAnchors() {
	$('a.pic, a.linkOverDark, a.linkEditMain, a.console, a.hyperLink, a.tab').not('[href^="http"], [href^="https"], [href^="#"], [href^="mailto"], [target="_blank"]').off('click').on('click', function(event) {
		event.preventDefault();
		event.stopPropagation();

		/* determine the page name */
		var href = $(this).attr('href');

		if (href === '#') {
			return false;
		}

		/* update menu selection */
		if ($(this).hasClass('pic')) {
			$(this).addClass('selected');
		}

		/* update menu selection */
		if ($(this).hasClass('lefttab')) {
			$('.lefttab').removeClass('selected');
			$(this).addClass('selected');
		}

		/* update menu selection */
		if ($(this).hasClass('righttab')) {
			$('.righttab').removeClass('selected');
			$(this).addClass('selected');
		}

		if (href != null) {
			pageName = basename(href);
		}

		/* --------------- start MidWinter mod --------------- */
		// set a marker if this a menu action
		$(this).closest('div[class^="cactiConsoleNavigation"]').addClass('active');

		// close the console navigation afterward
		$('[class^="mdw-ConsoleNavigationBox"][data-helper!="tree"]').removeClass('visible');

		/* ---------------- end MidWinter mod ---------------- */
		loadUrl({url:href});
		return false;
	});
}

function midWinterNavigation(element) {

	let action   		= element.parent().html();
	let category 		= element.closest('.menuitem').children('.menu_parent').first().html();
	let helper   		= element.closest('div[class^="mdw-ConsoleNavigationBox"]').data('helper');
	let rubric_title 	= element.closest('div[class^="mdw-ConsoleNavigationBox"]').data('title');
	let rubric_icon   	= $('.compact_nav_icon[data-helper="'+helper+'"]').html();

	$('#navBreadCrumb .rubric').html( rubric_icon + rubric_title ).attr('data-helper', helper);
	$('#navBreadCrumb .category').html( category );
	$('#navBreadCrumb .action').html( action );

}

function updateNavigation() {
	// use different search patterns until we have a valid location to populate the new breadcrumb navigation
	let menu_element;
	menu_element = $('[class^="mdw-ConsoleNavigationBox"] a[href$="'+window.location.pathname+window.location.search+'"').first();
	if (menu_element.length !== 0) return midWinterNavigation(menu_element);
	menu_element = $('[class^="mdw-ConsoleNavigationBox"] a[href$="'+window.location.pathname+'"').first();
	if (menu_element.length !== 0) return midWinterNavigation(menu_element);
	menu_element = $('[class^="mdw-ConsoleNavigationBox"] a[href$="'+window.location.pathname+'index.php"').first();
	if (menu_element.length !== 0) return midWinterNavigation(menu_element);

	// Append an action if the user did not provide one based upon the cactiAction variable
	menu_element = $('[class^="mdw-ConsoleNavigationBox"] a[href^="'+window.location.pathname+'?action='+cactiAction+'"').first();
	if (menu_element.length !== 0) return midWinterNavigation(menu_element);

	// Choose what fits best in situations where users have cleared their settings
	menu_element = $('[class^="mdw-ConsoleNavigationBox"] a[href^="'+window.location.pathname+'"').first();
	if (menu_element.length !== 0) return midWinterNavigation(menu_element);
}

function setupTheme() {
	let storage = Storages.localStorage;
	let midWinter_Color_Mode = storage.get('midWinter_Color_Mode');
	let midWinter_Color_Mode_Auto = storage.get('midWinter_Color_Mode_Auto');
	let midWinter_Font_Size = storage.get('midWinter_Font_Size');
	let midWinter_widthNavigationBox_dashboards = storage.get('midWinter_widthNavigationBox_dashboards');
	let midWinter_Animations = storage.get('midWinter_Animations');

	// -- login, logout -- rewrite
	if ($('.loginArea legend').length !== 0) {
		$('.loginArea legend').text('Cacti Monitoring');
		$('.loginTitle p').html('v'+cactiVersion);
		$('#login_username, #login_password').attr('placeholder', '');
	}

	// duplicate cactiConsolePageHeadBackdrop for compact mode
	if ($('#mdw-ConsolePageHead').length === 0 ) {
		$('<div id="mdw-ConsolePageHead">'+
			'<div id="navBreadCrumb">'+
				'<div class="rubric"></div><div class="separator">/</div>'+
				'<div class="category"></div><div class="separator">/</div>'+
				'<div class="action"></div>'+
			'</div>' +
			'<div id="navSearch"></div>'+
			'<div id="navFilter">'+
	//			'<div id="reportrange"style="cursor: pointer; padding: 5px 10px; border: 1px solid var(--border-color);">' +
	//			'<i className="fa fa-calendar"></i>&nbsp;<span></span> <i className="fa fa-caret-down"></i>' +
			'</div>'+
			'<div id="navControl"></div>'+
		'</div>').insertBefore("#breadCrumbBar");
	}

	if ($('.mdw-ConsoleNavigationArea').length === 0 && $('.cactiContent').length !== 0)  {
		$('<div id="mdw-ConsoleNavigation" class="mdw-ConsoleNavigationArea"></div>').insertBefore('#cactiContent');
	}

	// -- redesign console navigation area
	if ($('.mdw-ConsoleNavigationArea').length !== 0) {

		if ($('#navBackdrop').length === 0 ) {
			$('.mdw-ConsoleNavigationArea').empty().prepend('<div class="compact_nav_icon_menu" id="navBackdrop"></div>');
			if (cactiConsoleAllowed) {
				$("#navBackdrop").click( function() {
					/* hide open menu boxes first and remove menu selection */
					$('[class^="cactiConsoleNavigation"]').removeClass('visible');
					$('.compact_nav_icon').removeClass('active');
					loadUrl({url:urlPath+'index.php'});
				});
			} else {
				$("#navBackdrop").click( function() {
					window.open('https://cacti.net', '_blank');
				});
			}
		}

		if ($('#compact_tab_menu').length === 0 && $('#compact_user_menu').length === 0) {

			let element_menu = $('#menu').html();
			if(element_menu === undefined) {
				element_menu = loadElement('menu', 'about.php', true);
			}

			$('.mdw-ConsoleNavigationArea').append(
				'<div class="compact_nav_icon_menu" id="compact_tab_menu"></div>'
				+'<div class="compact_nav_icon_menu" id="compact_user_menu"></div>'
			);

			/* dashboards */
			new navigationButton('dashboards', 'fas fa-th-large', '#compact_tab_menu').build();
			new navigationBox(cactiDashboards, 'dashboards', 'full','1', 'menu').build();

			/* settings */
			if (cactiConsoleAllowed) {
				new navigationButton('settings', 'fas fa-cogs', '#compact_tab_menu').build();
				new navigationBox(zoom_i18n_settings, 'settings', 'full', '3', 'menu', 'left', zoom_i18n_settings, element_menu).build();
			}

			/* tree */
			if (cactiGraphsAllowed) {
				new navigationButton('tree', 'fas fa-seedling', '#compact_tab_menu').build();
				new navigationBox( 'Tree', 'tree', 'full', '2', 'menu','left', 'Tree').build();
			}

			/* user help */
			new navigationButton('help', 'far fa-comment-alt', '#compact_user_menu').build();
			new navigationBox(help, 'help', 'half', '2', 'none', 'left', justCacti+' &reg; v'+cactiVersion).build();

			/* user settings */
			new navigationButton('user', 'far fa-user', '#compact_user_menu').build();
			new navigationBox( cactiUser, 'user', 'half', '2', 'none', 'left', $('.loggedInAs').text() ).build();

			/* log out */
			new navigationButton('logout', 'fas fa-sign-out-alt', '#compact_user_menu', 'redirect', urlPath+'logout.php').build();

			/* table filters */
			new navigationBox( 'Display Filters', 'displayOptions', 'full', '1.5', 'close', 'right').build();
			new navigationButton('toggleColorMode', 'fas fa-adjust', '#navControl', 'toggleColorMode', 'on').build();
			new navigationButton('kioskMode', 'fas fa-tv', '#navControl', 'kioskMode', 'on').build();
		}
	}

	/* CLEAN UP */
	$('#menu_main_console').remove();

	/* replace default icons */
	$('i.menu_glyph:not(.ignore).fa-home').removeClass('fa fa-home').addClass('fa fa-tools');
	$('i.menu_glyph.fa-folder').removeClass('fa').addClass('far');
	$('i.menu_glyph.fa-clone').removeClass('fa').addClass('far');
	$('i.menu_glyph.fa-database').removeClass('fa fa-database').addClass('far fa-hdd');
	$('i.menu_glyph:not(.ignore).fa-chart-area').removeClass('fa fa-chart-area').addClass('fa fa-plus');
	$('i.menu_glyph.fa-cogs').removeClass('fa fa-cogs').addClass('fa fa-toolbox');
	$('i.menu_glyph.fa-superpowers').removeClass('fab fa-superpowers').addClass('fas fa-network-wired');

	/* hide settings icon if the user got access to console only for e.g. Intropage, but nothing else */
	if($('[class^="mdw-ConsoleNavigationBox"][data-helper="settings"]').has('li').length === 0) {
		$('[class^="compact_nav_icon"][data-helper="settings"]').addClass('hide');
	}else {
		$('[class^="compact_nav_icon"][data-helper="settings"]').removeClass('hide');
	}
}

function setupThemeActions() {
	$('[data-scope="theme"][id^="mdw_"], a[data-scope="theme"]').off().on('click', function(e) {
		let fname = $(this).attr('data-func');
		if(is_function(fname)) window[fname](e);
	});

	//$('.cactiConsoleContentArea, .cactiGraphContentArea').off().on('click', toggleCactiNavigationBox);
	$('#navBreadCrumb > div[class="rubric"]').each( function() {
		let navBox = $(this).attr('data-helper');
		$(this).off().on("click", {param: navBox}, toggleCactiNavigationBox);
	});

	$('#main').off().on('click', {param: 'off'}, toggleCactiNavigationBox);
	$('.mdw-ConsoleNavigationBox').off().on('click', hideDropDownMenu);
	//$('.dropdown').off().on('click', toggleDropDownMenu);
}

function redirect(event) {
	event.preventDefault();
	console.log(event.data);
	window.location = event.data.param;
}

function setNavigationBoxColumns(event) {
	event.preventDefault();
	let storage = Storages.localStorage;
	let helper = event.target.getAttribute('data-helper');
	let value = event.target.getAttribute('data-value');
	$('[class^="mdw-ConsoleNavigationBox"][data-helper="' + helper + '"]').attr('data-width', value);
	storage.set('midWinter_widthNavigationBox_'+helper, value);
}

function toggleCactiNavigationBox(event) {
	let helper = $(this).data('helper');

	if(event.data && event.data.param) {
		event.data.param = 'on';
	}

	if(event.data.param === 'on') {
		$(this).toggleClass('active');
	}


	/* hide open dropdown menu */
	hideDropDownMenu();
	$('.compact_nav_icon:not([data-helper="' + helper + '"])').removeClass('active');
	$('[class^="mdw-ConsoleNavigationBox"]:not([data-helper="' + helper + '"]) > div').scrollTop(0);
	$('[class^="mdw-ConsoleNavigationBox"]:not([data-helper="' + helper + '"])').removeClass('visible');
	$('[class^="mdw-ConsoleNavigationBox"][data-helper="' + helper + '"]').toggleClass('visible');
}

function toggleDropDownMenu(event) {
	//event.preventDefault();
	let helper = $(this).data('helper');
	$('[class^="navBox-header-dropdown"][data-helper="' + helper + '"]').toggleClass('show');
	return false;
}

function hideDropDownMenu() {
	$('[class^="navBox-header-dropdown"]').removeClass('show');
}

function toggleTableColumn(event) {
	let storage = Storages.localStorage;
	let tableHash = event.target.dataset.table;
	let cIndex = parseInt(event.target.dataset.column);
	let cClass = 'no-col'+cIndex;
	let storage_table_headers = storage.get('midWinter_' + tableHash);

	storage_table_headers[1][cIndex-1][4] = Number(event.target.checked);
	if(event.target.checked === false) {
		storage_table_headers[0].push(cClass);
		$('[data-table="'+tableHash+'"]').addClass(cClass);
	}else {
		let index = storage_table_headers[0].indexOf(cClass);
		if(index !== -1) {
			storage_table_headers[0].splice(index, 1);
		}
		$('[data-table="'+tableHash+'"]').removeClass(cClass);
	}
	storage.set('midWinter_' + tableHash, JSON.stringify(storage_table_headers));
}

function setupDefaultElements() {
	let storage = Storages.localStorage;
	var pageName = basename($(location).attr('pathname'));
	var hostTimer = false;
	var clickTimeout = false;
	var hostOpen = false;

	$(function() {

		var start = moment();
		var end = moment();

		function cb(start, end) {
			$('#reportrange1 span').html(start.format() + ' - ' + end.format());
		}

		$('#reportrange1').daterangepicker({
			startDate: start,
			endDate: end,
			"timePicker": true,
			"timePicker24Hour": true,
			"timePickerSeconds": true,
			ranges: {
				'Last Half Hour': [moment().subtract(30, 'minutes'), moment()],
				'Last Hour': [moment().subtract(60, 'minutes'), moment()],
				'Last 2 Hours': [moment().subtract(90, 'minutes'), moment()],
				'Today': [moment(), moment()],
				'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
				'Last 7 Days': [moment().subtract(6, 'days'), moment()],
				'Last 30 Days': [moment().subtract(29, 'days'), moment()],
				'This Month': [moment().startOf('month'), moment().endOf('month')],
				'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
			},
			"opens": "left",
		}, cb);

		cb(start, end);

	});

	/* cleanup - remove unused elements */
	$('#breadCrumbBar, .cactiPageHead, .cactiShadow, .cactiConsoleNavigationArea, .cactiTreeNavigationArea').detach();

// top right corner navigation bar - holds buttons
	//$('#navFilter').removeClass('visible');

	// ensure that filter table and 1st navBar will stay on top
	if ($('.stickyContainer').length) {
		$('.stickyContainer').remove();
	}

	if ($(".filterTable").length) {
		//$('#navFilter').addClass('visible');

		let filter;
		filter = $(".filterTable:first").closest('div').detach();
		$('[class^="mdw-ConsoleNavigationBox"][data-helper="displayOptions"] .tab-filters').html(filter);

		/* default setup */
//		$('<div id="filterTableOnTop" class="stickyContainer sticky"><div id="filterTableOnTopContent"></div><div id="filterTableOnTopControl"></div></div>').prependTo('#navigation_right');
		new navigationButton('displayOptions', 'fas fa-sliders-h', '#navFilter', 'auto', 'off').build();
//		new navigationButton('hideTopNavBar', 'fas fa-chevron-up', '#filterTableOnTopControl', 'toggleTopNavBar', 'off').build();

		/* custom content */
		if($("#main >div:first .filterTable:first").closest('div').length === 1) {
		//	$("#main >div:first .filterTable:first").closest('div').detach().prependTo('#filterTableOnTop');
			$(".break:first").detach().appendTo('#filterTableOnTop');

			/* hide filter table title */
			$('#filterTableOnTop .cactiTableTitle').detach();

			$(".navBarNavigation:first").detach(); //.appendTo('#filterTableOnTop');
			$("#filterTableOnTop").removeClass('hide');
		}
	}

	// ensure that filter table and 1st navBar will stay on top
	if ($("#main>div.tabs:first").length) {
		$('<div id="tabsOnTop" class="stickyContainer hide sticky">').prependTo('#navigation_right');
		$("#main>div.tabs:first").closest('div').detach().prependTo('#tabsOnTop');
		$("#tabsOnTop").removeClass('hide');
	}else if($("#main >div .tabs:first").length) {
		$('<div id="tabsOnTop" class="stickyContainer hide sticky">').prependTo('#navigation_right');
		$("#main >div .tabs:first").closest('div').detach().prependTo('#tabsOnTop');
		$("#tabsOnTop").removeClass('hide');
	}


	if ($('tr.tableHeader').length !== 0) {
		let cArray = [];
		let tClasses = [];
		let cIndex = 1;
		let cName;
		let cTitle;
		let cHideable = 0;
		let cVisible = 1;
		let tableID = $('tr.tableHeader').closest('.cactiTable').attr('id');
		let cHeaderStr = '';
		$('th', $('tr.tableHeader')).each(function () {
			cName = 'n/a';
			if ($(this).hasClass('sortable')) {
				cName = $('div.sortinfo', $(this)).attr('sort-column');
			}
			cHeaderStr += cName;
		})
		let tableHash = cyrb53(window.location.pathname + tableID + cHeaderStr);
		let table_settings;
		let storage_table_headers = storage.get('midWinter_' + tableHash);

		/* make this table addressable */
		$('#'+tableID).attr('data-table', tableHash);

		if (storage_table_headers !== null) {
			if (sessionLocale === storage_table_headers[2]) {
				$('#' + tableID).addClass(storage_table_headers[0]);
			}else {
				/* user language change detected */
				$('th', $('tr.tableHeader')).each(function () {
					cTitle = 'n/a';
					if ($(this).hasClass('sortable')) {
						cTitle = $('i:first', $(this)).parent().text();
					}else {
						cTitle = $(this).text();
					}
					storage_table_headers[1][cIndex-1][2] = cTitle;
					cIndex++;
				});
				storage_table_headers[2] = sessionLocale;
				storage.set('midWinter_' + tableHash, JSON.stringify(storage_table_headers));
			}
		} else {
			$('th', $('tr.tableHeader')).each(function () {
				cName = 'n/a';
				cTitle = 'n/a';
				cHideable = 0;
				if ($(this).hasClass('sortable')) {
					cName = $('div.sortinfo', $(this)).attr('sort-column');
					cTitle = $('i:first', $(this)).parent().text();
					cHideable = 1;
				} else {
					if (!$(this).hasClass('tableSubHeaderCheckbox')) {
						cName = 'n/a';
						cTitle = $(this).text();
						cHideable = 1;
					}
				}
				cArray.push([cIndex, cName, cTitle, cHideable, cVisible]);
				cIndex++;
			})

			if (cArray.length) {
				table_settings = [tClasses, cArray, sessionLocale];
				storage.set('midWinter_' + tableHash, JSON.stringify(table_settings));
				storage_table_headers = storage.get('midWinter_' + tableHash);
			}
		}

		if (storage_table_headers !== null) {
			let columns_filter = '';
			let columns = storage_table_headers[1];
			columns.forEach( (columns) => {
				cIndex = columns[0];
				cName = columns[1];
				cTitle = columns[2];
				cHideable = columns[3];
				cVisible = columns[4];

				if(cHideable) {
					columns_filter += '<div>' + cTitle + '</div>'
						+ '<div>'
						+ '<label class="checkboxSwitch">'
						+ '<input data-scope="theme" id="mdw_' + 'col_' + cIndex +'" data-func="toggleTableColumn" data-table="'+tableHash+'" data-column="'+cIndex+'" class="formCheckbox" type="checkbox" name="mdw_' + 'col_' + cIndex + '"' + (cVisible ? ' checked' : '') + ( (cIndex===1) ? ' disabled' : '') + '>'
						+ '<span class="checkboxSlider checkboxRound"></span>'
						+ '</label>'
						+ '<label class="checkboxLabel checkboxLabelWanted" for="mdw_' + 'col_' + cIndex + '"></label>'
						+ '</div>'
				}
			})
			$('[class^="mdw-ConsoleNavigationBox"][data-helper="displayOptions"] .tab-columns').html(columns_filter);
		}
	}else {
		$('[class^="mdw-ConsoleNavigationBox"][data-helper="displayOptions"] .tab-columns').html('');
	}


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


	//$('td:nth-child(2), th:nth-child(2)').addClass('hide');


	$('input#filter, input#rfilter').addClass('ui-state-default ui-corner-all');

	$('input[type="text"], input[type="password"], input[type="checkbox"], textarea').not('image').addClass('ui-state-default ui-corner-all');

	// really shitty workaround to make custom row checkboxes clickable again. :(
	$('tr[id*="line"]:not(.disabled_row)').each(function(data) {
		$(this).find('.formCheckboxLabel').removeAttr('for');
	});

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
					width: false
				});

				$('#'+id+'-menu').css('max-height', '250px');
			});
		} else {
			$(this).addClass('ui-state-default ui-corner-all');
		}
	});

	$('#host').off().autocomplete({
		source: pageName+'?action=ajax_hosts',
		autoFocus: true,
		minLength: 0,
		select: function(event,ui) {
			$('#host_id').val(ui.item.id);
			callBack = $('#call_back').val();
			if (callBack != 'undefined') {
				if (callBack.indexOf('applyFilter') >= 0) {
					applyFilter();
				} else if (callBack.indexOf('applyGraphFilter') >= 0) {
					applyGraphFilter();
				}
			} else if (typeof applyGraphFilter === 'function') {
				applyGraphFilter();
			} else {
				applyFilter();
			}
		}
	}).addClass('ui-state-default ui-selectmenu-text').css('border', 'none').css('background-color', 'transparent');

	$('#host_click').css('z-index', '4');
	$('#host_wrapper').off().dblclick(function() {
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
		}
	);

	function showGraphMenu(element) {
		element.find('.spikekillMenu').menu('disable');
		element.find('.iconWrapper').show(300, function() {
			graphMenuElement = element.attr('id').replace('dd', '');
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

function initStorageItem(name, default_value, data_attribute= '') {
	let storage = Storages.localStorage;
	if (storage.isSet(name) === false) {
		storage.set(name, default_value);
	}
	if (data_attribute !=='') {
		setDocumentAttribute(data_attribute, storage.get(name));
	}
	return storage.get(name);
}

function themeLoader(state='off', force = false) {
	if (state === 'on') {
		if (getDocumentAttribute('data-theme-state') !== 'ready' | force === true) {
			setDocumentAttribute('theme-state', 'loading');
		}
	} else {
		setDocumentAttribute('theme-state', 'ready');
	}
}

function setDocumentAttribute(name, value) {
	document.documentElement.setAttribute('data-'+name, value);
}

function getDocumentAttribute(name) {
	return document.documentElement.getAttribute('data-'+name);
}

function setCookieValue(name, value) {
	$.cookie(name, value.toString(), { expires: 365, path: urlPath + ';SameSite=Lax', secure: ( window.location.protocol === "https:") });
}

function getCookieValue(name) {
	return $.cookie(name);
}

function toggleColorMode() {
	let storage = Storages.localStorage;
	let midWinter_Color_Mode = storage.get('midWinter_Color_Mode');
	let midWinter_Color_Mode_Auto = storage.get('midWinter_Color_Mode_Auto');

	if (midWinter_Color_Mode_Auto !== 'on') {
		midWinter_Color_Mode = (midWinter_Color_Mode === 'dark') ? 'light' : 'dark';
		storage.set('midWinter_Color_Mode', midWinter_Color_Mode);
		setDocumentAttribute('theme-color', midWinter_Color_Mode);
		setCookieValue('CactiColorMode', midWinter_Color_Mode);
		initializeGraphs(true);
	}
}

function toggleColorModeAuto() {
	let storage = Storages.localStorage;
	let midWinter_Color_Mode = storage.get('midWinter_Color_Mode');
	let midWinter_Color_Mode_Auto = storage.get('midWinter_Color_Mode_Auto');

	midWinter_Color_Mode_Auto = (midWinter_Color_Mode_Auto === 'on') ? 'off' : 'on';
	storage.set('midWinter_Color_Mode_Auto', midWinter_Color_Mode_Auto);
	setDocumentAttribute('theme-color-auto', midWinter_Color_Mode_Auto);
	setThemeColor();
}

function changeGuiFontSize() {
	let storage = Storages.localStorage;
	let midWinter_Font_Size = storage.get('midWinter_Font_Size');
	midWinter_Font_Size = $('#mdw_themeFontSize').val();
	storage.set('midWinter_Font_Size', midWinter_Font_Size);
	setDocumentAttribute('zoom-level', midWinter_Font_Size);
}

function toggleGuiAnimations() {
	let storage = Storages.localStorage;
	let midWinter_Animations = storage.get('midWinter_Animations');
	midWinter_Animations = (midWinter_Animations === 'on') ? 'off' : 'on';
	storage.set('midWinter_Animations', midWinter_Animations);
	setDocumentAttribute('animations', midWinter_Animations);
}

function setThemeColor() {
	let storage = Storages.localStorage;
	let auto = storage.get('midWinter_Color_Mode_Auto');

	$('#mdw_themeColorMode').attr('disabled', (auto === 'on'));
	detectSystemColorSetup(auto);
}

function detectSystemColorSetup(state) {
	let storage = Storages.localStorage;
	const systemColorMode = window.matchMedia("(prefers-color-scheme: dark)");

	let _listener = (e) => { checkThemeColorSetup((e.matches) ? 'dark' : 'light'); };

	if(state === 'on') {
		systemColorMode.addEventListener('change', _listener);
		checkThemeColorSetup(systemColorMode.matches === true ? 'dark' : 'light');
	}else {
		systemColorMode.removeEventListener('change', _listener);
		checkThemeColorSetup(storage.get('midWinter_Color_Mode'));
	}
}

function checkThemeColorSetup(color_mode) {
	let document_color_mode = document.documentElement.getAttribute('data-theme-color');
	let cookie_color_mode = getCookieValue('CactiColorMode');

	if (document_color_mode !== color_mode || cookie_color_mode !== color_mode) {
		setDocumentAttribute('theme-color', color_mode)
		setCookieValue('CactiColorMode', color_mode);
		initializeGraphs(true);
	}
}

function kioskMode(event = false) {
		if (event === false) {
			setDocumentAttribute('kiosk-mode', 'off');
		}else {
			toggleCactiNavigationBox(event);
			setDocumentAttribute('kiosk-mode', 'on');
		}
}

function setHotKeys() {
	if(midWinter_classes.includes('hotkeys')) {
		hotkeys('c+d,c+l,c+p,c+F1,F5,SHIFT+m+d, SHIFT+m+g, SHIFT+p, SHIFT+c+s, ESC', function (event, handler) {
			event.preventDefault();
			switch (handler.key) {
				case 'c+d':
					loadUrl({url:urlPath+'index.php'});
					break;
				case 'c+l':
					loadUrl({url:urlPath+'graph_view.php?action=list'});
					break;
				case 'c+p':
					loadUrl({url:urlPath+'graph_view.php?action=preview'});
					break;
				case 'F5':

					loadUrl({url:window.location.href});
					break;
				case 'SHIFT+m+d':
					loadUrl({url:urlPath+'host.php'});
					break;
				case 'SHIFT+m+g':
					loadUrl({url:urlPath+'graphs.php'});
					break;
				case 'SHIFT+p':
					loadUrl({url:urlPath+'auth_profile.php?action=edit'});
					break;
				case 'SHIFT+c+s':
					loadUrl({url:urlPath+'settings.php'});
					break;
				case 'ESC':
					kioskMode(false);
					break;
				default:
					alert(event);

			}
			return false;
		});
	}
}

function loadScript(className, url='') {
	if(midWinter_classes.includes(className) === false) {
		$.ajax({
			dataType: 'script',
			cache: false,
			async: false,
			url: urlPath + url,
			success: midWinter_classes.push(className)
		}).fail(function(html) {
			getPresentHTTPError(html);
		});
	}
}

function loadElement(elementName, url='', content_only=false) {
	let element;
	$.ajax({
		dataType: 'html',
		cache: false,
		async: false,
		url: urlPath + url,
		success: function(html) {
			element = (content_only) ? $(html).find('#'+elementName).html() : $(html).find('#'+elementName);
		}
	}).fail(function(html) {
		getPresentHTTPError(html);
	});
	return element;
}

/*
    cyrb53 (c) 2018 bryc (github.com/bryc)
    License: Public domain. Attribution appreciated.
    A fast and simple 53-bit string hash function with decent collision resistance.
    Largely inspired by MurmurHash2/3, but with a focus on speed/simplicity.
*/
const cyrb53 = function(str, seed = 0) {
	let h1 = 0xdeadbeef ^ seed, h2 = 0x41c6ce57 ^ seed;
	for(let i = 0, ch; i < str.length; i++) {
		ch = str.charCodeAt(i);
		h1 = Math.imul(h1 ^ ch, 2654435761);
		h2 = Math.imul(h2 ^ ch, 1597334677);
	}
	h1  = Math.imul(h1 ^ (h1 >>> 16), 2246822507);
	h1 ^= Math.imul(h2 ^ (h2 >>> 13), 3266489909);
	h2  = Math.imul(h2 ^ (h2 >>> 16), 2246822507);
	h2 ^= Math.imul(h1 ^ (h1 >>> 13), 3266489909);
	return 4294967296 * (2097151 & h2) + (h1 >>> 0);
};
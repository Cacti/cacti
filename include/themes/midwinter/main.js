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

/* global setup */
select2Setup = {
	displayDefaultLabel : true
}

/* midwinter session object */
let mdw = {
    session: {
        theme: {
            boxes:      { animated: 'on' },
            color:      { mode: 'dark', auto: 'off' },
            controls:   { subTitle: 'off', tooltip: 'on' },
            font:       { zoom: 75 },
            mobile:     { autoTableLayout: 'off' },
        },
        dock: {
            left:   { height: 'auto', width: 'auto', split: 50 },
            right:  { height: 'auto', width: 'auto', split: 50 },
            bottom: { height: 'auto', width: 'auto', split: 50 },
        },
        controls: {
            leftTop: ['dashboards', 'settings', 'tree'],
            leftBottom: [],
            rightTop: ['filter', 'layout'],
            rightBottom: [],
            bottomLeft: ['help', 'user', 'logout'],
            bottomRight: ['info'],
        },
        table: [],
    },
    obj: { box: {}, ctrl: {} },
    cache: {
        classes:    [],
        path:       'include/js/',
        storage:    Storages.localStorage,
        tap:        { count: 0, clientX: 0, clientY: 0 }
    }
}

/* cache local and vendor libs */
loadScript('navigationBox',   mdw.cache.path + 'navigationBox.js');
loadScript('navigationTree',  mdw.cache.path + 'navigationTree.jstree.js');
loadScript('hotkeys',         mdw.cache.path + 'vendor/hotkeys/hotkeys.min.js');
loadScript('mark',            mdw.cache.path + 'vendor/mark/jquery.mark.js');
loadScript('moment',          mdw.cache.path + 'vendor/moment/moment.min.js');
loadScript('daterangepicker', mdw.cache.path + 'vendor/daterangepicker/daterangepicker.js');

restoreLocalStorage();


function themeReady() {
	setupTheme();
	setupDefaultElements();

	updateNavigation();
	updateAjaxAnchors();
	setThemeColor();

	hideConsoleNavigation();
	setupTree();
	setupThemeActions();
	themeLoader('off');

    setHotKeys();
}

function hideConsoleNavigation() {
	$('[class^="mdw-ConsoleNavigationBox"]').removeClass('visible');
	$('[class^="mdw-ConsoleNavigationBox"][data-helper!="tree"]').removeClass('visible');
	$('.compact_nav_icon[data-helper!="tree"]').removeClass('selected');
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
			$('a[class="pic selected"]').removeClass('selected');
			$(this).addClass('selected');
		}

		if (href != null) {
			pageName = basename(href);
		}

		/* close the console navigation afterward */
		$('[class^="mdw-ConsoleNavigationBox"]').removeClass('visible');

		loadUrl({url:href});
		return false;
	});
}

function midWinterNavigation(element) {

	let action   		= element.parent().html();
	let category 		= element.closest('.menuitem').children('.menu_parent').first().children('span').text();
	let helper   		= element.closest('div[class^="mdw-ConsoleNavigationBox"]').data('helper');
	let rubric		 	= element.closest('div[class^="mdw-ConsoleNavigationBox"]').data('title');

	$('#navBreadCrumb .rubric').html( '<span>'+rubric+'</span>').attr('data-helper', helper).off().on(
		"click", {param: 'force_open', filter: 'reset'}, toggleCactiNavigationBox
	);
	$('#navBreadCrumb .category').html( '<span>'+category+'</span>' ).attr('data-helper', helper).off().on(
		"click", {param: 'force_open', filter: category}, toggleCactiNavigationBox
	);
	$('#navBreadCrumb .action').html( action );

	if (helper !== undefined) {
		$('.compact_nav_icon[data-helper="'+helper+'"]').addClass('mdw-active');
		$('.compact_nav_icon[data-helper!="'+helper+'"]').removeClass('mdw-active');
	}

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

	// -- login, logout -- rewrite
	if ($('.cactiAuthBody').length !== 0 && $('.cactiAuthArea legend').text() !== 'WELCOME TO CACTI') {
		/* modify login area and element */
		$('.cactiAuthArea legend').text('WELCOME TO CACTI');

		/* get rid of outdated HTML table layout - that makes CSS layout difficult */
		let cactiAuthTable = $('.cactiAuthTable').detach();

		/* suppress issues with autofocus while page is loading */
		$('<input id="suppress_autofocus" type="text" style="display:none;" tab-index="-1" autofocus>').prependTo('.cactiAuth');

		$(cactiAuthTable).find("input, button, label").each(
			function() {
				if( $(this).attr('type') === 'password' || $(this).attr('type') === 'text' ) {
					if ($(this).attr('name') !== undefined) {
						$(this).appendTo('.cactiAuth');
						if($(this).attr('type') === 'password') {
							switch ($(this).attr('id')) {
								case 'current':
									$(this).attr('placeholder', 'Current Password');
									break;
								case 'password':
									$(this).attr('placeholder', 'New Password');
									break;
								case 'password_confirm':
									$(this).attr('placeholder', 'Confirm Password');
									break;
								default:
							}
							$('<i class="ti ti-lock" data-helper="' + $(this).attr('id') + '" data-func="togglePwdInputField"></i>').insertAfter($(this));
						}
					}
				}else {
					$(this).appendTo('.cactiAuth');
				}
			}
		)
		let welcome = $(cactiAuthTable).find('td').eq(0).html();
		$('<span>'+welcome+'</span>').prependTo('.cactiAuth');
		cactiAuthTable = undefined;

		$('.versionInfo').detach().appendTo('.cactiAuthBody');

		$('<i class="ti ti-user"></i>').insertAfter('#login_username');
	}


	if ($('.loginArea legend').length !== 0) {
		$('.loginArea legend').text('Cacti Monitoring');
		$('.loginTitle p').html('v'+cactiVersion);
		$('#login_username, #login_password').attr('placeholder', '');
	}

	// duplicate cactiConsolePageHeadBackdrop for compact mode

	if ($('#cactiContent').length) {
		$('<div id="mdw-GridContainer" class="mdw-GridContainer">' +
			'<div id="mdw-GridContainer-Overlay" class="mdw-GridContainer-Overlay mdw-PopOver hidden"></div>' +
			'<div id="mdw-GridContainer-PopOver" class="mdw-GridContainer-PopOver mdw-PopOver hidden">' +
				'<div id="mdw-PopOverTitle" class="mdw-PopOverElements mdw-PopOverTitle"></div>' +
				'<div id="mdw-PopOverContent" class="mdw-PopOverElements mdw-PopOverContent"></div>' +
				'<div id="mdw-PopOverFooter" class="mdw-PopOverElements mdw-PopOverFooter"></div>' +
			'</div>' +
			'<div id="mdw-ConsoleNavigation" class="mdw-ConsoleNavigation"></div>' +
			'<div id="mdw-ConsolePageHead" class="mdw-ConsolePageHead">' +
				'<div id="navBreadCrumb" class="navBreadCrumb">' +
					'<div class="home"><a href="' + urlPath + 'index.php" class="pic">Home</a></div>' +
					'<div class="rubric"></div>' +
					'<div class="category"></div>' +
					'<div class="action"></div>' +
				'</div>' +
				'<div id="navSearch" class="navSearch"></div>' +
				'<div id="navFilter" class="navFilter"></div>' +
				'<div id="navControl" class="navControl" ></div>' +
			'</div>' +
			'<div id="mdw-Main" class="mdw-Main">' +
				'<div id="mdw-DockTop" class="mdw-DockTop" >' +
                    '<div class="mdw-DockInnerLeft"></div>' +
                    '<div class="mdw-DockInnerRight"></div>' +
                '</div>' +
				'<div id="mdw-DockLeft" class="mdw-DockLeft" data-helper="displayDockTop">' +
                    '<div class="mdw-DockInnerTop"></div>' +
                    '<div class="mdw-DockInnerBottom"></div>' +
                '</div>' +
				'<div id="mdw-DockRight" class="mdw-DockRight">' +
                    '<div class="mdw-DockInnerTop"></div>' +
                    '<div class="mdw-DockInnerBottom"></div>' +
                '</div>' +
				'<div id="mdw-DockBottom" class="mdw-DockBottom">' +
                    '<div class="mdw-DockInnerLeft"></div>' +
                    '<div class="mdw-DockInnerRight"></div>' +
                '</div>' +
			'</div>' +
			'<div id="mdw-ActionBar" class="mdw-ActionBar">' +
				'<div id="mdw-ActionBarTop" class="mdw-ActionBarTop"></div>' +
				'<div id="mdw-ActionBarMiddle" class="mdw-ActionBarMiddle"></div>' +
				'<div id="mdw-ActionBarBottom" class="mdw-ActionBarBottom"></div>' +
			'</div>' +
		'</div>'
	).
		insertBefore("#breadCrumbBar");

		let element_main = $('#navigation_right').detach();
		$(element_main).insertAfter($('#mdw-DockLeft'));
		$('#cactiContent').remove();
	}

	// -- redesign console navigation area
	if ($('.mdw-ConsoleNavigation').length !== 0) {

		if ($('#navBackdrop').length === 0 ) {
			$('.mdw-ConsoleNavigation').empty().prepend('<div class="compact_nav_icon_menu">' +
				'<div class="compact_nav_icon" data-subtitle="Console" id="navBackdrop" data-tooltip="Console" role="button" tabindex="0" aria-pressed="false">' +
					'<div class="navBackdrop"></div>'+
				'</div></div>');
			if (cactiConsoleAllowed) {
				$("#navBackdrop").click( function() {
					/* hide open menu boxes first and remove menu selection */
					$('[class^="cactiConsoleNavigation"]').removeClass('visible');
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

			$('.mdw-ConsoleNavigation').append(
				'<div class="compact_nav_icon_menu" id="compact_tab_menu"></div>'
				+'<div class="compact_nav_icon_menu" id="compact_user_menu"></div>'
			);

			/* dashboards */
			new navigationButton('dashboards', 'Panels', 'Panels', 'ti ti-map', '#compact_tab_menu').show();
			new navigationBox(cactiDashboards, 'dashboards', 'full','auto', {
				close: true,
				search: 'searchToHighlight',
				resize: true
			}).build();

			/* settings */
			if (cactiConsoleAllowed) {
				new navigationButton('settings', 'Setup', 'Settings', 'ti ti-settings-cog', '#compact_tab_menu');
				new navigationBox(zoom_i18n_settings, 'settings', 'full', 'auto', {
					close: true,
					search: 'searchToHighlight',
					resize: true,
				}, 'left', zoom_i18n_settings, element_menu).build();
			}

			/* tree */
			if (cactiGraphsAllowed) {
				new navigationButton('tree', 'Tree', 'Tree View','ti ti-seedling', '#compact_tab_menu').show();
				new navigationBox( 'Tree', 'tree', 'full', 'auto', {
					close: true,
					search: 'searchCactiTree',
					resize: true,
				},'left', 'Tree').build();
			}


			/* user help */
			new navigationButton('help', 'Help', 'Help', 'ti ti-messages', '#compact_user_menu').show();
			new navigationBox(help, 'help', 'half', '2', {
				close: false,
				search: false,
				resize: false
			}, 'left', justCacti+' &reg; v'+cactiVersion).build();

			/* user settings */
			new navigationButton('user', 'User', 'User Settings', 'ti ti-user', '#compact_user_menu').show();
			new navigationBox( cactiUser, 'user', 'half', '2', {
				close: false,
				search: false,
				resize: false
			}, 'left', $('.loggedInAs').text() ).build();

			/* log out */
			new navigationButton('logout', 'Exit', 'Sign Out','ti ti-logout', '#compact_user_menu', 'redirect', urlPath+'logout.php').show();

			/* table filters */
	  		new navigationBox( 'Table Layout', 'displayOptions', 'full', '1', {
				close: true,
				search: false,
				resize: false,
				dock: true,
			}, 'right','Table Layout', 'auto').build();
			new navigationButton('toggleColorMode', 'Color', 'Toggle light/dark Mode', 'ti ti-contrast-filled', '#navControl', 'toggleColorMode', 'on').show();
			new navigationButton('kioskMode', 'Kiosk', 'Enable Kiosk Mode', 'ti ti-device-desktop', '#navControl', 'kioskMode', 'on').show();

			if ( document.fullscreenEnabled ) {
				let icon = (!document.fullscreenElement) ? 'ti ti-maximize' : 'ti ti-minimize';
				new navigationButton('fullScreen', 'Fullscreen', 'Switch to Fullscreen', icon, '#navControl', 'fullScreen').show();
			}

            /* theme setup */
            /* MidWinter Info Icon */
            new navigationBox( 'Theme', 'theme', 'full', '1', {
                close: true,
                search: false,
                resize: false,
                dock: true,
            }, 'right','Theme', 'auto').build();
            new navigationButton('theme', 'Theme Settings', 'Theme Settings', 'ti ti-color-swatch', '#mdw-ActionBarBottom').show();

            /* display Filter */
            mdw.obj.box.displayFilterOptions = new navigationBox('Filter', 'displayFilterOptions', 'auto', '1', {
                close: true,
                search: false,
                resize: false,
                dock: true,
            }, 'right', 'Display Filter', '');
            mdw.obj.box.displayFilterOptions.build();
            mdw.obj.ctrl.displayFilterOptions = new navigationButton('displayFilterOptions', 'Filter', 'Show Display Filter', 'ti ti-filter', '#mdw-ActionBarTop');

		}
	}

	/* CLEAN UP */
	$('#menu_main_console').remove();
	$('a.menu_parent').removeClass('mdw-active').prop('inert', true); // suppress focus

	/* hide settings icon if the user got access to console only for e.g. Intropage, but nothing else */
	if($('[class^="mdw-ConsoleNavigationBox"][data-helper="settings"]').has('li').length === 0) {
		$('[class^="compact_nav_icon"][data-helper="settings"]').addClass('hide');
	}else {
		$('[class^="compact_nav_icon"][data-helper="settings"]').removeClass('hide');
	}

	$('#main').off('resize').on('resize', function() {
		let width = $('#main');
		$('#main .saveRowParent').width(width);
	})
}

function setupThemeActions() {
	$('[data-scope="theme"][id^="mdw_"]:not([type="range"]), ' +
		'a[data-scope="theme"], ' +
		'i[data-func!=""][data-func]'
	).off().on('click', function(e) {
		let fname = $(this).attr('data-func');
		if(is_function(fname)) window[fname](e);
	});

	$('input[type="range"][data-scope="theme"][id^="mdw_"]').off().on('change', function(e) {
		let fname = $(this).attr('data-func');
		if(is_function(fname)) window[fname](e);
	});

	//$('.cactiConsoleContentArea, .cactiGraphContentArea').off().on('click', toggleCactiNavigationBox);

	$('#main, #navigation_right').off().on('click', {param: 'off'}, toggleCactiNavigationBox);
	$('.mdw-ConsoleNavigationBox').off().on('click', hideDropDownMenu);
	//$('.dropdown').off().on('click', toggleDropDownMenu);
	document.addEventListener("fullscreenchange", fullScreenChangeHandler);

	// make popover draggable
	$('#mdw-GridContainer-PopOver').draggable({
		containment: '#mdw-GridContainer',
		scroll: false,
		start: function() {
			$(this).css('transform', 'translateX(0)');
		}
	});
}

function redirect(event) {
	event.preventDefault();
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
	let caller = $(event.currentTarget);
	let helper = caller.attr('data-helper');
	let param = event.data.param;

	/* hide open dropdown menu */
	hideDropDownMenu();

	$('#mdw-ConsoleNavigation .compact_nav_icon:not([data-helper="' + helper + '"])').removeClass('selected');
	$('#mdw-SideBarContainer [class^="mdw-ConsoleNavigationBox"]:not([data-helper="' + helper + '"]) > div').scrollTop(0);
	$('#mdw-SideBarContainer [class^="mdw-ConsoleNavigationBox"]:not([data-helper="' + helper + '"])').removeClass('visible');

	let navigationBox = $('[class^="mdw-ConsoleNavigationBox"][data-helper="' + helper + '"]');
	let compact_nav_icon = $('[class^="compact_nav_icon"][data-helper="' + helper + '"]');

	if(param === 'on') {
		caller.toggleClass('selected');
		navigationBox.toggleClass('visible');
	}else if(param === 'force_open') {
		caller.addClass('selected');
		navigationBox.addClass('visible');
		compact_nav_icon.addClass('selected');

		if(event.data && event.data.filter) {
			let navBox_input_field = $("input[name=navBox-header-search]", navigationBox);
			$('.navBox-header-search', navigationBox).removeClass('hide');
			if(event.data.filter !== 'reset') {
				navBox_input_field.trigger('focus').val(event.data.filter).trigger('input');
			}else {
				navBox_input_field.val('').trigger('input').blur();
			}
		}
	}else if(param === 'force_close') {
		caller.removeClass('selected').trigger('blur');
		navigationBox.removeClass('visible');
	}
}

function toggleCactiNavigationBoxPin(event) {
	let caller = $(event.currentTarget);
	let helper = caller.attr('data-helper');
	let navigationBox = $('[class^="mdw-ConsoleNavigationBox"][data-helper="' + helper + '"]');
	let compact_nav_icon = $('[class^="compact_nav_icon"][data-helper="' + helper + '"]');

	if(event.data && event.data.dock) {
		event.data.dock = event.data.dock.replace(/^./, str => str.toUpperCase());
	}

	if(/^(?:Left|Right|Top|Bottom)$/.test(event.data.dock)) {
        let destination = $("#mdw-Dock" + event.data.dock + " > .mdw-DockInnerTop");
        let make_resizeable = true;
        if ( destination.is(':not(:empty)') ) {
            destination = $("#mdw-Dock" + event.data.dock + " > .mdw-DockInnerBottom");
            make_resizeable = false;
        }

        navigationBox.detach().appendTo(destination);

		$("#mdw-Dock" + event.data.dock).removeClass('invisible');
        if(make_resizeable) {
            $("#mdw-Dock" + event.data.dock).resizable({
                handles: 'w'
            });

            destination.resizable({
                handles: 's',
                resize: function (event, ui) {
                    let parentHeight = $(this).parent().innerHeight();
                    let newHeight = $(this).outerHeight() * 100 / parentHeight;
                    $(this).css("height", newHeight + '%');
                    /* update sibling */
                    $(this).siblings('.mdw-DockInnerBottom').css('height', 100 - newHeight + '%');
                }
            });
        }


       // resize: function() {
          //  $('.test:first-of-type').css('width', $('.test:first-of-type').outerWidth() * 100 / $(window).innerWidth() + '%');
    //$('.test:nth-of-type(2)').css('width', 100 - ($('.test:first-of-type').outerWidth() * 100 / $(window).innerWidth()) + '%');

    }

}

function toggleCactiDockNavigationBox(event) {
	let caller = $(event.currentTarget);
	let helper = caller.attr('data-helper');

	if(event.data && event.data.param) {
		event.data.param = 'on';
	}

	if(event.data.param === 'on') {
		$(this).toggleClass('selected');
	}

	$('[class^="mdw-Dock"][data-helper="' + helper + '"]').toggleClass('invisible');
}

function toggleDropDownMenu(event) {
	let caller = $(event.currentTarget);
	let helper = caller.attr('data-helper');

	$('[class^="navBox-header-button"][data-action="dropdown"][data-helper="' + helper + '"]').toggleClass('show');
	return false;
}

function hideDropDownMenu() {
	$('[class^="navBox-header-button"][data-action="dropdown"]').removeClass('show');
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
		$('table[data-table="'+tableHash+'"]').addClass(cClass);
	}else {
		let index = storage_table_headers[0].indexOf(cClass);
		if(index !== -1) {
			storage_table_headers[0].splice(index, 1);
		}
		$('table[data-table="'+tableHash+'"]').removeClass(cClass);
	}
	storage.set('midWinter_' + tableHash, JSON.stringify(storage_table_headers));

	$('#mdw-columns-reset').toggleClass('inactive', (storage_table_headers[0].length === 0));
}

function resetTableColumns(event) {
	event.preventDefault();
	let cIndex;
	let storage = Storages.localStorage;
	let tableHash = event.target.getAttribute('data-helper');
	let storage_table_headers = storage.get('midWinter_' + tableHash);

	/* remove "hide-column-classes" from table */
	$('[data-table="'+tableHash+'"]').removeClass(storage_table_headers[0]);

	/* update local storage */
	storage_table_headers[0] = [];
	for(cIndex in storage_table_headers[1]) {
		storage_table_headers[1][cIndex][4] = 1;
	}
	storage.set('midWinter_' + tableHash, JSON.stringify(storage_table_headers));

	/* reset all column input fields */
	$('#mdw-columns-reset').parent().find('input[type=checkbox]').prop('checked', true).attr('aria-checked', 'true').attr('data-prev-check', 'true');

	/* set reset button/link in inactive mode */
	$('#mdw-columns-reset').addClass('inactive');
}

function togglePwdInputField(event) {
	let helper = event.target.getAttribute('data-helper');

	let destination = $('input[id="' + helper + '"]');
	if ( destination.length) {
		if(destination.attr('type') === 'password') {
			destination.attr('type', 'text');
		}else {
			destination.attr('type', 'password');
		}
		event.target.classList.toggle('ti-lock')
		event.target.classList.toggle('ti-lock-off');
	}
}

function setupDefaultElements() {

	let popover = $('#mdw-GridContainer-PopOver');
	if ( popover.hasClass('hidden') ) {

		let storage = Storages.localStorage;
		var pageName = basename($(location).attr('pathname'));
		var hostTimer = false;
		var clickTimeout = false;
		var hostOpen = false;

		$(function () {

			var start = moment();
			var end = moment();

			function cb(start, end) {
				$('#reportrange span').html(start.format() + ' - ' + end.format());
			}

			$('.compact_nav_icon[data-helper="daterangepicker"]').daterangepicker({
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
		//$('#breadCrumbBar, .cactiPageHead, .cactiShadow, .cactiConsoleNavigationArea, .cactiTreeNavigationArea').detach();
		$('#breadCrumbBar, .cactiPageHead, .cactiShadow, .cactiConsoleNavigationArea').detach();

		// ensure that filter table and 1st navBar will stay on top
		if ($('.stickyContainer').length) {
			$('.stickyContainer').remove();
		}

		let btn_calendar = new navigationButton('daterangepicker', 'Calendar', 'Select Timeframe', 'ti ti-calendar-alt', '#mdw-ActionBarTop', '', '');

        transform_filter_table();

		// *********************************** Elements on Top (of Navigation right) ************************************
		// ensure that elementsOnTop container is always available
		if (!$("#elementsOnTop").length) {
			$('<div id="elementsOnTop" class="elementsOnTop">' +
				'<div id="tableTitleOnTop" class="elementOnTop tableTitleOnTop"></div>' +
				'<div id="tableNavBarOnTop" class="elementOnTop tableNavBarOnTop"></div>' +
				'<div id="tableActionOnTop" class="elementOnTop tableActionOnTop"></div>' +
				'<div id="tableTabsOnTop" class="elementOnTop tableTabsOnTop"></div>' +
				'</div>').prependTo('#navigation_right');
		}

		// empty all top elements first
		$(".elementOnTop").empty();
		// empty actionBar middle
		$("#mdw-ActionBarMiddle").empty();

		// move table tabs to top
		if ($("#main>div.tabs:first").length) {
			$("#main>div.tabs:first").closest('div').detach().appendTo('#tableTabsOnTop');
		}

		// move table title to top
		if ($("#main div.cactiTableTitleRow").length) {

			$("#main div.cactiTableTitleRow:first > .cactiTableTitle").detach().appendTo('#tableTitleOnTop');
			$("#main div.cactiTableTitleRow:first > .cactiTableAction:not(:empty)").detach().appendTo('#tableActionOnTop');
			$("#main div.cactiTableTitleRow:first > .cactiTableButton:not(:empty)").detach().appendTo('#mdw-ActionBarMiddle');
			$("#main div.cactiTableTitleRow:first").remove();

			if ($("#main div.saveRow").length) {
				$("#main div.saveRow").detach().appendTo('#tableActionOnTop');
			} else if ($("#main div.actionsDropdown").length) {
				$("#main div.actionsDropdown > div > span").detach().appendTo('#tableActionOnTop');
			}

			if ($("#main div.navBarNavigation").length) {
				$("#main div.navBarNavigation:first").clone().appendTo('#tableNavBarOnTop');
			}
		}
		// **************************************************************************************************************

		/* display option: table layout */
		let btn_table_layout = new navigationButton('displayOptions', 'Table', 'Setup Table Layout', 'ti ti-table-options', '#mdw-ActionBarTop');

		if ($('thead>tr.tableHeader:has(th:nth-of-type(2))').length !== 0) {
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


			/* internal structure of storage_table_headers as follows
            *	[0] - contains a cached string of classes hiding all unselected columns (by user) to save processing cycles
                [1] - contains all table columns identified described as follows
                      [ index, internal name |n/a|, title |n/a|, hide-able |0|, visible |1| ]
                [2] - contains i18n session locale
            */


			/* make this table addressable */
			$('#' + tableID).attr('data-table', tableHash);

			if (storage_table_headers !== null) {
				if (sessionLocale === storage_table_headers[2]) {
					$('#' + tableID).addClass(storage_table_headers[0]);
				} else {
					/* user language change detected */
					$('th', $('tr.tableHeader')).each(function () {
						cTitle = 'n/a';
						if ($(this).hasClass('sortable')) {
							cTitle = $('i:first', $(this)).parent().text();
						} else {
							cTitle = $(this).text();
						}
						storage_table_headers[1][cIndex - 1][2] = cTitle;
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
				columns.forEach((columns) => {
					cIndex = columns[0];
					cName = columns[1];
					cTitle = columns[2];
					cHideable = columns[3];
					cVisible = columns[4];

					if (cHideable) {
						columns_filter += '<div>' + cTitle + '</div>'
							+ '<div>'
							//+ '<label class="checkboxSwitch">'
							+ '<input data-scope="theme" id="mdw_' + 'col_' + cIndex + '" data-func="toggleTableColumn" data-table="' + tableHash + '" data-column="' + cIndex + '" class="formCheckbox" type="checkbox" name="mdw_' + 'col_' + cIndex + '"' + (cVisible ? ' checked' : '') + ((cIndex === 1) ? ' disabled' : '') + '>'
							//+ '<span class="checkboxSlider checkboxRound"></span>'
							//+ '</label>'
							//+ '<label class="checkboxLabel checkboxLabelWanted" for="mdw_' + 'col_' + cIndex + '"></label>'
							+ '<label for="mdw_' + 'col_' + cIndex + '"></label>'
							+ '</div>'
					}
				})
//[["no-col4","no-col5"],[[1,"name_cache","Data Source Name",1,1],[2,"local_data_id","ID",1,1],[3,"n/a","Graphs",1,1],[4,"n/a","Poller Interval",1,0],[5,"n/a","Deletable",1,0],[6,"active","Active",1,1],[7,"data_template_name","Template Name",1,1],[8,"n/a","n/a",0,1]],"en-US"]
				columns_filter += '<div id="mdw-columns-reset" class="mdw-columns-reset'
					+ ((storage_table_headers[0].length === 0) ? ' inactive' : '')
					+ '" data-helper="' + tableHash + '">Reset</div>';

				$('[class^="mdw-ConsoleNavigationBox"][data-helper="displayOptions"] .tab-columns').html(columns_filter);
				$('#mdw-columns-reset').off().on('click', resetTableColumns);
				btn_table_layout.show();
			}
		} else {
			$('[class^="mdw-ConsoleNavigationBox"][data-helper="displayOptions"] .tab-columns').html('');
			btn_table_layout.hide();
		}

		// Add nice search filter to filters
		if ($('input[id="filter"]').length > 0 && $('input[id="filter"] > i[class="ti ti-search filter"]').length < 1) {
			$('input[id="filter"]').after("<i class='ti ti-search filter'/>").attr('autocomplete', 'off').attr('placeholder', searchFilter).parent('td').css('white-space', 'nowrap');
		}

		if ($('input[id="filterd"]').length > 0 && $('input[id="filterd"] > i[class="ti ti-search filter"]').length < 1) {
			$('input[id="filterd"]').after("<i class='ti ti-search filter'/>").attr('autocomplete', 'off').attr('placeholder', searchFilter).parent('td').css('white-space', 'nowrap');
		}

		if ($('input[id="rfilter"]').length > 0 && $('input[id="rfilter"] > i[class="ti ti-search filter"]').length < 1) {
			$('input[id="rfilter"]').after("<i class='ti ti-search filter'/>").attr('autocomplete', 'off').attr('placeholder', searchRFilter).parent('td').css('white-space', 'nowrap');
		}

		$('input#filter, input#rfilter').addClass('ui-state-default ui-corner-all');
		$('input[type="text"], input[type="password"], input[type="checkbox"], textarea').not('image').addClass('ui-state-default ui-corner-all');

		/* Highlight sortable table columns */
		$('.tableHeader th').has('i.fa-sort').removeClass('tableHeaderColumnHover tableHeaderColumnSelected');
		$('.tableHeader th').has('i.fa-sort-up').addClass('tableHeaderColumnSelected');
		$('.tableHeader th').has('i.fa-sort-down').addClass('tableHeaderColumnSelected');
		$('.tableHeader th').has('i.fa-sort').hover(
			function () {
				$(this).addClass("tableHeaderColumnHover");
			}, function () {
				$(this).removeClass("tableHeaderColumnHover");
			}
		);


		//$('td:nth-child(2), th:nth-child(2)').addClass('hide');


		$('input#filter, input#rfilter').addClass('ui-state-default ui-corner-all');

		$('input[type="text"], input[type="password"], input[type="checkbox"], textarea').not('image').addClass('ui-state-default ui-corner-all');

		// really shitty workaround to make custom row checkboxes clickable again. :(
		$('tr[id*="line"]:not(.disabled_row)').each(function (data) {
			$(this).find('.formCheckboxLabel').removeAttr('for');
		});

		// Turn file buttons into jQueryUI buttons
		$('.import_label').button();
		$('.import_button').change(function () {
			text = this.value;
			setImportFile(text);
		});

		setImportFile(noFileSelected);

		function setImportFile(fileText) {
			$('.import_text').text(fileText);
		}

		// Hide the graph icons until you hover
		$('.graphDrillDown').hover(
			function () {
				element = $(this);

				// hide the previously shown element
				if (element.attr('id').replace('dd', '') != graphMenuElement && graphMenuElement > 0) {
					$('#dd' + graphMenuElement).find('.iconWrapper:first').hide(300);
				}

				clearTimeout(graphMenuTimer);
				graphMenuTimer = setTimeout(function () {
					showGraphMenu(element);
				}, 400);
			},
			function () {
				element = $(this);
				clearTimeout(graphMenuTimer);
				graphMenuTimer = setTimeout(function () {
					hideGraphMenu(element);
				}, 400);

				if (typeof spikeKillClose == 'function') {
					spikeKillClose();
				}
			}
		);

		function showGraphMenu(element) {
			element.find('.spikekillMenu').menu('disable');
			element.find('.iconWrapper').show(300, function () {
				graphMenuElement = element.attr('id').replace('dd', '');
				$(this).find('.spikekillMenu').menu('enable');
				$(this).css('display', 'block');
			});
		}

		function hideGraphMenu(element) {
			element.find('.spikekillMenu').menu('disable');
			element.find('.iconWrapper').hide(300, function () {
				$(this).find('.spikekillMenu').menu('enable');
			});
		}

		setNavigationScroll();
	}
}

function transform_filter_table() {
    if ($("#main .filterTable").length) {
        let filter;
        filter = $("#main .filterTable:first").closest('div.cactiTable').detach();
        $('[class^="mdw-ConsoleNavigationBox"][data-helper="displayFilterOptions"] .navBox-content').html(filter);

        /* custom content */
        if ($("#main >div:first .filterTable:first").closest('div').length === 1) {
            //	$("#main >div:first .filterTable:first").closest('div').detach().prependTo('#filterTableOnTop');
            $(".break:first").detach().appendTo('#filterTableOnTop');

            /* hide filter table title */
            $('#filterTableOnTop .cactiTableTitle').detach();
            $("#filterTableOnTop").removeClass('hide');
        }
        mdw.obj.ctrl.displayFilterOptions.show();
    } else {
        mdw.obj.ctrl.displayFilterOptions.hide();
      //  $('[class^="mdw-ConsoleNavigationBox"][data-helper="displayFilterOptions"]').hide();
    }
}

function restoreLocalStorage() {
    if (mdw.cache.storage.isSet('midWinter') === false) {
        refreshLocalStorage();
    } else {
        mdw.session = JSON.parse(lzjs.decompress(mdw.cache.storage.get('midWinter')));
    }
    setDocumentAttribute('theme-color',         mdw.session.theme.color.mode );
    setDocumentAttribute('theme-color-auto',    mdw.session.theme.color.auto );
    setDocumentAttribute('zoom-level',          mdw.session.theme.font.zoom );
    setDocumentAttribute('animations',          mdw.session.theme.boxes.animated );
    setDocumentAttribute('auto-table-layout',   mdw.session.theme.mobile.autoTableLayout );
    setDocumentAttribute('controls-subtitle',   mdw.session.theme.controls.subTitle );
}

function refreshLocalStorage() {
    mdw.cache.storage.set('midWinter', lzjs.compress(JSON.stringify(mdw.session)));
}

function themeLoader(state='off', force = false) {
	if (state === 'on') {
		if (getDocumentAttribute('data-theme-state') !== 'ready' || force === true) {
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
	if (mdw.session.theme.color.auto !== 'on') {
        mdw.session.theme.color.mode = (mdw.session.theme.color.mode === 'dark') ? 'light' : 'dark';
		refreshLocalStorage();
		setDocumentAttribute('theme-color', mdw.session.theme.color.mode);
		setCookieValue('CactiColorMode', mdw.session.theme.color.mode);
		initializeGraphs(true);
	}
}

function toggleColorModeAuto() {
    mdw.session.theme.color.auto = (mdw.session.theme.color.auto === 'on') ? 'off' : 'on';
    refreshLocalStorage();
	setDocumentAttribute('theme-color-auto', mdw.session.theme.color.auto);
	setThemeColor();
	/* update output field beside input selector */
	$('#mdw_themeColorModeAutoValue').val(mdw.session.theme.color.auto);
}

function changeGuiFontSize(change=true) {
    mdw.session.theme.font.zoom = $('#mdw_themeFontSize').val();
	if(change) {
		refreshLocalStorage();
        setDocumentAttribute('zoom-level', mdw.session.theme.font.zoom);
	}
	/* update output field beside input selector */
	$('#mdw_themeFontSizeValue').val((parseFloat(mdw.session.theme.font.zoom) + 25).toFixed(1) + ' %');
}

function toggleGuiAnimations() {
    mdw.session.theme.boxes.animated = (mdw.session.theme.boxes.animated === 'on') ? 'off' : 'on';
    refreshLocalStorage();
	setDocumentAttribute('animations', mdw.session.theme.boxes.animated);
	/* update output field beside input selector */
	$('#mdw_themeAnimationsValue').val(mdw.session.theme.boxes.animated);
}

function toggleControlsSubtitle() {
    mdw.session.theme.controls.subTitle = (mdw.session.theme.controls.subTitle === 'on') ? 'off' : 'on';
    refreshLocalStorage();
	setDocumentAttribute('controls-subtitle', mdw.session.theme.controls.subTitle);
	/* update output field beside input selector */
	$('#mdw_themeControlsSubTitleValue').val(mdw.session.theme.controls.subTitle);
}

function toggleAutoTableLayout() {
    mdw.session.theme.mobile.autoTableLayout = (mdw.session.theme.mobile.autoTableLayout === 'on') ? 'off' : 'on';
    refreshLocalStorage();
	setDocumentAttribute('auto-table-layout', mdw.session.theme.mobile.autoTableLayout);
	/* update output field beside input selector */
	$('#mdw_themeAutoTableLayoutValue').val(mdw.session.theme.mobile.autoTableLayout);
}

function setThemeColor() {
	$('#mdw_themeColorMode').attr('disabled', (mdw.session.theme.color.auto === 'on'));
	detectSystemColorSetup(mdw.session.theme.color.auto);
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
		checkThemeColorSetup(mdw.session.theme.color.mode);
	}
}

function checkThemeColorSetup(color_mode) {
	let document_color_mode = document.documentElement.getAttribute('data-theme-color');
	let cookie_color_mode = getCookieValue('CactiColorMode');

	if (document_color_mode !== color_mode || cookie_color_mode !== color_mode) {
        refreshLocalStorage();
		setDocumentAttribute('theme-color', color_mode)
		setCookieValue('CactiColorMode', color_mode);
		initializeGraphs(true);
	}
}

function preparePopOver(html) {
	const container = 'mdw-GridContainer-PopOver';
	const overlay = 'mdw-GridContainer-Overlay';

	const popover = $('#'+container);
	const screenOverlay = $('#'+overlay);

	if ( popover !== 'undefined' && screenOverlay !== 'undefined' ) {

		let title = popover.find('.mdw-PopOverTitle:first');
		let content = popover.find('.mdw-PopOverContent:first');
		let footer = popover.find('.mdw-PopOverFooter:first');

		content.html(html);
		title.html( content.find('.cactiTableTitleRow:first').detach() );
		footer.html( content.find('.saveRow:first').detach() );

		popover.find('button[value="cancel"]')
				.attr('onclick', '')
				.on('click', function(e) {
					e.preventDefault();
					e.stopPropagation();
					togglePopOver(false);
					return false;
				});

		popover.find('#action_confirm')
				.on('submit', function(e) {
					togglePopOver(false);
						//popover.find('.mdw-PopOverElements').empty();
				});

		togglePopOver( true);
	}
}

function togglePopOver(force) {
	let popover = $('.mdw-PopOver');
	if (popover !== 'undefined') {
		if (typeof force == 'boolean') {
			popover.toggleClass('hidden', (force !== true))
		}else {
			popover.toggleClass('hidden');
		}
	}
}

function fullScreen(event) {
	if (!document.fullscreenElement) {
		document.documentElement.requestFullscreen().then( r => fullScreenChangeHandler() );
	}else if (document.exitFullscreen) {
		document.exitFullscreen().then( r => fullScreenChangeHandler() );
	}
}

function fullScreenChangeHandler() {
	if (document.fullscreenElement) {
		$('.compact_nav_icon[data-helper="fullScreen"]>i').removeClass('ti-maximize').addClass('ti-minimize');
	}else {
		$('.compact_nav_icon[data-helper="fullScreen"]>i').addClass('ti-maximize').removeClass('ti-minimize');
	}
}


function kioskMode(event = false) {
	if (event === false) {
		setDocumentAttribute('kiosk-mode', 'off');
		if(isMobile.any() != null) {
			$('#mdw-Main').off('click');
		}
	}else {
		toggleCactiNavigationBox(event);
		setDocumentAttribute('kiosk-mode', 'on');
		if(isMobile.any() != null) {
			$('#mdw-Main').off('click').on('click', function(e) {
				let tap;
				mdw.cache.tab.count++;

				if(mdw.cache.tab.count === 1) {
					mdw.cache.tab.clientX = e.clientX;
					mdw.cache.tab.clientY = e.clientY;

					tap = setTimeout(function(){
						mdw.cache.tab.count = 0;
						mdw.cache.tab.clientX = 0;
						mdw.cache.tab.clientY = 0;
					},300);
				}else if (mdw.cache.tab.count === 2) {
					if(Math.abs(e.clientX-mdw.cache.tab.clientX) < 10 && Math.abs(e.clientY-mdw.cache.tab.clientY) < 10) {
						e.preventDefault();
						clearTimeout(tap);
						mdw.cache.tab.count = 0;
						mdw.cache.tab.clientX = 0;
						mdw.cache.tab.clientY = 0;
						kioskMode(false);
					}
				}else {
					mdw.cache.tab.count = 0;
					mdw.cache.tab.clientX = 0;
					mdw.cache.tab.clientY = 0;
					kioskMode(false);
				}
			});
		}
	}
}

function setHotKeys() {
	if(mdw.cache.classes.includes('hotkeys')) {
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
					togglePopOver( false);
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
					togglePopOver( false);
					break;
				default:
					alert(event);

			}
			return false;
		});
	}
}

function loadScript(className, url='') {
	if(!urlPath) {
		let location = window.location.pathname;
		let dirname = location.substring(0, location.lastIndexOf("/") + 1);
		urlPath = (dirname.search('/install/') !== -1) ? dirname + '../' : dirname;
	}

	if(mdw.cache.classes.includes(className) === false) {
		$.ajax({
			dataType: 'script',
			cache: false,
			async: false,
			url: urlPath + url,
			success: mdw.cache.classes.push(className)
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

function searchToHighlight(event) {
	let caller = $(event.currentTarget);
	let helper = caller.attr('data-helper');
	let container = $('[class^="mdw-ConsoleNavigationBox"][data-helper="' + helper + '"]').find('.navBox-content');

	let keyword = $(this).val();
	let pattern = '.*' + keyword.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '.*';
	let re = new RegExp(pattern,'gmiu');

	$("li.menuitem", container).removeClass('hide');
	$("a[role='menuitem'], li.menuitem", container).unmark({
		done: function() {
			if(keyword) {
				$("a[role='menuitem'], li.menuitem", container).markRegExp(re, {
					"accuracy": "complementary",
					"separateWordSearch": false,
				});
				$("li.menuitem", container).not(":has(mark)").addClass('hide');
			}
		}
	});
}

function get_theme_content() {

    let midWinter_Color_Mode = mdw.session.theme.color.mode;
    let midWinter_Color_Mode_Auto = mdw.session.theme.color.auto;
    let midWinter_Font_Size = mdw.session.theme.font.zoom;
  //  let midWinter_widthNavigationBox_dashboards = storage.get('midWinter_widthNavigationBox_dashboards');
    let midWinter_Animations = mdw.session.theme.boxes.animated
    let midWinter_ShownFontSizeValue = parseFloat(midWinter_Font_Size) + 25;
    let midWinter_Auto_Table_Layout = mdw.session.theme.mobile.autoTableLayout;
    let midWinter_Controls_SubTitle = mdw.session.theme.controls.subTitle;

    return '<ul class="nav">'
        +   '<li class="menuitem" id="menu_user_action">'
        +       '<a class="menu_parent" href="#" inert>'
        +           '<i class="menu_glyph ti ti-photo"></i>'
        +           '<span>General</span>'
        +       '</a>'
        +       '<ul>'
        +           '<li>'
        +				'<div>' + 'Animations' + '</div>'
        +				'<div>'
        +					'<label class="checkboxSwitch">'
        +						'<input data-scope="theme" id="mdw_themeAnimations" data-func="toggleGuiAnimations" class="formCheckbox" type="checkbox" name="mdw_themeAnimations" '+(midWinter_Animations === 'on' ? 'checked' : '')+'>'
        +						'<span class="checkboxSlider checkboxRound"></span>'
        +					'</label>'
        +					'<label class="checkboxLabel checkboxLabelWanted" for="mdw_themeAnimations"></label>'
        +                   '<output id="mdw_themeAnimationsValue">'+ midWinter_Animations +'</output>'
        +				'</div>'
        +           '</li>'
        +           '<li>'
        +				'<div>' + 'Show Control Names' + '</div>'
        +				'<div>'
        +					'<label class="checkboxSwitch">'
        +						'<input data-scope="theme" id="mdw_themeControlsSubTitle" data-func="toggleControlsSubtitle" class="formCheckbox" type="checkbox" name="mdw_themeControlsSubtitle" '+(midWinter_Controls_SubTitle === 'on' ? 'checked' : '')+'>'
        +						'<span class="checkboxSlider checkboxRound"></span>'
        +					'</label>'
        +					'<label class="checkboxLabel checkboxLabelWanted" for="mdw_themeControlsSubTitle"></label>'
        +                   '<output id="mdw_themeControlsSubTitleValue">'+ midWinter_Controls_SubTitle +'</output>'
        +				'</div>'
        +           '</li>'
        +           '<li>'
        +				'<div>' + 'Zoom Level' + '</div>'
        +				'<div>'
        +						'<input data-scope="theme" class="mdw_themeFontSize" id="mdw_themeFontSize" onchange="changeGuiFontSize()" oninput="changeGuiFontSize(false)" type="range" min="50" max="100" step="2.5" value="'+ midWinter_Font_Size +'" defaultValue="75">'
        +                       '<output id="mdw_themeFontSizeValue">'+midWinter_ShownFontSizeValue+'%</output>'
        +				'</div>'
        +           '</li>'
        +       '</ul>'
        +   '</li>'
        +   '<li class="menuitem" id="menu_user_action">'
        +       '<a class="menu_parent" href="#" inert>'
        +           '<i class="menu_glyph ti ti-color-swatch"></i>'
        +           '<span>Colors</span>'
        +       '</a>'
        +       '<ul>'
        +           '<li>'
        +				'<div>' + usePreferredColorTheme + '</div>'
        +				'<div>'
        +					'<label class="checkboxSwitch">'
        +						'<input data-scope="theme" id="mdw_themeColorModeAuto" data-func="toggleColorModeAuto" class="formCheckbox" type="checkbox" name="mdw_themeColorModeAuto" '+(midWinter_Color_Mode_Auto === 'on' ? 'checked' : '')+'>'
        +						'<span class="checkboxSlider checkboxRound"></span>'
        +					'</label>'
        +					'<label class="checkboxLabel checkboxLabelWanted" for="mdw_themeColorModeAuto"></label>'
        +                   '<output id="mdw_themeColorModeAutoValue">'+ midWinter_Color_Mode_Auto +'</output>'
        +				'</div>'
        +           '</li>'

        +       '</ul>'
        +   '</li>'
        +   '<li class="menuitem" id="menu_user_action">'
        +       '<a class="menu_parent" href="#" inert>'
        +           '<i class="menu_glyph ti ti-device-mobile"></i>'
        +           '<span>Mobile Devices</span>'
        +       '</a>'
        +       '<ul>'
        +           '<li>'
        +				'<div>' + 'Auto Table Layout' + '</div>'
        +				'<div>'
        +					'<label class="checkboxSwitch">'
        +						'<input data-scope="theme" id="mdw_themeAutoTableLayout" data-func="toggleAutoTableLayout" class="formCheckbox" type="checkbox" name="mdw_themeAutoTableLayout" '+(midWinter_Auto_Table_Layout === 'on' ? 'checked' : '')+'>'
        +						'<span class="checkboxSlider checkboxRound"></span>'
        +					'</label>'
        +					'<label class="checkboxLabel checkboxLabelWanted" for="mdw_themeAutoTableLayout"></label>'
        +                   '<output id="mdw_themeAutoTableLayoutValue">'+ midWinter_Auto_Table_Layout +'</output>'
        +				'</div>'
        +           '</li>'
        +       '</ul>'
        +   '</li>'
        +'</ul>';
}
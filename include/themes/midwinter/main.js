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

select2Setup = {
	displayDefaultLabel : true
}

/* registry object to use separate namespaces */
const registry = {};

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
    },
    obj: { box: {}, ctrl: {} },
	actions: {},
	domMap: {
		cactiContent:       '#cactiContent',
		cactiNavRight:      '#navigation_right',
		cactiBreadcrumb:    '#breadCrumbBar',
		cactiTable:         '.cactiTable',
		sortInfo:           'div.sortinfo',
		mdwMain:            '#mdw-Main',
		mdwGrid:            '#mdw-GridContainer',
		mdwPopOver:         '#mdw-GridContainer-PopOver',
		mdwActionBarTop:    '#mdw-ActionBarTop',
		cactiAuthBody:   	'.cactiAuthBody',				// login rewrite
		cactiAuthArea:   	'.cactiAuthArea legend',
		cactiAuthTable:  	'.cactiAuthTable',
		cactiAuthForm:   	'.cactiAuth',
		versionInfo:     	'.versionInfo',
		loginUsername:   	'#login_username'
	},
    cache: {
        classes:    [],
        path:       'include/js/',
        storage:    Storages.localStorage,
        tap:        { count: 0, clientX: 0, clientY: 0 }
    }
}

/* --- Inside main.js --- */
$(document).on('mdw:pluginStateUpdate', function(e) {
	const data = e.originalEvent.detail;
	const navManager = mdw.obj.ctrl.nav;
	const btnManager = mdw.obj.ctrl.btn;

	// sync Button visibility
	if (btnManager && typeof btnManager.show === 'function') {
		data.hasContent ? btnManager.show(data.helper) : btnManager.hide(data.helper);
	}

	// sync Box Presence via the new method
	// This handles both showing and hiding, including Dock recalculation
	if (navManager && typeof navManager.setBoxPresence === 'function') {
		navManager.setBoxPresence(data.helper, data.hasContent);
	}
});

/**
 * Helper to safely move elements using the mapping
 * @param {string} sourceKey - Key from mdw.domMap
 * @param {string} targetKey - Key from mdw.domMap
 */
mdw.actions.relocate = function(sourceKey, targetKey) {
	const $source = $(mdw.domMap[sourceKey]);
	const $target = $(mdw.domMap[targetKey]);

	if ($source.length && $target.length) {
		$source.detach().appendTo($target);
		return true;
	}
	return false;
};

/**
 * initialize global hotkey dispatcher
 * uses event.code for numbers to avoid shift-key character translation issues
 */
mdw.actions.initHotKeys = function() {
	// prevent multiple listener attachments
	if (mdw.cache.hotkeysActive) return;

	document.addEventListener('keydown', (event) => {
		// skip if user is focusing a form element
		if (['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName)) return;

		const parts = [];
		if (event.ctrlKey)  parts.push('CTRL');
		if (event.altKey)   parts.push('ALT');
		if (event.shiftKey) parts.push('SHIFT');

		// detect if the key is a digit to handle shift-translation (e.g. " instead of 2)
		let keyName = '';
		if (event.code.startsWith('Digit')) {
			// extract the actual digit from "Digit2" -> "2"
			keyName = event.code.replace('Digit', '');
		} else {
			keyName = event.key.toUpperCase();
		}

		if (keyName === 'ESCAPE') keyName = 'ESC';
		parts.push(keyName === ' ' ? 'SPACE' : keyName);

		const combo = parts.join('+');

		// find element with matching data-hotkey attribute
		const targetEl = document.querySelector(`[data-hotkey="${combo}"]`);

		if (targetEl) {
			// stop cacti and browser defaults immediately
			event.preventDefault();
			event.stopImmediatePropagation();

			// trigger click via jquery to ensure your add() logic fires
			$(targetEl).trigger('click');
			console.log(`[HotKey] Success: ${combo}`);
		}
	}, true); // use capture phase to catch event before cacti scripts

	mdw.cache.hotkeysActive = true;
};


mdw.uiObserver = {
	instance: null,

	init: function() {
		// PREVENTION: If an observer is already running, do nothing
		if (this.instance) {
			return;
		}

		const targetNode = document.body;
		const config = { childList: true, subtree: true };

		this.instance = new MutationObserver((mutations) => {
			let needsRelocate = false;

			for (let mutation of mutations) {
				if (mutation.type === 'childList') {
					// Check if any of the added nodes is the Cacti content we want to move
					mutation.addedNodes.forEach(node => {
						const $node = $(node);
						// Does this node match our source map for Cacti content?
						if ($node.is(mdw.domMap.cactiNavRight) || $node.find(mdw.domMap.cactiNavRight).length) {
							needsRelocate = true;
						}
					});
				}
			}

			if (needsRelocate) {
				/*
                 * 1. PAUSE: We temporarily disconnect to prevent an infinite loop
                 * while we move elements ourselves.
                 */
				this.instance.disconnect();

				/*
                 * 2. ACTION: Relocate the content and refresh everything
                 */
				mdw.actions.relocate('cactiNavRight', 'mdwMain');

				// This triggers the Plugin-Refresh and re-checks the table columns
				setupDefaultElements();
				setupThemeActions();

				/*
                 * 3. RESUME: Re-observe after the changes are done
                 */
				this.instance.observe(document.body, { childList: true, subtree: true });
			}
		});

		this.instance.observe(targetNode, config);
		console.log('[Midwinter] MutationObserver started once.');
	}
};


/* load and (auto) register navigationBox as well as its plugins and configuration */
loadScript('navigationBox',   mdw.cache.path + 'navigationBox.js');
loadScript('navigationBox.tree',  mdw.cache.path + 'navigationBox.tree.js');
loadScript('navigationBox.tableLayout',  mdw.cache.path + 'navigationBox.tableLayout.js');
loadScript('navigationBox.filter',  mdw.cache.path + 'navigationBox.tableFilter.js');
loadScript('config', 'include/themes/midwinter/config.js');


restoreLocalStorage();

/**
 * main entry point for the midwinter theme
 * called by cacti once the document is ready
 */
function themeReady() {
	/* setup basic theme layout and manager instances */
	setupTheme();

	/* initialize global hotkey dispatcher via dom attributes */
	mdw.actions.initHotKeys()

	/* process initial elements and trigger plugin refreshes */
	setupDefaultElements();

	/* start the mutation observer to handle future cacti ajax updates */
	if (mdw.uiObserver && typeof mdw.uiObserver.init === 'function') {
		mdw.uiObserver.init();
	}

updateNavigation();
updateAjaxAnchors();
setThemeColor();

	//hideConsoleNavigation();
	setupThemeActions();

	// set PWA Layout attribute
	checkPWADisplayMode();

	/* disable the initial theme loading overlay */
	if (typeof themeLoader === 'function') {
		themeLoader('off');
	}

	console.log('[Midwinter] UI fully initialized and reactive.');
}


function checkPWADisplayMode() {
	// initial setup
	let displayModeQuery = window.matchMedia('(display-mode: standalone)');
	setDocumentAttribute('theme-pwa', (displayModeQuery.matches) ? 'on' : 'off' );

	// monitor changes
	displayModeQuery.addEventListener('change', (e) => {
		setDocumentAttribute('theme-pwa', (e.matches) ? 'on' : 'off' );

	});

	// TODO conflict with fullscreen mode
}

function hideConsoleNavigation() {
	$('#mdw-SideBarContainer [class^="mdw-ConsoleNavigationBox"]').removeClass('visible');
	$('#mdw-SideBarContainer [class^="mdw-ConsoleNavigationBox"][data-helper!="tree"]').removeClass('visible');
	//$('.compact_nav_icon[data-helper!="tree"]').removeClass('selected');
}

function updateAjaxAnchors() {
	$('a.pic, a.linkOverDark, a.linkEditMain, a.console, a.hyperLink, a.tab').not('[href^="http"], [href^="https"], [href^="#"], [href^="mailto"], [target="_blank"]').off('click').on('click', function(event) {
		event.preventDefault();
		event.stopPropagation();

		/* determine the page name */
		let href = $(this).attr('href');

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
		$('#mdw-SideBarContainer [class^="mdw-ConsoleNavigationBox"]').removeClass('visible');

		loadUrl({url:href});
		return false;
	});
}

function midWinterNavigation(element) {

	let action   		= element.parent().html();
	let category 		= element.closest('.menuitem').children('.menu_parent').first().children('span').text();
	let helper   		= element.closest('div[class^="mdw-ConsoleNavigationBox"]').data('helper');
	let rubric		 	= element.closest('div[class^="mdw-ConsoleNavigationBox"]').data('title');

	const btnManager = new cactiButton();

	$('#navBreadCrumb .rubric').html( '<span>'+rubric+'</span>').attr('data-helper', helper).off().on(
		"click", {param: 'force_open', filter: 'reset'}, btnManager.toggleConsoleNavigationBox
	);
	$('#navBreadCrumb .category').empty().append($('<span>').text(category)).attr('data-helper', helper).off().on(
		"click", {param: 'force_open', filter: category}, btnManager.toggleConsoleNavigationBox
	);
	$('#navBreadCrumb .action').html( action );

	if (helper !== undefined) {
	//	$('.compact_nav_icon[data-helper="'+helper+'"]').addClass('mdw-active');
	//	$('.compact_nav_icon[data-helper!="'+helper+'"]').removeClass('mdw-active');
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

/**
 * Main theme setup logic
 * Handles login UI rewrites, main layout transformation and component initialization
 */
function setupTheme() {
	/* -- login, logout -- rewrite */
	const $authBody = $(mdw.domMap.cactiAuthBody);
	const $authArea = $(mdw.domMap.cactiAuthArea);

	if ($authBody.length !== 0 && $authArea.text() !== 'WELCOME TO CACTI') {
		/* modify login area title */
		$authArea.text('WELCOME TO CACTI');

		/* detach legacy table layout */
		const $authTable = $(mdw.domMap.cactiAuthTable).detach();
		const $authForm = $(mdw.domMap.cactiAuthForm);

		/* suppress issues with autofocus while page is loading */
		$('<input id="suppress_autofocus" type="text" style="display:none;" tab-index="-1" autofocus>').prependTo($authForm);

		/* define password placeholders for rewrite */
		const pwdPlaceholders = {
			'current': 'Current Password',
			'password': 'New Password',
			'password_confirm': 'Confirm Password'
		};

		/* process table elements and transform to modern layout */
		$authTable.find("input, button, label").each(function() {
			const $el = $(this);
			const type = $el.attr('type');
			const id = $el.attr('id');

			if ((type === 'password' || type === 'text') && $el.attr('name') !== undefined) {
				$el.appendTo($authForm);

				if (type === 'password') {
					if (pwdPlaceholders[id]) {
						$el.attr('placeholder', pwdPlaceholders[id]);
					}
					// Insert toggle icon using template literal
					$(`<i class="ti ti-lock" data-helper="${id}" data-func="togglePwdInputField"></i>`).insertAfter($el);
				}
			} else {
				$el.appendTo($authForm);
			}
		});

		/* handle welcome message and version info */
		const welcomeMsg = $authTable.find('td').eq(0).html();
		$(`<span>${welcomeMsg}</span>`).prependTo($authForm);

		$(mdw.domMap.versionInfo).detach().appendTo($authBody);
		$('<i class="ti ti-user"></i>').insertAfter(mdw.domMap.loginUsername);
	}

	/* --- start layout redesign --- */
	const cactiContent = document.querySelector(mdw.domMap.cactiContent);
	if (cactiContent) {
		const gridHTML = `
			<div id="mdw-GridContainer" class="mdw-GridContainer">
				<div id="mdw-GridContainer-Overlay" class="mdw-GridContainer-Overlay mdw-PopOver hidden"></div>
				<div id="mdw-GridContainer-PopOver" class="mdw-GridContainer-PopOver mdw-PopOver hidden">
					<div id="mdw-PopOverTitle" class="mdw-PopOverElements mdw-PopOverTitle"></div>
					<div id="mdw-PopOverContent" class="mdw-PopOverElements mdw-PopOverContent"></div>
					<div id="mdw-PopOverFooter" class="mdw-PopOverElements mdw-PopOverFooter"></div>
				</div>
				<div id="mdw-ConsoleNavigation" class="mdw-ConsoleNavigation"></div>
				<div id="mdw-ConsolePageHead" class="mdw-ConsolePageHead">
					<div id="navBreadCrumb" class="navBreadCrumb">
						<div class="home"><a href="${urlPath}index.php" class="pic">Home</a></div>
						<div class="rubric"></div><div class="category"></div><div class="action"></div>
					</div>
					<div id="navSearch" class="navSearch"></div>
					<div id="navFilter" class="navFilter"></div>
					<div id="navControl" class="navControl"></div>
				</div>
				<div id="mdw-Main" class="mdw-Main"></div>
				<div id="mdw-ActionBar" class="mdw-ActionBar">
					<div id="mdw-ActionBarTop" class="mdw-ActionBarTop"></div>
					<div id="mdw-ActionBarMiddle" class="mdw-ActionBarMiddle"></div>
					<div id="mdw-ActionBarBottom" class="mdw-ActionBarBottom"></div>
				</div>
			</div>`;

		const breadcrumb = document.querySelector(mdw.domMap.cactiBreadcrumb);
		if (breadcrumb) {
			breadcrumb.insertAdjacentHTML('beforebegin', gridHTML);
		}

		mdw.actions.relocate('cactiNavRight', 'mdwMain');
		cactiContent.remove();
	}

	/* -- redesign console navigation area */
	if ($('.mdw-ConsoleNavigation').length !== 0) {
		if ($('#navBackdrop').length === 0) {
			$('.mdw-ConsoleNavigation').empty().prepend('<div class="compact_nav_icon_menu">' +
				'<div class="compact_nav_icon hint--info hint--right hint--rounded" data-subtitle="Console" id="navBackdrop" aria-label="Console" role="button" tabindex="0">' +
				'<div class="navBackdrop"></div>' +
				'</div></div>');
			if (cactiConsoleAllowed) {
				$("#navBackdrop").on('click', function() {
					/* hide open menu boxes first and remove menu selection */
					$('[class^="cactiConsoleNavigation"]').removeClass('visible');
					loadUrl({url:urlPath+'index.php'});
				});
			} else {
				$("#navBackdrop").on('click', function() {
					window.open('https://cacti.net', '_blank');
				});
			}
		}

		if ($('#compact_tab_menu').length === 0 && $('#compact_user_menu').length === 0) {
			$('.mdw-ConsoleNavigation').append(
				'<div class="compact_nav_icon_menu" id="compact_tab_menu"></div>' +
				'<div class="compact_nav_icon_menu" id="compact_user_menu"></div>'
			);

			/**********************************************************************************************************/

			if (typeof cactiNavigation === 'function') {
				const navOptions = {dock: {top: false, bottom: false}, window: {enabled: false}};

				const navManager = new cactiNavigation(navOptions);
				const boxManager = new cactiBox();
				const btnManager = new cactiButton();

				// Register instances globally using the new manager
				mdw.obj.ctrl.nav = navManager;
				mdw.obj.ctrl.box = boxManager;
				mdw.obj.ctrl.btn = btnManager;

				const processedBoxConfigs = midwinter.navigationBox.buildConfigs(uiConfig.boxes);
				navManager.checkConfigurationIntegrity(processedBoxConfigs, uiConfig.buttons);

				uiConfig.buttons.forEach(btn => btnManager.add(btn));

				/* boxes are added; their child classes handle their own context menus internally */
				processedBoxConfigs.forEach(box => {
					boxManager.add(box);
					boxManager.restore(box.helper);
				});

			} else {
				console.error('[Midwinter] cactiNavigation class is not defined. Check script loading.');
			}

			/**********************************************************************************************************/
		}
	}

	/* CLEAN UP */
	$('#menu_main_console').remove();
	$('a.menu_parent').removeClass('mdw-active').prop('inert', true);

	/* visibility check for settings icon */
	const $settingsBox = $('[class^="mdw-ConsoleNavigationBox"][data-helper="settings"]');
	$('[class^="compact_nav_icon"][data-helper="settings"]').toggleClass('hide', $settingsBox.has('li').length === 0);

	$('#main').off('resize').on('resize', function() {
		$('#main .saveRowParent').width($(this).width());
	});
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

	document.addEventListener("fullscreenchange", fullScreenChangeHandler);

	// make popover draggable
	$('#mdw-GridContainer-PopOver').draggable({
		containment: '#mdw-GridContainer',
		scroll: false,
		start: function() {
			$(this).css('transform', 'translateX(0)');
		}
	});

	$('.graphPage').off().on('resize', function() { alert(); })
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
				navBox_input_field.val('').trigger('input').trigger('blur');
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
	let popover = $(mdw.domMap.mdwPopOver); // Use Mapping

	if (popover.hasClass('hidden')) {
		let storage = Storages.localStorage;

		// --- Cleanup legacy Cacti elements using Mapping ---
		$(mdw.domMap.cactiBreadcrumb + ', .cactiPageHead, .cactiShadow, .cactiConsoleNavigationArea').detach();

		if ($('.stickyContainer').length) {
			$('.stickyContainer').remove();
		}

		// --- Ensure elementsOnTop container is available ---
		if (!$("#elementsOnTop").length) {
			$('<div id="elementsOnTop" class="elementsOnTop">' +
				'<div id="tableTitleOnTop" class="elementOnTop tableTitleOnTop"></div>' +
				'<div id="tableNavBarOnTop" class="elementOnTop tableNavBarOnTop"></div>' +
				'<div id="tableActionOnTop" class="elementOnTop tableActionOnTop"></div>' +
				'<div id="tableTabsOnTop" class="elementOnTop tableTabsOnTop"></div>' +
				'</div>').prependTo(mdw.domMap.cactiNavRight); // Use Mapping
		}

		$(".elementOnTop").empty();
		$("#mdw-ActionBarMiddle").empty();

		// --- Move table elements to Midwinter containers ---
		if ($("#main > div.tabs:first").length) {
			$("#main > div.tabs:first").closest('div').detach().appendTo('#tableTabsOnTop');
		}

		if ($("#main div.cactiTableTitleRow").length) {
			const $titleRow = $("#main div.cactiTableTitleRow:first");
			$titleRow.children(".cactiTableTitle").detach().appendTo('#tableTitleOnTop');
			$titleRow.children(".cactiTableAction:not(:empty)").detach().appendTo('#tableActionOnTop');
			$titleRow.children(".cactiTableButton:not(:empty)").detach().appendTo('#mdw-ActionBarMiddle');
			$titleRow.remove();

			if ($("#main div.saveRow").length) {
				$("#main div.saveRow").detach().appendTo('#tableActionOnTop');
			} else if ($("#main div.actionsDropdown").length) {
				$("#main div.actionsDropdown > div > span").detach().appendTo('#tableActionOnTop');
			}

			if ($("#main div.navBarNavigation").length) {
				$("#main div.navBarNavigation:first").clone().appendTo('#tableNavBarOnTop');
			}
		}

		// *************************************************************************************************************

		/* PLUGIN REFRESH TRIGGER */
		if (typeof midwinter.navigationBox.refreshPlugins === 'function') {
			midwinter.navigationBox.refreshPlugins();
		}

		// *************************************************************************************************************


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
		$('.tableHeader th').has('i.fa-sort').on('mouseenter', function () {
				$(this).addClass("tableHeaderColumnHover");
			}).on('mouseleave', function () {
				$(this).removeClass("tableHeaderColumnHover");
			});


		//$('td:nth-child(2), th:nth-child(2)').addClass('hide');


		$('input#filter, input#rfilter').addClass('ui-state-default ui-corner-all');

		$('input[type="text"], input[type="password"], input[type="checkbox"], textarea').not('image').addClass('ui-state-default ui-corner-all');

		// really shitty workaround to make custom row checkboxes clickable again. :(
		$('tr[id*="line"]:not(.disabled_row)').each(function (data) {
			$(this).find('.formCheckboxLabel').removeAttr('for');
		});

		// Turn file buttons into jQueryUI buttons
		$('.import_label').button();
		$('.import_button').on('change', function () {
			text = this.value;
			setImportFile(text);
		});

		setImportFile(noFileSelected);

		function setImportFile(fileText) {
			$('.import_text').text(fileText);
		}

		// Hide the graph icons until you hover
		$('.graphDrillDown').on('mouseenter', function () {
				element = $(this);

				// hide the previously shown element
				if (element.attr('id').replace('dd', '') != graphMenuElement && graphMenuElement > 0) {
					$('#dd' + graphMenuElement).find('.iconWrapper:first').hide(300);
				}

				clearTimeout(graphMenuTimer);
				graphMenuTimer = setTimeout(function () {
					showGraphMenu(element);
				}, 400);
			}).on('mouseleave', function () {
				element = $(this);
				clearTimeout(graphMenuTimer);
				graphMenuTimer = setTimeout(function () {
					hideGraphMenu(element);
				}, 400);

				if (typeof spikeKillClose == 'function') {
					spikeKillClose();
				}
			});

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
		toggleConsoleNavigationBox(event);
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

/*
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
*/


function loadScript(className, url='') {
	if(!urlPath) {
		let location = window.location.pathname;
		let dirname = location.substring(0, location.lastIndexOf("/") + 1);
		urlPath = (dirname.search('/install/') !== -1) ? dirname + '../' : dirname;
	}

	if(mdw.cache.classes.includes(className) === false) {
		$.ajax({
			dataType: 'script',
			cache: true,
			async: false,
			url: urlPath + url,
			success: mdw.cache.classes.push(className)
		}).fail(function(html) {
			console.error('error');
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


function is_function(f_name) {
	return (typeof window[f_name] === 'function');
}




registry.midwinter = {
	navigationBox : {
		content: {
			dashboards: function(){
				let compact_tab_menu_content = '<ul class="nav">';

				if (cactiConsoleAllowed) {
					compact_tab_menu_content +=
						'<li class="menuitem" id="menu_home">'
						+    '<a class="menu_parent" href="#" inert>'
						+        '<i class="menu_glyph ignore ti ti-crown"></i>'
						+        '<span>'+cactiHome+'</span>'
						+    '</a>'
						+    '<ul>'
						+        '<li><a href="'+urlPath+'index.php" class="pic" role="menuitem">'+cactiConsole+'</a></li>'
						+    '</ul>'
						+'</li>';
				}

				//#todo : string handling list, preview
				if (cactiGraphsAllowed) {
					compact_tab_menu_content +=
						'<li class="menuitem" id="menu_tab_dashboard">'
						+    '<a class="menu_parent" href="#" inert>'
						+        '<i class="menu_glyph ignore ti ti-device-desktop-analytics"></i>'
						+        '<span>Views</span>'
						+    '</a>'
						+    '<ul>'
						+       '<li><a class="pic" role="menuitem" id="tab-graphs-list-view" href="' + urlPath + 'graph_view.php?action=list">List</a></li>'
						+       '<li><a class="pic" role="menuitem" id="tab-graphs-pre-view" href="' + urlPath + 'graph_view.php?action=preview">Preview</a></li>'
						+    '</ul>'
						+'</li>';
				}

				let showMisc = false;
				$('.maintabs nav ul li a.lefttab').each(function() {
					if ($(this).attr('id') !== 'tab-console' && $(this).attr('id') !== 'tab-graphs') {
						showMisc = true;
						return true;
					}
				});
				if (showMisc) {
					compact_tab_menu_content +=
						'<li class="menuitem" id="menu_tab_miscellaneous">'
						+   '<a class="menu_parent" href="#" inert>'
						+       '<i class="menu_glyph ignore ti ti-puzzle"></i>'
						+       '<span>'+cactiMisc+'</span>'
						+   '</a>'
						+'<ul>';
				}

				$('.maintabs nav ul li a.lefttab').each( function() {
					let id = $(this).attr('id');

					if (id === 'tab-graphs' && $(this).parent().hasClass('maintabs-has-submenu') === false) {
						$(this).parent().addClass('maintabs-has-submenu');

						let submenu_tab_graphs_content =
							'<ul id="submenu-tab-graphs" class="submenuoptions" style="display:none;">'
							+ '<li><a id="tab-graphs-tree-view" href="' + urlPath + 'graph_view.php?action=tree"><span>' + treeView + '</span></a></li>'
							+ '<li><a id="tab-graphs-list-view" href="' + urlPath + 'graph_view.php?action=list"><span>' + listView + '</span></a></li>'
							+ '<li><a id="tab-graphs-pre-view" href="' + urlPath + 'graph_view.php?action=preview"><span>' + previewView + '</span></a></li>'
							+ '</ul>';

						$('<div class="dropdownMenu">' + submenu_tab_graphs_content + '</div>').appendTo('body');
					} else if ($(this).attr('href') !== urlPath + 'index.php') {
						compact_tab_menu_content += '<li><a class="pic" role="menuitem" href="' + $(this).attr('href') + '">' + $('.text_' + id).text() + '</a></li>';
					}
				});
				compact_tab_menu_content += '</ul></li></ul></div>';
				return compact_tab_menu_content;
			},
			settings: function() {
				let element_menu = $('#menu').html();
				if (element_menu === undefined) {
					element_menu = loadElement('menu', 'about.php', true);
				}
				return element_menu;
			},
			displayOptions: function() {
				return '<div class="displayOptions">'
						+ '<div class="displayOptionsTap">'
						+	'<label class="tab-label" for="tab-columns">Columns <i class="ti ti-chevron-down"></i></label>'
						+	'<input data-scope="theme" id="tab-columns" class="tab-input" type="checkbox" checked/>'
						+ 	'<div class="tab-columns tab-content"></div>'
						+ '</div>'
						+ '</div>';
			},
			help: function() {
				return '<ul class="nav">'
						+   '<li class="menuitem" id="menu_user_help">'
						+       '<a class="menu_parent" href="#" inert>'
						+           '<i class="menu_glyph ti ti-book"></i>'
						+           '<span>'+cactiGeneral+'</span>'
						+       '</a>'
						+       '<ul>'
						+           '<li><a class="pic" role="menuitem" href="'+urlPath+'about.php">'+aboutCacti+'</a></li>'
						+           '<li><a href="https://github.com/Cacti/documentation/blob/develop/README.md" target="_blank" rel="noopener noreferrer">'+cactiDocumentation+'</a></li>'
						+           '<li><a href="https://github.com/cacti" target="_blank" rel="noopener noreferrer">'+cactiProjectPage+'</a></li>'
						+           '<li><a href="https://www.cacti.net" target="_blank" rel="noopener noreferrer">'+cactiHome+'</></a></li>'
						+       '</ul>'
						+   '</li>'
						+   '<li class="menuitem" id="menu_user_issues">'
						+       '<a class="menu_parent" href="#" inert>'
						+           '<i class="menu_glyph ti ti-bug"></i>'
						+           '<span>'+reportABug+'</span>'
						+       '</a>'
						+       '<ul>'
						+           '<li><a href="https://github.com/Cacti/cacti/issues/new/choose" target="_blank" rel="noopener noreferrer">'+justCacti+'</></a></li>'
						+           '<li><a href="https://github.com/Cacti/documentation/issues/new/choose" target="_blank" rel="noopener noreferrer">'+cactiDocumentation+'</></a></li>'
						+           '<li><a href="https://github.com/Cacti/spine/issues/new/choose" target="_blank" rel="noopener noreferrer">'+cactiSpine+'</a></li>'
						+           '<li><a href="https://github.com/Cacti/rrdproxy/issues/new/choose" target="_blank" rel="noopener noreferrer">'+cactiRRDProxy+'</a></li>'
						+       '</ul>'
						+   '</li>'
						// +   '<li class="menuitem" id="menu_user_shortcuts">'
						// +       '<a class="menu_parent" href="#" inert>'
						// +           '<i class="menu_glyph ti ti-keyboard"></i>'
						// +           '<span>'+cactiKeyboard+'</span>'
						// +       '</a>'
						// +       '<ul>'
						// +           '<li><a href="#" class="dialog_client" data-scope="theme" data-func="togglePopOver">'+cactiShortcuts+'</a></li>'
						// +       '</ul>'
						// +   '</li>'
						+   '<li class="menuitem" id="menu_user_help">'
						+       '<a class="menu_parent" href="#" inert>'
						+           '<i class="menu_glyph ti ti-heart-handshake"></i>'
						+           '<span>'+cactiContributeTo+'</span>'
						+       '</a>'
						+       '<ul>'
						+           '<li><a href="https://forums.cacti.net/" target="_blank" rel="noopener noreferrer">'+cactiCommunityForum+'</a></li>'
						+           '<li><a href="https://github.com/cacti" target="_blank" rel="noopener noreferrer">'+cactiDevHelp+'</a></li>'
						+           '<li><a href="https://www.cacti.net/development/contribute" target="_blank" rel="noopener noreferrer">'+cactiDonate+'</a></li>'
						+           '<li><a href="https://translate.cacti.net" target="_blank" rel="noopener noreferrer">'+cactiTranslate+'</a></li>'
						+       '</ul>'
						+   '</li>'
						+   '</ul>';
			},
			user: function() {
					return '<ul class="nav">'
						+   '<li class="menuitem" id="menu_user_action">'
						+       '<a class="menu_parent" href="#" inert>'
						+           '<i class="menu_glyph ti ti-user-edit""></i>'
						+           '<span>'+cactiProfile+'</span>'
						+       '</a>'
						+       '<ul>'
						+           '<li><a class="pic" role="menuitem" href="'+urlPath+'auth_profile.php?action=edit&header=false">'+editProfile+'</a></li>'
						+           '<li><a href="'+urlPath+'auth_changepassword.php" style="">'+changePassword+'</a></li>'
						+           '<li><a href="'+urlPath+'logout.php">'+logout+'</a></li>'
						+       '</ul>'
						+   '</li>';
			},
			theme: function () {

				let midWinter_Color_Mode = mdw.session.theme.color.mode;
				let midWinter_Color_Mode_Auto = mdw.session.theme.color.auto;
				let midWinter_Font_Size = mdw.session.theme.font.zoom;
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
		}
	}
}
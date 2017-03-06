/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2017 The Cacti Group                                 |
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

var theme;
var myRefresh;
var userMenuTimer;
var graphMenuTimer;
var graphMenuElement=0;
var pulsating=true;
var pageLoaded=false;
var shiftPressed=false;
var messageTimer;
var myTitle;

var isMobile = {
	Android: function() {
		return navigator.userAgent.match(/Android/i);
	},
	BlackBerry: function() {
		return navigator.userAgent.match(/BlackBerry/i);
	},
	iOS: function() {
		return navigator.userAgent.match(/iPhone|iPad|iPod/i);
	},
	Opera: function() {
		return navigator.userAgent.match(/Opera Mini/i);
	},
	Windows: function() {
		return navigator.userAgent.match(/IEMobile/i);
	},
	any: function() {
		return (isMobile.Android() || isMobile.BlackBerry() || isMobile.iOS() || isMobile.Opera() || isMobile.Windows());
	}
};

/** basename - this function will return the basename
 *  of the php script called
 *  @args path - the document.url
 *  @args suffix - remove the named suffix from the file */
function basename(path, suffix) {
	var b = path;
	var lastChar = b.charAt(b.length - 1);

	if (lastChar === '/' || lastChar === '\\\\') {
		b = b.slice(0, -1);
	}

	b = b.replace(/^.*[\\/\\\\]/g, '');

	if (typeof suffix === 'string' && b.substr(b.length - suffix.length) == suffix) {
		b = b.substr(0, b.length - suffix.length);
	}

	return b;
}

/** getTimestampFromDate - Simple function to convert a MySQL Date
 * to a timestamp */
function getTimestampFromDate(dateStamp) {
	var dateParts = dateStamp.split(' ');
	var timeParts = dateParts[1].split(':');

	dateParts = dateParts[0].split('-');
	var date = new Date(dateParts[0], parseInt(dateParts[1], 10) - 1, dateParts[2], timeParts[0], timeParts[1]);

	return date.getTime() / 1000;
}

/** getQueryString - this function will return the value
 *  of the get request variable defined as input.
 *  @args name - the variable name to return */
function getQueryString(name) {
	var match = RegExp('[?&]' + name + '=([^&]*)').exec(window.location.search);
	return match && decodeURIComponent(match[1].replace(/\+/g, ' '));
}


/** delayKeyup - this function will delay the keyup to
 *  provide debouncing of input strokes on the keyboard
 *  this preventing your backend server from becoming overloaded
 *  usage: $("#yourid").delayKeyup(function(){ console.log('do something'); }, 500);
 *  @args name - the variable name to return */
$.fn.delayKeyup = function(callback, ms){
	var timer = 0;
	$(this).keyup(function(){
		clearTimeout (timer);
		timer = setTimeout(callback, ms);
	});
	return $(this);
};

/** bindFirst - Function insures that the event is found to the top
 * of the event stack. */
$.fn.bindFirst = function(which, handler) {
	var $el = $(this);
	$el.unbind(which, handler);
	$el.bind(which, handler);

	var events = $._data($el[0]).events;
	var registered = events[which];
	registered.unshift(registered.pop());

	events[which] = registered;
}

/** replaceOptions - function replaces the options in a select dropdown */
$.fn.replaceOptions = function(options, selected) {
	var self, $option;

	this.empty();
	self = this;

	$.each(options, function(index, option) {
		if (selected == option.value) {
			$option = $('<option></option>')
			.attr('value', option.value)
			.prop('selected', true)
			.text(option.text);
		}else{
			$option = $('<option></option>')
			.attr('value', option.value)
			.text(option.text);
		}
		self.append($option);
	});
}

/** textWidth - This function will return the natural width of a string
 *  without any wrapping. */
$.fn.textWidth = function(text){
	var org = $(this);
	var html = $('<span style="display:none;white-space:nowrap;position:absolute;width:auto;left:-9999px">' + (text || org.html()) + '</span>');
	if (!text) {
		html.css('font-family', org.css('font-family'));
		html.css('font-weight', org.css('font-weight'));
	}
	$('body').append(html);
	var width = html.width();
	html.remove();
	return width;
}

/** classes - This function will return an array of all
 *  classes of an element */
$.fn.classes = function(callback) {
	var classes = [];
	$.each(this, function(i, v) {
		var splitClassName = v.className.split(/\s+/);
		for (var j in splitClassName) {
			var className = splitClassName[j];
			if (-1 === classes.indexOf(className)) {
				classes.push(className);
			}
		}
	});
	if ('function' === typeof callback) {
		for (var i in classes) {
			callback(classes[i]);
		}
	}
	return classes;
};

/** These three functions will set the cursor into
 *  a textbox or textara and optionally select characters */
$.fn.setCursorPosition = function(position) {
	if (this.length == 0) return this;
	return this.setSelection(position, position);
};

$.fn.selectRange = function(start, end) {
	if(!end) end = start;
	return this.each(function() {
		if (this.setSelectionRange) {
			this.focus();
			this.setSelectionRange(start, end);
		} else if (this.createTextRange) {
			var range = this.createTextRange();
			range.collapse(true);
			range.moveEnd('character', end);
			range.moveStart('character', start);
			range.select();
		}
	});

	//return this;
};

$.fn.focusEnd = function() {
	this.setCursorPosition($(this).val().length);
	return this;
};

$.fn.serializeObject = function() {
	var arrayData, objectData;
	arrayData = this.serializeArray();
	objectData = {};

	$.each(arrayData, function() {
		var value;

		if (this.value != null) {
			value = this.value;
		} else {
			value = '';
		}

		if (objectData[this.name] != null) {
			if (!objectData[this.name].push) {
				objectData[this.name] = [objectData[this.name]];
			}

			objectData[this.name].push(value);
		} else {
			objectData[this.name] = value;
		}
	});

	return objectData;
};

// Plugin to apply numeric format for tablesorter
$.tablesorter.addParser({
	id: 'numberFormat',
	is: function(s) {
		return /^[0-9]?[0-9,\.]*$/.test(s);
	},
	format: function(s) {
		return $.tablesorter.formatFloat(s.replace(/,/g, ''));
	},
	type: 'numeric'
});

/** Mini jquery plugin to determine if an element has a scrollbar present */
(function($) {
    $.fn.hasScrollBar = function() {
        return this.get(0).scrollHeight > this.outerHeight();
    }
})(jQuery);

/** Mini jquery plugin to create a bind to show/hide events */
(function ($) {
	$.each(['show', 'hide'], function (i, ev) {
		var el = $.fn[ev];
		$.fn[ev] = function () {
			this.trigger(ev);
			return el.apply(this, arguments);
		};
	});
})(jQuery);

/** applySelectorVisibility - This function set's the initial visibility
 *  of graphs for creation. Is will scan the against preset variables
 *  taking action as required to enable or disable rows. */
function applySelectorVisibilityAndActions() {
	// Change for accessibility  
	$('input[type="checkbox"], input[type="radio"]').click(function() {
		if ($(this).is(':checked')) {
			$(this).attr('aria-checked', 'true');
		}else{
			$(this).attr('aria-checked', 'false');
		}
	});

	// Apply disabled/enabled status first for Graph Templates
	$('tr[id^="gt_line"]').each(function(data) {
		var id = $(this).attr('id');
		var search = id.substr(7);
		if ($.inArray(search, gt_created_graphs) >= 0) {
			$(this).addClass('disabled_row');
			$(this).find(':checkbox').prop('disabled', true);
		}
	});

	// Create Actions for Rows
	$('tr[id^="gt_line"].selectable:not(.disabled_row)').find('td').not('.checkbox').each(function(data) {
		$(this).click(function(data) {
			$(this).closest('tr').toggleClass('selected');
			var checkbox = $(this).parent().find(':checkbox');
			checkbox.prop('checked', !checkbox.is(':checked'));
		});
	});

	// Create Actions for Checkboxes
	$('tr[id^="gt_line"].selectable').find('input.checkbox').click(function(data) {
		if (!$(this).is(':disabled')) {
			$(this).closest('tr').toggleClass('selected');
		}
	});

	// Create Actions for Rows
	$('tr[id^="line"].selectable:not(.disabled_row)').find('td').not('.checkbox').each(function(data) {
		$(this).click(function(data) {
			$(this).closest('tr').toggleClass('selected');
			var checkbox = $(this).parent().find(':checkbox');
			checkbox.prop('checked', !checkbox.is(':checked'));
		});
	});

	// Create Actions for Checkboxes
	$('tr[id^="line"].selectable').find('input.checkbox').click(function(data) {
		if (!$(this).is(':disabled')) {
			$(this).closest('tr').toggleClass('selected');
		}
	});
}

/** dqUpdateDeps - When a user changes the Graph dropdown for a data query
 *  we have to check to see if those graphs are already created.
 *  @arg snmp_query_id - The snmp query id the is current */
function dqUpdateDeps(snmp_query_id) {
	dqResetDeps(snmp_query_id);

	var snmp_query_graph_id = $('#sgg_'+snmp_query_id).val();
	var removeSelectAll = false;

	// Check if select all is clicked
	var allChecked = $('#all_'+snmp_query_id).is(':checked');

	// Next for Data Queries
	$('tr[id^="dqline'+snmp_query_id+'_"]').each(function(data) {
		var id = $(this).attr('id');
		var pieces = id.split('_');
		var dq = pieces[0].substr(6);
		var hash = pieces[1];

		if ($.inArray(hash, created_graphs[snmp_query_graph_id]) >= 0) {
			if ($(this).hasClass('selected')) {
				removeSelectAll = true;
			}

			$(this).addClass('disabled_row');
			$(this).removeClass('selected');
			$(this).removeClass('selectable');
			$(this).find(':checkbox').prop('disabled', true).prop('checked', false);
		}else{
			removeSelectAll = true;
		}
	});

	if (allChecked && removeSelectAll) {
		$('#all_'+snmp_query_id).prop('checked', false);
	}

	$('tr[id^="dqline'+snmp_query_id+'_"]').not('.disabled_row').each(function() {
		$(this).find(':checkbox').click(function() {
			$(this).closest('tr').toggleClass('selected');
		});

		$(this).find('td').not('.checkbox').each(function() {
			$(this).click(function() {
				checked = $(this).closest('tr').find(':checkbox').is(':checked');
				$(this).closest('tr').toggleClass('selected').find(':checkbox').prop('checked', !checked);;
			});
		});
	});
}

/** dqResetDeps - This function will make all rows selectable.
 *  It is done just before a new data query is checked.
 *  @arg snmp_query_id - The snmp query id the is current */
function dqResetDeps(snmp_query_id) {
	$('tr[id^="dqline'+snmp_query_id+'_"]').addClass('selectable').removeClass('disabled_row').find(':checkbox').prop('disabled', false);
}

/** SelectAll - This function will select all non-disabled rows
 *  @arg attrib - The Graph Type either graph template, or data query */
function SelectAll(attrib, checked) {
	if (attrib == 'chk') {
		if (checked == true) {
			$('tr[id^="line"]:not(.disabled_row)').each(function(data) {
				$(this).addClass('selected');
				$(this).find(':checkbox').prop('checked', true);
			});
		}else{
			$('tr[id^="line"]:not(.disabled_row)').each(function(data) {
				$(this).removeClass('selected');
				$(this).find(':checkbox').prop('checked', false);
			});
		}
	}else if (attrib == 'sg') {
		if (checked == true) {
			$('tr[id^="gt_line"]:not(.disabled_row)').each(function(data) {
				$(this).addClass('selected');
				$(this).find(':checkbox').prop('checked', true);
			});
		}else{
			$('tr[id^="gt_line"]:not(.disabled_row)').each(function(data) {
				$(this).removeClass('selected');
				$(this).find(':checkbox').prop('checked', false);
			});
		}
	}else{
		var attribSplit = attrib.split('_');
		var dq   = attribSplit[1];

		if (checked == true) {
			$('tr[id^="dqline'+dq+'\_"]:not(.disabled_row)').each(function(data) {
				$(this).addClass('selected');
				$(this).find(':checkbox').prop('checked', true);
			});
		}else{
			$('tr[id^="dqline'+dq+'\_"]:not(.disabled_row)').each(function(data) {
				$(this).removeClass('selected');
				$(this).find(':checkbox').prop('checked', false);
			});
		}
	}
}

/* graph filtering */
function applyTimespanFilterChange(objForm) {
	strURL = '?predefined_timespan=' + objForm.predefined_timespan.value;
	strURL = strURL + '&predefined_timeshift=' + objForm.predefined_timeshift.value;
	document.location = strURL;
}

/** cactiReturnTo - This function simply returns to the previous page
 *  @args href - the previous page */
function cactiReturnTo(href) {
	if (typeof window.history.pushState !== 'undefined') {
		window.history.pushState({page:href}, myTitle , href);
	}

	if (typeof href == 'string') {
		href = href + (href.indexOf('?') > 0 ? '&':'?') + 'header=false';
		loadPageNoHeader(href);
	}else{
		href = document.location.href;
		href = href + (href.indexOf('?') > 0 ? '&':'?') + 'header=false';
		loadPageNoHeader(href);
	}
}

/** applySkin - This function re-asserts all javascript behavior to a page
 *  that can't be set using a live attrbute 'on()' */
function applySkin() {
	if (!theme || theme == 'classic') {
		theme = 'classic';

		// debounce submits
		$('form').submit(function() {
			$('input[type="submit"], button[type="submit"]').not('.import, .export').prop('disabled', true);
		});
	}else{
		$('input[type="submit"], input[type="button"]').button();

		// debounce submits
		$('form').submit(function() {
			$('input[type="submit"], button[type="submit"]').not('.import, .export').button('disable');
		});
	}

	$('.ui-tooltip').remove();

	setupSortable();

	setupBreadcrumbs();

	applyTableSizing();

	setupPageTimeout();

	CsrfMagic.end();

	setupSpecialKeys();

	setupCollapsible();

	ajaxAnchors();

	applySelectorVisibilityAndActions();

	if (typeof themeReady == 'function') {
		themeReady();
	}

	// Add tooltips to graph drilldowns
	$('.drillDown').tooltip({
		content: function() {
			return $(this).prop('title');
		}
	});

	// Debug message actions
	$('table.debug').click(function() { 
		if ($(this).find('table').is(':visible')) { 
			$(this).find('table').slideUp('fast'); 
		} else { 
			$(this).find('table').slideDown('fast'); 
		}
	});

	$(document).tooltip({
		items: 'div.cactiTooltipHint, span.cactiTooltipHint, a',
		content: function() {
			var element = $(this);

			if (element.is('div')) {
				var text = $(this).find('span').html();
			}else if (element.is('span') || element.is('a')) {
				var text = $(this).prop('title');
			}
			return text;
		}
	}).tooltip('close').keydown(function(event) {
		if (event.keyCode == 16) {
			shiftPressed = true;
		}else{
			shiftPressed = false;
		}
	});

	// remove stray tooltips
	$(document).tooltip('close');

	$('#main').show();
}

function loadPage(href) {
	$.get(href, function(html) {
		var htmlObject  = $(html);
		var matches     = html.match(/<title>(.*?)<\/title>/);

		if (matches !== null) {
			var htmlTitle   = matches[1];
			var breadCrumbs = htmlObject.find('#breadcrumbs').html();
			var content     = htmlObject.find('#main').html();

			$('#main').empty().hide();
			$('title').text(htmlTitle);
			$('#breadcrumbs').html(breadCrumbs);
			$('div[class^="ui-"]').remove();
			$('#main').html(content);

			if (typeof window.history.pushState !== 'undefined') {
				window.history.pushState({page: href}, htmlTitle, href);
			}

			myTitle = htmlTitle;
		}else{
			$('#main').empty().hide();
			$('#main').html(html);
		}

		hrefParts = href.split('?');
		href = basename(hrefParts[0]);

		if (basename(href) != '') {
			$('#menu').find('.pic').removeClass('selected');
			$('#menu').find("a[href*='/"+basename(href)+"']").addClass('selected');
		}

		applySkin();

		window.scrollTo(0, 0);

		return false;
	});

	return false;
}

function loadPageNoHeader(href) {
	$.get(href, function(data) {
		$('#main').empty().hide();
		$('div[class^="ui-"]').remove();
		$('#main').html(data);

		hrefParts = href.split('?');
		href = basename(hrefParts[0]);

		if (basename(href) != '') {
			$('#menu').find('.pic').removeClass('selected');
			$('#menu').find("a[href*='/"+basename(href)+"']").addClass('selected');
		}

		applySkin();

		window.scrollTo(0, 0);

		return false;
	});

	return false;
}

function ajaxAnchors() {
	$('a.pic, a.linkOverDark, a.linkEditMain, a.hyperLink, a.tab, a.iconLink').not('[href^="http"], [href^="https"], [href^="#"]').unbind().click(function(event) {
		event.preventDefault();
		event.stopPropagation();

		/* update menu selection */
		if ($(this).hasClass('pic')) {
			$('.pic').removeClass('selected');
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

		/* execute an ajax request to load the data */
		href = $(this).attr('href');

		if (href == '#') {
			return false;
		}

		if (href != null) {
			pageName = basename(href);
		}

		loadPage(href);

		return false;
	});

	$(window).bind('popstate', function(event) {
		handlePopState();
	});

	$('#filter, #rfilter').keyup(function(event) {
		if (event.keyCode == 8 && $(this).val() == '') {
			handlePopState();
		}
	});
}

function handlePopState() {
	var href = document.location.href;

	if (href.indexOf('#') == -1) {
		document.location = href;
	}
}

function setupCollapsible() {
	storage=$.localStorage;

	$('.collapsible').each(function(data) {
		id=$(this).attr('id')+'_cs';
		state = storage.get(id);
		if (state == 'hide') {
			$(this).addClass('collapsed');
			$(this).nextUntil('tr.spacer').hide();
			$(this).find('i').removeClass('fa-angle-double-up').addClass('fa-angle-double-down');
			storage.set(id, 'hide');
		}
	});

	$('.collapsible').click(function(data) {
		id=$(this).attr('id')+'_cs';
		if ($(this).find('i').hasClass('fa-angle-double-up')) {
			$(this).addClass('collapsed');
			$(this).nextUntil('tr.spacer').slideUp('slow');
			$(this).find('i').removeClass('fa-angle-double-up').addClass('fa-angle-double-down');
			storage.set(id, 'hide');
		}else{
			$(this).removeClass('collapsed');
			$(this).nextUntil('tr.spacer').slideDown('slow');
			$(this).nextUntil('tr.spacer').each(function(data) {
				$(this).find('input, select').change();
			});
			$(this).find('i').removeClass('fa-angle-double-down').addClass('fa-angle-double-up');
			storage.set(id, 'show');
		}
	});
}

function openUserMenu() {
	$('.user').removeClass('usermenuup').addClass('usermenudown');
	$('.menuoptions').slideDown(120, 'easeInOutCubic');
}

function closeUserMenu() {
	$('.user').removeClass('usermenudown').addClass('usermenuup');
	$('.menuoptions').slideUp(120, 'easeInOutCubic');
}

function setupUserMenu() {
	$('.menuoptions').mouseenter(function() {
		clearTimeout(userMenuTimer);
	}).mouseleave(function() {
		if ($('.menuoptions').is(':visible')) {
			userMenuTimer = setTimeout('closeUserMenu()', 1000);
		}
	});

	$('.user').mouseenter(function(data) {
		clearTimeout(userMenuTimer);
		userMenuOpenTimer = setTimeout('openUserMenu()', 400);
		//openUserMenu();
	}).mouseleave(function(data) {
		if ($('.menuoptions').is(':visible')) {
			userMenuTimer = setTimeout('closeUserMenu()', 1000);
		}else{
			clearTimeout(userMenuOpenTimer);
		}
	});
}

function setupSpecialKeys() {
	$('#filter, #rfilter').unbind('keypress').attr('title', 'Press Ctrl+Shift+X to Clear Filter');
	$('#filter, #rfilter').tooltip({ closed: true }).on('focus', function() { $('#filter').tooltip('close') }).on('click', function() { $(this).tooltip('close'); });

	$('#filter, #rfilter').bind('keypress', 'ctrl+shift+x', function() {
		$('#filter, #rfilter').val('').css('text-align', 'left').submit();
	});

	if (!isMobile.any()) {
		$('#filter, #rfilter').focus();
	}
}

/** setupSortable - This function will set all actions for sortable columns
 *  every time a page is regenerated */
function setupSortable() {
	$('th.sortable').on('click', function(e) {
		var $target = $(e.target);
		if (!$target.is('.ui-resizable-handle')) {
			var page=$(this).find('.sortinfo').attr('sort-page');
			var column=$(this).find('.sortinfo').attr('sort-column');
			var direction=$(this).find('.sortinfo').attr('sort-direction');
			if (shiftPressed) {
				sortAdd='&add=true';
			}else{
				sortAdd='';
			}
			$.get(page+(page.indexOf('?') > 0 ? '&':'?')+'sort_column='+column+'&sort_direction='+direction+'&header=false'+sortAdd, function(data) {
				$('#main').empty().hide()
				$('div[class^="ui-"]').remove();
				$('#main').html(data);
				applySkin();
			});
		}
	});

	// Setup tool tips for all titles to match the jQueryUI theme
	$('i, th, img, input, label, select, button').tooltip({ closed: true }).on('focus', function() { $('#filter, #rfilter').tooltip('close') }).on('click', function() { $(this).tooltip('close'); });
}

function setupBreadcrumbs() {
	$('#breadcrumbs > li > a').click(function(event) {
		event.preventDefault();
		event.stopPropagation();
		href =  $(this).attr('href');
		if (href != '#') {
			href = href.replace('tree_content', 'tree');
			$(this).prop('href', href);
			document.location = href;
		}
	});
}

/** saveTableWidths - This function will initialize table widths on page
 *  load.  It includes the 'initial' boolean to initialize the page */
function saveTableWidths(initial) {
	// We will save columns widths persistently
	storage=$.localStorage;

	// Initialize table width on the page
	$('.cactiTable').each(function(data) {
		var key    = $(this).attr('id');
		var sizes  = storage.get(key);
		var items  = sizes ? sizes: new Array();
		var width  = $(document).width();

		// if the table width changes, reset the columns
		if (key !== undefined && sizes == undefined) {
			storage.remove(key);
			sizes = new Array();
			items = new Array();
			items[0] = width;
			sizes[0] = width;
		}else if (key !== undefined && initial) {
			if (items.length > 0) {
				if (items[0] + 18 < width) {
					storage.remove(key);
					sizes = new Array();
					items = new Array();
					items[0] = width;
					sizes[0] = width;
				}
			}
		}

		var i = 1;
		if (key !== undefined) {
			if (initial && items.length) {
				$('#'+key).find('th.ui-resizable').each(function(data) {
					if (parseInt(items[i]) == 0) {
						items[i] = $(this).width();
						sizes[i] = items[i];
					}

					if (items[i] != 0) {
						$(this).css('width', items[i]);
						$(this).attr('resizeWidth', items[i]);
					}
					i++;
				});
			}else{
				$('#'+key).find('th.ui-resizable').each(function(data) {
					sizes[i] = $(this).width();

					if (sizes[i] != 0) {
						$(this).css('width', items[i]);
						$(this).attr('resizeWidth', sizes[i]);
					}
					i++;
				});

				if (i > 1) {
					storage.set(key, sizes);
				}
			}
		}
	});
}

/** applyTableSizing - This function sets all table headers to be resizable using
 *  the jQueryUI function resizable.  It also calls the saveTableWidths function
 *  to store the widths in localStorage every time a column is resized. */
function applyTableSizing() {
	$('.tableHeader').not('.tableFixed').find('th').resizable({
		handles: 'e',

		start: function(event, ui) {
			colWidth     = $(this).width();
			originalSize = ui.size.width;

			if (originalSize == 0) {
				originalSize = $(this).width();
			}

			$(ui.originalElement).siblings().each(function(data) {
				$(this).attr('resizeWidth', $(this).width());
			});
		 },

		resize: function(event, ui) {
			var resizeDelta = ui.size.width - originalSize;
			var newColWidth = colWidth + resizeDelta;
			nextWidth = $(ui.element).next().attr('resizeWidth');
			$(ui.element).next().css('width', nextWidth-resizeDelta);
			$(ui.element).prevUntil('tr').each(function(data) {
				$(this).css('width', $(this).attr('resizeWidth'));
			});
			$(this).css('height', 'auto');
		},

		stop: function(event, ui) {
			saveTableWidths(false);
		}
	});

	saveTableWidths(true);
}

/** setupPageTimeout - This function will setup the page timeout based upon
 *  the plugin developers $refresh requirements.  It also sets up a location
 *  to redirect the user to upon timeout.  This is generally done for automatically
 *  logging out the user, but can be used for simply refreshing the page as in the
 *  case of the Graphs page. */
function setupPageTimeout() {
	if (typeof myRefresh != 'undefined') {
		clearTimeout(myRefresh);
	}

	if (typeof refreshMSeconds != 'undefined') {
		myRefresh = setTimeout(function() {
			if (refreshIsLogout) {
				document.location = urlPath+'logout.php?action=timeout';
			}else{
				if (previousPage != '') {
					refreshPage = previousPage;
				}

				/* fix coner case with tree refresh */
				refreshPage = refreshPage.replace('action=tree&', 'action=tree_content&');

				$.get(refreshPage, function(data) {
					$('#main').empty().hide()
					$('div[class^="ui-"]').remove();
					$('#main').html(data);
					applySkin();
				});
			}
		}, refreshMSeconds);
	}
}

function pulsateStart(element) {
	pulsating=true;
	pulsate(element);
}

function pulsate(element) {
	if (pulsating) {
		$(element || this).delay(100).fadeOut(800).delay(100).fadeIn(800, pulsate);
	}
}

function pulsateStop(element) {
	pulsating=false;
}

$(function() {
	setupUserMenu();

	applySkin();

	$('#navigation_right').show();
});

/* Graph related javascript functions */
if (typeof urlPath == 'undefined') {
	var urlPath = '';
}

var graphPage  = urlPath+'graph_view.php';
var pageAction = 'preview';

function clearGraphFilter() {
	href = graphPage+'?action='+pageAction+'&clear=1';

	new_href = href.replace('action=tree&', 'action=tree_content&');

	$.get(new_href+'&header=false', function(data) {
		$('#main').empty().hide()
		$('div[class^="ui-"]').remove();
		$('#main').html(data);
		applySkin();
	});
}

function saveGraphFilter(section) {
	href=graphPage+'?action=save'+
		'&columns='+$('#columns').val()+
		'&graphs='+$('#graphs').val()+
		'&graph_template_id='+$('#graph_template_id').val()+
		'&predefined_timespan='+$('#predefined_timespan').val()+
		'&predefined_timeshift='+$('#predefined_timeshift').val()+
		'&thumbnails='+$('#thumbnails').is(':checked');

	$.get(href+'&header=false&section='+section, function(data) {
		$('#text').show().text('Filter Settings Saved').fadeOut(2000, function() {
			$('#text').empty();
		});
	});
}

function applyGraphFilter() {
	href = graphPage+'?action='+pageAction+
		'&rfilter='+$('#rfilter').val()+
		'&host_id='+$('#host_id').val()+
		'&columns='+$('#columns').val()+
		'&graphs='+$('#graphs').val()+
		'&graph_template_id='+$('#graph_template_id').val()+
		'&thumbnails='+$('#thumbnails').is(':checked');

	new_href = href.replace('action=tree&', 'action=tree_content&');

	$.get(new_href+'&header=false', function(data) {
		$('#main').hide()
		$('div[class^="ui-"]').remove();
		$('#main').html(data);

		applySkin();

		if (typeof window.history.pushState !== 'undefined') {
			window.history.pushState({ page: href }, 'Preview Mode', href);
		}

		myTitle = 'Preview Mode';
	});
}

function applyGraphTimespan() {
	var href     = graphPage+'?action='+pageAction+'&header=false';
	var new_href = href.replace('action=tree&', 'action=tree_content&');

	$.get(new_href+'?action='+pageAction+'&header=false'+
		'&predefined_timespan='+$('#predefined_timespan').val()+
		'&predefined_timeshift='+$('#predefined_timeshift').val(), function(data) {
		$('#main').empty().hide()
		$('div[class^="ui-"]').remove();
		$('#main').html(data);
		applySkin();
	});
}

function refreshGraphTimespanFilter() {
	var json = { 
		custom: 1, 
		button_refresh_x: 1, 
		date1: $('#date1').val(), 
		date2: $('#date2').val(), 
		predefined_timespan: $('#predefined_timespan').val(), 
		predefined_timeshift: $('#predefined_timeshift').val(),
		__csrf_magic: csrfMagicToken
	};

	var href     = graphPage+'?action='+pageAction+'&header=false';
	var new_href = href.replace('action=tree&', 'action=tree_content&');
	$.post(new_href, json).done(function(data) {
		$('#main').empty().hide()
		$('div[class^="ui-"]').remove();
		$('#main').html(data);
		applySkin();
	});
}

function timeshiftGraphFilterLeft() {
	var json = { 
		move_left_x: 1, 
		move_left_y: 1, 
		date1: $('#date1').val(), 
		date2: $('#date2').val(), 
		predefined_timespan: $('#predefined_timespan').val(), 
		predefined_timeshift: $('#predefined_timeshift').val(),
		__csrf_magic: csrfMagicToken
	};

	var href     = graphPage+'?action='+pageAction+'&header=false';
	var new_href = href.replace('action=tree&', 'action=tree_content&');
	$.post(new_href, json).done(function(data) {
		$('#main').empty().hide()
		$('div[class^="ui-"]').remove();
		$('#main').html(data);
		applySkin();
	});
}

function timeshiftGraphFilterRight() {
	var json = { 
		move_right_x: 1, 
		move_right_y: 1, 
		date1: $('#date1').val(), 
		date2: $('#date2').val(), 
		predefined_timespan: $('#predefined_timespan').val(), 
		predefined_timeshift: $('#predefined_timeshift').val(),
		__csrf_magic: csrfMagicToken
	};

	var href     = graphPage+'?action='+pageAction+'&header=false';
	var new_href = href.replace('action=tree&', 'action=tree_content&');
	$.post(new_href, json).done(function(data) {
		$('#main').empty().hide()
		$('div[class^="ui-"]').remove();
		$('#main').html(data);
		applySkin();
	});
}

function clearGraphTimespanFilter() {
	var json = { 
		button_clear: 1, 
		date1: $('#date1').val(), 
		date2: $('#date2').val(), 
		predefined_timespan: $('#predefined_timespan').val(), 
		predefined_timeshift: $('#predefined_timeshift').val(),
		__csrf_magic: csrfMagicToken
	};

	var href     = graphPage+'?action='+pageAction+'&header=false';
	var new_href = href.replace('action=tree&', 'action=tree_content&');
	$.post(new_href, json).done(function(data) {
		$('#main').empty().hide()
		$('div[class^="ui-"]').remove();
		$('#main').html(data);
		applySkin();
	});
}

function removeSpikesStdDev(local_graph_id) {
	strURL = 'spikekill.php?method=stddev&local_graph_id='+local_graph_id;
	$.getJSON(strURL, function(data) {
		redrawGraph(local_graph_id);
		$('#spikeresults').remove();
		$('body').append('<div id="spikeresults" style="overflow-y:scroll;" title="SpikeKill Results"></div>');
		$('#spikeresults').html(data.results);
		$('#spikeresults').dialog({ width:1100, maxHeight: 600 });
	});
}

function removeSpikesVariance(local_graph_id) {
	strURL = "spikekill.php?method=variance&local_graph_id="+local_graph_id;
	$.getJSON(strURL, function(data) {
		redrawGraph(local_graph_id);
		$('#spikeresults').remove();
		$('body').append('<div id="spikeresults" style="overflow-y:scroll;" title="SpikeKill Results"></div>');
		$('#spikeresults').html(data.results);
		$('#spikeresults').dialog({ width:1100, maxHeight: 600 });
	});
}

function removeSpikesInRange(local_graph_id) {
	strURL = 'spikekill.php?avgnan=last&local_graph_id='+local_graph_id+'&outlier-start='+graph_start+'&outlier-end='+graph_end;
	$.getJSON(strURL, function(data) {
		redrawGraph(local_graph_id);
		$('#spikeresults').remove();
		$('body').append('<div id="spikeresults" style="overflow-y:scroll;" title="SpikeKill Results"></div>');
		$('#spikeresults').html(data.results);
		$('#spikeresults').dialog({ width:1100, maxHeight: 600 });
	});
}

function removeRangeFill(local_graph_id) {
	strURL = 'spikekill.php?method=float&avgnan=last&local_graph_id='+local_graph_id+'&outlier-start='+graph_start+'&outlier-end='+graph_end;
	$.getJSON(strURL, function(data) {
		redrawGraph(local_graph_id);
		$('#spikeresults').remove();
		$('body').append('<div id="spikeresults" style="overflow-y:scroll;" title="SpikeKill Results"></div>');
		$('#spikeresults').html(data.results);
		$('#spikeresults').dialog({ width:1100, maxHeight: 600 });
	});
}

function dryRunStdDev(local_graph_id) {
	strURL = "spikekill.php?method=stddev&dryrun=true&local_graph_id="+local_graph_id;
	$.getJSON(strURL, function(data) {
		$('#spikeresults').remove();
		$('body').append('<div id="spikeresults" style="overflow-y:scroll;" title="SpikeKill Results"></div>');
		$('#spikeresults').html(data.results);
		$('#spikeresults').dialog({ width:1100, maxHeight: 600 });
	});
}

function dryRunVariance(local_graph_id) {
	strURL = "spikekill.php?method=variance&dryrun=true&local_graph_id="+local_graph_id;
	$.getJSON(strURL, function(data) {
		$('#spikeresults').remove();
		$('body').append('<div id="spikeresults" style="overflow-y:scroll;" title="SpikeKill Results"></div>');
		$('#spikeresults').html(data.results);
		$('#spikeresults').dialog({ width:1100, maxHeight: 600 });
	});
}

function dryRunSpikesInRange(local_graph_id) {
	strURL = 'spikekill.php?avgnan=last&dryrun=true&local_graph_id='+local_graph_id+'&outlier-start='+graph_start+'&outlier-end='+graph_end;
	$.getJSON(strURL, function(data) {
		redrawGraph(local_graph_id);
		$('#spikeresults').remove();
		$('body').append('<div id="spikeresults" style="overflow-y:scroll;" title="SpikeKill Results"></div>');
		$('#spikeresults').html(data.results);
		$('#spikeresults').dialog({ width:1100, maxHeight: 600 });
	});
}

function dryRunRangeFill(local_graph_id) {
	strURL = 'spikekill.php?method=float&avgnan=last&dryrun=true&local_graph_id='+local_graph_id+'&outlier-start='+graph_start+'&outlier-end='+graph_end;
	$.getJSON(strURL, function(data) {
		redrawGraph(local_graph_id);
		$('#spikeresults').remove();
		$('body').append('<div id="spikeresults" style="overflow-y:scroll;" title="SpikeKill Results"></div>');
		$('#spikeresults').html(data.results);
		$('#spikeresults').dialog({ width:1100, maxHeight: 600 });
	});
}

function redrawGraph(graph_id) {
	mainWidth = $('#main').width();
	myColumns = $('#columns').val();
	isThumb   = $('#thumbnails').is(':checked');

	if (isThumb) {
		myWidth = (mainWidth-(40*myColumns))/myColumns;
	}

	graph_height=$('#wrapper_'+graph_id).attr('graph_height');
	graph_width=$('#wrapper_'+graph_id).attr('graph_width');

	$.getJSON(urlPath+'graph_json.php?rra_id=0'+
		'&local_graph_id='+graph_id+
		'&graph_start='+graph_start+
		'&graph_end='+graph_end+
		'&graph_height='+graph_height+
		'&graph_width='+graph_width+
		(isThumb ? '&graph_nolegend=true':''),
		function(data) {
			if (isThumb && myWidth < data.image_width) {
				ratio=myWidth/data.image_width;
				data.image_width  *= ratio;
				data.image_height *= ratio;
				data.graph_width  *= ratio;
				data.graph_height *= ratio;
				data.graph_top    *= ratio;
				data.graph_left   *= ratio;
			}

			$('#wrapper_'+data.local_graph_id).html("<img class='graphimage' id='graph_"+data.local_graph_id+"' src='data:image/"+data.type+";base64,"+data.image+"' graph_start='"+data.graph_start+"' graph_end='"+data.graph_end+"' graph_left='"+data.graph_left+"' graph_top='"+data.graph_top+"' graph_width='"+data.graph_width+"' graph_height='"+data.graph_height+"' width='"+data.image_width+"' height='"+data.image_height+"' image_width='"+data.image_width+"' image_height='"+data.image_height+"' value_min='"+data.value_min+"' value_max='"+data.value_max+"'>");
		}
	);
}

function initializeGraphs() {
	$('a[id$="_mrtg"]').unbind('click').click(function(event) {
		event.preventDefault();
		event.stopPropagation();
		graph_id=$(this).attr('id').replace('graph_','').replace('_mrtg','');
		$.get(urlPath+'graph.php?local_graph_id='+graph_id+'&header=false', function(data) {
			$('#main').empty().hide()
			$('#breadcrumbs').append('<li><a id="nav_mrgt" href="#">Time Graph View</a></li>');
			$('#zoom-container').remove();
			$('div[class^="ui-"]').remove();
			$('#main').html(data);
			applySkin();
			clearTimeout(myRefresh);
		});
	});

	$('a[id$="_csv"]').unbind('click').click(function(event) {
		event.preventDefault();
		event.stopPropagation();
		graph_id=$(this).attr('id').replace('graph_','').replace('_csv','');
		document.location = urlPath+
			'graph_xport.php?local_graph_id='+graph_id+
			'&rra_id=0&view_type=tree&graph_start='+getTimestampFromDate($('#date1').val())+
			'&graph_end='+getTimestampFromDate($('#date2').val());
	});

	$('#form_graph_view').unbind('submit').on('submit', function(event) {
		event.preventDefault();
		event.stopPropagation();
		applyFilter();
	});

	mainWidth = $('#main').width()-40;
	myColumns = $('#columns').val();
	isThumb   = $('#thumbnails').is(':checked');

	if (isThumb) {
		myWidth = (mainWidth-(32*myColumns))/myColumns;
	}

	//$('div[id^="wrapper_"]').each(function() {
	$('.graphWrapper').each(function() {
		graph_id=$(this).attr('id').replace('wrapper_','');
		graph_height=$(this).attr('graph_height');
		graph_width=$(this).attr('graph_width');

		$.getJSON(urlPath+'graph_json.php?rra_id=0'+
			'&local_graph_id='+graph_id+
			'&graph_start='+graph_start+
			'&graph_end='+graph_end+
			'&graph_height='+graph_height+
			'&graph_width='+graph_width+
			(isThumb ? '&graph_nolegend=true':''),
			function(data) {
				if (isThumb && myWidth < data.image_width) {
					ratio=myWidth/data.image_width;
					data.image_width  *= ratio;
					data.image_height *= ratio;
					data.graph_width  *= ratio;
					data.graph_height *= ratio;
					data.graph_top    *= ratio;
					data.graph_left   *= ratio;
				}

				$('#wrapper_'+data.local_graph_id).html("<img class='graphimage' id='graph_"+data.local_graph_id+"' src='data:image/"+data.type+";base64,"+data.image+"' graph_start='"+data.graph_start+"' graph_end='"+data.graph_end+"' graph_left='"+data.graph_left+"' graph_top='"+data.graph_top+"' graph_width='"+data.graph_width+"' graph_height='"+data.graph_height+"' width='"+data.image_width+"' height='"+data.image_height+"' image_width='"+data.image_width+"' image_height='"+data.image_height+"' value_min='"+data.value_min+"' value_max='"+data.value_max+"'>");
				$("#graph_"+data.local_graph_id).zoom({
					inputfieldStartTime : 'date1', 
					inputfieldEndTime : 'date2', 
					serverTimeOffset : timeOffset
				});
				realtimeArray[data.local_graph_id] = false;
		});
	})

	$('#realtimeoff').unbind('click').click(function() {
		stopRealtime();
	});

	$('#ds_step').unbind('change').change(function() {
		realtimeGrapher();
	});

	$('a[id$="_util"]').unbind('click').click(function(event) {
		event.preventDefault();
		event.stopPropagation();
		graph_id=$(this).attr('id').replace('graph_','').replace('_util','');
		$.get(urlPath+'graph.php?action=zoom&header=false&local_graph_id='+graph_id+'&rra_id=0&graph_start='+getTimestampFromDate($('#date1').val())+'&graph_end='+getTimestampFromDate($('#date2').val()), function(data) {
			$('#main').empty().hide()
			$('div[class^="ui-"]').remove();
			$('#main').html(data);
			$('#breadcrumbs').append('<li><a id="nav_util" href="#">Utility View</a></li>');
			applySkin();
			clearTimeout(myRefresh);
		});
	});

	$('a[id$="_realtime"]').unbind('click').click(function(event) {
		event.preventDefault();
		event.stopPropagation();
		graph_id=$(this).attr('id').replace('graph_','').replace('_realtime','');

		if (realtimeArray[graph_id]) {
			$('#wrapper_'+graph_id).html(keepRealtime[graph_id]).change();
			$(this).html("<img class='drillDown' title='Click to view just this Graph in Realtime' alt='' src='"+urlPath+"images/chart_curve_go.png'>");
			$(this).find('img').tooltip().zoom({ inputfieldStartTime : 'date1', inputfieldEndTime : 'date2', serverTimeOffset : timeOffset });
			realtimeArray[graph_id] = false;
			setFilters();
		}else{
			keepRealtime[graph_id]  = $('#wrapper_'+graph_id).html();
			$(this).html("<i style='text-align:center;padding:0px;' title='Click again to take this Graph out of Realtime' class='drillDown fa fa-circle-o-notch fa-spin'/>");
			$(this).find('i').tooltip();
			realtimeArray[graph_id] = true;
			setFilters();
			realtimeGrapher();
		}
	});
}

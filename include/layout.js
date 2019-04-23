/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2019 The Cacti Group                                 |
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
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

const MESSAGE_LEVEL_NONE  = 0;
const MESSAGE_LEVEL_INFO  = 1;
const MESSAGE_LEVEL_WARN  = 2;
const MESSAGE_LEVEL_ERROR = 3;
const MESSAGE_LEVEL_CSRF  = 4;
const MESSAGE_LEVEL_MIXED = 5;

var theme;
var myRefresh;
var userMenuTimer;
var userMenuOpenTimer = null;
var graphMenuTimer;
var graphMenuElement = 0;
var pulsating = true;
var pageLoaded = false;
var shiftPressed = false;
var sessionMessage = null;
var sessionMessageOpen = null;
var sessionMessageTimer = null;
var myTitle;
var myHref;
var lastPage = null;
var statePushed = false;
var popFired = false;
var hostInfoHeight = 0;
var menuHideResponsive = null;
var marginLeftTree = null;
var marginLeftConsole = null;
var pageName;
var columnsHidden = 0;
var lastColumnsHidden = {};
var lastWidth = {};
var resizeDelta = 100;
var resizeTime = 0;
var resizeTimeout = false;
var formArray;
var pageWidth = null;
var isHover = false;
var hoverTimer = false;

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

/* simple ajax request queueing */
jQuery.ajaxQ = (function(){
	var id = 0, Q = {};

	jQuery(document).ajaxSend(function(e, jqx){
		jqx._id = ++id;
		Q[jqx._id] = jqx;
	});

	jQuery(document).ajaxComplete(function(e, jqx){
		delete Q[jqx._id];
	});

	return {
		abortAll: function(){
		var r = [];
		jQuery.each(Q, function(i, jqx){
			r.push(jqx._id);
			jqx.abort();
		});
		return r;
		}
	};
})();

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

/** base64_encode - Simple function to base64 encode a utf-8 string */
function base64_encode(string) {
	return escape(btoa(unescape(encodeURIComponent(string))));
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

/** bindFirst - Function ensures that the event is found at the top
 * of the event stack. */
$.fn.bindFirst = function(which, handler) {
	var $el = $(this);
	$el.off(which, handler);
	$el.on(which, handler);

	var events = $._data($el[0]).events;
	var registered = events[which];
	registered.unshift(registered.pop());

	events[which] = registered;
};

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
		} else {
			$option = $('<option></option>')
			.attr('value', option.value)
			.text(option.text);
		}
		self.append($option);
	});
};

/** textWidth - This function will return the natural width of a string
 *  without any wrapping. */
$.fn.textWidth = function(text){
	var org = $(this);
	var html = $('<span style="display:none;white-space:nowrap;position:absolute;width:auto;left:-9999px">' + (text || org.html()) + '</span>');
	if (!text) {
		html.css('font-family', org.css('font-family'));
		html.css('font-weight', org.css('font-weight'));
		html.css('font-size',   org.css('font-size'));
		html.css('padding',     org.css('padding'));
		html.css('margin',      org.css('margin'));
	}
	$('body').append(html);
	var width = html.width();
	html.remove();
	return width;
};

/** textBoxWidth - This function will return the natural width of a string
 *  without any wrapping. */
$.fn.textBoxWidth = function() {
	var org = $(this);
	var html = $('<span style="display:none;white-space:nowrap;position:absolute;width:auto;left:-9999px">' + org.val() + '</span>');
	html.css('font-family', org.css('font-family'));
	html.css('font-weight', org.css('font-weight'));
	html.css('font-size',   org.css('font-size'));
	html.css('padding',     org.css('padding'));
	html.css('margin',      org.css('margin'));
	$('body').append(html);
	var width = html.width();
	html.remove();
	return width;
};

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

$.fn.getObjectDetails = function() {
	var coords = {};

	coords.width  = $(this).outerWidth();
	coords.height = $(this).outerHeight();
	coords.left = 0;
	coords.top  = 0;

	$(this).parentsUntil('body').each(function(){
		coords.left += $(this).position().left;
		coords.top  += $(this).position().top;
	});

	return coords;
};

$.fn.serializeForm = function() {
	var arrayData, objectData;
	arrayData = this.serializeArray();
	formID = $(this).attr('id');
	arrayData = arrayData.concat(
		$('#'+formID+' input[type=checkbox]:not(:checked)').map(function() {
			return {"name": this.name, "value": $(this).is(':checked') ? 'on':''}
		}).get());

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

$.fn.serializeObject = function() {
	var arrayData, objectData, formID;
	arrayData = this.serializeArray();
	formID = $(this).attr('id');

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
    };
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

// helper function which selects row range when shift key is pressed during click
function updateCheckboxes(checkboxes, clicked_element) {
	var prev_checkbox = clicked_element.closest('table').find('[data-prev-check]:checkbox');
	if (!prev_checkbox.length) {
		return;
	}
	var check = prev_checkbox.attr('data-prev-check') == 'true';
	var start = checkboxes.index(prev_checkbox);
	var stop = checkboxes.index(clicked_element);
	var i = Math.min(start, stop);
	var j = Math.max(start, stop);
	for (var k = i; k <= j; k++) {
		var tr = $(checkboxes[k]).prop('checked', check).closest('tr');
		if (check) {
			tr.addClass('selected');
		} else {
			tr.removeClass('selected');
		}
	}
}

/** applySelectorVisibility - This function set's the initial visibility
 *  of graphs for creation. Is will scan the against preset variables
 *  taking action as required to enable or disable rows. */
function applySelectorVisibilityAndActions() {
	// Change for accessibility
	$('input[type="radio"]').off('click').on('click', function() {
		if ($(this).is(':checked')) {
			$(this).attr('aria-checked', 'true');
		} else {
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
	$('tr[id^="gt_line"].selectable:not(.disabled_row)').off('click').on('click', function(event) {
		selectUpdateRow(event, $(this));
	});

	// Create Actions for Rows
	$('tr[id^="line"].selectable').filter(':not(.disabled_row)').off('click').on('click', function(event) {
		selectUpdateRow(event, $(this));
	});
}

function disableSelection() {
	$('tr.selectable').css('-webkit-user-select','none');
	$('tr.selectable').css('-moz-user-select','none');
	$('tr.selectable').css('-ms-user-select','none');
	$('tr.selectable').css('-o-user-select','none');
	$('tr.selectable').css('user-select','none');
}

function enableSelection() {
	$('tr.selectable').css('-webkit-user-select','');
	$('tr.selectable').css('-moz-user-select','');
	$('tr.selectable').css('-ms-user-select','');
	$('tr.selectable').css('-o-user-select','');
	$('tr.selectable').css('user-select','');
}

/** selectUpdateRow - Highlight a selectable row combined with checkbox
 *  @arg event - The click event to support multiple selections
 *  @arg element - The jQuery selected object */
function selectUpdateRow(event, element) {
	var checkboxes = element.closest('table').find('input[type=checkbox]:not(:disabled)');

	if (event.shiftKey) {
		updateCheckboxes(checkboxes, element.find(':checkbox'));
	} else {
		element.toggleClass('selected');
		if (element.hasClass('selected')) {
			element.find(':checkbox').prop('checked', true).attr('aria-checked', 'true').attr('data-prev-check', 'true');
		} else {
			element.find(':checkbox').prop('checked', false).removeAttr('aria-checked').removeAttr('data-prev-check');
		}
	}

	if (element.closest('table').find(':checked').length) {
		disableSelection();
	} else {
		enableSelection();
	}
}

/** dqUpdateDeps - When a user changes the Graph dropdown for a data query
 *  we have to check to see if those graphs are already created.
 *  @arg snmp_query_id - The snmp query id the is current */
function dqUpdateDeps(snmp_query_id) {
	$('tr[id^="dqline'+snmp_query_id+'_"]').addClass('selectable').removeClass('disabled_row').find(':checkbox').prop('disabled', false);

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

			$(this).addClass('disabled_row').removeClass('selected').removeClass('selectable');
			$(this).find(':checkbox').prop('disabled', true).prop('checked', false);
		} else {
			removeSelectAll = true;
		}
	});

	if (allChecked && removeSelectAll) {
		$('#all_'+snmp_query_id).prop('checked', false);
	}

	$('tr[id^="dqline'+snmp_query_id+'_"]').not('.disabled_row').off('click').on('click', function(event) {
		selectUpdateRow(event, $(this));
	});
}

/** selectAll - This function will select all non-disabled rows
 *  @arg attrib - The Graph Type either graph template, or data query */
function selectAll(attrib, checked) {
	if (attrib == 'chk') {
		if (checked == true) {
			$('tr[id^="line"]:not(.disabled_row)').each(function(data) {
				$(this).addClass('selected');
				$(this).find(':checkbox').prop('checked', true).attr('aria-checked', 'true').attr('data-prev-check', 'true');
			});
			disableSelection();
		} else {
			$('tr[id^="line"]:not(.disabled_row)').each(function(data) {
				$(this).removeClass('selected');
				$(this).find(':checkbox').prop('checked', false).removeAttr('aria-checked').removeAttr('data-prev-check');
			});
		}
	} else if (attrib == 'sg') {
		if (checked == true) {
			$('tr[id^="gt_line"]:not(.disabled_row)').each(function(data) {
				$(this).addClass('selected');
				$(this).find(':checkbox').prop('checked', true).attr('aria-checked', 'true').attr('data-prev-check', 'true');
			});
			disableSelection();
		} else {
			$('tr[id^="gt_line"]:not(.disabled_row)').each(function(data) {
				$(this).removeClass('selected');
				$(this).find(':checkbox').prop('checked', false).removeAttr('aria-checked').removeAttr('data-prev-check');
			});
		}
	} else {
		var attribSplit = attrib.split('_');
		var dq   = attribSplit[1];

		if (checked == true) {
			$('tr[id^="dqline'+dq+'\_"]:not(.disabled_row)').each(function(data) {
				$(this).addClass('selected');
				$(this).find(':checkbox').prop('checked', true).attr('aria-checked', 'true').attr('data-prev-check', 'true');
			});
			disableSelection();
		} else {
			$('tr[id^="dqline'+dq+'\_"]:not(.disabled_row)').each(function(data) {
				$(this).removeClass('selected');
				$(this).find(':checkbox').prop('checked', false).removeAttr('aria-checked').removeAttr('data-prev-check');
			});
		}
	}
}

/* graph filtering */
function applyTimespanFilterChange() {
	var strURL;

	strURL = '?header=false&predefined_timespan=' + $('#predefined_timespan').val();
	strURL = strURL + '&predefined_timeshift=' + $('#predefined_timeshift').val();
	loadPageNoHeader(strURL);
}

/** cactiReturnTo - This function simply returns to the previous page
 *  @args href - the previous page */
function cactiReturnTo(href) {
	if (typeof href == 'string') {
		href = href + (href.indexOf('?') > 0 ? '&':'?') + 'header=false';
		loadPageNoHeader(href);
	} else {
		href = document.location.href;
		href = href + (href.indexOf('?') > 0 ? '&':'?') + 'header=false';
		loadPageNoHeader(href);
	}
}

/** applySkin - This function re-asserts all javascript behavior to a page
 *  that can't be set using a live attrbute 'on()' */
function applySkin() {
	pageName = basename($(location).attr('pathname'));

	$('#messageContainer').remove();

	if (!theme || theme == 'classic') {
		theme = 'classic';

		// debounce submits
		$('form').submit(function() {
			$('input[type="submit"], button[type="submit"]').not('.import, .export').prop('disabled', true);
		});
	} else {
		$('input[type="submit"], input[type="button"], button').button();

		// Handle re-index changes
		$('fieldset.reindex_methods').buttonset();

		// debounce submits
		$('form').submit(function() {
			$('input[type="submit"], button[type="submit"]').not('.import, .export').button('disable');
		});
	}

	setGraphTabs();

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

	makeFiltersResponsive();

	setupResponsiveMenuAndTabs();

	setupButtonStyle();

	// Debug message actions
	$('table.debug').off('click').on('click', function() {
		if ($(this).find('table').is(':visible')) {
			$(this).find('table').slideUp('fast');
		} else {
			$(this).find('table').slideDown('fast');
		}
	});

	$('.cactiTableCopy').off('click').on('click', function(event) {
		event.preventDefault();
		event.stopPropagation();
		var containerId =  $(this).attr('id');
		copyToClipboard(containerId);
	});

	$('i, th, img, input, label, select, button, .drillDown, .checkboxSlider')
	.tooltip({
		closed: true
	})
	.on('focus', function() {
		$(this).tooltip('close');
	})
	.on('click', function() {
		$(this).tooltip('close');
	});

	$(document).tooltip({
		items: 'div.cactiTooltipHint, span.cactiTooltipHint, a, span, .checkboxSlider',
		content: function() {
			var element = $(this);

			if (element.is('div')) {
				var text = $(this).find('span').html();
			} else if (element.is('span') || element.is('a')) {
				var text = $(this).prop('title');
			}
			return text;
		}
	}).tooltip('close').on('keyup keydown', function(event) {
		shiftPressed = event.shiftKey;
	});

	$('#main').show();

	var showPage = $('#main').map(function(i, el) {
		var dfd = $.Deferred();
		$(el).show(function() {
			dfd.resolve();
		});

		return dfd;
	});

	keepWindowSize();

	displayMessages();

	renderLanguages();
}

function renderLanguages() {
	if ($('select#user_language').selectmenu('instance') !== undefined) {
		$('select#user_language').selectmenu('destroy');

		$('select#user_language').languageselect({
			width: '220',
			change: function() {
				var name  = $(this).attr('id');
				var value = $(this).val();
				var page  = basename(location.pathname);
				if (page == 'auth_profile.php') {
					$.get('auth_profile.php?tab='+currentTab+'&action=update_data&name='+name+'&value='+value, function() {
						if (name == 'selected_theme' || name == 'user_language') {
							document.location = 'auth_profile.php?action=edit';
						}
					});
				}
			}
		}).languageselect('menuWidget').addClass('ui-menu-icons customicons');
	}

	if ($('select#i18n_default_language').selectmenu('instance') !== undefined) {
		$('select#i18n_default_language').selectmenu('destroy');

		$('select#i18n_default_language').languageselect({
			width: '220'
		}).languageselect('menuWidget').addClass('ui-menu-icons customicons');
	}

	$('#user_language-menu').css('max-height', '200px');
	$('#i18n_default_language-menu').css('max-height', '200px');
}

function setupButtonStyle() {
	if ($('input#submit').length) {
		$('input#submit').addClass('ui-state-active');
	} else if ($('input#return').length) {
		$('input#return').addClass('ui-state-active');
	}

	if ($('input#refresh').length) {
		$('input#refresh').addClass('ui-state-active');
	}

	if ($('input#go').length) {
		$('input#go').addClass('ui-state-active');
	}
}

function displayMessages() {
	var error   = false;
	var title   = '';
	var header  = '';

	if (typeof sessionMessageTimer === 'function' || sessionMessageTimer !== null) {
		clearInterval(sessionMessageTimer);
	}

	if (sessionMessage == null) {
		return;
	}

	if (typeof sessionMessage.level != 'undefined') {
		if (sessionMessage.level == MESSAGE_LEVEL_ERROR) {
			title = errorReasonTitle;
			header = errorOnPage;
			var sessionMessageButtons = {
				'Ok': {
					text: sessionMessageOk,
					id: 'btnSessionMessageOk',
					click: function() {
						$(this).dialog('close');
					}
				}
			};

			sessionMessageOpen = {};
		} else if (sessionMessage.level == MESSAGE_LEVEL_MIXED) {
			title  = mixedReasonTitle;
			header = mixedOnPage;
			var sessionMessageButtons = {
				'Ok': {
					text: sessionMessageOk,
					id: 'btnSessionMessageOk',
					click: function() {
						$(this).dialog('close');
					}
				}
			};

			sessionMessageOpen = {};
		} else if (sessionMessage.level == MESSAGE_LEVEL_CSRF) {
			var href = document.location.href;
			href = href + (href.indexOf('?') > 0 ? '&':'?') + 'csrf_timeout=true';
			document.location = href;
			return false;
		} else {
			title = sessionMessageTitle;
			header = sessionMessageSave;
			var sessionMessageButtons = {
				'Pause': {
					text: sessionMessagePause,
					id: 'btnSessionMessagePause',
					click: function() {
						if (sessionMessageTimer != null) {
							clearInterval(sessionMessageTimer);
							sessionMessageTimer = null;
						}
						$('#btnSessionMessagePause').remove();
						$('#btnSessionMessageOk').html('<span class="ui-button-text">' + sessionMessageOk + '</span>');
					}
				},
				'Ok': {
					text: sessionMessageOk,
					id: 'btnSessionMessageOk',
					click: function() {
						$(this).dialog('close');
						$('#messageContainer').remove();
						clearInterval(sessionMessageTimer);
					}
				}
			};

			sessionMessageOpen = function() {
				sessionMessageCountdown(5000);
			}
		}

		var returnStr = '<div id="messageContainer" style="display:none">' +
			'<h4>' + header + '</h4>' +
			'<p style="display:table-cell;overflow:auto"> ' + sessionMessage.message + '</p>' +
			'</div>';

		$('#messageContainer').remove();
		$('body').append(returnStr);

		var messageWidth = $(window).width();
		if (messageWidth > 600) {
			messageWidth = 600;
		} else {
			messageWidth -= 50;
		}

		$('#messageContainer').dialog({
			open: sessionMessageOpen,
			draggable: true,
			resizable: false,
			height: 'auto',
			minWidth: messageWidth,
			maxWidth: 800,
			maxHeight: 600,
			title: title,
			buttons: sessionMessageButtons
		});

		sessionMessage = null;
	}
}

function sessionMessageCountdown(time) {
	var sessionMessageTimeLeft = (time / 1000);

	$('#btnSessionMessageOk').html('<span class="ui-button-text">' + sessionMessageOk + ' (' + sessionMessageTimeLeft + ')</span>');

	sessionMessageTimer = setInterval(function() {
		sessionMessageTimeLeft--;

		$('#btnSessionMessageOk').html('<span class="ui-button-text">' + sessionMessageOk + ' (' + sessionMessageTimeLeft + ')</span>');

		if (sessionMessageTimeLeft <= 0) {
			clearInterval(sessionMessageTimer);
			$('#messageContainer').dialog('close');
			$('#messageContainer').remove();
		}
	}, 1000);
}

function markFilterTDs(child, filterNum) {
	trNum = 0;

	$('#'+child).find('tr').each(function() {
		tdNum = 0;
		$(this).find('td').each(function() {
			$(this).attr('id', 'fn'+filterNum+'tr'+trNum+'td'+tdNum);
			tdNum++;
		});
		trNum++;
	});
}

function makeFiltersResponsive() {
	storage = Storages.localStorage;

	filterNum = 0;

	if ($('div.cactiTableButton').closest('.cactiTable').not('#dqdebug').find('.filterTable').length) {
		$('div.cactiTableButton').closest('.cactiTable').not('#dqdebug').each(function() {
			if ($(this).find('.filterTable').length) {
				filterHeader = $(this).closest('.cactiTable');
				id     = filterHeader.attr('id');
				child  = id+'_child';
				filterContents = $('#'+child);

				filterHeader.find('.cactiTableTitle, .cactiTableButton').css('cursor', 'pointer');

				if (filterHeader.find('div.cactiTableButton').find('.cactiFilterAdd').length) {
					markFilterTDs(child, filterNum);

					$('.cactiFilterAdd').tooltip();
				}

				if (filterContents.find('#export').length) {
					title = $('#export').attr('value');
					filterHeader.find('div.cactiTableButton').append('<span title="'+title+'" style="display:none;" class="cactiFilterExport"><i class="fa fa-arrow-down"></i></span>');

					$('.cactiFilterExport').off('click').on('click', function(event) {
						event.stopPropagation();
						$('#export').trigger('click');
					}).tooltip();
				}

				if (filterContents.find('#import').length) {
					title = $('#import').attr('value');
					filterHeader.find('div.cactiTableButton').append('<span title="'+title+'" style="display:none;" class="cactiFilterImport"><i class="fa fa-arrow-up"></i></span>');

					$('.cactiFilterImport').off('click').on('click', function(event) {
						event.stopPropagation();
						$('#import').trigger('click');
					}).tooltip();
				}

				if (filterContents.find('#clear').length) {
					if (filterHeader.find('.cactiFilterClear').length == 0) {
						filterHeader.find('div.cactiTableButton').append('<span title="'+clearFilterTitle+'" style="display:none;" class="cactiFilterClear"><i class="fa fa-trash-alt"></i></span>');
					}

					$('.cactiFilterClear').off('click').on('click', function(event) {
						event.stopPropagation();
						$('#clear').trigger('click');
					}).tooltip();
				}

				toggleFilterAndIcon(id, child, true);

				filterHeader.find('.cactiTableTitle, .cactiTableButton').off('click').on('click', function() {
					id     = $(this).closest('.cactiTable').attr('id');
					child  = id+'_child';
					toggleFilterAndIcon(id, child, false);
				});

				state = storage.get('filterVisibility');

				if (state == 'hidden') {
					if (filterHeader.find('.cactiFilterState').length == 0) {
						filterHeader.find('div.cactiTableButton').append('<span class="cactiFilterState"><i class="fa fa-angle-double-down"></i></span>');
					}
				} else {
					if (filterHeader.find('.cactiFilterState').length == 0) {
						filterHeader.find('div.cactiTableButton').append('<span class="cactiFilterState"><i class="fa fa-angle-double-up"></i></span>');
					}
				}

				$('.cactiFilterState').attr('title', showHideFilter).tooltip();

				filterNum++;
			}
		});
	} else if ($('#dqdebug').length) {
		$('#dqdebug').find('div.cactiTableButton').each(function() {
			if ($(this).find('a').length) {
				anchors = $('div.cactiTableButton').find('a');
				anchors.each(function(){
					$(this).attr('title', $(this).text());
				});
				anchors.not('.cactiTableCopy').addClass('fa fa-trash-alt');
				anchors.filter('.cactiTableCopy').addClass('fa fa-copy');
				anchors.tooltip().text('');
			}
		});
	}
}

function toggleFilterAndIcon(id, child, initial) {
	storage = Storages.localStorage;

	state = storage.get('filterVisibility');

	if (initial) {
		if (state == 'hidden') {
			$('#'+child).hide();
			$('#'+id).find('.cactiFilterClear, .cactiFilterImport, .cactiFilterExport').show();
		}
	} else if ($('#'+child).is(':visible')) {
		$('#'+child).hide();
		$('#'+id).find('.cactiFilterClear, .cactiFilterImport, .cactiFilterExport').show();
		$('.cactiFilterState').find('i').removeClass('fa-angle-double-up').addClass('fa-angle-double-down');
		storage.set('filterVisibility', 'hidden');
	} else {
		$('#'+child).show();
		$('#'+id).find('.cactiFilterClear, .cactiFilterImport, .cactiFilterExport').hide();
		$('.cactiFilterState').find('i').removeClass('fa-angle-double-down').addClass('fa-angle-double-up');
		storage.set('filterVisibility', 'visible');
	}

	$(window).trigger('resize');
}

function setGraphTabs() {
	url = window.location.href;

	if (refreshPage.indexOf('graph_view.php') >= 0) {
		$('.lefttab').removeClass('selected');
		$('#tab-graphs').addClass('selected');

		$('.righttab').removeClass('selected');
		if (refreshPage.indexOf('action=tree') > 0) {
			$('#treeview').addClass('selected');
		} else if (refreshPage.indexOf('action=list') > 0) {
			$('#listview').addClass('selected');
		} else if (refreshPage.indexOf('action=preview') > 0) {
			$('#preview').addClass('selected');
		}

		/* update menu selection */
		if (theme == 'classic') {
			$('.righttab').each(function() {
				if ($(this).hasClass('selected')) {
					if ($(this).find('img').length) {
						imageSRC = $(this).find('img').attr('src');
						if (imageSRC.indexOf('_down') < 0) {
							imageSRC = imageSRC.replace('.gif', '_down.gif');
							$(this).find('img').attr('src', imageSRC);
						}
					}
				} else {
					if ($(this).find('img').length) {
						imageSRC = $(this).find('img').attr('src');
						if (imageSRC.indexOf('_down') >= 0) {
							imageSRC = imageSRC.replace('_down.gif', '.gif');
							$(this).find('img').attr('src', imageSRC);
						}
					}
				}
			});
		}
	}
}

function setupResponsiveMenuAndTabs() {
	$('.maintabs a.lefttab, .dropdownMenu a, .menuoptions a, #gtabs a.righttab').not('[href^="http"], [href^="https"], [href^="#"], [target="_blank"]').off('click').on('click', function(event) {
		page = basename($(this).attr('href'));

		if (page == 'logout.php' || page == 'auth_changepassword.php') {
			return;
		} else {
			event.preventDefault();
		}

		if ($(this).hasClass('selected')) {
			if ($('#navigation').length) {
				if (menuOpen(page)) {
					menuHide(true);
				} else {
					menuShow();
				}
			}
		} else if (pageName == page) {
			if ($('#navigation').length) {
				if (menuOpen(page)) {
					menuHide(true);
				} else {
					menuShow();
				}
			}
		} else {
			id   = $(this).attr('id');
			href = $(this).attr('href');

			loadTopTab(href, id, false);
		}
	});

	$(window).on('orientationchange, fullscreenchange', function() {
		responsiveUI('force');
	});
}

function loadMenuStateOpen(page) {
	storage = Storages.localStorage;
	page    = page.replace('.php', '');

	state = storage.get('menuState_' + page);
	if (state == 'hidden') {
		return false;
	} else {
		return true;
	}
}

function menuOpen(page) {
	storage = Storages.localStorage;
	page    = page.replace('.php', '');

	if ($('#navigation').is(':visible')) {
		return true;
	} else {
		return false;
	}
}

function responsiveUI(event) {
	var page = basename(location.pathname);
	var tree = false;

	if (event != 'force') {
	    if (new Date() - resizeTime < resizeDelta) {
			var myEvent = event;
			setTimeout(function() {
				responsiveUI(myEvent);
			}, resizeDelta);

			return false;
		} else {
			resizeTimeout = false;
		}
	}

	if ($('.cactiTreeNavigationArea').length > 0) {
		tree = true;
	} else {
		tree = false;
	}

	if ($('#navigation').length) {
		if (theme != 'classic') {
			if (loadMenuStateOpen(page)) {
				if ($(window).width() < 640) {
					menuHide(false);
					menuHideResponsive = true;
				} else {
					menuShow();
				}
			} else {
				menuHide(false);
			}
		} else {
			menuShow();
		}
	}

	if ($('#navigation').length && $('#navigation').is(':visible')) {
		var mainWidth = $('body').innerWidth() - $('#navigation').width();
	} else {
		var mainWidth = $('body').innerWidth();
	}

	/* change textbox and textarea widths */
	$('input[type="text"], textarea').each(function() {
		if ($(this).attr('type') == 'text') {
			var offset = 20;
		} else {
			var offset = 5;
		}

		if (mainWidth != 100) {
			if ($(this).width() > mainWidth) {
				$(this).css('max-width', (mainWidth - offset)+'px');
			} else {
				$(this).css('max-width', '');
			}

			if ($(this).width() > mainWidth) {
				$(this).css('max-width', (mainWidth - offset)+'px');
			}
		}
	});

	$('.filterTable').each(function() {
		tuneFilter($(this), mainWidth);
	});

	$('.cactiTable').each(function() {
		$(this).find('th:first-child').each(function() {
			var object = $(this).closest('.cactiTable');

			tuneTable(object, mainWidth);
		});
	});
}

function getMainWidth() {
	if ($('#navigation').length && $('#navigation').is(':visible')) {
		var mainWidth = $('body').innerWidth() - $('#navigation').width();
	} else {
		var mainWidth = $('body').innerWidth();
	}

	return mainWidth - 30;
}

function responsiveResizeGraphs() {
	if ($('.graphimage').length == 0) {
		return false;
	}

	var mainWidth = getMainWidth();
	var myColumns = $('#columns').val();
	var isThumb   = $('#thumbnails').is(':checked');
	var graphRow  = $('.tableRowGraph:first').width();
	var drillDown = $('.graphDrillDown:first').outerWidth() + 15;

	if (myColumns == null) {
		myColumns = 1;
	}

	if (mainWidth < graphRow) {
		graphRow = mainWidth - drillDown;
	} else if (graphRow == 0) {
		graphRow = mainWidth;
	}

	var myWidth = parseInt((graphRow - (drillDown * myColumns)) / myColumns);

	$('.graphimage').each(function() {
		var graph_id = $(this).attr('graph_id');

		if (!(graph_id > 0)) {
			graph_id = $(this).attr('id').replace('wrapper_','');
			graph_id = $(this).attr('id').replace('graph_','');
		}

		var rra_id = $(this).attr('rra_id');

		var original_cwidth  = $('#wrapper_'+graph_id).attr('graph_width');
		var original_cheight = $('#wrapper_'+graph_id).attr('graph_height');

		/* original image attributes */
		var image_width   = $(this).attr('image_width');
		var image_height  = $(this).attr('image_height');
		var canvas_top    = $(this).attr('canvas_top');
		var canvas_left   = $(this).attr('canvas_left');
		var canvas_width  = $(this).attr('canvas_width');
		var canvas_height = $(this).attr('canvas_height');

		var remove_whcss = false;
		var ratio = myWidth / image_width;

		/* optimize display and set correct ratio if image is full size */
		if (image_width * original_cwidth / canvas_width < myWidth) {
			remove_whcss = true;
			ratio = original_cwidth / canvas_width;
		}

		var new_image_width       = parseInt(image_width * ratio)
		var new_image_height      = parseInt(image_height * ratio)
		var new_canvas_width      = parseInt(canvas_width  * ratio);
		var new_canvas_height     = parseInt(canvas_height * ratio);
		var new_canvas_graph_top  = parseInt(canvas_top  * ratio);
		var new_canvas_graph_left = parseInt(canvas_left * ratio);

		$(this).attr('graph_width', new_canvas_width);
		$(this).attr('graph_height', new_canvas_height);
		$(this).attr('graph_top', new_canvas_graph_top);
		$(this).attr('graph_left', new_canvas_graph_left);

		if (!remove_whcss) {
			$(this).css('width', new_image_width);
			$(this).css('height', new_image_height);
		} else {
			$(this).css('width', '');
			$(this).css('height', '');
			$(this).removeAttr('width');
			$(this).removeAttr('height');
		}

		$('#zoom-container').remove();
		$(this).zoom({
			inputfieldStartTime : 'date1',
			inputfieldEndTime : 'date2',
			serverTimeOffset : timeOffset
		});
	});
}

function countHiddenCols(object) {
	var hidden = 0;

	$(object).find('th').each(function() {
		if ($(this).css('display') == 'none') {
			hidden++;
		}
	});

	return hidden;
}

function tuneTable(object, width) {
	var rows           = $(object).find('tr').length;
	var width          = width;
	var tableWidth     = $(object).width();
	var totalCols      = $(object).find('th').length;
	var reducedWidth   = 0;
	var columnsHidden  = countHiddenCols(object);
	var visibleColumns = totalCols - columnsHidden;
	var id             = $(object).attr('id');

	if (rows > 101) return false;

	if (tableWidth > width) {
		var column = totalCols;
		var hasCheckbox = $(object).find('th.tableSubHeaderCheckbox').length;

		if (hasCheckbox) {
			var minColumns = 2;
		} else {
			var minColumns = 2;
		}

		$($(object).find('th').not('.noHide').get().reverse()).each(function() {
			if (!$(this).hasClass('tableSubHeaderCheckbox') && $(this).is(':visible')) {
				reducedWidth += $(this).width();
				$('#'+id+' th:nth-child('+column+')').hide();
				$('#'+id+' td:nth-child('+column+')').hide();
				columnsHidden++;
			}

			visibleColumns = totalCols - columnsHidden;

			if (tableWidth-reducedWidth < width || visibleColumns <= minColumns) {
				lastColumnsHidden[id] = columnsHidden;
				lastWidth[id] = $(object).width();
				return false;
			}

			column--;
		});

		lastWidth[id] = $(object).width();
	} else if ($(object).width() > lastWidth[id]+40 && columnsHidden > 0) {
		var column = 1;
		var id     = $(object).attr('id');

		$($(object).find('th').get()).each(function() {
			if (!$(this).hasClass('tableSubHeaderCheckbox') && $(this).is(':hidden')) {
				if (lastWidth[id]+$(this).width() < width) {
					$('#'+id+' td:nth-child('+column+')').show();
					$('#'+id+' th:nth-child('+column+')').show();
				}
			}

			if ($(object).width() >= width) {
				lastColumnsHidden[id] = columnsHidden = $(object).find('th:hidden').length;
			}

			column++;
		});

		lastWidth[id] = $(object).width();
	}
}

function tuneFilter(object, width) {
	if ($(object).find('#timespan').length && $(object).find('#timespan').is(':visible')) {
		var timespan = true;

		var timeShiftWidth = $(object).find('.shiftArrow').closest('td').width();
		var dateWidth      = $(object).find('#date1').closest('td').width() + $(object).find('#date1').closest('td').prev('td').width();
		var clearWidth     = $('#tsclear').width();
		var refreshWidth   = $('#tsrefresh').width();
	} else {
		var timespan = false;

		var clearWidth     = $(object).find('#clear').width();
		var saveWidth      = $(object).find('#save').width();
		var exportWidth    = $(object).find('#export').width();
		var importWidth    = $(object).find('#import').width();
	}

	var minTds = 2;
	var visTds = $(object).find('td:visible').length;

	if ($(object).find('input[type="button"]').length) {
		minTds++;
	}

	if ($(object).width() > width) {
		if (!timespan) {
			$($(object).find('td').get().reverse()).each(function() {
				if ($(this).find('input[type="button"]').length == 0) {
					if ($(this).is(':visible')) {
						$(this).hide();
						visTds--;

						if ($(this).closest('td').prev().find('input, select').length == 0) {
							$(this).closest('td').prev().hide();
							visTds--;
						}
					}

					if ($(object).width() < width) {
						return false;
					}

					if (visTds <= minTds) {
						return false;
					}
				}
			});

			if ($(object).width() > width) {
				if (saveWidth > 0) {
					$('#save').hide();
				}
			}

			if ($(object).width() > width) {
				if (exportWidth > 0) {
					$('#export').hide();
				}
			}

			if ($(object).width() > width) {
				if (importWidth > 0) {
					$('#import').hide();
				}
			}

			if ($(object).width() > width) {
				if (clearWidth > 0) {
					$('#clear').hide();
				}
			}
		} else {
			$('#date1').closest('td').hide().prev('td').hide();
			$('#date2').closest('td').hide().prev('td').hide();

			if ($(object).width() > width) {
				$('.shiftArrow').closest('td').hide();
			}

			if ($(object).width() > width) {
				$('#tsclear').hide();
			}

			if ($(object).width() > width) {
				$('#tsrefresh').hide();
			}
		}
	} else {
		if (!timespan) {
			if ($(object).width() + clearWidth < width) {
				$('#clear').show();
			}

			if ($(object).width() + importWidth < width) {
				$('#import').show();
			}

			if ($(object).width() + exportWidth < width) {
				$('#export').show();
			}

			if ($(object).width() + saveWidth < width) {
				$('#save').show();
			}

			if ($(object).width() < width) {
				$(object).find('td').each(function() {
					if ($(this).find('input[type="button"]').length == 0) {
						if (!$(this).is(':visible')) {
							showWidth = $(this).width();
							if ($(this).next('td').find('input, select').length > 0) {
								showWidth += $(this).next('td').width();
							}

							if ($(object).width() + showWidth < width) {
								$(this).show();
								if ($(this).next('td').find('input, select').length > 0) {
									$(this).next('td').show();
								}
							} else {
								return false;
							}
						}
					}
				});
			}
		} else {
			if ($(object).width() + refreshWidth < width) {
				$('#tsrefresh').show();
			}

			if ($(object).width() + clearWidth < width) {
				$('#tsclear').show();
			}

			if ($(object).width() + timeShiftWidth < width) {
				$('.shiftArrow').closest('td').show();
			}

			if ($(object).width() + (2 * dateWidth) < width) {
				$('#date1').closest('td').show().prev('td').show();
				$('#date2').closest('td').show().prev('td').show();
			}
		}
	}
}

function menuHide(store) {
	var storage = Storages.localStorage;
	var page = basename(location.pathname).replace('.php', '');

	var myClass = '';
    var curMargin = $('#navigation').outerWidth();

	if ($('.cactiTreeNavigationArea').length) {
		myClass = '.cactiTreeNavigationArea';

		if (curMargin > 0) {
			marginLeftTree = curMargin;
		}
	} else if ($('.cactiConsoleNavigationArea').length) {
		myClass = '.cactiConsoleNavigationArea';

		if (curMargin > 0) {
			marginLeftConsole = curMargin;
		}
	}

	$('#navigation_right').animate({'margin-left': '0px'}, 20);

	if (myClass != '') {
		$(myClass).hide('slide', {direction: 'left'}, 20, function() {
			responsiveResizeGraphs();
		});
	}

	$('#navigation').hide();

	if (store) {
		storage.set('menuState_' + page, 'hidden');
	}
}

function menuShow() {
	var storage = Storages.localStorage;
	var page = basename(location.pathname).replace('.php', '');

	var myClass = '';

	if ($('.cactiTreeNavigationArea').length) {
		myClass = '.cactiTreeNavigationArea';

		if (marginLeftTree > 0) {
			$('#navigation_right').animate({'margin-left': marginLeftTree}, 20);
		}
	} else if ($('.cactiConsoleNavigationArea').length) {
		myClass = '.cactiConsoleNavigationArea';

		if (marginLeftConsole > 0) {
			$('#navigation_right').animate({'margin-left': marginLeftConsole}, 20);
		}
	}

	if (myClass != '') {
		$(myClass).show('slide', {direction: 'left'}, 20, function() {
			responsiveResizeGraphs();
		});
	}

	$('#navigation').show();

	storage.set('menuState_' + page, 'visible');
}

function loadTopTab(href, id, force) {
	statePushed = false;
	var cont = false;

	if (force == undefined) {
		force = false;
	}

	if (!force) {
		cont = checkFormStatus(href, 'toptab', id);
	} else {
		cont = true;
	}

	if (cont) {
		var thref = stripHeaderSuppression(href);
		var url   = thref+(thref.indexOf('?') > 0 ? '&':'?') + 'headercontent=true';

		$('.submenuoptions').slideUp(120);
		$('.menuoptions').slideUp(120);

		if (href.indexOf('graph_view.php') >= 0) {
			$('.cactiGraphHeaderBackground').show();
			$('.cactiConsolePageHeadBackdrop').hide();
		} else {
			$('.cactiGraphHeaderBackground').hide();
			$('.cactiConsolePageHeadBackdrop').show();
		}

		$.ajaxQ.abortAll();
		$.get(url)
			.done(function(html) {
				var htmlObject  = $(html);
				var matches     = html.match(/<title>(.*?)<\/title>/);

				if (matches != null) {
					var htmlTitle   = matches[1];
					var breadCrumbs = htmlObject.find('#breadcrumbs').html();
					var parts       = html.split('</title>');
					var data        = parts[1];

					checkForLogout(data);

					$('title').text(htmlTitle);
					$('#breadcrumbs').html(breadCrumbs);
					$('div[class^="ui-"]').remove();
					$('#cactiContent').replaceWith(data);

					myTitle = htmlTitle;
					myHref  = cleanHeader(href);

					pushState(myTitle, href);
				} else {
					checkForLogout(html);

					$('#cactiContent').replaceWith(html);

					thref = stripHeaderSuppression(href);

					pushState(myTitle, href);
				}

				var hrefParts = href.split('?');
				pageName = basename(hrefParts[0]);

				if (pageName != '') {
					if ($('#menu').find("a[href^='"+href+"']").length > 0) {
						$('#menu').find('.pic').removeClass('selected');
						$('#menu').find("a[href^='"+href+"']").addClass('selected');
					} else if ($('#menu').find("a[href*='/"+pageName+"']").length > 0) {
						$('#menu').find('.pic').removeClass('selected');
						$('#menu').find("a[href*='/"+pageName+"']").addClass('selected');
					}
				}

				applySkin();

				if (isMobile.any() != null) {
					window.scrollTo(0,1);
				} else {
					window.scrollTo(0,0);
				}

				handleConsole(pageName);

				return false;
			})
			.fail(function(data) {
				getPresentHTTPError(data);
			}
		);

		/* update menu selection */
		if ($('#'+id).hasClass('lefttab')) {
			$('.lefttab').removeClass('selected');
			$('#'+id).addClass('selected');
		}

		return true;
	} else {
		return false;
	}
}

function loadPage(href, force) {
	statePushed = false;
	cont = false;

	if (force == undefined) {
		force = false;
	}

	if (!force) {
		cont = checkFormStatus(href, 'loadpage');
	} else {
		cont = true;
	}

	if (cont) {
		$.ajaxQ.abortAll();
		$.get(href)
			.done(function(html) {
				var htmlObject  = $(html);
				var matches     = html.match(/<title>(.*?)<\/title>/);

				if (matches != null) {
					var htmlTitle   = matches[1];
					var breadCrumbs = htmlObject.find('#breadcrumbs').html();
					var data        = htmlObject.find('#main').html();

					checkForLogout(data);

					$('#main').empty().hide();
					$('title').text(htmlTitle);
					$('#breadcrumbs').html(breadCrumbs);
					$('div[class^="ui-"]').remove();
					$('#main').html(data);

					myTitle = htmlTitle;
					myHref  = cleanHeader(href);

					pushState(myTitle, href);
				} else {
					checkForLogout(html);

					$('#main').empty().hide();
					$('#main').html(html);

					thref = stripHeaderSuppression(href);

					pushState(myTitle, href);
				}

				var hrefParts = href.split('?');
				pageName = basename(hrefParts[0]);

				if (pageName != '') {
					if ($('#menu').find("a[href^='"+href+"']").length > 0) {
						$('#menu').find('.pic').removeClass('selected');
						$('#menu').find("a[href^='"+href+"']").addClass('selected');
					} else if ($('#menu').find("a[href*='/"+pageName+"']").length > 0) {
						$('#menu').find('.pic').removeClass('selected');
						$('#menu').find("a[href*='/"+pageName+"']").addClass('selected');
					}

					if (pageName == 'graph_templates_items.php' || pageName == 'graph_templates_inputs.php') {
						$('#menu').find('a[href*="graph_templates.php"]').addClass('selected');
					}
				}

				applySkin();

				if (isMobile.any() != null) {
					window.scrollTo(0,1);
				} else {
					window.scrollTo(0,0);
				}

				handleConsole(pageName);

				return false;
			})
			.fail(function(data) {
				getPresentHTTPError(data);
			}
		);
	}

	return false;
}

function setNavigationScroll() {
	var object = '';

	$(document).unbind('mousemove').on('mousemove', function(pos) {
		if ($('.cactiConsoleNavigationArea').length) {
			object = '.cactiConsoleNavigationArea';
		} else if ($('.cactiTreeNavigationArea').length) {
			object = '.cactiTreeNavigationArea';
		} else {
			object = '';
		}

		if (object != '') {
			var mpos   = $(object).position();
			var width  = $(object).outerWidth();
			var height = $(object).outerHeight();
			if (pos.pageX < mpos.left ||
				pos.pageY < mpos.top ||
				pos.pageX > mpos.left + width - 1 ||
				pos.pageY > mpos.top + height - 1) {

				if (isHover) {
					clearTimeout(hoverTimer);
					hoverTimer = setTimeout(function() {
						$(object).css('overflow-y', 'hidden');
					}, 500);
				}

				isHover = false;
			} else {
				if (!isHover) {
					clearTimeout(hoverTimer);
					hoverTimer = setTimeout(function() {
						$(object).css('overflow-y', 'auto');
					}, 500);
				}
	
				isHover = true;
			}
		}
	});
}

function loadPageNoHeader(href, scroll, force) {
	statePushed = false;
	cont = false;

	if (scroll == undefined) {
		scroll = false;
	}

	if (force == undefined) {
		force = false;
	}

	if (!force) {
		cont = checkFormStatus(href, 'noheader', scroll);
	} else {
		cont = true;
	}

	if (cont) {
		$.ajaxQ.abortAll();
		$.get(href)
			.done(function(data) {
				var htmlObject  = $(data);
				var matches     = data.match(/<title>(.*?)<\/title>/);

				if (matches != null) {
					checkForLogout(data);

					var htmlTitle   = matches[1];
					var breadCrumbs = htmlObject.filter('#breadcrumbs').html();
					var data        = htmlObject.filter('#main').html();

					$('#main').empty().hide();
					$('title').text(htmlTitle);
					$('#breadcrumbs').html(breadCrumbs);
					$('div[class^="ui-"]').remove();
					$('#main').html(data);

					myTitle = htmlTitle;
					myHref  = cleanHeader(href);

					pushState(myTitle, href);
				} else {
					checkForLogout(data);

					$('#main').empty().hide();
					$('div[class^="ui-"]').remove();
					$('#main').html(data);

					var hrefParts = href.split('?');
					pageName = basename(hrefParts[0]);

					if (pageName != '') {
						$('#menu').find('.pic').removeClass('selected');
						$('#menu').find("a[href*='/"+pageName+"']").addClass('selected');

						if (pageName == 'graph_templates_items.php' || pageName == 'graph_templates_inputs.php') {
							$('#menu').find('a[href*="graph_templates.php"]').addClass('selected');
						}
					}

					applySkin();

					pushState(myTitle, href);
				}

				if (isMobile.any() != null) {
					window.scrollTo(0,1);
				} else {
					window.scrollTo(0,0);
				}

				handleConsole(pageName);

				return false;
			})
			.fail(function(data) {
				getPresentHTTPError(data);
			}
		);
	}

	return false;
}

function getPresentHTTPError(data) {
	if (typeof data != 'undefined') {
		var errorStr  = data.status;
		var errorSub  = data.statusText;
		var errorText = errorReasonUnexpected;

		if (typeof data.responseText != 'undefined') {
			var dataText = data.responseText;

			var title_match = dataText.match(/<title>(.*?)<\/title>/);
			var head_match  = dataText.match(/<h1>(.*?)<\/h1>/);
			var para_match  = dataText.match(/<p>(.*?)<\/p>/);

			if (title_match != null) {
				var errorSub = title_match[1];
			}

			if (head_match != null) {
				var errorSub = head_match[1];
			}

			if (para_match != null) {
				var errorText = para_match[1];
			}

			var returnStr = '<div id="httperror" style="display:none">' +
				'<h4>' + errorOnPage + '</h4><hr>' +
				'<div style="padding-bottom: 5px;"><div style="display:table-cell;width:75px"><b>' + errorNumberPrefix + '</b></div> ' +
				'<div style="display:table-cell"> ' + errorStr + ' ' + errorSub + '</div></div>' +
				'<div><div style="display:table-cell;width:75px"><b>'  + errorReasonPrefix + '</b></div> ' +
				'<div style="display:table-cell"> ' + errorText + '</div></div>' +
				'</div></div>';

			var messageWidth = $(window).width();
			if (messageWidth > 400) {
				messageWidth = 400;
			} else {
				messageWidth -= 50;
			}

			$('#httperror').remove();
			$('body').append(returnStr);
			$('#httperror').dialog({
				resizable: false,
				height: 'auto',
				width: messageWidth,
				title: errorReasonTitle,
				buttons: {
					Ok: function() {
						$(this).dialog('close');
					}
				}
			});
		}
	}
}

function ajaxAnchors() {
	var page = basename(location.pathname);

	$('a.pic, a.linkOverDark, a.linkEditMain, a.console, a.hyperLink, a.tab').not('[href^="http"], [href^="https"], [href^="#"], [target="_blank"]').off('click').on('click', function(event) {
		event.preventDefault();
		event.stopPropagation();

		if ($(window).width() < 640) {
			if (theme != 'classic') {
				menuHide(false);
			}
		}

		/* determine the page name */
		var href = $(this).attr('href');

		if (href == '#') {
			return false;
		}

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

		if (href != null) {
			pageName = basename(href);
		}

		loadPage(href);

		return false;
	});
}

function checkFormStatus(href, type, scroll_or_id) {
	var changed = false;

	if ($('.cactiFormStart').not('#chk').length) {
		$('.cactiFormStart').not('#chk').each(function() {
			var formID     = $(this).attr('id');
			var submitData = $(this).serializeForm();

			if (typeof formArray != 'undefined' && typeof formArray[formID] != 'undefined') {
				var formData   = formArray[formID];

				$.each(submitData, function(index, value) {
					if (typeof formData[index] != 'undefined') {
						if (formData[index] != value) {
							if (index == 'settings_sendmail_path' || index == 'rrd_archive' || index == '__csrf_magicSubmit' || index == '__csrf_magic' || index == 'settings_smtp_password' || index == 'settings_smtp_password_confirm') {
								// Ignore this entry
							} else if (index.indexOf('[]') > 0) {
								// Ignore this entry
							} else {
								console.log('Index:-'+index+'-:Submit:'+value+', Orig:'+formData[index]);
								changed = true;
							}
						}
					}
				});
			}
		});

		if (changed) {
			warningMessage(href, type, scroll_or_id);
			return false;
		} else {
			return true;
		}
	} else {
		return true;
	}
}

function setupCollapsible() {
	var storage = Storages.localStorage;

	$('.collapsible').each(function(data) {
		var id = $(this).attr('id')+'_cs';
		var state = storage.get(id);

		if (state == 'hide') {
			$(this).addClass('collapsed');
			$(this).nextUntil('div.spacer').hide();
			$(this).find('i').removeClass('fa-angle-double-up').addClass('fa-angle-double-down');
			storage.set(id, 'hide');
		}
	});

	$('.collapsible').off('click').on('click', function(data) {
		var id = $(this).attr('id')+'_cs';

		if ($(this).find('i').hasClass('fa-angle-double-up')) {
			$(this).addClass('collapsed');
			$(this).nextUntil('div.spacer').slideUp('slow');
			$(this).find('i').removeClass('fa-angle-double-up').addClass('fa-angle-double-down');
			storage.set(id, 'hide');
		} else {
			$(this).removeClass('collapsed');
			$(this).nextUntil('div.spacer').slideDown('slow');
			$(this).nextUntil('div.spacer').each(function(data) {
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

function handleConsole(pageName) {
	if (pageName == null) {
		pageName = basename($(location).attr('pathname'));
	}

	// Modify the console pic
	$('#menu_main_console').find('.menu_parent').attr('href', 'index.php').addClass('console selected');
	$('#menu_main_console_div').remove();
	if (pageName != 'index.php') {
		$('#menu_main_console').find('.menu_parent').removeClass('selected');
	}
}

function setupUserMenu() {
	handleConsole();

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
		} else {
			clearTimeout(userMenuOpenTimer);
		}
	});
}

function setupSpecialKeys() {
	if (!isMobile.any()) {
		$('#filter, #rfilter').focus();
	} else {
		$('#filter, #rfilter').prop('size', '15');
	}
}

/** setupSortable - This function will set all actions for sortable columns
 *  every time a page is regenerated */
function setupSortable() {
	$('th.sortable').on('click', function(e) {
		document.getSelection().removeAllRanges();

		var $target = $(e.target);
		var sortAdd = '';
		var url     = '';

		if (!$target.is('.ui-resizable-handle')) {
			var page      = $(this).find('.sortinfo').attr('sort-page');
			var column    = $(this).find('.sortinfo').attr('sort-column');
			var direction = $(this).find('.sortinfo').attr('sort-direction');
			var returnto  = $(this).find('.sortinfo').attr('sort-return');

			if (shiftPressed) {
				sortAdd='&add=true';
			} else {
				sortAdd='&add=reset';
			}

			var url = page+(page.indexOf('?') > 0 ? '&':'?') +
				'sort_column=' + column +
				'&sort_direction=' + direction +
				'&header=false' +
				sortAdd;

			$.ajaxQ.abortAll();
			$.get(url)
				.done(function(data) {
					checkForLogout(data);

					$('#'+returnto).empty().hide();
					$('div[class^="ui-"]').remove();
					$('#'+returnto).html(data);

					applySkin();
				})
				.fail(function(data) {
					getPresentHTTPError(data);
				}
			);
		}
	});
}

function setupBreadcrumbs() {
	$('#breadcrumbs > li > a').click(function(event) {
		event.preventDefault();
		event.stopPropagation();

		var href =  $(this).attr('href');

		if (href != '#') {
			href = href.replace('action=tree_content', 'action=tree');
			$(this).prop('href', href);
			document.location = href;
		}
	});
}

/** saveTableWidths - This function will initialize table widths on page
 *  load.  It includes the 'initial' boolean to initialize the page */
function saveTableWidths(initial) {
	// We will save columns widths persistently
	var storage = Storages.localStorage;
	var key;

	// Initialize table width on the page
	$('.cactiTable').each(function(data) {
		var key    = $(this).attr('id');
		var sizes  = storage.get(key);
		var items  = sizes ? sizes: new Array();
		var width  = $(document).width();

		// if the table width changes, reset the columns
		if (key !== undefined && sizes == undefined) {
			storage.remove(key);

			var sizes = new Array();
			var items = new Array();

			items[0] = width;
			sizes[0] = width;
		} else if (key !== undefined && initial) {
			if (items.length > 0) {
				if (items[0] + 18 < width) {
					storage.remove(key);

					var sizes = new Array();
					var items = new Array();

					items[0] = width;
					sizes[0] = width;
				}
			}
		}

		var i = 1;
		if (key !== undefined) {
			if (initial && items.length) {
				$('#'+key).find('th.ui-resizable').each(function(data) {
					if (items[i] == 0) {
						items[i] = $(this).width();
						sizes[i] = items[i];
					}

					if (items[i] != 0) {
						$(this).css('width', items[i]);
						$(this).attr('resizeWidth', items[i]);
					}
					i++;
				});
			} else {
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
	var originalSize = 0;
	var colWidth = 0;

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
			var nextWidth = $(ui.element).next().attr('resizeWidth');
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

function appendHeaderSuppression(url) {
	if (url.indexOf('action=tree_content') < 0 && url.indexOf('action=tree') >= 0) {
		url = url.replace('action=tree', 'action=tree_content');
	}

	if (url.indexOf('header=false') < 0) {
		url += (url.indexOf('?') > 0 ? '&header=false':'?header=false');
	}

	return url;
}

function stripHeaderSuppression(url) {
	return url.replace('header=false', '').replace('?&', '?').replace('&&', '&');
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
			} else {
				if (previousPage != '') {
					refreshPage = previousPage;
				}

				/* fix coner case with tree refresh */
				refreshPage = appendHeaderSuppression(refreshPage);

				$.ajaxQ.abortAll();
				$.get(refreshPage)
					.done(function(data) {
						checkForLogout(data);

						$('#main').empty().hide();
						$('div[class^="ui-"]').remove();
						$('#main').html(data);
						applySkin();
					})
					.fail(function(data) {
						getPresentHTTPError(data);
					}
				);
			}
		}, refreshMSeconds);
	}
}

function pulsateStart(element) {
	pulsating = true;
	pulsate(element);
}

function pulsate(element) {
	if (pulsating) {
		$(element || this).delay(100).fadeOut(800).delay(100).fadeIn(800, pulsate);
	}
}

function pulsateStop(element) {
	pulsating = false;
}

function setTitleAndHref() {
	myHref  = $(location).attr('href');
	myTitle = $(document).attr('title');
}

$(function() {
	statePushed = false;
	popFired    = false;
	var tapped  = false;

	$(window).on('popstate', function(event) {
		handlePopState();
	});

	$('#filter, #rfilter').keydown(function(event) {
		if (event.keyCode == 8 && $(this).val() == '') {
			handlePopState();
		}
	});

	setTitleAndHref();

	setupUserMenu();

	applySkin();

	setupEllipsis();

	$('#navigation_right').show();

	if (isMobile.any() != null) {
		$(window).on('touchstart', function(event) {
			if (!tapped) {
				tapped = setTimeout(function() { tapped=null; }, 300);
			} else {
				clearTimeout(tapped);
				tapped = null;

				if (screenfull.enabled) {
					screenfull.request();
				}
			}
		});

		$(window).on('load', function(event) {
			setTimeout(function() { window.scrollTo(0, 1); }, 0);
		});
	}
});

/* only perform the recalculation of elements at the final end of the windows resize event */
var waitForFinalEvent = (function () {
  var timers = {};

  return function (callback, ms, uniqueId) {
    if (!uniqueId) {
      uniqueId = "Don't call this twice without a uniqueId";
    }

    if (timers[uniqueId]) {
      clearTimeout (timers[uniqueId]);
    }

    timers[uniqueId] = setTimeout(callback, ms);
  };
})();

function setupEllipsis() {
	$('<div class="dropdownMenu">'
		+'<ul id="submenu-ellipsis" class="submenuoptions" style="display:none;">'
		+'</ul>'
	+'</div>').appendTo('body');

	$('.maintabs-submenu, .usertabs-submenu, .submenu-ellipsis').off('click').on('click', function(event) {
		event.preventDefault();

		var submenu_index = $(this).attr('id').replace('menu-', 'submenu-');
		var submenu = $('#'+submenu_index);

		if (submenu.is(':visible') === false) {
			/* close other drop down menus first */
			$('.submenuoptions').slideUp(120);
			$('.menuoptions').slideUp(120);

			/* re-position */
			var position = $(this).position();

			if (position.left - parseInt(submenu.outerWidth()) < 0) {
				submenu.css({'left': 0}).slideDown(120);
			} else {
				submenu.css({'left': position.left - parseInt(submenu.outerWidth()) + parseInt($(this).outerWidth())}).slideDown(120);
			}
		} else {
			submenu.slideUp(120);
		}

		return false;
	});

	$('.submenuoptions').mouseenter(function(data) {
		clearTimeout(userMenuTimer);
	}).mouseleave(function(data) {
		if ($('.submenuoptions').is(':visible')) {
			userMenuTimer = setTimeout(function() {$('.submenuoptions').stop().slideUp(120);}, 1000);
		} else {
			clearTimeout(userMenuOpenTimer);
		}
	});
}

function keepWindowSize() {
	$(window).resize(function (event) {
		waitForFinalEvent(function() {
			$('.cactiGraphContentArea').show();

   			var resizeTime = new Date();
			var myEvent = event;
			if (resizeTimeout === false) {
				resizeTimeout = true;
				setTimeout(function() {
					responsiveUI(myEvent);
				}, resizeDelta);
			}
			heightPage = $(window).height();

			if ($('#cactiPageHead').is(':visible')) {
				heightPageHead = $('#cactiPageHead').outerHeight();
			} else {
				heightPageHead = 0;
			}

			if ($('#breadCrumbBar').is(':visible')) {
				heightBreadCrumbBar = $('#breadCrumbBar').outerHeight();
			} else {
				heightBreadCrumbBar = 0;
			}

			if ($('#cactiPageBottom').is(':visible')) {
				heightPageBottom = $('#cactiPageBottom').outerHeight();
			} else {
				heightPageBottom = 0;
			}

			heightPageContent = heightPage - heightPageHead - heightPageBottom - heightBreadCrumbBar;

			if (theme != 'classic') {
				$('body').css('height', heightPage);
				$('#cactiContent, #navigation, #navigation_right').css('height', heightPageContent);

				// Handle links pages
				$('#content').css({'width':'100%', 'height':heightPageContent-4});
			} else {
				// Handle links pages
				$('#content').css({'width':'100%', 'height':$(document).height()});
			}

			var navWidth = $('#navigation').width();
			$('#searcher').css('width', navWidth-70);

			responsiveResizeGraphs();

			/* close open dropdown menues first off */
			$('.dropdownMenu > ul').hide();

			if ($('#gtabs > .tabs').is(':visible')) {
				var graphTabWidth = $('#gtabs > .tabs').outerWidth();
			} else {
				var graphTabWidth = 0;
			}

			if ($('.usertabs').length) {
				var userTabs = $('.usertabs').outerWidth();
			} else {
				var userTabs = 0;
			}

			var bodyWidth     = $('body').width();
			var otherWidth    = 0;
			$('#tabs').find('div:not(.maintabs):visible').each(function() {
				otherWidth += $(this).outerWidth();
			});

			var ellipsisWidth = $('.maintabs-submenu-ellipsis').outerWidth();
			var tabHeight     = $('#tabs').outerHeight();
			var mainTabPos    = false;

			if ($('.maintabs').length) {
				mainTabPos    = $('.maintabs:first').position();
			}

			if ($('.usertabs').length) {
				mainTabHeight = tabHeight;
				userTabPos = $('.usertabs').position();
			} else if (mainTabPos != false) {
				mainTabHeight = $('.maintabs:first nav').outerHeight();
				userTabPos = mainTabPos;
			}

			var shrinking = true;
			if (pageWidth === null) {
				shrinking = true;
				items = $($('.maintabs nav ul li a.lefttab:not(.ellipsis)').get().reverse());
			} else if (bodyWidth > pageWidth) {
				shrinking = false;
				items = $($('.maintabs nav ul li a.lefttab:not(.ellipsis)').get());
			} else if (mainTabPos != false && (mainTabPos.top != userTabPos.top || mainTabHeight > tabHeight)) {
				shrinking = true;
				items = $($('.maintabs nav ul li a.lefttab:not(.ellipsis)').get().reverse());
			} else if (pageWidth != null && bodyWidth < pageWidth) {
				shrinking = true;
				items = $($('.maintabs nav ul li a.lefttab:not(.ellipsis)').get().reverse());
			} else {
				shrinking = false;
				items = $($('.maintabs nav ul li a.lefttab:not(.ellipsis)').get());
			}

			pageWidth  = bodyWidth;

			var done = false;

			items.each(function() {
				var id = $(this).attr('id');

				if (!done) {
					if (shrinking) {
						if (tabsWrapping()) {
							hideCurrentTab(id, true);
						} else {
							done = true;
						}
					} else if (!shrinking) {
						showCurrentTab(id);

						if (tabsWrapping()) {
							hideCurrentTab(id, false);
						}
					}
				}
			});

			if ($('#submenu-ellipsis li').length == 0) {
				$('.ellipsis').hide();
			} else {
				$('.ellipsis').show();
			}
		}, 50, 'resize-content');
	}).trigger('resize')
}

function hideCurrentTab(id, shrinking) {
	if ($('#'+id+'-ellipsis').length == 0) {
		var myid = id+'-ellipsis';
		var href = $('#'+id).attr('href');
		var text = $('#'+id).text();

		if (shrinking) {
			$('#submenu-ellipsis').prepend('<li><a id="'+myid+'" href="'+href+'">' + text + '</a></li>');
		} else {
			$('#submenu-ellipsis').append('<li><a id="'+myid+'" href="'+href+'">' + text + '</a></li>');
		}

		$('#'+id).parent().hide();
	}
}

function showCurrentTab(id) {
	$('#'+id+'-ellipsis').parent().remove();
	$('#'+id).parent().show();
}

function tabsWrapping() {
	var mainTabPos    = $('.maintabs:first').position();
	var tabHeight     = $('#tabs').height();

	if ($('.usertabs').length) {
		var mainTabHeight = tabHeight;
		var userTabPos    = $('.usertabs').position();
	} else {
		var mainTabHeight = $('.maintabs:first nav').height();
		var userTabPos    = mainTabPos;
	}

	var bodyWidth     = $('body').width();
	var otherWidth    = 0;
	$('#tabs').find('div:not(.maintabs):visible').each(function() {
		otherWidth += $(this).outerWidth();
	});

	var ellipsisWidth = $('.maintabs-submenu-ellipsis').outerWidth();
	var mtabsWidth  = $('.maintabs:not(.usertabs)').outerWidth();

	if ($('#gtabs>.tabs').length) {
		var gtabsWidth = $('#gtabs>.tabs').outerWidth();
	} else {
		var gtabsWidth = 0;
	}

	if ($('.usertabs').length) {
		var utabsWidth  = $('.usertabs').outerWidth();
	} else {
		var utabsWidth  = 0;
	}

	if (gtabsWidth + mtabsWidth + utabsWidth + otherWidth + ellipsisWidth > bodyWidth - 20) {
		return true;
	}

	if (mainTabPos.top != userTabPos.top || mainTabHeight > tabHeight) {
		return true;
	} else {
		return false;
	}
}

/* Graph related javascript functions */
if (typeof urlPath == 'undefined') {
	var urlPath = '';
}

var graphPage  = urlPath+'graph_view.php';
var pageAction = 'preview';

function checkForLogout(data) {
	if (typeof data == 'undefined') {
		return true;
	} else if (typeof data === 'object') {
		return true;
	} else if (data.indexOf('cactiLoginLogo') >= 0) {
		document.location = urlPath + 'logout.php?action=timeout';
	}
}

function clearGraphFilter() {
	var href = appendHeaderSuppression(graphPage+'?action='+pageAction+'&clear=1');

	$.ajaxQ.abortAll();
	$.get(href)
		.done(function(data) {
			checkForLogout(data);

			$('#main').empty().hide();
			$('div[class^="ui-"]').remove();
			$('#main').html(data);

			applySkin();
		})
		.fail(function(data) {
			getPresentHTTPError(data);
		}
	);
}

function saveGraphFilter(section) {
	var href = graphPage+'?action=save'+
		'&columns='+$('#columns').val()+
		'&graphs='+$('#graphs').val()+
		'&graph_template_id='+$('#graph_template_id').val()+
		'&predefined_timespan='+$('#predefined_timespan').val()+
		'&predefined_timeshift='+$('#predefined_timeshift').val()+
		'&thumbnails='+$('#thumbnails').is(':checked');

	$.get(href+'&header=false&section='+section)
		.done(function(data) {
			checkForLogout(data);

			$('#text').show().text(filterSettingsSaved).fadeOut(2000, function() {
				$('#text').empty();
			});
		})
		.fail(function(data) {
			getPresentHTTPError(data);
		}
	);
}

function applyGraphFilter() {
	var href = appendHeaderSuppression(graphPage+'?action='+pageAction+
		'&rfilter=' + base64_encode($('#rfilter').val())+
		(typeof($('#host_id').val()) !== 'undefined' ? '&host_id='+$('#host_id').val():'')+
		'&columns='+$('#columns').val()+
		'&graphs='+$('#graphs').val()+
		'&graph_template_id='+$('#graph_template_id').val()+
		'&thumbnails='+$('#thumbnails').is(':checked'));

	$.ajaxQ.abortAll();
	$.get(href)
		.done(function(data) {
			checkForLogout(data);

			$('#main').hide();
			$('div[class^="ui-"]').remove();
			$('#main').html(data);
			applySkin();

			pushState(myTitle, myHref);
		})
		.fail(function(data) {
			getPresentHTTPError(data);
		}
	);
}

function cleanHeader(href) {
	href = stripHeaderSuppression(href);
	href = href.replace('action=tree_content', 'action=tree');
	href = href.replace('?nostate=true', '?').replace('&nostate=true', '').replace('?&', '?');

	return href;
}

function pushState(myTitle, myHref) {
	if (myHref.indexOf('nostate') < 0) {
		if (statePushed == false) {
			if (typeof window.history.pushState != 'undefined') {
				var myObject = { Page: myTitle, Url: cleanHeader(myHref) };
				window.history.pushState(myObject, myObject.Page, myObject.Url);
			}
		}
	} else if (typeof window.history.popState === 'function') {
		window.history.popState();
	}

	statePushed = true;
}

function handlePopState() {
	var href = document.location.href;

	if (popFired == false) {
		if (href.indexOf('#') == -1) {
			if (href.indexOf('header=false') > 0) {
				loadPageNoHeader(href + '&nostate=true');
			} else if (basename(href) == lastPage) {
				loadPageNoHeader(href + (href.indexOf('?') > 0 ? '&header=false&nostate=true':'?header=false&nostate=true'));
			} else {
				href.replace('header=false').replace('?&', '?').replace('&&', '&');
				document.location = href + (href.indexOf('?') > 0 ? '&nostate=true':'?nostate=true');
			}
		}
	}

	popFired = true;
	lastPage = basename(href);
}

function applyGraphTimespan() {
	var href = appendHeaderSuppression(graphPage+'?action='+pageAction+
		'&predefined_timespan='+$('#predefined_timespan').val()+
		'&predefined_timeshift='+$('#predefined_timeshift').val());

	$.ajaxQ.abortAll();
	$.get(href)
		.done(function(data) {
			checkForLogout(data);

			$('#main').empty().hide();
			$('div[class^="ui-"]').remove();
			$('#main').html(data);
			applySkin();
		})
		.fail(function(data) {
			getPresentHTTPError(data);
		}
	);
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

	var href = appendHeaderSuppression(graphPage+'?action='+pageAction);

	$.ajaxQ.abortAll();
	$.post(href, json).done(function(data) {
		checkForLogout(data);

		$('#main').empty().hide();
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

	var href = appendHeaderSuppression(graphPage+'?action='+pageAction);

	$.ajaxQ.abortAll();
	$.post(href, json).done(function(data) {
		checkForLogout(data);

		$('#main').empty().hide();
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

	var href = appendHeaderSuppression(graphPage+'?action='+pageAction);

	$.ajaxQ.abortAll();
	$.post(href, json).done(function(data) {
		checkForLogout(data);

		$('#main').empty().hide();
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

	var href = appendHeaderSuppression(graphPage+'?action='+pageAction);

	$.ajaxQ.abortAll();
	$.post(href, json).done(function(data) {
		checkForLogout(data);

		$('#main').empty().hide();
		$('div[class^="ui-"]').remove();
		$('#main').html(data);
		applySkin();
	});
}

function removeSpikesStdDev(local_graph_id) {
	var strURL = urlPath+'spikekill.php?method=stddev&local_graph_id='+local_graph_id;

	$.getJSON(strURL)
		.done(function(data) {
			checkForLogout(data);

			redrawGraph(local_graph_id);
			$('#spikeresults').remove();
		})
		.fail(function(data) {
			getPresentHTTPError(data);
		}
	);
}

function removeSpikesVariance(local_graph_id) {
	var strURL = urlPath+'spikekill.php?method=variance&local_graph_id='+local_graph_id;

	$.getJSON(strURL)
		.done(function(data) {
			checkForLogout(data);

			redrawGraph(local_graph_id);
			$('#spikeresults').remove();
		})
		.fail(function(data) {
			getPresentHTTPError(data);
		}
	);
}

function removeSpikesInRange(local_graph_id) {
	var strURL = urlPath+'spikekill.php?method=fill&avgnan=last&local_graph_id='+local_graph_id+'&outlier-start='+graph_start+'&outlier-end='+graph_end;

	$.getJSON(strURL)
		.done(function(data) {
			checkForLogout(data);

			redrawGraph(local_graph_id);
			$('#spikeresults').remove();
		})
		.fail(function(data) {
			getPresentHTTPError(data);
		}
	);
}

function removeRangeFill(local_graph_id) {
	var strURL = urlPath+'spikekill.php?method=float&avgnan=last&local_graph_id='+local_graph_id+'&outlier-start='+graph_start+'&outlier-end='+graph_end;

	$.getJSON(strURL)
		.done(function(data) {
			checkForLogout(data);

			redrawGraph(local_graph_id);
			$('#spikeresults').remove();
		})
		.fail(function(data) {
			getPresentHTTPError(data);
		}
	);
}

function dryRunStdDev(local_graph_id) {
	var strURL = urlPath+'spikekill.php?method=stddev&dryrun=true&local_graph_id='+local_graph_id;

	$.getJSON(strURL)
		.done(function(data) {
			checkForLogout(data);

			$('#spikeresults').remove();
			$('body').append('<div id="spikeresults" style="overflow-y:scroll;" title="'+spikeKillResults+'"></div>');
			$('#spikeresults').html(data.results);
			$('#spikeresults').dialog({ width:1100, maxHeight: 600 });
		})
		.fail(function(data) {
			getPresentHTTPError(data);
		}
	);
}

function dryRunVariance(local_graph_id) {
	var strURL = urlPath+'spikekill.php?method=variance&dryrun=true&local_graph_id='+local_graph_id;

	$.getJSON(strURL)
		.done(function(data) {
			checkForLogout(data);

			$('#spikeresults').remove();
			$('body').append('<div id="spikeresults" style="overflow-y:scroll;" title="'+spikeKillResults+'"></div>');
			$('#spikeresults').html(data.results);
			$('#spikeresults').dialog({ width:1100, maxHeight: 600 });
		})
		.fail(function(data) {
			getPresentHTTPError(data);
		}
	);
}

function dryRunSpikesInRange(local_graph_id) {
	var strURL = urlPath+'spikekill.php?method=fill&avgnan=last&dryrun=true&local_graph_id='+local_graph_id+'&outlier-start='+graph_start+'&outlier-end='+graph_end;

	$.getJSON(strURL)
		.done(function(data) {
			checkForLogout(data);

			redrawGraph(local_graph_id);
			$('#spikeresults').remove();
			$('body').append('<div id="spikeresults" style="overflow-y:scroll;" title="'+spikeKillResults+'"></div>');
			$('#spikeresults').html(data.results);
			$('#spikeresults').dialog({ width:1100, maxHeight: 600 });
		})
		.fail(function(data) {
			getPresentHTTPError(data);
		}
	);
}

function dryRunRangeFill(local_graph_id) {
	var strURL = urlPath+'spikekill.php?method=float&avgnan=last&dryrun=true&local_graph_id='+local_graph_id+'&outlier-start='+graph_start+'&outlier-end='+graph_end;

	$.getJSON(strURL)
		.done(function(data) {
			checkForLogout(data);

			redrawGraph(local_graph_id);
			$('#spikeresults').remove();
			$('body').append('<div id="spikeresults" style="overflow-y:scroll;" title="'+spikeKillResults+'"></div>');
			$('#spikeresults').html(data.results);
			$('#spikeresults').dialog({ width:1100, maxHeight: 600 });
		})
		.fail(function(data) {
			getPresentHTTPError(data);
		}
	);
}

function redrawGraph(graph_id) {
	var mainWidth = getMainWidth();
	var isThumb   = $('#thumbnails').is(':checked');
	var myColumns = $('#columns').val();
	var graphRow  = $('.tableRowGraph').width();
	var drillDown = $('.graphDrillDown:first').outerWidth() + 10;

	if (mainWidth < graphRow) {
		graphRow = mainWidth - drillDown;
	} else if (graphRow == 0) {
		graphRow = mainWidth;
	}

	var myWidth = (graphRow-(drillDown * myColumns)) / myColumns;

	var graph_height = $('#wrapper_'+graph_id).attr('graph_height');
	var graph_width  = $('#wrapper_'+graph_id).attr('graph_width');

	$.getJSON(urlPath+'graph_json.php?rra_id=0'+
		'&local_graph_id='+graph_id+
		'&graph_start='+graph_start+
		'&graph_end='+graph_end+
		'&graph_height='+graph_height+
		'&graph_width='+graph_width+
		(isThumb ? '&graph_nolegend=true':''))
		.done(function(data) {
			if (typeof data.status == 'undefined') {
				if (myWidth < data.image_width) {
					ratio=myWidth/data.image_width;
					data.image_width  = parseInt(data.image_width  * ratio);
					data.image_height = parseInt(data.image_height * ratio);
					data.graph_width  = parseInt(data.graph_width  * ratio);
					data.graph_height = parseInt(data.graph_height * ratio);
					data.graph_top    = parseInt(data.graph_top  * ratio);
					data.graph_left   = parseInt(data.graph_left * ratio);
				}

				$('#wrapper_'+data.local_graph_id).html(
					"<img class='graphimage' id='graph_"+data.local_graph_id+"'"+
					" src='data:image/"+data.type+";base64,"+data.image+"'"+
					" rra_id='"+data.rra_id+"'"+
					" graph_id='"+data.local_graph_id+"'"+
					" graph_start='"+data.graph_start+"'"+
					" graph_end='"+data.graph_end+"'"+
					" graph_left='"+data.graph_left+"'"+
					" graph_top='"+data.graph_top+"'"+
					" graph_width='"+data.graph_width+"'"+
					" graph_height='"+data.graph_height+"'"+
					" width='"+data.image_width+"'"+
					" height='"+data.image_height+"'"+
					" image_width='"+data.image_width+"'"+
					" image_height='"+data.image_height+"'"+
					" canvas_top='"+data.graph_top+"'"+
					" canvas_left='"+data.graph_left+"'"+
					" canvas_width='"+data.graph_width+"'"+
					" canvas_height='"+data.graph_height+"'"+
					" value_min='"+data.value_min+"'"+
					" value_max='"+data.value_max+"'>"
				);

				$('#zoom-container').remove();
				$('#graph_'+data.local_graph_id).zoom({
					inputfieldStartTime : 'date1',
					inputfieldEndTime : 'date2',
					serverTimeOffset : timeOffset
				});
			} else {
				getPresentHTTPError(data);
			}
		})
		.fail(function(data) {
			getPresentHTTPError(data);
		}
	);
}

function initializeGraphs() {
	$.ajaxQ.abortAll();

	$('a[id$="_mrtg"]').each(function() {
		var graph_id = $(this).attr('id').replace('graph_','').replace('_mrtg','');

		$(this).attr('href', urlPath+'graph.php?local_graph_id='+graph_id).addClass('linkEditMain');;

		$(this).off('click').on('click', function(event) {
			var graph_id=$(this).attr('id').replace('graph_','').replace('_mrtg','');

			event.preventDefault();
			event.stopPropagation();

			$.ajaxQ.abortAll();
			$.get(urlPath+'graph.php?local_graph_id='+graph_id+'&header=false')
				.done(function(data) {
					checkForLogout(data);

					$('#main').empty().hide();
					$('#breadcrumbs').append('<li><a id="nav_mrgt" href="#">'+timeGraphView+'</a></li>');
					$('#zoom-container').remove();
					$('div[class^="ui-"]').remove();
					$('#main').html(data);
					applySkin();
					clearTimeout(myRefresh);
				})
				.fail(function(data) {
					getPresentHTTPError(data);
				}
			);
		});
	});

	$('a[id$="_csv"]').each(function() {
		var graph_id=$(this).attr('id').replace('graph_','').replace('_csv','');

		// Disable context menu
		$(this).children().contextmenu(function() {
			return false;
		});

		$(this).attr('href',urlPath+
			'graph_xport.php?local_graph_id='+graph_id+
			'&rra_id=0&view_type=tree&graph_start='+getTimestampFromDate($('#date1').val())+
			'&graph_end='+getTimestampFromDate($('#date2').val())).addClass('linkEditMain');

		$(this).off('click').on('click', function(event) {
			var graph_id = $(this).attr('id').replace('graph_','').replace('_csv','');
			event.preventDefault();
			event.stopPropagation();
			document.location = urlPath+
				'graph_xport.php?local_graph_id='+graph_id+
				'&rra_id=0&view_type=tree&graph_start='+getTimestampFromDate($('#date1').val())+
				'&graph_end='+getTimestampFromDate($('#date2').val());
		});
	});

	$('#form_graph_view').off('submit').on('submit', function(event) {
		event.preventDefault();
		event.stopPropagation();
		applyFilter();
	});

	var mainWidth = getMainWidth();
	var myColumns = $('#columns').val();
	var isThumb   = $('#thumbnails').is(':checked');
	var myWidth   = (mainWidth-(30*myColumns))/myColumns;

	$('.graphWrapper').each(function() {
		var graph_id = $(this).attr('graph_id');
		if (!(graph_id > 0)) {
			graph_id=$(this).attr('id').replace('wrapper_','');
		}

		var rra_id = $(this).attr('rra_id');
		if (!(rra_id > 0)) {
			rra_id=0;
		}

		var graph_height = $(this).attr('graph_height');
		var graph_width  = $(this).attr('graph_width');

		$.getJSON(urlPath+'graph_json.php?rra_id='+rra_id+
			'&local_graph_id='+graph_id+
			'&graph_start='+graph_start+
			'&graph_end='+graph_end+
			'&graph_height='+graph_height+
			'&graph_width='+graph_width+
			(isThumb ? '&graph_nolegend=true':''))
			.done(function(data) {
				if (myWidth < data.image_width) {
					ratio=myWidth/data.image_width;
					data.image_width  = parseInt(data.image_width  * ratio);
					data.image_height = parseInt(data.image_height * ratio);
					data.graph_width  = parseInt(data.graph_width  * ratio);
					data.graph_height = parseInt(data.graph_height * ratio);
					data.graph_top    = parseInt(data.graph_top  * ratio);
					data.graph_left   = parseInt(data.graph_left * ratio);
				}
				var wrapper_id = '#wrapper_'+data.local_graph_id;
				if (rra_id > 0) {
					wrapper_id += '[rra_id=\'' + data.rra_id + '\']';
				}

				$(wrapper_id).html(
					"<img class='graphimage' id='graph_"+data.local_graph_id+"'"+
					" src='data:image/"+data.type+";base64,"+data.image+"'"+
					" rra_id='"+data.rra_id+"'"+
					" graph_start='"+data.graph_start+"'"+
					" graph_end='"+data.graph_end+"'"+
					" graph_left='"+data.graph_left+"'"+
					" graph_top='"+data.graph_top+"'"+
					" graph_width='"+data.graph_width+"'"+
					" graph_height='"+data.graph_height+"'"+
					" width='"+data.image_width+"'"+
					" height='"+data.image_height+"'"+
					" image_width='"+data.image_width+"'"+
					" image_height='"+data.image_height+"'"+
					" canvas_top='"+data.graph_top+"'"+
					" canvas_left='"+data.graph_left+"'"+
					" canvas_width='"+data.graph_width+"'"+
					" canvas_height='"+data.graph_height+"'"+
					" value_min='"+data.value_min+"'"+
					" value_max='"+data.value_max+"'>"
				);

				var graph_id = '#graph_'+data.local_graph_id;
				if (rra_id > 0) {
					graph_id += '[rra_id=\'' + data.rra_id + '\']';
				}

				$(graph_id).zoom({
					inputfieldStartTime : 'date1',
					inputfieldEndTime : 'date2',
					serverTimeOffset : timeOffset
				});

				realtimeArray[data.local_graph_id] = false;
			})
			.fail(function(data) {
				getPresentHTTPError(data);
			}
		);
	});

	$('#realtimeoff').off('click').on('click', function() {
		stopRealtime();
	});

	$('#ds_step').off('change').on('change', function() {
		realtimeGrapher();
	});

	$('a[id$="_util"]').each(function() {
		var graph_id = $(this).attr('id').replace('graph_','').replace('_util','');
		$(this).attr('href',urlPath+
			'graph.php?action=zoom&local_graph_id='+graph_id+
			'&rra_id=0&graph_start='+getTimestampFromDate($('#date1').val())+
			'&graph_end='+getTimestampFromDate($('#date2').val())).addClass('linkEditMain');

		$(this).off('click').on('click', function(event) {
			var graph_id = $(this).attr('id').replace('graph_','').replace('_util','');

			event.preventDefault();
			event.stopPropagation();

			$.ajaxQ.abortAll();
			$.get(urlPath+'graph.php?action=zoom&header=false&local_graph_id='+graph_id+'&rra_id=0&graph_start='+getTimestampFromDate($('#date1').val())+'&graph_end='+getTimestampFromDate($('#date2').val()))
				.done(function(data) {
					checkForLogout(data);

					$('#main').empty().hide();
					$('div[class^="ui-"]').remove();
					$('#main').html(data);
					$('#breadcrumbs').append('<li><a id="nav_butil" href="#">'+utilityView+'</a></li>');
					applySkin();
					clearTimeout(myRefresh);
				})
				.fail(function(data) {
					getPresentHTTPError(data);
				}
			);
		});
	});

	$('a[id$="_realtime"]').each(function() {
		// Disable right click
		$(this).children().on('contextmenu', function(event) {
			return false;
		});

		$(this).off('click').on('click', function(event) {
			var graph_id = $(this).attr('id').replace('graph_','').replace('_realtime','');

			event.preventDefault();
			event.stopPropagation();

			if (realtimeArray[graph_id]) {
				$('#wrapper_'+graph_id).html(keepRealtime[graph_id]).change();
				$(this).html("<img class='drillDown' title='"+realtimeClickOn+"' alt='' src='"+urlPath+"images/chart_curve_go.png'>");

				$('graph_id'+graph_id).tooltip().zoom({
					inputfieldStartTime : 'date1',
					inputfieldEndTime : 'date2',
					serverTimeOffset : timeOffset
				});

				realtimeArray[graph_id] = false;
				setFilters();
			} else {
				keepRealtime[graph_id]  = $('#wrapper_'+graph_id).html();
				$(this).html("<i style='text-align:center;padding:0px;' title='"+realtimeClickOff+"' class='drillDown fa fa-circle-notch fa-spin'/>");
				$(this).find('i').tooltip();
				realtimeArray[graph_id] = true;
				setFilters();
				realtimeGrapher();
			}
		});
	});
}

$.widget('custom.languageselect', $.ui.selectmenu, {
	_renderItem: function(ul, item) {
		var li = $('<li>');
		var wrapper = $('<div>', { text: item.label });
		if (item.disabled) {
			li.addClass( 'ui-state-disabled' );
		}

		$('<span>', {
			style: item.element.attr('data-style') + ';float:right',
			'class': 'right flag-icon flag-icon-squared ' + item.element.attr('data-class')
		}).appendTo(wrapper);

		return li.append(wrapper).appendTo(ul);
	}
});

// combobox example borrowed from jqueryui
$.widget('custom.dropcolor', {
	_create: function() {
		$('body').append('<div id="cwrap" class="ui-selectmenu-menu ui-front">');

		this.wrapper = $('<span><span class="ui-select-text"><div id="bgc" class="ui-icon color-icon" style="margin-left:2px;margin-right:3px;"></div></span></span>')
		.addClass('class="ui-selectmenu-button ui-selectmenu-button-closed ui-corner-all ui-button ui-widget"')
		.insertAfter(this.element);

		this.element.hide();
		this._createAutocomplete();
		this._createShowAllButton();
	},

	_createAutocomplete: function() {
		var selected = this.element.children(':selected');
		var value = selected.val() ? selected.text() : '';
		var regExp = /\(([^)]+)\)/;
		var hex   = regExp.exec(value);

		if (hex != null) {
			this.wrapper.find('#bgc').css('background-color', '#'+hex[1]);
		}
		this.input = $('<input class="ui-autocomplete-input ui-state-default ui-selectmenut-text" style="background:transparent;border:0px;margin-left:-22px;padding:0px 3px 0px 22px;" value="'+value+'">')
		.appendTo(this.wrapper)
		.on('click', function() {
			$(this).autocomplete('search', '');
		})
		.autocomplete({
			delay: 0,
			minLength: 0,
			source: $.proxy(this, '_source'),
			select: $.proxy(this, '_select'),
			search: function() {
				$(this).data('ui-autocomplete').menu.bindings = $();
			},
			close: function() {
				$(this).data('ui-autocomplete').menu.bindings = $();
			},
			create: function() {
				$(this).data('ui-autocomplete')._renderItem = function(ul, item) {
					var regExp = /\(([^)]+)\)/;
					var hex   = regExp.exec(item.label);

					if (hex !== null) {
						color = hex[1];
						return $('<li>').attr('data-value', item.value).html('<div><span style="background-color:#'+color+';" class="ui-icon color-icon"></span>'+item.label+'</div>').appendTo(ul);
					} else {
						return $('<li>').attr('data-value', item.value).html('<div><span class="ui-icon color-icon"></span>'+item.label+'</div>').appendTo(ul);
					}
				}

				$(this).data('ui-autocomplete')._resizeMenu = function () {
					var ul = this.menu.element;
					ul.outerWidth('220px');
				}
			}
		});

		this._on(this.input, {
			autocompleteselect: function(event, ui) {
				ui.item.option.selected = true;
				this._trigger('select', event, {
					item: ui.item.option
				});
			},

			autocompletechange: '_removeIfInvalid'
		});
	},

	_select: function(event, ui) {
		var regExp = /\(([^)]+)\)/;
		var hex    = regExp.exec(ui.item.label);
		var id     = $(ui.item.option).attr('value');

		if (hex !== null) {
			color = hex[1];
			this.wrapper.find('#bgc').css('background-color', '#'+color);
			this.wrapper.find('input').val(ui.item.value);
		} else {
			this.wrapper.find('#bgc').css('background-color', '');
			this.wrapper.find('input').val(ui.item.value);
		}
	},

	_createShowAllButton: function() {
		var input = this.input;
		var wasOpen = false;

		$('<span>')
		.attr('tabIndex', -1)
		.appendTo(this.wrapper)
		.addClass('ui-icon ui-icon-triangle-1-s')
		.on('mousedown', function() {
			wasOpen = input.autocomplete('widget').is(':visible');
		})
		.on('click', function() {
			input.trigger('focus');

			// Close if already visible
			if (wasOpen) {
				return;
			}

			input.autocomplete('search', '');
		});
	},

	_source: function(request, response) {
		var matcher = new RegExp($.ui.autocomplete.escapeRegex(request.term), 'i');
		results = this.element.children('option').map(function() {
			var text = $(this).text();
			if (this.value && (!request.term || matcher.test(text))) {
				return {
					label: text,
					value: text,
					option: this
				};
			}
		});

		response(results);
	},

	_removeIfInvalid: function(event, ui) {
		// Selected an item, nothing to do
		if (ui.item) {
			return;
		}

		// Search for a match (case-insensitive)
		var value = this.input.val();
		var valueLowerCase = value.toLowerCase();
		var valid = false;

		this.element.children('option').each(function() {
			if ($(this).text().toLowerCase() === valueLowerCase) {
				this.selected = valid = true;
				return false;
			}
		});

		// Found a match, nothing to do
		if (valid) {
			return;
		}

		// Remove invalid value
		this.input.val('');
		this.element.val('');
		this._delay(function() {
			this.input.tooltip('close').attr('title', '');
		}, 2500 );
		this.input.autocomplete('instance').term = '';
	},

	_destroy: function() {
		this.wrapper.remove();
		this.element.show();
	}
});

function expandClipboardSection(section) {
	var isVisible = section.is(':visible');
	if (!isVisible) {
		section.slideDown('fast');
	}

	var children = section.find('table').each(function (i) {
		expandClipboardSection($(this));
	});
}

function copyToClipboard(containerId) {
	var clipboardDataId = containerId.replace('copyToClipboard','clipboardData');
	var clipboardData   = document.getElementById(clipboardDataId);

	var messageWidth = $(window).width();
	if (messageWidth > 350) {
		messageWidth = 350;
	} else {
		messageWidth -= 50;
	}

	if (clipboardData == null) {
		$('body').append('<div style="display:none;" id="clipboardMessage" title="'+clipboard+'">'+clipboardCopyFailed+'<br/><br/>'+clipboardID+': '+clipboardDataId+'</div>');

		$('#clipboardMessage').dialog({
			resizable: false,
			draggable: false,
			height: 170,
			width: messageWidth,
			buttons: {
				Ok: function() {
					$(this).dialog('close');
					$('#clipboardMessage').remove();
				}
			}
		});

		$('#clipboardMessage').dialog('open');
	} else if (!document.queryCommandSupported('copy')) {
		$('body').append('<div style="display:none;" id="clipboardMessage" title="'+clipboard+'">'+clipboardNotAvailable+'</div>');

		$('#clipboardMessage').dialog({
			resizable: false,
			draggable: false,
			height: 120,
			width: messageWidth,
			buttons: {
				Ok: function() {
					$(this).dialog('close');
					$('#clipboardMessage').remove();
				}
			}
		});

		$('#clipboardMessage').dialog('open');
	} else {
		var clipboardHeaderId = containerId.replace('copyToClipboard','clipboardHeader');
		var clipboardHeader   = document.getElementById(clipboardHeaderId);

		if (clipboardData != null) {
			expandClipboardSection($(clipboardData));
		}

		if (clipboardHeader != null) {
			expandClipboardSection($(clipboardHeader));
		}

		// get Selection object from currently user selected text
		var selection = window.getSelection();

		// unselect any user selected text (if any)
		selection.removeAllRanges();

		// create new range object
		var range = document.createRange();

		// set range to encompass desired element text
		if (clipboardHeader == null) {
			range.selectNode(clipboardData);
		} else {
			range.selectNode(clipboardHeader);
		}

		// add range to Selection object to select it
		selection.addRange(range);

		var success = document.execCommand('copy');
		selection.removeAllRanges();

		var successMessage = (!success ? clipboardNotUpdated : clipboardUpdated);

		$('body').append('<div style="display:none;" id="clipboardMessage" title="'+clipboard+'">'+successMessage+'</div>');

		$('#clipboardMessage').dialog({
			resizable: false,
			draggable: false,
			height: 120,
			width: messageWidth,
			buttons: {
				Ok: function() {
					$(this).dialog('close');
					$('#clipboardMessage').remove();
				}
			}
		});

		$('#clipboardMessage').dialog('open');
	}
}

var snmp_password        = '';
var snmp_auth_protocol   = '';
var snmp_priv_protocol   = '';
var snmp_priv_passphrase = '';
var snmp_security_initialized = false;

function storeSNMPSecurity() {
	if ($('#snmp_version').val() == '3') {
		if ($('#snmp_auth_protocol').val() != '[None]') {
			snmp_auth_protocol = $('#snmp_auth_protocol').val();
			snmp_password      = $('#snmp_password').val();
		} else {
			snmp_auth_protocol = '';
			snmp_password      = '';
		}

		if ($('#snmp_priv_protocol').val() != '[None]') {
			snmp_priv_protocol   = $('#snmp_priv_protocol').val();
			snmp_priv_passphrase = $('#snmp_priv_passphrase').val();
		} else {
			snmp_priv_protocol   = '';
			snmp_priv_passphrase = '';
		}
	} else {
		$('#snmp_security_level').val(defaultSNMPSecurityLevel);
	}
}

function setSNMPSecurity() {
	if ($('#snmp_version').val() == '3') {
		if (!snmp_security_initialized) {
			if ($('#snmp_auth_protocol').val() == '[None]') {
				$('#snmp_security_level').val('noAuthNoPriv');
			} else if ($('#snmp_priv_protocol').val() == '[None]') {
				$('#snmp_security_level').val('authNoPriv');
			} else {
				$('#snmp_security_level').val('authPriv');
			}

			var selectmenu = ($('#snmp_security_level').selectmenu('instance') !== undefined);
			if (selectmenu) {
				$('#snmp_security_level').selectmenu('refresh');
			}

			$('#snmp_password').keyup(function() {
				checkSNMPPassphrase('auth');
			});

			$('#snmp_password_confirm').keyup(function() {
				checkSNMPPassphraseConfirm('auth');
			});

			$('#snmp_priv_passphrase').keyup(function() {
				checkSNMPPassphrase('priv');
			});

			$('#snmp_priv_passphrase_confirm').keyup(function() {
				checkSNMPPassphraseConfirm('priv');
			});
		}

		snmp_security_initialized = true;
	}
}

function setSNMP() {
	var snmp_version = $('#snmp_version').val();

	storeSNMPSecurity();
	setSNMPSecurity();

	switch(snmp_version) {
		case '0': // Not in Use
			$('#row_snmp_username').hide();
			$('#row_snmp_password').hide();
			$('#row_snmp_community').hide();
			$('#row_snmp_security_level').hide();
			$('#row_snmp_auth_password').hide();
			$('#row_snmp_auth_protocol').hide();
			$('#row_snmp_priv_passphrase').hide();
			$('#row_snmp_priv_protocol').hide();
			$('#row_snmp_engine_id').hide();
			$('#row_snmp_context').hide();
			$('#row_snmp_port').hide();
			$('#row_snmp_timeout').hide();
			$('#row_max_oids').hide();

			if ($('#row_snmp_engine_id')) {
				$('#row_snmp_engine_id').hide();
			}

			if ($('#row_snmp_retries')) {
				$('#row_snmp_retries').hide();
			}

			break;
		case '1': // SNMP v1
		case '2': // SNMP v2c
			$('#row_snmp_username').hide();
			$('#row_snmp_password').hide();
			$('#row_snmp_community').show();
			$('#row_snmp_security_level').hide();
			$('#row_snmp_auth_password').hide();
			$('#row_snmp_auth_protocol').hide();
			$('#row_snmp_priv_passphrase').hide();
			$('#row_snmp_priv_protocol').hide();
			$('#row_snmp_engine_id').hide();
			$('#row_snmp_context').hide();
			$('#row_snmp_port').show();
			$('#row_snmp_timeout').show();
			$('#row_max_oids').show();

			if ($('#row_snmp_engine_id')) {
				$('#row_snmp_engine_id').hide();
			}

			if ($('#row_snmp_retries')) {
				$('#row_snmp_retries').show();
			}

			break;
		case '3': // SNMP v3
			$('#row_snmp_username').show();
			$('#row_snmp_password').show();
			$('#row_snmp_community').hide();
			$('#row_snmp_security_level').show();
			$('#row_snmp_auth_password').show();
			$('#row_snmp_auth_protocol').show();
			$('#row_snmp_priv_passphrase').show();
			$('#row_snmp_priv_protocol').show();
			$('#row_snmp_engine_id').show();
			$('#row_snmp_context').show();
			$('#row_snmp_port').show();
			$('#row_snmp_timeout').show();
			$('#row_max_oids').show();

			if ($('#row_snmp_engine_id')) {
				$('#row_snmp_engine_id').show();
			}

			if ($('#row_snmp_retries')) {
				$('#row_snmp_retries').show();
			}

			if ($('#snmp_security_level').val() == 'noAuthNoPriv') {
				$('#snmp_auth_protocol option[value*="None"').prop('disabled', false);
				$('#snmp_priv_protocol option[value*="None"').prop('disabled', false);

				if ($('#snmp_auth_protocol').val() != '[None]') {
					snmp_auth_protocol   = $('#snmp_auth_protocol').val();
					snmp_password        = $('#snmp_password').val();
				}

				if ($('#snmp_priv_protocol').val() != '[None]') {
					snmp_priv_protocol   = $('#snmp_priv_protocol').val();
					snmp_priv_passphrase = $('#snmp_priv_passphrase').val();
				}

				$('#snmp_auth_protocol').val('[None]');
				$('#snmp_priv_protocol').val('[None]');
				$('#row_snmp_auth_protocol').hide();
				$('#row_snmp_priv_protocol').hide();
				$('#row_snmp_password').hide();
				$('#row_snmp_priv_passphrase').hide();
			} else if ($('#snmp_security_level').val() == 'authNoPriv') {
				$('#snmp_auth_protocol option[value*="None"').prop('disabled', false);
				$('#snmp_priv_protocol option[value*="None"').prop('disabled', false);

				if ($('#snmp_priv_protocol').val() != '[None]') {
					snmp_priv_protocol   = $('#snmp_priv_protocol').val();
					snmp_priv_passphrase = $('#snmp_priv_passphrase').val();
				}

				if (snmp_auth_protocol != '[None]' && snmp_auth_protocol != '' && $('#snmp_auth_protocol').val() == '[None]') {
					$('#snmp_auth_protocol').val(snmp_auth_protocol);
					$('#snmp_password').val(snmp_password);
					$('#snmp_password_confirm').val(snmp_password);
				} else if ($('#snmp_auth_protocol').val() == '[None]' || $('#snmp_auth_protocol').val() == '') {
					if (defaultSNMPAuthProtocol == '' || defaultSNMPAuthProtocol == '[None]') {
						$('#snmp_auth_protocol').val('MD5');
					} else {
						$('#snmp_auth_protocol').val(defaultSNMPAuthProtocol);
					}
				}

				$('#snmp_priv_protocol').val('[None]');
				$('#row_snmp_priv_protocol').hide();
				$('#row_snmp_priv_passphrase').hide();

				$('#snmp_auth_protocol option[value*="None"').prop('disabled', true);
				$('#snmp_priv_protocol option[value*="None"').prop('disabled', false);
				checkSNMPPassphrase('auth');
			} else {
				$('#snmp_auth_protocol option[value*="None"').prop('disabled', false);
				$('#snmp_priv_protocol option[value*="None"').prop('disabled', false);

				if (snmp_auth_protocol != '' && $('#snmp_auth_protocol').val() == '[None]') {
					$('#snmp_auth_protocol').val(snmp_auth_protocol);
					$('#snmp_password').val(snmp_password);
					$('#snmp_password_confirm').val(snmp_password);
				} else if ($('#snmp_auth_protocol').val() == '[None]' || $('#snmp_auth_protocol').val() == '') {
					if (defaultSNMPAuthProtocol == '' || defaultSNMPAuthProtocol == '[None]') {
						$('#snmp_auth_protocol').val('MD5');
					} else {
						$('#snmp_auth_protocol').val(defaultSNMPAuthProtocol);
					}
				}

				if (snmp_priv_protocol != '' && $('#snmp_priv_protocol').val() == '[None]') {
					$('#snmp_priv_protocol').val(snmp_priv_protocol);
					$('#snmp_priv_passphrase').val(snmp_priv_passphrase);
				} else if ($('#snmp_priv_protocol').val() == '[None]' || $('#snmp_priv_protocol').val() == '') {
					if (defaultSNMPPrivProtocol == '' || defaultSNMPPrivProtocol == '[None]') {
						$('#snmp_priv_protocol').val('DES');
					} else {
						$('#snmp_priv_protocol').val(defaultSNMPPrivProtocol);
					}
				}

				$('#snmp_auth_protocol option[value*="None"').prop('disabled', true);
				$('#snmp_priv_protocol option[value*="None"').prop('disabled', true);
				checkSNMPPassphrase('auth');
				checkSNMPPassphrase('priv');
			}

			if ($('#snmp_auth_protocol').val() == '[None]') {
				$('#row_snmp_password').hide();
				$('#snmp_password').val('');
				$('#snmp_password_confirm').val('');
			}

			if ($('#snmp_priv_protocol').val() == '[None]') {
				$('#row_snmp_priv_passphrase').hide();
				$('#snmp_priv_passphrase').val('');
			}

			selectmenu = ($('#snmp_security_level').selectmenu('instance') !== undefined);
			if (selectmenu) {
				$('#snmp_security_level').selectmenu('refresh');
				$('#snmp_auth_protocol').selectmenu('refresh');
				$('#snmp_priv_protocol').selectmenu('refresh');
			}

			break;
	}
}

function checkSNMPPassphrase(type) {
	var minChars = 8;

	if (type == 'priv') {
		var pass = '#snmp_priv_passphrase';
		var conf = '#snmp_priv_passphrase_confirm';
		var span = 'priv';
	} else {
		var pass = '#snmp_password';
		var conf = '#snmp_password_confirm';
		var span = 'auth';
	}

	if ($(pass).val().length == 0) {
		$('#'+span).remove();
		$('#'+span+'conf').remove();
	} else if ($(pass).val().length < minChars) {
		$('#'+span).remove();
		$(pass).after('<span id="'+span+'"><i class="badpassword fa fa-times"></i><span style="padding-left:4px;">'+passwordTooShort+'<span></span>');
		checkSNMPPassphraseConfirm(type);
	} else {
		$('#'+span).remove();
		$(pass).after('<span id="'+span+'"><i class="goodpassword fa fa-check"></i><span style="padding-left:4px;">'+passwordPass+'</span></span>');
		checkSNMPPassphraseConfirm(type);
	}
}

function checkSNMPPassphraseConfirm(type) {
	var minChars = 8;

	if (type == 'priv') {
		var pass     = '#snmp_priv_passphrase';
		var conf     = '#snmp_priv_passphrase_confirm';
		var span     = 'priv';
		var spanconf = 'privconf';
	} else {
		var pass     = '#snmp_password';
		var conf     = '#snmp_password_confirm';
		var span     = 'auth';
		var spanconf = 'authconf';
	}

	if ($(conf).val().length < minChars) {
		passphrase = $(pass).val();
		if (passphrase.indexOf($(conf).val()) == 0) {
			$('#'+spanconf).remove();
			$(conf).after('<span id="'+spanconf+'"><i class="badpassword fa fa-times"></i><span style="padding-left:4px;">'+passwordMatchTooShort+'<span></span>');
		} else {
			$('#'+spanconf).remove();
			$(conf).after('<span id="'+spanconf+'"><i class="badpassword fa fa-times"></i><span style="padding-left:4px;">'+passwordNotMatchTooShort+'<span></span>');
		}
	} else {
		if ($(pass).val() != $(conf).val()) {
			$('#'+spanconf).remove();
			$(conf).after('<span id="'+spanconf+'"><i class="badpassword fa fa-times"></i><span style="padding-left:4px;">'+passwordNotMatch+'</span></span>');
		} else {
			$('#'+span).remove();
			$('#'+spanconf).remove();
			$(pass).after('<span id="'+spanconf+'"><i class="goodpassword fa fa-check"></i><span style="padding-left:4px;">'+passwordMatch+'</span></span>');
		}
	}
}



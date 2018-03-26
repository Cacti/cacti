/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2018 The Cacti Group                                 |
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

var theme;
var myRefresh;
var userMenuTimer;
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
var menuOpen = null;
var menuHideResponsive = null;
var marginLeft = null;
var pageName;
var columnsHidden = 0;
var lastColumnsHidden = {};
var lastWidth = {};
var resizeDelta = 100;
var resizeTime = 0;
var resizeTimeout = false;
var myGraphLocation = false;

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
	$el.unbind(which, handler);
	$el.bind(which, handler);

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
	}
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
	var prev_checkbox = $(clicked_element).closest('tr').siblings().find('[data-prev-check]:checkbox');
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
	$('input[type="checkbox"], input[type="radio"]').unbind('click').click(function() {
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
	$('tr[id^="gt_line"].selectable:not(.disabled_row)').find('td').not('.checkbox').each(function(data) {
		$(this).unbind('click').click(function(data) {
			$(this).closest('tr').toggleClass('selected');
			var checkbox = $(this).parent().find(':checkbox');
			checkbox.prop('checked', !checkbox.is(':checked'));
		});
	});

	// Create Actions for Checkboxes
	$('tr[id^="gt_line"].selectable').find('input.checkbox').unbind('click').click(function(data) {
		if (!$(this).is(':disabled')) {
			if ($(this).is(':checked')) {
				$('#all_cg').prop('checked', false);
			}

			$(this).closest('tr').toggleClass('selected');
		}
	});

	var lines = $('tr[id^="line"].selectable');
	var checkboxes = lines.find(':checkbox');

	// Create Actions for Rows
	lines.filter(':not(.disabled_row)').find('td').not('.checkbox').each(function(data) {
		$(this).unbind('click').click(function(e) {
			if (e.shiftKey) {
				updateCheckboxes(checkboxes, $(this).closest('tr').find(':checkbox'));
			} else {
				var checked = $(this).closest('tr').find(':checkbox').is(':checked');
				$(this).closest('tr').siblings().find(':checkbox').removeAttr('data-prev-check');
				$(this).closest('tr').toggleClass('selected').find(':checkbox').prop('checked', !checked)
					.attr('data-prev-check', !checked);
			}
		});
	});

	// Create Actions for Checkboxes
	lines.find('input.checkbox').unbind('click').click(function(e) {
		if (!$(this).is(':disabled')) {
			if (e.shiftKey) {
				updateCheckboxes(checkboxes, this);
			} else {
				$(this).closest('tr').toggleClass('selected');
				$(this).closest('tr').siblings().find(':checkbox').removeAttr('data-prev-check');
				$(this).attr('data-prev-check', $(this).prop('checked'));
			}
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
		} else {
			removeSelectAll = true;
		}
	});

	if (allChecked && removeSelectAll) {
		$('#all_'+snmp_query_id).prop('checked', false);
	}

	var dqlines = $('tr[id^="dqline'+snmp_query_id+'_"]').not('.disabled_row');
	var checkboxes = dqlines.find(':checkbox');
	dqlines.each(function() {
		$(this).find(':checkbox').unbind('click').click(function(e) {
			if (e.shiftKey) {
				updateCheckboxes(checkboxes, this);
			} else {
				$(this).closest('tr').toggleClass('selected');
				$(this).closest('tr').siblings().find(':checkbox').removeAttr('data-prev-check');
				$(this).attr('data-prev-check', $(this).prop('checked'));
			}
		});

		$(this).find('td').not('.checkbox').each(function() {
			$(this).unbind('click').click(function(e) {
				if (e.shiftKey) {
					updateCheckboxes(checkboxes, $(this).closest('tr').find(':checkbox'));
				} else {
					var checked = $(this).closest('tr').find(':checkbox').is(':checked');
					$(this).closest('tr').siblings().find(':checkbox').removeAttr('data-prev-check');
					$(this).closest('tr').toggleClass('selected').find(':checkbox').prop('checked', !checked)
						.attr('data-prev-check', !checked);
				}
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
		} else {
			$('tr[id^="line"]:not(.disabled_row)').each(function(data) {
				$(this).removeClass('selected');
				$(this).find(':checkbox').prop('checked', false);
			});
		}
	} else if (attrib == 'sg') {
		if (checked == true) {
			$('tr[id^="gt_line"]:not(.disabled_row)').each(function(data) {
				$(this).addClass('selected');
				$(this).find(':checkbox').prop('checked', true);
			});
		} else {
			$('tr[id^="gt_line"]:not(.disabled_row)').each(function(data) {
				$(this).removeClass('selected');
				$(this).find(':checkbox').prop('checked', false);
			});
		}
	} else {
		var attribSplit = attrib.split('_');
		var dq   = attribSplit[1];

		if (checked == true) {
			$('tr[id^="dqline'+dq+'\_"]:not(.disabled_row)').each(function(data) {
				$(this).addClass('selected');
				$(this).find(':checkbox').prop('checked', true);
			});
		} else {
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

	makeFiltersResponsive();

	setupResponsiveMenuAndTabs();

	keepWindowSize();

	// Add tooltips to graph drilldowns
	$('.drillDown').tooltip({
		content: function() {
			return $(this).prop('title');
		}
	});

	// Debug message actions
	$('table.debug').unbind('click').click(function() {
		if ($(this).find('table').is(':visible')) {
			$(this).find('table').slideUp('fast');
		} else {
			$(this).find('table').slideDown('fast');
		}
	});

	$('.cactiTableCopy').unbind('click').click(function(event) {
		event.preventDefault();
		event.stopPropagation();
		containerId =  $(this).attr('id');
		copyToClipboard(containerId);
	});

	$(document).tooltip({
		items: 'div.cactiTooltipHint, span.cactiTooltipHint, a, span',
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

	// remove stray tooltips
	$(document).tooltip('close');

	$('#main').show();

	var showPage = $('#main').map(function(i, el) {
		var dfd = $.Deferred();
		$(el).show(function() {
			dfd.resolve();
		});

		return dfd;
	});

	$.when.apply(this, showPage).done(function() {
		responsiveUI('force');
	});

	displayMessages();
}

function displayMessages() {
	var error   = false;
	var title   = '';
	var header  = '';

	if (typeof sessionMessageTimer === 'function') {
		clearInterval(sessionMessageTimer);
	}

	if (sessionMessage == null) {
		return;
	}

	if (typeof sessionMessage.type != 'undefined') {
		if (sessionMessage.type == 'error') {
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
					}
				}
			};

			sessionMessageOpen = function() {
				sessionMessageCountdown(5000);
			}
		}

		returnStr = '<div id="messageContainer" style="display:none">' +
			'<h4>' + header + '</h4>' +
			'<p style="display:table-cell;overflow:auto"> ' + sessionMessage.message + '</p>' +
			'</div>';

		$('#messageContainer').remove();
		$('body').append(returnStr);
		$('#messageContainer').dialog({
			open: sessionMessageOpen,
			draggable: true,
			resizable: false,
			height: 'auto',
			minWidth: 600,
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
	storage=Storages.localStorage;

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
					filterHeader.find('div.cactiTableButton').append('<span title="'+title+'" style="display:none;" class="cactiFilterExport"><i class="fa fa-arrow-down"</i></span>');

					$('.cactiFilterExport').unbind('click').click(function(event) {
						event.stopPropagation();
						$('#export').trigger('click');
					}).tooltip();
				}

				if (filterContents.find('#import').length) {
					title = $('#import').attr('value');
					filterHeader.find('div.cactiTableButton').append('<span title="'+title+'" style="display:none;" class="cactiFilterImport"><i class="fa fa-arrow-up"></i></span>');

					$('.cactiFilterImport').unbind('click').click(function(event) {
						event.stopPropagation();
						$('#import').trigger('click');
					}).tooltip();
				}

				if (filterContents.find('#clear').length) {
					filterHeader.find('div.cactiTableButton').append('<span title="'+clearFilterTitle+'" style="display:none;" class="cactiFilterClear"><i class="fa fa-trash-o"></i></span>');

					$('.cactiFilterClear').unbind('click').click(function(event) {
						event.stopPropagation();
						$('#clear').trigger('click');
					}).tooltip();
				}

				toggleFilterAndIcon(id, child, true);

				filterHeader.find('.cactiTableTitle, .cactiTableButton').unbind('click').click(function() {
					id     = $(this).closest('.cactiTable').attr('id');
					child  = id+'_child';
					toggleFilterAndIcon(id, child, false);
				});

				state = storage.get('filterVisibility');

				if (state == 'hidden') {
					filterHeader.find('div.cactiTableButton').append('<span class="cactiFilterState"><i class="fa fa-angle-double-down"></i></span>');
				} else {
					filterHeader.find('div.cactiTableButton').append('<span class="cactiFilterState"><i class="fa fa-angle-double-up"></i></span>');
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
				anchors.not('.cactiTableCopy').addClass('fa fa-trash-o');
				anchors.filter('.cactiTableCopy').addClass('fa fa-copy');
				anchors.tooltip().text('');
			}
		});
	}
}

function toggleFilterAndIcon(id, child, initial) {
	storage=Storages.localStorage;

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

function setupResponsiveMenuAndTabs() {
	$('a[id^="maintab-anchor"]').unbind('click').click(function(event) {
		event.preventDefault();

		if ($('.cactiTreeNavigationArea').length > 0) {
			tree = true;
		} else {
			tree = false;
		}

		if ($(this).hasClass('selected')) {
			if (menuOpen) {
				menuHide(tree);
			} else {
				menuShow(tree);
			}
		} else if (pageName == basename($(this).attr('href'))) {
			if (menuOpen) {
				menuHide(tree);
			} else {
				menuShow(tree);
			}
		} else {
			document.location = $(this).attr('href');
		}
	});

	$(window).on('orientationchange, fullscreenchange', function() {
		responsiveUI('force');
	});
}

function responsiveUI(event) {
	if (event != 'force') {
	    if (new Date() - resizeTime < resizeDelta) {
			myEvent = event;
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

	if ($(window).width() < 640) {
		menuHide(tree);
		menuHideResponsive = true;
	} else if (menuHideResponsive == true) {
		if (!menuOpen) {
			menuShow(tree);
			menuHideResponsive = false;
		}
	} else if (menuOpen != null) {
		if (!menuOpen) {
			menuHide(tree);
		}
	} else if (!menuOpen) {
		menuShow(tree);
	}

	if ($('#navigation').length && $('#navigation').is(':visible')) {
		mainWidth = $('body').innerWidth() - $('#navigation').width();
	} else {
		mainWidth = $('body').innerWidth();
	}

	/* change textbox and textarea widths */
	$('input[type="text"], textarea').each(function() {
		if ($(this).attr('type') == 'text') {
			offset = 20;
		} else {
			offset = 5;
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
			object = $(this).closest('.cactiTable');

			tuneTable(object, mainWidth);
		});
	});
}

function responsiveResizeGraphs() {
	if ($('.graphimage').length == 0) {
		return false;
	}

	if ($('#navigation').length && $('#navigation').is(':visible')) {
		mainWidth = $('body').innerWidth() - $('#navigation').width();
	} else {
		mainWidth = $('body').innerWidth();
	}

	myColumns = $('#columns').val();
	isThumb   = $('#thumbnails').is(':checked');
	graphRow  = $('.tableRowGraph:first').width();
	drillDown = $('.graphDrillDown:first').outerWidth() + 15;

	if (myColumns == null) {
		myColumns = 1;
	}

	if (mainWidth < graphRow) {
		graphRow = mainWidth - drillDown;
	} else if (graphRow == 0) {
		graphRow = mainWidth;
	}

	myWidth = parseInt((graphRow - (drillDown * myColumns)) / myColumns);

	$('.graphimage').each(function() {
		graph_id = $(this).attr('graph_id');
		if (!(graph_id > 0)) {
			graph_id = $(this).attr('id').replace('wrapper_','');
		}

		rra_id = $(this).attr('rra_id');

		/* original image attributes */
		image_width   = $(this).attr('image_width');
		image_height  = $(this).attr('image_height');
		canvas_top    = $(this).attr('canvas_top');
		canvas_left   = $(this).attr('canvas_left');
		canvas_width  = $(this).attr('canvas_width');
		canvas_height = $(this).attr('canvas_height');

		if (myWidth < image_width) {
			ratio = myWidth / image_width;
		} else {
			ratio = 1;
		}

		new_image_width       = parseInt(image_width * ratio)
		new_image_height      = parseInt(image_height * ratio)
		new_canvas_width      = parseInt(canvas_width  * ratio);
		new_canvas_height     = parseInt(canvas_height * ratio);
		new_canvas_graph_top  = parseInt(canvas_top  * ratio);
		new_canvas_graph_left = parseInt(canvas_left * ratio);

		$(this).attr('graph_width', new_canvas_width);
		$(this).attr('graph_height', new_canvas_height);
		$(this).attr('graph_top', new_canvas_graph_top);
		$(this).attr('graph_left', new_canvas_graph_left);

		$(this).css('width', new_image_width);
		$(this).css('height', new_image_height);

		$('#zoom-container').remove();
		$(this).zoom({
			inputfieldStartTime : 'date1',
			inputfieldEndTime : 'date2',
			serverTimeOffset : timeOffset
		});
	});
}

function countHiddenCols(object) {
	hidden = 0;
	$(object).find('th').each(function() {
		if ($(this).css('display') == 'none') {
			hidden++;
		}
	});

	return hidden;
}

function tuneTable(object, width) {
	rows           = $(object).find('tr').length;
	width          = width;
	tableWidth     = $(object).width();
	totalCols      = $(object).find('th').length;
	reducedWidth   = 0;
	columnsHidden  = countHiddenCols(object);
	visibleColumns = totalCols - columnsHidden;
	id             = $(object).attr('id');

	if (rows > 101) return false;

	if (tableWidth > width) {
		column = totalCols;
		hasCheckbox = $(object).find('th.tableSubHeaderCheckbox').length;

		if (hasCheckbox) {
			minColumns = 2;
		} else {
			minColumns = 2;
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
		column = 1;
		id     = $(object).attr('id');

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
		timespan = true;

		timeShiftWidth = $(object).find('.shiftArrow').closest('td').width();
		dateWidth      = $(object).find('#date1').closest('td').width() + $(object).find('#date1').closest('td').prev('td').width();
		clearWidth     = $('#tsclear').width();
		refreshWidth   = $('#tsrefresh').width();
	} else {
		timespan = false;

		clearWidth     = $(object).find('#clear').width();
		saveWidth      = $(object).find('#save').width();
		exportWidth    = $(object).find('#export').width();
		importWidth    = $(object).find('#import').width();
	}

	minTds = 2;
	visTds = $(object).find('td:visible').length;
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

function menuHide() {
	myClass = '';
    curMargin = $('#navigation').outerWidth();
	if (curMargin > 0) {
		marginLeft = curMargin;
	}

	if ($('.cactiTreeNavigationArea').length) {
		myClass = '.cactiTreeNavigationArea';
	} else if ($('.cactiConsoleNavigationArea').length) {
		myClass = '.cactiConsoleNavigationArea';
	}

	$('#navigation_right').animate({'margin-left': '0px'}, 20);

	if (myClass != '') {
		$(myClass).hide('slide', {direction: 'left'}, 20, function() {
			responsiveResizeGraphs();
		});
	}

	menuOpen = false;
}

function menuShow(tree) {
	myClass = '';

	if ($('.cactiTreeNavigationArea').length) {
		myClass = '.cactiTreeNavigationArea';
	} else if ($('.cactiConsoleNavigationArea').length) {
		myClass = '.cactiConsoleNavigationArea';
	}

	if (marginLeft > 0) {
		$('#navigation_right').animate({'margin-left': marginLeft}, 20);
	}

	if (myClass != '') {
		$(myClass).show('slide', {direction: 'left'}, 20, function() {
			responsiveResizeGraphs();
		});
	}

	menuOpen = true;
}

function loadPage(href) {
	statePushed = false;

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
				myHref  = href.replace('?header=false&', '?').replace('&header=false', '').replace('?header=false', '');

				pushState(myTitle, href);
			} else {
				checkForLogout(html);

				$('#main').empty().hide();
				$('#main').html(html);

				href = href.replace('?header=false&', '?').replace('&header=false', '').replace('?header=false', '');

				pushState(myTitle, href);
			}

			var hrefParts = href.split('?');
			pageName = basename(hrefParts[0]);

			if (pageName != '') {
				if ($('#menu').find("a[href*='"+href+"']").length > 0) {
					$('#menu').find('.pic').removeClass('selected');
					$('#menu').find("a[href*='"+href+"']").addClass('selected');
				} else {
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

			return false;
		})
		.fail(function(data) {
			getPresentHTTPError(data);
		}
	);

	return false;
}

function loadPageNoHeader(href, scroll) {
	statePushed = false;

	$.get(href)
		.done(function(data) {
			checkForLogout(data);

			$('#main').empty().hide();
			$('div[class^="ui-"]').remove();
			$('#main').html(data);

			var hrefParts = href.split('?');
			pageName = basename(hrefParts[0]);

			if (pageName != '') {
				$('#menu').find('.pic').removeClass('selected');
				$('#menu').find("a[href*='/"+pageName+"']").addClass('selected');
			}

			applySkin();

			pushState(myTitle, href);

			if (isMobile.any() != null) {
				window.scrollTo(0,1);
			} else {
				window.scrollTo(0,0);
			}

			return false;
		})
		.fail(function(data) {
			getPresentHTTPError(data);
		}
	);

	return false;
}

function getPresentHTTPError(data) {
	if (typeof data != 'undefined') {
		errorStr  = data.status;
		errorSub  = data.statusText;
		errorText = errorReasonUnexpected;

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

			returnStr = '<div id="httperror" style="display:none">' +
				'<h4>' + errorOnPage + '</h4><hr>' +
				'<div style="padding-bottom: 5px;"><div style="display:table-cell;width:75px"><b>' + errorNumberPrefix + '</b></div> ' +
				'<div style="display:table-cell"> ' + errorStr + ' ' + errorSub + '</div></div>' +
				'<div><div style="display:table-cell;width:75px"><b>'  + errorReasonPrefix + '</b></div> ' +
				'<div style="display:table-cell"> ' + errorText + '</div></div>' +
				'</div></div>';

			$('#httperror').remove();
			$('body').append(returnStr);
			$('#httperror').dialog({
				resizable: false,
				height: 'auto',
				width: 400,
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
	$('a.pic, a.linkOverDark, a.linkEditMain, a.hyperLink, a.tab').not('[href^="http"], [href^="https"], [href^="#"]').unbind('click').click(function(event) {
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

	$(window).on('popstate', function(event) {
		handlePopState();
	});

	$('#filter, #rfilter').keydown(function(event) {
		if (event.keyCode == 8 && $(this).val() == '') {
			handlePopState();
		}
	});
}

function setupCollapsible() {
	storage=Storages.localStorage;

	$('.collapsible').each(function(data) {
		id=$(this).attr('id')+'_cs';
		state = storage.get(id);
		if (state == 'hide') {
			$(this).addClass('collapsed');
			$(this).nextUntil('div.spacer').hide();
			$(this).find('i').removeClass('fa-angle-double-up').addClass('fa-angle-double-down');
			storage.set(id, 'hide');
		}
	});

	$('.collapsible').unbind('click').click(function(data) {
		id=$(this).attr('id')+'_cs';
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
		if (!$target.is('.ui-resizable-handle')) {
			var page     = $(this).find('.sortinfo').attr('sort-page');
			var column   = $(this).find('.sortinfo').attr('sort-column');
			var direction= $(this).find('.sortinfo').attr('sort-direction');

			if (shiftPressed) {
				sortAdd='&add=true';
			} else {
				sortAdd='&add=reset';
			}

			url = page+(page.indexOf('?') > 0 ? '&':'?') +
				'sort_column=' + column +
				'&sort_direction=' + direction +
				'&header=false' +
				sortAdd;

			$.get(url)
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
	});

	// Setup tool tips for all titles to match the jQueryUI theme
	$('i, th, img, input, label, select, button').tooltip({ closed: true }).on('focus', function() { $('#filter, #rfilter').tooltip('close'); }).on('click', function() { $(this).tooltip('close'); });
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
	storage=Storages.localStorage;

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
		} else if (key !== undefined && initial) {
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
			} else {
				if (previousPage != '') {
					refreshPage = previousPage;
				}

				/* fix coner case with tree refresh */
				refreshPage = refreshPage.replace('action=tree&', 'action=tree_content&');
				if (refreshPage.indexOf('header=false') == -1) {
					refreshPage += (refreshPage.indexOf('?') > 0 ? '&header=false':'?header=false');
				}

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

function setTitleAndHref() {
	myHref  = $(location).attr('href');
	myTitle = $(document).attr('title');
}

$(function() {
	var statePushed = false;
	var popFired    = false;
	var tapped      = false;

	setTitleAndHref();

	setupUserMenu();

	applySkin();

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

function keepWindowSize() {
	$(window).resize(function (event) {
		$('.cactiGraphContentArea').show();

   		resizeTime = new Date();
		myEvent = event;
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
			$('#cactiContent, #navigation, #navigation_right, #main').css('height', heightPageContent);

			// Handle links pages
			$('#content').css({'width':'100%', 'height':heightPageContent-4});
		} else {
			// Handle links pages
			$('#content').css({'width':'100%', 'height':$(document).height()});
		}

		navWidth = $('#navigation').width();
		$('#searcher').css('width', navWidth-70);

		responsiveResizeGraphs();
	}).resize();
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
	href = graphPage+'?action='+pageAction+'&clear=1';

	new_href = href.replace('action=tree&', 'action=tree_content&');

	$.ajaxQ.abortAll();
	$.get(new_href+'&header=false')
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
	href=graphPage+'?action=save'+
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
	href = graphPage+'?action='+pageAction+
		'&rfilter=' + base64_encode($('#rfilter').val())+
		'&host_id='+$('#host_id').val()+
		'&columns='+$('#columns').val()+
		'&graphs='+$('#graphs').val()+
		'&graph_template_id='+$('#graph_template_id').val()+
		'&thumbnails='+$('#thumbnails').is(':checked');

	new_href = href.replace('action=tree&', 'action=tree_content&');

	$.ajaxQ.abortAll();
	$.get(new_href+'&header=false')
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
	href = href.replace('header=false', '').replace('header=false', '').replace('&&', '&').replace('?&', '?');
	href = href.replace('action=tree_content', 'action=tree').replace('&&', '&');
	href = href.replace('&nostate=true', '').replace('?nostate=true', '');

	return href;
}

function pushState(myTitle, myHref) {
	if (myHref.indexOf('nostate') < 0) {
		if (statePushed == false) {
			var myObject = { myTitle: myHref };
			if (typeof window.history.pushState != 'undefined') {
				window.history.pushState(myObject, myTitle, cleanHeader(myHref));
			}
		}
	} else {
		if (typeof window.history.popState === 'function') {
			window.history.popState();
		}
	}

	statePushed = true;
}

function handlePopState() {
	var href = document.location.href;

	if (popFired == false) {
		if (href.indexOf('#') == -1) {
			if (href.indexOf('header=false') > 0) {
				loadPageNoHeader(href + '&nostate=true');
			} else if  (basename(href) == lastPage) {
				loadPageNoHeader(href + (href.indexOf('?') > 0 ? '&header=false&nostate=true':'?header=false&nostate=true'));
			} else {
				document.location = href + (href.indexOf('?') > 0 ? '&nostate=true':'?nostate=true');
			}
		}
	}

	popFired = true;
	lastPage = basename(href);
}

function applyGraphTimespan() {
	var href     = graphPage+'?action='+pageAction+'&header=false';
	var new_href = href.replace('action=tree&', 'action=tree_content&');

	$.ajaxQ.abortAll();
	$.get(new_href+'?action='+pageAction+'&header=false'+
		'&predefined_timespan='+$('#predefined_timespan').val()+
		'&predefined_timeshift='+$('#predefined_timeshift').val())
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

	var href     = graphPage+'?action='+pageAction+'&header=false';
	var new_href = href.replace('action=tree&', 'action=tree_content&');
	$.ajaxQ.abortAll();
	$.post(new_href, json).done(function(data) {
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

	var href     = graphPage+'?action='+pageAction+'&header=false';
	var new_href = href.replace('action=tree&', 'action=tree_content&');
	$.ajaxQ.abortAll();
	$.post(new_href, json).done(function(data) {
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

	var href     = graphPage+'?action='+pageAction+'&header=false';
	var new_href = href.replace('action=tree&', 'action=tree_content&');
	$.ajaxQ.abortAll();
	$.post(new_href, json).done(function(data) {
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

	var href     = graphPage+'?action='+pageAction+'&header=false';
	var new_href = href.replace('action=tree&', 'action=tree_content&');
	$.ajaxQ.abortAll();
	$.post(new_href, json).done(function(data) {
		checkForLogout(data);

		$('#main').empty().hide();
		$('div[class^="ui-"]').remove();
		$('#main').html(data);
		applySkin();
	});
}

function removeSpikesStdDev(local_graph_id) {
	strURL = 'spikekill.php?method=stddev&local_graph_id='+local_graph_id;
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
	strURL = 'spikekill.php?method=variance&local_graph_id='+local_graph_id;
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
	strURL = 'spikekill.php?method=fill&avgnan=last&local_graph_id='+local_graph_id+'&outlier-start='+graph_start+'&outlier-end='+graph_end;
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
	strURL = 'spikekill.php?method=float&avgnan=last&local_graph_id='+local_graph_id+'&outlier-start='+graph_start+'&outlier-end='+graph_end;
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
	strURL = 'spikekill.php?method=stddev&dryrun=true&local_graph_id='+local_graph_id;
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
	strURL = 'spikekill.php?method=variance&dryrun=true&local_graph_id='+local_graph_id;
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
	strURL = 'spikekill.php?method=fill&avgnan=last&dryrun=true&local_graph_id='+local_graph_id+'&outlier-start='+graph_start+'&outlier-end='+graph_end;
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
	strURL = 'spikekill.php?method=float&avgnan=last&dryrun=true&local_graph_id='+local_graph_id+'&outlier-start='+graph_start+'&outlier-end='+graph_end;
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
	mainWidth = $('#main').width();
	isThumb   = $('#thumbnails').is(':checked');

	myColumns = $('#columns').val();
	graphRow  = $('.tableRowGraph').width();
	drillDown = $('.graphDrillDown:first').outerWidth() + 10;

	if (mainWidth < graphRow) {
		graphRow = mainWidth - drillDown;
	} else if (graphRow == 0) {
		graphRow = mainWidth;
	}

	myWidth = (graphRow-(drillDown * myColumns)) / myColumns;

	graph_height= $('#wrapper_'+graph_id).attr('graph_height');
	graph_width = $('#wrapper_'+graph_id).attr('graph_width');

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
		graph_id=$(this).attr('id').replace('graph_','').replace('_mrtg','');

		$(this).attr('href', urlPath+'graph.php?local_graph_id='+graph_id).addClass('linkEditMain');;

		$(this).unbind('click').click(function(event) {
			graph_id=$(this).attr('id').replace('graph_','').replace('_mrtg','');
			event.preventDefault();
			event.stopPropagation();
			graph_id=$(this).attr('id').replace('graph_','').replace('_mrtg','');
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
		graph_id=$(this).attr('id').replace('graph_','').replace('_csv','');

		// Disable context menu
		$(this).children().contextmenu(function() {
			return false;
		});

		$(this).attr('href',urlPath+
			'graph_xport.php?local_graph_id='+graph_id+
			'&rra_id=0&view_type=tree&graph_start='+getTimestampFromDate($('#date1').val())+
			'&graph_end='+getTimestampFromDate($('#date2').val())).addClass('linkEditMain');

		$(this).unbind('click').click(function(event) {
			graph_id=$(this).attr('id').replace('graph_','').replace('_csv','');
			event.preventDefault();
			event.stopPropagation();
			document.location = urlPath+
				'graph_xport.php?local_graph_id='+graph_id+
				'&rra_id=0&view_type=tree&graph_start='+getTimestampFromDate($('#date1').val())+
				'&graph_end='+getTimestampFromDate($('#date2').val());
		});
	});

	$('#form_graph_view').unbind('submit').on('submit', function(event) {
		event.preventDefault();
		event.stopPropagation();
		applyFilter();
	});

	mainWidth = $('#main').width()-30;
	myColumns = $('#columns').val();
	isThumb   = $('#thumbnails').is(':checked');

	myWidth = (mainWidth-(30*myColumns))/myColumns;

	$('.graphWrapper').each(function() {
		graph_id=$(this).attr('graph_id');
		if (!(graph_id > 0)) {
			graph_id=$(this).attr('id').replace('wrapper_','');
		}

		rra_id=$(this).attr('rra_id');
		if (!(rra_id > 0)) {
			rra_id=0;
		}

		graph_height=$(this).attr('graph_height');
		graph_width=$(this).attr('graph_width');

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

	$('#realtimeoff').unbind('click').click(function() {
		stopRealtime();
	});

	$('#ds_step').unbind('change').change(function() {
		realtimeGrapher();
	});

	$('a[id$="_util"]').each(function() {
		graph_id=$(this).attr('id').replace('graph_','').replace('_util','');
		$(this).attr('href',urlPath+
			'graph.php?action=zoom&local_graph_id='+graph_id+
			'&rra_id=0&graph_start='+getTimestampFromDate($('#date1').val())+
			'&graph_end='+getTimestampFromDate($('#date2').val())).addClass('linkEditMain');

		$(this).unbind('click').click(function(event) {
			event.preventDefault();
			event.stopPropagation();
			graph_id=$(this).attr('id').replace('graph_','').replace('_util','');
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
		$(this).children().bind('contextmenu', function(event) {
			return false;
		});

		$(this).unbind('click').click(function(event) {
			graph_id=$(this).attr('id').replace('graph_','').replace('_realtime','');

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
				$(this).html("<i style='text-align:center;padding:0px;' title='"+realtimeClickOff+"' class='drillDown fa fa-circle-o-notch fa-spin'/>");
				$(this).find('i').tooltip();
				realtimeArray[graph_id] = true;
				setFilters();
				realtimeGrapher();
			}
		});
	});
}

// combobox example borrowed from jqueryui
$.widget('custom.dropcolor', {
	_create: function() {
		$('body').append('<div id="cwrap" class="ui-selectmenu-menu ui-front">');

		this.wrapper = $('<span>')
		.addClass('autodrop ui-selectmenu-button ui-widget ui-state-default ui-corner-all')
		.insertAfter(this.element);

		this.element.hide();
		this._createAutocomplete();
		this._createShowAllButton();
	},

	_createAutocomplete: function() {
		var selected = this.element.children(':selected');
		var value = selected.val() ? selected.text() : '';

		this.input = $('<input>')
		.appendTo(this.wrapper)
		.val(value)
		.attr('title', '')
		.addClass('ui-autocomplete-input ui-state-default ui-selectmenu-text')
		.css({'border':'medium none', 'background-color':'transparent', 'width':'220px', 'padding-left':'12px'})
		.tooltip({
			classes: {
				'ui-tooltip': 'ui-state-highlight'
			}
		})
		.on('click', function() {
			$(this).autocomplete('search', '');
		})
		.autocomplete({
			delay: 0,
			minLength: 0,
			source: $.proxy(this, '_source'),
			create: function() {
				$(this).data('ui-autocomplete')._renderItem = function(ul, item) {
					pie = item.label.replace(')', '').split(' (');
					if (pie[1] != undefined) {
						color = pie[1];
						return $('<li>').attr('data-value', item.value).html('<div style="background-color:#'+color+';" class="ui-icon color-icon"></div><div>'+item.label+'</div>').appendTo(ul);
					} else {
						return $('<li>').attr('data-value', item.value).append(item.label).appendTo(ul);
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

	_createShowAllButton: function() {
		var input = this.input;
		var wasOpen = false;

		$('<span>')
		.attr('tabIndex', -1)
		.tooltip()
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

			// Pass empty string as value to search for, displaying all results
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

		response(results.slice(0,15));
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

	children=section.find('table').each(function (i) {
		expandClipboardSection($(this));
	});
}

function copyToClipboard(containerId) {
	var clipboardDataId = containerId.replace('copyToClipboard','clipboardData');
	var clipboardData   = document.getElementById(clipboardDataId);

	if (clipboardData == null) {
		$('body').append('<div style="display:none;" id="clipboardMessage" title="'+clipboard+'">'+clipboardCopyFailed+'<br/><br/>'+clipboardID+': '+clipboardDataId+'</div>');

		$('#clipboardMessage').dialog({
			resizable: false,
			draggable: false,
			height: 170,
			width: 350,
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
			width: 350,
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

		var success=document.execCommand('copy');
		selection.removeAllRanges();

		var successMessage = (!success ? clipboardNotUpdated : clipboardUpdated);

		$('body').append('<div style="display:none;" id="clipboardMessage" title="'+clipboard+'">'+successMessage+'</div>');

		$('#clipboardMessage').dialog({
			resizable: false,
			draggable: false,
			height: 120,
			width: 350,
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

			selectmenu = ($('#snmp_security_level').selectmenu('instance') !== undefined);
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
	snmp_version = $('#snmp_version').val();

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
				$('#snmp_auth_protocol option[value="[None]"').prop('disabled', false);
				$('#snmp_priv_protocol option[value="[None]"').prop('disabled', false);

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
				$('#snmp_auth_protocol option[value="[None]"').prop('disabled', false);
				$('#snmp_priv_protocol option[value="[None]"').prop('disabled', false);

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

				$('#snmp_auth_protocol option[value="[None]"').prop('disabled', true);
				$('#snmp_priv_protocol option[value="[None]"').prop('disabled', false);
				checkSNMPPassphrase('auth');
			} else {
				$('#snmp_auth_protocol option[value="[None]"').prop('disabled', false);
				$('#snmp_priv_protocol option[value="[None]"').prop('disabled', false);

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

				$('#snmp_auth_protocol option[value="[None]"').prop('disabled', true);
				$('#snmp_priv_protocol option[value="[None]"').prop('disabled', true);
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
		pass = '#snmp_priv_passphrase';
		conf = '#snmp_priv_passphrase_confirm';
		span = 'priv';
	} else {
		pass = '#snmp_password';
		conf = '#snmp_password_confirm';
		span = 'auth';
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
		pass     = '#snmp_priv_passphrase';
		conf     = '#snmp_priv_passphrase_confirm';
		span     = 'priv';
		spanconf = 'privconf';
	} else {
		pass     = '#snmp_password';
		conf     = '#snmp_password_confirm';
		span     = 'auth';
		spanconf = 'authconf';
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


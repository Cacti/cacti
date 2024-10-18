/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2024 The Cacti Group                                 |
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

const MESSAGE_LEVEL_NONE = 0;
const MESSAGE_LEVEL_INFO = 1;
const MESSAGE_LEVEL_WARN = 2;
const MESSAGE_LEVEL_ERROR = 3;
const MESSAGE_LEVEL_CSRF = 4;
const MESSAGE_LEVEL_MIXED = 5;

var theme;
var myRefresh;
var userMenuTimer;
var userMenuOpenTimer = null;
var graphMenuTimer;
var graphMenuElement = 0;
var callbackTimer = {};
var callbackClickTimer = {}
var callbackOpen = {};
var pulsating = true;
var pageLoaded = false;
var shiftPressed = false;
var sessionMessage = null;
var sessionMessageOpen = null;
var sessionMessageTimer = null;
var currentNavState = null;
var currentNavId = null;
var myTitle;
var myHref;
var lastPage = null;
var statePushed = false;
var popFired = false;
var hostInfoHeight = 0;
var marginLeftTree = null;
var marginLeftConsole = null;
var minTreeWidth = null;
var maxTreeWidth = null;
var pageName;
var columnsHidden = 0;
var lastColumnsHidden = {};
var lastWidth = {};
var resizeDelta = 100;
var resizeTime = 0;
var resizeTimeout = false;
var formArray = {};
var formRules = {};
var pageWidth = null;
var isHover = false;
var hoverTimer = false;
var previousMainWidth = null;
var previousColumns = null;
var faIcons = {
	open: {
		icon: '<i class="fas fa-caret-down" aria-hidden="true"></i>'
	},
	close: {
		icon: '<i class="fas fa-times-circle" aria-hidden="true"></i>'
	},
	checkAll: {
		icon: '<i class="fas fa-check" aria-hidden="true"></i>'
	},
	uncheckAll: {
		icon: '<i class="fas fa-ban" aria-hidden="true"></i>'
	},
	flipAll: {
		icon: '<i class="fas fa-undo" aria-hidden="true"></i>'
	},
	collapseAll: {
		icon: '<i class="fas fa-double-angle-down" aria-hidden="true"></i>'
	},
	expandAll: {
		icon: '<i class="fas fa-double-angle-right" aria-hidden="true"></i>'
	},
	collapse: {
		icon: '<i class="fas fa-chevron-down" aria-hidden="true"></i>'
	},
	expand: {
		icon: '<i class="fas fa-chevron-right" aria-hidden="true"></i>'
	}
};

window.paceOptions = {
	ajax: true,
	document: true,
	elements: false,
	minTime: 400,
	startOnPageLoad: false,
	restartOnPushState: false,
	restartOnRequestAfter: 120,
	eventLag: false,
};

window.onbeforeunload = renderLoading;

function renderLoading() {
	Pace.stop();
	Pace.bar.render();
}

var isMobile = {
	Android: function () {
		return navigator.userAgent.match(/Android/i);
	},
	BlackBerry: function () {
		return navigator.userAgent.match(/BlackBerry/i);
	},
	iOS: function () {
		return navigator.userAgent.match(/iPhone|iPad|iPod/i);
	},
	Opera: function () {
		return navigator.userAgent.match(/Opera Mini/i);
	},
	Windows: function () {
		return navigator.userAgent.match(/IEMobile/i);
	},
	any: function () {
		return (isMobile.Android() || isMobile.BlackBerry() || isMobile.iOS() || isMobile.Opera() || isMobile.Windows());
	}
};

/* simple ajax request queueing */
jQuery.ajaxQ = (function () {
	var id = 0, Q = {};

	jQuery(document).ajaxSend(function (e, jqx) {
		jqx._id = ++id;
		Q[jqx._id] = jqx;
	});

	jQuery(document).ajaxComplete(function (e, jqx) {
		delete Q[jqx._id];
	});

	return {
		abortAll: function () {
			var r = [];
			jQuery.each(Q, function (i, jqx) {
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

	// Don't remove trailing slash for now //
	/*
	if (lastChar === '/' || lastChar === '\\\\') {
		b = b.slice(0, -1);
	}
	*/

	if (b.indexOf('?') > 0) {
		var questionPosition = b.indexOf('?');
		b = b.slice(0, questionPosition);
	}

	b = b.replace(/^.*[\\/\\\\]/g, '');

	if (suffix !== undefined && b.substr(b.length - suffix.length) == suffix) {
		b = b.substr(0, b.length - suffix.length);
	}

	if (b.length == 0) {
		b = "index.php";
	}

	return b;
}

/** getTimestampFromDate - Simple function to convert a MySQL Date
 * to a timestamp */
function getTimestampFromDate(dateStamp) {
	if (typeof dateStamp != 'undefined') {
		var dateParts = dateStamp.split(' ');
		var timeParts = dateParts[1].split(':');

		dateParts = dateParts[0].split('-');
		var date = new Date(dateParts[0], parseInt(dateParts[1], 10) - 1, dateParts[2], timeParts[0], timeParts[1]);

		return date.getTime() / 1000;
	}
	return '';
}

/** base64_encode - Simple function to base64 encode a utf-8 string */
function base64_encode(string) {
	return btoa(unescape(encodeURIComponent(string)));
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
$.fn.delayKeyup = function (callback, ms) {
	var timer = 0;
	if (typeof ms === 'undefined' || ms < 500) {
		ms = keyup_delay;
	}

	$(this).keyup(function () {
		clearTimeout(timer);
		timer = setTimeout(callback, ms);
	});
	return $(this);
};

/** bindFirst - Function ensures that the event is found at the top
 * of the event stack. */
$.fn.bindFirst = function (which, handler) {
	var $el = $(this);
	$el.off(which, handler);
	$el.on(which, handler);

	var events = $._data($el[0]).events;
	var registered = events[which];
	registered.unshift(registered.pop());

	events[which] = registered;
};

/** replaceOptions - function replaces the options in a select dropdown */
$.fn.replaceOptions = function (options, selected) {
	var self, $option;

	this.empty();
	self = this;

	$.each(options, function (index, option) {
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

/**
 * Enable or disable options that match the values passed
 *
 * @param {array} values            The values to be updated
 * @param {bool} optionEnabled      Whether to enable or disable
 * @param {function} valueCheckFunc The function be to called if anything changes
 * @returns element
 */
$.fn.enableDisableOptions = function (values, optionEnabled, valueCheckFunc) {
	var hasChanged = false;

	if ($(this).length > 0) {
		var self = $(this)[0];

		$.each(self.options, function (index, option) {
			var optionValue = $(this).val();
			var optionDisabled = $(this).prop('disabled');
			var optionIncluded = values.includes(optionValue);

			/*
			console.log('option ' + index + ' Name     = ' + $(this).name);
			console.log('option ' + index + ' Value    = ' + optionValue);
			console.log();
			*/

			var optionFinally = '---';
			if (optionIncluded && optionDisabled == optionEnabled) {
				hasChanged = true;
				$(this).prop('disabled', !optionEnabled);
				optionFinally = ($(this).prop('disabled') ? 'Yes':'No ');
			}

			/*
			console.log('## option ' + index + ' (' + optionValue + ')' +
				', Enabled = ' + (optionEnabled ? 'Yes' : 'No ') +
				', Included = ' + (optionIncluded ? 'Yes' : 'No ') +
				', Disabled = ' + (optionDisabled ? 'Yes' : 'No ') +
				', Finally = ' + (optionFinally)
			);
			*/
		});
	}

	/*
	console.log('hasChanged = ' + hasChanged);
	console.log();
	*/

	if (hasChanged) {
		if (typeof valueCheckFunc == 'function') {
			valueCheckFunc();
		}

		var selectMenu = $(this).selectmenu('instance');
		if (typeof selectMenu != 'undefined') {
			$(this).selectmenu('refresh');
		}

		if ($(this).hasClass("select2-hidden-accessible")) {
			$(this).trigger('change.select2');
		}
	}

	return $(this);
};

/**
 * Enable options that match the values passed
 *
 * @param {array} values            The values to be updated
 * @param {function} valueCheckFunc The function be to called if anything changes
 * @returns
 */

$.fn.disableOptions = function (values, valueCheckFunc) {
   return $(this).enableDisableOptions(values, false, valueCheckFunc);
}

/**
 * Disable options that match the values passed
 *
 * @param {array} values            The values to be updated
 * @param {function} valueCheckFunc The function be to called if anything changes
 * @returns
 */

$.fn.enableOptions = function (values, valueCheckFunc) {
   return $(this).enableDisableOptions(values, true, valueCheckFunc);
}

/** textWidth - This function will return the natural width of a string
 *  without any wrapping. */
$.fn.textWidth = function (text) {
	var org = $(this);
	var html = $('<span style="display:none;white-space:nowrap;position:absolute;width:auto;left:-9999px">' + (text || org.text()) + '</span>');
	if (!text) {
		html.css('font-family', org.css('font-family'));
		html.css('font-weight', org.css('font-weight'));
		html.css('font-size', org.css('font-size'));
		html.css('padding', org.css('padding'));
		html.css('margin', org.css('margin'));
	}
	$('body').append(html);
	var width = html.width();
	html.remove();
	return width;
};

/** textBoxWidth - This function will return the natural width of a string
 *  without any wrapping. */
$.fn.textBoxWidth = function () {
	var org = $(this);
	if (org.val()) {
		var text = encodeURIComponent(org.val());
	} else {
		var text = encodeURIComponent(org.text());
	}

	var html = $('<span style="display:none;white-space:nowrap;position:absolute;width:auto;left:-9999px">' + text + '</span>');
	html.css('font-family', org.css('font-family'));
	html.css('font-weight', org.css('font-weight'));
	html.css('font-size', org.css('font-size'));
	html.css('padding', org.css('padding'));
	html.css('margin', org.css('margin'));
	$('body').append(html);
	var width = html.width();
	html.remove();
	return width;
};

/** classes - This function will return an array of all
 *  classes of an element */
$.fn.classes = function (callback) {
	var classes = [];
	$.each(this, function (i, v) {
		var splitClassName = v.className.split(/\s+/);
		for (var j in splitClassName) {
			var className = splitClassName[j];
			if (-1 === classes.indexOf(className)) {
				classes.push(className);
			}
		}
	});
	if ('function' == typeof callback) {
		for (var i in classes) {
			callback(classes[i]);
		}
	}
	return classes;
};

/** These three functions will set the cursor into
 *  a textbox or textarea and optionally select characters */
$.fn.setCursorPosition = function (position) {
	if (this.length == 0) return this;
	return this.setSelection(position, position);
};

$.fn.selectRange = function (start, end) {
	if (!end) end = start;
	return this.each(function () {
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

$.fn.focusEnd = function () {
	this.setCursorPosition($(this).val().length);
	return this;
};

$.fn.getObjectDetails = function () {
	var coords = {};

	coords.width = $(this).outerWidth();
	coords.height = $(this).outerHeight();
	coords.left = 0;
	coords.top = 0;

	$(this).parentsUntil('body').each(function () {
		coords.left += $(this).position().left;
		coords.top += $(this).position().top;
	});

	return coords;
};

$.fn.serializeForm = function () {
	var arrayData, objectData;
	arrayData = this.serializeArray();
	formID = $(this).attr('id');
	arrayData = arrayData.concat(
		$('#' + formID + ' input[type=checkbox]:not(:checked)').map(function () {
			return { "name": this.name, "value": $(this).is(':checked') ? 'on' : '' }
		}).get());

	objectData = {};

	$.each(arrayData, function () {
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

$.fn.serializeObject = function () {
	var arrayData, objectData, formID;
	arrayData = this.serializeArray();
	formID = $(this).attr('id');

	objectData = {};

	$.each(arrayData, function () {
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

// Borrowed from mustache.js
function escapeString(string) {
	var entityMap = {
		'<': '&lt;',
		'>': '&gt;',
		'"': '&quot;',
		"'": '&#39;',
		'`': '&#x60;'
	};

	return String(string).replace(/[<>"'`]/g, function fromEntityMap(s) {
		return entityMap[s];
	});
}

// Plugin to apply numeric format for tablesorter
$.tablesorter.addParser({
	id: 'numberFormat',
	is: function (s) {
		return /^[0-9]?[0-9,\.]*$/.test(s);
	},
	format: function (s) {
		return $.tablesorter.formatFloat(s.replace(/,/g, ''));
	},
	type: 'numeric'
});

/** Mini jquery plugin to determine if an element has a scrollbar present */
(function ($) {
	$.fn.hasScrollBar = function () {
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

// Helper function to get text dimensions
(function ($) {
	$.textMetrics = function (el) {
		var h = 0,
			w = 0;

		var div = document.createElement('div');
		document.body.appendChild(div);
		$(div).css({
			position: 'absolute',
			left: -1000,
			top: -1000,
			display: 'none'
		});

		$(div).html($(el).html());

		var styles = ['font-size', 'font-style', 'font-weight', 'font-family', 'line-height', 'text-transform', 'letter-spacing'];

		$(styles).each(function () {
			var s = this.toString();
			$(div).css(s, $(el).css(s));
		});

		var h = $(div).outerHeight();
		var w = $(div).outerWidth();

		$(div).remove();

		var ret = {
			height: h,
			width: w
		};

		return ret;
	}
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
	$('input[type="radio"]').off('click').on('click', function () {
		if ($(this).is(':checked')) {
			$(this).attr('aria-checked', 'true');
		} else {
			$(this).attr('aria-checked', 'false');
		}
	});

	// Apply disabled/enabled status first for Graph Templates
	$('tr[id^="gt_line"]').each(function (data) {
		var id = $(this).attr('id');
		var search = id.substr(7);
		if ($.inArray(search, gt_created_graphs) >= 0) {
			$(this).addClass('disabled_row');
			$(this).find(':checkbox').prop('disabled', true);
		}
	});

	// Create Actions for Rows
	$('tr[id^="gt_line"].selectable:not(.disabled_row)').off('click').on('click', function (event) {
		selectUpdateRow(event, $(this));
	});

	// Create Actions for Rows
	$('tr[id^="line"].selectable').filter(':not(.disabled_row)').off('click').on('click', function (event) {
		selectUpdateRow(event, $(this));
	});
}

function disableSelection() {
	$('tr.selectable').css('-webkit-user-select', 'none');
	$('tr.selectable').css('-moz-user-select', 'none');
	$('tr.selectable').css('-ms-user-select', 'none');
	$('tr.selectable').css('-o-user-select', 'none');
	$('tr.selectable').css('user-select', 'none');
}

function enableSelection() {
	$('tr.selectable').css('-webkit-user-select', '');
	$('tr.selectable').css('-moz-user-select', '');
	$('tr.selectable').css('-ms-user-select', '');
	$('tr.selectable').css('-o-user-select', '');
	$('tr.selectable').css('user-select', '');
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
	$('tr[id^="dqline' + snmp_query_id + '_"]').addClass('selectable').removeClass('disabled_row').find(':checkbox').prop('disabled', false);

	var snmp_query_graph_id = $('#sgg_' + snmp_query_id).val();
	var removeSelectAll = false;

	// Check if select all is clicked
	var allChecked = $('#all_' + snmp_query_id).is(':checked');

	// Next for Data Queries
	$('tr[id^="dqline' + snmp_query_id + '_"]').each(function (data) {
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
		$('#all_' + snmp_query_id).prop('checked', false);
	}

	$('tr[id^="dqline' + snmp_query_id + '_"]').not('.disabled_row').off('click').on('click', function (event) {
		selectUpdateRow(event, $(this));
	});
}

/** selectAll - This function will select all non-disabled rows
 *  @arg attrib - The Graph Type either graph template, or data query */
function selectAll(attrib, checked) {
	if (attrib == 'chk') {
		if (checked == true) {
			$('tr[id^="line"]:not(.disabled_row)').each(function (data) {
				$(this).addClass('selected');
				$(this).find(':checkbox').prop('checked', true).attr('aria-checked', 'true').attr('data-prev-check', 'true');
			});
			disableSelection();
		} else {
			$('tr[id^="line"]:not(.disabled_row)').each(function (data) {
				$(this).removeClass('selected');
				$(this).find(':checkbox').prop('checked', false).removeAttr('aria-checked').removeAttr('data-prev-check');
			});
		}
	} else if (attrib == 'sg') {
		if (checked == true) {
			$('tr[id^="gt_line"]:not(.disabled_row)').each(function (data) {
				$(this).addClass('selected');
				$(this).find(':checkbox').prop('checked', true).attr('aria-checked', 'true').attr('data-prev-check', 'true');
			});
			disableSelection();
		} else {
			$('tr[id^="gt_line"]:not(.disabled_row)').each(function (data) {
				$(this).removeClass('selected');
				$(this).find(':checkbox').prop('checked', false).removeAttr('aria-checked').removeAttr('data-prev-check');
			});
		}
	} else if (attrib.startsWith('sg_', 0)) {
		var attribSplit = attrib.split('_');
		var dq = attribSplit[1];

		if (checked == true) {
			$('tr[id^="dqline' + dq + '\_"]:not(.disabled_row)').each(function (data) {
				$(this).addClass('selected');
				$(this).find(':checkbox').prop('checked', true).attr('aria-checked', 'true').attr('data-prev-check', 'true');
			});
			disableSelection();
		} else {
			$('tr[id^="dqline' + dq + '\_"]:not(.disabled_row)').each(function (data) {
				$(this).removeClass('selected');
				$(this).find(':checkbox').prop('checked', false).removeAttr('aria-checked').removeAttr('data-prev-check');
			});
		}
	} else {
		if (checked == true) {
			$('tr[id^="line_' + attrib + '"]:not(.disabled_row)').each(function (data) {
				$(this).addClass('selected');
				$(this).find(':checkbox').prop('checked', true).attr('aria-checked', 'true').attr('data-prev-check', 'true');
			});
			disableSelection();
		} else {
			$('tr[id^="line_' + attrib + '"]:not(.disabled_row)').each(function (data) {
				$(this).removeClass('selected');
				$(this).find(':checkbox').prop('checked', false).removeAttr('aria-checked').removeAttr('data-prev-check');
			});
		}
	}
}

/* graph filtering */
function applyTimespanFilterChange() {
	var href;

	href = '?predefined_timespan=' + $('#predefined_timespan').val();
	href += '&predefined_timeshift=' + $('#predefined_timeshift').val();

	loadUrl({ url: href });

}

/** cactiReturnTo - This function simply returns to the previous page
 *  @args href - the previous page */
function cactiReturnTo(href) {
	if (typeof href !== 'string' || href.trim() == '') {
		href = document.referrer;
	}

	loadUrl({ url: href, force: true });
}

/** applySkin - This function re-asserts all javascript behavior to a page
 *  that can't be set using a live attribute 'on()' */
function applySkin() {
	pageName = basename($(location).attr('pathname'));

	$('#messageContainer').remove();

	/* Replace icons */
	$('.fa-arrow-down').addClass('fa-chevron-down').removeClass('fa-arrow-down');
	$('.fa-arrow-up').addClass('fa-chevron-up').removeClass('fa-arrow-up');
	$('.fa-remove').addClass('fa-trash-o').removeClass('fa-remove');

	if (!theme) {
		theme = 'midwinter';

		// debounce submits
		$('form').submit(function () {
			$('input[type="submit"], button[type="submit"]').not('.import, .export').prop('disabled', true);
		});
	} else {
		$('input[type="submit"], input[type="button"], button').button();

		// Handle re-index changes
		$('fieldset.reindex_methods').buttonset();

		// debounce submits
		$('form').submit(function () {
			$('input[type="submit"], button[type="submit"]').not('.import, .export').button('disable');
		});
	}

	setSelectMenus();

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

	makeCallbacks();

	$('.helpPage').off('click').on('click', function (event) {
		event.stopPropagation();
		getCactiHelp($(this).attr('data-page'));
	});

	if (typeof themeReady == 'function') {
		themeReady();
	}

	makeFiltersResponsive();

	setupResponsiveMenuAndTabs();

	setupButtonStyle();

	// Debug message actions
	$('table.debug tr:nth-child(1)').off('click').on('click', function () {
		if ($(this).parent().find('table').is(':visible')) {
			$(this).parent().find('table').slideUp('fast');
		} else {
			$(this).parent().find('table').slideDown('fast');
		}
	});

	$('.cactiTableCopy').off('click').on('click', function (event) {
		event.preventDefault();
		event.stopPropagation();
		var containerId = $(this).attr('id');
		copyToClipboard(containerId);
	});

	$('i, a, th, img, input, label, select, button, .drillDown, .checkboxSlider')
		.tooltip({
			close: function () {
				$("[role=log].ui-helper-hidden-accessible").remove();
			}
		})
		.on('focus', function () {
			if ($(this).tooltip('instance')) {
				$(this).tooltip('close');
			}
		})
		.on('click', function () {
			if ($(this).tooltip('instance')) {
				$(this).tooltip('close');
			}
		});

	$(document).tooltip({
		close: function () {
			$("[role=log].ui-helper-hidden-accessible").remove();
		},
		items: 'div.cactiTooltipHint, span.cactiTooltipHint, .checkboxSlider',
		content: function () {
			var element = $(this);

			if (element.is('div')) {
				var text = DOMPurify.sanitize($(this).find('span').html());
			} else if (element.is('span') || element.is('a')) {
				var text = DOMPurify.sanitize($(this).prop('title'));
			}
			return text;
		}
	});

	$(document).on('keyup keydown', function (event) {
		shiftPressed = event.shiftKey;
	});

	$('#main').css('display', 'table');

	var showPage = $('#main').map(function (i, el) {
		var dfd = $.Deferred();
		$(el).show(function () {
			dfd.resolve();
		});

		return dfd;
	});

	$('#password').delayKeyup(function () {
		var url = window.location.href.split('?')[0] + '?action=checkpass';
		checkPassword(url);
	});

	$('#password_confirm').delayKeyup(function () {
		checkPasswordConfirm();
	});

	keepWindowSize();

	sessionMessageDisplay();
	sessionNoticesDisplay();

	renderLanguages();
}

function finalizeAuthProfile(options, data) {
	if (name == 'selected_theme' || name == 'user_language') {
		document.location = 'auth_profile.php?action=edit';
	}
}

function checkPassword(url) {
	var options = {
		url: url,
		handle: false,
		funcEnd: 'checkPasswordFinalize'
	}

	if ($('#password').val().length == 0) {
		$('#pass').remove();
		$('#passconfirm').remove();
	} else if ($('#password').val().length < passwordMinChars) {
		checkPasswordFinalize(options, passwordTooShort);
	} else {
		var data = {
			password: $('#password').val(),
			password_confim: $('#password_confirm').val(),
			__csrf_magic: csrfMagicToken
		}

		postUrl(options, data);
	}
}

function checkPasswordFinalize(options, data) {
	var className = 'fa-times badpassword';
	if (data == 'ok') {
		className = 'fa-check goodpassword';
		data = passwordValid;
	}

	$('#pass').remove();
	$('#password').after('<div id="pass" class="fa ' + className + '" title="' + data + '"></div>');
	$('#password').tooltip();
	checkPasswordConfirm();
}

function checkPasswordConfirm() {
	if ($('#password_confirm').val().length > 0) {
		if ($('#password').val() != $('#password_confirm').val()) {
			$('#passconfirm').remove();
			$('#password_confirm').after('<div id="passconfirm" class="badpassword fa fa-times" title="' + passwordNotMatch + '"></div>');
		} else {
			$('#passconfirm').remove();
			$('#password_confirm').after('<div id="passconfirm" class="goodpassword fa fa-check" title="' + passwordMatch + '"></div>');
		}
	} else {
		$('#passconfirm').remove();
	}
}

function renderLanguages() {
	if ($('select#user_language').selectmenu('instance') !== undefined) {
		$('select#user_language').selectmenu('destroy');
	}
	$('select#user_language').languageselect({
		width: '220',
		change: function () {
			var name = $(this).attr('id');
			var value = $(this).val();
			var page = basename(location.pathname);
			if (page == 'auth_profile.php') {
				postUrl({
					url: 'auth_profile.php?tab=' + currentTab + '&action=update_data',
					funcEnd: 'finalizeAuthProfile'
				}, {
					__csrf_magic: csrfMagicToken,
					name: name,
					value: value,
				});
			}
		}
	}).languageselect('menuWidget').addClass('ui-menu-icons customicons');

	if ($('select#i18n_default_language').selectmenu('instance') !== undefined) {
		$('select#i18n_default_language').selectmenu('destroy');
	}

	$('select#i18n_default_language').languageselect({
		width: '220'
	}).languageselect('menuWidget').addClass('ui-menu-icons customicons');

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

function raiseMessage(title, header, detail, level) {
	var origErrorReasonTitle    = errorReasonTitle;
	var origErrorOnPage         = errorOnPage;
	var origMixedReasonTitle    = mixedReasonTitle;
	var origMixedOnPage         = mixedOnPage;
	var origSessionMessageTitle = sessionMessageTitle;
	var origSessionMessageSave  = sessionMessageSave;
	var origSessionMessage      = sessionMessage;

	sessionMessage.message = detail;
	sessionMessage.level   = level;

	if (level == MESSAGE_LEVEL_ERROR) {
		errorReasonTitle  = title;
		errorOnPage       = header;
	} else if (level == MESSAGE_LEVEL_MIXED) {
		mixedOnPage       = header;
		mixedReasonTitle  = title;
	} else {
		sessionMessageTitle = title;
		sessionMessageSave  = header;
	}

	sessionMessageDisplay();

	if (level == MESSAGE_LEVEL_ERROR) {
		errorReasonTitle  = origErrorReasonTitle;
		errorOnPage       = origErrorOnPage;
	} else if (level == MESSAGE_LEVEL_MIXED) {
		mixedOnPage      = origMixedOnPage;
		mixedReasonTitle = origMixedReasonTitle;
	} else {
		sessionMessageTitle = origSessionMessageTitle;
		sessionMessageSave  = origSessionMessageSave;
	}

	sessionMessage = origSessionMessage;
}

function sessionNoticesDisplay() {
	if (typeof sessionNotices !== 'undefined' && Array.isArray(sessionNotices) && sessionNotices.length > 0) {
		var currentNotices = sessionNotices;
		sessionNotices = {};

		currentNotices.forEach(function (notice, index) {
			var level   = MESSAGE_LEVEL_NONE;
			var message = 'No message';
			var title   = '';
			var id      = '';

			if (typeof notice.message !== 'undefined') {
				message = notice.message;
			}

			if (typeof notice.title !== 'undefined') {
				titLe = notice.tite;
			}

			if (typeof notice.level !== 'undefined') {
				level = notice.level;
			}

			if (typeof notice.id !== 'undefined') {
				id = '-' + notice.id;
			}

			switch (notice.level) {
				case MESSAGE_LEVEL_CSRF:
					location.reload();

					break;
				case MESSAGE_LEVEL_INFO:
					PopupNotice(message, title);

					break;
				case MESSAGE_LEVEL_MIXED:
					PopupUnknown(message, title);

					break;
				case MESSAGE_LEVEL_ERROR:
					PopupError(message, title);

					break;
				case MESSAGE_LEVEL_WARN:
					PopupWarning(message, title);

					break;

				default:
					console.log('');
					console.log('noticeDisplay(' + index + id + ') - Failed to find correct popup function for level ' + level + ': ' + message);
					console.log('');
			}
		});
	}
}

function sessionMessageDisplay() {
	var title = '';
	var header = '';

	if (typeof sessionMessageTimer == 'function' || sessionMessageTimer !== null) {
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
					click: function () {
						$(this).dialog('close');
					}
				}
			};

			sessionMessageOpen = {};
		} else if (sessionMessage.level == MESSAGE_LEVEL_MIXED) {
			title = mixedReasonTitle;
			header = mixedOnPage;
			var sessionMessageButtons = {
				'Ok': {
					text: sessionMessageOk,
					id: 'btnSessionMessageOk',
					click: function () {
						$(this).dialog('close');
					}
				}
			};

			sessionMessageOpen = {};
		} else if (sessionMessage.level == MESSAGE_LEVEL_CSRF) {
			location.reload();
			return false;
		} else {
			title = sessionMessageTitle;
			header = sessionMessageSave;
			var sessionMessageButtons = {
				'Pause': {
					text: sessionMessagePause,
					id: 'btnSessionMessagePause',
					click: function () {
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
					click: function () {
						$(this).dialog('close');
						$('#messageContainer').remove();
						clearInterval(sessionMessageTimer);
					}
				}
			};

			sessionMessageOpen = function () {
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

	sessionMessageTimer = setInterval(function () {
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

	$('#' + child).find('tr').each(function () {
		tdNum = 0;
		$(this).find('td').each(function () {
			$(this).attr('id', 'fn' + filterNum + 'tr' + trNum + 'td' + tdNum);
			tdNum++;
		});
		trNum++;
	});
}

function finalizeAuthProfileData(options, data) {
	if (hScroll) {
		$('#main, .cactiConsoleContentArea').css({ 'overflow-x': 'visible' });
		$('#overflow').removeClass('fa-expand').addClass('fa-compress');

		resetTables();
	} else {
		$('#main, .cactiConsoleContentArea').css({ 'overflow-x': 'hidden' });
		$('#overflow').removeClass('fa-compress').addClass('fa-expand');

		tuneTables();
	}
}

function makeFiltersResponsive() {
	storage = Storages.localStorage;

	filterNum = 0;

	if ($('div.cactiTableButton').closest('.cactiTable').not('#dqdebug').find('.filterTable').length) {
		$('div.cactiTableButton').closest('.cactiTable').not('#dqdebug').each(function () {
			if ($(this).find('.filterTable').length) {
				filterHeader = $(this).closest('.cactiTable');
				id = filterHeader.attr('id');
				child = id + '_child';
				filterContents = $('#' + child);

				filterHeader.find('.cactiTableTitle, .cactiTableButton').css('cursor', 'pointer');

				if (pageHasHidableColumnsAndProfile()) {
					if (filterHeader.find('.cactiSwitchConstraints').length == 0) {
						if (hScroll) {
							$('#main, .cactiConsoleContentArea').css({ 'overflow-x': 'visible' });
							filterHeader.find('div.cactiTableButton').append('<span class="cactiSwitchConstraintWrapper"><a title="' + tableConstraints + '" class="linkOverDark cactiSwitchConstraints" href="#"><i id="overflow" class="fa fa-compress"></i></a></span>');
						} else {
							$('#main, .cactiConsoleContentArea').css({ 'overflow-x': 'hidden' });
							filterHeader.find('div.cactiTableButton').append('<span class="cactiSwitchConstraintWrapper"><a title="' + tableConstraints + '" class="linkOverDark cactiSwitchConstraints" href="#"><i id="overflow" class="fa fa-expand"></i></a></span>');
						}

						$('.cactiSwitchConstraints').off('click').on('click', function (event) {
							event.preventDefault();
							event.stopPropagation();

							hScroll = !hScroll;

							postUrl({
								url: urlPath + 'auth_profile.php?tab=general&action=update_data',
								handle: false,
								noState: true,
								funcEnd: 'finalizeAuthProfileData'
							}, {
								__csrf_magic: csrfMagicToken,
								name: 'enable_hscroll',
								value: hScroll ? 'on' : ''
							});
						});
					}
				}

				if (filterHeader.find('div.cactiTableButton').find('.cactiFilterAdd').length) {
					markFilterTDs(child, filterNum);

					$('.cactiFilterAdd').tooltip();
				}

				if (filterContents.find('#export').length) {
					title = $('#export').attr('value');
					filterHeader.find('div.cactiTableButton').append('<span title="' + title + '" style="display:none;" class="cactiFilterExport"><i class="fa fa-chevron-down"></i></span>');

					$('.cactiFilterExport').off('click').on('click', function (event) {
						event.stopPropagation();
						$('#export').trigger('click');
					}).tooltip();
				}

				if (filterContents.find('#import').length) {
					title = $('#import').attr('value');
					filterHeader.find('div.cactiTableButton').append('<span title="' + title + '" style="display:none;" class="cactiFilterImport"><i class="fa fa-chevron-up"></i></span>');

					$('.cactiFilterImport').off('click').on('click', function (event) {
						event.stopPropagation();
						$('#import').trigger('click');
					}).tooltip();
				}

				if (filterContents.find('#clear').length) {
					if (filterHeader.find('.cactiFilterClear').length == 0) {
						filterHeader.find('div.cactiTableButton').append('<span title="' + clearFilterTitle + '" style="display:none;" class="cactiFilterClear"><i class="fa fa-trash-alt"></i></span>');
					}

					$('.cactiFilterClear').off('click').on('click', function (event) {
						event.stopPropagation();
						$('#clear').trigger('click');
					}).tooltip();
				}

				toggleFilterAndIcon(id, child, true);

				filterHeader.find('.cactiTableTitle, .cactiTableButton').off('click').on('click', function () {
					id = $(this).closest('.cactiTable').attr('id');
					child = id + '_child';
					toggleFilterAndIcon(id, child, false);
				});

				if (storage.isSet('filterVisibility')) {
					state = storage.get('filterVisibility');
				} else {
					state = 'visible';
				}

				if (state == 'hidden') {
					if (filterHeader.find('.cactiFilterState').length == 0) {
						filterHeader.find('div.cactiTableButton').append('<span class="cactiFilterState"><i class="fa fa-angle-double-down"></i></span>');
					}
				} else {
					if (filterHeader.find('.cactiFilterState').length == 0) {
						filterHeader.find('div.cactiTableButton').append('<span class="cactiFilterState"><i class="fa fa-angle-double-up"></i></span>');
					}
				}

				if (typeof showHideFilter != 'undefined') {
					$('.cactiFilterState').attr('title', showHideFilter).tooltip();
				}

				filterNum++;
			}
		});
	} else if ($('#dqdebug').length) {
		$('#dqdebug').find('div.cactiTableButton').each(function () {
			if ($(this).find('a').length) {
				anchors = $('div.cactiTableButton').find('a');
				anchors.each(function () {
					$(this).attr('title', $(this).text());
				});
				anchors.not('.cactiTableCopy').addClass('fa fa-trash-alt');
				anchors.filter('.cactiTableCopy').addClass('fa fa-copy');
				anchors.tooltip().text('');
			}
		});
	}

	if ($('#form_graph_view').length) {
		$('#form_graph_view').filter('input, select').not('#date1, #date2').click(function () {
			closeDateFilters();
		});
	}
}

function toggleFilterAndIcon(id, child, initial) {
	storage = Storages.localStorage;

	if (storage.isSet('filterVisibility')) {
		state = storage.get('filterVisibility');
	} else {
		state = 'visible';
	}

	if (initial) {
		if (state == 'hidden') {
			$('#' + child).hide();
			$('#' + id).find('.cactiFilterClear, .cactiFilterImport, .cactiFilterExport').show();
		}
	} else if ($('#' + child).is(':visible')) {
		$('#' + child).hide();
		$('#' + id).find('.cactiFilterClear, .cactiFilterImport, .cactiFilterExport').show();
		$('.cactiFilterState').find('i').removeClass('fa-angle-double-up').addClass('fa-angle-double-down');
		storage.set('filterVisibility', 'hidden');
	} else {
		$('#' + child).show();
		$('#' + id).find('.cactiFilterClear, .cactiFilterImport, .cactiFilterExport').hide();
		$('.cactiFilterState').find('i').removeClass('fa-angle-double-down').addClass('fa-angle-double-up');
		storage.set('filterVisibility', 'visible');
	}

	$(window).trigger('resize');
}

function setGraphTabs() {
	page = window.location.href;

	if (page.indexOf('graph_view.php') >= 0) {
		$('.lefttab').removeClass('selected');
		$('#tab-graphs').addClass('selected');

		if (page.indexOf('action=tree') > 0) {
			$('#preview, #listview, #treeview').removeClass('selected').prop('aria-selected', false);
			$('#treeview').addClass('selected').prop('aria-selected', true);
		} else if (page.indexOf('action=list') > 0) {
			$('#preview, #listview, #treeview').removeClass('selected').prop('aria-selected', false);
			$('#listview').addClass('selected').prop('aria-selected', true);
		} else if (page.indexOf('action=preview') > 0) {
			$('#preview, #listview, #treeview').removeClass('selected').prop('aria-selected', false);
			$('#preview').addClass('selected').prop('aria-selected', true);
		}
	}
}

function setupResponsiveMenuAndTabs() {
	$('.maintabs a.lefttab, .dropdownMenu a, .menuoptions a, #gtabs a.righttab').not('[href^="http"], [href^="https"], [href^="#"], [target="_blank"]').off('click').on('click', function (event) {
		if (!shouldCaptureClick(event)) {
			return;
		}

		page = basename($(this).attr('href'));

		if (page == 'logout.php' || page == 'auth_changepassword.php') {
			return;
		} else if (page == 'index.php' && $(this).attr('href').indexOf('login') > -1) {
			return;
		} else {
			event.preventDefault();
		}

		if ((userMenuNavigationExists(page) || page == 'graph_view.php') && ($(this).hasClass('selected') || (pageName == page && pageName != 'graph_view.php') && pageName != 'link.php')) {
			handleUserMenu(true);
		} else {
			var id = $(this).attr('id');
			var href = $(this).attr('href');

			loadTopTab(href, id, false);
		}
	});

	$(window).on('orientationchange, fullscreenchange', function () {
		responsiveUI('force');
	});
}

function responsiveUI(event) {
	var tree = false;

	if (event != 'force') {
		if (new Date() - resizeTime < resizeDelta) {
			var myEvent = event;
			setTimeout(function () {
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

	handleUserMenu(false);

	var mainWidth = getMainWidth();

	/* change textbox and textarea widths */
	$('input[type="text"], textarea').each(function () {
		if ($(this).attr('type') == 'text') {
			var offset = 20;
		} else {
			var offset = 5;
		}

		if (mainWidth != 100) {
			if ($(this).width() > mainWidth) {
				$(this).css('max-width', (mainWidth - offset) + 'px');
			} else {
				$(this).css('max-width', '');
			}

			if ($(this).width() > mainWidth) {
				$(this).css('max-width', (mainWidth - offset) + 'px');
			}

			if ($(this).parents('.formColumnRight').is(":visible") && $(this).width() > $(this).parents('.formColumnRight').prop('clientWidth')) {
				$(this).css('max-width', ($(this).parents('.formColumnRight').prop('clientWidth')) + 'px');
			} else {
				$(this).css('max-width', '');
			}
		}
	});

	$('.filterTable').each(function () {
		tuneFilter($(this), mainWidth);
	});

	tuneTables();
}

function getMainWidth() {
	// Subtract 15px for the scroll bar
	if ($('#navigation').length && $('#navigation').is(':visible')) {
		var mainWidth = $('body').outerWidth() - $('#navigation').width() - 15;
	} else {
		var mainWidth = $('body').outerWidth() - 15;
	}

	return mainWidth;
}

function getCactiHelp(cactiPage) {
	var url = urlPath + 'help.php?page=' + cactiPage;

	$.getJSON(url, function (data) {
		if (data.status == 'Success') {
			window.open(data.location, '_blank');
		}
	});
}

function responsiveResizeGraphs(initialize) {
	var mainWidth = getMainWidth() - 30;
	var myColumns = $('#columns').val();
	var isThumb = $('#thumbnails').is(':checked');

	if ($('.tableRowGraph:first').length) {
		var graphRow = $('.tableRowGraph:first').width();
	} else {
		var graphRow = 0;
	}

	if ($('.graphDrillDown:first').length) {
		var drillDown = $('.graphDrillDown:first').outerWidth() + 15;
	} else {
		var drillDown = 0;
	}

	if (myColumns == null) {
		myColumns = 1;
	}

	if (mainWidth < graphRow) {
		graphRow = mainWidth - drillDown;
	} else if (graphRow == 0) {
		graphRow = mainWidth;
	}

	// Dont resize if nothing changed
	if (typeof initialize == 'undefined' && (previousMainWidth == null || (previousMainWidth == mainWidth && previousColumns == myColumns))) {
		previousMainWidth = mainWidth;
		return true;
	}

	var myWidth = parseInt((graphRow - (drillDown * myColumns)) / myColumns);

	$('.graphimage').each(function () {
		var graph_id = $(this).attr('graph_id');

		if (!(graph_id > 0)) {
			graph_id = $(this).attr('id').replace('wrapper_', '');
			graph_id = $(this).attr('id').replace('graph_', '');
		}

		var rra_id = $(this).attr('rra_id');
		var type = $(this).attr('graph_type');

		var original_cwidth = $('#wrapper_' + graph_id).attr('graph_width');
		var original_cheight = $('#wrapper_' + graph_id).attr('graph_height');

		/* original image attributes */
		var image_width = $(this).attr('image_width');
		var image_height = $(this).attr('image_height');
		var canvas_top = $(this).attr('canvas_top');
		var canvas_left = $(this).attr('canvas_left');
		var canvas_width = $(this).attr('canvas_width');
		var canvas_height = $(this).attr('canvas_height');

		var remove_whcss = false;
		var ratio = myWidth / image_width;

		/* optimize display and set correct ratio if image is full size */
		if (image_width * original_cwidth / canvas_width < myWidth) {
			remove_whcss = true;
			ratio = original_cwidth / canvas_width;
		}

		// Dont change the size if the differential is small
		if (ratio > .97 && ratio < 1.03) {
			ratio = 1;
		}

		var new_image_width = parseInt(image_width * ratio)
		var new_image_height = parseInt(image_height * ratio)
		var new_canvas_width = parseInt(canvas_width * ratio);
		var new_canvas_height = parseInt(canvas_height * ratio);
		var new_canvas_graph_top = parseInt(canvas_top * ratio);
		var new_canvas_graph_left = parseInt(canvas_left * ratio);

		$(this).attr('graph_width', new_canvas_width);
		$(this).attr('graph_height', new_canvas_height);
		$(this).attr('graph_top', new_canvas_graph_top);
		$(this).attr('graph_left', new_canvas_graph_left);

		if (!remove_whcss || type == 'svg+xml') {
			$(this).css('width', new_image_width);
			$(this).css('height', new_image_height);
		} else {
			$(this).css('width', '');
			$(this).css('height', '');
			$(this).removeAttr('width');
			$(this).removeAttr('height');
		}
	});

	previousMainWidth = mainWidth;
	previousColumns = myColumns;

	if ($('.cactiTreeNavigationArea').length) {
		resizeTreePanel();
	}
}

function resizeTreePanel() {
	var docHeight = $(window).outerHeight();
	var navWidth = $('.cactiTreeNavigationArea').width();
	var searchHeight = $('.cactiTreeSearch').outerHeight();
	var pageHeadHeight = $('.cactiPageHead').outerHeight();
	var breadCrHeight = $('.breadCrumbBar').outerHeight();
	var pageBottomHeight = $('.cactiPageBottom').outerHeight();
	//console.log('----------------------');

	var jsTreeHeight = Math.max.apply(Math, $('#jstree').children(':visible').map(function () {
		return $(this).outerHeight();
	}).get());

	var treeAreaHeight = docHeight - pageHeadHeight - breadCrHeight - searchHeight - pageBottomHeight;
	//console.log('docHeight:' + docHeight);
	//console.log('searchHeight:' + searchHeight);
	//console.log('pageHeadHeight:' + pageHeadHeight);
	//console.log('pageBottomHeight:' + pageBottomHeight);
	//console.log('breadCrHeight:' + breadCrHeight);
	//console.log('jsTreeHeight:' + jsTreeHeight);
	//console.log('treeAreaHeight:' + treeAreaHeight);

	$('#jstree').height(jsTreeHeight + 30);
	$('.cactiTreeNavigationArea').height(treeAreaHeight + searchHeight);

	var visWidth = Math.max.apply(Math, $('#jstree').children(':visible').map(function () {
		return $(this).width();
	}).get());

	if (visWidth < 0) {
		$('.cactiTreeNavigationArea').css('width', 0);
		$('.cactiGraphContentArea').css('margin-left', 0);
		$('.cactiTreeNavigationArea').css('overflow-x', '');
	} else if (visWidth < minTreeWidth) {
		$('.cactiTreeNavigationArea').css('width', minTreeWidth);
		$('.cactiGraphContentArea').css('margin-left', minTreeWidth + 5);
		$('.cactiTreeNavigationArea').css('overflow-x', '');
	} else if (visWidth > maxTreeWidth) {
		$('.cactiTreeNavigationArea').css('width', maxTreeWidth);
		$('.cactiGraphContentArea').css('margin-left', maxTreeWidth + 5);
		$('.cactiTreeNavigationArea').css('overflow-x', 'auto');
	} else if (visWidth > navWidth) {
		$('.cactiTreeNavigationArea').css('width', visWidth);
		$('.cactiGraphContentArea').css('margin-left', visWidth + 5);
		$('.cactiTreeNavigationArea').css('overflow-x', 'auto');
	} else {
		$('.cactiTreeNavigationArea').css('width', navWidth);
		$('.cactiGraphContentArea').css('margin-left', navWidth + 5);
		$('.cactiTreeNavigationArea').css('overflow-x', '');
	}

	var navWidth = $('#navigation').width();
	if (navWidth > 220) {
		$('#searcher').css('width', navWidth - 70);
	} else {
		$('#searcher').css('width', 150);
	}
}

function countHiddenCols(object) {
	var hidden = 0;

	$(object).find('th').each(function () {
		if ($(this).css('display') == 'none') {
			hidden++;
		}
	});

	return hidden;
}

function tuneTables() {
	var mainWidth = getMainWidth();

	$('.cactiTable').each(function () {
		$(this).find('th:first-child').each(function () {
			var object = $(this).closest('.cactiTable');

			tuneTable(object, mainWidth);
		});
	});
}

function pageHasHidableColumnsAndProfile() {
	if (typeof userSettings != 'undefined' && userSettings && $(document).find('th').length) {
		return true;
	}

	return false;
}

function resetTables() {
	$('.cactiTable').each(function () {
		$(this).find('th:first-child').each(function () {
			var object = $(this).closest('.cactiTable');

			resetTable(object);
		});
	});
}

function resetTable(object) {
	var id = $(object).attr('id');
	var column = 1;
	$(object).find('th').each(function () {
		$('#' + id + ' th:nth-child(' + column + ')').show();
		$('#' + id + ' td:nth-child(' + column + ')').show();
		column++;
	});
}

function tuneTable(object, width) {
	var rows = $(object).find('tr').length;
	var width = width;
	var tableWidth = $(object).width();
	var totalCols = $(object).find('th').length;
	var reducedWidth = 0;
	var columnsHidden = countHiddenCols(object);
	var visibleColumns = totalCols - columnsHidden;
	var id = $(object).attr('id');

	if (rows > 101) return false;

	// Enable horizontal scroll bar
	if (hScroll) {
		$('#main, .cactiConsoleContentArea').css({ 'overflow-x': 'visible' });

		return false;
	} else {
		$('#main, .cactiConsoleContentArea').css({ 'overflow-x': 'hidden' });
	}

	// We have to both show and hide columns that fit.
	// So, always find the size of the page columns.
	var calculatedColumns = [];
	var calculatedWidth = 0;
	var allSeenWidth = 0;
	var calculatedPadding = 15;
	var tableChanged = false;
	var stopExpand = false;
	var debug = false;

	var tableHeaders = $(object).find('th');
	var tableCheckBox = $(tableHeaders).each(function () {
		if ($(this).index() == tableHeaders.length) {
			calculatedColumns.addClass('noHide');
		}
	});

	// Traverse the table and look for columns to hide from left to right
	$($(object).find('th').get()).each(function () {
		var isLastCheckBox = $(this).hasClass('tableSubHeaderCheckbox') && $(this).index() == tableHeaders.length - 1;
		var columnWidth = $.textMetrics(this).width;

		// Get the width of columns that can not be hidden
		if ($(this).hasClass('noHide') || isLastCheckBox) {
			calculatedColumns.push($(this).index());
			calculatedWidth += columnWidth + calculatedPadding;
		}

		if ($(this).is(':visible')) {
			allSeenWidth += columnWidth + calculatedPadding;
		}
	});

	if (debug) {
		console.log(
			'allSeenWidth:' + Math.round(allSeenWidth, 1) +
			', calulatedWidth:' + Math.round(calculatedWidth, 1) +
			', tableWidth:' + Math.round(tableWidth, 1) +
			', width:' + Math.round(width, 1)
		);
	}

	if (width < tableWidth) {
		$($(object).find('th').get()).each(function () {
			// Now traverse the available columns for hiding
			// and See which need to be hidden
			if (!calculatedColumns.includes($(this).index())) {
				var columnWidth = $.textMetrics(this).width + calculatedPadding;
				var name = $(this).find('div.sortinfo').attr('sort-column');

				if (debug) {
					console.log(
						'ColIndex:' + $(this).index() +
						', ColWidth:' + Math.round(columnWidth, 1) +
						', TotalWidth:' + Math.round(calculatedWidth, 1) +
						', PageWidth:' + Math.round(width, 1)
					);
				}

				if (width < calculatedWidth) {
					$(this).hide();
					tableChanged = true;
				} else {
					calculatedColumns.push($(this).index());
					calculatedWidth += columnWidth + calculatedPadding;
				}
			}
		});

		if (tableChanged) {
			$($(object).find('td').each(function () {
				if (!calculatedColumns.includes($(this).index())) {
					$(this).hide();
				}
			}));
		}
	}

	tableChanged = false;

	if (allSeenWidth < width) {
		calculatedColumns = calculatedColumns.sort();

		// Since we can show hidden columns now, let's go
		// in reverse until we run out of space
		$($(object).find('th').get()).each(function () {
			if (!calculatedColumns.includes($(this).index())) {
				if ($(this).is(':hidden') && stopExpand == false) {
					var columnWidth = $.textMetrics(this).width + calculatedPadding;
					var name = $(this).find('div.sortinfo').attr('sort-column');

					if (allSeenWidth + columnWidth < tableWidth) {
						$(this).show();

						var newTableWidth = $(object).width();

						if (debug) {
							console.log(
								'Reverse ColIndex:' + $(this).index() +
								', columnWidth:' + Math.round(columnWidth, 1) +
								', allSeenWidth:' + Math.round(allSeenWidth, 1) +
								', width:' + Math.round(width, 1) +
								', newTableWidth:' + Math.round(newTableWidth, 1)
							);
						}

						if (newTableWidth > width) {
							$(this).hide();
							stopExpand = true;
						} else {
							calculatedColumns.push($(this).index());
							tableChanged = true;
						}
					}
				}
			}
		});

		if (tableChanged) {
			$($(object).find('td').each(function () {
				if (calculatedColumns.includes($(this).index())) {
					$(this).show();
				}
			}));
		}
	}
}

function tuneFilter(object, width) {
	if ($(object).find('#timespan').length && $(object).find('#timespan').is(':visible')) {
		var timespan = true;

		var timeShiftWidth = $(object).find('.shiftArrow').closest('td').width();
		var dateWidth = $(object).find('#date1').closest('td').width() + $(object).find('#date1').closest('td').prev('td').width();
		var clearWidth = $('#tsclear').width();
		var refreshWidth = $('#tsrefresh').width();
	} else {
		var timespan = false;

		var clearWidth = $(object).find('#clear').width();
		var saveWidth = $(object).find('#save').width();
		var exportWidth = $(object).find('#export').width();
		var importWidth = $(object).find('#import').width();
	}

	var minTds = 2;
	var visTds = $(object).find('td:visible').length;

	if ($(object).find('input[type="button"]').length) {
		minTds++;
	}

	if ($(object).width() > width) {
		if (!timespan) {
			$($(object).find('td').get().reverse()).each(function () {
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
				$(object).find('td').each(function () {
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

function handleUserMenu(toggle) {
	var storage = Storages.localStorage;
	var windowWidth = $(window).width();

	/* set the navigation id is not set */
	if (currentNavId == 'undefined' || currentNavId == null) {
		currentNavId = 'menu:' + $('.lefttab.selected').attr('id');
	}

	/* check if the tab is selected */
	var checkNav = currentNavId.replace('menu:', '');

	if (!$('#'+checkNav).hasClass('selected')) {
		$('#'+checkNav).addClass('selected');
	}

	if (toggle) {
		if (currentNavState == 'visible') {
			currentNavState = 'hidden';
		} else {
			currentNavState = 'visible';
		}
	} else if (currentNavState == null) {
		currentNavState = 'visible';
	}

	storage.set(currentNavId, currentNavState);

	if ($('#navigation').length) {
		if (currentNavState == 'hidden') {
			menuHide(true);
		} else if (currentNavState == 'visible') {
			menuShow();
		}
	}
}

function menuHide(store) {
	var myClass = '';
	var curMargin = parseInt($('#navigation_right').css('margin-left'));

	if ($('.cactiTreeNavigationArea').length) {
		myClass = '.cactiTreeNavigationArea';

		if (curMargin > 0) {
			marginLeftTree = curMargin;
			$('.cactiTreeNavigationArea').css('width', curMargin);
		}
	} else if ($('.cactiConsoleNavigationArea').length) {
		myClass = '.cactiConsoleNavigationArea';

		if (curMargin > 0) {
			marginLeftConsole = curMargin;
		}
	}

	$('#navigation_right').animate({ 'margin-left': '0px' }, 20);

	if (myClass != '') {
		$(myClass).hide('slide', { direction: 'left' }, 20, function () {
			responsiveResizeGraphs();
		});
	}

	$('#navigation').hide();

	if (myClass == '.cactiTreeNavigationArea' || pageName == 'graph_view.php') {
		responsiveResizeGraphs();
	}
}

function menuShow() {
	var myClass = '';

	if (!userMenuNavigationExists(pageName) && pageName != 'graph_view.php' && pageName != 'about.php') {
		$('#navigation').hide();
		return;
	}

	if ($('.cactiTreeNavigationArea').length) {
		if (marginLeftTree == null) {
			marginLeftTree = minTreeWidth;
		}

		var treeWidth = $('.cactiTreeNavigationArea').width();

		myClass = '.cactiTreeNavigationArea';

		if (marginLeftTree > treeWidth) {
			$('#navigation_right').animate({ 'margin-left': marginLeftTree }, 20);
			$('.cactiTreeNavigationArea').css('width', marginLeftTree);
		}
	} else if ($('.cactiConsoleNavigationArea').length) {
		myClass = '.cactiConsoleNavigationArea';

		if (marginLeftConsole > 0) {
			$('#navigation_right').animate({ 'margin-left': marginLeftConsole }, 20);
		}
	}

	if (myClass != '') {
		$(myClass).show('slide', { direction: 'left' }, 20, function () {
			responsiveResizeGraphs();
		});
	}

	$('#navigation').show();
}

function loadTopTab(href, id, force) {
	/* used for debugging */
	var stack = ''; //getStackTrace(); // new Error().stack;
	var url   = href;// + (href.indexOf('?') > 0 ? '&' : '?') + 'headercontent=true';

	/* close all toasts */
	$.toast().reset('all');

	/**
	 * set the last visible lefttabs have a menu state,
	 * right tabs do not and are always visible.
	 */
	if (id !== undefined && id.startsWith('tab-')) {
		currentNavId    = 'menu:'+id;
		currentNavState = storage.get(currentNavId);

		if (currentNavState == null) {
			currentNavState = 'visible';
		}
	} else {
		currentNavState = 'visible';
	}

	return loadUrl({
		url: url,
		tabId: id,
		force: force,
		loadType: 'toptab',
		funcStart: 'loadTopTabStart',
		funcEnd: 'loadTopTabEnd',
		elementId: 'cactiContent'
	});
}

function loadTopTabStart(options) {
	$('.submenuoptions').slideUp(120);
	$('.menuoptions').slideUp(120);

	if (options.url.indexOf('graph_view.php') >= 0) {
		$('.cactiGraphHeaderBackground').show();
		$('.cactiConsolePageHeadBackdrop').hide();
	} else if (userMenuNavigationExists(options.url)) {
		$('.cactiGraphHeaderBackground').hide();
		$('.cactiConsolePageHeadBackdrop').show();
	}
}

function loadTopTabEnd(options) {
	if (options.pageName != '') {
		if ($('#menu').find("a[href^='" + escapeString(options.url) + "']").length > 0) {
			$('#menu').find('.pic').removeClass('selected');
			$('#menu').find("a[href^='" + escapeString(options.url) + "']").addClass('selected');
		} else if ($('#menu').find("a[href*='/" + pageName + "']").length > 0) {
			$('#menu').find('.pic').removeClass('selected');
			$('#menu').find("a[href*='/" + pageName + "']").addClass('selected');
		}
	}

	if (options.tabId) {
		var tabElementId = '#' + options.tabId;
		var tabElement = $(tabElementId);

		if (tabElement.hasClass('lefttab')) {
			$('.lefttab').removeClass('selected');
			$('.submenuoptions').find('.selected').removeClass('selected');
			tabElement.addClass('selected');

			var hideTabId = tabElementId.substring(0, tabElementId.length - 9);

			if (hideTabId) {
				$(hideTabId).addClass('selected');
			}
		} else if (tabElement.parents('.submenuoptions').length > 0) {
			tabElement.parents('.submenuoptions').find('.selected').removeClass('selected');
			tabElement.addClass('selected');
		}
	}
}

function userMenuNavigationExists(url) {
	if (url == 'index.php' || basename(url) == 'index.php') {
		return true;
	} else {
		var myFound = false;

		$('#navigation').find('a').each(function () {
			var checkUrl = basename($(this).attr('href'));

			if (checkUrl == url || checkUrl == basename(url)) {
				myFound = true;

				return true;
			}
		});

		return myFound;
	}

	return false;
}

function loadPageUsingPost(href, postData, returnLocation) {
	var stack = ''; //getStackTrace(); // new Error().stack;
	console.error("Function loadPageUsingPost is now depreciated, use postUrl instead\n" + stack);
	return postUrl({
		url: href,
		tabId: returnLocation,
		type: 'loadPageUsingPost',
	}, postData);
}

function setNavigationScroll() {
	var object = '';

	$('.cactiConsoleNavigationArea, .cactiTreeNavigationArea').unbind('mousemove').on('mousemove', function (pos) {
		object = '';

		if ($('.cactiConsoleNavigationArea').length) {
			object = '.cactiConsoleNavigationArea';
		} else if ($('.cactiTreeNavigationArea').length) {
			object = '.cactiTreeNavigationArea';
		}

		if (object != '') {
			var mpos = $(object).position();
			var width = $(object).outerWidth();
			var height = $(object).outerHeight();
			if (pos.pageX < mpos.left ||
				pos.pageY < mpos.top ||
				pos.pageX > mpos.left + width - 1 ||
				pos.pageY > mpos.top + height - 1) {

				if (isHover) {
					clearTimeout(hoverTimer);
					hoverTimer = setTimeout(function () {
						$(object).css('overflow-y', 'hidden');
					}, 500);
				}

				isHover = false;
			} else {
				if (!isHover) {
					clearTimeout(hoverTimer);
					hoverTimer = setTimeout(function () {
						$(object).css('overflow-y', 'auto');
					}, 500);
				}

				isHover = true;
			}
		}
	});

	$('.cactiConsoleNavigationArea, .cactiTreeNavigationArea').unbind('mouseleave').on('mouseleave', function (pos) {
		if ($('.cactiConsoleNavigationArea').length) {
			object = '.cactiConsoleNavigationArea';
		} else if ($('.cactiTreeNavigationArea').length) {
			object = '.cactiTreeNavigationArea';
		}

		isHover = false;

		clearTimeout(hoverTimer);
		hoverTimer = setTimeout(function () {
			$(object).css('overflow-y', 'hidden');
		}, 500);
	});
}

function loadPageNoHeader(href, scroll, force) {
	var stack = ''; //getStackTrace(); // new Error().stack;
	console.error("Function loadPageNoHeader is now depreciated, use loadUrl instead\n" + stack);
	return loadUrl({
		url: href,
		scroll: scroll,
		force: force,
		loadType: 'noheader'
	});
}

function loadPage(href, force) {
	var stack = ''; //getStackTrace(); // new Error().stack;
	console.error("Function loadPage is now depreciated, use loadUrl instead\n" + stack);
	return loadUrl({
		url: href,
		force: force,
		loadType: 'loadPage'
	});
}

function loadUrl(options) {
	statePushed = false;
	cont = false;

	if (typeof options.noState == 'undefined' || options.noState == false) {
		/* close all toasts */
		$.toast().reset('all');
	}

	options = sanitizeAjaxOptions(options);
	if (!options.force) {
		cont = checkFormStatus(options.url, options.loadType, options.scroll);
	} else {
		cont = true;
	}

	if (cont) {
		if (options.funcStart != '') {
			window[options.funcStart](options);
		}

		/* remove dialogs that were not purged */
		$('.ui-dialog').empty().remove();

		closeDateFilters();

		clearAllTimeouts();

		$.ajaxQ.abortAll();
		return $.get(options.url)
			.done(function (html) {
				handleAjaxResponse(html, options);
				return false;
			})
			.fail(function (html) {
				getPresentHTTPError(html);
			});
	}

	return false;
}

function postUrl(options, data) {
	statePushed = false;
	cont = false;

	if (typeof options.noState == 'undefined' || options.noState == false) {
		/* close all toasts */
		$.toast().reset('all');
	}

	options = sanitizeAjaxOptions(options);
	if (options.funcStart != '') {
		window[options.funcStart](options);
	}

	$.ajaxQ.abortAll();
	return $.post(options.url, data)
		.done(function (html) {
			handleAjaxResponse(html, options);
			return false;
		})
		.fail(function (html) {
			getPresentHTTPError(html);
		});
}

function findElement(htmlObject, element) {
	var wanted = $();

	if (htmlObject !== null) {
        wanted = htmlObject.find(element);
        if (typeof wanted == 'undefined' || wanted.length == 0) {
            wanted = htmlObject.filter(element);
        }
	}

	return wanted;
}

function sanitizeAjaxOptions(check) {
	options = {
		force: false,
		scroll: false,
		elementId: 'main',
		tabId: '',
		loadType: 'noheader',
		funcStart: '',
		funcEnd: '',
		url: '',
		pageName: '',
		noState: false,
		handle: true,
		redirect: '',
	};

	if (typeof check == 'undefined') {
		check = {};
	}

	if (typeof check.url == 'string') {
		options.url = check.url;
		var hrefParts = options.url.split('?');
		options.pageName = basename(hrefParts[0]);
		options.noState = options.url.indexOf('nostate') > -1;
	}

	if (typeof check.noState != 'undefined') {
		options.noState = check.noState;
	}

	if (typeof check.scroll != 'undefined') {
		options.scroll = check.scroll;
	}

	if (typeof check.funcStart == 'string') {
		options.funcStart = check.funcStart;
	}

	if (typeof check.funcEnd == 'string') {
		options.funcEnd = check.funcEnd;
	}

	if (typeof check.elementId == 'string') {
		options.elementId = check.elementId;
	}

	if (typeof check.tabId == 'string') {
		options.tabId = check.tabId;
	}

	if (typeof check.loadType == 'string') {
		options.loadType = check.loadType;
	}

	if (typeof check.redirect == 'string') {
		options.redirect = check.redirect;
	}

	if (typeof check.handle == 'boolean') {
		options.handle = check.handle;
	}

	if (typeof check.force == 'boolean') {
		options.force = check.force;
	}

	if (options.elementId.trim() == '') {
		options.elementId = 'main';
	}

	return options;
}

function handleAjaxResponse(html, options) {
	checkForLogout(html);

	options = sanitizeAjaxOptions(options);

	if (options.handle && options.redirect.trim() == '') {
		elementId = '#' + options.elementId;

		var htmlObject = null;
		try {
			htmlObject = $(html);
		} catch (Exception) {
			htmlObject = null;
		}

		var matches = null;
		if (typeof html.match !== 'undefined') {
			matches = html.match(/<title>(.*?)<\/title>/);
		}

		if (matches != null) {
			var htmlTitle = matches[1];
			var breadCrumbs = findElement(htmlObject, '#breadcrumbs').html();
			var htmlContent = findElement(htmlObject, elementId).html();

			$('title').text(htmlTitle);
			$('#breadcrumbs').html(breadCrumbs);

			myTitle = htmlTitle;
		}

		if (typeof htmlContent == 'undefined') {
			htmlContent = html;
		}

		// No need to check noState on options
		// as this is handled inside pushState();
		pushState(myTitle, options);

		if (html !== null && html.length > 0) {
			$(elementId).empty().hide();
			$(elementId).html(htmlContent);
			$(elementId).show();
		}

		if (options.pageName != '') {
			// Workaround for Create Device
			if (options.pageName == 'host.php') {
				if (options.url.indexOf('create') >= 0) {
					$('#menu').find('.pic').removeClass('selected');
					$('#menu').find("a[href='" + escapeString(options.url) + "']").addClass('selected');
				} else {
					$('#menu').find('.pic').removeClass('selected');
					$('#menu').find("a[href$='host.php']").addClass('selected');
				}
			} else if ($('#menu').find("a[href^='" + escapeString(options.url) + "']").length > 0) {
				$('#menu').find('.pic').removeClass('selected');
				$('#menu').find("a[href^='" + escapeString(options.url) + "']").addClass('selected');
			} else if ($('#menu').find("a[href*='/" + options.pageName + "']").length > 0) {
				$('#menu').find('.pic').removeClass('selected');
				$('#menu').find("a[href*='/" + options.pageName + "']").addClass('selected');
			}
		}

		applySkin()

		var scrollTop = (isMobile.any() != null) ? 1 : 0;

		if (options.scroll) {
			scrollTop = options.scroll;
		}

		window.scrollTo(0, scrollTop);

		handleConsole(options.pageName);

		Pace.stop();
	}

	if (options.funcEnd != '') {
		if (typeof window[options.funcEnd] === 'undefined') {
			console.log('WARNING: Missing function "' + options.funcEnd + '".');
		} else {
			window[options.funcEnd](options, html);
		}
	}

	if (options.redirect.trim() != '') {
		document.location = options.redirect;
	}
}

function getPresentHTTPError(data) {
	getPresentHTTPErrorOrRedirect(data);
}

function getPresentHTTPErrorOrRedirect(data, url) {
	if (typeof data.status != 'undefined') {
		var errorStr = data.status;
		var errorSub = data.statusText;
		var errorText = errorReasonUnexpected;
		var found = false;

		if (typeof data.responseText != 'undefined') {
			var dataText = data.responseText;

			var title_match = dataText.match(/<title>(.*?)<\/title>/);
			var head_match = dataText.match(/<h1>(.*?)<\/h1>/);
			var para_match = dataText.match(/<p>(.*?)<\/p>/);

			if (title_match != null) {
				var errorSub = title_match[1];
				found = true;
			}

			if (head_match != null) {
				var errorSub = head_match[1];
				found = true;
			}

			if (para_match != null) {
				var errorText = para_match[1];
				found = true;
			}

			if (!found && dataText != '') {
				var errorText = dataText;
			}

			var returnStr = '<div id="httperror" style="display:none">' +
				'<h4>' + errorOnPage + '</h4><hr>' +
				'<div style="padding-bottom: 5px;"><div style="display:table-cell;width:75px"><b>' + errorNumberPrefix + '</b></div> ' +
				'<div style="display:table-cell"> ' + errorStr + ' ' + errorSub + '</div></div>' +
				'<div><div style="display:table-cell;width:75px"><b>' + errorReasonPrefix + '</b></div> ' +
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
					Ok: function () {
						$(this).dialog('close');
					}
				}
			});
		}
	}

	if (typeof url != 'undefined') {
		if (data.status >= 500) {
			// Let the HTTP Error stick log an error
			$.get(urlPath + 'help.php?page=' + escape(url) + '&error=' + data.status);
		} else if (data.status >= 400) {
			// Let the HTTP Error stick
		} else if (data.statusText != 'abort') {
			$.ajaxQ.abortAll();
			document.location = url;
		}
	}
}

function ajaxAnchors() {
	var page = basename(location.pathname);

	$('a.pic, a.linkOverDark, a.linkEditMain, a.console, a.hyperLink, a.tab').not('[href^="http"], [href^="https"], [href^="#"], [href^="mailto"], [target="_blank"]').off('click').on('click', function (event) {
		if (!shouldCaptureClick(event)) {
			return;
		}

		event.preventDefault();
		event.stopPropagation();

		if ($(window).width() < 640) {
			menuHide(false);
		}

		/* determine the page name */
		var href = $(this).attr('href');

		if (href == '#') {
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

		loadUrl({
			url: href,
			loadType: 'loadPage'
		});

		return false;
	});
}

function checkFormStatus(href, type, scroll_or_id) {
	var changed = false;

	if ($('.cactiFormStart').not('#chk').length) {
		$('.cactiFormStart').not('#chk').each(function () {
			var formID = $(this).attr('id');
			var submitData = $(this).serializeForm();

			if (typeof formArray != 'undefined' && typeof formArray[formID] != 'undefined') {
				var formData = formArray[formID];

				$.each(submitData, function (index, value) {
					if (typeof formData[index] != 'undefined') {
						if (formData[index] != value) {
							if (index == 'settings_sendmail_path' || index == 'rrd_archive' || index == '__csrf_magicSubmit' || index == '__csrf_magic' || index == 'settings_smtp_password' || index == 'settings_smtp_password_confirm') {
								// Ignore this entry
							} else if (index.indexOf('[]') > 0) {
								// Ignore this entry
							} else {
								console.log('Index:-' + index + '-:Submit:' + value + ', Orig:' + formData[index]);
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

	$('.collapsible').each(function (data) {
		var id = $(this).attr('id') + '_cs';
		if (storage.isSet(id)) {
			var state = storage.get(id);
		} else {
			var state = 'show';
		}

		if (state == 'hide') {
			$(this).addClass('collapsed');
			$(this).nextUntil('div.spacer').hide();
			$(this).find('i').removeClass('fa-angle-double-up').addClass('fa-angle-double-down');
			storage.set(id, 'hide');
		}
	});

	$('.collapsible').off('click').on('click', function (data) {
		var id = $(this).attr('id') + '_cs';

		if ($(this).find('i').hasClass('fa-angle-double-up')) {
			$(this).addClass('collapsed');
			$(this).nextUntil('div.spacer').slideUp('slow');
			$(this).find('i').removeClass('fa-angle-double-up').addClass('fa-angle-double-down');
			storage.set(id, 'hide');
		} else {
			$(this).removeClass('collapsed');
			$(this).nextUntil('div.spacer').slideDown('slow');
			$(this).nextUntil('div.spacer').each(function (data) {
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
	if (pageName == null || pageName.length == 0) {
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

	$('.menuoptions').mouseenter(function () {
		clearTimeout(userMenuTimer);
	}).mouseleave(function () {
		if ($('.menuoptions').is(':visible')) {
			userMenuTimer = setTimeout(function () { closeUserMenu(); }, 1000);
		}
	});

	$('.user').mouseenter(function (data) {
		clearTimeout(userMenuTimer);
		userMenuOpenTimer = setTimeout(function () { openUserMenu(); }, 400);
		openUserMenu();
	}).mouseleave(function (data) {
		if ($('.menuoptions').is(':visible')) {
			userMenuTimer = setTimeout(function () { closeUserMenu(); }, 1000);
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
	$('th.sortable').on('click', function (e) {
		document.getSelection().removeAllRanges();

		var $target = $(e.target);
		var sortAdd = '';
		var href = '';

		if (!$target.is('.ui-resizable-handle')) {
			var page = $(this).find('.sortinfo').attr('sort-page');
			var column = $(this).find('.sortinfo').attr('sort-column');
			var direction = $(this).find('.sortinfo').attr('sort-direction');
			var returnto = $(this).find('.sortinfo').attr('sort-return');

			if (shiftPressed) {
				sortAdd = '&add=true';
			} else {
				sortAdd = '&add=reset';
			}

			url = page + (page.indexOf('?') > 0 ? '&' : '?') +
				'sort_column=' + column +
				'&sort_direction=' + direction +
				sortAdd;

			loadUrl({
				url: url,
				elementId: returnto
			});
		}
	});
}

function shouldCaptureClick(event) {
	var evt = event ? event : window.event;
	return !(evt.shiftKey || evt.altKey || evt.ctrlKey || evt.metaKey);
}

function setupBreadcrumbs() {
	$('#breadcrumbs > li > a').click(function (event) {
		if (!shouldCaptureClick(event))
			return

		event.preventDefault();
		event.stopPropagation();

		var href = $(this).attr('href');

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
	$('table.cactiTable').each(function (data) {
		var key = $(this).attr('id');

		if (storage.isSet(key)) {
			var sizes = storage.get(key);
		} else {
			var sizes = new Array();
		}

		var items = sizes ? sizes : new Array();
		var width = $(document).width();

		// if the table width changes, reset the columns
		if (key !== undefined && sizes == undefined) {
			storage.remove(key);

			var sizes = new Array();
			var items = new Array();
		} else if (key !== undefined && initial) {
			if (items.length > 0) {
				if (items[0] + 18 < width) {
					storage.remove(key);

					var sizes = new Array();
					var items = new Array();
				}
			}
		}

		var i = 1;

		items[0] = width;
		sizes[0] = width;

		if (key !== undefined) {
			if (initial && items.length > 1) {
				$('#' + key).find('th.ui-resizable').each(function (data) {
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
				$('#' + key).find('th.ui-resizable').each(function (data) {
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

		start: function (event, ui) {
			colWidth = $(this).width();
			originalSize = ui.size.width;

			if (originalSize == 0) {
				originalSize = $(this).width();
			}

			$(ui.originalElement).siblings().each(function (data) {
				$(this).attr('resizeWidth', $(this).width());
			});
		},

		resize: function (event, ui) {
			var resizeDelta = ui.size.width - originalSize;
			var newColWidth = colWidth + resizeDelta;
			var nextWidth = $(ui.element).next().attr('resizeWidth');
			$(ui.element).next().css('width', nextWidth - resizeDelta);
			$(ui.element).prevUntil('tr').each(function (data) {
				$(this).css('width', $(this).attr('resizeWidth'));
			});
			$(this).css('height', 'auto');
		},

		stop: function (event, ui) {
			saveTableWidths(false);
		}
	});

	saveTableWidths(true);
}

function correctUrlParameters(url) {
	if (url.indexOf('action=tree_content') < 0 && url.indexOf('action=tree') >= 0) {
		url = url.replace('action=tree', 'action=tree_content');
	}

	return url;
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
		myRefresh = setTimeout(function () {
			if (refreshIsLogout) {
				document.location = urlPath + 'logout.php?action=timeout';
			} else {
				if (previousPage != '') {
					refreshPage = previousPage;
				}

				/* fix coner case with tree refresh */
				refreshPage = correctUrlParameters(refreshPage);
				loadUrl({ url: refreshPage });
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
	myHref = $(location).attr('href');
	myTitle = $(document).attr('title');
}

function clearAllTimeouts() {
	if (typeof installTimer != 'undefined') {
		return true;
	}

	var id = window.setTimeout(function () { }, 0);

	while (id--) {
		window.clearTimeout(id); // will do nothing if no timeout with id is present
	}
}

function setZoneInfo() {
	var dt = new Date();
	var tz = -dt.getTimezoneOffset();
	var maxAge = 365 * 86400;

	var CactiDateTime = 'CactiDateTime=' + dt.toString() + '; Max-Age=' + maxAge + '; path=' + urlPath + '; SameSite=Strict;';
	var CactiTimeZone = 'CactiTimeZone=' + tz.toString() + '; Max-Age=' + maxAge + '; path=' + urlPath + '; SameSite=Strict;';

	if (window.location.protocol == 'https:') {
		CactiDateTime += ' Secure;';
		CactiTimeZone += ' Secure;';
	}

	document.cookie = CactiDateTime;
	document.cookie = CactiTimeZone;
}

$(function () {
	statePushed = false;
	popFired = false;
	var tapped = false;

	/**
	 * Unbind key elements to debounce actions
	 */
	$('input, select, textarea, a').unbind();

	// Use traditional popstate handler
	window.onpopstate = function (event) {
		handlePopState(event);
	}

	$('#filter, #rfilter').keydown(function (event) {
		if (event.keyCode == 8 && $(this).val() == '') {
			handlePopState();
		}
	});

	setZoneInfo();

	setTitleAndHref();

	setupUserMenu();

	applySkin();

	setupEllipsis();

	handleUserMenu();

	$('#navigation_right').show();

	if (isMobile.any() != null) {
		$(window).on('touchstart', function (event) {
			if (!tapped) {
				tapped = setTimeout(function () { tapped = null; }, 300);
			} else {
				clearTimeout(tapped);
				tapped = null;

				if (screenfull.enabled) {
					screenfull.request();
				}
			}
		});

		$(window).on('load', function (event) {
			setTimeout(function () { window.scrollTo(0, 1); }, 0);
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
			clearTimeout(timers[uniqueId]);
		}

		timers[uniqueId] = setTimeout(callback, ms);
	};
})();

function setSelectMenus() {
	$.widget("ui.selectmenu", $.ui.selectmenu, {
		_renderMenu: function(ul, items) {
			let that = this;
			let attr = this.element[0].attributes;

			$.each( items, function( index, item ) {
				that._renderItemData( ul, item );
			});

			if (typeof(attr['data-defaultLabel']) !== 'undefined') {
				$(ul).parent().prepend('<div class="mdw-selectmenu-search"><input style="width:100%" type="search" class="ui-state-default ui-corner-all" data-scope="theme" placeholder="' + searchPlaceholder + '"></div>');

				this._on(false, this.menuWrap.find('input'), {
					'keydown': function (event) {
						var preventDefault = true;
						switch ( event.keyCode ) {
							case $.ui.keyCode.TAB:
							case $.ui.keyCode.ESCAPE:
								this.close(event);
								break;
							case $.ui.keyCode.ENTER:
							case $.ui.keyCode.UP:
							case $.ui.keyCode.DOWN:
							case $.ui.keyCode.HOME:
							case $.ui.keyCode.PAGE_UP:
							case $.ui.keyCode.END:
							case $.ui.keyCode.PAGE_DOWN:
							case $.ui.keyCode.SPACE:
								this.menu.trigger(event);
								break;
							default:
								preventDefault = false;
								break;
						}
						if ( preventDefault ) {
							event.preventDefault();
						}
					},
					'input': function (event) {
						let search_string = that.menuWrap.find('input').val().toUpperCase();
						$(ul).find('li').each(function (index, item) {
							if ($(this).text().toUpperCase().indexOf(search_string) > -1) {
								$(this).addClass('ui-menu-item').prop('hidden', false);
							} else {
								$(this).removeClass('ui-menu-item').prop('hidden', true);
							}
						})
					}
				})
			}
		},
		_renderButtonItem: function(item) {
			let that = this;
			let attr = this.element[0].attributes;

			if (typeof(attr['data-defaultLabel']) !== 'undefined') {
				let defaultLabel = attr['data-defaultLabel'].value;
				let filterActive = '';
				let defaultIndex = 0;
				let defaultValue = this.element.find("option").eq(0).val();

				if (typeof(attr['data-defaultValue']) !== 'undefined') {
					let defaultValue = attr['data-defaultValue'].value;
				}

				filterActive = (defaultValue !== item.value) ? 'true' : 'false';

				/* fallback to index value -- maybe superfluous */
				if (filterActive === '') {
					filterActive = (defaultIndex !== item.index) ? 'true' : 'false';
				}

				let buttonItem = $( "<span>", {
					"class": "ui-selectmenu-text",
					"data-active": filterActive
				})

				if (filterActive === 'true') {
					this._setText( buttonItem, defaultLabel + ': ' + item.label );

					let icon = {'button' : 'ui-icon-close'};
					this._setOption( 'icons', icon );

					this._off( this.button.find( "span.ui-icon" ), 'click');
					this._on( false, this.button.find( "span.ui-icon" ), {
						click: function( event ) {
							event.stopImmediatePropagation();
							let defaultValue = that.element.find( "option" ).eq(0).val();
							let item = {'index' : 0, 'value' : defaultValue };
							this._select( item, event);
						}
					} );
				} else {
					this._setText( buttonItem, defaultLabel );
				}
				return buttonItem;
			} else {
				let buttonItem = $( "<span>", {
					"class": "ui-selectmenu-text",
				})
				this._setText( buttonItem, item.label );
				return buttonItem;
			}
		}
	});

	$('select.colordropdown').dropcolor();

	$('select').not('.colordropdown').not('.multi-select').each(function() {
		if ($(this).prop('multiple') != true) {
			$(this).each(function() {
				let id = $(this).attr('id');
				let text = 'hello';

				$(this).selectmenu({
					open: function(event, ui) {
						let instance = $(this).selectmenu('instance');
						instance.menuInstance.focus(null, instance._getSelectedItem());
						let search = instance.menuWrap.find('input');
						if (search.length > 0) search.focus();
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
}

function setupEllipsis() {
	$('<div class="dropdownMenu">' +
		'<ul id="submenu-ellipsis" class="submenuoptions" style="display:none;">' +
		'</ul>' +
		'</div>').appendTo('body');

	$('.maintabs-submenu, .usertabs-submenu, .submenu-ellipsis').off('click').on('click', function (event) {
		event.preventDefault();

		var submenu_index = $(this).attr('id').replace('menu-', 'submenu-');
		var submenu = $('#' + submenu_index);

		if (submenu.is(':visible') === false) {
			/* close other drop down menus first */
			$('.submenuoptions').slideUp(120);
			$('.menuoptions').slideUp(120);

			/* re-position */
			var position = $(this).position();

			if (position.left - parseInt(submenu.outerWidth()) < 0) {
				submenu.css({ 'left': 0 }).slideDown(120);
			} else {
				submenu.css({ 'left': position.left - parseInt(submenu.outerWidth()) + parseInt($(this).outerWidth()) }).slideDown(120);
			}
		} else {
			submenu.slideUp(120);
		}

		return false;
	});

	$('.submenuoptions').mouseenter(function (event) {
		clearTimeout(userMenuTimer);
	}).mouseleave(function (event) {
		if ($('.submenuoptions').is(':visible')) {
			userMenuTimer = setTimeout(function () { $('.submenuoptions').stop().slideUp(120); }, 1000);
		} else {
			clearTimeout(userMenuOpenTimer);
		}
	});

	$(window).on('click', function (event) {
		if ($(event.target).parents('.submenuoptions').length == 0 && $('.submenuoptions').is(':visible')) {
			$('.submenuoptions').slideUp(120);
		}
		if ($(event.target).parents('.menuoptions').length == 0 && $('.menuoptions').is(':visible')) {
			$('.menuoptions').slideUp(120);
		}
	});
}

function keepWindowSize() {
	$(window).resize(function (event) {
		waitForFinalEvent(function () {
			$('.cactiGraphContentArea').show();

			var resizeTime = new Date();
			var myEvent = event;
			if (resizeTimeout === false) {
				resizeTimeout = true;
				setTimeout(function () {
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

			$('body').css('height', heightPage);
			$('#cactiContent, #navigation, #navigation_right').css('height', heightPageContent);

			// Handle links pages
			$('#content').css({ 'width': '100%', 'height': heightPageContent - 4 });

			var navWidth = $('#navigation').width();
			if (navWidth > 220) {
				$('#searcher').css('width', navWidth - 70);
			} else {
				$('#searcher').css('width', 150);
			}

			responsiveResizeGraphs();

			/* close open dropdown menus first off */
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

			var bodyWidth = $('body').width();
			var otherWidth = 0;
			$('#tabs').find('div:not(.maintabs):visible').each(function () {
				otherWidth += $(this).outerWidth();
			});

			var ellipsisWidth = $('.maintabs-submenu-ellipsis').outerWidth();
			var tabHeight = $('#tabs').outerHeight();
			var mainTabPos = false;

			if ($('.maintabs').length) {
				mainTabPos = $('.maintabs:first').position();
			}

			if ($('.usertabs').length) {
				mainTabHeight = tabHeight;
				userTabPos = $('.usertabs').position();
			} else if (mainTabPos != false) {
				mainTabHeight = $('.maintabs:first nav').outerHeight();
				userTabPos = mainTabPos;
			}

			pageWidth = bodyWidth;

			var items = $($('.maintabs nav ul li a.lefttab').get());
			var done = false;
			items.each(function () {
				var id = $(this).attr('id');

				showCurrentTab(id);
			});

			// Hide top menus if you have to
			var items = $($('.maintabs nav ul li a.lefttab').get().reverse());
			var done = false;
			items.each(function () {
				var id = $(this).attr('id');

				if (!done) {
					if (tabsWrapping()) {
						hideCurrentTab(id, true);
					} else {
						done = true;
					}
				}
			});

			if ($('#submenu-ellipsis li').length == 0) {
				$('.ellipsis').hide();
			} else {
				$('.ellipsis').show();
			}
		}, 50, 'resize-content');
	}).trigger('resize');
}

function hideCurrentTab(id, shrinking) {
	if ($('#' + id + '-ellipsis').length == 0) {
		var myid = id + '-ellipsis';
		var href = $('#' + id).attr('href');
		var selected = $('#' + id).hasClass('selected');
		var text = $('#' + id).text();

		if (shrinking) {
			$('#submenu-ellipsis').prepend('<li><a class="lefttab' + (selected ? ' selected' : '') + '" id="' + myid + '" href="' + href + '">' + text + '</a></li>');
		} else {
			$('#submenu-ellipsis').append('<li><a class="lefttab' + (selected ? ' selected' : '') + '" id="' + myid + '" href="' + href + '">' + text + '</a></li>');
		}

		setupResponsiveMenuAndTabs();

		$('#' + id).parent().hide();
	}
}

function showCurrentTab(id) {
	$('#' + id + '-ellipsis').parent().remove();
	$('#' + id).parent().show();
}

function tabsWrapping() {
	var mainTabPos = $('.maintabs:first').position();
	var tabHeight = $('#tabs').height();

	if ($('.usertabs').length) {
		var mainTabHeight = tabHeight;
		var userTabPos = $('.usertabs').position();
	} else {
		var mainTabHeight = $('.maintabs:first nav').height();
		var userTabPos = mainTabPos;
	}

	var bodyWidth = $('body').width();
	var otherWidth = 0;
	$('#tabs').find('div:not(.maintabs):visible').each(function () {
		otherWidth += $(this).outerWidth();
	});

	var ellipsisWidth = $('.maintabs-submenu-ellipsis').outerWidth();
	var mtabsWidth = $('.maintabs:not(.usertabs)').outerWidth();

	if ($('#gtabs>.tabs').length) {
		var gtabsWidth = $('#gtabs>.tabs').outerWidth();
	} else {
		var gtabsWidth = 0;
	}

	if ($('.usertabs').length) {
		var utabsWidth = $('.usertabs').outerWidth();
	} else {
		var utabsWidth = 0;
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

var graphPage = urlPath + 'graph_view.php';
var pageAction = 'preview';

function checkForLogout(data) {
	checkForRedirects(data, null);
}

function checkForRedirects(data, href) {
	if (typeof data == 'undefined') {
		return true;
	} else if (typeof data == 'object') {
		return true;
	} else if (data.indexOf('cactiLoginSuspend') >= 0) {
		$.ajaxQ.abortAll();
		document.location = urlPath + 'logout.php?action=disabled';
	} else if (data.indexOf('cactiRemoteState') >= 0) {
		$.ajaxQ.abortAll();
		document.location = urlPath + 'logout.php?action=remote';
	} else if (data.indexOf('cactiPermissionDenied') >= 0) {
		$.ajaxQ.abortAll();
		document.location = urlPath + 'permission_denied.php';
	} else if (data.indexOf('cactiRedirect') >= 0) {
		if (typeof href == 'undefined' || href == null) {
			$.ajaxQ.abortAll();
			location.reload();
		} else {
			$.ajaxQ.abortAll();
			document.location = href;
		}
	} else if (data.indexOf('cactiLoginLogo') >= 0) {
		$.ajaxQ.abortAll();
		document.location = urlPath + 'logout.php?action=timeout';
	}

	return false;
}

function clearGraphFilter() {
	var href = correctUrlParameters(graphPage + '?action=' + pageAction + '&clear=1');
	loadUrl({ url: href });
}

function closeDateFilters() {
	date1Open = false;
	date2Open = false;

	$('#date1').datetimepicker('hide');
	$('#date2').datetimepicker('hide');
}

function saveGraphFilter(section) {
	postUrl({
		url: graphPage + '?action=save',
		funcEnd: 'finializeGraphFilter'
	}, {
		section: section,
		columns: $('#columns').val(),
		graphs: $('#graphs').val(),
		graph_template_id: $('#graph_template_id').val(),
		predefined_timespan: $('#predefined_timespan').val(),
		predefined_timeshift: $('#predefined_timeshift').val(),
		thumbnails: $('#thumbnails').is(':checked'),
		__csrf_magic: csrfMagicToken
	});
}

function finalizeGraphFilter(options, data) {
	$('#text').show().text(filterSettingsSaved).fadeOut(2000, function () {
		$('#text').empty();
	});
}

function applyGraphFilter() {
	var href = correctUrlParameters(graphPage + '?action=' + pageAction +
		'&rfilter=' + base64_encode($('#rfilter').val()) +
		(typeof $('#host_id').val() != 'undefined' ? '&host_id=' + $('#host_id').val() : '') +
		(typeof $('#graph_source').val() != 'undefined' ? '&graph_source=' + $('#graph_source').val() : '') +
		(typeof $('#graph_order').val() != 'undefined' ? '&graph_order=' + $('#graph_order').val() : '') +
		'&columns=' + $('#columns').val() +
		'&graphs=' + $('#graphs').val() +
		'&graph_template_id=' + $('#graph_template_id').val() +
		'&thumbnails=' + $('#thumbnails').is(':checked'));

	loadUrl({ url: href });
}

function cleanUrlParameters(href) {
	href = href.replace('action=tree_content', 'action=tree');
	href = href.replace('?header=false', '?').replace('&header=false', '');
	href = href.replace('?nostate=true', '?').replace('&nostate=true', '').replace('?&', '?');

	return href;
}

function pushState(myTitle, options) {
	options = sanitizeAjaxOptions(options);
	if (!options.noState) {
		if (statePushed == false) {
			if (typeof window.history.pushState != 'undefined') {
				var myObject = { Page: myTitle, Url: cleanUrlParameters(options.url), Options: Object.assign({}, options) };
				window.history.pushState(myObject, myObject.Page, myObject.Url);
			}
		}
		statePushed = true;
	}
}

function handlePopState(e) {
	var href = document.location.href;

	if (typeof e !== 'undefined' && e.state != 'undefined' && e.state != null) {
		if (typeof e.state.Options != 'undefined' && e.state.Options != null) {
			loadUrl(e.state.Options);
			return false;
		} else if (typeof e.state.Url != 'undefined' && e.state.Url != null) {
			loadUrl({ url: href });
			return false;
		}
	}
	return true;
}

function applyGraphTimespan() {
	var href = correctUrlParameters(graphPage + '?action=' + pageAction +
		'&predefined_timespan=' + $('#predefined_timespan').val() +
		($('#rfilter').length ? '&rfilter=' + base64_encode($('#rfilter').val()) : '') +
		'&predefined_timeshift=' + $('#predefined_timeshift').val());

	closeDateFilters();

	loadUrl({ url: href });
}

function refreshGraphTimespanFilter() {
	postUrl({
		url: correctUrlParameters(graphPage + '?action=' + pageAction),
		funcEnd: 'finalizeGraphFilter',
	}, {
		custom: 1,
		button_refresh_x: 1,
		date1: $('#date1').val(),
		rfilter: base64_encode($('#rfilter').val()),
		date2: $('#date2').val(),
		predefined_timespan: $('#predefined_timespan').val(),
		predefined_timeshift: $('#predefined_timeshift').val(),
		__csrf_magic: csrfMagicToken
	});
}

function timeshiftGraphFilterLeft() {
	postUrl({
		url: correctUrlParameters(graphPage + '?action=' + pageAction),
		funcEnd: 'finalizeGraphFilter',
	}, {
		move_left_x: 1,
		move_left_y: 1,
		date1: $('#date1').val(),
		date2: $('#date2').val(),
		predefined_timespan: $('#predefined_timespan').val(),
		predefined_timeshift: $('#predefined_timeshift').val(),
		__csrf_magic: csrfMagicToken
	});
}

function timeshiftGraphFilterRight() {
	postUrl({
		url: correctUrlParameters(graphPage + '?action=' + pageAction),
		funcEnd: 'finalizeGraphFilter',
	}, {
		move_right_x: 1,
		move_right_y: 1,
		date1: $('#date1').val(),
		date2: $('#date2').val(),
		predefined_timespan: $('#predefined_timespan').val(),
		predefined_timeshift: $('#predefined_timeshift').val(),
		__csrf_magic: csrfMagicToken
	});
}

function clearGraphTimespanFilter() {
	postUrl({
		url: correctUrlParameters(graphPage + '?action=' + pageAction),
		funcEnd: 'finalizeGraphFilter',
	}, {
		button_clear: 1,
		date1: $('#date1').val(),
		date2: $('#date2').val(),
		predefined_timespan: $('#predefined_timespan').val(),
		predefined_timeshift: $('#predefined_timeshift').val(),
		__csrf_magic: csrfMagicToken
	});
}

function finalizeSpikeKillResults(options, data) {
	redrawGraph(local_graph_id);
	$('#spikeresults').remove();
	$('body').append('<div id="spikeresults" style="overflow-y:scroll;" title="' + spikeKillResults + '"></div>');
	$('#spikeresults').html(data.results);
	$('#spikeresults').dialog({ width: 1100, maxHeight: 600 });
}

function removeSpikesStdDev(local_graph_id) {
	var href = urlPath + 'spikekill.php' +
		'?method=stddev' +
		'&local_graph_id=' + local_graph_id;

	loadUrl({
		url: href,
		funcEnd: 'finalizeSpikeKillResults',
	});
}

function removeSpikesVariance(local_graph_id) {
	var href = urlPath + 'spikekill.php' +
		'?method=variance' +
		'&local_graph_id=' + local_graph_id;

	loadUrl({
		url: href,
		funcEnd: 'finalizeSpikeKillResults',
	});
}

function removeSpikesInRange(local_graph_id) {
	var href = urlPath + 'spikekill.php' +
		'?method=fill' +
		'&local_graph_id=' + local_graph_id +
		'&outlier-start=' + graph_start +
		'&outlier-end=' + graph_end;

	loadUrl({
		url: href,
		funcEnd: 'finalizeSpikeKillResults',
	});
}

function removeRangeFill(local_graph_id) {
	var href = urlPath + 'spikekill.php' +
		'?method=float' +
		'&local_graph_id=' + local_graph_id +
		'&outlier-start=' + graph_start +
		'&outlier-end=' + graph_end;

	loadUrl({
		url: href,
		funcEnd: 'finalizeSpikeKillResults',
	});
}

function removeSpikesAbsolute(local_graph_id) {
	var strURL = urlPath + 'spikekill.php?method=absolute&local_graph_id=' + local_graph_id;

	loadUrl({
		url: href,
		funcEnd: 'finalizeSpikeKillResults',
	});
}

function dryRunStdDev(local_graph_id) {
	var href = urlPath + 'spikekill.php' +
		'?method=stddev' +
		'&dryrun=true' +
		'&local_graph_id=' + local_graph_id;

	loadUrl({
		url: href,
		funcEnd: 'finalizeSpikeKillResults',
	});
}

function dryRunVariance(local_graph_id) {
	var href = urlPath + 'spikekill.php' +
		'?method=variance' +
		'&dryrun=true' +
		'&local_graph_id=' + local_graph_id;

	loadUrl({
		url: href,
		funcEnd: 'finalizeSpikeKillResults',
	});
}

function dryRunSpikesInRange(local_graph_id) {
	var href = urlPath + 'spikekill.php' +
		'?method=fill' +
		'&dryrun=true' +
		'&local_graph_id=' + local_graph_id +
		'&outlier-start=' + graph_start +
		'&outlier-end=' + graph_end;

	closeDateFilters();

	loadUrl({
		url: href,
		funcEnd: 'finalizeSpikeKillResults',
	});
}

function dryRunRangeFill(local_graph_id) {
	var href = urlPath + 'spikekill.php' +
		'?method=float' +
		'&dryrun=true' +
		'&local_graph_id=' + local_graph_id +
		'&outlier-start=' + graph_start +
		'&outlier-end=' + graph_end;

	loadUrl({
		url: href,
		funcEnd: 'finalizeSpikeKillResults',
	});
}

function dryRunAbsolute(local_graph_id) {
	var href = urlPath + 'spikekill.php?method=absolute&dryrun=true&local_graph_id=' + local_graph_id;

	loadUrl({
		url: href,
		funcEnd: 'finalizeSpikeKillResults',
	});
}

function redrawGraph(graph_id) {
	var mainWidth = getMainWidth() - 30;
	var isThumb = $('#thumbnails').is(':checked');
	var myColumns = $('#columns').val();
	var graphRow = $('.tableRowGraph').width();
	var drillDown = $('.graphDrillDown:first').outerWidth() + 10;

	if (mainWidth < graphRow) {
		graphRow = mainWidth - drillDown;
	} else if (graphRow == 0) {
		graphRow = mainWidth;
	}

	var myWidth = (graphRow - (drillDown * myColumns)) / myColumns;

	var graph_height = $('#wrapper_' + graph_id).attr('graph_height');
	var graph_width = $('#wrapper_' + graph_id).attr('graph_width');

	closeDateFilters();

	var href = 'graph_json.php?rra_id=0' +
		'&local_graph_id=' + graph_id +
		'&graph_start=' + graph_start +
		'&graph_end=' + graph_end +
		'&graph_height=' + graph_height +
		'&graph_width=' + graph_width +
		(isThumb ? '&graph_nolegend=true' : '');

	loadUrl({
		url: href,
		funcEnd: 'finalizeGraphRedraw',
	});
}

function finalizeGraphRedraw(options, data) {
	if (typeof data.status == 'undefined') {
		if (myWidth < data.image_width) {
			ratio = myWidth / data.image_width;
			data.image_width = parseInt(data.image_width * ratio);
			data.image_height = parseInt(data.image_height * ratio);
			data.graph_width = parseInt(data.graph_width * ratio);
			data.graph_height = parseInt(data.graph_height * ratio);
			data.graph_top = parseInt(data.graph_top * ratio);
			data.graph_left = parseInt(data.graph_left * ratio);
		}

		$('#wrapper_' + data.local_graph_id).empty().html(
			"<img class='graphimage' id='graph_" + data.local_graph_id + "'" +
			" src='data:image/" + data.type + ";base64," + data.image + "'" +
			" rra_id='" + data.rra_id + "'" +
			" graph_type='" + data.type + "'" +
			" graph_id='" + data.local_graph_id + "'" +
			" graph_start='" + data.graph_start + "'" +
			" graph_end='" + data.graph_end + "'" +
			" graph_left='" + data.graph_left + "'" +
			" graph_top='" + data.graph_top + "'" +
			" graph_width='" + data.graph_width + "'" +
			" graph_height='" + data.graph_height + "'" +
			" width='" + data.image_width + "'" +
			" height='" + data.image_height + "'" +
			" image_width='" + data.image_width + "'" +
			" image_height='" + data.image_height + "'" +
			" canvas_top='" + data.graph_top + "'" +
			" canvas_left='" + data.graph_left + "'" +
			" canvas_width='" + data.graph_width + "'" +
			" canvas_height='" + data.graph_height + "'" +
			" value_min='" + data.value_min + "'" +
			" value_max='" + data.value_max + "'>"
		);

		$('#graph_' + data.local_graph_id).zoom({
			inputfieldStartTime: 'date1',
			inputfieldEndTime: 'date2',
			serverTimeOffset: timeOffset
		});

		data = undefined;
	} else {
		getPresentHTTPError(data);
	}
}

function initializeGraphs(disable_cache) {
	disable_cache = (typeof disable_cache == 'undefined') ? false : true;

	$.ajaxQ.abortAll();

	$('a[id$="_mrtg"]').each(function () {
		var graph_id = $(this).attr('id').replace('graph_', '').replace('_mrtg', '');

		$(this).attr('href', urlPath + 'graph.php?local_graph_id=' + graph_id);

		$(this).off('click').on('click', function (event) {
			if (!shouldCaptureClick(event))
				return;

			var graph_id = $(this).attr('id').replace('graph_', '').replace('_mrtg', '');

			event.preventDefault();
			event.stopPropagation();

			loadUrl({ url: urlPath + 'graph.php?local_graph_id=' + graph_id });
		});
	});

	// Get these outside of the each to reduce calls
	var timestampDate1 = getTimestampFromDate($('#date1').val());
	var timestampDate2 = getTimestampFromDate($('#date2').val());

	$('a[id$="_csv"]').each(function () {
		var graph_id = $(this).attr('id').replace('graph_', '').replace('_csv', '');

		// Disable context menu
		$(this).children().contextmenu(function () {
			return false;
		});

		var url = urlPath +
			'graph_xport.php' +
			'?local_graph_id=' + graph_id +
			'&rra_id=0' +
			'&view_type=tree' +
			'&graph_start=' + timestampDate1 +
			'&graph_end=' + timestampDate2;

		$(this).attr('href', url);

		$(this).off('click').on('click', function (event) {
			if (!shouldCaptureClick(event))
				return;

			var graph_id = $(this).attr('id').replace('graph_', '').replace('_csv', '');
			event.preventDefault();
			event.stopPropagation();

			document.location = urlPath +
				'graph_xport.php' +
				'?local_graph_id=' + graph_id +
				'&rra_id=0' +
				'&view_type=tree' +
				'&graph_start=' + timestampDate1 +
				'&graph_end=' + timestampDate2;

			Pace.stop();
		});
	});

	$('#form_graph_view').off('submit').on('submit', function (event) {
		event.preventDefault();
		event.stopPropagation();
		applyFilter();
	});

	var mainWidth = getMainWidth() - 30;
	var myColumns = $('#columns').val();
	var isThumb = $('#thumbnails').is(':checked');
	var myWidth = (mainWidth - (30 * myColumns)) / myColumns;
	var numGraphs = $('.graphWrapper').length;

	$('.graphWrapper').each(function () {
		var graph_id = $(this).attr('graph_id');
		if (!(graph_id > 0)) {
			graph_id = $(this).attr('id').replace('wrapper_', '');
		}

		var rra_id = $(this).attr('rra_id');
		if (!(rra_id > 0)) {
			rra_id = 0;
		}

		var graph_height = $(this).attr('graph_height');
		var graph_width  = $(this).attr('graph_width');
		var error_url    = urlPath + 'graph_view.php';

		closeDateFilters();

		var url = urlPath + 'graph_json.php' +
			'?rra_id=' + rra_id +
			'&local_graph_id=' + graph_id +
			'&graph_start=' + graph_start +
			'&graph_end=' + graph_end +
			'&graph_height=' + graph_height +
			'&graph_width=' + graph_width +
			(disable_cache ? '&disable_cache=true' : '') +
			(isThumb ? '&graph_nolegend=true' : '');

		$.getJSON(url)
			.done(function (data) {
				if (myWidth < data.image_width) {
					ratio = myWidth / data.image_width;

					data.image_width = parseInt(data.image_width * ratio);
					data.image_height = parseInt(data.image_height * ratio);
					data.graph_width = parseInt(data.graph_width * ratio);
					data.graph_height = parseInt(data.graph_height * ratio);
					data.graph_top = parseInt(data.graph_top * ratio);
					data.graph_left = parseInt(data.graph_left * ratio);
				}

				var wrapper_id = '#wrapper_' + data.local_graph_id;

				if (rra_id > 0) {
					wrapper_id += '[rra_id=\'' + data.rra_id + '\']';
				}

				$(wrapper_id).empty().html(
					"<img class='graphimage' id='graph_" + data.local_graph_id + "'" +
					" src='data:image/" + data.type + ";base64," + data.image + "'" +
					" rra_id='" + data.rra_id + "'" +
					" graph_type='" + data.type + "'" +
					" graph_id='" + data.local_graph_id + "'" +
					" graph_start='" + data.graph_start + "'" +
					" graph_end='" + data.graph_end + "'" +
					" graph_left='" + data.graph_left + "'" +
					" graph_top='" + data.graph_top + "'" +
					" graph_width='" + data.graph_width + "'" +
					" graph_height='" + data.graph_height + "'" +
					" width='" + data.image_width + "'" +
					" height='" + data.image_height + "'" +
					" image_width='" + data.image_width + "'" +
					" image_height='" + data.image_height + "'" +
					" canvas_top='" + data.graph_top + "'" +
					" canvas_left='" + data.graph_left + "'" +
					" canvas_width='" + data.graph_width + "'" +
					" canvas_height='" + data.graph_height + "'" +
					" value_min='" + data.value_min + "'" +
					" value_max='" + data.value_max + "'>"
				);

				var graph_id = '#graph_' + data.local_graph_id;
				if (rra_id > 0) {
					graph_id += '[rra_id=\'' + data.rra_id + '\']';
				}

				$(graph_id).zoom({
					inputfieldStartTime: 'date1',
					inputfieldEndTime: 'date2',
					serverTimeOffset: timeOffset
				});

				if (typeof realtimeArray != 'undefined') {
					realtimeArray[data.local_graph_id] = false;
				}

				if (!--numGraphs) {
					responsiveResizeGraphs();
				}

				data = undefined;
			})
			.fail(function (data) {
				getPresentHTTPErrorOrRedirect(data, error_url);
			});
	});

	$('#realtimeoff').off('click').on('click', function () {
		stopRealtime();
	});

	$('#ds_step').off('change').on('change', function () {
		realtimeGrapher();
	});

	$('a[id$="_util"]').each(function () {
		var graph_id = $(this).attr('id').replace('graph_', '').replace('_util', '');

		$(this).attr('href', urlPath +
			'graph.php' +
			'?action=zoom' +
			'&rra_id=0' +
			'&local_graph_id=' + graph_id +
			'&graph_start=' + timestampDate1 +
			'&graph_end=' + timestampDate2);

		$(this).off('click').on('click', function (event) {
			if (!shouldCaptureClick(event))
				return;

			var graph_id = $(this).attr('id').replace('graph_', '').replace('_util', '');
			var graph_url = urlPath + 'graph.php?action=zoom&local_graph_id=' + graph_id + '&rra_id=0&graph_start=' + getTimestampFromDate($('#date1').val()) + '&graph_end=' + getTimestampFromDate($('#date2').val());

			event.preventDefault();
			event.stopPropagation();

			loadUrl({ url: graph_url });
		});
	});

	$('a[id$="_realtime"]').each(function () {
		// Disable right click
		$(this).children().on('contextmenu', function (event) {
			return false;
		});

		$(this).off('click').on('click', function (event) {
			if (!shouldCaptureClick(event))
				return;

			var graph_id = $(this).attr('id').replace('graph_', '').replace('_realtime', '');

			event.preventDefault();
			event.stopPropagation();

			if (realtimeArray[graph_id]) {
				$('#wrapper_' + graph_id).html(keepRealtime[graph_id]).change();
				$(this).html("<img class='drillDown' title='" + realtimeClickOn + "' alt='' src='" + urlPath + "images/chart_curve_go.png'>");

				$('graph_id' + graph_id).tooltip().zoom({
					inputfieldStartTime: 'date1',
					inputfieldEndTime: 'date2',
					serverTimeOffset: timeOffset
				});

				realtimeArray[graph_id] = false;
				setFilters();
			} else {
				keepRealtime[graph_id] = $('#wrapper_' + graph_id).html();
				$(this).html("<i style='text-align:center;padding:0px;' title='" + realtimeClickOff + "' class='drillDown fa fa-circle-notch fa-spin'/>");
				$(this).find('i').tooltip();
				realtimeArray[graph_id] = true;
				setFilters();
				realtimeGrapher();
			}
		});
	});
}

$.widget('custom.languageselect', $.ui.selectmenu, {
	_renderItem: function (ul, item) {
		var li = $('<li>');
		var wrapper = $('<div>', { text: item.label });
		if (item.disabled) {
			li.addClass('ui-state-disabled');
		}

		$('<span>', {
			style: item.element.attr('data-style') + ';float:right',
			'class': 'right fi fis ' + item.element.attr('data-class')
		}).appendTo(wrapper);

		return li.append(wrapper).appendTo(ul);
	}
});

// combobox example borrowed from jqueryui
$.widget('custom.dropcolor', {
	_create: function () {
		this.wrapper = $('<span style="display:inline-flex"><span class="ui-select-text"><div id="bgc" class="ui-icon color-icon" style="margin-left:2px;margin-right:3px;"></div></span></span>')
			.addClass('ui-selectmenu-button ui-selectmenu-button-closed ui-corner-all ui-button ui-widget')
			.insertAfter(this.element);

		this.element.hide();
		this._createAutocomplete();
		this._createShowAllButton();
	},

	_createAutocomplete: function () {
		var selected = this.element.children(':selected');
		var value = selected.val() ? selected.text() : '';
		var regExp = /\(([^)]+)\)/;
		var hex = regExp.exec(value);

		if (hex != null) {
			this.wrapper.find('#bgc').css('background-color', '#' + hex[1]);
		}
		this.input = $('<input class="ui-autocomplete-input ui-state-default ui-selectmenu-text" style="background:transparent;border:0px;padding:0px;padding-left:24px;margin-left:-24px" value="' + value + '">')
			.appendTo(this.wrapper)
			.on('click', function () {
				$(this).autocomplete('search', '');
			})
			.autocomplete({
				delay: 0,
				minLength: 0,
				source: $.proxy(this, '_source'),
				select: $.proxy(this, '_select'),
				search: function () {
					$(this).data('ui-autocomplete').menu.bindings = $();
				},
				close: function () {
					$(this).data('ui-autocomplete').menu.bindings = $();
				},
				create: function () {
					$(this).data('ui-autocomplete')._renderItem = function (ul, item) {
						var regExp = /\(([^)]+)\)/;
						var hex = regExp.exec(item.label);
						var mylabel = $($.parseHTML(item.label));
						var label = mylabel.text();

						if (hex !== null) {
							color = hex[1];
							return $('<li>').attr('data-value', item.value).html('<div><span style="background-color:#' + color + ';" class="ui-icon color-icon"></span>' + label + '</div>').appendTo(ul);
						} else {
							return $('<li>').attr('data-value', item.value).html('<div><span class="ui-icon color-icon"></span>' + label + '</div>').appendTo(ul);
						}
					}

					$(this).data('ui-autocomplete')._resizeMenu = function () {
						var ul = this.menu.element;
						ul.outerWidth('220px');
					}
				}
			});

		this._on(this.input, {
			autocompleteselect: function (event, ui) {
				ui.item.option.selected = true;
				this._trigger('select', event, {
					item: ui.item.option
				});
			},

			autocompletechange: '_removeIfInvalid'
		});
	},

	_select: function (event, ui) {
		var regExp = /\(([^)]+)\)/;
		var hex = regExp.exec(ui.item.label);
		var id = $(ui.item.option).attr('value');

		if (hex !== null) {
			color = hex[1];
			this.wrapper.find('#bgc').css('background-color', '#' + color);
			this.wrapper.find('input').val(ui.item.value);
		} else {
			this.wrapper.find('#bgc').css('background-color', '');
			this.wrapper.find('input').val(ui.item.value);
		}
	},

	_createShowAllButton: function () {
		var input = this.input;
		var wasOpen = false;

		$('<span>')
			.attr('tabIndex', -1)
			.appendTo(this.wrapper)
			.addClass('ui-icon ui-icon-triangle-1-s ui-selectmenu-icon')
			.on('mousedown', function () {
				wasOpen = input.autocomplete('widget').is(':visible');
			})
			.on('click', function () {
				input.trigger('focus');

				// Close if already visible
				if (wasOpen) {
					return;
				}

				input.autocomplete('search', '');
			});
	},

	_source: function (request, response) {
		var matcher = new RegExp($.ui.autocomplete.escapeRegex(request.term), 'i');
		results = this.element.children('option').map(function () {
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

	_removeIfInvalid: function (event, ui) {
		// Selected an item, nothing to do
		if (ui.item) {
			return;
		}

		// Search for a match (case-insensitive)
		var value = this.input.val();
		var valueLowerCase = value.toLowerCase();
		var valid = false;

		this.element.children('option').each(function () {
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
		this._delay(function () {
			this.input.tooltip('close').attr('title', '');
		}, 2500);
		this.input.autocomplete('instance').term = '';
	},

	_destroy: function () {
		this.wrapper.remove();
		this.element.show();
	}
});

function makeCallbacks() {
	var cn = 0;

	$('.drop-callback').each(function() {
		var title    = searchSelect;
		var action   = $(this).attr('data-action');
		var value    = $(this).attr('data-value');

		var Id        = $(this).attr('id');
		var dcId      = '#' + Id;
		var dcWrap    = dcId + '_wrap';
		var dcWrapId  = Id + '_wrap';
		var dcClickId = Id + '_click';
		var dcInput   = dcId + '_input';
		var dcInputId = Id + '_input';

		var dcInputFields = 'input' + dcId + '_input';

		$(dcId).attr('data-callback-id', cn);

		if ($(dcWrap).length) {
			$(dcWrap).remove();
		}

		if (value == '') {
			value = title;
		}

		var dialogForm = "<span id='" + dcWrapId + "' class='ui-selectmenu-button ui-selectmenu-button-closed ui-corner-all ui-button ui-widget'>";
		dialogForm    += "<span id='" + dcClickId + "' style='z-index:4' class='ui-selectmenu-icon ui-icon ui-icon-triangle-1-s'></span>";
		dialogForm    += "<span class='ui-select-text'>";
		dialogForm    += "<input type='text' class='ui-state-default ui-corner-all' id='" + dcInputId + "' value='" + value + "'>";
		dialogForm    += "</span>";
		dialogForm    += "</span>&nbsp;";

		$(this).after(dialogForm);
		$(this).hide();

		$(dcInput).autocomplete({
			source: pageName + '?action=' + action,
			autoFocus: true,
			minLength: 0,
			select: function(event, ui) {
				var callBack = $(dcId).attr('data-callback');

				$(dcInput).val(ui.item.label);

				if (ui.item.id) {
					$(dcId).val(ui.item.id);
				} else {
					$(dcId).val(ui.item.value);
				}

				if (callBack != '') {
					callBack = callBack.replace('(', '').replace(')', '');

					executeFunctionByName(callBack, window);
				}
			},
			open: function(event, ui) {
				$('.ui-dialog').css('z-index', '20');
				$(this).css('z-index', '5000');
			},
			close: function(event, ui) {
				var cn = $(dcId).attr('data-callback-id');
				callbackOpen[cn] = false;
				clearTimeout(callbackTimer[cn]);
			}
		}).css('border', 'none').css('background-color', 'transparent');

		$(dcWrap).on('dblclick', function() {
			var cn = $(dcId).attr('data-callback-id');

			callbackOpen[cn] = false;

			clearTimeout(callbackTimer[cn]);

			clearTimeout(callbackClickTimer[cn]);
			$(dcInput).autocomplete('close').select();
		}).on('click', function() {
			var cn = $(dcId).attr('data-callback-id');

			if (callbackOpen[cn]) {
				$(dcInput).autocomplete('close');
				clearTimeout(callbackTimer[cn]);
				callbackOpen[cn] = false;
			} else {
				callbackClickTimer[cn] = setTimeout(function() {
					var cn = $(dcId).attr('data-callback-id');
					$(dcInput).autocomplete('search', '');

					clearTimeout(callbackTimer[cn]);
					callbackOpen[cn] = true;
				}, 200);
			}
			$(dcInput).select();
		}).on('mouseleave', function() {
			var cn = $(dcId).attr('data-callback-id');

			callbackTimer[cn] = setTimeout(function() { $(dcInput).autocomplete('close'); }, 800);
		});

		var width = $(dcInput).textBoxWidth();
		if (width < 200) {
			width = 200;
		}

		$(dcWrap).css('width', width+20);
		$(dcInput).css('width', width);
		$(dcWrap).find('.ui-select-text').css('width', width);

		$('ul[id^="ui-id"]').on('mouseenter', function() {
			var cn = $(dcId).attr('data-callback-id');
			clearTimeout(callbackTimer[cn]);
		}).on('mouseleave', function() {
			var cn = $(dcId).attr('data-callback-id');
			callbackTimer[cn] = setTimeout(function() {
				$(dcInput).autocomplete('close');
			}, 800);
		});

		$('ul[id^="ui-id"] > li').on('mouseenter', function() {
			$(this).addClass('ui-state-hover');
		}).on('mouseleave', function() {
			$(this).removeClass('ui-state-hover');
		});

		$(dcWrap).on('mouseenter', function() {
			$(this).addClass('ui-state-hover');
			$('input#' + id + '_input').addClass('ui-state-hover');
		}).on('mouseleave', function() {
			$(this).removeClass('ui-state-hover');
			$(dcInputFields).removeClass('ui-state-hover');
		});

		cn++;
	});
}

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
	var clipboardDataId = containerId.replace('copyToClipboard', 'clipboardData');
	var clipboardData = document.getElementById(clipboardDataId);

	var messageWidth = $(window).width();
	if (messageWidth > 350) {
		messageWidth = 350;
	} else {
		messageWidth -= 50;
	}

	if (clipboardData == null) {
		$('body').append('<div style="display:none;" id="clipboardMessage" title="' + clipboard + '">' + clipboardCopyFailed + '<br/><br/>' + clipboardID + ': ' + clipboardDataId + '</div>');

		$('#clipboardMessage').dialog({
			resizable: false,
			draggable: false,
			height: 170,
			width: messageWidth,
			buttons: {
				Ok: function () {
					$(this).dialog('close');
					$('#clipboardMessage').remove();
				}
			}
		});

		$('#clipboardMessage').dialog('open');
	} else if (!document.queryCommandSupported('copy')) {
		$('body').append('<div style="display:none;" id="clipboardMessage" title="' + clipboard + '">' + clipboardNotAvailable + '</div>');

		$('#clipboardMessage').dialog({
			resizable: false,
			draggable: false,
			height: 120,
			width: messageWidth,
			buttons: {
				Ok: function () {
					$(this).dialog('close');
					$('#clipboardMessage').remove();
				}
			}
		});

		$('#clipboardMessage').dialog('open');
	} else {
		var clipboardHeaderId = containerId.replace('copyToClipboard', 'clipboardHeader');
		var clipboardHeader = document.getElementById(clipboardHeaderId);

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

		$('body').append('<div style="display:none;" id="clipboardMessage" title="' + clipboard + '">' + successMessage + '</div>');

		$('#clipboardMessage').dialog({
			resizable: false,
			draggable: false,
			height: 120,
			width: messageWidth,
			buttons: {
				Ok: function () {
					$(this).dialog('close');
					$('#clipboardMessage').remove();
				}
			}
		});

		$('#clipboardMessage').dialog('open');
	}
}

var snmp_password = '';
var snmp_auth_protocol = '';
var snmp_priv_protocol = '';
var snmp_priv_passphrase = '';
var snmp_security_initialized = false;

function storeSNMPSecurity() {
	if ($('#snmp_version').val() == '3') {
		if ($('#snmp_auth_protocol').val() != '[None]') {
			snmp_auth_protocol = $('#snmp_auth_protocol').val();
			snmp_password = $('#snmp_password').val();
		} else {
			snmp_auth_protocol = '';
			snmp_password = '';
		}

		if ($('#snmp_priv_protocol').val() != '[None]') {
			snmp_priv_protocol = $('#snmp_priv_protocol').val();
			snmp_priv_passphrase = $('#snmp_priv_passphrase').val();
		} else {
			snmp_priv_protocol = '';
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

			$('#snmp_password').delayKeyup(function () {
				checkSNMPPassphrase('auth');
			});

			$('#snmp_password_confirm').delayKeyup(function () {
				checkSNMPPassphraseConfirm('auth');
			});

			$('#snmp_priv_passphrase').delayKeyup(function () {
				checkSNMPPassphrase('priv');
			});

			$('#snmp_priv_passphrase_confirm').delayKeyup(function () {
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

	let snmp_advanced = snmp_version > 2;
	let snmp_basic = snmp_version > 0 && !snmp_advanced;
	let snmp_none = !snmp_advanced && !snmp_basic;

	if (snmp_version == 3) {
		if ($('#snmp_security_level').val() == 'noAuthNoPriv') {
			$('#snmp_auth_protocol option[value*="None"').prop('disabled', false);
			$('#snmp_priv_protocol option[value*="None"').prop('disabled', false);

			if ($('#snmp_auth_protocol').val() != '[None]') {
				snmp_auth_protocol = $('#snmp_auth_protocol').val();
				snmp_password = $('#snmp_password').val();
			}

			if ($('#snmp_priv_protocol').val() != '[None]') {
				snmp_priv_protocol = $('#snmp_priv_protocol').val();
				snmp_priv_passphrase = $('#snmp_priv_passphrase').val();
			}

			$('#snmp_auth_protocol').val('[None]');
			$('#snmp_priv_protocol').val('[None]');
		} else if ($('#snmp_security_level').val() == 'authNoPriv') {
			$('#snmp_auth_protocol option[value*="None"').prop('disabled', false);
			$('#snmp_priv_protocol option[value*="None"').prop('disabled', false);

			if ($('#snmp_priv_protocol').val() != '[None]') {
				snmp_priv_protocol = $('#snmp_priv_protocol').val();
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
				$('#snmp_priv_passphrase_confirm').val(snmp_priv_passphrase);
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
			$('#snmp_password').val('');
			$('#snmp_password_confirm').val('');
		}

		if ($('#snmp_priv_protocol').val() == '[None]') {
			$('#snmp_priv_passphrase').val('');
			$('#snmp_priv_passphrase_confirm').val('');
		}
	}


	var canSnmpAuth = !$('#snmp_auth_protocol').is(':disabled');
	var canSnmpPriv = !$('#snmp_priv_protocol').is(':disabled');
	var canSnmpAuthPass = $('#snmp_auth_protocol').val() != '[None]';
	var canSnmpPrivPass = $('#snmp_priv_protocol').val() != '[None]';

	toggleFields({
		snmp_username: snmp_advanced && canSnmpAuthPass,
		snmp_password: snmp_advanced && canSnmpAuthPass,
		snmp_community: snmp_basic,
		snmp_security_level: snmp_advanced && canSnmpAuth,
		snmp_auth_password: snmp_advanced && canSnmpAuth && canSnmpAuthPass,
		snmp_auth_protocol: snmp_advanced && canSnmpAuth && canSnmpAuthPass,
		snmp_priv_passphrase: snmp_advanced && canSnmpPriv && canSnmpPrivPass,
		snmp_priv_protocol: snmp_advanced && canSnmpPriv && canSnmpAuthPass,
		snmp_engine_id: snmp_advanced,
		snmp_context: snmp_advanced,
		snmp_port: snmp_basic || snmp_advanced,
		snmp_timeout: snmp_basic || snmp_advanced,
		snmp_retries: snmp_basic || snmp_advanced,
		max_oids: snmp_basic || snmp_advanced,
		bulk_walk_size: snmp_basic || snmp_advanced,
	});

	selectmenu = ($('#snmp_security_level').selectmenu('instance') !== undefined);
	if (selectmenu) {
		$('#snmp_security_level').selectmenu('refresh');
		$('#snmp_auth_protocol').selectmenu('refresh');
		$('#snmp_priv_protocol').selectmenu('refresh');
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
		$('#' + span).remove();
		$('#' + span + 'conf').remove();
	} else if ($(pass).val().length < minChars) {
		$('#' + span).remove();
		$(pass).after('<span id="' + span + '"><i class="badpassword fa fa-times"></i><span style="padding-left:4px;">' + passwordTooShort + '<span></span>');
		checkSNMPPassphraseConfirm(type);
	} else {
		$('#' + span).remove();
		$(pass).after('<span id="' + span + '"><i class="goodpassword fa fa-check"></i><span style="padding-left:4px;">' + passwordPass + '</span></span>');
		checkSNMPPassphraseConfirm(type);
	}
}

function checkSNMPPassphraseConfirm(type) {
	var minChars = 8;

	if (type == 'priv') {
		var pass = '#snmp_priv_passphrase';
		var conf = '#snmp_priv_passphrase_confirm';
		var span = 'priv';
		var spanconf = 'privconf';
	} else {
		var pass = '#snmp_password';
		var conf = '#snmp_password_confirm';
		var span = 'auth';
		var spanconf = 'authconf';
	}

	if ($(conf).val().length < minChars) {
		var passphrase = $(pass).val();

		if (passphrase.indexOf($(conf).val()) == 0) {
			$('#' + spanconf).remove();
			$(conf).after('<span id="' + spanconf + '"><i class="badpassword fa fa-times"></i><span style="padding-left:4px;">' + passwordMatchTooShort + '<span></span>');
		} else {
			$('#' + spanconf).remove();
			$(conf).after('<span id="' + spanconf + '"><i class="badpassword fa fa-times"></i><span style="padding-left:4px;">' + passwordNotMatchTooShort + '<span></span>');
		}
	} else {
		if ($(pass).val() != $(conf).val()) {
			$('#' + spanconf).remove();
			$(conf).after('<span id="' + spanconf + '"><i class="badpassword fa fa-times"></i><span style="padding-left:4px;">' + passwordNotMatch + '</span></span>');
		} else {
			$('#' + span).remove();
			$('#' + spanconf).remove();
			$(pass).after('<span id="' + spanconf + '"><i class="goodpassword fa fa-check"></i><span style="padding-left:4px;">' + passwordMatch + '</span></span>');
		}
	}
}

// 0 - None
// 1 - Ping and SNMP Uptime
// 4 - Ping or SNMP Uptime
// 2 - SNMP Uptime
// 5 - SNMP Desc
// 6 - SNMP GetNext
// 3 - Ping
const availabilityToggleOptions = ['1', '2', '4', '5', '6'];

function setAvailability() {
   var hasChanged = false;
	if ($('#snmp_version').val() == '0') {
		$('#availability_method').disableOptions(availabilityToggleOptions, function() {
			if ($('#availability_method').val() != '3' && $('#availability_method').val() != '0') {
				$('#availability_method').val('3');
			}
		});
	} else {
		$('#availability_method').enableOptions(availabilityToggleOptions);
	}

	var availability_method = $('#availability_method').val();
	var canPing = true;

	if (availability_method == 0 ||
		availability_method == 2 ||
		availability_method == 5 ||
		availability_method == 6) {
		$('#ping_method').val('1');
		canPing = false;
	}

	toggleFields({
		ping_method: availability_method != 1 && availability_method != 3 && availability_method != 4,
		ping_timeout: canPing,
		ping_port: canPing,
		ping_retries: canPing,
	})
}

function setPing() {
	var availability_method = $('#availability_method').val();
	var ping_method = $('#ping_method').val();
	var show_ping_port = (availability_method == 1 || availability_method == 4 || availability_method == 3) && ping_method != 1;
	var show_ping_method = availability_method == 1 || availability_method == 3 || availability_method == 4;

	toggleFields({
		ping_method: show_ping_method,
		ping_port: show_ping_port,
		ping_timeout: availability_method > 0,
		ping_retries: availability_method > 0
	});
}


(function ($) {
	$.textMetrics = function (el) {

		var h = 0, w = 0;

		var div = document.createElement('div');
		document.body.appendChild(div);
		$(div).css({
			position: 'absolute',
			left: -1000,
			top: -1000,
			display: 'none'
		});

		$(div).html($(el).html());
		var styles = ['font-size', 'font-style', 'font-weight', 'font-family', 'line-height', 'text-transform', 'letter-spacing'];
		$(styles).each(function () {
			var s = this.toString();
			$(div).css(s, $(el).css(s));
		});

		h = $(div).outerHeight();
		w = $(div).outerWidth();

		$(div).remove();

		var ret = {
			height: h,
			width: w
		};

		return ret;
	}
})(jQuery);

function formValidate(formId, href) {
	changed = false;

	var formObj = $(formId);
	if (formObj.length) {
		formArray[formId] = $(formObj).serializeForm();

		$(formObj).find('input[type="email"]').each(function () {
			var name = $(this).attr('id');
			if (name === null || name.length == 0) {
				name = $(this).attr('name');
			}

			if (name !== null && name.length > 0) {
				if (typeof formRules[formId] !== 'undefined') {
					formRules[formId][name] = {
						required: true,
						email: true,
					};
				}
			}
		});


		$(formObj).submit(function (event) {
			event.preventDefault();

			// Disable the submit button so it can't be done twice
			$(this).find('input, textarea, select').prop('disabled', false);
		}).validate({
			rules: formRules[formId],
			errorClass: 'txtErrorText',
			validClass: 'success',
			highlight: function (element, errorClass, validClass) {
				$(element).parents("div.formData").addClass("txtErrorTextBox"); //.addClass(errorClass).removeClass(validClass);
			},
			unhighlight: function (element, errorClass, validClass) {
				$(element).parents("div.formData").removeClass("txtErrorTextBox"); // .removeClass(errorClass).addClass(validClass);
			},
			submitHandler: function (form) {
				$('input[type="submit"], button[type="submit"]').not('.import, .export').button('disable');

				var json = $(form).serializeObject();
				postUrl({ url: href }, json).always(function () {
					$('input[type="submit"], button[type="submit"]').not('.import, .export').button('enable');
				});
			},
			invalidHandler: function (event, validator) {
				// 'this' refers to the form
				var errors = validator.numberOfInvalids();
				if (errors) {
					var message = errors == 1
						? 'You missed 1 field. It has been highlighted'
						: 'You missed ' + errors + ' fields. They have been highlighted';
					$("div.error span").html(message);
					$("div.error").show();
				} else {
					$("div.error").hide();
				}

				$('input[type="submit"], button[type="submit"]').not('.import, .export').button('enabled');
			}
		});
	}
}

function toggleFields(fields, prefix = '#row_') {
	Object.keys(fields).forEach(index => {
		var value = fields[index];
		if (value) {
			$(prefix + index).show();
		} else {
			$(prefix + index).hide();
		}
	});
}

function executeFunctionByName(functionName, context /*, args */) {
	var args       = Array.prototype.slice.call(arguments, 2);
	var namespaces = functionName.split('.');
	var func       = namespaces.pop();

	for(var i = 0; i < namespaces.length; i++) {
		context = context[namespaces[i]];
	}

	return context[func].apply(context, args);
}


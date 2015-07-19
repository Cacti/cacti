/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2015 The Cacti Group                                 |
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
var pulsating=true;

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
			$option = $("<option></option>")
			.attr("value", option.value)
			.prop("selected", true)
			.text(option.text);
		}else{
			$option = $("<option></option>")
			.attr("value", option.value)
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
		html.css("font-family", org.css("font-family"));
		html.css("font-weight", org.css("font-weight"));
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

	return this;
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
	// Apply disabled/enabled status first for Graph Templates
	$('tr[id^=gt_line]').each(function(data) {
		var id = $(this).attr('id');
		var search = id.substr(7);
		//console.log('The id is : '+id+', The search is : '+search);
		if ($.inArray(search, gt_created_graphs) >= 0) {
			//console.log('The id is : '+id+', The search is : '+search+', Result found');
			$(this).addClass('disabled');
			$(this).css('color', '#999999');
			$(this).find(':checkbox').prop('disabled', true);
		}
	});

	// Create Actions for Rows
	$('tr.selectable').find('td').not('.checkbox').each(function(data) {
		if (!$(this).parent().hasClass('disabled')) {
			$(this).click(function(data) {
				$(this).parent().toggleClass('selected');
				var $checkbox = $(this).parent().find(':checkbox');
				$checkbox.prop('checked', !$checkbox.is(':checked'));
			});
		}
	});

	// Create Actions for Checkboxes
	$('tr.selectable').find('.checkbox').click(function(data) {
		if (!$(this).is(':disabled')) {
			$(this).parent().toggleClass('selected');
			var checked = $(this).is(':checkbox');
			$(this).prop('checked', checked);
		}
	});
}

/** dqUpdateDeps - When a user changes the Graph dropdown for a data query
 *  we have to check to see if those graphs are already created.
 *  @arg snmp_query_id - The snmp query id the is current */
function dqUpdateDeps(snmp_query_id) {
	dqResetDeps(snmp_query_id);

	var snmp_query_graph_id = $('#sgg_'+snmp_query_id).val();

	// Next for Data Queries
	$('tr[id^=line'+snmp_query_id+'_]').each(function(data) {
		var id = $(this).attr('id');
		var pieces = id.split('_');
		var dq = pieces[0].substr(4);
		var hash = pieces[1];

		if ($.inArray(hash, created_graphs[snmp_query_graph_id]) >= 0) {
			$(this).addClass('disabled');
			$(this).find(':checkbox').prop('disabled', true);
		}
	});
}

/** dqResetDeps - This function will make all rows selectable.
 *  It is done just before a new data query is checked.
 *  @arg snmp_query_id - The snmp query id the is current */
function dqResetDeps(snmp_query_id) {
	var prefix = 'sg_' + snmp_query_id + '_'

	$('tr[id^=line'+snmp_query_id+'_]').removeClass('disabled').removeClass('selected').find(':checkbox').prop('disabled', false);
}

/** SelectAll - This function will select all non-disabled rows
 *  @arg attrib - The Graph Type either graph template, or data query */
function SelectAll(attrib, checked) {
	if (attrib == 'sg') {
		if (checked == true) {
			$('tr[id^=gt_line]').each(function(data) {
				if (!$(this).hasClass('disabled')) {
					$(this).addClass('selected');
					$(this).find(':checkbox').prop('checked', true);
				}
			});
		}else{
			$('tr[id^=gt_line]').each(function(data) {
				if (!$(this).hasClass('disabled')) {
					$(this).removeClass('selected');
					$(this).find(':checkbox').prop('checked', false);
				}
			});
		}
	}else{
		var attribSplit = attrib.split('_');
		var dq   = attribSplit[1];

		if (checked == true) {
			$('tr[id^=line'+dq+'_]').each(function(data) {
				if (!$(this).hasClass('disabled')) {
					$(this).addClass('selected');
					$(this).find(':checkbox').prop('checked', true);
				}
			});
		}else{
			$('tr[id^=line'+dq+'_]').each(function(data) {
				if (!$(this).hasClass('disabled')) {
					$(this).removeClass('selected');
					$(this).find(':checkbox').prop('checked', false);
				}
			});
		}
	}
}

/* calendar stuff */
// Initialize the calendar
var calendar=null;

// This function displays the calendar associated to the input field 'id'
function showCalendar(id) {
	var el = document.getElementById(id);
	if (calendar != null) {
		// we already have some calendar created
		calendar.hide();  // so we hide it first.
	} else {
		// first-time call, create the calendar.
		var cal = new Calendar(true, null, selected, closeHandler);
		cal.weekNumbers = false;  // Do not display the week number
		cal.showsTime = true;     // Display the time
		cal.time24 = true;        // Hours have a 24 hours format
		cal.showsOtherMonths = false;    // Just the current month is displayed
		calendar = cal;                  // remember it in the global var
		cal.setRange(1900, 2070);        // min/max year allowed.
		cal.create();
	}

	calendar.setDateFormat('%Y-%m-%d %H:%M');    // set the specified date format
	calendar.parseDate(el.value);                // try to parse the text in field
	calendar.sel = el;                           // inform it what input field we use

	// Display the calendar below the input field
	calendar.showAtElement(el, 'Br');        // show the calendar

	return false;
}

// This function update the date in the input field when selected
function selected(cal, date) {
	cal.sel.value = date;      // just update the date in the input field.
}

// This function gets called when the end-user clicks on the 'Close' button.
// It just hides the calendar without destroying it.
function closeHandler(cal) {
	cal.hide();                        // hide the calendar
	calendar = null;
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
	}else{
		$('input[type=submit], input[type=button]').button();
	}

	// For deletable objects, don't alway disable, only on 'Delete'
	$('#drp_action').change(function() {
		// Delete we will disable
		if ($(this).find('option:selected').text() == 'Delete') {
			$(':checkbox.disabled').each(function(data) {
				$(this).prop('disabled', true).change().closest('tr').removeClass('selectable');
				if ($(this).is(':checked')) {
					$(this).prop('checked', false);
				}
			});
			$(':checkbox.disabled').parentsUntil('tr').unbind().closest('tr').removeClass('selected').change();
		}else{
			$(':checkbox.disabled').prop('disabled', false).change().closest('tr').addClass('selectable').change();
		}
		//
		// Allows selection of a non disabled row
		$('tr.selectable').find('td').not('.checkbox').each(function(data) {
			$(this).unbind();
			$(this).click(function(data) {
				$(this).parent().toggleClass('selected');
				var $checkbox = $(this).parent().find(':checkbox');
				$checkbox.prop('checked', !$checkbox.is(':checked'));
			});
		});

		// Generic Checkbox Function
		$('tr.selectable').find('.checkbox').each(function(data) {
			$(this).unbind();
			$(this).click(function(data) {
				if (!$(this).is(':disabled')) {
					$(this).parent().toggleClass('selected');
					var checked = $(this).is(':checkbox');
					$(this).prop('checked', !checked);
				}
			});
		});
	});

	// Select All Action for everyone but graphs_new, else do ugly shit
	if (basename(document.location.pathname, '.php') != 'graphs_new') {
		$('.tableSubHeaderCheckbox').find(':checkbox').click(function(data) {
			if ($(this).is(':checked')) {
				$('input[id^=chk_]').not(':disabled').prop('checked',true);
				$('tr.selectable').addClass('selected');
			}else{
				$('input[id^=chk_]').not(':disabled').prop('checked',false);
				$('tr.selectable').removeClass('selected');
			}
		});

		// Allows selection of a non disabled row
		$('tr.selectable').find('td').not('.checkbox').each(function(data) {
			$(this).click(function(data) {
				$(this).parent().toggleClass('selected');
				var $checkbox = $(this).parent().find(':checkbox');
				$checkbox.prop('checked', !$checkbox.is(':checked'));
			});
		});

		// Generic Checkbox Function
		$('tr.selectable').find('.checkbox').click(function(data) {
			if (!$(this).is(':disabled')) {
				$(this).parent().toggleClass('selected');
				var checked = $(this).is(':checkbox');
				$(this).prop('checked', !checked);
			}
		});
	}else{
		applySelectorVisibilityAndActions();
	}

	setupSortable();

	setupBreadcrumbs();

	applyTableSizing();

	setupPageTimeout();

	CsrfMagic.end();

	setupSpecialKeys();

	setupCollapsible();

	ajaxAnchors();

	if (typeof themeReady == 'function') {
		themeReady();
	}

	// Add tooltips to graph drilldowns
	$('.drillDown').tooltip();

	// Debug message actions
	$('table.debug').click(function() { 
		if ($(this).find('table').is(':visible')) { 
			$(this).find('table').slideUp('fast'); 
		} else { 
			$(this).find('table').slideDown('fast'); 
		}
	});

	// Don't show the message container until all GUI interaction is done
	$('#message_container').delay(2000).slideUp('fast');
	$('#main').show();
}

function loadPage(href) {
	$.get(href, function(html) {
		var htmlObject  = $(html);
		var matches     = html.match(/<title>(.*?)<\/title>/);
		var htmlTitle   = matches[1];
		var breadCrumbs = htmlObject.find('#breadcrumbs').html();
		var content     = htmlObject.find('#main').html();

		$('title').text(htmlTitle);
		$('#breadcrumbs').html(breadCrumbs);
		$('#main').html(content);

		applySkin();

		if (typeof window.history.pushState !== 'undefined') {
			window.history.pushState({page:href}, htmlTitle, href);
		}

		window.scrollTo(0, 0);

		return false;
	});

	return false;
}

function loadPageNoHeader(href) {
	$.get(href, function(data) {
		$('#main').html(data);

		applySkin();

		window.scrollTo(0, 0);

		return false;
	});

	return false;
}

function ajaxAnchors() {
	$('a.pic, a.linkOverDark, a.linkEditMain, a.hyperLink, a.tab').not('[href^=http], [href^=https], [href^=#]').unbind().click(function(event) {
		event.preventDefault();

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

		if (href != null) {
			pageName = basename(href);
		}

		loadPage(href);

		return false;
	});

	$(window).unbind('popstate').on('popstate', function(event) {
		if (event.state) {
			href = document.location.href;
			if (href !== null) {
				document.location = href;
			}
		}
	}, false);
}

function setupCollapsible() {
	storage=$.sessionStorage;

	$('.collapsible').each(function(data) {
		id=$(this).attr('id')+'_cs';
		state = storage.get(id);
		if (state == 'hide') {
			$(this).nextUntil('tr.spacer').hide();
			$(this).find('i').removeClass('fa-angle-double-up').addClass('fa-angle-double-down');
		}
	});

	$('.collapsible').click(function(data) {
		id=$(this).attr('id')+'_cs';
		if ($(this).find('i').hasClass('fa-angle-double-up')) {
			$(this).nextUntil('tr.spacer').slideUp('fast');
			$(this).find('i').removeClass('fa-angle-double-up').addClass('fa-angle-double-down');
			storage.set(id, 'hide');
		}else{
			$(this).nextUntil('tr.spacer').slideDown('fast');
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
	$('#filter').unbind('keypress').attr('title', 'Press Ctrl+Shift+X to Clear Filter');
	$('#filter').tooltip({ closed: true }).on('focus', function() { $('#filter').tooltip('close') }).on('click', function() { $(this).tooltip('close'); });

	$('#filter').bind('keypress', 'ctrl+shift+x', function() {
		clearFilter();
	});

	if (!isMobile.any()) {
		$('#filter').focus();
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
			$.get(page+(page.indexOf('?') > 0 ? '&':'?')+'sort_column='+column+'&sort_direction='+direction+'&header=false', function(data) {
				$('#main').html(data);
				applySkin();
			});
		}
	});

	// Setup tool tips for all titles to match the jQueryUI theme
	$('th').tooltip();
}

function setupBreadcrumbs() {
	$('#breadcrumbs > li > a').click(function(event) {
		event.preventDefault();
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

		var i = 0;
		if (key !== undefined) {
			if (initial && items.length) {
				$(this).find('th.ui-resizable').each(function(data) {
					if (parseInt(items[i]) == 0) {
						items[i] = parseInt($(this).css('width'));
					}

					if (items[i] != 0) {
						$(this).css('width', items[i]);
						$(this).attr('resizeWidth', items[i]);
					}
					i++;
				});
			}else{
				var sizes = new Array();
				$(this).find('th.ui-resizable').each(function(data) {
					sizes[i] = parseInt($(this).css('width'));

					if (sizes[i] != 0) {
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
			colWidth     = parseInt($(this).width());
			originalSize = parseInt(ui.size.width);

			if (originalSize == 0) {
				originalSize = parseInt($(this).width());
			}

			$(ui.originalElement).siblings().each(function(data) {
				$(this).attr('resizeWidth', parseInt($(this).width()));
			});
		 },
 
		resize: function(event, ui) {
			var resizeDelta = parseInt(ui.size.width - originalSize);
			var newColWidth = parseInt(colWidth + resizeDelta);
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
	//console.log('Page Timeout is :'+refreshMSeconds+', and going to Page :'+refreshPage);
	if (typeof refreshMSeconds != 'undefined') {
		clearTimeout(myRefresh);
		myRefresh = setTimeout(function() {
			if (refreshIsLogout) {
				document.location = refreshPage;
			}else{
				if (previousPage != '') {
					refreshPage = previousPage;
				}

				/* fix coner case with tree refresh */
				refreshPage = refreshPage.replace('action=tree&', 'action=tree_content&');

				$.get(refreshPage, function(data) {
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
	$('body').css('height', $(window).height());
	$('#navigation').css('height', ($(window).height())+'px');

	$(window).resize(function(event) {
		$('body').css('height', $(window).height());

		if (!$(event.target).hasClass('ui-resizable')) {
			$('#navigation').css('height', ($(window).height()-20)+'px');
		}
		//saveTableWidths(false);
	});

	$('#message_container').show().delay(2000).slideUp('fast');

	setupUserMenu();

	applySkin();

	$('#navigation_right').show();
});

/* Graph related javascript functions */
var graphPage  = urlPath+'graph_view.php';
var pageAction = 'preview';

function clearGraphFilter() {
	$.get(graphPage+'?action='+pageAction+'&header=false&clear=1', function(data) {
		$('#main').html(data);
		applySkin();
		initializeGraphs();
	});
}

function saveGraphFilter(section) {
	href=graphPage+'?action=save'+
		'&columns='+$('#columns').val()+
		'&graphs='+$('#graphs').val()+
		'&predefined_timespan='+$('#predefined_timespan').val()+
		'&predefined_timeshift='+$('#predefined_timeshift').val()+
		'&thumbnails='+$('#thumbnails').is(':checked');

	$.get(href+'&header=false&section='+section, function(data) {
		$('#text').show().text('Filter Settings Saved').fadeOut(2000);
	});
}

function applyGraphFilter() {
	href=graphPage+'?action='+pageAction+
		'&filter='+$('#filter').val()+'&host_id='+$('#host_id').val()+'&columns='+$('#columns').val()+
		'&graphs='+$('#graphs').val()+'&graph_template_id='+$('#graph_template_id').val()+
		'&thumbnails='+$('#thumbnails').is(':checked');

	$.get(href+'&header=false', function(data) {
		$('#main').html(data);
		applySkin();
		if (typeof window.history.pushState !== 'undefined') {
			window.history.pushState({ page: href }, 'Preview Mode', href);
		}

		initializeGraphs();
	});
}

function applyGraphTimespan() {
	$.get(graphPage+'?action='+pageAction+'&header=false'+
		'&predefined_timespan='+$('#predefined_timespan').val()+
		'&predefined_timeshift='+$('#predefined_timeshift').val(), function(data) {
		$('#main').html(data);
		applySkin();
		initializeGraphs();
	});
}

function refreshGraphTimespanFilter() {
	var json = { 
		custom: 1, 
		button_refresh_x: 1, 
		date1: $('#date1').val(), 
		date2: $('#date2').val(), 
		predefined_timespan: $('#predefined_timespan').val(), 
		predefined_timeshift: $('#predefined_timeshift').val()
	};

	var url  = graphPage+'?action='+pageAction+'&header=false';
	$.post(url, json).done(function(data) {
		$('#main').html(data);
		applySkin();
		initializeGraphs();
	});
}

function timeshiftGraphFilterLeft() {
	var json = { 
		move_left_x: 1, 
		move_left_y: 1, 
		date1: $('#date1').val(), 
		date2: $('#date2').val(), 
		predefined_timespan: $('#predefined_timespan').val(), 
		predefined_timeshift: $('#predefined_timeshift').val() 
	};

	var url  = graphPage+'?action='+pageAction+'&header=false';
	$.post(url, json).done(function(data) {
		$('#main').html(data);
		applySkin();
		initializeGraphs();
	});
}

function timeshiftGraphFilterRight() {
	var json = { 
		move_right_x: 1, 
		move_right_y: 1, 
		date1: $('#date1').val(), 
		date2: $('#date2').val(), 
		predefined_timespan: $('#predefined_timespan').val(), 
		predefined_timeshift: $('#predefined_timeshift').val() 
	};

	var url  = graphPage+'?action='+pageAction+'&header=false';
	$.post(url, json).done(function(data) {
		$('#main').html(data);
		applySkin();
		initializeGraphs();
	});
}

function clearGraphTimespanFilter() {
	var json = { 
		button_clear: 1, 
		date1: $('#date1').val(), 
		date2: $('#date2').val(), 
		predefined_timespan: $('#predefined_timespan').val(), 
		predefined_timeshift: $('#predefined_timeshift').val()
	};

	var url  = graphPage+'?action='+pageAction+'&header=false';

	$.post(url, json).done(function(data) {
		$('#main').html(data);
		applySkin();
		initializeGraphs();
	});
}

function initializeGraphs() {
	$('span[id$="_mrtg"]').unbind('click').click(function() {
		graph_id=$(this).attr('id').replace('graph_','').replace('_mrtg','');
		$.get(urlPath+'graph.php?local_graph_id='+graph_id+'&header=false', function(data) {
			$('#breadcrumbs').append('<li><a id="nav_mrgt" href="#">MRTG View</a></li>');
			$('#main').html(data);
			applySkin();
		});
	});

	$('span[id$="_csv"]').unbind('click').click(function() {
		graph_id=$(this).attr('id').replace('graph_','').replace('_csv','');
		document.location = urlPath+'graph_xport.php?local_graph_id='+graph_id+'&rra_id=0&view_type=tree&graph_start='+getTimestampFromDate($('#date1').val())+'&graph_end='+getTimestampFromDate($('#date2').val());
	});

	$('#form_graph_view').unbind('submit').on('submit', function(event) {
		event.preventDefault();
		applyFilter();
	});

	$('div[id^="wrapper_"]').each(function() {
		graph_id=$(this).attr('id').replace('wrapper_','');
		graph_height=$(this).attr('graph_height');
		graph_width=$(this).attr('graph_width');

		$.getJSON(urlPath+'graph_json.php?rra_id=0'+
			'&local_graph_id='+graph_id+
			'&graph_start='+graph_start+
			'&graph_end='+graph_end+
			'&graph_height='+graph_height+
			'&graph_width='+graph_width+
			($('#thumbnails').is(':checked') ? '&graph_nolegend=true':''),
			function(data) {
				$('#wrapper_'+data.local_graph_id).html("<img class='graphimage' id='graph_"+data.local_graph_id+"' src='data:image/"+data.type+";base64,"+data.image+"' border='0' graph_start='"+data.graph_start+"' graph_end='"+data.graph_end+"' graph_left='"+data.graph_left+"' graph_top='"+data.graph_top+"' graph_width='"+data.graph_width+"' graph_height='"+data.graph_height+"' width='"+data.image_width+"' height='"+data.image_height+"' image_width='"+data.image_width+"' image_height='"+data.image_height+"' value_min='"+data.value_min+"' value_max='"+data.value_max+"'>");
				$("#graph_"+data.local_graph_id).zoom({
					inputfieldStartTime : 'date1', 
					inputfieldEndTime : 'date2', 
					serverTimeOffset : timeOffset
				});
				realtimeArray[data.local_graph_id] = false;
			});
	});

	$('#realtimeoff').unbind('click').click(function() {
		stopRealtime();
	});

	$('#ds_step').unbind('change').change(function() {
		realtimeGrapher();
	});

	$('span[id$="_util"]').unbind('click').click(function() {
		graph_id=$(this).attr('id').replace('graph_','').replace('_util','');
		$.get(urlPath+'graph.php?action=zoom&header=false&local_graph_id='+graph_id+'&rra_id=0&graph_start='+getTimestampFromDate($('#date1').val())+'&graph_end='+getTimestampFromDate($('#date2').val()), function(data) {
			$('#main').html(data);
			$('#breadcrumbs').append('<li><a id="nav_util" href="#">Utility View</a></li>');
			applySkin();
		});
	});

	$('span[id$="_realtime"]').unbind('click').click(function() {
		graph_id=$(this).attr('id').replace('graph_','').replace('_realtime','');

		if (realtimeArray[graph_id]) {
			$('#wrapper_'+graph_id).html(keepRealtime[graph_id]).change();
			$(this).html("<img class='drillDown' border='0' title='Click to view just this Graph in Realtime' alt='' src='"+urlPath+"images/chart_curve_go.png'>");
			$(this).find('img').tooltip().zoom({ inputfieldStartTime : 'date1', inputfieldEndTime : 'date2', serverTimeOffset : timeOffset });
			realtimeArray[graph_id] = false;
			setFilters();
		}else{
			keepRealtime[graph_id]  = $('#wrapper_'+graph_id).html();
			$(this).html("<i style='font-size:16px;' title='Click again to take this Graph out of Realtime' class='fa fa-circle-o-notch fa-spin'/>");
			$(this).find('i').tooltip();
			realtimeArray[graph_id] = true;
			setFilters();
		}
	});
}

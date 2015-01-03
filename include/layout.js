/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2014 The Cacti Group                                 |
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

/** textWidth - This function will return the natural width of a string
 *  without any wrapping. */
$.fn.textWidth = function(text){
	var org = $(this)
	var html = $('<span style="display:none;position:absolute;width:auto;left:-9999px">' + (text || org.html()) + '</span>');
	if (!text) {
		html.css("font-family", org.css("font-family"));
		html.css("font-size", org.css("font-size"));
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
	if (href != '') {
		href = href+ (href.indexOf('?') > 0 ? '&':'?') + 'header=false';
		$.get(href, function(data) {
			$('#main').html(data);
			window.scrollTo(0,0);
			applySkin();
		});
	}else{
		href = document.location;
		href = href+ (href.indexOf('?') > 0 ? '&':'?') + 'header=false';
		$.get(href, function(data) {
			$('#main').html(data);
			window.scrollTo(0,0);
			applySkin();
		});
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

	if ($.isFunction(themeReady)) {
		themeReady();
	}

	$('#message_container').delay(2000).slideUp('fast');
}

function setupCollapsible() {
	$('.collapsible').each(function(data) {
		id=$(this).attr('id')+'_cs';
		state = $.cookie(id);
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
			$.cookie(id, 'hide', { expires: 31, path: '/cacti/' } );
		}else{
			$(this).nextUntil('tr.spacer').slideDown('fast');
			$(this).find('i').removeClass('fa-angle-double-down').addClass('fa-angle-double-up');
			$.cookie(id, 'show', { expires: 31, path: '/cacti/' } );
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
		openUserMenu();
	}).mouseleave(function(data) {
		if ($('.menuoptions').is(':visible')) {
			userMenuTimer = setTimeout('closeUserMenu()', 1000);
		}
	});
}

function setupSpecialKeys() {
	$('#filter').unbind('keypress').attr('title', 'Press Ctrl+C to Clear Filter');
	$('#filter').tooltip({ closed: true }).on('focus', function() { $('#filter').tooltip('close') }).on('click', function() { $(this).tooltip('close'); });

	$('#filter').bind('keypress', 'ctrl+c', function() {
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
			$.get(page+'?sort_column='+column+'&sort_direction='+direction+'&header=false', function(data) {
				$('#main').html(data);
				applySkin();
			});
		}
	});

	// Add nice search filter to filters
	$('input[id="filter"]').after("<i class='fa fa-search filter'/>").attr('autocomplete', 'off').attr('placeholder', 'Enter a search term');

	// Setup tool tips for all titles to match the jQueryUI theme
	$('th').tooltip();
}

function setupBreadcrumbs() {
	$('#breadcrumbs > li > a').click(function(event) {
		event.preventDefault;
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
	// Initialize table width on the page
	$('.cactiTable').each(function(data) {
		var key    = $(this).attr('id');
		var sizes  = $.cookie(key);
		var items  = sizes ? sizes.split(/,/) : new Array();

		var i = 0;
		if (key !== undefined) {
			if (initial && items.length) {
				$(this).find('th').each(function(data) {
					$(this).css('width', items[i]);
					i++;
				});
			}else{
				var sizes = new Array();
				$(this).find('th').each(function(data) {
					sizes[i] = parseInt($(this).css('width'));
					$(this).css('width', sizes[i]);
					i++;
				});

				if (i > 1) {
					$.cookie(key, sizes, { expires: 31, path: '/cacti/' } );
				}
			}
		}
	});
}

/** applyTableSizing - This function sets all table headers to be resizable using
 *  the jQueryUI function resizable.  It also calls the saveTableWidths function
 *  to store the cookie value every time a column is resized. */
function applyTableSizing() {
	saveTableWidths(true);

	$('.tableHeader th').resizable({
		handles: 'e',

		start: function(event, ui) {
			colWidth     = parseInt($(this).width());
			originalSize = parseInt(ui.size.width);
			originalSize = parseInt($(this).width());
		 },
 
		resize: function(event, ui) {
			var resizeDelta = parseInt(ui.size.width - originalSize);
			var newColWidth = parseInt(colWidth + resizeDelta);
			$(this).css('height', 'auto');
		},

		stop: function(event, ui) {
			saveTableWidths(false);
		}
	});
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

function pulsate(element) {
	$(element || this).delay(100).fadeOut(800).delay(100).fadeIn(800, pulsate);
}

$(function() {
	$('body').css('height', $(window).height());
	$('#navigation').css('height', ($(window).height())+'px').css('overflow-y', 'initial').css('overflow-x', 'initial');

	$(window).resize(function(event) {
		$('body').css('height', $(window).height());

		if (!$(event.target).hasClass('ui-resizable')) {
			$('#navigation').css('height', ($(window).height()-20)+'px');
		}
		saveTableWidths(false);
	});

	$('#message_container').show().delay(2000).slideUp('fast');

	setupUserMenu();

	applySkin();

	$('#navigation_right').show();
});

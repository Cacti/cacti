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
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

(function($){
 $.fn.DropDownMenu = function(options) {

	var defaults = {
		title: 			false,
		subtitle: 		false,
		name: 			'myName',
		maxHeight: 		300,
		width: 			'auto',
		timeout: 		500,
		auto_close: 	10000,
		html: 			'<h6>empty</h6>',
		offsetX: 		0,
		offsetY: 		0,
		simultaneous: 	false,
		textAlign:		'left'
	};

	var timerref 		= null;
	var menu 			= null;
	var menu_head, menu_content, menu_back, menu_subhead, menu_html;
	var menu_head_height, menu_back_height, menu_subhead_height;
	var menuHeight 		= 0;
	var options 		= $.extend(defaults, options);
	var contentHeight	= 0;

	if (typeof options.name !== 'string' || !/^[a-zA-Z][a-zA-Z0-9_-]*$/.test(options.name)) {
		return this;
	}

	// This is the sole boundary for caller-provided menu HTML. Without the
	// supported sanitizer, retain the content as text. DOM copies below only
	// clone this sanitized tree and nodes constructed by this implementation.
	function renderMenuHtml(target, html) {
		var content = typeof html === 'string' ? html : '';
		if (!window.DOMPurify || !window.DOMPurify.isSupported) {
			target.text(content);
			return;
		}
		var fragment = window.DOMPurify.sanitize(content, {
			ALLOWED_TAGS: ['h6', 'div', 'a', 'span', 'p', 'br', 'ul', 'ol', 'li', 'strong', 'em', 'b', 'i'],
			ALLOWED_ATTR: ['href', 'title'],
			ALLOW_DATA_ATTR: false,
			ALLOW_ARIA_ATTR: false,
			RETURN_DOM_FRAGMENT: true
		});
		fragment.querySelectorAll('a[href]').forEach(function(link) {
			try {
				var url = new URL(link.getAttribute('href'), document.baseURI);
				if (!['http:', 'https:', 'mailto:', 'tel:'].includes(url.protocol)) {
					link.removeAttribute('href');
				}
			} catch (error) {
				link.removeAttribute('href');
			}
		});
		target.empty().append(fragment);
	}

	/* do nothing if requested menu is still loaded */
	if($(document.getElementById(options.name)).is(":visible")) { return; }

	/* remove all open menus from DOM if they should not stay in front at the same time */
	var oldMenus = $(".cacti_dd_menu");
	if(options.simultaneous == false) {
		oldMenus.css({'overflow-y':'hidden'}).slideUp('200');
		oldMenus.queue(function () {
			oldMenus.remove();
			oldMenus.dequeue();
		});
	}

	return this.each(function() {
		var obj = $(this);
		var newMenu = _init_menu(obj);
		_open_menu(newMenu);
	});

	function _init_menu(initiator){
		/* create the main menu structure without parsing option values */
		menu = $(document.createElement('div')).attr('id', options.name)
			.addClass('cacti_dd_menu ui-widget ui-corner-all').hide();
		menu_head = $(document.createElement('div')).attr('id', options.name + '_title')
			.addClass('title ui-state-default ui-corner-top')
			.append($(document.createElement('h6')).text(options.title === false ? '' : options.title));
		menu_back = $(document.createElement('div')).attr('id', options.name + '_back')
			.addClass('back ui-state-active');
		menu_content = $(document.createElement('div')).attr('id', options.name + '_content')
			.addClass('content ui-widget-content ui-state-highlight')
			.toggleClass('ui-corner-bottom', options.subtitle === false);
		menu_subhead = $(document.createElement('div')).attr('id', options.name + '_subtitle')
			.addClass('subtitle ui-state-default ui-corner-bottom')
			.append($(document.createElement('h6')).text(options.subtitle === false ? '' : options.subtitle));
		menu_html = $(document.createElement('div')).attr('id', options.name + '_html').addClass('html').hide();
		renderMenuHtml(menu_html, options.html);
		menu.append(menu_head, menu_back, menu_content, menu_subhead, menu_html).appendTo(document.body);
		var i=1;
		menu_html.find("h6:has(div)").each(function() {
			var subMenu = $(this);
			var subMenuClass = options.name + '_' + i;
			var subMenuTitle = subMenu.find('a:first').text();
			subMenu.addClass(subMenuClass);
			subMenu.data('cacti-menu-class', subMenuClass);
			subMenu.on('click', function(event) { event.preventDefault(); event.stopPropagation(); _switch_layer(subMenuClass); });
			subMenu.children("div").hide();
			subMenu.find('a:first').empty().append(
				$(document.createElement('span')).css({display: 'inline-block', 'min-width': '80%'}).text(subMenuTitle),
				$(document.createElement('span')).addClass('ui-icon ui-icon-triangle-1-e').css({float: 'right'})
			);
			i++;
		});

		/* ... "myName_content" will have the visible menu data */
		menu_content.append(menu_html.contents().clone(true));

		/* if necessary show title, subtitle ... */
		menu_head.toggle(options.title !== false);
		menu_subhead.toggle(options.subtitle !== false);

		/* make content visible */
		menu_content.show();

		/* reduce height to a minimum for best fit */
		menuHeight = (menu.outerHeight() > options.maxHeight) ? options.maxHeight : menu.outerHeight();

		/* set the width to a fixed value */
		if(!isNaN(parseInt(options.width))) {
			menu.css({
				'min-width' : options.width + 'px',
				'max-width' : options.width + 'px'
			});
			menu.width(options.width);
		}else {
			// use real width plus 15 percent
			var width = menu.outerWidth(true)*1.15;
			menu.css({
				'min-width' : width + 'px',
				'max-width' : width + 'px'
			});
			menu.width(width);
		}

		/* default position of the menu container */
		menu.css({
			// x-position in relation to the initiator
			'left'	: initiator.offset().left + options.offsetX + 'px',
			// y-position in relation to the initiator
			'top' 	: initiator.offset().top + initiator.height() + options.offsetY + 'px'
		});

		/* change the orientation from right to left if width exceeds the windows size */
		if((initiator.offset().left + initiator.width() + options.offsetX + menu.outerWidth(true)) > $(window).width()) {
			menu.css({'left' : (initiator.offset().left + initiator.width() - menu.outerWidth(true)) + 'px'});
		}

		menu.css({'height':0, 'text-align':options.textAlign});
		menu.bind('mouseover', _cancel_timer);
		menu.bind('mouseout', _set_timer);
		return menu;
	}


	function _switch_layer(subMenuClass){
		if(subMenuClass == null) {
			var content = menu_html;
			menu_back.empty().hide();
			menu_content.height(contentHeight);
		}else {
			var content = menu_html.find('.' + subMenuClass + ' div:first');
			menu_back.show();
		}

		var parentClass = menu_html.find('.' + subMenuClass).parents('h6').first().data('cacti-menu-class');

		menu_back.empty().append(menu_html.find('.' + subMenuClass + ' a:first').contents().clone(true));
		menu_back.find('span:last').removeClass('ui-icon-triangle-1-e').addClass('ui-icon-triangle-1-s');
		menu_back.unbind('click').click( function() { _switch_layer( parentClass); });

		menu_content.empty().append(content.contents().clone(true));

		/* re-calculate content height */
		if(subMenuClass != null) {
				menu_head_height	= menu_head.is(":visible")		? menu_head.outerHeight()		: 0;
				menu_back_height	= menu_back.is(":visible")		? menu_back.outerHeight()		: 0;
				menu_subhead_height	= menu_subhead.is(":visible")	? menu_subhead.outerHeight()	: 0;

				menu_content.height(menuHeight - menu_head_height - menu_back_height - menu_subhead_height);
		}

		/* return false to suppress unwanted click events*/
		return false;
	}

	function _set_timer(timer){
			timer = ( typeof(timer) != 'number' ) ? options.timeout : timer;
			timerref = window.setTimeout( _close_menu, timer);
	}

	function _cancel_timer() {
		if(timerref) {
			window.clearTimeout(timerref);
			timerref = null;
		}
	}

	function _close_menu(){
		menu = $(document.getElementById(options.name));
		menu.slideUp(menuHeight*3);
		menu.queue(function () {
			menu.remove();
			menu.dequeue();
		});
	}

	function _open_menu(obj){
		//wait until oldMenu is completey closed before opening a new one
		var wait = setInterval(function() {
			if( !oldMenus.is(":animated") ) {
				clearInterval(wait);
				obj.show().animate({height: menuHeight}, menuHeight*3);

				//setup contentHeight;
				menu_head_height	= menu_head.is(":visible")		? menu_head.outerHeight()		: 0;
				menu_back_height	= menu_back.is(":visible")		? menu_back.outerHeight()		: 0;
				menu_subhead_height	= menu_subhead.is(":visible")	? menu_subhead.outerHeight()	: 0;

				menu_content.height(menuHeight - menu_head_height - menu_back_height - menu_subhead_height);

				contentHeight = menu_content.height();
				menu_content.css({'overflow-y':'auto'});

				obj.find('h6').eq(0).focus();
				if(options.auto_close !== false) {
					_set_timer(options.auto_close);
				}
			}
		}, 200);
	}

 };
})(jQuery);

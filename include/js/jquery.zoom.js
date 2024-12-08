/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2022 The Cacti Group                                 |
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
	$.fn.zoom = function(options) {
		/* +++++++++++++++++++++++ Global Variables +++++++++++++++++++++++++ */
		let storage = Storages.localStorage;

		// JS calculates in relation to the localization of the client - we have to take care of that, but only for 0.8.8
		let clientTime = new Date();
		let clientTimeOffset = clientTime.getTimezoneOffset()*60*(-1);											//requires -1 as PHP returns the opposite
		let timeOffset = 0;
		let mouseDown = false;

		// default values of the different options being offered
		let defaults = {
			inputfieldStartTime	: '',                  																	// ID of the input field that contains the start date
			inputfieldEndTime	: '',                  																	// ID of the input field that contains the end date
			submitButton		: 'button_refresh_x',  																	// ID of the 'submit' button
			cookieName			: 'cacti_zoom',        																	// default name required for session cookie
			serverTimeOffset	: 0,				   																	// JS calculates in relation to the localization of the browser (0.8.8 only)
			zoomMinTime         : 725846400,
			rangeTitle          : zoom_outOfRangeTitle,
			rangeMessage        : zoom_outOfRangeMessage
		};

		// define global variables / objects here
		let zoom = {
			initiator: 	$(this),																						// 'initiator' is the element that initiates Zoom
			image: 		{ top:0, left:0, width:0, height:0 },															// 'image' means the image tag and its properties
			graph: 		{ timespan:0, secondsPerPixel:0 },																// 'graph' stands for the rrdgraph itself, excluding legend, graph title, etc.
			box: 		{ top:0, left:0, right:0, width:0, height:0 },													// 'box' describes the area in front of the graph within jQueryZoom will allow interaction
			marker: 	{ 1 : { placed:false }, 2 : { placed:false} },													// 'markers' are selectors usable within the advanced mode
			custom: 	{
				zoomMode			: 'quick',																			// 'custom' holds the local configuration done by the user
				zoomOutPositioning	: 'center',
				zoomOutFactor		: '2',
				zoomTimestamps		: 'auto',
				zoom3rdMouseButton	: 'zoom_out'
			},
			options: 	$.extend(defaults, options),																	// 'options' contains the start input parameters
			attr: 		{
				start				: 'none',																			// 'attributes' holds all values that will describe the selected area
				end					: 'none',
				action				: 'left2right',
				location			: window.location.href.split('?'),
				urlPath				: ((typeof urlPath == 'undefined')
										? '' : urlPath),
				origin				: ((typeof location.origin == 'undefined')
										? location.protocol + '//' + location.host : location.origin)
			},
			raw: {																										// raw holds all raw data points and legend items, requires RRDtool 1.8.0+
				data 				: [],
				legend				: {},
				step 				: 0,
				start 				: 0,
				end 				: 0,
				current_timeframe	: 0,
				formatted_date		: '',
			},
			refs: 		{
				m: 	{ 1 : {}, 2 : {} },																					// 'references' allows addressing all zoom container elements without an extra document query
				livedata: { container: false, header: false, content: false, items: [] }
			},
			obj: {
				date : new Intl.DateTimeFormat(navigator.languages, {
					year: "numeric",
					month: "numeric",
					day: "numeric",
				}),
				time : new Intl.DateTimeFormat(navigator.languages, {
					hour: "numeric",
					minute: "numeric",
					second: "numeric",
					hour12: false,
				})
			},
			livedata: true
		};

		const si_prefixes = {
			 '24': 'Y',		// yotta
			 '21': 'Z',		// zeta
			 '18': 'E',		// eta
			 '15': 'P',		// peta
			 '12': 'T',		// tera
			  '9': 'G',		// giga
			  '6': 'M',		// mega
			  '3': 'k',		// kilo
			  '0': ' ',
			 '-3': 'm',		// milli
			 '-6': 'µ',		// micro
			 '-9': 'n', 	// nano
			'-12': 'p',		// pico
			'-15': 'f',		// femto
			'-18': 'a',		// atto
			'-21': 'z',		// zepto
			'-24': 'y'		// yocto
		};

		// support jQuery's concatenation
		return this.each(function() {
			zoom_init( $(this) );
		});

		/* ++++++++++++++++++++ Universal Functions +++++++++++++++++++++++++ */
		/**
		 * transforms an object into a comma separated string of key-value pairs
		 **/
		function serialize(object){
			let str = '';
			for (let key in object) { str += (key + '=' + object[key] + ','); }
			return str.slice(0, -1);
		}

		/**
		 * transforms a comma separated string of key-values pairs into an object
		 * including a change of the value type from string to boolean or numeric if reasonable.
		 **/
		function deserialize(string){
			let obj = [];

			if (string != null) {
				let pairs = string.split(',');
				for (var i=0; i<pairs.length; i++) {
					let pair = pairs[i].split('=');
					if (pair[1] === 'true') {
						pair[1] = true;
					} else if (pair[1] === 'false') {
						pair[1] = false;
					} else if ($.isNumeric(pair[1])) {
						pair[1] = +pair[1];
					}
					obj[pair[0]] = pair[1];
				}
			}
			return obj;
		}

		/**
		 * converts a Unix time stamp to a formatted date string
		 **/
		function unixTime2Date(unixTime){
			let date				= new Date(unixTime*1000+timeOffset);
			let year			= date.getFullYear();
			let month		= ((date.getMonth()+1) < 9 ) ? '0' + (date.getMonth()+1) : date.getMonth()+1;
			let day		= (date.getDate() > 9) ? date.getDate() : '0' + date.getDate();
			let hours		= (date.getHours() > 9) ? date.getHours() : '0' + date.getHours();
			let minutes	= (date.getMinutes() > 9) ? date.getMinutes() : '0' + date.getMinutes();
			let seconds	= (date.getSeconds() > 9) ? date.getSeconds() : '0' + date.getSeconds();

			return year + '-' + month + '-' + day + ' ' + hours + ':' + minutes + ':' + seconds;
		}

		/* +++++++++++++++++++++++ Core Functions +++++++++++++++++++++++++++ */

		/* init zoom */
		function zoom_init(image) {
			let activeElement = '';

			image.parent().disableSelection();
			image.off().on('mouseenter',
				function(){
					if($('#zoom-container').length !== 0) {
						activeElement = $('#zoom-container').attr('data-active-element');
					}
					if (!activeElement || activeElement !== zoomGetImageId(image)){
						zoomFunction_init(image);
					}
					zoomLiveData_hide();
				}
			);
		}

		function zoomGetElement(zoom) {
			let id = '#' + zoom.image.reference;
			if (zoom.image.rra_id > 0) {
				id += '[rra_id=\'' + zoom.image.rra_id + '\']';
			}
			return id;
		}

		function zoomGetImageId(image) {
			let id = image.attr('id');
			if (image.attr('rra_id') > 0) {
				id += '_rra' + image.attr('rra_id');
			}
			return id;
		}

		function zoomGetId(zoom) {
			let id = zoom.image.reference;
			if (zoom.image.rra_id > 0) {
				id += '_rra' + zoom.image.rra_id;
			}
			return id;
		}

		function zoomFunction_init(image) {

			/* load global settings cached in a cookie if available */
			if (storage.isSet(zoom.options.cookieName)) {
				zoom.custom = deserialize(storage.get(zoom.options.cookieName));
			}

			/* take care of different time zones server and client can make use of */
			if (zoom.options.serverTimeOffset > clientTimeOffset ) {
				timeOffset = (zoom.options.serverTimeOffset - clientTimeOffset)*1000;
			} else {
				timeOffset = (clientTimeOffset - zoom.options.serverTimeOffset)*1000*(-1);
			}

			/* fetch all attributes that rrdgraph provides */
			zoom.image.data 			= atob( zoom.initiator.attr('src').split(',')[1] );
			zoom.image.type 			= (zoom.initiator.attr('src').split(';')[0] === 'data:image/svg+xml' )? 'svg' : 'png';
			zoom.image.reference		= zoom.initiator.attr('id');
			zoom.image.id				= zoom.image.reference.replace('graph_', '');
			zoom.image.rra_id			= zoom.initiator.attr('rra_id');
			zoom.image.name 			= 'cacti_' + zoomGetImageId(zoom.initiator)+ '.' + zoom.image.type;
			zoom.image.legend			= (!($('#thumbnails').length !== 0 && $('#thumbnails').is(':checked')));
			zoom.image.top				= parseInt(zoom.initiator.offset().top);
			zoom.image.left				= parseInt(zoom.initiator.offset().left);
			zoom.image.width			= parseInt(zoom.initiator.attr('image_width'));
			zoom.image.right			= zoom.image.left + zoom.image.width;
			zoom.image.height			= parseInt(zoom.initiator.attr('image_height'));
			zoom.graph.top				= parseInt(zoom.initiator.attr('graph_top'));
			zoom.graph.left				= parseInt(zoom.initiator.attr('graph_left'));
			zoom.graph.width			= parseInt(zoom.initiator.attr('graph_width'));
			zoom.graph.height			= parseInt(zoom.initiator.attr('graph_height'));
			zoom.graph.start			= parseInt(zoom.initiator.attr('graph_start'));
			zoom.graph.end				= parseInt(zoom.initiator.attr('graph_end'));
			zoom.graph.timespan			= zoom.graph.end - zoom.graph.start;
			zoom.graph.secondsPerPixel	= zoom.graph.timespan/zoom.graph.width;
			zoom.box.width				= zoom.graph.width + ((zoom.custom.zoomMode === 'quick') ? 0 : 1);
			zoom.box.height				= zoom.graph.height;
			zoom.box.top 				= zoom.graph.top-1;
			zoom.box.bottom 			= zoom.graph.top + zoom.box.height;
			zoom.box.left				= zoom.graph.left;
			zoom.box.right				= zoom.box.left + zoom.box.width;

			if (typeof(zoom.initiator.attr('data-raw')) !== 'undefined') {
				let raw_data = JSON.parse(lzjs.decompressFromBase64(zoom.initiator.attr('data-raw')));
				if(raw_data.data !== undefined && raw_data.data.length > 0) {
					zoom.raw.data = raw_data.data;
				}
				if(raw_data.meta !== undefined) {
					if (raw_data.meta.legend !== undefined) {
						for (let key in raw_data.meta.legend) {
							if (raw_data.meta.legend[key] !== '') {
								zoom.raw.legend[key] = raw_data.meta.legend[key];
							}
						}
					}
					if (raw_data.meta.step !== undefined) zoom.raw.step = raw_data.meta.step;
					if (raw_data.meta.start !== undefined) zoom.raw.start = raw_data.meta.start;
					if (raw_data.meta.end !== undefined) zoom.raw.end = raw_data.meta.end;
				}
				/* reset time parameters */
				zoom.raw.current_timeframe = 0;
				zoom.raw.formatted_date = '';
			}

			// add all additional HTML elements to the DOM if necessary and register
			// the individual events needed. Once added we will only reset
			// and reposition these elements.

			// add zoom container plus elements to DOM if not existing and fill up reference cache
			zoom.refs.container = $('#zoom-container');
			if (zoom.refs.container.length === 0) {
				zoom.refs.container 		= $('<div id="zoom-container" data-active-element="'+zoomGetImageId(image)+'"></div>').appendTo('body');
				zoom.refs.box 				= $('<div id="zoom-box"></div>').appendTo('#zoom-container');
				zoom.refs.crosshair_x 		= $('<div id="zoom-crosshair-x" class="zoom-crosshair x-axis"></div>').appendTo('#zoom-box');
				zoom.refs.crosshair_y 		= $('<div id="zoom-crosshair-y" class="zoom-crosshair y-axis"></div>').appendTo('#zoom-box');
				zoom.refs.area 				= $('<div id="zoom-area"></div>').appendTo('#zoom-container');
				zoom.refs.m[1].excludedArea	= $('<div id="zoom-excluded-area-1" class="zoom-area-excluded"></div>').appendTo('#zoom-container');
				zoom.refs.m[1].marker		= $('<div id="zoom-marker-1" class="zoom-marker"></div>').appendTo('#zoom-container');
				zoom.refs.m[1].tooltip 		= $('<div id="zoom-marker-tooltip-1" class="zoom-marker-tooltip"><span id="zoom-marker-tooltip-value-1" class="zoom-marker-tooltip-value"></span></div>').appendTo('#zoom-marker-1');
				zoom.refs.m[2].excludedArea	= $('<div id="zoom-excluded-area-2" class="zoom-area-excluded"></div>').appendTo('#zoom-container');
				zoom.refs.m[2].marker		= $('<div class="zoom-marker" id="zoom-marker-2"></div>').appendTo('#zoom-container');
				zoom.refs.m[2].tooltip  	= $('<div id="zoom-marker-tooltip-2" class="zoom-marker-tooltip"><span id="zoom-marker-tooltip-value-2" class="zoom-marker-tooltip-value"></span></div>').appendTo('#zoom-marker-2');

				zoom.refs.container.removeClass().addClass('zoom_active_' + zoomGetId(zoom));

			} else {
				zoom.refs.box 				= $('#zoom-box');
				zoom.refs.crosshair_x 		= $('#zoom-crosshair-x');
				zoom.refs.crosshair_y 		= $('#zoom-crosshair-y');
				zoom.refs.area 				= $('#zoom-area');
				zoom.refs.m[1].excludedArea	= $('#zoom-excluded-area-1');
				zoom.refs.m[1].marker		= $('#zoom-marker-1');
				zoom.refs.m[1].tooltip  	= $('#zoom-marker-tooltip-1');
				zoom.refs.m[2].excludedArea	= $('#zoom-excluded-area-2');
				zoom.refs.m[2].marker 		= $('#zoom-marker-2');
				zoom.refs.m[2].tooltip 		= $('#zoom-marker-tooltip-2');

				zoom.refs.container.attr('data-active-element', zoomGetImageId(image));
            }

			// add the context (right click) menu
			zoom.refs.menu = $('#zoom-menu');
			if (zoom.refs.menu.length === 0) {
				zoom.refs.menu = $('<div id="zoom-menu" class="zoom-menu">'
					+ '<div class="first_li">'
					+ 	'<div class="ui-icon ui-icon-zoomin zoomContextMenuAction__zoom_in"></div>'
					+ 	'<span class="zoomContextMenuAction__zoom_in">' + zoom_i18n_zoom_in + '</span>'
					+ '</div>'
					+ '<div class="first_li">'
					+ 	'<div class="ui-icon ui-icon-zoomout zoomContextMenuAction__zoom_out"></div>'
					+	'<div class="ui-icon ui-icon-play ui-icon-right"></div>'
					+ 	'<span class="zoomContextMenuAction__zoom_out">' + zoom_i18n_zoom_out + '</span>'
					+ 	'<div class="inner_li">'
					+ 		'<span class="zoomContextMenuAction__zoom_out__2">' + zoom_i18n_zoom_2 + '</span>'
					+ 		'<span class="zoomContextMenuAction__zoom_out__4">' + zoom_i18n_zoom_4 + '</span>'
					+ 		'<span class="zoomContextMenuAction__zoom_out__8">' + zoom_i18n_zoom_8 + '</span>'
					+ 		'<span class="zoomContextMenuAction__zoom_out__16">' + zoom_i18n_zoom_16 + '</span>'
					+ 		'<span class="zoomContextMenuAction__zoom_out__32">' + zoom_i18n_zoom_32 + '</span>'
					+ 	'</div>'
					+ '</div>'
					+ '<div class="sep_li"></div>'
					+ '<div class="first_li">'
					+ 	'<div class="ui-icon ui-icon-empty"></div>'
					+	'<div class="ui-icon ui-icon-play ui-icon-right"></div>'
					+	'<span>' + zoom_i18n_mode + '</span>'
					+ 	'<div class="inner_li">'
					+ 		'<span class="zoomContextMenuAction__set_zoomMode__quick">' + zoom_i18n_quick + '</span>'
					+ 		'<span class="zoomContextMenuAction__set_zoomMode__advanced">' + zoom_i18n_advanced + '</span>'
					+ 	'</div>'
					+ '</div>'
					+ '<div class="sep_li"></div>'
					+ '<div class="first_li">'
					+ 	'<div class="ui-icon ui-icon-empty"></div>'
					+	'<div class="ui-icon ui-icon-play ui-icon-right"></div>'
					+	'<span>' + zoom_i18n_graph + '</span>'
					+	'<div class="inner_li">'
					+ 		'<span class="zoomContextMenuAction__newTab">' + zoom_i18n_newTab + '</span>'
					+		'<span class="zoomContextMenuAction__save">' + zoom_i18n_save_graph + '</span>'
					+		'<span class="zoomContextMenuAction__copy">' + zoom_i18n_copy_graph + '</span>'
					+		'<span class="zoomContextMenuAction__link">' + zoom_i18n_copy_graph_link + '</span>'
					+	'</div>'
					+ '</div>'
					+ '<div class="first_li">'
					+	'<div class="ui-icon ui-icon-wrench"></div>'
					+	'<div class="ui-icon ui-icon-play ui-icon-right"></div>'
					+	'<span>' + zoom_i18n_settings + '</span>'
					+	'<div class="inner_li">'
					+		'<div class="sec_li">'
					+			'<div class="ui-icon ui-icon-play ui-icon-right"></div>'
					+			'<span>' + zoom_i18n_timestamps + '</span>'
					+			'<div class="inner_li">'
					+ 				'<span class="zoomContextMenuAction__set_zoomTimestamps__on">' + zoom_i18n_on + '</span>'
					+ 				'<span class="zoomContextMenuAction__set_zoomTimestamps__auto">' + zoom_i18n_auto + '</span>'
					+ 				'<span class="zoomContextMenuAction__set_zoomTimestamps__off">' + zoom_i18n_off + '</span>'
					+ 			'</div>'
					+ 		'</div>'
					+ 		'<div class="sep_li"></div>'
					+ 		'<div class="sec_li">'
					+			'<div class="ui-icon ui-icon-play ui-icon-right"></div>'
					+			'<span>' + zoom_i18n_zoom_out_factor + '</span>'
					+ 			'<div class="inner_li">'
					+ 				'<span class="zoomContextMenuAction__set_zoomOutFactor__2">' + zoom_i18n_zoom_2 + '</span>'
					+ 				'<span class="zoomContextMenuAction__set_zoomOutFactor__4">' + zoom_i18n_zoom_4 + '</span>'
					+ 				'<span class="zoomContextMenuAction__set_zoomOutFactor__8">' + zoom_i18n_zoom_8 + '</span>'
					+ 				'<span class="zoomContextMenuAction__set_zoomOutFactor__16">' + zoom_i18n_zoom_16 + '</span>'
					+ 				'<span class="zoomContextMenuAction__set_zoomOutFactor__32">' + zoom_i18n_zoom_32 + '</span>'
					+ 			'</div>'
					+ 		'</div>'
					+ 		'<div class="sec_li">'
					+			'<div class="ui-icon ui-icon-play ui-icon-right"></div>'
					+			'<span>' + zoom_i18n_zoom_out_positioning + '</span>'
					+ 				'<div class="inner_li">'
					+ 					'<span class="zoomContextMenuAction__set_zoomOutPositioning__begin">' + zoom_i18n_begin + '</span>'
					+ 					'<span class="zoomContextMenuAction__set_zoomOutPositioning__center">' + zoom_i18n_center + '</span>'
					+ 					'<span class="zoomContextMenuAction__set_zoomOutPositioning__end">' + zoom_i18n_end + '</span>'
					+ 				'</div>'
					+ 			'</div>'
					+ 			'<div class="sec_li">'
					+				'<div class="ui-icon ui-icon-play ui-icon-right"></div>'
					+				'<span>' + zoom_i18n_3rd_button + '</span>'
					+ 				'<div class="inner_li">'
					+ 					'<span class="zoomContextMenuAction__set_zoom3rdMouseButton__zoom_in">' + zoom_i18n_zoom_in + '</span>'
					+ 					'<span class="zoomContextMenuAction__set_zoom3rdMouseButton__zoom_out">' + zoom_i18n_zoom_out + '</span>'
					+ 					'<span class="zoomContextMenuAction__set_zoom3rdMouseButton__off">' + zoom_i18n_disabled + '</span>'
					+ 				'</div>'
					+ 			'</div>'
					+ 		'</div>'
					+ 	'</div>'
					+ '<div class="sep_li"></div>'
					+ '<div class="first_li">'
					+ 	'<div class="ui-icon ui-icon-close zoomContextMenuAction__close"></div>'
					+	'<span class="zoomContextMenuAction__close">' + zoom_i18n_close + '</span>'
					+ '</div>').appendTo('body');
			}

			// add a hidden anchor to use for downloads
			zoom.refs.image = $('#zoom-image');
			if (zoom.refs.image.length === 0) {
				zoom.refs.image = $('<a class="zoom-hidden" id="zoom-image"></a>').appendTo('body');
			}

			// add a hidden textarea used to copy images / links
			zoom.refs.textarea = $('#zoom-textarea');
			if (zoom.refs.textarea.length === 0) {
				zoom.refs.textarea = $('<textarea id="zoom-textarea" class="zoom-hidden"></textarea>').appendTo('body');
			}

			// add a hidden live data container
			zoom.refs.livedata.container = $('#zoom-livedata');
			if (zoom.refs.livedata.container.length === 0) {
				zoom.refs.livedata.container = $('<div id="zoom-livedata" class="zoom-livedata"></div>').appendTo('body');
				zoom.refs.livedata.header = $('<div id="zoom-livedata-header" class="zoom-livedata-header"></div>').appendTo('#zoom-livedata');
				zoom.refs.livedata.content = $('<div id="zoom-livedata-content" class="zoom-livedata-content"></div>').appendTo('#zoom-livedata');
			} else {
				zoom.refs.livedata.header = $('#zoom-livedata-header');
				zoom.refs.livedata.content = $('#zoom-livedata-content');
				zoom.refs.livedata.content.empty();
			}

			for (let key in zoom.raw.legend) {
				let ref = 'zoom-livedata-item-' + key;
				zoom.refs.livedata.items[key] = $('<div id="' + ref + '" class="zoom-livedata-item">' +
					'<div id="' + ref + '-color" class="zoom-livedata-color"></div>' +
					'<div id="' + ref + '-title" class="zoom-livedata-title">' + zoom.raw.legend[key].legend + '</div>' +
					'<div id="' + ref + '-value" class="zoom-livedata-value"></div>' +
					'<div id="' + ref + '-unit" class="zoom-livedata-unit"></div>' +
					'</div>').appendTo('#zoom-livedata-content');
				zoom.refs.livedata.items[key].color = $('#'+ref+'-color');
				zoom.refs.livedata.items[key].color.css("background-color", '#'+zoom.raw.legend[key].color);
				zoom.refs.livedata.items[key].title = $('#'+ref+'-title');
				zoom.refs.livedata.items[key].value = $('#'+ref+'-value');
				zoom.refs.livedata.items[key].unit 	= $('#'+ref+'-unit');
			}

			// ensure that zoom markers are hidden
			zoom.marker[1].placed = false;
			zoom.marker[2].placed = false;

			zoomElements_reposition();
			zoomElements_reset();
			zoomContextMenu_init();
			zoomAction_init();
		}

		/**
		 * reposition all elements of Zoom
		 **/
		function zoomElements_reposition() {
			zoom.refs.container.insertBefore( zoomGetElement(zoom) );
		}

		/**
		 * resets all elements of Zoom
		 **/
		function zoomElements_reset() {
			zoom.attr.start 	= 'none';
			zoom.marker 		= { 1 : { placed:false }, 2 : { placed:false} };

			zoom.refs.container.find('div[id^="zoom-"]:not("#zoom-menu")').removeAttr('style');
			zoom.refs.container.find('div[id^="zoom-"]').css({ 'pointer-events': 'all' });
			zoom.refs.container.off();
			zoom.refs.container.on('contextmenu', '', '', function(e) { zoomContextMenu_toggle(e); return false; } );

			zoom.refs.box.off('contextmenu');
			zoom.refs.box.on('contextmenu', '','',function(e) { zoomContextMenu_toggle(e); return false; } );
			zoom.refs.box.css({ cursor:'crosshair', width:zoom.box.width + 'px', height:zoom.box.height + 'px', top:zoom.box.top+'px', left:zoom.box.left+'px' } );

			zoom.refs.area.off()
			zoom.refs.area.css({ top:zoom.graph.top+'px', height:zoom.graph.height+'px' } );

			$('.zoom-area-excluded').off().on(
				'contextmenu', function(e) { zoomContextMenu_toggle(e); return false;}
			).on(
				'click', function() { zoomContextMenu_hide(); return false;}
			);

			$('.zoom-marker-tooltip-value').disableSelection();	// TODO - update CSS instead

			zoomLiveData_hide();
		}

		/*
		* registers all the different mouse click event handler
		*/
		function zoomAction_init() {

			if (zoom.custom.zoomMode === 'quick') {
				zoom.box.width = zoom.graph.width;
				zoom.refs.box.css({ width: zoom.box.width + 'px' });
				zoom.refs.area.resizable({ containment: '#zoom-box', handles: 'e, w' });
				zoom.refs.box.off().on('mousedown', '', {zoom : zoom}, function(e) {
					switch(e.which) {
						/* clicking the left mouse button will initiate a zoom-in */
						case 1:
							// ensure menu is closed
							zoomContextMenu_hide();
							// hide Live Data
							zoomLiveData_hide();
							// reset the zoom area
							zoom.attr.start = e.pageX;
							zoom.refs.box.css({ cursor:'e-resize' });
							zoom.refs.area.css({ width:'0px', left: zoom.attr.start-zoom.image.left+'px', display:'block'  });
						break;
					}
				}).on(
					'mousemove', function(e) {
						zoomAction_draw(e); zoomAction_position_crosshair(e)
					}
				).on(
					'mouseleave', function(e) { zoomAction_draw(e); zoomAction_position_crosshair(e) }
				);

				// avoid that drawing stops if the mouse will be quickly moved into the opposite direction.
				zoom.refs.area.off('mousemove').on('mousemove', function(e) {
					zoomAction_draw(e);
				});

				/* register the mouse up event */
				$('#zoom-box, #zoom-area').off('mouseup').on('mouseup', '', {zoom:zoom},function(e) {
					switch(e.which) {
						/* leaving the left mouse button will execute a zoom in */
						case 1:
							if (zoom.attr.start !== 'none') {
								zoomAction_zoom_in();
							}
						break;

						case 2:
							/* hide context menu if open */
							zoomContextMenu_hide();
							if (zoom.custom.zoom3rdMouseButton === 'zoom_in') {
								zoomAction_zoom_in();
							} else {
								zoomAction_zoom_out( zoom.custom.zoomOutFactor );
							}
						break;
					}
				});

				/* capture mouse up/down events for zoom */
				$(document).off('mousedown').on('mousedown', function() {
					mouseDown = true;
					clearTimeout(myRefresh);
				}).off('mouseup').on('mouseup', function() {
					if (mouseDown) {
						if (zoom.attr.start !== 'none') {
							zoomAction_zoom_in();
						}
					}
					mouseDown = false;
				});

				/* moving the mouse pointer quickly will avoid it
				that the mousemove event has enough time to actualize the zoom area */
				zoom.refs.container.on( 'mouseout', function(e) {
					zoomAction_draw(e);
				} );

			} else{
				/* welcome to the advanced mode ;) */
				zoom.box.width = zoom.graph.width+1;
				zoom.refs.box.css({ width:zoom.box.width + 'px' });
				zoom.refs.box.off().on('mousedown', '', {zoom:zoom},function(e) {
					let zoom = e.data.zoom;
					switch(e.which) {
						case 1:
							// ensure menu is closed
							zoomContextMenu_hide();

							/* find out which marker has to be added */
							if (zoom.marker[1].placed && zoom.marker[2].placed) {
								zoomAction_zoom_in();
								return;
							}

							let marker = zoom.marker[1].placed ? 2 : 1;
							let secondmarker = (marker === 1) ? 2 : 1;

							/* select marker */
							let $this = zoom.refs.m[marker].marker;

							/* place the marker and make it visible */
							zoom.marker[marker].placed = true;
							zoom.marker[marker].left = e.pageX-zoom.image.left;
							zoom.marker[marker].right = zoom.image.right-e.pageX;

							$this.css({ height:zoom.box.height+'px', top:zoom.box.top+'px', left:zoom.marker[marker].left+'px', display:'block' });


							/* place the marker's tooltip, update its value and make it visible if necessary (Setting: 'Always On') */
							zoom.marker[marker].unixtime = parseInt(parseInt(zoom.graph.start) + (zoom.marker[marker].left - zoom.box.left)*zoom.graph.secondsPerPixel);
							$('#zoom-marker-tooltip-value-' + marker).html(
								zoomFormattedDateTime(zoom.marker[marker].unixtime).replace(' ', '<br>')
							);

							/* use Vanilla JS as methods width() and height() by jQuery are unreliable for hidden elements */
							zoom.marker[marker].height = document.getElementById('zoom-marker-tooltip-'+marker).offsetHeight;
							zoom.marker[marker].width  = document.getElementById('zoom-marker-tooltip-'+marker).offsetWidth;
							zoom.marker[marker].dst_lt = zoom.marker[marker].left - zoom.marker[marker].width;
							zoom.marker[marker].dst_rt = zoom.marker[marker].right - zoom.marker[marker].width;

							/* make the excluded areas immediately visible if both markers are set */
							if (zoom.marker[1].placed && zoom.marker[2].placed) {
								zoom.marker.distance = zoom.marker[marker].left - zoom.marker[secondmarker].left;

								zoom.refs.m[marker].excludedArea.css({
									position:'absolute',
									height:zoom.box.height+'px',
									top:zoom.box.top+'px',
									left: (zoom.marker.distance > 0) ? zoom.marker[marker].left : zoom.box.left,
									width: (zoom.marker.distance > 0) ? zoom.box.right - zoom.marker[marker].left : zoom.marker[marker].left - zoom.box.left,
									display:'block'
								});

								zoom.refs.m[secondmarker].excludedArea.css({
									position:'absolute',
									height:zoom.box.height+'px',
									top:zoom.box.top+'px',
									left: (zoom.marker.distance > 0) ? zoom.box.left : zoom.marker[secondmarker].left,
									width: (zoom.marker.distance > 0) ? zoom.marker[secondmarker].left - zoom.box.left : zoom.box.right - zoom.marker[secondmarker].left,
									display:'block'
								});

								/* change cursor */
								zoom.refs.box.css({cursor: 'pointer'});
							}

							if (zoom.custom.zoomTimestamps === true) {
								zoom.refs.m[marker].tooltip.show(0);
							}
							checkTooltipOrientation(marker);


							/* make the marker draggable */
							$this.draggable({
								containment: '#zoom-box',
								axis: 'x',
								scroll: false,
								start:
									function() {
										zoom.livedata = false;
										if (zoom.custom.zoomTimestamps === 'auto') {
											$('.zoom-marker-tooltip').fadeIn(500);
										}
									},
								drag:
									function(event, ui) {
										if (ui.position['left'] <= zoom.box.left) {
											zoom.marker[marker].left = zoom.box.left;
										} else if (ui.position['left'] >= zoom.box.right) {
											zoom.marker[marker].left = zoom.box.right;
										} else {
											zoom.marker[marker].left = Math.ceil(parseFloat(ui.position['left']));
										}
										zoom.marker[marker].right = zoom.image.width - Math.ceil(parseFloat(ui.position['left']));

										/* update the timestamp shown in tooltip */
										zoom.marker[marker].unixtime = Math.ceil( parseFloat(parseInt(zoom.graph.start) + (zoom.marker[marker].left - zoom.graph.left)*zoom.graph.secondsPerPixel));
										$('#zoom-marker-tooltip-value-' + marker).html(
											zoomFormattedDateTime(zoom.marker[marker].unixtime).replace(' ', '<br>')
										);

										/* use Vanilla JS as methods width() and height() by jQuery are unreliable for hidden elements */
										zoom.marker[marker].width = document.getElementById('zoom-marker-tooltip-'+marker).offsetWidth;
										zoom.marker[marker].dst_lt = zoom.marker[marker].left - zoom.marker[marker].width;
										zoom.marker[marker].dst_rt = zoom.marker[marker].right - zoom.marker[marker].width;

										/* update the excludedArea if both markers have been placed */
										if (zoom.marker[1].placed && zoom.marker[2].placed) {
											zoom.marker.distance = zoom.marker[marker].left - zoom.marker[secondmarker].left;

											if ( zoom.marker.distance > 0 ) {
												zoom.marker[marker].excludeArea = 'right';
												zoom.marker[secondmarker].excludeArea = 'left';
											} else {
												zoom.marker[marker].excludeArea = 'left';
												zoom.marker[secondmarker].excludeArea = 'right';
											}

											/* in that case, we have to update the tooltip of both markers */
											zoom.refs.m[marker].excludedArea.css({ left: (zoom.marker.distance > 0) ? zoom.marker[marker].left : zoom.box.left, width: (zoom.marker.distance > 0) ? zoom.box.right - zoom.marker[marker].left : zoom.marker[marker].left - zoom.box.left});
											zoom.refs.m[secondmarker].excludedArea.css({ left: (zoom.marker.distance > 0) ? zoom.box.left : zoom.marker[secondmarker].left, width: (zoom.marker.distance > 0) ? zoom.marker[secondmarker].left - zoom.box.left : zoom.box.right - zoom.marker[secondmarker].left});


										}
										checkTooltipOrientation(marker);
									},
								stop:
									function() {
										zoom.livedata = true;
										/* hide all tooltips if we are in auto mode */
										if (zoom.custom.zoomTimestamps === 'auto') {
											$('.zoom-marker-tooltip').fadeOut(1000);
										}
									}

							});

							break;
						case 2:
							if (zoom.custom.zoom3rdMouseButton !== false) {
								/* hide the context menu if open */
								zoomContextMenu_hide();
								if (zoom.custom.zoom3rdMouseButton === 'zoom_in') {
									zoomAction_zoom_in();
								} else {
									zoomAction_zoom_out( zoom.custom.zoomOutFactor );
								}
							}
							break;
					}
					return false;

				}).on(
					'mousemove', function(e) { zoomAction_position_crosshair(e) }
				).on(
					'mouseleave', function(e) {zoomAction_position_crosshair(e) }
				)

			}
		}

		function checkTooltipOrientation(marker) {
			let secondmarker = (marker === 1) ? 2 : 1;

			if(zoom.marker.distance === undefined) {
				let test = (zoom.marker[marker].dst_lt < 1);
				zoom.refs.m[marker].tooltip.toggleClass('relative-right', (zoom.marker[marker].dst_lt < 1) );
			} else {
				if (zoom.marker.distance < 0) {
					// marker is left beside secondmarker;
					zoom.refs.m[marker].tooltip.toggleClass('relative-right', (zoom.marker[marker].dst_lt < 1));
					zoom.refs.m[secondmarker].tooltip.toggleClass('relative-right', (zoom.marker[secondmarker].dst_rt > 1));
				} else {
					// marker is right beside secondmarker;
					zoom.refs.m[marker].tooltip.toggleClass('relative-right', (zoom.marker[marker].dst_rt > 1));
					zoom.refs.m[secondmarker].tooltip.toggleClass('relative-right', (zoom.marker[secondmarker].dst_lt < 1));
				}
			}
		}

		/*
		* executes a dynamic zoom in
		*/
		function zoomAction_zoom_in(){
			setCustomFilterActionActionAndDate();

			/* hide context menu if open */
			zoomContextMenu_hide();
			let newGraphStartTime;
			let newGraphEndTime;

			if (zoom.custom.zoomMode === 'quick') {
				newGraphStartTime 	= (zoom.attr.action === 'left2right') 	? parseInt(parseInt(zoom.graph.start) + (zoom.attr.start -zoom.image.left -zoom.box.left)*zoom.graph.secondsPerPixel)
																				: parseInt(parseInt(zoom.graph.start) + (zoom.attr.end -zoom.image.left -zoom.box.left)*zoom.graph.secondsPerPixel);
				newGraphEndTime 	= (zoom.attr.action === 'left2right')	? Math.ceil( parseFloat(newGraphStartTime + (zoom.attr.end-zoom.attr.start)*zoom.graph.secondsPerPixel))
																				: parseInt(newGraphStartTime + (zoom.attr.start-zoom.attr.end)*zoom.graph.secondsPerPixel);

				/* If the user only clicked on a graph then equal end and start date to ensure that we do not propagate NaNs */
				if (isNaN(newGraphStartTime) && isNaN(newGraphEndTime)) {
					return;
				} else if (isNaN(newGraphStartTime) && !isNaN(newGraphEndTime)) {
					newGraphStartTime = newGraphEndTime;
				} else if (!isNaN(newGraphStartTime) && isNaN(newGraphEndTime)){
					newGraphEndTime = newGraphStartTime;
				}
			} else {
				/* advanced mode has other requirements */
				/* first of, do nothing if not both markers have been positioned */
				if (!zoom.marker[1].placed || !zoom.marker[2].placed) {
					alert('NOTE: In advanced mode both markers have to be positioned first to define the period of time you want to zoom in.');
					return;
				} else {
					newGraphStartTime = zoom.marker[((zoom.marker[1].unixtime > zoom.marker[2].unixtime)? 2 : 1 )].unixtime;
					newGraphEndTime = zoom.marker[((zoom.marker[1].unixtime > zoom.marker[2].unixtime)? 1 : 2 )].unixtime;
				}
			}

			/* hide Zoom without destroying its container */
			zoom.refs.container.html('');

			if (zoom.options.inputfieldStartTime !== '' && zoom.options.inputfieldEndTime !== ''){
				zoom.initiator.attr('graph_start', newGraphStartTime);
				zoom.initiator.attr('graph_end', newGraphEndTime);

				/* execute zoom within 'tree view' or the 'preview view' */
				$('#' + zoom.options.inputfieldStartTime).val(unixTime2Date(newGraphStartTime));
				$('#' + zoom.options.inputfieldEndTime).val(unixTime2Date(newGraphEndTime));

				if (graph_start !== null && graph_end !== null) {
					zoom.attr.start = 'none';

					if (pageAction !== 'graph') {
						graph_start = newGraphStartTime;
						graph_end   = newGraphEndTime;

						if (newGraphStartTime >= defaults.zoomMinTime) {
							initializeGraphs(true);
						}
					} else{
						$('#graph_start').val(newGraphStartTime);
						$('#graph_end').val(newGraphEndTime);

						if (newGraphStartTime >= defaults.zoomMinTime) {
							initializeGraphs(true);
						}
					}
				} else {
					$("input[name='" + zoom.options.submitButton + "']").trigger('click');
				}

				zoomAction_update_session(newGraphStartTime, newGraphEndTime);

				return false;
			} else {
				/* graph view is already in zoom status */
				open(zoom.attr.location[0] + '?action=' + zoom.graph.action + '&local_graph_id=' + zoom.graph.local_graph_id + '&rra_id=' + zoom.graph.rra_id + '&view_type=' + zoom.graph.view_type + '&graph_start=' + newGraphStartTime + '&graph_end=' + newGraphEndTime + '&graph_height=' + zoom.graph.height + '&graph_width=' + zoom.graph.width + '&title_font_size=' + zoom.graph.title_font_size + '&disable_cache=true', '_self');
			}

			zoom.attr.start = 'none';

		}

		/*
		* sets the predefined timespan to 'Custom'
		*/
		function setCustomFilterActionActionAndDate() {
			if (typeof $('#predefined_timespan').selectmenu() == 'function') {
				$('#predefined_timespan').val('0').selectmenu('refresh');
			} else{
				$('#predefined_timespan').val('0');
			}
		}

		function getZoomOutFactorText(zoomOutFactor) {
			switch(zoomOutFactor) {
				case 2:
					return zoom_i18n_zoom_out + ' (' + zoom_i18n_zoom_2 + ')';
				case 4:
					return zoom_i18n_zoom_out + ' (' + zoom_i18n_zoom_4 + ')';
				case 8:
					return zoom_i18n_zoom_out + ' (' + zoom_i18n_zoom_8 + ')';
				case 16:
					return zoom_i18n_zoom_out + ' (' + zoom_i18n_zoom_16 + ')';
				case 32:
					return zoom_i18n_zoom_out + ' (' + zoom_i18n_zoom_32 + ')';
			}
		}

		/*
		* executes a static zoom out (as right click event)
		*/
		function zoomAction_zoom_out(multiplier){
			setCustomFilterActionActionAndDate();

			/* hide context menus if open */
			zoomContextMenu_hide();

			multiplier--;
			/* avoid that we cannot zoom out anymore if start and end date will be equal */
			if (zoom.graph.timespan === 0) {
				zoom.graph.timespan = 1;
			}

			let newGraphStartTime;
			let newGraphEndTime;

			if (zoom.custom.zoomMode === 'quick' || !zoom.marker[1].placed || !zoom.marker[2].placed ) {
				if (zoom.custom.zoomOutPositioning === 'begin') {
					newGraphStartTime = parseInt(zoom.graph.start);
					newGraphEndTime   = parseInt(parseInt(zoom.graph.end) + (multiplier * zoom.graph.timespan));
				} else if (zoom.custom.zoomOutPositioning === 'end') {
					newGraphStartTime = parseInt(parseInt(zoom.graph.start) - (multiplier * zoom.graph.timespan));
					newGraphEndTime   = parseInt(zoom.graph.end);
				} else {
					if ($('#future').val() === 'on') {
						// define the new start and end time, so that the selected area will be centered per default
						newGraphStartTime = parseInt(parseInt(zoom.graph.start) - (0.5 * multiplier * zoom.graph.timespan));
						newGraphEndTime   = parseInt(parseInt(zoom.graph.end) + (0.5 * multiplier * zoom.graph.timespan));
					} else{
						let now = parseInt($.now() / 1000);
						newGraphEndTime   = parseInt(parseInt(zoom.graph.end) + (0.5 * multiplier * zoom.graph.timespan));
						newGraphStartTime = parseInt(parseInt(zoom.graph.start) - (0.5 * multiplier * zoom.graph.timespan));

						if (newGraphEndTime > now) {
							let offset = newGraphEndTime - now;
							newGraphEndTime    = now;
							newGraphStartTime -= offset;
						}
					}
				}
			} else {
				newGraphStartTime = zoom.marker[((zoom.marker[1].unixtime > zoom.marker[2].unixtime)? 2 : 1 )].unixtime;
				newGraphEndTime = zoom.marker[((zoom.marker[1].unixtime > zoom.marker[2].unixtime)? 1 : 2 )].unixtime;
				let selectedTimeSpan = newGraphEndTime - newGraphStartTime;

				if (zoom.custom.zoomOutPositioning === 'begin') {
					newGraphEndTime = newGraphEndTime + multiplier * selectedTimeSpan;
				} else if (zoom.custom.zoomOutPositioning === 'end') {
					newGraphStartTime = newGraphStartTime - multiplier * selectedTimeSpan;
				} else {
					newGraphStartTime = parseInt(newGraphStartTime - 0.5 * multiplier * selectedTimeSpan);
					newGraphEndTime = parseInt(newGraphEndTime + 0.5 * multiplier * selectedTimeSpan);
				}
			}

			/* hide Zoom without destroying its container */
			$('#zoom-container').html('');

			if (zoom.options.inputfieldStartTime !== '' && zoom.options.inputfieldEndTime !== ''){
				zoom.initiator.attr('graph_start', newGraphStartTime);
				zoom.initiator.attr('graph_end', newGraphEndTime);

				/* execute zoom within 'tree view' or the 'preview view' */
				$('#' + zoom.options.inputfieldStartTime).val(unixTime2Date(newGraphStartTime));
				$('#' + zoom.options.inputfieldEndTime).val(unixTime2Date(newGraphEndTime));

				if (graph_start !== null && graph_end !== null) {
					zoom.attr.start = 'none';

					if (pageAction !== 'graph') {
						graph_start = newGraphStartTime;
						graph_end = newGraphEndTime;

						if (newGraphStartTime >= defaults.zoomMinTime) {
							initializeGraphs(true);
						}
					} else{
						$('#graph_start').val(newGraphStartTime);
						$('#graph_end').val(newGraphEndTime);

						if (newGraphStartTime >= defaults.zoomMinTime) {
							initializeGraph();
						}
					}
				} else {
					$("input[name='" + zoom.options.submitButton + "']").trigger('click');
				}

				zoomAction_update_session(newGraphStartTime, newGraphEndTime);
			} else {
				open(zoom.attr.location[0] + '?action=' + zoom.graph.action + '&local_graph_id=' + zoom.graph.local_graph_id + '&rra_id=' + zoom.graph.rra_id + '&view_type=' + zoom.graph.view_type + '&graph_start=' + newGraphStartTime + '&graph_end=' + newGraphEndTime + '&graph_height=' + zoom.graph.height + '&graph_width=' + zoom.graph.width + '&title_font_size=' + zoom.graph.title_font_size + '&disable_cache=true', '_self');
			}
		}

		/*
		* when updating the zoom window, we have to update cacti's zoom session variables
		*/
		function zoomAction_update_session(newGraphStartTime, newGraphEndTime) {
			if (newGraphStartTime <= defaults.zoomMinTime) {
				PopupWarning(defaults.rangeMessage, defaults.rangeTitle);

				$('.graphimage').each(function() {
					$(this).zoom({
						inputfieldStartTime: 'date1',
						inputfieldEndTime: 'date2',
						serverTimeOffset: timeOffset
					});
				});
			} else {
				$.get(document.location.pathname +
					'?action=update_timespan' +
					'&date1=' + unixTime2Date(newGraphStartTime) +
					'&date2=' + unixTime2Date(newGraphEndTime), function() {
					$('#predefined_timespan').val('0');
					if (typeof $('#predefined_timespan').selectmenu() === 'object') {
						$('#predefined_timespan').selectmenu('refresh');
					}
				});
			}
		}

		/*
		* updates the CSS parameters of the zoom area to reflect user's interaction
		*/
		function zoomAction_draw(event) {
			if (zoom.attr.start === 'none') {
				return;
			} else {
				zoom.livedata = false;
			}

			/* the mouse has been moved from right to left */
			if ((event.pageX-zoom.attr.start)<0) {
				zoom.attr.action = 'right2left';
				zoom.attr.end = (event.pageX < zoom.image.left+zoom.box.left) ? zoom.image.left+zoom.box.left : event.pageX;
				zoom.refs.area.css({ left:zoom.attr.end-zoom.image.left+'px', width:Math.abs(zoom.attr.start-zoom.attr.end+1)+'px' });
			/* the mouse has been moved from left to right*/
			} else {
				zoom.attr.action = 'left2right';
				zoom.attr.end = (event.pageX > zoom.image.left+zoom.box.right) ? zoom.image.left+zoom.box.right : event.pageX;
				zoom.refs.area.css({ left:zoom.attr.start-zoom.image.left+'px', width:Math.abs(zoom.attr.end-zoom.attr.start)+'px' });
			}
		}

		function zoomAction_position_crosshair(event) {
			if (zoom.livedata === true && (event.type === 'mousemove' || event.type === 'mouseenter') ) {
				zoom.refs.crosshair_x.css('top', (event.pageY - parseInt(zoom.initiator.offset().top) - zoom.box.top)+"px").show();
				zoom.refs.crosshair_y.css('left', (event.pageX - parseInt(zoom.initiator.offset().left) - zoom.box.left)+"px").show();
				zoomLiveData_show(event);
			} else if (event.type === 'mouseleave') {
				zoomLiveData_hide()
			}
		}

		function zoomLiveData_hide() {
			zoom.refs.crosshair_x.hide();
			zoom.refs.crosshair_y.hide();
			zoom.refs.livedata.container.hide();
		}

		/**
		 *
		 * @access public
		 * @return void
		 **/
		function zoomContextMenu_init(){

			/* sync menu with cookie parameters */
			$('.zoomContextMenuAction__set_zoomMode__' + zoom.custom.zoomMode).addClass('zoom-menu-highlight');
			$('.zoomContextMenuAction__set_zoomTimestamps__' + ((zoom.custom.zoomTimestamps === 'auto') ? 'auto' : ((zoom.custom.zoomTimestamps) ? 'on' : 'off' ))).addClass('zoom-menu-highlight');
			$('.zoomContextMenuAction__set_zoomOutFactor__' + zoom.custom.zoomOutFactor).addClass('zoom-menu-highlight');
			$('.zoomContextMenuAction__set_zoomOutPositioning__' + zoom.custom.zoomOutPositioning).addClass('zoom-menu-highlight');
			$('.zoomContextMenuAction__set_zoom3rdMouseButton__' + ((zoom.custom.zoom3rdMouseButton === false) ? 'off' : zoom.custom.zoom3rdMouseButton) ).addClass('zoom-menu-highlight');
			$('.zoomContextMenuAction__zoom_out').text(getZoomOutFactorText(zoom.custom.zoomOutFactor));

			if (zoom.custom.zoomMode === 'quick') {
				$('.advanced_mode').hide(); //# TODO - check if still required.
			}

			/* init click on events */
			$('[class*=zoomContextMenuAction__]').off().on('click', function() {
				let zoomContextMenuAction = false;
				let zoomContextMenuActionValue = false;
				let classList = $.trim($(this).attr('class')).split(/\s+/);

				$.each( classList, function(index, item){
					if ( item.search('zoomContextMenuAction__') !== -1) {
						let zoomContextMenuActionList = item.replace('zoomContextMenuAction__', '').split('__');
						zoomContextMenuAction = zoomContextMenuActionList[0];
						if (zoomContextMenuActionList[1] === 'undefined' || zoomContextMenuActionList[1] === 'off') {
							zoomContextMenuActionValue = false;
						} else if (zoomContextMenuActionList[1] === 'on') {
							zoomContextMenuActionValue = true;
						} else {
							zoomContextMenuActionValue = zoomContextMenuActionList[1];
						}
						return false;
					}
				});

				if ( zoomContextMenuAction ) {
					if ( zoomContextMenuAction.substring(0,8) === 'set_zoom') {
						zoomContextMenuAction_set( zoomContextMenuAction.replace('set_zoom', '').toLowerCase(), zoomContextMenuActionValue);
					} else {
						zoomContextMenuAction_do( zoomContextMenuAction, zoomContextMenuActionValue);
					}
				}
			});

			/* init hover events */
			$('.first_li , .sec_li, .inner_li span').hover(
				function () {
					$(this).addClass('zoom-menu-hover');
					if ( $(this).children().length >0 )
						if (zoom.custom.zoomMode === 'quick') {
							$(this).children('.inner_li:not(.advanced_mode)').show();
						} else {
							$(this).children('.inner_li').show();
						}
					},
				function () {
					$(this).removeClass('zoom-menu-hover');
					$(this).children('.inner_li').hide();
				}
			);
		}

		/**
		 *
		 * @access public
		 * @return void
		 **/
		function zoomContextMenuAction_set(object, value){
			switch(object) {
				case 'mode':
					if ( zoom.custom.zoomMode !== value) {
						zoom.custom.zoomMode = value;

						$('[class*=zoomContextMenuAction__set_zoomMode__]').toggleClass('zoom-menu-highlight');

						if (value === 'quick') {
							// reset menu
							$('.advanced_mode').hide();

							zoom.custom.zoomMode			= 'quick';
							storage.set(zoom.options.cookieName, serialize(zoom.custom));
						} else {
							// switch to advanced mode
							$('.sec_li.advanced_mode').show();

							zoom.custom.zoomMode			= 'advanced';
							storage.set(zoom.options.cookieName, serialize(zoom.custom));
						}
						zoomContextMenu_hide();
						zoomElements_reset();
						zoomAction_init();
					}
					break;
				case 'timestamps':
					if ( zoom.custom.zoomTimestamps !== value) {
						zoom.custom.zoomTimestamps = value;
						storage.set(zoom.options.cookieName, serialize(zoom.custom));
						$('[class*=zoomContextMenuAction__set_zoomTimestamps__]').removeClass('zoom-menu-highlight');
						$('.zoomContextMenuAction__set_zoomTimestamps__' + ((zoom.custom.zoomTimestamps === 'auto') ? 'auto' : ((zoom.custom.zoomTimestamps) ? 'on' : 'off' ))).addClass('zoom-menu-highlight');

						/* make them visible only for mode 'Always On' */
						if (zoom.custom.zoomTimestamps === true) {
							$('.zoom-marker-tooltip').fadeIn(500);
						} else {
							$('.zoom-marker-tooltip').fadeOut(500);
						}
					}
					break;
				case 'outfactor':
					if ( zoom.custom.zoomOutFactor !== value) {
						zoom.custom.zoomOutFactor = value;
						storage.set(zoom.options.cookieName, serialize(zoom.custom));
						$('[class*=zoomContextMenuAction__set_zoomOutFactor__]').removeClass('zoom-menu-highlight');
						$('.zoomContextMenuAction__set_zoomOutFactor__' + value).addClass('zoom-menu-highlight');
						$('.zoomContextMenuAction__zoom_out').text(getZoomOutFactorText(value));
					}
					break;
				case 'outpositioning':
					if ( zoom.custom.zoomOutPositioning !== value) {
						zoom.custom.zoomOutPositioning = value;
						storage.set(zoom.options.cookieName, serialize(zoom.custom));
						$('[class*=zoomContextMenuAction__set_zoomOutPositioning__]').removeClass('zoom-menu-highlight');
						$('.zoomContextMenuAction__set_zoomOutPositioning__' + value).addClass('zoom-menu-highlight');
					}
					break;
				case '3rdmousebutton':
					if ( zoom.custom.zoom3rdMouseButton !== value) {
						zoom.custom.zoom3rdMouseButton = value;
						storage.set(zoom.options.cookieName, serialize(zoom.custom));
						$('[class*=zoomContextMenuAction__set_zoom3rdMouseButton__]').removeClass('zoom-menu-highlight');
						$('.zoomContextMenuAction__set_zoom3rdMouseButton__' + ((value === false) ? 'off' : value)).addClass('zoom-menu-highlight');
					}
					break;
			}
		}

		function zoomContextMenuAction_do(action, value){
			switch(action) {
				case 'close':
					zoomContextMenu_hide();

					break;
				case 'zoom_out':
					if (value === undefined) {
						value = zoom.custom.zoomOutFactor;
					}
					zoomAction_zoom_out(value);

					break;
				case 'zoom_in':
					zoomAction_zoom_in();

					break;
				case 'copy':
					zoom.refs.textarea.html('<img src="data:image/png;base64,'+btoa(unescape(encodeURIComponent(zoom.image.data)))+'" width="'+zoom.image.width+'" height="'+zoom.image.height+'">').select();

					try {
						let successful = document.execCommand('copy');
					} catch (err) {
						alert('Unsupported Browser');
					}
					return false;

				case 'save':
					let arraybuffer = new ArrayBuffer(zoom.image.data.length);
					let view = new Uint8Array(arraybuffer);
					for (let i = 0; i < zoom.image.data.length; i++) {
						view[i] = zoom.image.data.charCodeAt(i) & 0xff;
					}

					let blob;
					try {
						blob = new Blob([arraybuffer], {type: 'application/octet-stream'});
					} catch (e) {
						let bb = new (window.WebKitBlobBuilder || window.MozBlobBuilder);
						bb.append(arraybuffer);
						blob = bb.getBlob('application/octet-stream');
					}

					if (window.navigator && window.navigator.msSaveOrOpenBlob) {
						window.navigator.msSaveOrOpenBlob(blob, zoom.image.name);
					} else {
						let objectUrl = URL.createObjectURL(blob);
						zoom.refs.image.removeAttr('target').attr({'download':zoom.image.name, 'href':objectUrl }).get(0).click();
					}

					break;
				case 'newTab':
					url = zoom.attr.urlPath + 'graph_image.php?local_graph_id=' + zoom.image.id;
					if (zoom.image.rra_id > 0) {
						url += '&rra_id='+zoom.image.rra_id;
					}
					url += '&graph_start=' + zoom.graph.start + '&graph_end=' + zoom.graph.end + '&graph_width=' + zoom.graph.width + '&graph_height=' + zoom.graph.height + ( (zoom.image.legend === true) ? '' : '&graph_nolegend=true' ) + '&disable_cache=true';
					zoom.refs.image.removeAttr('download').attr({ 'href':url, 'target': '_bank' }).get(0).click();

					break;
				case 'link':
					url = zoom.attr.origin + ((zoom.attr.urlPath === '') ? '/' : zoom.attr.urlPath) + 'graph_image.php?local_graph_id=' + zoom.image.id + '&graph_start=' + zoom.graph.start + '&graph_end=' + zoom.graph.end + '&graph_width=' + zoom.graph.width + '&graph_height=' + zoom.graph.height + ( (zoom.image.legend === true) ? '' : '&graph_nolegend=true' ) + '&disable_cache=true';
					zoom.refs.textarea.html(url).select();
					try {
						let successful = document.execCommand('copy');
					} catch (err) {
						alert('Unsupported Browser');
					}
					return false;
				default:
					break;
			}
		}

		function zoomLiveData_show(e) {
			if (zoom.raw.data.length > 0) {
				if (e.type === 'mousemove' || e.type === 'mouseenter') {
					let container_y_pos = e.pageY;
					let container_y_offset = 10;
					let container_x_pos = e.pageX;
					let container_x_offset = 10;

					let window_size_x_1 = $(document).scrollLeft();
					let window_size_x_2 = $(window).width() + $(document).scrollLeft();
					let window_size_y_1 = $(document).scrollTop();
					let window_size_y_2 = $(window).height() + $(document).scrollTop();

					let container_height = zoom.refs.livedata.container.outerHeight();
					let container_width = zoom.refs.livedata.container.outerWidth();

					let unixTime = parseInt(parseInt(zoom.graph.start) + (e.pageX - zoom.image.left - zoom.box.left + 1) * zoom.graph.secondsPerPixel);
					let unixTimeframe = -1;

					if (zoom.raw.step > 0) {
						unixTimeframe = unixTime - unixTime % zoom.raw.step;

						// avoid superfluous calculation steps immediately
						if (zoom.raw.current_timeframe !== unixTimeframe) {

							const index = (unixTimeframe - zoom.raw.start) / zoom.raw.step + 1;

							for (let key in zoom.raw.legend) {
								let dataIndex = key;
								dataIndex++;
								let value = zoom.raw.data[index][dataIndex];
								if (value !== null) {
									value = zoomFormatNumToSI(value);
								}
								zoom.refs.livedata.items[key].value.html(value === null ? 'n/a  ' : value);
							}
							zoom.raw.current_timeframe = unixTimeframe;
						}
						unixTime = unixTimeframe;
					}

					// avoid superfluous calculation steps immediately
					if (zoom.raw.formatted_date === '' || zoom.raw.current_timeframe !== unixTimeframe) {
						zoom.refs.livedata.header.html( zoomFormattedDateTime(unixTime) );
					}

					if ((container_x_pos + container_x_offset + container_width) > window_size_x_2) {
						container_x_offset = -1 * (container_x_offset + container_width);
					}
					if ((container_y_pos + container_y_offset + container_height) > window_size_y_2) {
						container_y_offset = -1 * (container_y_offset + container_height);
					}

					zoom.refs.livedata.container.css({
						left: container_x_pos + container_x_offset,
						top: container_y_pos + container_y_offset,
						zIndex: '101'
					}).show();
				} else {
					if (e.type === 'mouseleave' && e.target === e.currentTarget) {
						zoom.refs.livedata.container.hide();
					}
				}
			}
		}

		function zoomFormattedDateTime(unixTime) {
			const date = new Date(unixTime * 1000);
			return zoom.obj.date.format(date) + ' ' +  zoom.obj.time.format(date);
		}

		function zoomFormatNumToSI(num) {
			const signPrefix = num < 0 ? '-' : ' ';
			let sig = Math.abs(num);
			let exponent = 0;

			if (num === 0) {
				return ' ' + new Big(0).toFixed(2) + ' ' + si_prefixes[exponent];
			}

			while (sig >= 1000 && exponent < 24) {
				sig /= 1000;
				exponent += 3;
			}
			while (sig < 1 && exponent > -24) {
				sig *= 1000;
				exponent -= 3;
			}
			return signPrefix + new Big(sig).toFixed(2) + ' ' + si_prefixes[exponent];
		}

		function zoomContextMenu_show(e) {
			let menu_y_pos				= e.pageY;
			let menu_y_offset	= 5;
			let menu_x_pos				= e.pageX;
			let menu_x_offset	= 5;

			let window_size_x_1		= $(document).scrollLeft();
			let window_size_x_2		= $(window).width() + $(document).scrollLeft();
			let window_size_y_1		= $(document).scrollTop();
			let window_size_y_2		= $(window).height() + $(document).scrollTop();

			let menu_height			= zoom.refs.menu.outerHeight();
			let menu_width			= zoom.refs.menu.outerWidth();
			let menu_width_level_1	= Math.abs($('.zoom-menu .first_li span').outerWidth());
			let menu_width_level_2	= Math.abs($('.zoom-menu .sec_li span').outerWidth());
			let menu_height_level_1	= Math.abs($('.zoom-menu .first_li span').outerHeight());
			let menu_height_level_2	= Math.abs($('.zoom-menu .sec_li span').outerHeight());

			/* let the menu occur on the right per default if possible, otherwise move it to the left: */
			if (( menu_x_pos + menu_x_offset + menu_width) > window_size_x_2 ) {
				menu_x_offset += (-1*menu_width);
				$('.zoom-menu .inner_li').css({ 'margin-left': -menu_width_level_1 });
			} else {
				if (( menu_x_pos + menu_x_offset + menu_width + menu_width_level_1 + menu_width_level_2 ) > window_size_x_2) {
					$('.zoom-menu .inner_li').css({ 'margin-left': -menu_width_level_1 });
				} else {
					$('.zoom-menu .inner_li').css({ 'margin-left': menu_width_level_1 });
				}
			}

			if (( menu_y_pos + menu_y_offset + menu_height ) > window_size_y_2 ) {
				menu_y_offset += (-1*menu_height);
			}

			zoom.refs.menu.css({ left: menu_x_pos+menu_x_offset, top: menu_y_pos+menu_y_offset, zIndex: '102' }).show();
		}

		function zoomContextMenu_hide(){
			zoom.refs.menu.hide();
		}

		function zoomContextMenu_toggle(e){
			(zoom.refs.menu.css('display') === 'none') ? zoomContextMenu_show(e) : zoomContextMenu_hide();
		}

	};

})(jQuery);

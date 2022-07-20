/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2021 The Cacti Group                                 |
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

/* requirements:
	jQuery 1.7.x or above
	jQuery UI 1.8.x or above
	jQuery cookie plugin
*/

(function($){
	$.fn.zoom = function(options) {
		storage=Storages.localStorage;

		/* +++++++++++++++++++++++ Global Variables +++++++++++++++++++++++++ */

		// JS calculates in relation to the localization of the client - we have to take care of that, but only for 0.8.8
		var clientTime = new Date();
		var clientTimeOffset = clientTime.getTimezoneOffset()*60*(-1);			//requires -1, because PHP return the opposite
		var timeOffset = 0;
		var mouseDown = false;

		// default values of the different options being offered
		var defaults = {
			inputfieldStartTime	: '',                  // ID of the input field that contains the start date
			inputfieldEndTime	: '',                  // ID of the input field that contains the end date
			submitButton		: 'button_refresh_x',  // ID of the submit button
			cookieName			: 'cacti_zoom',        // default name required for session cookie
			serverTimeOffset	: 0					   // JS calculates in relation to the localization of the browser :/ - only required for 0.8.8
		};

		// define global variables / objects here
		var zoom = {
			// 'initiator' is the element that initiates Zoom
			initiator: $(this),
			// 'image' means the image tag and its properties
			image: { top:0, left:0, width:0, height:0 },
			// 'graph' stands for the rrdgraph itself excluding legend, graph title etc.
			graph: { timespan:0, secondsPerPixel:0 },
			// 'box' describes the area in front of the graph within jQueryZoom will allow interaction
			box: { top:0, left:0, right:0, width:0, height:0 },
			// 'markers' are selectors useable within the advanced mode
			marker: { 1 : { placed:false }, 2 : { placed:false} },
			// 'custom' holds the local configuration done by the user
			custom: {},
			// 'options' contains the start input parameters
			options: $.extend(defaults, options),
			// 'attributes' holds all values that will describe the selected area
			attr: { start:'none', end:'none', action:'left2right', location: window.location.href.split('?'), urlPath: ((typeof urlPath == 'undefined') ? '' : urlPath), origin: ((typeof location.origin == 'undefined') ? location.protocol + '//' + location.host : location.origin)}
		};

		// support jQuery's concatenation
		return this.each(function() {
			zoom_init( $(this) );
		});

		/* ++++++++++++++++++++ Universal Functions +++++++++++++++++++++++++ */

		/**
		 * splits off the parameters of a given URL
		 **/
		function getUrlVars(url) {
			var parameters = [], name, value;

			urlBaseAndParameters = url.split('?');
			urlBase = urlBaseAndParameters[0];
			urlParameters = urlBaseAndParameters[1].split('&');
			parameters['urlBase'] = urlBase;

			for (var i=0; i<urlParameters.length; i++) {
				parameter = urlParameters[i].split('=');
				parameters[parameter[0].replace(/^graph_/, '')] = $.isNumeric(parameter[1]) ? +parameter[1] : parameter[1];
			}
			return parameters;
		}

		/**
		 * transforms an object into a comma separated string of key-value pairs
		 **/
		function serialize(object){
			var str = '';
			for (var key in object) { str += (key + '=' + object[key] + ','); }
			return str.slice(0, -1);
		}

		/**
		 * transforms a comma separated string of key-values pairs into an object
		 * including a change of the value type from string to boolean or numeric if reasonable.
		 **/
		function unserialize(string){
			var obj = new Array();

			if (string != null) {
				pairs = string.split(',');
				for (var i=0; i<pairs.length; i++) {
					pair = pairs[i].split('=');
					if (pair[1] == 'true') {
						pair[1] = true;
					} else if (pair[1] == 'false') {
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
			var date	= new Date(unixTime*1000+timeOffset);
			var year	= date.getFullYear();
			var month	= ((date.getMonth()+1) < 9 ) ? '0' + (date.getMonth()+1) : date.getMonth()+1;
			var day		= (date.getDate() > 9) ? date.getDate() : '0' + date.getDate();
			var hours	= (date.getHours() > 9) ? date.getHours() : '0' + date.getHours();
			var minutes	= (date.getMinutes() > 9) ? date.getMinutes() : '0' + date.getMinutes();
			var seconds	= (date.getSeconds() > 9) ? date.getSeconds() : '0' + date.getSeconds();

			var formattedTime = year + '-' + month + '-' + day + ' ' + hours + ':' + minutes + ':' + seconds;
			return formattedTime;
		}


		/* +++++++++++++++++++++++ Core Functions +++++++++++++++++++++++++++ */

		/* init zoom */
		function zoom_init(image) {
			var $this = image;
			var activeElement = '';

			$this.parent().disableSelection();
			$this.off().mouseover(
				function(){
					if($('#zoom-container').length != 0) {
						activeElement = $('#zoom-container').attr('data-active-element');
					}
					if (!activeElement || activeElement !== zoomGetImageId($this)){
						zoomElements_remove();
						zoomFunction_init($this);
					}
				}
			);
		}

		function zoomGetElement(zoom) {
			var id = '#' + zoom.image.reference;
			if (zoom.image.rra_id > 0) {
				id += '[rra_id=\'' + zoom.image.rra_id + '\']';
			}
			return id;
		}

		function zoomGetImageId(image) {
			var id = image.attr('id');
			if (image.attr('rra_id') > 0) {
				id += '_rra' + image.attr('rra_id');
			}
			return id;
		}

		function zoomGetId(zoom) {
			var id = zoom.image.reference;
			if (zoom.image.rra_id > 0) {
				id += '_rra' + zoom.image.rra_id;
			}
			return id;
		}

		function zoomFunction_init(image) {
			var $this = image;

			/* load global settings cached in a cookie if available */
			if (storage.isSet(zoom.options.cookieName)) {
				zoom.custom = unserialize(storage.get(zoom.options.cookieName));
			}

			if (zoom.custom.zoomMode == undefined) zoom.custom.zoomMode = 'quick';
			if (zoom.custom.zoomOutPositioning == undefined) zoom.custom.zoomOutPositioning = 'center';
			if (zoom.custom.zoomOutFactor == undefined) zoom.custom.zoomOutFactor = '2';
			if (zoom.custom.zoomTimestamps == undefined) zoom.custom.zoomTimestamps = 'auto';
			if (zoom.custom.zoom3rdMouseButton == undefined) zoom.custom.zoom3rdMouseButton = 'zoom_out';
			storage.set(zoom.options.cookieName, serialize(zoom.custom));

			/* take care of different time zones server and client can make use of */
			if (zoom.options.serverTimeOffset > clientTimeOffset ) {
				timeOffset = (zoom.options.serverTimeOffset - clientTimeOffset)*1000;
			} else {
				timeOffset = (clientTimeOffset - zoom.options.serverTimeOffset)*1000*(-1);
			}

			/* fetch all attributes that rrdgraph provides */
			zoom.image.data 			= atob( zoom.initiator.attr('src').split(',')[1] );
			zoom.image.type 			= (zoom.initiator.attr('src').split(';')[0] == 'data:image/svg+xml' )? 'svg' : 'png';
			zoom.image.reference		= zoom.initiator.attr('id');
			zoom.image.id				= zoom.image.reference.replace('graph_', '');
			zoom.image.rra_id			= zoom.initiator.attr('rra_id');
			zoom.image.name 			= 'cacti_' + zoomGetImageId(zoom.initiator)+ '.' + zoom.image.type;
			zoom.image.legend			= ($('#thumbnails').length != 0 && $('#thumbnails').is(':checked')) ? false : true;
			zoom.image.top				= parseInt(zoom.initiator.offset().top);
			zoom.image.left				= parseInt(zoom.initiator.offset().left);
			zoom.image.width			= parseInt(zoom.initiator.attr('image_width'));
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

			//console.log('BoxTop: '    + zoom.box.top);
			//console.log('BoxBottom: ' + zoom.box.bottom);
			//console.log('BoxLeft: '   + zoom.box.left);
			//console.log('BoxRight: '  + zoom.box.right);
			//console.log('ImageTop: '  + zoom.initiator.offset().top);
			//console.log('ImageLeft: ' + zoom.initiator.offset().left);
			//console.log('GraphTop: '  + zoom.initiator.attr('graph_top'));
			//console.log('GraphLeft: ' + zoom.initiator.attr('graph_left'));
			// get all graph parameters and merge results with zoom.graph object
			// $.extend(zoom.graph, getUrlVars( $this.attr("src") ));

			// add all additional HTML elements to the DOM if necessary and register
			// the individual events needed. Once added we will only reset
			// and reposition these elements.

			// add the container for all elements Zoom requires
			if ($('#zoom-container').length === 0) {
				// Please note: IE does not fire hover or click behaviors on completely transparent elements.
				// Use a background color and set opacity to 1% as a workaround.(see CSS file)
				$('<div id="zoom-container" data-active-element=""></div>').appendTo('body').delay(1000);
				$('#zoom-container').css({ position: 'absolute', 'pointer-events': 'none' });
			}else {
                $('#zoom-container').attr('data-active-element', '');
            }

			// add a hidden anchor to use for downloads
			if ($('#zoom-image').length == 0) {
				$('<a class="zoom-hidden" id="zoom-image"></a>').appendTo('body');
			}
			// add a hidden textareas used to copy images / links
			if ($('#zoom-textarea').length == 0) {
				$('<textarea id="zoom-textarea" class="zoom-hidden"></textarea>').appendTo('body');
			}

			// add the 'zoomBox'
			if ($('#zoom-box').length == 0) {
				// Please note: IE does not fire hover or click behaviors on completely transparent elements.
				// Use a background color and set opacity to 1% as a workaround.(see CSS file)
				$('<div id="zoom-box"></div>').appendTo('#zoom-container');
			}

			// add the 'zoomSelectedArea'
			if ($('#zoom-area').length == 0) {
				$('<div id="zoom-area"></div>').appendTo('#zoom-container');
			}

			// add two markers for the advanced mode
			if ($('#zoom-marker-1').length == 0) {
				$('<div id="zoom-excluded-area-1" class="zoom-area-excluded"></div>').appendTo('#zoom-container');
				$('<div class="zoom-marker" id="zoom-marker-1"><div class="zoom-marker-arrow-down"></div><div class="zoom-marker-arrow-up"></div></div>').appendTo('#zoom-container');
				$('<div id="zoom-marker-tooltip-1" class="zoom-marker-tooltip"><div id="zoom-marker-tooltip-1-arrow-left" class="zoom-marker-tooltip-arrow-left"><div id="zoom-marker-tooltip-1-arrow-left-inner" class="zoom-marker-tooltip-arrow-left-inner"></div></div><span id="zoom-marker-tooltip-value-1" class="zoom-marker-tooltip-value">-</span><div id="zoom-marker-tooltip-1-arrow-right" class="zoom-marker-tooltip-arrow-right"><div id="zoom-marker-tooltip-1-arrow-right-inner" class="zoom-marker-tooltip-arrow-right-inner"></div></div></div>').appendTo('#zoom-container');
			}
			if ($('#zoom-marker-2').length == 0) {
				$('<div id="zoom-excluded-area-2" class="zoom-area-excluded"></div>').appendTo('#zoom-container');
				$('<div class="zoom-marker" id="zoom-marker-2"><div class="zoom-marker-arrow-down"></div><div class="zoom-marker-arrow-up"></div></div>').appendTo('#zoom-container');
				$('<div id="zoom-marker-tooltip-2" class="zoom-marker-tooltip"><div id="zoom-marker-tooltip-2-arrow-left" class="zoom-marker-tooltip-arrow-left"><div id="zoom-marker-tooltip-1-arrow-left-inner" class="zoom-marker-tooltip-arrow-left-inner"></div></div><span id="zoom-marker-tooltip-value-2" class="zoom-marker-tooltip-value">-</span><div id="zoom-marker-tooltip-2-arrow-right" class="zoom-marker-tooltip-arrow-right"><div id="zoom-marker-tooltip-2-arrow-right-inner" class="zoom-marker-tooltip-arrow-right-inner"></div></div></div>').appendTo('#zoom-container');
			}
			zoom.marker[1].placed = false;
			zoom.marker[2].placed = false;

			// add the context (right click) menu
			if ($('#zoom-menu').length == 0) {
				$('<div id="zoom-menu" class="zoom-menu">'
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

			zoomElements_reposition();
			zoomElements_reset();
			zoomContextMenu_init();
			zoomAction_init(image);
		}

		/**
		 * reposition all elements of Zoom
		 **/
		function zoomElements_reposition() {
			$('#zoom-container').insertBefore(zoomGetElement(zoom));
		}

		/**
		 * resets and destroys all elements of Zoom
		 **/
		function zoomElements_remove() {
			zoomElements_reset();
			$('#zoom-container').find('*').off().remove();
			$('#zoom-menu').remove();
		}

		/**
		 * resets all elements of Zoom
		 **/
		function zoomElements_reset() {
			zoom.attr.start = 'none';
			zoom.marker = { 1 : { placed:false }, 2 : { placed:false} };
			$('#zoom-container > div[id^="zoom-"]').not('#zoom-menu').each( function () {
				$(this).removeAttr('style');
			});
			$('#zoom-container').off();
			$('#zoom-container').bind('contextmenu', function(e) { zoomContextMenu_toggle(e); return false;} );
			$('#zoom-box').off();
			$('#zoom-box').css({ cursor:'crosshair', width:zoom.box.width + 'px', height:zoom.box.height + 'px', top:zoom.box.top+'px', left:zoom.box.left+'px' });
			$('#zoom-box').bind('contextmenu', function(e) { zoomContextMenu_toggle(e); return false;} );
			$('#zoom-area').off().css({ top:zoom.graph.top+'px', height:zoom.graph.height+'px' });
			$('.zoom-area-excluded').off();
			$('.zoom-area-excluded').bind('contextmenu', function(e) { zoomContextMenu_toggle(e); return false;} );
			$('.zoom-area-excluded').bind('click', function(e) { zoomContextMenu_hide(); return false;} );
			$('.zoom-marker-arrow-up').css({ top:(zoom.box.height-6) + 'px' });
			$('.zoom-marker-tooltip-value').disableSelection();
			$('#zoom-container > div[id^="zoom-"]').css({ 'pointer-events': 'all' });
		}

		/*
		* registers all the different mouse click event handler
		*/
		function zoomAction_init(image) {

			if (zoom.custom.zoomMode === 'quick') {
				zoom.box.width = zoom.graph.width;
				$('#zoom-box').css({ width:zoom.box.width + 'px' });
				$('#zoom-area').resizable({ containment: '#zoom-box', handles: 'e, w' });
				$('#zoom-box').off('mousedown').on('mousedown', function(e) {
					switch(e.which) {
						/* clicking the left mouse button will initiates a zoom-in */
						case 1:
							// remember active element
							$('#zoom-container').attr('data-active-element', zoomGetImageId(image));
							// ensure menu is closed
							zoomContextMenu_hide();
							// reset the zoom area
							zoom.attr.start = e.pageX;
							$('#zoom-box').css({ cursor:'e-resize' });
							$('#zoom-area').css({ width:'0px', left: zoom.attr.start-zoom.image.left+'px', display:'block'  });
						break;
					}
				});

				/* register the mouse up event */
				$('#zoom-box, #zoom-area').off('mouseup').on('mouseup', function(e) {
					switch(e.which) {
						/* leaving the left mouse button will execute a zoom in */
						case 1:
							if (zoom.attr.start != 'none') {
								zoomAction_zoom_in();
							}
						break;

						case 2:
							/* hide context menu if open */
							zoomContextMenu_hide();
							if (zoom.custom.zoom3rdMouseButton == 'zoom_in') {
								zoomAction_zoom_in();
							} else {
								zoomAction_zoom_out( zoom.custom.zoomOutFactor );
							}
						break;
					}
				});

				/* stretch the zoom area in that direction the user moved the mouse pointer.
				   That is required to get it working faultlessly with Opera, IE and Chrome	*/
				$('#zoom-box, #zoom-area').mousemove( function(e) {
					zoomAction_draw(e);
				} );

				/* capture mouse up/down events for zoom */
				$(document).off('mousedown').on('mousedown', function() {
					mouseDown = true;
					clearTimeout(myRefresh);
				}).off('mouseup').on('mouseup', function() {
					if (mouseDown) {
						if (zoom.attr.start != 'none') {
							zoomAction_zoom_in();
						}
					}
					mouseDown = false;
				});

				/* moving the mouse pointer quickly will avoid that the mousemove event has enough time to actualize the zoom area */
				$('#zoom-container').mouseout( function(e) {
					zoomAction_draw(e);
				} );

			} else{
				/* welcome to the advanced mode ;) */
				zoom.box.width = zoom.graph.width+1;
				$('#zoom-box').css({ width:zoom.box.width + 'px' });
				$('#zoom-box').off('mousedown').on('mousedown', function(e) {
					switch(e.which) {
						case 1:
							// remember active element
							$('#zoom-container').attr('data-active-element', zoomGetImageId(image));
							// ensure menu is closed
							zoomContextMenu_hide();

							/* find out which marker has to be added */
							if (zoom.marker[1].placed && zoom.marker[2].placed) {
								zoomAction_zoom_in();
								return;
							} else {
								var marker = zoom.marker[1].placed ? 2 : 1;
								var secondmarker = (marker == 1) ? 2 : 1;
							}

							/* select marker */
							var $this = $('#zoom-marker-' + marker);

							/* place the marker and make it visible */
							var pos_relative_left = e.pageX-zoom.image.left;
							var pos_relative_top =  e.pageY-zoom.image.top;

							$this.css({ height:zoom.box.height+'px', top:zoom.box.top+'px', left:pos_relative_left+'px', display:'block' });
							zoom.marker[marker].placed = true;
							zoom.marker[marker].left = pos_relative_left;

							/* place the marker's tooltip, update its value and make it visible if necessary (Setting: 'Always On') */
							zoom.marker[marker].unixtime = parseInt(parseInt(zoom.graph.start) + (pos_relative_left - zoom.box.left)*zoom.graph.secondsPerPixel);
							$('#zoom-marker-tooltip-value-' + marker).html(
								unixTime2Date(zoom.marker[marker].unixtime).replace(' ', '<br>')
							);
							zoom.marker[marker].width = Math.ceil($('#zoom-marker-tooltip-' + marker).width());
							zoom.marker[marker].height = Math.ceil($('#zoom-marker-tooltip-' + marker).height());

							$('#zoom-marker-tooltip-' + marker).css({
								width: zoom.marker[marker].width +'px',
								top: ( (marker == 1) ? zoom.box.top+3 : zoom.box.bottom-zoom.marker[marker].height-3 )+'px',
								left:( (marker == 1) ? pos_relative_left - zoom.marker[marker].width : pos_relative_left )+'px'}
							);

							if (zoom.custom.zoomTimestamps === true) {
								$('#zoom-marker-tooltip-' + marker).fadeIn(500);
							}

							if (e.pageX == $('#zoom-marker-tooltip-' + marker).position().left) {
								$('#zoom-marker-tooltip-' + marker + '-arrow-right').css({ visibility:'hidden'});
							} else {
								$('#zoom-marker-tooltip-' + marker + '-arrow-left').css({ visibility:'hidden'});
							}

							/* make the excluded areas visible directly in that moment both markers are set */
							if (zoom.marker[1].placed && zoom.marker[2].placed) {
								zoom.marker.distance	= zoom.marker[1].left - zoom.marker[2].left;

								$('#zoom-excluded-area-1').css({
									position:'absolute',
									height:zoom.box.height+'px',
									top:zoom.box.top+'px',
									left: (zoom.marker.distance > 0) ? zoom.marker[1].left : zoom.box.left,
									width: (zoom.marker.distance > 0) ? zoom.box.right - zoom.marker[1].left : zoom.marker[1].left - zoom.box.left,
									display:'block'
								});

								$('#zoom-excluded-area-2').css({
									position:'absolute',
									height:zoom.box.height+'px',
									top:zoom.box.top+'px',
									left: (zoom.marker.distance < 0) ? zoom.marker[2].left : zoom.box.left,
									width: (zoom.marker.distance < 0) ? zoom.box.right - zoom.marker[2].left : zoom.marker[2].left - zoom.box.left,
									display:'block'
								});

								/* reposition both tooltips */
								$('#zoom-marker-tooltip-1').css({ left: $('#zoom-marker-1').position().left - ( (zoom.marker.distance > 0) ? 0 : $('#zoom-marker-tooltip-1').width() ) + 'px' });
								$('#zoom-marker-tooltip-1-arrow-left').css({ visibility: (($('#zoom-marker-tooltip-1').position().left < $('#zoom-marker-1').position().left ) ? 'hidden' : 'visible') });
								$('#zoom-marker-tooltip-1-arrow-right').css({ visibility: (($('#zoom-marker-tooltip-1').position().left < $('#zoom-marker-1').position().left ) ? 'visible' : 'hidden') });

								$('#zoom-marker-tooltip-2').css({ left: $('#zoom-marker-2').position().left - ( (zoom.marker.distance < 0) ? 0 : $('#zoom-marker-tooltip-2').width() ) + 'px' });
								$('#zoom-marker-tooltip-2-arrow-left').css({ visibility: (($('#zoom-marker-tooltip-2').position().left < $('#zoom-marker-2').position().left ) ? 'hidden' : 'visible') });
								$('#zoom-marker-tooltip-2-arrow-right').css({ visibility: (($('#zoom-marker-tooltip-2').position().left < $('#zoom-marker-2').position().left ) ? 'visible' : 'hidden') });

								/* change cursor */
								$('#zoom-box').css({cursor: 'pointer'});
							}

							/* make the marker draggable */
							$this.draggable({
								containment: '#zoom-box',
								axis: 'x',
								scroll: false,
								start:
									function(event, ui) {
										if (zoom.custom.zoomTimestamps == 'auto') {
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

										/* update the timestamp shown in tooltip */
										zoom.marker[marker].unixtime = Math.ceil( parseFloat(parseInt(zoom.graph.start) + (zoom.marker[marker].left - zoom.graph.left)*zoom.graph.secondsPerPixel));
										$('#zoom-marker-tooltip-value-' + marker).html(
											unixTime2Date(zoom.marker[marker].unixtime).replace(' ', '<br>')
										);

										zoom.marker[marker].width = $('#zoom-marker-tooltip-' + marker).width();

										/* update the execludedArea if both markers have been placed */
										if (zoom.marker[1].placed && zoom.marker[2].placed) {
											zoom.marker.distance = zoom.marker[marker].left - zoom.marker[secondmarker].left;

											if ( zoom.marker.distance > 0 ) {
												zoom.marker[marker].excludeArea = 'right';
												zoom.marker[secondmarker].excludeArea = 'left';
											} else {
												zoom.marker[marker].excludeArea = 'left';
												zoom.marker[secondmarker].excludeArea = 'right';
											}

											/* in that case we have to update the tooltip of both marker */
											$('#zoom-excluded-area-' + marker).css({ left: (zoom.marker.distance > 0) ? zoom.marker[marker].left : zoom.box.left, width: (zoom.marker.distance > 0) ? zoom.box.right - zoom.marker[marker].left : zoom.marker[marker].left - zoom.box.left});
											$('#zoom-marker-tooltip-' + marker).css({ left: zoom.marker[marker].left + ( (zoom.marker[marker].excludeArea == 'right') ? (0) : (-zoom.marker[marker].width) ) });
											$('#zoom-marker-tooltip-' + marker + '-arrow-left').css({ visibility: ( zoom.marker[marker].excludeArea == 'left' ? 'hidden' : 'visible') });
											$('#zoom-marker-tooltip-' + marker + '-arrow-right').css({ visibility: ( zoom.marker[marker].excludeArea == 'left' ? 'visible' : 'hidden') });

											$('#zoom-excluded-area-' + secondmarker).css({ left: (zoom.marker.distance > 0) ? zoom.box.left : zoom.marker[secondmarker].left, width: (zoom.marker.distance > 0) ? zoom.marker[secondmarker].left - zoom.box.left : zoom.box.right - zoom.marker[secondmarker].left});
											$('#zoom-marker-tooltip-' + secondmarker ).css({ left: zoom.marker[secondmarker].left + ( (zoom.marker[secondmarker].excludeArea == 'right') ? (0) : (-zoom.marker[secondmarker].width) ) });
											$('#zoom-marker-tooltip-' + secondmarker + '-arrow-left').css({ visibility: ( zoom.marker[secondmarker].excludeArea == 'left' ? 'hidden' : 'visible') });
											$('#zoom-marker-tooltip-' + secondmarker + '-arrow-right').css({ visibility: ( zoom.marker[secondmarker].excludeArea == 'left' ? 'visible' : 'hidden') });

										} else {
											/* let the tooltip follow its marker */
											$('#zoom-marker-tooltip-' + marker).css({ left: zoom.marker[marker].left -zoom.marker[marker].width });
										}

									},
								stop:
									function(event,ui) {
										/* hide all tooltip if we are in auto mode */
										if (zoom.custom.zoomTimestamps == 'auto') {
											$('.zoom-marker-tooltip').fadeOut(1000);
										}
									}

							});

							break;
						case 2:
							if (zoom.custom.zoom3rdMouseButton != false) {
								/* hide context menu if open */
								zoomContextMenu_hide();
								if (zoom.custom.zoom3rdMouseButton == 'zoom_in') {
									zoomAction_zoom_in();
								} else {
									zoomAction_zoom_out( zoom.custom.zoomOutFactor );
								}
							}
							break;
					}
					return false;

				});

			}
		}

		/*
		* executes a dynamic zoom in
		*/
		function zoomAction_zoom_in(){
			setCustomFilterActionActionAndDate();

			/* hide context menu if open */
			zoomContextMenu_hide();

			if (zoom.custom.zoomMode == 'quick') {
				var newGraphStartTime 	= (zoom.attr.action == 'left2right') 	? parseInt(parseInt(zoom.graph.start) + (zoom.attr.start -zoom.image.left -zoom.box.left)*zoom.graph.secondsPerPixel)
																				: parseInt(parseInt(zoom.graph.start) + (zoom.attr.end -zoom.image.left -zoom.box.left)*zoom.graph.secondsPerPixel);
				var newGraphEndTime 	= (zoom.attr.action == 'left2right')	? Math.ceil( parseFloat(newGraphStartTime + (zoom.attr.end-zoom.attr.start)*zoom.graph.secondsPerPixel))
																				: parseInt(newGraphStartTime + (zoom.attr.start-zoom.attr.end)*zoom.graph.secondsPerPixel);

				/* If the user only clicked on a graph then equal end and start date to ensure that we do not propergate NaNs */
				if (isNaN(newGraphStartTime) & isNaN(newGraphEndTime)) {
					return;
				} else if (isNaN(newGraphStartTime) & !isNaN(newGraphEndTime)) {
					newGraphStartTime = newGraphEndTime;
				} else if (!isNaN(newGraphStartTime) & isNaN(newGraphEndTime)){
					newGraphEndTime = newGraphStartTime;
				}
			} else {
				/* advanced mode has other requirements */
				/* first of, do nothing if not both marker have been positioned */
				if (!zoom.marker[1].placed | !zoom.marker[2].placed) {
					alert('NOTE: In advanced mode both markers have to be positioned first to define the period of time you want to zoom in.');
					return;
				} else {
					var newGraphStartTime = zoom.marker[((zoom.marker[1].unixtime > zoom.marker[2].unixtime)? 2 : 1 )].unixtime;
					var newGraphEndTime = zoom.marker[((zoom.marker[1].unixtime > zoom.marker[2].unixtime)? 1 : 2 )].unixtime;
				}
			}

			/* hide Zoom without destroying its container */
			$('#zoom-container').html('');

			if (zoom.options.inputfieldStartTime != '' & zoom.options.inputfieldEndTime != ''){
				zoom.initiator.attr('graph_start', newGraphStartTime);
				zoom.initiator.attr('graph_end', newGraphEndTime);

				/* execute zoom within 'tree view' or the 'preview view' */
				$('#' + zoom.options.inputfieldStartTime).val(unixTime2Date(newGraphStartTime));
				$('#' + zoom.options.inputfieldEndTime).val(unixTime2Date(newGraphEndTime));

				if (graph_start !== null && graph_end !== null) {
					zoom.attr.start = 'none';

					if (pageAction != 'graph') {
						graph_start = newGraphStartTime;
						graph_end = newGraphEndTime;

						initializeGraphs(true);
					}else{
						$('#graph_start').val(newGraphStartTime);
						$('#graph_end').val(newGraphEndTime);

						initializeGraph();
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
			onChange = $('#predefined_timespan').attr('onchange');
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
					break;
				case 4:
					return zoom_i18n_zoom_out + ' (' + zoom_i18n_zoom_4 + ')';
					break;
				case 8:
					return zoom_i18n_zoom_out + ' (' + zoom_i18n_zoom_8 + ')';
					break;
				case 16:
					return zoom_i18n_zoom_out + ' (' + zoom_i18n_zoom_16 + ')';
					break;
				case 32:
					return zoom_i18n_zoom_out + ' (' + zoom_i18n_zoom_32 + ')';
					break;
			}
		}

		/*
		* executes a static zoom out (as right click event)
		*/
		function zoomAction_zoom_out(multiplier){
			setCustomFilterActionActionAndDate();

			/* hide context menu if open */
			zoomContextMenu_hide();

			multiplier--;
			/* avoid that we can not zoom out anymore if start and end date will be equal */
			if (zoom.graph.timespan == 0) {
				zoom.graph.timespan = 1;
			}

			if (zoom.custom.zoomMode == 'quick' || !zoom.marker[1].placed || !zoom.marker[2].placed ) {
				if (zoom.custom.zoomOutPositioning == 'begin') {
					var newGraphStartTime = parseInt(zoom.graph.start);
					var newGraphEndTime   = parseInt(parseInt(zoom.graph.end) + (multiplier * zoom.graph.timespan));
				} else if (zoom.custom.zoomOutPositioning == 'end') {
					var newGraphStartTime = parseInt(parseInt(zoom.graph.start) - (multiplier * zoom.graph.timespan));
					var newGraphEndTime   = parseInt(zoom.graph.end);
				} else {
					if ($('#future').val() == 'on') {
						// define the new start and end time, so that the selected area will be centered per default
						var newGraphStartTime = parseInt(parseInt(zoom.graph.start) - (0.5 * multiplier * zoom.graph.timespan));
						var newGraphEndTime   = parseInt(parseInt(zoom.graph.end) + (0.5 * multiplier * zoom.graph.timespan));
					} else{
						var now = parseInt($.now() / 1000);
						var newGraphEndTime   = parseInt(parseInt(zoom.graph.end) + (0.5 * multiplier * zoom.graph.timespan));
						var newGraphStartTime = parseInt(parseInt(zoom.graph.start) - (0.5 * multiplier * zoom.graph.timespan));

						if (newGraphEndTime > now) {
							offset = newGraphEndTime - now;
							newGraphEndTime    = now;
							newGraphStartTime -= offset;
						}
					}
				}
			} else {
				var newGraphStartTime = zoom.marker[((zoom.marker[1].unixtime > zoom.marker[2].unixtime)? 2 : 1 )].unixtime;
				var newGraphEndTime = zoom.marker[((zoom.marker[1].unixtime > zoom.marker[2].unixtime)? 1 : 2 )].unixtime;
				var selectedTimeSpan = newGraphEndTime - newGraphStartTime;

				if (zoom.custom.zoomOutPositioning == 'begin') {
					newGraphEndTime = newGraphEndTime + multiplier * selectedTimeSpan;
				} else if (zoom.custom.zoomOutPositioning == 'end') {
					newGraphStartTime = newGraphStartTime - multiplier * selectedTimeSpan;
				} else {
					newGraphStartTime = parseInt(newGraphStartTime - 0.5 * multiplier * selectedTimeSpan);
					newGraphEndTime = parseInt(newGraphEndTime + 0.5 * multiplier * selectedTimeSpan);
				}
			}

			/* hide Zoom without destroying its container */
			$('#zoom-container').html('');

			if (zoom.options.inputfieldStartTime != '' & zoom.options.inputfieldEndTime != ''){
				zoom.initiator.attr('graph_start', newGraphStartTime);
				zoom.initiator.attr('graph_end', newGraphEndTime);

				/* execute zoom within 'tree view' or the 'preview view' */
				$('#' + zoom.options.inputfieldStartTime).val(unixTime2Date(newGraphStartTime));
				$('#' + zoom.options.inputfieldEndTime).val(unixTime2Date(newGraphEndTime));

				if (graph_start !== null && graph_end !== null) {
					zoom.attr.start = 'none';

					if (pageAction != 'graph') {
						graph_start = newGraphStartTime;
						graph_end = newGraphEndTime;

						initializeGraphs(true);
					}else{
						$('#graph_start').val(newGraphStartTime);
						$('#graph_end').val(newGraphEndTime);

						initializeGraph();
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

		/*
		* updates the css parameters of the zoom area to reflect user's interaction
		*/
		function zoomAction_draw(event) {
			if (zoom.attr.start == 'none') { return; }

			/* mouse has been moved from right to left */
			if ((event.pageX-zoom.attr.start)<0) {
				zoom.attr.action = 'right2left';
				zoom.attr.end = (event.pageX < zoom.image.left+zoom.box.left) ? zoom.image.left+zoom.box.left : event.pageX;
				$('#zoom-area').css({ left:zoom.attr.end-zoom.image.left+'px', width:Math.abs(zoom.attr.start-zoom.attr.end-1)+'px' });
			/* mouse has been moved from left to right*/
			} else {
				zoom.attr.action = 'left2right';
				zoom.attr.end = (event.pageX > zoom.image.left+zoom.box.right) ? zoom.image.left+zoom.box.right : event.pageX;
				$('#zoom-area').css({ left:zoom.attr.start-zoom.image.left+'px', width:Math.abs(zoom.attr.end-zoom.attr.start)+'px' });
			}
		}

		/**
		 *
		 * @access public
		 * @return void
		 **/
		function zoomContextMenu_init(){

			/* sync menu with cookie parameters */
			$('.zoomContextMenuAction__set_zoomMode__' + zoom.custom.zoomMode).addClass('zoom-menu-highlight');
			$('.zoomContextMenuAction__set_zoomTimestamps__' + ((zoom.custom.zoomTimestamps == 'auto') ? 'auto' : ((zoom.custom.zoomTimestamps) ? 'on' : 'off' ))).addClass('zoom-menu-highlight');
			$('.zoomContextMenuAction__set_zoomOutFactor__' + zoom.custom.zoomOutFactor).addClass('zoom-menu-highlight');
			$('.zoomContextMenuAction__set_zoomOutPositioning__' + zoom.custom.zoomOutPositioning).addClass('zoom-menu-highlight');
			$('.zoomContextMenuAction__set_zoom3rdMouseButton__' + ((zoom.custom.zoom3rdMouseButton === false) ? 'off' : zoom.custom.zoom3rdMouseButton) ).addClass('zoom-menu-highlight');
			$('.zoomContextMenuAction__zoom_out').text(getZoomOutFactorText(zoom.custom.zoomOutFactor));

			if (zoom.custom.zoomMode == 'quick') {
				$('.advanced_mode').hide();
			}

			/* init click on events */
			$('[class*=zoomContextMenuAction__]').off().on('click', function() {
				var zoomContextMenuAction = false;
				var zoomContextMenuActionValue = false;
				var classList = $.trim($(this).attr('class')).split(/\s+/);

				$.each( classList, function(index, item){
					if ( item.search('zoomContextMenuAction__') != -1) {
						zoomContextMenuActionList = item.replace('zoomContextMenuAction__', '').split('__');
						zoomContextMenuAction = zoomContextMenuActionList[0];
						if (zoomContextMenuActionList[1] == 'undefined' || zoomContextMenuActionList[1] == 'off') {
							zoomContextMenuActionValue = false;
						} else if (zoomContextMenuActionList[1] == 'on') {
							zoomContextMenuActionValue = true;
						} else {
							zoomContextMenuActionValue = zoomContextMenuActionList[1];
						}
						return( false );
					}
				});

				if ( zoomContextMenuAction ) {
					if ( zoomContextMenuAction.substring(0,8) == 'set_zoom') {
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
						if (zoom.custom.zoomMode == 'quick') {
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
		};

		/**
		 *
		 * @access public
		 * @return void
		 **/
		function zoomContextMenuAction_set(object, value){
			switch(object) {
				case 'mode':
					if ( zoom.custom.zoomMode != value) {
						zoom.custom.zoomMode = value;

						$('[class*=zoomContextMenuAction__set_zoomMode__]').toggleClass('zoom-menu-highlight');

						if (value == 'quick') {
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
						zoomAction_init(zoom.initiator);

					}
					break;
				case 'timestamps':
					if ( zoom.custom.zoomTimestamps != value) {
						zoom.custom.zoomTimestamps = value;
						storage.set(zoom.options.cookieName, serialize(zoom.custom));
						$('[class*=zoomContextMenuAction__set_zoomTimestamps__]').removeClass('zoom-menu-highlight');
						$('.zoomContextMenuAction__set_zoomTimestamps__' + ((zoom.custom.zoomTimestamps == 'auto') ? 'auto' : ((zoom.custom.zoomTimestamps) ? 'on' : 'off' ))).addClass('zoom-menu-highlight');

						/* make them visible only for mode 'Always On' */
						if (zoom.custom.zoomTimestamps === true) {
							$('.zoom-marker-tooltip').fadeIn(500);
						} else {
							$('.zoom-marker-tooltip').fadeOut(500);
						}
					}
					break;
				case 'outfactor':
					if ( zoom.custom.zoomOutFactor != value) {
						zoom.custom.zoomOutFactor = value;
						storage.set(zoom.options.cookieName, serialize(zoom.custom));
						$('[class*=zoomContextMenuAction__set_zoomOutFactor__]').removeClass('zoom-menu-highlight');
						$('.zoomContextMenuAction__set_zoomOutFactor__' + value).addClass('zoom-menu-highlight');
						$('.zoomContextMenuAction__zoom_out').text(getZoomOutFactorText(value));
					}
					break;
				case 'outpositioning':
					if ( zoom.custom.zoomOutPositioning != value) {
						zoom.custom.zoomOutPositioning = value;
						storage.set(zoom.options.cookieName, serialize(zoom.custom));
						$('[class*=zoomContextMenuAction__set_zoomOutPositioning__]').removeClass('zoom-menu-highlight');
						$('.zoomContextMenuAction__set_zoomOutPositioning__' + value).addClass('zoom-menu-highlight');
					}
					break;
				case '3rdmousebutton':
					if ( zoom.custom.zoom3rdMouseButton != value) {
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
					if (value == undefined) {
						value = zoom.custom.zoomOutFactor;
					}
					zoomAction_zoom_out(value);

					break;
				case 'zoom_in':
					zoomAction_zoom_in();

					break;
				case 'copy':
					$('#zoom-textarea').html('<img src="data:image/png;base64,'+btoa(unescape(encodeURIComponent(zoom.image.data)))+'" width="'+zoom.image.width+'" height="'+zoom.image.height+'">').select();

					try {
						var successful = document.execCommand('copy');
					} catch (err) {
						alert('Unsupported Browser');
					}
					return false;

					break;
				case 'save':
					var arraybuffer = new ArrayBuffer(zoom.image.data.length);
					var view = new Uint8Array(arraybuffer);
					for (var i = 0; i < zoom.image.data.length; i++) {
						view[i] = zoom.image.data.charCodeAt(i) & 0xff;
					}

					try {
						var blob = new Blob([arraybuffer], {type: 'application/octet-stream'});
					} catch (e) {
						var bb = new (window.WebKitBlobBuilder || window.MozBlobBuilder);
						bb.append(arraybuffer);
						var blob = bb.getBlob('application/octet-stream');
					}

					if (window.navigator && window.navigator.msSaveOrOpenBlob) {
						window.navigator.msSaveOrOpenBlob(blob, zoom.image.name);
					} else {
						var objectUrl = URL.createObjectURL(blob);
						$('#zoom-image').removeAttr('target').attr({'download':zoom.image.name, 'href':objectUrl }).get(0).click();
					}

					break;
				case 'newTab':
					var url = zoom.attr.urlPath + 'graph_image.php?local_graph_id=' + zoom.image.id;
					if (zoom.image.rra_id > 0) {
						url += '&rra_id='+zoom.image.rra_id;
					}
					url += '&graph_start=' + zoom.graph.start + '&graph_end=' + zoom.graph.end + '&graph_width=' + zoom.graph.width + '&graph_height=' + zoom.graph.height + ( (zoom.image.legend === true) ? '' : '&graph_nolegend=true' ) + '&disable_cache=true';
					$('#zoom-image').removeAttr('download').attr({ 'href':url, 'target': '_bank' }).get(0).click();

					break;
				case 'link':
					var url = zoom.attr.origin + ((zoom.attr.urlPath == '') ? '/' : zoom.attr.urlPath) + 'graph_image.php?local_graph_id=' + zoom.image.id + '&graph_start=' + zoom.graph.start + '&graph_end=' + zoom.graph.end + '&graph_width=' + zoom.graph.width + '&graph_height=' + zoom.graph.height + ( (zoom.image.legend === true) ? '' : '&graph_nolegend=true' ) + '&disable_cache=true';
					$('#zoom-textarea').html(url).select();
					try {
						var successful = document.execCommand('copy');
					} catch (err) {
						alert('Unsupported Browser');
					}
					return false;

					break;
			}
		}

		function zoomContextMenu_show(e){
			var menu_y_pos			= e.pageY;
			var menu_y_offset		= 5;
			var menu_x_pos			= e.pageX;
			var menu_x_offset		= 5;

			var window_size_x_1		= $(document).scrollLeft();
			var window_size_x_2		= $(window).width() + $(document).scrollLeft();
			var window_size_y_1		= $(document).scrollTop();
			var window_size_y_2		= $(window).height() + $(document).scrollTop();

			var menu_height			= $('.zoom-menu').outerHeight();
			var menu_width			= $('.zoom-menu').outerWidth();
			var menu_width_level_1	= Math.abs($('.zoom-menu .first_li span').outerWidth());
			var menu_width_level_2	= Math.abs($('.zoom-menu .sec_li span').outerWidth());
			var menu_height_level_1	= Math.abs($('.zoom-menu .first_li span').outerHeight());
			var menu_height_level_2	= Math.abs($('.zoom-menu .sec_li span').outerHeight());

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

			$('#zoom-menu').css({ left: menu_x_pos+menu_x_offset, top: menu_y_pos+menu_y_offset, zIndex: '101' }).show();
		};

		function zoomContextMenu_hide(){
			$('#zoom-menu').hide();
		}

		function zoomContextMenu_toggle(e){
			($('#zoom-menu').css('display') == 'none') ? zoomContextMenu_show(e) : zoomContextMenu_hide();
		}

	};

})(jQuery);

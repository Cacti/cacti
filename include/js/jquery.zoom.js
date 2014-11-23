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

/* requirements:
	jQuery 1.7.x or above
	jQuery UI 1.8.x or above
	jQuery cookie plugin
*/

(function($){
	$.fn.zoom = function(options) {

		/* +++++++++++++++++++++++ Global Variables +++++++++++++++++++++++++ */

		// JS calculates in relation to the localization of the client - we have to take care of that, but only for 0.8.8
		var clientTime = new Date();
		var clientTimeOffset = clientTime.getTimezoneOffset()*60*(-1);			//requires -1, because PHP return the opposite
		var timeOffset = 0;

		// default values of the different options being offered
		var defaults = {
			inputfieldStartTime	: '',                                           // ID of the input field that contains the start date
			inputfieldEndTime	: '',                                           // ID of the input field that contains the end date
			submitButton		: 'button_refresh_x',                           // ID of the submit button
			cookieName			: 'cacti_zoom',                                 // default name required for session cookie
			serverTimeOffset	: 0												// JS calculates in relation to the localization of the browser :/ - only required for 0.8.8
		};

		// define global variables / objects here
		var zoom = {
			// "initiator" is the element that initiates Zoom
			initiator: $(this),
			// "image" means the image tag and its properties
			image: { top:0, left:0, width:0, height:0 },
			// "graph" stands for the rrdgraph itself excluding legend, graph title etc.
			graph: { timespan:0, secondsPerPixel:0 },
			// "box" describes the area in front of the graph whithin jQueryZoom will allow interaction
			box: { top:0, left:0, right:0, width:0, height:0 },
			// "markers" are selectors useable within the advanced mode
			marker: { 1 : { placed:false }, 2 : { placed:false} },
			// "custom" holds the local configuration done by the user
			custom: {},
			// "options" contains the start input parameters
			options: $.extend(defaults, options),
			// "attributes" holds all values that will describe the selected area
			attr: { activeElement:'', start:'none', end:'none', action:'left2right', location: window.location.href.split("?") }
		};


		/* ++++++++++++++++++++++++ Initialization ++++++++++++++++++++++++++ */

		// use a cookie to support local settings
		zoom.custom =  $.cookie(zoom.options.cookieName) ? unserialize( $.cookie(zoom.options.cookieName) ) : {};
		if(zoom.custom.zoomMode == undefined) zoom.custom.zoomMode = 'quick';
		if(zoom.custom.zoomOutPositioning == undefined) zoom.custom.zoomOutPositioning = 'center';
		if(zoom.custom.zoomOutFactor == undefined) zoom.custom.zoomOutFactor = '2';
		if(zoom.custom.zoomMarkers == undefined) zoom.custom.zoomMarkers = true;
		if(zoom.custom.zoomTimestamps == undefined) zoom.custom.zoomTimestamps = 'auto';
		if(zoom.custom.zoom3rdMouseButton == undefined) zoom.custom.zoom3rdMouseButton = false;

		// create or update a session cookie
		$.cookie( zoom.options.cookieName, serialize(zoom.custom), {expires: null} );

		// support jQuery's concatination
		return this.each(function() { zoom_init( $(this) ); });


		/* ++++++++++++++++++++ Universal Functions +++++++++++++++++++++++++ */

		/**
		 * checks if an image has been already loaded or if the link is broken
		 **/
		function isReady(image){
			var $this = image;

			if ($this.width() > 0) {
				return true;
			}
			
			return false;
		}

		/**
		 * splits off the parameters of a given url
		 **/
		function getUrlVars(url) {
			var parameters = [], name, value;

			urlBaseAndParameters = url.split("?");
			urlBase = urlBaseAndParameters[0];
			urlParameters = urlBaseAndParameters[1].split("&");
			parameters["urlBase"] = urlBase;

			for(var i=0; i<urlParameters.length; i++) {
				parameter = urlParameters[i].split("=");
				parameters[parameter[0].replace(/^graph_/, "")] = $.isNumeric(parameter[1]) ? +parameter[1] : parameter[1];
			}
			return parameters;
		}

		/**
		 * transforms an object into a comma separated string of key-value pairs
		 **/
		function serialize(object){
			var str = "";
			for(var key in object) { str += (key + '=' + object[key] + ','); }
			return str.slice(0, -1);
		}

		/**
		 * transforms a comma separated string of key-values pairs into an object
		 * including a change of the value type from string to boolean or numeric if reasonable.
		 **/
		function unserialize(string){
			var obj = new Array();
			pairs = string.split(',');
			for(var i=0; i<pairs.length; i++) {
				pair = pairs[i].split("=");
				if(pair[1] == "true") {
					pair[1] = true;
				}else if(pair[1] == "false") {
					pair[1] = false;
				}else if($.isNumeric(pair[1])) {
					pair[1] = +pair[1];
				}
				obj[pair[0]] = pair[1];
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
			if(zoom.options.serverTimeOffset > clientTimeOffset ) {
				timeOffset = (zoom.options.serverTimeOffset - clientTimeOffset)*1000;
			}else {
				timeOffset = (clientTimeOffset - zoom.options.serverTimeOffset)*1000*(-1);
			}

			var $this = image;
			$this.mouseenter(
				function(){
					if(zoom.attr.activeElement == '') {
						zoom.attr.activeElement = $(this).attr('id');
						zoomFunction_init($this);
					// focusing another image will trigger a reset of Zoom
					}else if(zoom.attr.activeElement != $(this).attr('id')) {
						zoom.attr.activeElement = $(this).attr('id');
						zoomFunction_init($this);
					}
				}
			);
		}

		function zoomFunction_sleep(milliseconds) {
			var start = new Date().getTime();
			for (var i = 0; i < 1e7; i++) {
				if ((new Date().getTime() - start) > milliseconds){
					break;
				}
			}
		}

		function zoomFunction_init(image) {
			var $this = image;
			var image_loaded = isReady($this);

			// exit if image has not been already loaded or if image is not available
			if (image_loaded == false) {
				var i = 0;
				var sleep = 100;

				while (i < 100) {
					zoomFunction_sleep(sleep);

					image_loaded = isReady($this);

					if (image_loaded) {
						break;
					}
					i++;
				}
			}

			if (image_loaded) {
				// update zoom.image object with the attributes of this image
				zoom.image.width	= parseInt($this.width());
				zoom.image.height	= parseInt($this.height());
				zoom.image.top	= parseInt($this.offset().top);
				zoom.image.left	= parseInt($this.offset().left);
			} else {
				return;
			}

			// get all graph parameters and merge results with zoom.graph object
			$.extend(zoom.graph, getUrlVars( $this.attr("src") ));
			zoom.graph.timespan			= zoom.graph.end - zoom.graph.start;
			zoom.graph.secondsPerPixel 	= zoom.graph.timespan/zoom.graph.width;

			if((zoom.graph.title_font_size <= 0) || (zoom.graph.title_font_size == "")) {
				zoom.graph.title_font_size = 10;
			}

			if(zoom.graph.nolegend != undefined) {
				zoom.graph.title_font_size	*= .70;
			}

			// update all zoom box attributes. Unfortunately we have to use that best fit way
			// to support RRDtool 1.2 and below. With RRDtool 1.3 or higher there would be a
			// much more elegant solution available. (see RRDdtool graph option "graphv")
			zoom.box.width		= zoom.graph.width;
			zoom.box.height		= zoom.graph.height;

			if(zoom.graph.title_font_size == null) {
				zoom.box.top = 32 - 1;
			}else {
				//default multiplier
				var multiplier = 2.4;
				// array of "best fit" multipliers
				multipliers = new Array("-5", "-2", "0", "1.7", "1.6", "1.7", "1.8", "1.9", "2", "2", "2.1", "2.1", "2.2", "2.2", "2.3", "2.3", "2.3", "2.3", "2.3");
				if(multipliers[Math.round(zoom.graph.title_font_size)] != null) {
					multiplier = multipliers[Math.round(zoom.graph.title_font_size)];
				}
				zoom.box.top = zoom.image.top + parseInt(Math.abs(zoom.graph.title_font_size) * multiplier) + 15;
			}

			zoom.box.bottom = zoom.box.top + zoom.box.height;
			zoom.box.right	= zoom.image.left + zoom.image.width - 30;
			zoom.box.left	= zoom.box.right - zoom.graph.width;

			// add all additional HTML elements to the DOM if necessary and register
			// the individual events needed. Once added we will only reset
			// and reposition these elements.

			// add the "zoomBox"
			if($("#zoom-box").length == 0) {
				// Please note: IE does not fire hover or click behaviors on completely transparent elements.
				// Use a background color and set opacity to 1% as a workaround.(see CSS file)
				$("<div id='zoom-box'></div>").appendTo("body");
			}

			// add the "zoomSelectedArea"
			if($("#zoom-area").length == 0) {
				$("<div id='zoom-area'></div>").appendTo("body");
			}

			// add two markers for the advanced mode
			if($("#zoom-marker-1").length == 0) {
				$('<div id="zoom-excluded-area-1" class="zoom-area-excluded"></div>').appendTo("body");
				$('<div class="zoom-marker" id="zoom-marker-1"><div class="zoom-marker-arrow-down"></div><div class="zoom-marker-arrow-up"></div></div>').appendTo("body");
				$('<div id="zoom-marker-tooltip-1" class="zoom-marker-tooltip"><div id="zoom-marker-tooltip-1-arrow-left" class="zoom-marker-tooltip-arrow-left"><div id="zoom-marker-tooltip-1-arrow-left-inner" class="zoom-marker-tooltip-arrow-left-inner"></div></div><span id="zoom-marker-tooltip-value-1" class="zoom-marker-tooltip-value">-</span><div id="zoom-marker-tooltip-1-arrow-right" class="zoom-marker-tooltip-arrow-right"><div id="zoom-marker-tooltip-1-arrow-right-inner" class="zoom-marker-tooltip-arrow-right-inner"></div></div></div>').appendTo('body');
			}
			if($("#zoom-marker-2").length == 0) {
				$('<div id="zoom-excluded-area-2" class="zoom-area-excluded"></div>').appendTo("body");
				$('<div class="zoom-marker" id="zoom-marker-2"><div class="zoom-marker-arrow-down"></div><div class="zoom-marker-arrow-up"></div></div>').appendTo("body");
				$('<div id="zoom-marker-tooltip-2" class="zoom-marker-tooltip"><div id="zoom-marker-tooltip-2-arrow-left" class="zoom-marker-tooltip-arrow-left"><div id="zoom-marker-tooltip-1-arrow-left-inner" class="zoom-marker-tooltip-arrow-left-inner"></div></div><span id="zoom-marker-tooltip-value-2" class="zoom-marker-tooltip-value">-</span><div id="zoom-marker-tooltip-2-arrow-right" class="zoom-marker-tooltip-arrow-right"><div id="zoom-marker-tooltip-2-arrow-right-inner" class="zoom-marker-tooltip-arrow-right-inner"></div></div></div>').appendTo('body');
			}
			zoom.marker[1].placed = false;
			zoom.marker[2].placed = false;

			// add the context (right click) menu
			if($("#zoom-menu").length == 0) {
				$('<div id="zoom-menu" class="zoom-menu">'
					+ '<div class="first_li">'
					+ 		'<div class="ui-icon ui-icon-zoomin zoomContextMenuAction__zoom_in"></div>'
					+       '<span class="zoomContextMenuAction__zoom_in">Zoom In</span>'
					+ '</div>'
					+ '<div class="first_li">'
					+ 		'<div class="ui-icon ui-icon-zoomout zoomContextMenuAction__zoom_out"></div>'
					+ 		'<span class="zoomContextMenuAction__zoom_out">Zoom Out (2x)</span>'
					+ 		'<div class="inner_li advanced_mode">'
					+ 			'<span class="zoomContextMenuAction__zoom_out__2">2x</span>'
					+ 			'<span class="zoomContextMenuAction__zoom_out__4">4x</span>'
					+ 			'<span class="zoomContextMenuAction__zoom_out__8">8x</span>'
					+ 			'<span class="zoomContextMenuAction__zoom_out__16">16x</span>'
					+ 			'<span class="zoomContextMenuAction__zoom_out__32">32x</span>'
					+ 		'</div>'
					+ '</div>'
					+ '<div class="sep_li"></div>'
					+ '<div class="first_li">'
					+ 		'<div class="ui-icon ui-icon-empty"></div><span>Zoom Mode</span>'
					+ 		'<div class="inner_li">'
					+ 			'<span class="zoomContextMenuAction__set_zoomMode__quick">Quick</span>'
					+ 			'<span class="zoomContextMenuAction__set_zoomMode__advanced">Advanced</span>'
					+ 		'</div>'
					+ '</div>'
					+ '<div class="first_li advanced_mode">'
					+ 		'<div class="ui-icon ui-icon-wrench"></div><span>Settings</span>'
					+ 			'<div class="inner_li">'
					+ 				'<div class="sec_li" style="display:none;"><span>Markers</span>'
					+ 					'<div class="inner_li advanced_mode">'
					+ 						'<span class="zoomContextMenuAction__set_zoomMarkers__on">Enabled</span>'
					+ 						'<span class="zoomContextMenuAction__set_zoomMarkers__off">Disabled</span>'
					+ 					'</div>'
					+ 				'</div>'
					+ 				'<div class="sec_li"><span>Timestamps</span></span>'
					+ 					'<div class="inner_li advanced_mode">'
					+ 						'<span class="zoomContextMenuAction__set_zoomTimestamps__on">Always On</span>'
					+ 						'<span class="zoomContextMenuAction__set_zoomTimestamps__auto">Auto</span>'
					+ 						'<span class="zoomContextMenuAction__set_zoomTimestamps__off">Always Off</span>'
					+ 					'</div>'
					+ 				'</div>'
					+ 				'<div class="sep_li"></div>'
					+ 				'<div class="sec_li"><span>Zoom Out Factor</span>'
					+ 					'<div class="inner_li advanced_mode">'
					+ 						'<span class="zoomContextMenuAction__set_zoomOutFactor__2">2x</span>'
					+ 						'<span class="zoomContextMenuAction__set_zoomOutFactor__4">4x</span>'
					+ 						'<span class="zoomContextMenuAction__set_zoomOutFactor__8">8x</span>'
					+ 						'<span class="zoomContextMenuAction__set_zoomOutFactor__16">16x</span>'
					+ 						'<span class="zoomContextMenuAction__set_zoomOutFactor__32">32x</span>'
					+ 					'</div>'
					+ 				'</div>'
					+ 				'<div class="sec_li"><span>Zoom Out Positioning</span>'
					+ 					'<div class="inner_li advanced_mode">'
					+ 						'<span class="zoomContextMenuAction__set_zoomOutPositioning__begin">Begin with</span>'
					+ 						'<span class="zoomContextMenuAction__set_zoomOutPositioning__center">Center</span>'
					+ 						'<span class="zoomContextMenuAction__set_zoomOutPositioning__end">End with</span>'
					+ 					'</div>'
					+ 				'</div>'
					+ 				'<div class="sec_li"><span>3rd Mouse Button</span>'
					+ 					'<div class="inner_li advanced_mode">'
					+ 						'<span class="zoomContextMenuAction__set_zoom3rdMouseButton__zoom_in">Zoom in</span>'
					+ 						'<span class="zoomContextMenuAction__set_zoom3rdMouseButton__zoom_out">Zoom out</span>'
					+ 						'<span class="zoomContextMenuAction__set_zoom3rdMouseButton__off">Disabled</span>'
					+ 					'</div>'
					+ 				'</div>'
					+ 			'</div>'
					+ 		'</div>'
					+ '<div class="sep_li"></div>'
					+ '<div class="first_li">'
					+ 		'<div class="ui-icon ui-icon-close zoomContextMenuAction__close"></div><span class="zoomContextMenuAction__close">Close</span>'
					+ '</div>').appendTo('body');
			}
			zoomElemtents_reset()
			zoomContextMenu_init();
			zoomAction_init(image);
		}

		/**
		 * resets all elements of Zoom
		 **/
		function zoomElemtents_reset() {
			zoom.marker = { 1 : { placed:false }, 2 : { placed:false} };
			$('div[id^="zoom-"]').not('#zoom-menu').each( function () {
				$(this).removeAttr('style');
			});
			$("#zoom-box").off();
			$("#zoom-box").css({ cursor:'crosshair', width:zoom.box.width + 'px', height:zoom.box.height + 'px', top:zoom.box.top+'px', left:zoom.box.left+'px' });
			$("#zoom-box").bind('contextmenu', function(e) { zoomContextMenu_show(e); return false;} );
			$("#zoom-area").off().css({ top:zoom.box.top+'px', height:zoom.box.height+'px' });
			$(".zoom-area-excluded").off();
			$(".zoom-area-excluded").bind('contextmenu', function(e) { zoomContextMenu_show(e); return false;} );
			$(".zoom-area-excluded").bind('click', function(e) { zoomContextMenu_hide(); return false;} );
			$(".zoom-marker-arrow-up").css({ top:(zoom.box.height-6) + 'px' });
			$(".zoom-marker-tooltip-value").disableSelection();
		}

		/*
		* registers all the different mouse click event handler
		*/
		function zoomAction_init(image) {

			if(zoom.custom.zoomMode == 'quick') {
				$("#zoom-box").off("mousedown").on("mousedown", function(e) {
					switch(e.which) {
						/* clicking the left mouse button will initiates a zoom-in */
						case 1:
							zoomContextMenu_hide();
							// reset the zoom area
							zoom.attr.start = e.pageX;
							if(zoom.custom.zoomMode != 'quick') {
								$("#zoom-marker-1").css({ height:zoom.box.height+'px', top:zoom.box.top+'px', left:zoom.attr.start+'px', display:'block' });
								$("#zoom-marker-tooltip-1").css({ top:zoom.box.top+'px', left:zoom.attr.start+'px'});
							}
							$("#zoom-box").css({ cursor:'e-resize' });
							$("#zoom-area").css({ width:'0px', left:zoom.attr.start+'px' });
						break;
					}
				});

				/* register the mouse up event */
				$("#zoom-box").off("mouseup").on("mouseup", function(e) {
					switch(e.which) {
						/* leaving the left mouse button will execute a zoom in */
						case 1:
							if(zoom.attr.start != 'none') {
								zoomAction_zoom_in();
							}
						break;
					}
				});

				/* register the mouse up event */
				$("#zoom-area").off("mouseup").on("mouseup", function(e) {
					switch(e.which) {
						/* leaving the left mouse button will execute a zoom in */
						case 1:
							if(zoom.attr.start != 'none') {
								zoomAction_zoom_in();
							}
						break;
					}
				});

				/* stretch the zoom area in that direction the user moved the mouse pointer */
				$("#zoom-box").mousemove( function(e) { zoomAction_draw(e) } );

				/* stretch the zoom area in that direction the user moved the mouse pointer.
				   That is required to get it working faultlessly with Opera, IE and Chrome	*/
				$("#zoom-area").mousemove( function(e) { zoomAction_draw(e); } );

				/* moving the mouse pointer quickly will avoid that the mousemove event has enough time to actualize the zoom area */
				$("#zoom-box").mouseout( function(e) { zoomAction_draw(e) } );

			}else{
				/* welcome to the advanced mode ;) */
				$("#zoom-box").off("mousedown").on("mousedown", function(e) {
					switch(e.which) {
						case 1:
							/* hide context menu if open */
							zoomContextMenu_hide();

							/* find out which marker has to be added */
							if(zoom.marker[1].placed && zoom.marker[2].placed) {
								zoomAction_zoom_in();
								return;
							}else {
								var marker = zoom.marker[1].placed ? 2 : 1;
								var secondmarker = (marker == 1) ? 2 : 1;
							}

							/* select marker */
							var $this = $("#zoom-marker-" + marker);

							/* place the marker and make it visible */
							$this.css({ height:zoom.box.height+'px', top:zoom.box.top+'px', left:e.pageX+'px', display:'block' });
							zoom.marker[marker].placed = true;
							zoom.marker[marker].left = e.pageX;

							/* place the marker's tooltip, update its value and make it visible if necessary (Setting: "Always On") */
							zoom.marker[marker].unixtime = parseInt(parseInt(zoom.graph.start) + (e.pageX + 1 - zoom.box.left)*zoom.graph.secondsPerPixel);
							$("#zoom-marker-tooltip-value-" + marker).html(
								unixTime2Date(zoom.marker[marker].unixtime).replace(" ", "<br>")
							);
							zoom.marker[marker].width = $("#zoom-marker-tooltip-" + marker).width();

							$("#zoom-marker-tooltip-" + marker).css({
								top: ( (marker == 1) ? zoom.box.top+3 : zoom.box.bottom-30 )+'px',
								left:( (marker == 1) ? e.pageX - zoom.marker[marker].width : e.pageX )+'px'}
							);

							if(zoom.custom.zoomTimestamps === true) {
								$("#zoom-marker-tooltip-" + marker).fadeIn(500);
							}

							if(e.pageX == $("#zoom-marker-tooltip-" + marker).position().left) {
								$("#zoom-marker-tooltip-" + marker + "-arrow-right").css({ visibility:'hidden'});
							}else {
								$("#zoom-marker-tooltip-" + marker + "-arrow-left").css({ visibility:'hidden'});
							}

							/* make the excluded areas visible directly in that moment both markers are set */
							if(zoom.marker[1].placed && zoom.marker[2].placed) {
								zoom.marker.distance	= zoom.marker[1].left - zoom.marker[2].left;

								$("#zoom-excluded-area-1").css({
									height:zoom.box.height+'px',
									top:zoom.box.top+'px',
									left: (zoom.marker.distance > 0) ? zoom.marker[1].left : zoom.box.left,
									width: (zoom.marker.distance > 0) ? zoom.box.right - zoom.marker[1].left : zoom.marker[1].left - zoom.box.left,
									display:'block'
								});

								$("#zoom-excluded-area-2").css({
									height:zoom.box.height+'px',
									top:zoom.box.top+'px',
									left: (zoom.marker.distance < 0) ? zoom.marker[2].left : zoom.box.left,
									width: (zoom.marker.distance < 0) ? zoom.box.right - zoom.marker[2].left : zoom.marker[2].left - zoom.box.left,
									display:'block'
								});

								/* reposition both tooltips */
								$("#zoom-marker-tooltip-1").css({ left: $("#zoom-marker-1").position().left - ( (zoom.marker.distance > 0) ? 0 : $("#zoom-marker-tooltip-1").width() ) + 'px' });
								$("#zoom-marker-tooltip-1-arrow-left").css({ visibility: (($("#zoom-marker-tooltip-1").position().left < $("#zoom-marker-1").position().left ) ? 'hidden' : 'visible') });
								$("#zoom-marker-tooltip-1-arrow-right").css({ visibility: (($("#zoom-marker-tooltip-1").position().left < $("#zoom-marker-1").position().left ) ? 'visible' : 'hidden') });

								$("#zoom-marker-tooltip-2").css({ left: $("#zoom-marker-2").position().left - ( (zoom.marker.distance < 0) ? 0 : $("#zoom-marker-tooltip-2").width() ) + 'px' });
								$("#zoom-marker-tooltip-2-arrow-left").css({ visibility: (($("#zoom-marker-tooltip-2").position().left < $("#zoom-marker-2").position().left ) ? 'hidden' : 'visible') });
								$("#zoom-marker-tooltip-2-arrow-right").css({ visibility: (($("#zoom-marker-tooltip-2").position().left < $("#zoom-marker-2").position().left ) ? 'visible' : 'hidden') });

								/* change cursor */
								$("#zoom-box").css({cursor: 'pointer'});
							}

							/* make the marker draggable */
							$this.draggable({
								containment:[ zoom.box.left-1, 0 , zoom.box.left+parseInt(zoom.box.width), 0 ],
								axis: "x",
								start:
									function(event, ui) {
										if(zoom.custom.zoomTimestamps == "auto") {
											$(".zoom-marker-tooltip").fadeIn(500);
										}
									},
								drag:
									function(event, ui) {

										if(ui.position["left"] < zoom.box.left) {
											zoom.marker[marker].left = zoom.box.left;
										}else if(ui.position["left"] > zoom.box.right) {
											zoom.marker[marker].left = zoom.box.right;
										}else {
										zoom.marker[marker].left = ui.position["left"];
										}

										/* update the timestamp shown in tooltip */
										zoom.marker[marker].unixtime = parseInt(parseInt(zoom.graph.start) + (zoom.marker[marker].left + 1 - zoom.box.left)*zoom.graph.secondsPerPixel);
										$("#zoom-marker-tooltip-value-" + marker).html(
											unixTime2Date(zoom.marker[marker].unixtime).replace(" ", "<br>")
										);

										zoom.marker[marker].width = $("#zoom-marker-tooltip-" + marker).width();

										/* update the execludedArea if both markers have been placed */
										if(zoom.marker[1].placed && zoom.marker[2].placed) {
											zoom.marker.distance = zoom.marker[marker].left - zoom.marker[secondmarker].left;

											if( zoom.marker.distance > 0 ) {
												zoom.marker[marker].excludeArea = 'right';
												zoom.marker[secondmarker].excludeArea = 'left';
											}else {
												zoom.marker[marker].excludeArea = 'left';
												zoom.marker[secondmarker].excludeArea = 'right';
											}

											/* in that case we have to update the tooltip of both marker */
											$("#zoom-excluded-area-" + marker).css({ left: (zoom.marker.distance > 0) ? zoom.marker[marker].left : zoom.box.left, width: (zoom.marker.distance > 0) ? zoom.box.right - zoom.marker[marker].left : zoom.marker[marker].left - zoom.box.left});
											$("#zoom-marker-tooltip-" + marker).css({ left: zoom.marker[marker].left + ( (zoom.marker[marker].excludeArea == 'right') ? (0) : (-zoom.marker[marker].width) ) });
											$("#zoom-marker-tooltip-" + marker + "-arrow-left").css({ visibility: ( zoom.marker[marker].excludeArea == 'left' ? 'hidden' : 'visible') });
											$("#zoom-marker-tooltip-" + marker + "-arrow-right").css({ visibility: ( zoom.marker[marker].excludeArea == 'left' ? 'visible' : 'hidden') });

											$("#zoom-excluded-area-" + secondmarker).css({ left: (zoom.marker.distance > 0) ? zoom.box.left : zoom.marker[secondmarker].left, width: (zoom.marker.distance > 0) ? zoom.marker[secondmarker].left - zoom.box.left : zoom.box.right - zoom.marker[secondmarker].left});
											$("#zoom-marker-tooltip-" + secondmarker ).css({ left: zoom.marker[secondmarker].left + ( (zoom.marker[secondmarker].excludeArea == 'right') ? (0) : (-zoom.marker[secondmarker].width) ) });
											$("#zoom-marker-tooltip-" + secondmarker + "-arrow-left").css({ visibility: ( zoom.marker[secondmarker].excludeArea == 'left' ? 'hidden' : 'visible') });
											$("#zoom-marker-tooltip-" + secondmarker + "-arrow-right").css({ visibility: ( zoom.marker[secondmarker].excludeArea == 'left' ? 'visible' : 'hidden') });

										}else {
											/* let the tooltip follow its marker */
											$("#zoom-marker-tooltip-" + marker).css({ left: zoom.marker[marker].left -zoom.marker[marker].width });
										}

									},
								stop:
									function(event,ui) {
										/* hide all tooltip if we are in auto mode */
										if(zoom.custom.zoomTimestamps == "auto") {
											$(".zoom-marker-tooltip").fadeOut(1000);
										}
									}

							});

							break;
						case 2:
							if(zoom.custom.zoom3rdMouseButton != false) {
								/* hide context menu if open */
								zoomContextMenu_hide();
								if(zoom.custom.zoom3rdMouseButton == "zoom_in") {
									zoomAction_zoom_in();
								}else {
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

			/* hide context menu if open */
			zoomContextMenu_hide();

			if(zoom.custom.zoomMode == 'quick') {

				var newGraphStartTime 	= (zoom.attr.action == 'left2right') 	? parseInt(parseInt(zoom.graph.start) + (zoom.attr.start - zoom.box.left)*zoom.graph.secondsPerPixel)
																				: parseInt(parseInt(zoom.graph.start) + (zoom.attr.end - zoom.box.left)*zoom.graph.secondsPerPixel);
				var newGraphEndTime 	= (zoom.attr.action == 'left2right')	? parseInt(newGraphStartTime + (zoom.attr.end-zoom.attr.start)*zoom.graph.secondsPerPixel)
																				: parseInt(newGraphStartTime + (zoom.attr.start-zoom.attr.end)*zoom.graph.secondsPerPixel);

				/* If the user only clicked on a graph then equal end and start date to ensure that we do not propergate NaNs */
				if(isNaN(newGraphStartTime) & isNaN(newGraphEndTime)) {
					return;
				}else if(isNaN(newGraphStartTime) & !isNaN(newGraphEndTime)) {
					newGraphStartTime = newGraphEndTime;
				}else if(!isNaN(newGraphStartTime) & isNaN(newGraphEndTime)){
					newGraphEndTime = newGraphStartTime;
				}
			}else {
				/* advanced mode has other requirements */
				/* first of, do nothing if not both marker have been positioned */
				if(!zoom.marker[1].placed | !zoom.marker[2].placed) {
					alert("NOTE: In advanced mode both markers have to be positioned first to define the period of time you want to zoom in.");
					return;
				}else {
					var newGraphStartTime = zoom.marker[((zoom.marker[1].unixtime > zoom.marker[2].unixtime)? 2 : 1 )].unixtime;
					var newGraphEndTime = zoom.marker[((zoom.marker[1].unixtime > zoom.marker[2].unixtime)? 1 : 2 )].unixtime;
				}
			}

			if(zoom.options.inputfieldStartTime != '' & zoom.options.inputfieldEndTime != ''){
				/* execute zoom within "tree view" or the "preview view" */
				$('#' + zoom.options.inputfieldStartTime).val(unixTime2Date(newGraphStartTime));
				$('#' + zoom.options.inputfieldEndTime).val(unixTime2Date(newGraphEndTime));
				/* destroy all zoom elements */
				$("[id^='zoom-']").remove();
				/* submit form data and restart */
				$("input[name='" + zoom.options.submitButton + "']").trigger('click');
				zoom_init($('#' + zoom.attr.activeElement));
				return false;
			}else {
				/* graph view is alread in zoom status */
				open(zoom.attr.location[0] + "?action=" + zoom.graph.action + "&local_graph_id=" + zoom.graph.local_graph_id + "&rra_id=" + zoom.graph.rra_id + "&view_type=" + zoom.graph.view_type + "&graph_start=" + newGraphStartTime + "&graph_end=" + newGraphEndTime + "&graph_height=" + zoom.graph.height + "&graph_width=" + zoom.graph.width + "&title_font_size=" + zoom.graph.title_font_size, "_self");
			}

		}




		/*
		* executes a static zoom out (as right click event)
		*/
		function zoomAction_zoom_out(multiplier){

			multiplier--;
			/* avoid that we can not zoom out anymore if start and end date will be equal */
			if(zoom.graph.timespan == 0) {
				zoom.graph.timespan = 1;
			}

			if(zoom.custom.zoomMode == 'quick' || !zoom.marker[1].placed || !zoom.marker[2].placed ) {
				if(zoom.custom.zoomOutPositioning == 'begin') {
					var newGraphStartTime = parseInt(zoom.graph.start);
					var newGraphEndTime = parseInt(parseInt(zoom.graph.end) + (multiplier * zoom.graph.timespan));
				}else if(zoom.custom.zoomOutPositioning == 'end') {
					var newGraphStartTime = parseInt(parseInt(zoom.graph.start) - (multiplier * zoom.graph.timespan));
					var newGraphEndTime = parseInt(zoom.graph.end);
				}else {
					// define the new start and end time, so that the selected area will be centered per default
					var newGraphStartTime = parseInt(parseInt(zoom.graph.start) - (0.5 * multiplier * zoom.graph.timespan));
					var newGraphEndTime = parseInt(parseInt(zoom.graph.end) + (0.5 * multiplier * zoom.graph.timespan));
				}
			}else {
				var newGraphStartTime = zoom.marker[((zoom.marker[1].unixtime > zoom.marker[2].unixtime)? 2 : 1 )].unixtime;
				var newGraphEndTime = zoom.marker[((zoom.marker[1].unixtime > zoom.marker[2].unixtime)? 1 : 2 )].unixtime;
				var selectedTimeSpan = newGraphEndTime - newGraphStartTime;

				if(zoom.custom.zoomOutPositioning == 'begin') {
					newGraphEndTime = newGraphEndTime + multiplier * selectedTimeSpan;
				}else if(zoom.custom.zoomOutPositioning == 'end') {
					newGraphStartTime = newGraphStartTime - multiplier * selectedTimeSpan;
				}else {
					newGraphStartTime = parseInt(newGraphStartTime - 0.5 * multiplier * selectedTimeSpan);
					newGraphEndTime = parseInt(newGraphEndTime + 0.5 * multiplier * selectedTimeSpan);
				}
			}

			if(zoom.options.inputfieldStartTime != '' & zoom.options.inputfieldEndTime != ''){
				$('#' + zoom.options.inputfieldStartTime).val(unixTime2Date(newGraphStartTime));
				$('#' + zoom.options.inputfieldEndTime).val(unixTime2Date(newGraphEndTime));
				/* destroy all zoom elements */
				$("[id^='zoom-']").remove();
				/* submit form data and restart */
				$("input[name='" + zoom.options.submitButton + "']").trigger('click');
				zoom_init($('#' + zoom.attr.activeElement));
				return false;
			}else {
				open(zoom.attr.location[0] + "?action=" + zoom.graph.action + "&local_graph_id=" + zoom.graph.local_graph_id + "&rra_id=" + zoom.graph.rra_id + "&view_type=" + zoom.graph.view_type + "&graph_start=" + newGraphStartTime + "&graph_end=" + newGraphEndTime + "&graph_height=" + zoom.graph.height + "&graph_width=" + zoom.graph.width + "&title_font_size=" + zoom.graph.title_font_size, "_self");
			}
		}


		/*
		* updates the css parameters of the zoom area to reflect user's interaction
		*/
		function zoomAction_draw(event) {

			if(zoom.attr.start == 'none') { return; }

			/* mouse has been moved from right to left */
			if((event.pageX-zoom.attr.start)<0) {
				zoom.attr.action = 'right2left';
				zoom.attr.end = (event.pageX < zoom.box.left) ? zoom.box.left : event.pageX;
				$("#zoom-area").css({ background:'red', left:(zoom.attr.end+1)+'px', width:Math.abs(zoom.attr.start-zoom.attr.end-1)+'px' });
			/* mouse has been moved from left to right*/
			}else {
				zoom.attr.action = 'left2right';
				zoom.attr.end = (event.pageX > zoom.box.right) ? zoom.box.right : event.pageX;
				$("#zoom-area").css({ background:'red', left:zoom.attr.start+'px', width:Math.abs(zoom.attr.end-zoom.attr.start-1)+'px' });
			}
			/* move second marker if necessary */
			if(zoom.custom.zoomMode != 'quick') {
				$("#zoom-marker-2").css({ left:(zoom.attr.end+1)+'px' });
				$("#zoom-marker-tooltip-2").css({ top:zoom.box.top+'px', left:(zoom.attr.end-5)+'px' });
			}
		}

		/**
		 *
		 * @access public
		 * @return void
		 **/
		function zoomContextMenu_init(){

			/* sync menu with cookie parameters */
			$(".zoomContextMenuAction__set_zoomMode__" + zoom.custom.zoomMode).addClass("ui-state-highlight");
			$(".zoomContextMenuAction__set_zoomMarkers__" + ((zoom.custom.zoomMarkers === true) ? "on" : "off") ).addClass("ui-state-highlight");
			$(".zoomContextMenuAction__set_zoomTimestamps__" + ((zoom.custom.zoomTimestamps == 'auto') ? "auto" : ((zoom.custom.zoomTimestamps) ? "on" : "off" ))).addClass("ui-state-highlight");
			$(".zoomContextMenuAction__set_zoomOutFactor__" + zoom.custom.zoomOutFactor).addClass("ui-state-highlight");
			$(".zoomContextMenuAction__set_zoomOutPositioning__" + zoom.custom.zoomOutPositioning).addClass("ui-state-highlight");
			$(".zoomContextMenuAction__set_zoom3rdMouseButton__" + ((zoom.custom.zoom3rdMouseButton === false) ? "off" : zoom.custom.zoom3rdMouseButton) ).addClass("ui-state-highlight");

			if(zoom.custom.zoomMode == "quick") {
				$("#zoom-menu > .advanced_mode").hide();
			}else {
				$(".zoomContextMenuAction__zoom_out").text("Zoom Out (" + zoom.custom.zoomOutFactor + "x)");
			}

			/* init click on events */
			$('[class*=zoomContextMenuAction__]').off().on('click', function() {
				var zoomContextMenuAction = false;
				var zoomContextMenuActionValue = false;
				var classList = $.trim($(this).attr('class')).split(/\s+/);

				$.each( classList, function(index, item){
					if( item.search("zoomContextMenuAction__") != -1) {
						zoomContextMenuActionList = item.replace("zoomContextMenuAction__", "").split("__");
						zoomContextMenuAction = zoomContextMenuActionList[0];
						if(zoomContextMenuActionList[1] == 'undefined' || zoomContextMenuActionList[1] == 'off') {
							zoomContextMenuActionValue = false;
						}else if(zoomContextMenuActionList[1] == 'on') {
							zoomContextMenuActionValue = true;
						}else {
							zoomContextMenuActionValue = zoomContextMenuActionList[1];
						}
						return( false );
					}
				});

				if( zoomContextMenuAction ) {
					if( zoomContextMenuAction.substring(0,8) == "set_zoom") {
						zoomContextMenuAction_set( zoomContextMenuAction.replace("set_zoom", "").toLowerCase(), zoomContextMenuActionValue);
					}else {
						zoomContextMenuAction_do( zoomContextMenuAction, zoomContextMenuActionValue);
					}
				}
			});

			/* init hover events */
			$(".first_li , .sec_li, .inner_li span").hover(
				function () {
					$(this).css({backgroundColor : '#E0EDFE' , cursor : 'pointer'});
					if ( $(this).children().size() >0 )
						if(zoom.custom.zoomMode == "quick") {
							$(this).children('.inner_li:not(.advanced_mode)').show();
						}else {
							$(this).children('.inner_li').show();
						}
					},
				function () {
					$(this).css('background-color' , '#fff' );
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
				case "mode":
					if( zoom.custom.zoomMode != value) {
						zoom.custom.zoomMode = value;
						$('[class*=zoomContextMenuAction__set_zoomMode__]').toggleClass("ui-state-highlight");

						if(value == "quick") {
							// reset menu
							$("#zoom-menu > .advanced_mode").hide();
							$(".zoomContextMenuAction__zoom_out").text("Zoom Out (2x)");

							zoom.custom.zoomMode			= 'quick';
							$.cookie( zoom.options.cookieName, serialize(zoom.custom));
						}else {
							// switch to advanced mode
							$("#zoom-menu > .advanced_mode").show();
							$(".zoomContextMenuAction__zoom_out").text("Zoom Out (" +  + zoom.custom.zoomOutFactor + "x)");

							zoom.custom.zoomMode			= 'advanced';
							$.cookie( zoom.options.cookieName, serialize(zoom.custom));
						}
						zoomElemtents_reset();
						zoomAction_init(zoom.initiator);

					}
					break;
				case "markers":
					if( zoom.custom.zoomMarkers != value) {
						zoom.custom.zoomMarkers = value;
						$.cookie( zoom.options.cookieName, serialize(zoom.custom));
						$('[class*=zoomContextMenuAction__set_zoomMarkers__]').toggleClass('ui-state-highlight');
					}
					break;
				case "timestamps":
					if( zoom.custom.zoomTimestamps != value) {
						zoom.custom.zoomTimestamps = value;
						$.cookie( zoom.options.cookieName, serialize(zoom.custom));
						$('[class*=zoomContextMenuAction__set_zoomTimestamps__]').removeClass('ui-state-highlight');
						$('.zoomContextMenuAction__set_zoomTimestamps__' + ((zoom.custom.zoomTimestamps == 'auto') ? "auto" : ((zoom.custom.zoomTimestamps) ? "on" : "off" ))).addClass('ui-state-highlight');

						/* make them visible only for mode "Always On" */
						if(zoom.custom.zoomTimestamps === true) {
							$('.zoom-marker-tooltip').fadeIn(500);
						}else {
							$('.zoom-marker-tooltip').fadeOut(500);
						}
					}
					break;
				case "outfactor":
					if( zoom.custom.zoomOutFactor != value) {
						zoom.custom.zoomOutFactor = value;
						$.cookie( zoom.options.cookieName, serialize(zoom.custom));
						$('[class*=zoomContextMenuAction__set_zoomOutFactor__]').removeClass('ui-state-highlight');
						$('.zoomContextMenuAction__set_zoomOutFactor__' + value).addClass('ui-state-highlight');
						$('.zoomContextMenuAction__zoom_out').text('Zoom Out (' + value + 'x)');
					}
					break;
				case "outpositioning":
					if( zoom.custom.zoomOutPositioning != value) {
						zoom.custom.zoomOutPositioning = value;
						$.cookie( zoom.options.cookieName, serialize(zoom.custom));
						$('[class*=zoomContextMenuAction__set_zoomOutPositioning__]').removeClass('ui-state-highlight');
						$('.zoomContextMenuAction__set_zoomOutPositioning__' + value).addClass('ui-state-highlight');
					}
					break;
				case "3rdmousebutton":
					if( zoom.custom.zoom3rdMouseButton != value) {
						zoom.custom.zoom3rdMouseButton = value;
						$.cookie( zoom.options.cookieName, serialize(zoom.custom));
						$('[class*=zoomContextMenuAction__set_zoom3rdMouseButton__]').removeClass('ui-state-highlight');
						$('.zoomContextMenuAction__set_zoom3rdMouseButton__' + ((value === false) ? "off" : value)).addClass('ui-state-highlight');
					}
					break;
			}
		}

		function zoomContextMenuAction_do(action, value){
			switch(action) {
				case "close":
					zoomContextMenu_hide();
					break;
				case "zoom_out":
					if(value == undefined) {
						value = (zoom.custom.zoomMode != "quick") ? zoom.custom.zoomOutFactor : 2;
					}
					zoomAction_zoom_out(value);
					break;
				case "zoom_in":
					zoomAction_zoom_in();
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

			var menu_height			= $(".zoom-menu").outerHeight();
			var menu_width			= $(".zoom-menu").outerWidth();
			var menu_width_level_1	= Math.abs($(".zoom-menu .first_li span").outerWidth());
			var menu_width_level_2	= Math.abs($(".zoom-menu .sec_li span").outerWidth());
			var menu_height_level_1	= Math.abs($(".zoom-menu .first_li span").outerHeight());
			var menu_height_level_2	= Math.abs($(".zoom-menu .sec_li span").outerHeight());

			/* let the menu occur on the right per default if possible, otherwise move it to the left: */
			if (( menu_x_pos + menu_x_offset + menu_width) > window_size_x_2 ) {
				menu_x_offset += (-1*menu_width);
				$(".zoom-menu .inner_li").css({ 'margin-left': -menu_width_level_1 });
			}else {
				if(( menu_x_pos + menu_x_offset + menu_width + menu_width_level_1 + menu_width_level_2 ) > window_size_x_2) {
					$(".zoom-menu .inner_li").css({ 'margin-left': -menu_width_level_1 });
				}else {
					$(".zoom-menu .inner_li").css({ 'margin-left': menu_width_level_1 });
				}
			}

			if (( menu_y_pos + menu_y_offset + menu_height ) > window_size_y_2 ) {
				menu_y_offset += (-1*menu_height);
			}

			$("#zoom-menu").css({ left: menu_x_pos+menu_x_offset, top: menu_y_pos+menu_y_offset, zIndex: '101' }).show();
		};

		function zoomContextMenu_hide(){
			$('#zoom-menu').hide();
		}

	};

})(jQuery);

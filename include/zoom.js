<script type="text/javascript">
<!--
/*
+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
+ Bonsai: A more user friendly zoom function for Cacti                        +
+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
+ Copyright (C) 2004  Eric Steffen                                            +
+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
+ This program is free software; you can redistribute it and/or               +
+ modify it under the terms of the GNU General Public License                 +
+ as published by the Free Software Foundation; either version 2              +
+ of the License, or (at your option) any later version.                      +
+                                                                             +
+ This program is distributed in the hope that it will be useful,             +
+ but WITHOUT ANY WARRANTY; without even the implied warranty of              +
+ MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the               +
+ GNU General Public License for more details.                                +
+                                                                             +
+ You should have received a copy of the GNU General Public License           +
+ along with this program; if not, write to the Free Software                 +
+ Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA. +
+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
+ email : eric.steffen@gmx.net                                                +
+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++

 zoom.js version 0.4
*/
/*+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++*/

// Global constant
var cURLBase = "graph.php?action=zoom";

// Global variables
var gZoomGraphObj;
var gMouseObj;
var gUrlObj;
var gBrowserObj;

// Objects declaration

/*+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++*/
/*++++++++++++++++++++++++++++++++  urlObj  +++++++++++++++++++++++++++++++++*/
/*+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++*/

function urlObj(url) {
	var urlBaseAndParameters;

	urlBaseAndParameters = url.split("?");
	this.urlBase = urlBaseAndParameters[0];
	this.urlParameters = urlBaseAndParameters[1].split("&");

	this.getUrlBase = urlObjGetUrlBase;
	this.getUrlParameterValue = urlObjGetUrlParameterValue;
}

/*++++++++++++++++++++++++  urlObjGetUrlBase  +++++++++++++++++++++++++++++++*/

function urlObjGetUrlBase() {
	return this.urlBase;
}

/*++++++++++++++++++++  urlObjGetUrlParameterValue  +++++++++++++++++++++++++*/

function urlObjGetUrlParameterValue(parameter) {
	var i;
	var fieldAndValue;
	var value;

	i = 0;
	while (this.urlParameters [i] != undefined) {
		fieldAndValue = this.urlParameters[i].split("=");
		if (fieldAndValue[0] == parameter) {
			value = fieldAndValue[1];
		}
		i++;
	}

	return value;
}



/*+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++*/
/*+++++++++++++++++++++++++++++++  mouseObj  ++++++++++++++++++++++++++++++++*/
/*+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++*/

function mouseObj() {
	this.startedX = 0;
	this.startedY = 0;

	this.stoppedX = 0;
	this.stoppedY = 0;

	this.currentX = 0;
	this.currentY = 0;

	this.dragging = false;

	this.setEvent = mouseObjSetEvent;
	this.leftButtonPressed = mouseObjLeftButtonPressed;
	this.rightButtonPressed = mouseObjRightButtonPressed;
	this.getCurrentPosition = mouseObjGetCurrentPosition;
	this.saveCurrentToStartPosition = mouseObjSaveCurrentToStartPosition;
	this.saveCurrentToStopPosition = mouseObjSaveCurrentToStopPosition;
}

/*++++++++++++++++++++++++  mouseObjSetEvent  +++++++++++++++++++++++++++++++*/

function mouseObjSetEvent(theEvent) {
	if (gBrowserObj.browser == "Netscape") {
		this.event = theEvent;
	} else {
		this.event = window.event;
	}
}

/*++++++++++++++++++++++++  mouseObjLeftMouseButton  +++++++++++++++++++++++++++++++*/

function mouseObjLeftButtonPressed() {
	var LeftButtonPressed = false;
	// alert ("Button Pressed");
	if (gBrowserObj.browser == "IE") {
		LeftButtonPressed = (this.event.button < 2);
	} else {
		LeftButtonPressed = (this.event.which  < 2);
	}

	return LeftButtonPressed;
}

/*++++++++++++++++++++++++  mouseObjRightMouseButton  +++++++++++++++++++++++++++++++*/

function mouseObjRightButtonPressed() {
	var RightButtonPressed = false;
	//alert ("Button Pressed");
	if (gBrowserObj.browser == "IE") {
		if ((this.event.button >= 2) && (this.event.button != 4)) {
			RightButtonPressed = true;
		}
	} else {
		if (this.event.which > 2) {
			RightButtonPressed = true;
		}
	}

	return RightButtonPressed;
}

/*+++++++++++++++++++  mouseObjGetCurrentPosition  ++++++++++++++++++++++++++*/

function mouseObjGetCurrentPosition() {
	this.currentX = this.event.clientX + document.body.scrollLeft;
	this.currentY = this.event.clientY + document.body.scrollTop;
	// alert (this.currentX + "\n" + this.currentY);
}

/*+++++++++++++++++  mouseObjSaveCurrentToStartPosition  ++++++++++++++++++++*/

function mouseObjSaveCurrentToStartPosition() {
	this.startedX = this.currentX;
	this.startedY = this.currentY;
}

/*++++++++++++++++++  mouseObjSaveCurrentToStopPosition  ++++++++++++++++++++*/

function mouseObjSaveCurrentToStopPosition() {
	this.stoppedX = this.currentX;
	this.stoppedY = this.currentY;
}

/*+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++*/
/*+++++++++++++++++++++++++++++  zoomGraphObj  ++++++++++++++++++++++++++++++*/
/*+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++*/

function zoomGraphObj(zoomGraphName) {
	// We use 3 zones. The first (zoomGraph) represent the entire graph image.
	// The second (zoomBox) represent the graph itself.
	// The last zone (zoomSensitiveZone) represent the area where the user can
	// launch the zoom function

	this.zoomGraphName = zoomGraphName;
	this.imgObject     = document.getElementById(this.zoomGraphName);
	gUrlObj            = new urlObj(this.imgObject.src);

	this.zoomGraphLeft   = 0;
	this.zoomGraphTop    = 0;
	this.zoomGraphRight  = 0;
	this.zoomGraphBottom = 0;
	this.zoomGraphWidth  = 0;
	this.zoomGraphHeight = 0;

	this.zoomBoxLeft   = 0;
	this.zoomBoxTop    = 0;
	this.zoomBoxRight  = 0;
	this.zoomBoxBottom = 0;
	this.zoomBoxWidth  = 0;
	this.zoomBoxHeight = 0;

	this.zoomSensitiveZoneLeft   = 0;
	this.zoomSensitiveZoneTop    = 0;
	this.zoomSensitiveZoneRight  = 0;
	this.zoomSensitiveZoneBottom = 0;
	this.zoomSensitiveZoneWith   = 0;
	this.zoomSensitiveZoneHeight = 0;

	this.refresh = zoomGraphObjRefresh;
	this.drawSelection = zoomGraphObjDrawSelection;

	this.refresh();
}

/*+++++++++++++++++++++++++++  zoomGraphObjRefresh  +++++++++++++++++++++++++*/

function zoomGraphObjRefresh() {
	var titleFontSize = parseInt(gUrlObj.getUrlParameterValue("title_font_size"));

	if (titleFontSize == 0) {
		var cZoomBoxTopOffsetWOText = 15 - 1;
		var cZoomBoxTopOffsetWText  = 32 - 1;
		var cZoomBoxRightOffset     = 16;
	} else {
		var cZoomBoxTopOffsetWOText = 10 - 1;
		var cZoomBoxTopOffsetWText  = titleFontSize + (titleFontSize * 1.6) + 10 - 1;
		var cZoomBoxRightOffset     = 28;
	}

	// zone outside of Zoom box where user can move cursor to without causing odd behavior
	var cZoomSensitiveZoneOffset = 5;

	var imgObject = this.imgObject;
	var imgAlt = imgObject.alt;

	// get the graph area size from the url
	var imageWidth  = imgObject.clientWidth;
	var imageHeight = imgObject.clientHeight;
	//alert("Overall Image Dimensions:"+imageWidth+", "+imageHeight);

	// get the graph area size from the url
	var graphWidth  = parseInt(gUrlObj.getUrlParameterValue("graph_width")) + 1;
	var graphHeight = parseInt(gUrlObj.getUrlParameterValue("graph_height")) + 1;
	//alert("Inside Image Dimensions:"+zoomBoxWidth+", "+zoomBoxHeight);

	// Get absolute image position relative to the overall window.
	//
	// start with the image's coordinates and walk through it's
	// ancestory of elements (tables, div's, spans, etc...) until
	// we're at the top of the display.  Along the way we add in each element's
	// coordinates to get absolute image postion.
	var imageLeft = 0;
	var imageTop  = 0;
	do {
		imageLeft += imgObject.offsetLeft;
		imageTop  += imgObject.offsetTop;
		imgObject  = imgObject.offsetParent;
	} while(imgObject);

	// set the images's Ix1,Iy1 and Ix2,Iy2 postions based upon results
	this.zoomGraphLeft   = imageLeft;
	this.zoomGraphTop    = imageTop;
	this.zoomGraphRight  = imageLeft + imageWidth;
	this.zoomGraphBottom = imageTop  + imageHeight;
	this.zoomGraphWidth  = imageWidth;
	this.zoomGraphHeight = imageHeight;
	//alert("Image Position (x,y):("+imageLeft+','+imageTop+') x ('+this.zoomGraphRight+', '+this.zoomGraphBottom+')');

	// calculate the right hand coordinate (rrdGAx2) of the zoom box (aka rrd Graph area)
	this.zoomBoxRight = this.zoomGraphRight - cZoomBoxRightOffset;

	// calculate the top coordinate (rrdGAy2) of the zoom box (aka rrd Graph area)
	if(imgAlt == "") {
		this.zoomBoxTop = this.zoomGraphTop + cZoomBoxTopOffsetWOText;
	} else {
		this.zoomBoxTop = this.zoomGraphTop + cZoomBoxTopOffsetWText;
	}

	// calculate the left hand coordinate (rrdGAx1) of the zoom box (aka rrd Graph area)
	this.zoomBoxLeft = this.zoomBoxRight - graphWidth;

	// calculate the bottom coordinate (rrdGAy1) of the zoom box (aka rrd Graph area)
	this.zoomBoxBottom = this.zoomBoxTop + graphHeight;
	//alert("ZoomBox Position (x,y):("+this.zoomBoxLeft+', '+this.zoomBoxTop+') x ('+this.zoomBoxRight+', '+this.zoomBoxBottom+')');

	// set the objects zoom sizes from the url values (aka rrd Graph size)
	this.zoomBoxWidth  = graphWidth;
	this.zoomBoxHeight = graphHeight;

	// this.drawSelection(this.zoomBoxLeft, this.zoomBoxTop, this.zoomBoxRight, this.zoomBoxBottom);
	this.drawSelection(0, 0, 0, 0); // reset selection

	var zoomBox          = document.getElementById("zoomBox");
	zoomBox.style.left   = this.zoomBoxLeft+'px';
	zoomBox.style.top    = this.zoomBoxTop+'px';
	zoomBox.style.width  = this.zoomBoxWidth+'px';
	zoomBox.style.height = this.zoomBoxHeight+'px';

	// allow the crosshair to extend outside of the Graph area without graphical glitches
	this.zoomSensitiveZoneLeft   = this.zoomBoxLeft - cZoomSensitiveZoneOffset;
	this.zoomSensitiveZoneTop    = this.zoomBoxTop - cZoomSensitiveZoneOffset;
	this.zoomSensitiveZoneRight  = this.zoomBoxRight + cZoomSensitiveZoneOffset;
	this.zoomSensitiveZoneBottom = this.zoomBoxBottom + cZoomSensitiveZoneOffset;
	this.zoomSensitiveZoneWidth  = this.zoomSensitiveZoneRight - this.zoomSensitiveZoneLeft;
	this.zoomSensitiveZoneHeight = this.zoomSensitiveZoneBottom - this.zoomSensitiveZoneTop;

	var zoomZone          = document.getElementById("zoomSensitiveZone");
	zoomZone.style.left   = this.zoomSensitiveZoneLeft+'px';
	zoomZone.style.top    = this.zoomSensitiveZoneTop+'px';
	zoomZone.style.width  = this.zoomSensitiveZoneWidth+'px';
	zoomZone.style.height = this.zoomSensitiveZoneHeight+'px';
}

/*++++++++++++++++++++++  zoomGraphObjDrawSelection  ++++++++++++++++++++++++*/

function zoomGraphObjDrawSelection (x1, y1, x2, y2) {
	x1 = x1 - this.zoomBoxLeft;
	x2 = x2 - this.zoomBoxLeft;
	y1 = y1 - this.zoomBoxTop;
	y2 = y2 - this.zoomBoxTop;

	var minX = Math.min(x1, x2);
	var maxX = Math.max(x1, x2) + 1;
	var minY = Math.min(y1, y2);
	var maxY = Math.max(y1, y2) + 1;

	/* check for upper bounds */
	if (maxX > this.zoomBoxWidth) {
		maxX = this.zoomBoxWidth;
	}

	if (maxY > this.zoomBoxHeight) {
		maxY = this.zoomBoxHeight;
	}

	var divObject = document.getElementById("dragBox");
	divObject.style.top    = this.zoomBoxTop+minY+"px";
	divObject.style.left   = this.zoomBoxLeft+minX+"px";
	divObject.style.width  = maxX-minX+"px";
	divObject.style.height = maxY-minY+"px";
//	divObject.style.clip ="rect(" + minY + "px  " + maxX + "px  " + maxY + "px  " + minX + "px)";
}

/*+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++*/
/*++++++++++++++++++++  standard functions definition  ++++++++++++++++++++++*/
/*+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++*/

/*
BrowserDetector()
Parses User-Agent string into useful info.

Source: Webmonkey Code Library
(http://www.hotwired.com/webmonkey/javascript/code_library/)

Author: Richard Blaylock
Author Email: blaylock@wired.com

Usage: var bd = new BrowserDetector(navigator.userAgent);
*/

// utility function to trim spaces from both ends of a string
function Trim(inString) {
	var retVal = "";
	var start = 0;
	while ((start < inString.length) && (inString.charAt(start) == ' ')) {
		++start;
	}

	var end = inString.length;

	while ((end > 0) && (inString.charAt(end - 1) == ' ')) {
		--end;
	}

	retVal = inString.substring(start, end);

	return retVal;
}

function BrowserDetector(ua) {
	// defaults
	this.browser = "Unknown";
	this.platform = "Unknown";
	this.version = "";
	this.majorver = "";
	this.minorver = "";

	if (ua) {
		var uaLen = ua.length;
		var i = ua.indexOf("(");
	}

	// ##### split into stuff before parens and stuff in parens
	var preparens = "";
	var parenthesized = "";

	if (i >= 0) {
		preparens = Trim(ua.substring(0,i));
		parenthesized = ua.substring(i+1, uaLen);
		var j = parenthesized.indexOf(")");
		if (j >= 0) {
			parenthesized = parenthesized.substring(0, j);
		}
	} else {
		preparens = ua;
	}

	// ##### first assume browser and version are in preparens
	// ##### override later if we find them in the parenthesized stuff
	var browVer = preparens;

	var tokens = parenthesized.split(";");
	var token = "";

	// # Now go through parenthesized tokens
	for (var i=0; i < tokens.length; i++) {
		token = Trim(tokens[i]);
		//## compatible - might want to reset from Netscape
		if (token == "compatible") {
			//## One might want to reset browVer to a null string
			//## here, but instead, we'll assume that if we don't
			//## find out otherwise, then it really is Mozilla
			//## (or whatever showed up before the parens).
			//## browser - try for Opera or IE
		} else if (token.indexOf("MSIE") >= 0) {
			browVer = token;
		} else if (token.indexOf("Opera") >= 0) {
			browVer = token;
		} else if ((token.indexOf("X11") >= 0) || (token.indexOf("SunOS") >= 0) || (token.indexOf("Linux") >= 0)) {
			//'## platform - try for X11, SunOS, Win, Mac, PPC
			this.platform = "Unix";
		} else if (token.indexOf("Win") >= 0) {
			this.platform = token;
		} else if ((token.indexOf("Mac") >= 0) || (token.indexOf("PPC") >= 0)) {
			this.platform = token;
		}
	}

	var msieIndex = browVer.indexOf("MSIE");
	if (msieIndex >= 0) {
		browVer = browVer.substring(msieIndex, browVer.length);
	}

	var leftover = "";
	if (browVer.substring(0, "Mozilla".length) == "Mozilla") {
		this.browser = "Netscape";
		leftover = browVer.substring("Mozilla".length+1, browVer.length);
	} else if (browVer.substring(0, "Lynx".length) == "Lynx") {
		this.browser = "Lynx";
		leftover = browVer.substring("Lynx".length+1, browVer.length);
	} else if (browVer.substring(0, "MSIE".length) == "MSIE") {
		this.browser = "IE";
		leftover = browVer.substring("MSIE".length+1, browVer.length);
	} else if (browVer.substring(0, "Microsoft Internet Explorer".length) == "Microsoft Internet Explorer") {
		this.browser = "IE"
		leftover = browVer.substring("Microsoft Internet Explorer".length+1, browVer.length);
	} else if (browVer.substring(0, "Opera".length) == "Opera") {
		this.browser = "Opera"
		leftover = browVer.substring("Opera".length+1, browVer.length);
	}

	leftover = Trim(leftover);

	// # try to get version info out of leftover stuff
	i = leftover.indexOf(" ");
	if (i >= 0) {
		this.version = leftover.substring(0, i);
	} else {
		this.version = leftover;
	}

	j = this.version.indexOf(".");
	if (j >= 0) {
		this.majorver = this.version.substring(0,j);
		this.minorver = this.version.substring(j+1, this.version.length);
	} else {
		this.majorver = this.version;
	}
} // function BrowserCap


/*++++++++++++++++++++++++++  initBonsai  ++++++++++++++++++++++++++*/

function initBonsai() {
	gBrowserObj   = new BrowserDetector(navigator.userAgent);
	//alert("Browser: " + gBrowserObj.browser + "\nPlatform: " + gBrowserObj.platform + "\nVersion: " + gBrowserObj.version + "\nMajorVer: " + gBrowserObj.majorver + "\nMinorVer: " + gBrowserObj.minorver);

	// gUrlObj = new urlObj(document.URL);
	gZoomGraphObj = new zoomGraphObj("zoomGraphImage");
	gMouseObj     = new mouseObj();
	initEvents();
}

/*+++++++++++++++++++++++++++  insideZoomBox  +++++++++++++++++++++++++++++++*/

function insideZoomBox() {
	var szLeft   = gZoomGraphObj.zoomSensitiveZoneLeft;
	var szTop    = gZoomGraphObj.zoomSensitiveZoneTop;
	var szRight  = gZoomGraphObj.zoomSensitiveZoneRight;
	var szBottom = gZoomGraphObj.zoomSensitiveZoneBottom;

	var mpX = gMouseObj.currentX;
	var mpY = gMouseObj.currentY;

	return ((mpX >= szLeft) && (mpX <= szRight) && (mpY >= szTop) && (mpY <= szBottom));
}

/*++++++++++++++++++++++++++++  initEvents  +++++++++++++++++++++++++++++++++*/

function initEvents() {
	object = document.getElementById("zoomSensitiveZone");

	if (object) {
		object.onmousemove   = onMouseMoveEvent;
		object.onmousedown   = onMouseDownEvent;
		object.onmouseup     = onMouseUpEvent;
	}

	window.onresize = windowOnResizeEvent;

}

/*+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++*/
/*+++++++++++++++++++++  events functions definition  +++++++++++++++++++++++*/
/*+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++*/

/*+++++++++++++++++++++++++++  onMouseDownEvent  ++++++++++++++++++++++++++++*/

function onMouseDownEvent(e) {
	gMouseObj.setEvent(e);
	gMouseObj.getCurrentPosition();

	if (insideZoomBox()) {
		if ((gMouseObj.leftButtonPressed()) && (!gMouseObj.dragging)) {
			gMouseObj.dragging = true;
			gMouseObj.saveCurrentToStartPosition();
			gZoomGraphObj.drawSelection(gMouseObj.currentX, gMouseObj.currentY, gMouseObj.currentX, gMouseObj.currentY);
		} else if (gMouseObj.rightButtonPressed()) {
			e.preventDefault();
			var test = true;
		}
	}
}

/*+++++++++++++++++++++++++++  onMouseMoveEvent  +++++++++++++++++++++++++++*/

function onMouseMoveEvent(e) {
	gMouseObj.setEvent(e);
	if (gMouseObj.dragging) {
		gMouseObj.getCurrentPosition();
		gZoomGraphObj.drawSelection(gMouseObj.startedX, gMouseObj.startedY, gMouseObj.currentX, gMouseObj.currentY);
	}
}

/*+++++++++++++++++++++++++++++  onMouseUpEvent  ++++++++++++++++++++++++++++*/

function onMouseUpEvent(e) {
	var graphStart;
	var graphEnd;

	var newGraphStart;
	var newGraphEnd;

	gMouseObj.setEvent(e);

	graphStart = parseInt(gUrlObj.getUrlParameterValue("graph_start"));
	graphEnd = parseInt(gUrlObj.getUrlParameterValue("graph_end"));

	// zoom out action
	if ((gMouseObj.rightButtonPressed()) && (insideZoomBox())) {
		var Timespan = graphEnd - graphStart;

		gMouseObj.dragging = false;
		newGraphEnd        = graphEnd   + Timespan * 2;
		newGraphStart      = graphStart - Timespan * 2;

		var urlBase       = cURLBase;
		var localGraphId  = gUrlObj.getUrlParameterValue("local_graph_id");
		var rraId         = gUrlObj.getUrlParameterValue("rra_id");
		var graphWidth    = gUrlObj.getUrlParameterValue("graph_width");
		var graphHeight   = gUrlObj.getUrlParameterValue("graph_height");
		var viewType      = gUrlObj.getUrlParameterValue("view_type");
		var titleFontSize = gUrlObj.getUrlParameterValue("title_font_size");

		open(urlBase + "&local_graph_id=" + localGraphId + "&rra_id=" + rraId + "&view_type=" + viewType + "&graph_start=" + newGraphStart + "&graph_end=" + newGraphEnd + "&graph_height=" + graphHeight + "&graph_width=" + graphWidth + "&title_font_size=" + titleFontSize, "_self");
	}

	// zoom in action
	if ((gMouseObj.leftButtonPressed()) && (gMouseObj.dragging)) {
		gMouseObj.getCurrentPosition();
		gMouseObj.saveCurrentToStopPosition();
		gMouseObj.dragging = false;

		// check for appropriate selection zone
		if (((gMouseObj.startedX < gZoomGraphObj.zoomBoxLeft)   && (gMouseObj.stoppedX < gZoomGraphObj.zoomBoxLeft)) ||
			((gMouseObj.startedX > gZoomGraphObj.zoomBoxRight)  && (gMouseObj.stoppedX > gZoomGraphObj.zoomBoxRight)) ||
			((gMouseObj.startedY > gZoomGraphObj.zoomBoxBottom) && (gMouseObj.stoppedY > gZoomGraphObj.zoomBoxBottom)) ||
			((gMouseObj.startedY < gZoomGraphObj.zoomBoxTop)    && (gMouseObj.stoppedY < gZoomGraphObj.zoomBoxTop))) {
			// alert("Selection Outside of Allowed Area");
		}else {
			var x1 = gMouseObj.startedX - gZoomGraphObj.zoomBoxLeft;
			var x2 = gMouseObj.stoppedX - gZoomGraphObj.zoomBoxLeft;

			var y1 = gMouseObj.startedY - gZoomGraphObj.zoomBoxTop;
			var y2 = gMouseObj.stoppedY - gZoomGraphObj.zoomBoxTop;

			var minX = Math.min(x1, x2);
			var maxX = Math.max(x1, x2);
			var minY = Math.min(y1, y2);
			var maxY = Math.max(y1, y2);

			if (minX < 0) {
				minX = 0;
			}

			if (maxX > gZoomGraphObj.zoomBoxWidth) {
				maxX = gZoomGraphObj.zoomBoxWidth;
			}

			if (minY < 0) {
				minY = 0;
			}

			if (maxY > gZoomGraphObj.zoomBoxHeight) {
				maxY = gZoomGraphObj.zoomBoxHeight;
			}

			if ((minX != maxX) || (minY != maxY)) {
				var OnePixel = (graphEnd - graphStart) / gZoomGraphObj.zoomBoxWidth;  // Represent # of seconds for 1 pixel on the graph

				newGraphEnd = Math.round(graphEnd - (gZoomGraphObj.zoomBoxWidth - maxX) * OnePixel);
				newGraphStart = Math.round(graphStart + minX * OnePixel);

				//  var urlBase = gUrlObj.getUrlBase();
				var urlBase       = cURLBase;
				var localGraphId  = gUrlObj.getUrlParameterValue("local_graph_id");
				var rraId         = gUrlObj.getUrlParameterValue("rra_id");
				var graphWidth    = gUrlObj.getUrlParameterValue("graph_width");
				var graphHeight   = gUrlObj.getUrlParameterValue("graph_height");
				var viewType      = gUrlObj.getUrlParameterValue("view_type");
				var titleFontSize = gUrlObj.getUrlParameterValue("title_font_size");

				open(urlBase + "&local_graph_id=" + localGraphId + "&rra_id=" + rraId + "&view_type=" + viewType + "&graph_start=" + newGraphStart + "&graph_end=" + newGraphEnd + "&graph_height=" + graphHeight + "&graph_width=" + graphWidth + "&title_font_size=" + titleFontSize, "_self");
			}
		}
	}
}

/*+++++++++++++++++++++++++++  windowOnResizeEvent  +++++++++++++++++++++++++*/

function windowOnResizeEvent() {
	gZoomGraphObj.refresh();
}

/*+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++*/
/*++++++++++++++++++++++++++++++  main script  ++++++++++++++++++++++++++++++*/
/*+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++*/

window.onload = initBonsai;

-->
</script>

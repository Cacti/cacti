var realtimeArray   = [];
var keepRealtime    = [];
var inRealtime      = false;
var count           = 0;
var resltimeTimer   = '';
var originalRefresh = 0;
var timeOffset;
var realtimeTimer;
var realtimePopout  = false;
var rtWidth         = 0;
var rtHeight        = 0;
var url;

function realtimeDetectBrowser() {
	if (navigator.userAgent.indexOf('MSIE') >= 0) {
		browser = "IE";
	}else if (navigator.userAgent.indexOf('Chrome') >= 0) {
		browser = 'Chrome';
	}else if (navigator.userAgent.indexOf('Mozilla') >= 0) {
		browser = "FF";
	}else if (navigator.userAgent.indexOf('Opera') >= 0) {
		browser = "Opera";
	}else{
		browser = "Other";
	}

	return browser;
}

function imageOptionsChanged(action) {
	graph_start    = $("#graph_start").val();
	graph_end      = 0;
	ds_step        = $("#ds_step").val();
	size           = $('#size').val();
	isThumb        = $('#thumbnails').is(':checked');

	if ($('#local_graph_id').length) {
		local_graph_id = $('#local_graph_id').val();
	} else {
		local_graph_id = 0;
	}

	if (rtWidth == 0) {
		rtWidth = $(window).width();
	}

	if (rtHeight == 0) {
		rtHeight = $(window).height()+50;
	}

	if (action == 'countdown') {
		url="graph_realtime.php?action=countdown&top=0&left=0&action="+action+"&local_graph_id="+local_graph_id;
	} else if (action == 'initial') {
		url="graph_realtime.php?action=initial&top=0&left=0&action="+action+"&local_graph_id="+local_graph_id+"&graph_start=-"+(parseInt(graph_start) > 0 ? graph_start:'60')+"&ds_step="+ds_step+"&count="+count+"&size="+size;
	} else {
		url="graph_realtime.php?action="+action+"&top=0&left=0&action="+action+"&local_graph_id="+local_graph_id+"&graph_start=-"+(parseInt(graph_start) > 0 ? graph_start:'60')+"&ds_step="+ds_step+"&count="+count+"&size="+size+"&graph_nolegend="+isThumb;
	}

	Pace.stop;

	$.getJSON(url)
		.done(function(data) {
			$('#image').empty().append('<img id="rimage" class="realtimeimage" src="data:image/png;base64,'+data.data+'"/>');
			if (realtimePopout) {
				setRealtimeWindowSize();
				$('#ds_step').val(data.ds_step);
				if ($('#ds_step').selectmenu('instance') !== undefined) {
					$('#ds_step').selectmenu('refresh');
				}

				$('#graph_start').val(Math.abs(data.graph_start));
				if ($('#graph_start').selectmenu('instance') !== undefined) {
					$('#graph_start').selectmenu('refresh');
				}

				$('#size').val(data.size);
				if ($('#size').selectmenu('instance') !== undefined) {
					$('#size').selectmenu('refresh');
				}

				if (data.thumbnails == 'true') {
					$('#thumbnails').prop('checked', true);
				} else {
					$('#thumbnails').prop('checked', false);
				}
			}
		})
		.fail(function(data) {
			getPresentHTTPError(data);
		});
}

function setRealtimeWindowSize() {
	if (realtimePopout == true) {
		/* set the window size */
		height1 = $('#rtfilter').outerHeight() + 60;
		height2 = $('#rimage').outerHeight() + 30;
		width   = $('#rimage').outerWidth() + 40;

		if (width > 60) {
			window.outerHeight = height1+height2;
			window.outerWidth  = width
			window.resizeTo(width, height1+height2);
		}
	}
}

function stopRealtime() {
	for (key in realtimeArray) {
		graph_id=key;

		$('#wrapper_'+graph_id).html(keepRealtime[graph_id]).change();
		$('#graph_'+graph_id+'_realtime').html("<img class='drillDown' alt='' title='"+realtimeClickOn+"' src='"+urlPath+"images/chart_curve_go.png'>").find('img').tooltip();

		// Disable right click
		$(this).children().bind('contextmenu', function(event) {
			return false;
		});

		$('graph_'+graph_id).zoom({
			inputfieldStartTime : 'date1',
			inputfieldEndTime : 'date2',
			serverTimeOffset : timeOffset
		});

		$('.dispose_'+graph_id).remove();
		$('#graph_'+graph_id).css('position','').css('top','').css('left', '');

		realtimeArray[graph_id] = false;
	}

	setFilters();
	tuneFilter();
}

function setFilters() {
	var inRT = false;
	for (key in realtimeArray) {
		if (realtimeArray[key] == true) {
			inRT = true;
			break;
		}
	}

	if (!inRT) {
		$('#timespan').show();
		$('#realtime').hide();
		$('#search').show();
		$('#device').show();
		restorePageRefresh();
	}else{
		$('#timespan').hide();
		$('#realtime').show();
		$('#search').hide();
		$('#device').hide();
	}
}

function restorePageRefresh() {
	clearTimeout(realtimeTimer);
	refreshMSeconds = originalRefresh;
	setupPageTimeout();
}

function realtimeGrapher() {
	clearTimeout(myRefresh);
	clearTimeout(realtimeTimer);

	if (originalRefresh == 0) {
		originalRefresh = refreshMSeconds;
	}

	graph_start = $('#graph_start').val();
	graph_end   = 0;
	ds_step     = $('#ds_step').val();
	size        = $('#size').val();
	inRealtime  = false;
    isThumb     = $('#thumbnails').is(':checked');

	for (key in realtimeArray) {
		if (realtimeArray[key] == true) {
			inRealtime = true;
			local_graph_id=key

			if (isThumb) {
				if (rtWidth == 0) {
					rtWidth    = $('#wrapper_'+local_graph_id).find('img').width();
					rtHeight   = $('#wrapper_'+local_graph_id).find('img').height();
				}
			}

			position = $('#wrapper_'+local_graph_id).find('img').position();

			Pace.ignore(function() {
				position = $('#wrapper_'+local_graph_id).find('img').position();

				$.get(urlPath+'graph_realtime.php?action=countdown&top='+parseInt(position.top)+'&left='+parseInt(position.left)+(isThumb ? '&graph_nolegend=true':'&graph_nolegend=false')+'&graph_end=0&graph_start=-'+(parseInt(graph_start) > 0 ? graph_start:'60')+'&local_graph_id='+local_graph_id+'&ds_step='+ds_step+'&count='+count+'&size='+size)
					.done(function(data) {
						results = $.parseJSON(data);

						$('#graph_'+results.local_graph_id).prop('src', 'data:image/png;base64,'+results.data).change();

						if (isThumb) {
							$('#graph_'+results.local_graph_id).width(rtWidth).height(rtHeight);
						}else{
							$('#graph_'+results.local_graph_id);
						}
					})
					.fail(function(data) {
						getPresentHTTPError(data);
					});

			});
		}
	}

	if (inRealtime == false) {
		stopRealtime();
	} else {
		count--;
		realtimeTimer = setTimeout('realtimeGrapher()', $('#ds_step').val()*1000);
	}
}


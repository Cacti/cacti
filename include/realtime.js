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
var graphsRendered  = null;
var prevTotalGraphs = null;
var url;
var local_graph_id  = null;

function realtimeDetectBrowser() {
	if (navigator.userAgent.indexOf('MSIE') >= 0) {
		var browser = 'IE';
	} else if (navigator.userAgent.indexOf('Chrome') >= 0) {
		var browser = 'Chrome';
	} else if (navigator.userAgent.indexOf('Mozilla') >= 0) {
		var browser = 'FF';
	} else if (navigator.userAgent.indexOf('Opera') >= 0) {
		var browser = 'Opera';
	} else {
		var browser = 'Other';
	}

	return browser;
}

function imageOptionsChanged(action) {
	var graph_start    = $('#graph_start').val();
	var graph_end      = 0;
	var ds_step        = $('#ds_step').val();
	var size           = $('#size').val();
	var isThumb        = $('#thumbnails').is(':checked');
	var url            = '';

	if (size == null) {
		size = 100;
	}

	local_graph_id = $('#local_graph_id').val();

	if (rtWidth == 0) {
		rtWidth = $(window).width();
	}

	if (rtHeight == 0) {
		rtHeight = $(window).height()+50;
	}

	if (action == 'countdown') {
		url = 'graph_realtime.php?action=countdown&top=0&left=0&local_graph_id='+local_graph_id+'&ds_step='+ds_step+'&count='+count+'&size='+size+'&graph_nolegend='+isThumb;
	} else if (action == 'initial') {
		url = 'graph_realtime.php?action=initial&top=0&left=0&local_graph_id='+local_graph_id+'&graph_start=-'+(parseInt(graph_start) > 0 ? graph_start:'60')+'&ds_step='+ds_step+'&count='+count+'&size='+size;
	} else {
		url = 'graph_realtime.php?action='+action+'&top=0&left=0&local_graph_id='+local_graph_id+'&graph_start=-'+(parseInt(graph_start) > 0 ? graph_start:'60')+'&ds_step='+ds_step+'&count='+count+'&size='+size+'&graph_nolegend='+isThumb;
	}

	Pace.stop;

	$.getJSON(url)
		.done(function(data) {
			var image_format = (data.image_format == 'svg+xml') ? 'svg+xml' : 'png';
			if ($('#rimage').length) {
				$('#rimage').empty().attr('src', 'data:image/'+image_format+';base64,'+data.data);
			} else {
				$('#image').empty().html('<img id="rimage" class="realtimeimage" src="data:image/'+image_format+';base64,'+data.data+'"/>');
			}

			realtimePopout = $('#rtfilter').outerHeight() + 60 + $('#rimage').outerHeight() + 30 > window.innerHeight || $('#rimage').outerWidth() + 40 > window.innerWidth ? true : false;

			if (realtimePopout) {
				setRealtimeWindowSize();

				if ($('#ds_step').val() != data.ds_step) {
					$('#ds_step').val(data.ds_step);
					if ($('#ds_step').selectmenu('instance') !== undefined) {
						$('#ds_step').selectmenu('refresh');
					}
				}

				var curStart = Math.abs(data.graph_start);

				if ($('#graph_start').val() != curStart) {
					$('#graph_start').val(Math.abs(data.graph_start));
					if ($('#graph_start').selectmenu('instance') !== undefined) {
						$('#graph_start').selectmenu('refresh');
					}
				}

				if ($('#size').val() != data.size) {
					$('#size').val(data.size);
					if ($('#size').selectmenu('instance') !== undefined) {
						$('#size').selectmenu('refresh');
					}
				}

				if (data.thumbnails == 'true') {
					$('#thumbnails').prop('checked', true);
				} else {
					$('#thumbnails').prop('checked', false);
				}

				destroy(data);
			}
		})
		.fail(function(data) {
			getPresentHTTPError(data);
		});
}

function setRealtimeWindowSize() {
	if (realtimePopout == true) {
		/* set the window size */
		var height1 = $('#rtfilter').outerHeight() + 60;
		var height2 = $('#rimage').outerHeight() + 30;
		var width   = $('#rimage').outerWidth() + 40;

		if (width > 60) {
			window.outerHeight = height1+height2;
			window.outerWidth  = width
			window.resizeTo(width, height1+height2);
		}
	}
}

function countRealtimeGraphs() {
	var graphs = 0;

	for (key in realtimeArray) {
		if (realtimeArray[key] == true) {
			graphs++;
		}
	}

	return graphs;
}

function stopRealtime() {
	var graph;

	for (key in realtimeArray) {
		var graph_id = key;

		$('#wrapper_'+graph_id).html(keepRealtime[graph_id]).change();
		$('#graph_'+graph_id+'_realtime').empty().html("<img class='drillDown' alt='' title='"+realtimeClickOn+"' src='"+urlPath+"images/chart_curve_go.png'>").find('img').tooltip();

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
	var key;

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
	} else {
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

	var graph_start = $('#graph_start').val();
	var graph_end   = 0;
	var ds_step     = $('#ds_step').val();
	var size        = $('#size').val();
    var isThumb     = $('#thumbnails').is(':checked');
	var totalGraphs = countRealtimeGraphs();
	var key;

	if (size == null) {
		size = 100;
	}

	if (graphsRendered == null || graphsRendered >= totalGraphs || prevTotalGraphs != totalGraphs) {
		//console.log('Rendering: Total Graphs:' + totalGraphs + ', Rendered Graphs:' + graphsRendered);

		graphsRendered = 0;
		prevTotalGraphs = totalGraphs;

		for (key in realtimeArray) {
			if (realtimeArray[key] == true) {
				local_graph_id = key

				if (isThumb) {
					if (rtWidth == 0) {
						rtWidth    = $('#wrapper_'+local_graph_id).find('img').width();
						rtHeight   = $('#wrapper_'+local_graph_id).find('img').height();
					}
				}

				var position = $('#wrapper_'+local_graph_id).find('img').position();

				Pace.ignore(function() {
					if ($('#wrapper_'+local_graph_id).find('img').length) {
						position = $('#wrapper_'+local_graph_id).find('img').position();
					} else {
						position = $('body').position();
					}

					$.get(urlPath+'graph_realtime.php?action=countdown&top='+parseInt(position.top)+'&left='+parseInt(position.left)+(isThumb ? '&graph_nolegend=true':'&graph_nolegend=false')+'&graph_end=0&graph_start=-'+(parseInt(graph_start) > 0 ? graph_start:'60')+'&local_graph_id='+local_graph_id+'&ds_step='+ds_step+'&count='+count+'&size='+size)
						.done(function(data) {
							var results = $.parseJSON(data);

							if (realtimeArray[results.local_graph_id] == true) {
								var image_format = (results.image_format == 'svg+xml') ? 'svg+xml' : 'png';
								$('#graph_'+results.local_graph_id).attr('src', 'data:image/'+image_format+';base64,'+results.data).change();

								if (isThumb) {
									$('#graph_'+results.local_graph_id).width(rtWidth).height(rtHeight);
								} else {
									$('#graph_'+results.local_graph_id);
								}
							}

							destroy(data);
							destroy(results);
							destroy(position);

							graphsRendered++;
						})
						.fail(function(data) {
							getPresentHTTPError(data);
						});
				});
			}
		}
	}

	if (totalGraphs == 0) {
		stopRealtime();
	} else if (graphsRendered < totalGraphs) {
		destroy(realtimeTimer);

		realtimeTimer = setTimeout(function() {
			realtimeGrapher();
		}, $('#ds_step').val()*1000);
	} else {
		count--;
		destroy(realtimeTimer);

		realtimeTimer = setTimeout(function() {
			realtimeGrapher();
		}, $('#ds_step').val()*1000);
	}
}

function destroy(obj) {
	for (var prop in obj){
		var property = obj[prop];

		if (property != null && typeof(property) == 'object') {
            destroy(property);
        } else {
            obj[prop] = null;
        }
	}
}

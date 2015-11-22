var realtimeArray   = [];
var keepRealtime    = [];
var inRealtime      = false;
var count           = 0;
var resltimeTimer   = '';
var originalRefresh = 0;
var timeOffset;
var realtimeTimer;

function stopRealtime() {
	for (key in realtimeArray) {
		graph_id=key;

		$('#wrapper_'+graph_id).html(keepRealtime[graph_id]).change();
		$('#graph_'+graph_id+'_realtime').html("<img class='drillDown' title='Click to view just this Graph in Realtime' alt='' src='"+urlPath+"images/chart_curve_go.png'>");
		$(this).find('img').tooltip().zoom({ inputfieldStartTime : 'date1', inputfieldEndTime : 'date2', serverTimeOffset : timeOffset });
		$('.dispose_'+graph_id).remove();
		$('#graph_'+graph_id).css('position','').css('top','').css('left', '');

		realtimeArray[graph_id] = false;
	}

	setFilters();
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

function graphDispose(graph_id) {
	$('#dispose_'+graph_id).remove();
	$('#graph_'+graph_id).css('position','').css('top','').css('left', '');
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
	inRealtime  = false;

    isThumb   = $('#thumbnails').is(':checked');

	for (key in realtimeArray) {
		if (realtimeArray[key] == true) {
			inRealtime = true;
			local_graph_id=key

			if (isThumb) {
				width    = $('#wrapper_'+local_graph_id).find('img').width();
				height   = $('#wrapper_'+local_graph_id).find('img').height();
			}

			position = $('#wrapper_'+local_graph_id).find('img').attr('id', 'dispose_'+local_graph_id).position();

			$.get(urlPath+'graph_realtime.php?action=countdown&top='+parseInt(position.top)+'&left='+parseInt(position.left)+(isThumb ? '&graph_nolegend=true':'')+'&graph_end=0&graph_start=-'+graph_start+'&local_graph_id='+local_graph_id+'&ds_step='+ds_step+'#count='+count, function(data) {
				results = $.parseJSON(data);

				$('#wrapper_'+results.local_graph_id).append("<img style='display:none;position:absolute;left:"+results.left+"px;top:"+results.top+"px;z-index:"+count+";' id='graph_"+results.local_graph_id+"' class='graphimage' alt='' src='data:image/png;base64,"+results.data+"' />");

				if (isThumb) {
					$('#graph_'+results.local_graph_id).width(width).height(height).show();
				}else{
					$('#graph_'+results.local_graph_id).show();
				}

				setTimeout('graphDispose('+results.local_graph_id+')', 1000);
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

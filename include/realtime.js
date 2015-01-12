var realtimeArray   = [];
var keepRealtime    = [];
var inRealtime      = false;
var count           = 0;
var realtimeTimer   = setTimeout('realtimeGrapher()', 5000);
var originalRefresh;
var timeOffset;
var realtimeTimer;

function stopRealtime() {
	for (key in realtimeArray) {
		graph_id=key;

		$('#wrapper_'+graph_id).html(keepRealtime[graph_id]).change();
		$('#graph_'+graph_id+'_realtime').html("<img class='drillDown' border='0' title='Click to view just this Graph in Realtime' alt='' src='/cacti/images/chart_curve_go.png'>");
		$(this).find('img').tooltip().zoom({ inputfieldStartTime : 'date1', inputfieldEndTime : 'date2', serverTimeOffset : timeOffset });
		$('.dispose_'+graph_id).remove();
		$('#graph_'+graph_id).css('position','').css('top','').css('left', '');

		realtimeArray[graph_id] = false;
	}

	setFilters();

	restorePageRefresh(false);
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
	}else{
		$('#timespan').hide();
		$('#realtime').show();
		$('#search').hide();
		$('#device').hide();
	}
}

function restorePageRefresh(inRealtime) {
	if (inRealtime) {
		if (originalRefresh == '') {
			originalRefresh = refreshMSeconds;
		}
		clearTimeout(myRefresh);
	}else{
		refreshMSeconds = originalRefresh;
		setupPageTimeout();
	}
		
}

function graphDispose(graph_id) {
	$('#dispose_'+graph_id).remove();
	$('#graph_'+graph_id).css('position','').css('top','').css('left', '');
}

function realtimeGrapher() {
	clearTimeout(realtimeTimer);

	graph_start = $('#graph_start').val();
	graph_end   = 0;
	ds_step     = $('#ds_step').val();
	inRealtime  = false;
	thumbnails  = $('#thumbnails').is(':checked');
	for (key in realtimeArray) {
		if (realtimeArray[key]) {
			inRealtime = true;
			local_graph_id=key
			position=$('#wrapper_'+local_graph_id).find('img').attr('id', 'dispose_'+local_graph_id).position();
			$.get('graph_realtime.php?action=countdown&top='+parseInt(position.top)+'&left='+parseInt(position.left)+(thumbnails ? '&graph_nolegend='+thumbnails:'')+'&graph_end=0&graph_start=-'+graph_start+'&local_graph_id='+local_graph_id+'&ds_step='+ds_step+'#count='+count, function(data) {
				results = $.parseJSON(data);
				$('#wrapper_'+results.local_graph_id).append("<img style='position:absolute;left:"+results.left+"px;top:"+results.top+"px;z-index:"+count+";' id='graph_"+results.local_graph_id+"' class='graphimage' alt='' src='graph_realtime.php?action=view&local_graph_id="+results.local_graph_id+"&count="+count+"'/>").change();
				setTimeout('graphDispose('+results.local_graph_id+')', 1000);
			});
		}
	}

	restorePageRefresh(inRealtime);

	count--;
	realtimeTimer = setTimeout('realtimeGrapher()', $('#ds_step').val()*1000);
}

$(function() {
	$(".graphimage").zoom({ inputfieldStartTime : 'date1', inputfieldEndTime : 'date2', serverTimeOffset : timeOffset });
	$('.graphimage').each(function() {
		graph_id = $(this).attr('id').replace('graph_','');
		realtimeArray[graph_id] = false;
	});

	$('span[id$="_mrtg"]').click(function() {
		graph_id=$(this).attr('id').replace('graph_','').replace('_mrtg','');
		$.get('graph.php?local_graph_id='+graph_id+'&header=false', function(data) {
			$('#breadcrumbs').find('li:last-child a').click(function(event, data) {
				id=$(this).attr('id');
				$('#jstree').jstree('activate_node', node, event);
				$('#'+id).unbind();
			});
			$('#breadcrumbs').append('<li><a id="nav_mrgt" href="#">MRTG View</a></li>');
			$('#main').html(data);
			applySkin();
		});
	});

	$('#realtimeoff').click(function() {
		stopRealtime();
	});

	$('#ds_step').change(function() {
		realtimeGrapher();
	});

	$('span[id$="_csv"]').click(function() {
		graph_id=$(this).attr('id').replace('graph_','').replace('_csv','');
		document.location = 'graph_xport.php?local_graph_id='+graph_id+'&rra_id=0&view_type=tree&graph_start='+getTimestampFromDate($('#date1').val())+'&graph_end='+getTimestampFromDate($('#date2').val());
	});

	$('span[id$="_realtime"]').click(function() {
		graph_id=$(this).attr('id').replace('graph_','').replace('_realtime','');

		if (realtimeArray[graph_id]) {
			$('#wrapper_'+graph_id).html(keepRealtime[graph_id]).change();
			$(this).html("<img class='drillDown' border='0' title='Click to view just this Graph in Realtime' alt='' src='/cacti/images/chart_curve_go.png'>");
			$(this).find('img').tooltip().zoom({ inputfieldStartTime : 'date1', inputfieldEndTime : 'date2', serverTimeOffset : timeOffset });

			realtimeArray[graph_id] = false;

			setFilters();
		}else{
			keepRealtime[graph_id]  = $('#wrapper_'+graph_id).html();

			$(this).html("<i style='font-size:16px;' title='Click again to take this Graph our of Realtime' class='fa fa-circle-o-notch fa-spin'/>");
			$(this).find('i').tooltip();

			realtimeArray[graph_id] = true;

			setFilters();
		}
		
	});
});

var hostTimer;
var clickTimeout;
var hostOpen = false;

function themeReady() {
	$('a.pic').unbind().click(function(event) {
		event.preventDefault();	

		/* update menu selection */
		$('.pic').removeClass('selected');
		$(this).addClass('selected');

		/* execute an ajax request to load the data */
		href = $(this).attr('href');

		$.get(href, function(html) {
			var htmlObject  = $(html);
			var matches     = html.match(/<title>(.*?)<\/title>/);
			var htmlTitle   = matches[1];
			var breadCrumbs = htmlObject.find('#breadcrumbs').html();
			var content     = htmlObject.find('#main').html();

			$('title').text(htmlTitle);
			$('#breadcrumbs').html(breadCrumbs);
			$('#main').html(content);

			applySkin();

			if (typeof window.history.replaceState !== 'undefined') {
				window.history.replaceState(html, htmlTitle, href);
			}

			return false;
		});

		return false;
	});

	// Add nice search filter to filters
	$('input[id="filter"]').after("<i class='fa fa-search filter'/>").attr('autocomplete', 'off').attr('placeholder', 'Enter a search term').parent('td').css('white-space', 'nowrap');

	$('#host').autocomplete({
		source: 'graphs.php?action=ajax_hosts',
		autoFocus: true,
		minLength: 0,
		select: function(event,ui) {
			$('#host_id').val(ui.item.id);
			applyFilter();
		}
	}).addClass('ui-selectmenu-text').css('border', 'none').css('background-color', 'transparent');

	$('#host_click').css('z-index', '4');
	$('input#filter').addClass('ui-state-default ui-corner-all');

	$('input[type="text"], input[type="password"], input[type="checkbox"], textarea').not('image').addClass('ui-state-default ui-corner-all');

	$.ui.selectmenu.prototype._renderItem = function(ui, item) {
		if (item.element.closest('select').hasClass('colordropdown')) {
			if (item.label != 'None') {
				var li = $("<li>").css( "background-color", '#'+item.label );
			}else{
				var li = $("<li>").css( "background-color", '' );
			}
		}else if (item.element.closest('select').hasClass('iconselect')) {
			var li = $('<li>', { text: item.label });

			if ( item.disabled ) {
				li.addClass('ui-state-disabled');
			}

			$('<span>', {
				style: item.element.attr('data-style'),
				'class': 'ui-icon ' + item.element.attr('data-class')
			}).appendTo(li);

			return li.appendTo(ui);
		}else{
			var li = $("<li>");
		}

		if (item.disabled) {
			li.addClass("ui-state-disabled");
		}

		this._setText(li, item.label);

		return li.appendTo(ui);
	}

	$('.checkboxgroup').children('br').remove();
	$('.checkboxgroup').buttonset();

	$('select').each(function() {
		if ($(this).prop('multiple') != true) {
			$(this).selectmenu({
				change: function(event, ui) {
					$(this).val(ui.item.value).change();
				},
				position: {
					my: "left top",
					at: "left bottom",
					collision: "flip"
				},
			}).each(function() {
				id = $(this).attr('id');
				minWidth = 0;
				$('#'+id+' > option').each(function() {
					width=$(this).textWidth();
					if (width > minWidth) {
						minWidth = width;
					}
				});

				minWidth+=60;
				$('#'+id+'-button').css('min-width', minWidth+'px').css('max-width', '400px').css('width','');
				$('#'+id+'-menu').css('max-height', '250px');
			});
		}else{
			$(this).addClass('ui-state-default ui-corner-all');
		}
	});

	$('#host_wrapper').dblclick(function() {
		hostOpen = false;
		clearTimeout(hostTimer);
		clearTimeout(clickTimeout);
		$('#host').autocomplete('close');
	}).click(function() {
		if (hostOpen) {
			$('#host').autocomplete('close');
			clearTimeout(hostTimer);
			hostOpen = false;
		}else{
			clickTimeout = setTimeout(function() {
				$('#host').autocomplete('search', '');
				clearTimeout(hostTimer);
				hostOpen = true;
			}, 200);
		}
	}).on('mouseleave', function() {
		hostTimer = setTimeout(function() { $('#host').autocomplete('close'); }, 800);
	});

	$('ul[id="ui-id-1"]').on('mouseover', function() {
		clearTimeout(hostTimer);
	}).on('mouseout', function() {
		hostTimer = setTimeout(function() { $('#host').autocomplete('close'); }, 800);
	});

	$('#host_wrapper').on('mouseenter', function() { 
		$(this).addClass('ui-state-hover'); 
		$('input#host').addClass('ui-state-hover');
	}).on('mouseleave', function() { 
		$(this).removeClass('ui-state-hover'); 
		$('input#host').removeClass('ui-state-hover');
	});
}

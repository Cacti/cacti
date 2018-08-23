// Host Autocomplete Magic
var pageName = basename($(location).attr('pathname'));

function themeReady() {
	var hostTimer = false;
	var clickTimeout = false;
	var hostOpen = false;

	$('body').css('height', $(window).height());
	$('#navigation').css('height', ($(window).height()-40)+'px');
	$('#navigation_right').css('height', ($(window).height()-40)+'px');

	keepWindowSize();

	// Setup the navigation menu
	setMenuVisibility();

	// Add nice search filter to filters
	if ($('input[id="filter"]').length > 0) {
		$('input[id="filter"]').after("<i class='fa fa-search filter'/>").attr('autocomplete', 'off').attr('placeholder', searchFilter).parent('td').css('white-space', 'nowrap');
	}

	if ($('input[id="filterd"]').length > 0) {
		$('input[id="filterd"]').after("<i class='fa fa-search filter'/>").attr('autocomplete', 'off').attr('placeholder', searchFilter).parent('td').css('white-space', 'nowrap');
	}

	if ($('input[id="rfilter"]').length > 0) {
		$('input[id="rfilter"]').after("<i class='fa fa-search filter'/>").attr('autocomplete', 'off').attr('placeholder', searchRFilter).parent('td').css('white-space', 'nowrap');
	}

	$('input#filter, input#rfilter').addClass('ui-state-default ui-corner-all');

	$('input[type="text"], input[type="password"], input[type="checkbox"], textarea').not('image').addClass('ui-state-default ui-corner-all');

	$('.checkboxgroup').children('br').remove();
	$('.checkboxgroup').buttonset();

	// Turn file buttons into jQueryUI buttons
	$('.import_label').button();
	$('.import_button').change(function() {
		text=this.value;
		setImportFile(text);
	});
	setImportFile(noFileSelected);

	function setImportFile(fileText) {
		$('.import_text').html(fileText);
	}

	maxWidth = 480;

	$('select.colordropdown').dropcolor();

	$('select').not('.colordropdown').each(function() {
		if ($(this).prop('multiple') != true) {
			$(this).each(function() {
				id = $(this).attr('id');

				$(this).selectmenu({
					change: function(event, ui) {
						$(this).val(ui.item.value).change();
					},
					position: {
						my: "left top",
						at: "left bottom",
						collision: "flip"
					},
					width: 'auto'
				});

				$('#'+id+'-menu').css('max-height', '250px');
			});
		}else{
			$(this).addClass('ui-state-default ui-corner-all');
		}
	});

	$('#host').unbind().autocomplete({
		source: pageName+'?action=ajax_hosts',
		autoFocus: true,
		minLength: 0,
		select: function(event,ui) {
			$('#host_id').val(ui.item.id);
			callBack = $('#call_back').val();
			if (callBack != 'undefined') {
				eval(callBack);
			}else if (typeof applyGraphFilter === 'function') {
				applyGraphFilter();
			}else{
				applyFilter();
			}
		}
	}).addClass('ui-state-default ui-selectmenu-text').css('border', 'none').css('background-color', 'transparent');

	$('#drp_action').change(function() {
		if ($(this).val() != '0') {
			$('#submit').button('enable');
		}else{
			$('#submit').button('disable');
		}
	});

	$('#graph_type_id').change(function() {
		switch($(this).val()) {
		case '4':
		case '5':
		case '6':
		case '7':
		case '8':
			$('#alpha').selectmenu('enable');
		}
	});

	$('#host_click').css('z-index', '4');
	$('#host_wrapper').unbind().dblclick(function() {
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
	}).on('mouseenter', function() {
		$(this).addClass('ui-state-hover');
		$('input#host').addClass('ui-state-hover');
	}).on('mouseleave', function() {
		$(this).removeClass('ui-state-hover');
		$('#host').removeClass('ui-state-hover');
		hostTimer = setTimeout(function() { $('#host').autocomplete('close'); }, 800);
	});

	var hostPrefix = '';
	$('#host').autocomplete('widget').each(function() {
		hostPrefix=$(this).attr('id');

		if (hostPrefix != '') {
			$('ul[id="'+hostPrefix+'"]').on('mouseenter', function() {
				clearTimeout(hostTimer);
			}).on('mouseleave', function() {
				hostTimer = setTimeout(function() { $('#host').autocomplete('close'); }, 800);
				$(this).removeClass('ui-state-hover');
				$('input#host').removeClass('ui-state-hover');
			});
		}
	});

	// Hid the scroll bar when not hovering
	var hoverTimer;
	$('.cactiConsoleNavigationArea').unbind().mouseenter(function() {
		clearTimeout(hoverTimer);
		hoverTimer = setTimeout(function() {
			$('.cactiConsoleNavigationArea').css('overflow-y', 'auto');
		}, 500);
	}).mouseleave(function() {
		clearTimeout(hoverTimer);
		hoverTimer = setTimeout(function() {
			$('.cactiConsoleNavigationArea').css('overflow-y', 'hidden');
		}, 500);
	});
	$('.cactiTreeNavigationArea').unbind().mouseenter(function() {
		clearTimeout(hoverTimer);
		hoverTimer = setTimeout(function() {
			$('.cactiTreeNavigationArea').css('overflow-y', 'auto');
		}, 500);
	}).mouseleave(function() {
		clearTimeout(hoverTimer);
		hoverTimer = setTimeout(function() {
			$('.cactiTreeNavigationArea').css('overflow-y', 'hidden');
		}, 500);
	});
}

function setMenuVisibility() {
	storage=Storages.localStorage;

	// Initialize the navigation settings
	$('#navigation').hide();
	$('li.menuitem').each(function() {
		active = storage.get($(this).attr('id'));
		if (active != null && active == 'active') {
			$(this).find('ul').attr('aria-hidden', 'false').attr('aria-expanded', 'true').show();
			$(this).next('a').show();
		}else{
			$(this).find('ul').attr('aria-hidden', 'true').attr('aria-expanded', 'false').hide();
			$(this).next('a').hide();
		}

		if ($(this).find('a.selected').length) {
			$('li.menuitem').not('#'+$(this).attr('id')).each(function() {
				$(this).find('ul').attr('aria-hidden', 'true').attr('aria-expanded', 'false').hide();
				$(this).next('a').hide();
				storage.set($(this).closest('.menuitem').attr('id'), 'collapsed');
			});

			if ($(this).is(':hidden')) {
				$(this).find('ul').attr('aria-hidden', 'false').attr('aria-expanded', 'true').show();
				$(this).next('a').show();
				storage.set($(this).closest('.menuitem').attr('id'), 'active');
			}
		}
	});
	$('#navigation').show();

	// Functon to give life to the Navigation pane
	$('#nav li:has(ul) a.active').unbind().click(function(event) {
		event.preventDefault();

		id = $(this).closest('.menuitem').attr('id');

		if ($(this).next().is(':visible')){
			$(this).next('ul').attr('aria-hidden', 'true').attr('aria-expanded', 'false');
			$(this).next().slideUp( { duration: 200, easing: 'swing' } );
			storage.set($(this).closest('.menuitem').attr('id'), 'collapsed');
		} else {
			$(this).next('ul').attr('aria-hidden', 'false').attr('aria-expanded', 'true');
			$(this).next().slideToggle( { duration: 200, easing: 'swing' } );
			if ($(this).next().is(':visible')) {
				storage.set($(this).closest('.menuitem').attr('id'), 'active');
			}else{
				storage.set($(this).closest('.menuitem').attr('id'), 'collapsed');
			}
		}

		$('li.menuitem').not('#'+id).each(function() {
			text = $(this).attr('id');
			id   = $(this).attr('id');

			$(this).find('ul').attr('aria-hidden', 'true').attr('aria-expanded', 'false');
			$(this).find('ul').slideUp( { duration: 200, easing: 'swing' } );
			storage.set($(this).attr('id'), 'collapsed');
		});
	});
}

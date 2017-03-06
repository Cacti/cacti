// Host Autocomplete Magic
var pageName = basename($(location).attr('pathname'));

/* only perform the recalculation of elements at the final end of the windows resize event */
var waitForFinalEvent = (function () {
  var timers = {};
  return function (callback, ms, uniqueId) {
    if (!uniqueId) {
      uniqueId = "Don't call this twice without a uniqueId";
    }
    if (timers[uniqueId]) {
      clearTimeout (timers[uniqueId]);
    }
    timers[uniqueId] = setTimeout(callback, ms);
  };
})();

$(window).resize(function () {
	waitForFinalEvent(function(){
		/* close open dropdown menues first off */
		$('.dropdownMenu > ul').hide();
		
		heightPage = $(window).height();
		heightPageHead = $('#cactiPageHead').outerHeight();
		heightPageContent = heightPage -heightPageHead +1;
		$('body').css('height', heightPage);
		$('#cactiContent').css('height', heightPageContent);
		
		/* check visibility of all tabs */
		$('#submenu-ellipsis').empty();
		$('.maintabs nav ul li a').each(function() {
			id = $(this).attr('id');
			if( $(this).offset().top !== 0 ) {
				if( $('#' + id + '-ellipsis').length == 0 ) {
					var str = $(this).parent().html();
					var str2 = str.replace( id , id + '-ellipsis');
					$('#submenu-ellipsis').prepend('<li>' + str2 + '</li>');
				}
			}else {
				$('#' + id + '-ellipsis').parent().remove();
			}
		});

		if($("#submenu-ellipsis li").length == 0) {
			$(".ellipsis").hide(0);
		}else {
			$(".ellipsis").show(0);
		}
	}, 200, "resize-content");
});

$('<div id="cactiPageBottom" class="cactiPageBottom"></div>').insertAfter('#cactiContent');

function themeReady() {
	var pageName = basename($(location).attr('pathname'));
	var hostTimer = false;
	var clickTimeout = false;
	var hostOpen = false;

	// Setup the navigation menu
	setMenuVisibility();

	// Add nice search filter to filters
	$('input[id="filter"]').after("<i class='fa fa-search filter'/>").attr('autocomplete', 'off').attr('placeholder', 'Enter a search term').parent('td').css('white-space', 'nowrap');

	$('input#filter').addClass('ui-state-default ui-corner-all');

	$('input[type="text"], input[type="password"], input[type="checkbox"], textarea').not('image').addClass('ui-state-default ui-corner-all');
	
	/* Start clean up */

	//login page
	$('.cactiLoginLogo').html("<i class='fa fa-paw'/>").css('font-size: 20px');
	
	/* clean up the navigation menu */
	$('.cactiConsoleNavigationArea').find('#menu').appendTo($('.cactiConsoleNavigationArea').find('#navigation'));
	$('.cactiConsoleNavigationArea').find('#navigation > table').remove();

	/* 'ellipsis' menu in the middle */
	if ($('.ellipsis').length == 0) {
		$('<div class="maintabs ellipsis">'
			+'<nav><ul>'
				+'<li class="maintabs-submenu">'
					+'<a class="submenu-ellipsis" href="#"><i class="fa fa-angle-down"></i></a></li>'
			+'</ul></nav>'
		+'</div>').insertAfter('.maintabs');	
	}
	$('<div class="dropdownMenu">'
		+'<ul id="submenu-ellipsis" class="submenuoptions" style="display:none;">'
		+'</ul>'
	+'</div>').appendTo('body');	
	
	/* Hey - No footer available ? */
	//if($('#cactiPageBottom').length == 0) {
		//$('<div id="cactiPageBottom" class="cactiPageBottom"></div>').insertAfter('#cactiContent');
	//}
	/* Console? Nope! */
	submenu_counter = 10;
		
	$('.maintabs nav ul li a').each( function() {
		id = $(this).attr('id');
		if ( id == 'maintab-anchor-console') {
			$(this).html("<i class='fa fa-paw'/>").css({'border-right':'1px solid #aaa'});
		}else if ( id == 'maintab-anchor-graphs' && $(this).parent().hasClass('maintabs-has-submenu') == 0 ) {
			submenu_counter++;
			$(this).parent().addClass('maintabs-has-submenu');
			$('<li class="maintabs-submenu"><a class="submenu-' + submenu_counter + '" href="#"><i class="fa fa-angle-down"></i></a></li>').insertAfter( $(this) );
			$('<div class="dropdownMenu">'
				+'<ul id="submenu-' + submenu_counter + '" class="submenuoptions" style="display:none;">'
					+'<li><a href="graph_view.php?action=tree">Tree View</a></li>'
					+'<li><a href="graph_view.php?action=list">List View</a></li>'
					+'<li><a href="graph_view.php?action=preview">Preview View</a></li>'
				+'</ul>'
			+'</div>').appendTo('body');
		}else {
			/* plugin stuff here ? */
		}		
	});

	/* user menu on the right ... */
	if ($('.usertabs').length == 0) {
		$('<div class="maintabs usertabs">'
			+'<nav><ul>'
				+'<li class="action-icon-user"><a class="pic" href="#"><i class="fa fa-user"></i></a></li>'
			+'</ul></nav>'
		+'</div>').insertAfter('.ellipsis');	
	}
	
	ajaxAnchors();
	
	/* User Menu */
	$('.menuoptions').parent().appendTo('body');

	$(window).trigger('resize');

	$('.action-icon-user').unbind().click(function(event) {
		event.preventDefault();

		if( $('.menuoptions').is(':visible') === false ) {
			$('.submenuoptions').slideUp(120);
			$('.menuoptions').slideDown(120);
		}else {
			$('.menuoptions').slideUp(120);
		}
		return false;
	});

	$('.maintabs-submenu').unbind('click').click(function(event) {
		event.preventDefault();
		
		submenu_index = $(this).children('a:first').attr('class');
		submenu = $('#'+submenu_index);
		
		if( submenu.is(':visible') === false ) {
			/* close other drop down menus first */
			$('.submenuoptions').slideUp(120);
			$('.menuoptions').slideUp(120);
			/* re-position */
			position = $(this).parent('.maintabs-has-submenu').position();
			if(!position) {
				position = $(this).position();
				submenu.css({'left':position.left - parseInt(submenu.outerWidth()) + parseInt($(this).outerWidth()) }).slideDown(120);
			}else {
				/* move dd to the left */
				submenu.css({'left':position.left}).slideDown(120);
			}
		}else {
			submenu.slideUp(120);
		}
		
		return false;
	});

	/* Highlight sortable table columns */
	$('.tableHeader th').has('i.fa-unsorted').removeClass('tableHeaderColumnHover tableHeaderColumnSelected');
	$('.tableHeader th').has('i.fa-sort-asc').addClass('tableHeaderColumnSelected');
	$('.tableHeader th').has('i.fa-sort-desc').addClass('tableHeaderColumnSelected');
	$('.tableHeader th').has('i.fa-unsorted').hover(
		function() {
			$( this ).addClass("tableHeaderColumnHover" );
		}, function() {
			$( this ).removeClass( "tableHeaderColumnHover" );
		}
	);
	
	$.ui.selectmenu.prototype._renderItem = function(ui, item) {
		if (item.element.closest('select').hasClass('colordropdown')) {
			if (item.label != 'None') {
				var li = $("<li>", { text: item.label });

				$('<span>', {
					style: item.element.attr('data-style'),
					'class': 'ui-icon color-icon'
				}).appendTo(li);
			}else{
				var li = $("<li>", { text: item.label });
			}
		}else if (item.element.closest('select').hasClass('iconselect')) {
			var li = $('<li>', { text: item.label });

			if (item.disabled) {
				li.addClass('ui-state-disabled');
			}

			$('<span>', {
				style: item.element.attr('data-style'),
				'class': 'ui-icon ' + item.element.attr('data-class')
			}).appendTo(li);

			return li.appendTo(ui);
		}else{
			var li = $("<li>");

			this._setText(li, item.label);
		}

		if (item.disabled) {
			li.addClass("ui-state-disabled");
		}

		return li.appendTo(ui);
	}

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

				minWidth+=80;
				$('#'+id+'-button').css('min-width', minWidth+'px').css('max-width', '400px').css('width','');
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

	$('#host, #host_click').click(function() {
		if (!hostOpen) {
			$('#host').autocomplete('option', 'minLength', 0).autocomplete('search', '');
			hostOpen = true;
		}else{
			$('#host').autocomplete('close');
			hostOpen = false;
		}
	});

/* End clean up */


	/* Notification Handler */
	if( $("#message").length ) {
	//	alert($('#message_container').html());
	}

	/* Replace icons */
	$('.fa-arrow-down').addClass('fa-chevron-down').removeClass('fa-arrow-down');
	$('.fa-arrow-up').addClass('fa-chevron-up').removeClass('fa-arrow-up');
	$('.fa-remove').addClass('fa-trash-o').removeClass('fa-remove');


}

function setMenuVisibility() {
	storage=$.localStorage;

	// Initialize the navigation settings
	$('#navigation').hide();
	$('li.menuitem').each(function() {
		active = storage.get($(this).attr('id'));
		if (active != null) {
			if (active == 'active') {
				$(this).find('ul').attr('aria-hidden', 'false').attr('aria-expanded', 'true').show();
				$(this).next('a').show();
			}else{
				$(this).find('ul').attr('aria-hidden', 'true').attr('aria-expanded', 'false').hide();
				$(this).next('a').hide();
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
	});
}
	

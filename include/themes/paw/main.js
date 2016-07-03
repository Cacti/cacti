// Host Autocomplete Magic
var pageName = basename($(location).attr('pathname'));


/* only perform the the whole resizing blabla at the final end of the windows resize event */
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


function themeReady() {
	var pageName = basename($(location).attr('pathname'));
	var hostTimer = false;
	var clickTimeout = false;
	var hostOpen = false;

	$(window).resize(function () {
		waitForFinalEvent(function(){
			heightPage = $(window).height();
			heightPageHead = $('#cactiPageHead').outerHeight();
			heightPageBottom = $('#cactiPageBottom').outerHeight();
			heightPageContent = heightPage -heightPageHead -heightPageBottom +1;
			
			$('body').css('height', heightPage);
			$('#cactiContent').css('height', heightPageContent);
		}, 200, "resize-content");
	});
	
	// Setup the navigation menu
	setMenuVisibility();

	// Add nice search filter to filters
	$('input[id="filter"]').after("<i class='fa fa-search filter'/>").attr('autocomplete', 'off').attr('placeholder', 'Enter a search term').parent('td').css('white-space', 'nowrap');

	$('input#filter').addClass('ui-state-default ui-corner-all');

	$('input[type="text"], input[type="password"], input[type="checkbox"], textarea').not('image').addClass('ui-state-default ui-corner-all');


	
/* Start clean up */

	//login page
	$('head').append('<link href="/cactidev/include/fa/css/font-awesome.css" type="text/css" rel="stylesheet">');
	$('.cactiLoginLogo').html("<i class='fa fa-paw'/>").css('font-size: 20px');
	
	/* clean up the navigation menu */
	$('.cactiConsoleNavigationArea').find('#menu').appendTo($('.cactiConsoleNavigationArea').find('#navigation'));
	$('.cactiConsoleNavigationArea').find('#navigation > table').remove();

	/* Hey - No footer available ? */
	$('<div id="cactiPageBottom" class="cactiPageBottom"></div>').insertAfter('#cactiContent');
	
	/* Console? Nope! */
	$('.maintabs nav ul li a').each( function() {	
		if ( $(this).attr('id') == 'maintab-anchor-console') {
			$(this).html("<i class='fa fa-paw'/>").css({'border-right':'1px solid #aaa'});
		}else if ( $(this).attr('id') == 'maintab-anchor-graphs' && $(this).parent().hasClass('maintabs-has-submenu') == 0 ) {
			//link = $(this).parent().html();
			$(this).parent().addClass('maintabs-has-submenu');
			$('<li class="maintabs-submenu"><a class="submenu-link" href="#"><i class="fa fa-angle-down"></i></a></li>').insertAfter( $(this));
			$('body').append('<div class="submenu"><span><a>Tree View</a></span><span><a>List View</a></span><span><a>Preview View</a></span></div>')
		}
	});
	
	
	/* user menu on the right ... */
	if($('.usertabs').length == 0 ){
		$('<div class="maintabs usertabs">'
			+'<nav><ul>'
				+'<li><a class="pic" href="graph_settings.php"><i class="fa fa-search"></i></a></li>'
				+'<li><a class="pic" href="graph_settings.php"><i class="fa fa-cog"></i></a></li>'
				+'<li class="action-icon-user"><a class="pic" href="#"><i class="fa fa-user"></i></a></li>'
			+'</ul></nav>'
		+'</div>').insertAfter('.maintabs');	
	}
	
	ajaxAnchors();
	
	/* User Menu */
	$('.menuoptions').addClass('dropdownMenu').parent().appendTo('body');
	
	
	$(window).trigger('resize');
	$('.action-icon-user').unbind('click').click( function() {
		$('.menuoptions').slideToggle(120);
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
	

/* End clean up */


	/* Notification Handler */
	if( $("#message").length ) {
		alert($('#message_container').html());
	}

	/* Replace icons */
	$('.fa-arrow-down').addClass('fa-chevron-down').removeClass('fa-arrow-down');
	$('.fa-arrow-up').addClass('fa-chevron-up').removeClass('fa-arrow-up');
	$('.fa-remove').addClass('fa-trash-o').removeClass('fa-remove');


}

function setMenuVisibility() {
	storage=$.localStorage;

	// Initialize the navigation settings
	$('.menu_parent').each(function() {
		active = storage.get($(this).text());
		if (active != null) {
			if (active == 'active') {
				$(this).next().show();
			}else{
				$(this).next().hide();
			}
		}
	});

	// Functon to give life to the Navigation pane
	$('#nav li:has(ul) a.active').unbind().click(function(event) {
		event.preventDefault();
		if ($(this).next().is(':visible')){
			$(this).next().slideUp( { duration: 200, easing: 'swing' } );
			storage.set($(this).text(), 'collapsed');
		} else {
			$(this).next().slideToggle( { duration: 200, easing: 'swing' } );
			if ($(this).next().is(':visible')) {
				storage.set($(this).text(), 'active');
			}else{
				storage.set($(this).text(), 'collapsed');
			}
		}
	});
}



	

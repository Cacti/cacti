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

function keepWindowSize() {
	$(window).resize(function (event) {
		waitForFinalEvent(function(){
			heightPage = $(window).height();
			heightPageHead = $('#cactiPageHead').outerHeight();
			heightPageBottom = $('#cactiPageBottom').outerHeight();
			heightPageContent = heightPage -heightPageHead -heightPageBottom +1;

			$('body').css('height', heightPage);
			$('#cactiContent').css('height', heightPageContent);

			$('.cactiTreeNavigationArea').css('height', heightPageContent);
			width = parseInt($('#searcher').width())+65;
			$('.cactiTreeNavigationArea').css('width', width+'px');
			$('.cactiTreeNavigationArea > div').css('padding-top', '5px');
		}, 300, "resize-content");
	});
}

function themeReady() {
	height = get_height();
	$('#navigation, .cactiConsoleNavigationArea').css('height', height);
	$('#navigation, #navigation_right').show();

	keepWindowSize();

	$(window).unbind().resize(function(event) {
		if (pageName == 'graph_view.php') {
			treeWidth    = $('#navigation').width();
			totalWidth   = $('body').width();
			contentWidth = totalWidth - treeWidth - 25;
			$('#navigation').css('width', treeWidth);
			$('#navigation_right').css('width', contentWidth);
		}

		if (!$(event.target).hasClass('ui-resizable')) {
			height = get_height();
			$('#navigation, .cactiConsoleNavigationArea').css('height', height);
		}
	});
}

function get_height() {
	nsh  = parseInt($('#navigation').prop('scrollHeight'));
	nrsh = parseInt($('#navigation_right').prop('scrollHeight'));
	nh   = parseInt($('#navigation').height());
	nrh  = parseInt($('#navigation_right').height());
	wht  = $(window).height();
	return Math.max(nsh, nrsh, nh, nrh, wht);
}

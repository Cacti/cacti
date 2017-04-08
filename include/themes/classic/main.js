function themeReady() {
	height = get_height();
	$('#navigation, .cactiConsoleNavigationArea').css('height', height);
	$('#navigation').show();

	$(window).unbind().resize(function(event) {
		height = get_height();
		treeWidth    = $('#navigation').width();
		totalWidth   = $('body').width();
		contentWidth = totalWidth - treeWidth - 25;
		$('#navigation').css('width', treeWidth);
		$('#navigation_right').css('width', contentWidth);

		if (!$(event.target).hasClass('ui-resizable')) {
			$('#navigation, .cactiConsoleNavigationArea').css('height', height);
		}
	});
}

function get_height() {
	nsh  = parseInt($('#navigation').prop('scrollHeight'));
	nrsh = parseInt($('#navigation_right').prop('scrollHeight'));
	nh   = parseInt($('#navigation').height());
	nrh  = parseInt($('#navigation_right').height());
	return Math.max(nsh, nrsh, nh, nrh);
}

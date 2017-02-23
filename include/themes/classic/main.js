function themeReady() {
	height = document.body.scrollHeight;
	$('body').css('height', height);
	$('#navigation').css('height', height);

	$(window).unbind().resize(function(event) {
		height       = document.body.scrollHeight;
		treeWidth    = $('#navigation').width();
		totalWidth   = $('body').width();
		contentWidth = totalWidth - treeWidth - 23;
		$('#navigation').css('width', treeWidth);
		$('#navigation_right').css('width', contentWidth);

		if (!$(event.target).hasClass('ui-resizable')) {
			$('#navigation').css('height', height);
		}
	});
}

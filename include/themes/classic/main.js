function themeReady() {
	height = document.body.scrollHeight;
	$('body').css('height', height);
	$('#navigation').css('height', height);

	$(window).resize(function(event) {
		height = document.body.scrollHeight;

		if (!$(event.target).hasClass('ui-resizable')) {
			$('#navigation').css('height', height);
		}
	});
}

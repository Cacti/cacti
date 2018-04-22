
function updateButtons(step, install_type) {
	$('#next, #previous, #test').button();
	if (step == 0) {
		$('#next').button('disable');
	}else if (step == 1) {
		$('#next').button('disable');
	}else if (step == 8) {
		// Was __('Finish');
		$('#next').val();
	}else if (step == 5 && install_type == 2) {
		// Was __('Finish');
		$('#next').val();
	}else if (step == 6 && install_type == 1) {
		// Was __('Finish');
		$('#next').val();
	}

	$('#previous').click(function() {
		document.location = '?step='+$('#previous_step').val();
	});

	if (step == 3) {
		// script is handled in the step
	}else if (enabled) {
		$('#next').button('enable');
	} else {
		$('#next').button('disable');
	}

	$('#database_hostname').keyup(function() {
		if ($('#database_hostname').val() == 'localhost') {
			$('#testdb').button('disable');
		}else if ($('#database_hostname').val() == '127.0.0.1') {
			$('#testdb').button('disable');
		} else {
			$('#testdb').button('enable');
		}
	});
}

function setButtonData(buttonName, buttonData) {
	button = $('#button'+buttonName);
	if (button != null) {
		button.button();
		button.data('buttonData', buttonData);
		buttonCheck = button.data('buttonData');
		if (buttonData.Enabled) {
			button.button('enable');
		} else {
			button.button('disable');
		}

		if (buttonData.Visible) {
			button.show();
		} else {
			button.hide();
		}

		button.val(buttonData.Text);
	}
}

function toggleHeader(key, initial = null) {
	if (key != null) {
		header = $(key);
		if (header != null && header.length > 0) {
			if (initial == null) {
				firstSibling = header.next();
				enable = (!firstSibling.is(':visible'));
			} else {
				enable = initial;
			}

			next = header.next();
			while (next != null && next.length > 0 && !next.is('h1,h2,h3,h4,h5,h6')) {
				if (enable) {
					if (initial != null) {
						next.show();
					} else {
						next.slideDown();
					}
				} else {
					if (initial != null) {
						next.hide();
					} else {
						next.slideUp();
					}
				}
				next = next.next();
			}
		}
	}		
}

function disableButton(buttonName) {
	button = $('#button'+buttonName);
	if (button != null) {
		button.button();
		button.button('disable');
	}
}

function performStep(install_step) {
	$.ajaxQ.abortAll();
	window.history.pushState("" , "Cacti Installation - Step " + install_step, 'index.php?step=' + install_step);
	url = 'step_json.php?step=' + install_step;
	$.get(url)
		.done(function(data) {
			checkForLogout(data);

			$('#installContent').empty().hide();
			$('div[class^="ui-"]').remove();
			$('#installContent').html(data.html);
			$('#installContent').show();
			setButtonData('Previous',data.prev);
			setButtonData('Next',data.next);
			setButtonData('Test',data.test);

			$('buttonTest').button('enable');
			$('buttonTest').val(data);
			$('buttonTest').show();

			if (data.step_data != null)  {
				step_data = data.step_data;
				for (var key in step_data) {
					// skip loop if the property is from prototype
					if (!step_data.hasOwnProperty(key)) continue;

					var enabled = step_data[key];
					var element = $('#' + key);
					if (element != null && element.length > 0) {
						fa_icon = 'fa fa-exclamation-triangle';
						if (enabled) {
							fa_icon = 'fa fa-check-circle';
							toggleHeader(element, false);
						}

						element.append('<div class="cactiInstallValid"><i class="' + fa_icon + '"></i></div>');

						element.click(function(e) {
							toggleHeader(e.currentTarget);
						});
					} else {
						window.alert('missing section "' + key + '"');
					}
				}
			}

			if ($('#accept').length) {
				$('#accept').click(function() {
					if ($(this).is(':checked')) {
						$('#buttonNext').button('enable');
					} else {
						$('#buttonNext').button('disable');
					}
				});

				if ($('#accept').is(':checked')) {
					$('#buttonNext').button('enable');
				} else {
					$('#buttonNext').button('disable');
				}
			}

			$('.installButton').click(function(e) {
				button = $(e.currentTarget);
				if (button != null) {
					buttonData = button.data('buttonData');
					if (buttonData != null) {
						performStep(buttonData.Step);
						return;
					}
				}
				getPresentHTTPError('');
			});
		})
		.fail(function(data) {
			getPresentHTTPError(data);
		}
	);
}

install_step = 0;

$().ready(function() {
	disableButton('Previous');
	disableButton('Next');
	disableButton('Test');
	performStep(0);
});


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
		if (buttonData != null) {
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

function collapseHeadings(headingStates) {
	for (var key in headingStates) {
		// skip loop if the property is from prototype
		if (!headingStates.hasOwnProperty(key)) continue;

		var enabled = headingStates[key];
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

function hideHeadings(headingStates) {
	for (var key in headingStates) {
		// skip loop if the property is from prototype
		if (!headingStates.hasOwnProperty(key)) continue;

		var enabled = headingStates[key];
		var element = $('#' + key);
		if (element != null && element.length > 0) {
			if (enabled) {
				element.show();
				toggleHeader(element, true);
			} else {
				element.hide();
				toggleHeader(element, false);
			}
		} else {
			window.alert('missing section "' + key + '"');
		}
	}
}

function processStep1(StepData) {
	if (StepData.Eula == 1) {
		$("#accept").prop('checked',true);
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
}

function processStep2(StepData) {
	collapseHeadings(StepData);
}

function processStep3(StepData) {
	hideHeadings(StepData);

	$("#install_type").on('change', function() {
		installData = $("#installData").data("installData");
		//debugger;
		installData.Mode = this.value;
		$("#installData").data("installData", installData);
		performStep(3);
	});
}

function getDefaultInstallData() {
	return { Step: 1, Eula: 0 };
}

function prepareInstallData(installStep) {
	//debugger;
	installData = $("#installData").data('installData');
	if (typeof installData == 'undefined') {
		installData = getDefaultInstallData();
	}

	step = installData.Step;
	if (step == 1) {
		element = $("#accept");
		if (element != null && element.length > 0) {
			installData.Eula = element.is(':checked');
		}
	} else if (step == 2) {
		element = $("#mode");
		if (element != null && element.length > 0) {
			installData.Mode = element.val();
		}
	}

	props = [ 'Step', 'Eula', 'Mode' ];
	newData = getDefaultInstallData();
	for (i = 0; i < props.length; i++) {
		propName = props[i];
		if (installData.hasOwnProperty(propName)) {
			newData[propName] = installData[propName];
		}
	}

	if (typeof installStep != 'undefined') {
		newData.Step = installStep;
	}

	return JSON.stringify(newData);
}

function performStep(installStep) {
	$.ajaxQ.abortAll();

	installData = prepareInstallData(installStep);
	url = 'step_json.php?data=' + installData;

	$.get(url)
		.done(function(data) {
			checkForLogout(data);

			$("#installData").data("installData", data);

			window.history.pushState("" , "Cacti Installation - Step " + data.Step, 'index.php?data=' + prepareInstallData());

			$('#installContent').empty().hide();
			$('div[class^="ui-"]').remove();
			$('#installContent').html(data.Html);
			$('#installContent').show();

			if (typeof $("#installData").data("debug") != 'undefined') {
				debugData = data;
				debugData.Html = '';
				debug = $('#installDebug');
				debug.empty();
				debug.html('<h5 style="border: 1px dashed grey">' + JSON.stringify(debugData) + '</h5>');
 			}

			setButtonData('Previous',data.Prev);
			setButtonData('Next',data.Next);
			setButtonData('Test',data.Test);

			$('buttonTest').button('enable');
			$('buttonTest').val(data);
			$('buttonTest').show();

			if (data.StepData != null)  {
				if (data.Step == 1) {
					processStep1(data.StepData);
				} else if (data.Step == 2) {
					processStep2(data.StepData);
				} else if (data.Step == 3) {
					processStep3(data.StepData);
				}
			}
		})
		.fail(function(data) {
			getPresentHTTPError(data);
		}
	);
}

$.urlParam = function(name){
    var results = new RegExp('[\?&]' + name + '=([^&#]*)').exec(window.location.href);
    if (results==null){
       return null;
    }
    else{
       return decodeURI(results[1]) || 0;
    }
}

install_step = 0;

$().ready(function() {
	disableButton('Previous');
	disableButton('Next');
	disableButton('Test');

	//debugger;
	installData = $.urlParam('data');
	if (installData != null && installData != 0) {
//		try {
			installData = JSON.parse(installData);
//		} catch (ex) {
//			installData = getDefaultInstallData();
//		}
	}
	$("#installData").data('installData', installData);

	installDebug = $.urlParam("debug");
	if (installDebug != null && installDebug != 0) {
		$("#installData").data("debug", true);
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
	performStep();
});


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

function processStep5(StepData) {
	collapseHeadings(StepData);

}

function processStep6(StepData) {
	element = $("#selectall");
	if (element != null && element.length > 0) {
		element.click();
//prop("checked", true);
	}
}

function processStep7(StepData) {
	if ($('#confirm').length) {
		$('#confirm').click(function() {
			if ($(this).is(':checked')) {
				$('#buttonNext').button('enable');
			} else {
				$('#buttonNext').button('disable');
			}
		});

		if ($('#confirm').is(':checked')) {
			$('#buttonNext').button('enable');
		} else {
			$('#buttonNext').button('disable');
		}
	}
}

function processStep97Refresh() {
	performStep(97);
}

function processStep97Status(current, total) {
	return "";
}

function processStep97(StepData) {
	progress(0.0, 1.0, $("#cactiInstallProgressCountdown"), processStep97Refresh, processStep97Status);
	setProgressBar(StepData.Current, StepData.Total, $("#cactiInstallProgressBar"), 0.0);
}


function setProgressBar(current, total, element, updatetime, fnStatus) {
	var progressBarWidth = element.width() * (current / total);
	if (fnStatus != null) {
		status = fnStatus(current, total);
	} else {
		status = (current * 100) / total + "&nbsp;%";
	}
	element.find('div').animate({ width: progressBarWidth }, updatetime).html(status);
}

function progress(timeleft, timetotal, $element, fnComplete, fnStatus) {
	setProgressBar(timetotal, timetotal, $element, 0, fnStatus);
	setProgressBar(timeleft, timetotal, $element, 5000, fnStatus);
	setTimeout(function() {
		fnComplete();
	}, 5000);
}

function progress_old(timeleft, timetotal, $element, fnComplete, fnStatus) {
	setProgressBar(timeleft, timetotal, $element, 100, fnStatus);
	if(timeleft < timetotal - 0.1) {
		setTimeout(function() {
			//progress(timeleft + 0.5, timetotal, $element, fnComplete, fnStatus);
			fnComplete();
		}, 100);
	} else if (fnComplete != null) {
		fnComplete();
	}
}

function getDefaultInstallData() {
	return { Step: 1, Eula: 0 };
}

function prepareInstallData(installStep) {
	//debugger;
	installData = $("#installData").data('installData');
	if (typeof installData == 'undefined' || installData == null) {
		installData = getDefaultInstallData();
	}

	newData = getDefaultInstallData();

	props = [ 'Step' , 'Eula' ];
	for (i = 0; i < props.length; i++) {
		propName = props[i];
		if (installData.hasOwnProperty(propName)) {
			newData[propName] = installData[propName];
		}
	}

	step = installData.Step;
	if (step == 1) prepareStep1(newData);
	else if (step == 3) prepareStep3(newData);
	else if (step == 4) prepareStep4(newData);
	else if (step == 6) prepareStep6(newData);

	if (typeof installStep != 'undefined') {
		newData.Step = installStep;
	}

	return JSON.stringify(newData);
}

function prepareStep1(installData) {
	element = $("#accept");
	if (element != null && element.length > 0) {
		installData.Eula = element.is(':checked');
	}
}

function prepareStep3(installData) {
	element = $("#mode");
	if (element != null && element.length > 0) {
		installData.Mode = element.value;
	}
}

function prepareStep4(installData) {
	paths = {}
	$('input[name^="path_"]').each(function(index,element) {
		paths[element.id] = element.value;
	});

	installData.Paths = paths;
	element = $("#selected_theme");
	if (element != null && element.length > 0) {
		installData.Theme = element.value;
	}

	element = $("#rrdtool_version");
	if (element != null && element.length > 0) {
		installData.RRDVer = element.value;
	}
}

function prepareStep6(installData) {
	templates = {}
	$('input[name^="chk_"]').each(function(index,element) {
		templates[element.id] = element.value;
	});
	installData.Templates = templates;
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

			if (data.Step == 1) {
				processStep1(data.StepData);
			} else if (data.Step == 2) {
				processStep2(data.StepData);
			} else if (data.Step == 3) {
				processStep3(data.StepData);
			} else if (data.Step == 5) {
				processStep5(data.StepData);
			} else if (data.Step == 6) {
				processStep6(data.StepData);
			} else if (data.Step == 7) {
				processStep7(data.StepData);
			} else if (data.Step == 97) {
				processStep97(data.StepData);
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
		try {
			installData = JSON.parse(installData);
		} catch (ex) {
			installData = getDefaultInstallData();
		}
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
				if (buttonData.Step == -1) {
					window.location.assign('../../');
				} else {
					performStep(buttonData.Step);
				}
				return;
			}
		}
		getPresentHTTPError('');
	});
	performStep();
});

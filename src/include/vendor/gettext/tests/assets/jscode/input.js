$(document).ready(function () {
	var value = i18n.__('Value with simple quotes');
	var value2 = __("Value with double quotes");
	var value3 = i18n.gettext("Other value with double quotes", 'and other value');

	var value4 = myFunction(__('function inside function', 'with 2 arguments'), 'other argument');

	/* var value5 = __("Commented function, not valid");*/
	// var value6 = __('Other commented function')
        
        var resp = __("I can't get response.");
        resp += " " + noop__("Please, try with other interface type.");
        
        resp = '<div class="alert alert-danger">';
        resp += __("I can't get response. Please, try with other interface type.");
        resp += '</div>';
});
$.toastPopup = function(options) {
    var fixedOptions = {
        stack: 50,
        textAlign: 'left',
        allowToastClose: true,
        position: 'bottom-right',
        showHideTransition: 'fade',
    }

    var defaultOptions = {
        text: "No text was set, please check the usage of showToast",
        hideAfter: 5000,
        loader: true,
    }

    if (typeof options == "undefined") {
        options = {};
    } else if (typeof options == "string" || typeof options == "String") {
        options = {
            text: options
        };
    }

    toastOptions = $.extend(defaultOptions, options, fixedOptions);
    return $.toast(toastOptions);
}

PopupWarning = function(message, heading, currentToast) {
    return $.toastPopup({
        text: message,
        heading: heading,
        icon: 'warning',
		hideAfter: false,
    }, currentToast);
}

PopupUnknown = function(message, heading, currentToast) {
    return $.toastPopup({
        text: message,
        heading: heading,
        icon: 'info',
		hideAfter: false,
    }, currentToast);
}

PopupError = function(message, heading, currentToast) {
    return $.toastPopup({
        text: message,
        heading: heading,
        icon: 'error',
        hideAfter: false,
    }, currentToast);
}

PopupNotice = function(message, heading, currentToast) {
    return $.toastPopup({
        text: message,
        heading: heading,
        icon: 'success',
    }, currentToast);
}

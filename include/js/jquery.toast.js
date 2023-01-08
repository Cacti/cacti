// jQuery toast plugin created by Kamran Ahmed copyright MIT license 2015
if (typeof Object.create !== 'function') {
    Object.create = function(obj) {
        function F() {}
        F.prototype = obj;
        return new F();
    };
}

(function($, window, document, undefined) {
    "use strict";

    var Toast = {

        _positionClasses: ['bottom-left', 'bottom-right', 'top-right', 'top-left', 'bottom-center', 'top-center', 'mid-center'],
        _defaultIcons: ['success', 'error', 'info', 'warning'],

        init: function(options, elem) {
            this.prepareOptions(options, $.toast.options);
            this.process();
        },

        prepareOptions: function(options, options_to_extend) {
            var _options = {};
            if ((typeof options === 'string') || (options instanceof Array)) {
                _options.text = options;
            } else {
                _options = options;
            }
            this.options = $.extend({}, options_to_extend, _options);
        },

        process: function() {
            this.setup();
            this.addToDom();
            this.position();
            this.bindToast();
            this.animate();
        },

        setup: function() {

            var _toastContent = '';

            this._toastEl = this._toastEl || $('<div></div>', {
                class: 'jq-toast-single'
            });

            // For the loader on top
            _toastContent += '<span class="jq-toast-loader"></span>';

            if (this.options.allowToastClose) {
                _toastContent += '<span class="close-jq-toast-single">&times;</span>';
            };

            if (this.options.text instanceof Array) {

                if (this.options.heading) {
                    _toastContent += '<h2 class="jq-toast-heading">' + this.options.heading + '</h2>';
                };

                _toastContent += '<ul class="jq-toast-ul">';
                for (var i = 0; i < this.options.text.length; i++) {
                    _toastContent += '<li class="jq-toast-li" id="jq-toast-item-' + i + '">' + this.options.text[i] + '</li>';
                }
                _toastContent += '</ul>';

            } else {
                if (this.options.heading) {
                    _toastContent += '<h2 class="jq-toast-heading">' + this.options.heading + '</h2>';
                };
                _toastContent += this.options.text;
            }

            this._toastEl.html(_toastContent);

            if (this.options.bgColor !== false) {
                this._toastEl.css("background-color", this.options.bgColor);
            };

            if (this.options.textColor !== false) {
                this._toastEl.css("color", this.options.textColor);
            };

            if (this.options.textAlign) {
                this._toastEl.css('text-align', this.options.textAlign);
            }

            if (this.options.icon !== false) {
                this._toastEl.addClass('jq-has-icon');

                if ($.inArray(this.options.icon, this._defaultIcons) !== -1) {
                    this._toastEl.addClass('jq-icon-' + this.options.icon);
                };
            };

            if (this.options.class !== false) {
                this._toastEl.addClass(this.options.class)
            }
        },

        position: function() {
            if ((typeof this.options.position === 'string') && ($.inArray(this.options.position, this._positionClasses) !== -1)) {

                if (this.options.position === 'bottom-center') {
                    this._container.css({
                        left: ($(window).outerWidth() / 2) - this._container.outerWidth() / 2,
                        bottom: 20
                    });
                } else if (this.options.position === 'top-center') {
                    this._container.css({
                        left: ($(window).outerWidth() / 2) - this._container.outerWidth() / 2,
                        top: 20
                    });
                } else if (this.options.position === 'mid-center') {
                    this._container.css({
                        left: ($(window).outerWidth() / 2) - this._container.outerWidth() / 2,
                        top: ($(window).outerHeight() / 2) - this._container.outerHeight() / 2
                    });
                } else {
                    this._container.addClass(this.options.position);
                }

            } else if (typeof this.options.position === 'object') {
                this._container.css({
                    top: this.options.position.top ? this.options.position.top : 'auto',
                    bottom: this.options.position.bottom ? this.options.position.bottom : 'auto',
                    left: this.options.position.left ? this.options.position.left : 'auto',
                    right: this.options.position.right ? this.options.position.right : 'auto'
                });
            } else {
                this._container.addClass('bottom-left');
            }
        },

        bindToast: function() {

            var that = this;

            this.resetTimer();

            this._toastEl.on('afterShown', function() {
                that.processLoader();
            });

            this._toastEl.find('.close-jq-toast-single').on('click', function(e) {

                e.preventDefault();

                if (that.options.showHideTransition === 'fade') {
                    that._toastEl.trigger('beforeHide');
                    that._toastEl.fadeOut(function() {
                        that._toastEl.trigger('afterHidden');
                    });
                } else if (that.options.showHideTransition === 'slide') {
                    that._toastEl.trigger('beforeHide');
                    that._toastEl.slideUp(function() {
                        that._toastEl.trigger('afterHidden');
                    });
                } else {
                    that._toastEl.trigger('beforeHide');
                    that._toastEl.hide(function() {
                        that._toastEl.trigger('afterHidden');
                    });
                }
            });

            if (typeof this.options.beforeShow == 'function') {
                this._toastEl.on('beforeShow', function() {
                    that.options.beforeShow(that._toastEl);
                });
            };

            if (typeof this.options.afterShown == 'function') {
                this._toastEl.on('afterShown', function() {
                    that.options.afterShown(that._toastEl);
                });
            };

            if (typeof this.options.beforeHide == 'function') {
                this._toastEl.on('beforeHide', function() {
                    that.options.beforeHide(that._toastEl);
                });
            };

            if (typeof this.options.afterHidden == 'function') {
                this._toastEl.on('afterHidden', function() {
                    that.options.afterHidden(that._toastEl);
                });
            };

            if (typeof this.options.onClick == 'function') {
                this._toastEl.on('click', function() {
                    that.options.onClick(that._toastEl);
                });
            };
        },

        addToDom: function() {

            var _container = $('.jq-toast-wrap');

            if (_container.length === 0) {

                _container = $('<div></div>', {
                    class: "jq-toast-wrap",
                    role: "alert",
                    "aria-live": "polite"
                });

                $('body').append(_container);

            } else if (!this.options.stack || isNaN(parseInt(this.options.stack, 10))) {
                _container.empty();
            }

            _container.find('.jq-toast-single:hidden').remove();

            _container.append(this._toastEl);

            if (this.options.stack && !isNaN(parseInt(this.options.stack), 10)) {

                var _prevToastCount = _container.find('.jq-toast-single').length,
                    _extToastCount = _prevToastCount - this.options.stack;

                if (_extToastCount > 0) {
                    $('.jq-toast-wrap').find('.jq-toast-single').slice(0, _extToastCount).remove();
                };

            }

            this._container = _container;
        },

        canAutoHide: function() {
            return (this.options.hideAfter !== false) && !isNaN(parseInt(this.options.hideAfter, 10));
        },

        processLoader: function() {
            // Show the loader only, if auto-hide is on and loader is demanded
            if (!this.canAutoHide() || this.options.loader === false) {
                return false;
            }

            var loader = this._toastEl.find('.jq-toast-loader');

            // 400 is the default time that jquery uses for fade/slide
            // Divide by 1000 for milliseconds to seconds conversion
            var transitionTime = (this.options.hideAfter - 400) / 1000 + 's';
            var loaderBg = this.options.loaderBg;

            var style = loader.attr('style') || '';
            style = style.substring(0, style.indexOf('-webkit-transition')); // Remove the last transition definition

            style += '-webkit-transition: width ' + transitionTime + ' ease-in; \
                      -o-transition: width ' + transitionTime + ' ease-in; \
                      transition: width ' + transitionTime + ' ease-in; \
                      background-color: ' + loaderBg + ';';


            loader.attr('style', style).addClass('jq-toast-loaded');
        },

        animate: function() {

            var that = this;

            this._toastEl.hide();

            this._toastEl.trigger('beforeShow');

            if (this.options.showHideTransition.toLowerCase() === 'fade') {
                this._toastEl.fadeIn(function() {
                    that._toastEl.trigger('afterShown');
                });
            } else if (this.options.showHideTransition.toLowerCase() === 'slide') {
                this._toastEl.slideDown(function() {
                    that._toastEl.trigger('afterShown');
                });
            } else {
                this._toastEl.show(function() {
                    that._toastEl.trigger('afterShown');
                });
            }
        },

        resetTimer: function() {
            if (this.timeoutID) {
                window.clearTimeout(this.timeoutID);
            }

            if (this.canAutoHide()) {
                var that = this;
                that.timeoutID = window.setTimeout(function() {

                    if (that.options.showHideTransition.toLowerCase() === 'fade') {
                        that._toastEl.trigger('beforeHide');
                        that._toastEl.fadeOut(function() {
                            that._toastEl.trigger('afterHidden');
                        });
                    } else if (that.options.showHideTransition.toLowerCase() === 'slide') {
                        that._toastEl.trigger('beforeHide');
                        that._toastEl.slideUp(function() {
                            that._toastEl.trigger('afterHidden');
                        });
                    } else {
                        that._toastEl.trigger('beforeHide');
                        that._toastEl.hide(function() {
                            that._toastEl.trigger('afterHidden');
                        });
                    }

                }, this.options.hideAfter);
            };

        },

        reset: function(resetWhat) {

            if (resetWhat === 'all') {
                $('.jq-toast-wrap').remove();
            } else {
                this._toastEl.remove();
            }

        },

        update: function(options) {
            this.prepareOptions(options, this.options);
            this.setup();

            this.position();
            this.bindToast();
        },

        close: function() {
            this._toastEl.find('.close-jq-toast-single').click();
        },

        Popup: function(options) {
            var fixedOptions = {
                stack: 50,
                position: 'bottom-right',
                showHideTransition: 'fade',
            }

            var defaultOptions = {
                text: "No text was set, please check the usage of showToast",
                allowToastClose: true,
                hideAfter: 5000,
                loader: true,
                textAlign: 'left',
            }

            if (typeof options == "undefined") {
                options = {};
            } else if (typeof options == "string" || typeof options == "String") {
                options = {
                    text: options
                };
            }

            toastOptions = $.extend(defaultOptions, options, fixedOptions);
            if (typeof this._toastEl == 'undefined') {
                return $.toast(toastOptions);
            }

            if (!$(this._toastEl).is(":hidden")) {
                this.update(toastOptions);
            } else {
                this.init(toastOptions);
            }
        }

    };

    $.toast = function(options) {
        var toast = Object.create(Toast);
        toast.init(options, this);

        return {

            reset: function(what) {
                toast.reset(what);
            },

            update: function(options) {
                toast.update(options);
            },

            close: function() {
                toast.close();
            },

            /* BlueDAG custom functions */
            Popup: function(options) {
                toast.Popup(options);
            },

            Error: function(message, heading, timeout = false) {
                if (typeof timeout == 'undefined' || timeout !== false || !isNaN(parseInt(timeout))) {
                    timeout = false;
                }

                return toast.Popup({
                    text: message,
                    heading: heading,
                    icon: 'error',
                    hideAfter: timeout,
                })
            },

            Warning: function(message, heading, timeout = 5000) {
                if (typeof timeout == 'undefined' || timeout !== false || !isNaN(parseInt(timeout))) {
                    timeout = 5000;
                }

                return toast.Popup({
                    text: message,
                    heading: heading,
                    icon: 'warning',
                    hideAfter: timeout,
                })
            },

            Success: function(message, heading, timeout = 3000) {
                if (typeof timeout == 'undefined' || timeout !== false || !isNaN(parseInt(timeout))) {
                    timeout = 3000;
                }

                return toast.Popup({
                    text: message,
                    heading: heading,
                    icon: 'success',
                    hideAfter: timeout,
                })
            },

            Info: function(message, heading, timeout = 3000) {
                if (typeof timeout == 'undefined' || timeout !== false || !isNaN(parseInt(timeout))) {
                    timeout = 3000;
                }

                return toast.Popup({
                    text: message,
                    heading: heading,
                    icon: 'info',
                    hideAfter: timeout,
                })
            },
        }
    };

    $.toast.options = {
        text: '',
        heading: '',
        showHideTransition: 'fade',
        allowToastClose: true,
        hideAfter: 3000,
        loader: true,
        loaderBg: '#9EC600',
        stack: 5,
        position: 'bottom-left',
        bgColor: false,
        textColor: false,
        textAlign: 'left',
        icon: false,
        beforeShow: function() {},
        afterShown: function() {},
        beforeHide: function() {},
        afterHidden: function() {},
        onClick: function() {}
    };

})(jQuery, window, document);
/*
  +-------------------------------------------------------------------------+
  | Copyright (C) 2004-2026 The Cacti Group                                 |
  |                                                                         |
  | This program is free software; you can redistribute it and/or           |
  | modify it under the terms of the GNU General Public License             |
  | as published by the Free Software Foundation; either version 2          |
  | of the License, or (at your option) any later version.                  |
  |                                                                         |
  | This program is distributed in the hope that it will be useful,         |
  | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
  | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
  | GNU General Public License for more details.                            |
  +-------------------------------------------------------------------------+
  | Cacti: The Complete RRDTool-based Graphing Solution                     |
  +-------------------------------------------------------------------------+
  | This code is designed, written, and maintained by the Cacti Group. See  |
  | about.php and/or the AUTHORS file for specific developer information.   |
  +-------------------------------------------------------------------------+
  | http://www.cacti.net/                                                   |
  +-------------------------------------------------------------------------+
*/
window.midwinter = window.midwinter || {};
midwinter.navigationBox = {
    _plugins: {},

    /**
     * registers a plugin by its namespace path
     * @param {string} path - string path to the plugin
     */
    registerPlugin: function (path) {
        const parts = path.split('.');
        const plugin = parts.reduce((obj, key) => (obj && obj[key] ? obj[key] : null), window);

        if (plugin && typeof plugin.getDefaultConfig === 'function') {
            // Check if the plugin is actually allowed to run (e.g., dependency check)
            const config = plugin.getDefaultConfig();

            if (config !== null) {
                const type = parts.pop();
                this._plugins[type] = plugin;
                // console.log(`[Plugin] Registered: ${type}`);
            } else {
                // Plugin aborted registration itself due to missing dependencies
                console.warn(`[Plugin] Registration aborted for ${path} (dependencies not met).`);
            }
        }
    },

    buildConfigs: function (boxDefinitions) {
        return boxDefinitions.map(def => {
            if (def.type && this._plugins[def.type]) {
                // get the default config from the plugin
                const config = this._plugins[def.type].getDefaultConfig(def.overrides || {});
                if (config) {
                    // we just ensure the type is set so add() can pick it up
                    config.pluginType = def.type;
                }
                return config;
            }
            return def;
        }).filter(config => config !== null);
    },

    refreshPlugins: function() {
        Object.keys(this._plugins).forEach(type => {
            const plugin = this._plugins[type];

            // Only target boxes that have the data-refresh attribute set to true
            $(`[data-plugin="${type}"][data-refresh="true"]`).each((index, el) => {
                const $box = $(el);

                if (typeof plugin.content === 'function') {
                    $box.find('.navBox-content').html(plugin.content($box));
                }
                if (typeof plugin.init === 'function') {
                    plugin.init($box);
                }
            });
        });
    }
}

class cactiNavigation {
    #refreshStorage = false;
    #prefix = 'mdw';
    #storageKey = 'navigationBox';

    target;

    #nav = {
        refs: {},
        session: {},
        storage: typeof Storages !== 'undefined' ? Storages.localStorage : null
    };

    #navOptions = {
        container: `${this.#prefix}-SideBarContainer`,

        dock: {
            enabled: true,
            wrapper: `${this.#prefix}-Main`,
            top: true, left: true, right: true, bottom: true
        },

        overlay: {enabled: true, left: true, right: true},
        window: {enabled: true, wrapper: `${this.#prefix}-Windows`}
    };

    constructor(options = {}) {
        /* identify caller */
        this.target = new.target;

        /* restore local storage */
        this._restoreLocalStorage();

        /* setup limited to parent instance only */
        if (this.target === cactiNavigation) {
            this._validateOptions(options, this.#navOptions);
            this.#nav.session = $.extend(true, {}, this.#navOptions, this.#nav.session);
            this._checkContainers();
            this._initDockResizables();
            this._applySavedSplits();
            this._refreshLocalStorage(this.#refreshStorage);
        }
    }

    _restoreLocalStorage() {
        if (this.#nav.storage?.isSet(this.#storageKey)) {
            try {
                const data = this.#nav.storage.get(this.#storageKey);
                this.#nav.session = typeof data === 'string' ? JSON.parse(data) : data;
                // Handle potential double stringification
                if (typeof this.#nav.session === 'string') {
                    this.#nav.session = JSON.parse(this.#nav.session);
                }
            } catch (e) {
                console.error("Navigation: Storage data corrupted.");
            }
        }

        if (!this._isObject(this.#nav.session)) {
            this.#nav.session = $.extend(true, {}, this.#navOptions);
            this._refreshLocalStorage(true);
        }
    }

    _refreshLocalStorage(force = false) {
        if (force === true && this.#nav.storage) {
            this.#nav.storage.set(this.#storageKey, JSON.stringify(this.#nav.session));
        }
    }

    _checkContainers() {
        const session = this.#nav.session;
        this._ensureContainer(session.container);

        if (session.dock.enabled) {
            const $wrapper = $(`#${session.dock.wrapper}`);
            if ($wrapper.length) {
                const positions = ["top", "left", "right", "bottom"];
                for (const [pos, value] of Object.entries(session.dock)) {
                    if (positions.includes(pos) && value === true) {
                        const name = `${this.#prefix}-Dock${this._ucFirst(pos)}`;
                        const dirs = ['left', 'right'].includes(pos) ? ['Top', 'Bottom'] : ['Left', 'Right'];
                        const innerHtml = dirs.map(d => `<div class="${this.#prefix}-DockInner${d}"></div>`).join('');
                        this._ensureContainer(name, $wrapper, innerHtml);
                    }
                }
            } else {
                session.dock.enabled = false;
                this.#refreshStorage = true;
            }
        }

        // restore dock with / height
        const savedSizes = this.#nav.session.dockSizes || {};

        for (const [dockId, size] of Object.entries(savedSizes)) {
            const $dock = $(`#${this.#prefix}-Dock${dockId}`);
            if ($dock.length) {
                const dimension = (dockId === 'Top' || dockId === 'Bottom') ? 'height' : 'width';
                $dock.css(dimension, size);
            }
        }
    }

    _initDockResizables() {
        const prefix = this.#prefix;
        const self = this;

        const docks = [
            { id: 'Top',    handle: 's', dim: 'height' },
            { id: 'Bottom', handle: 'n', dim: 'height' },
            { id: 'Left',   handle: 'e', dim: 'width'  },
            { id: 'Right',  handle: 'w', dim: 'width'  }
        ];

        docks.forEach(dock => {
            const $el = $(`#${prefix}-Dock${dock.id}`);
            if (!$el.length) return;

            $el.resizable({
                handles: dock.handle,
                stop: function(event, ui) {

                    const newVal = ui.size[dock.dim] + 'px';

                    if (!self.#nav.session.dockSizes) self.#nav.session.dockSizes = {};
                    self.#nav.session.dockSizes[dock.id] = newVal;

                    self._refreshLocalStorage(true);
                }
            });
        });

        // built dock container split
        $(`.${prefix}-DockInnerTop, .${prefix}-DockInnerLeft`).each(function() {
            const $this = $(this);
            const isVerticalSplit = $this.hasClass(`${prefix}-DockInnerTop`);
            const handle = isVerticalSplit ? 's' : 'e';
            const dimension = isVerticalSplit ? 'height' : 'width';

            $this.resizable({
                handles: handle,
                resize: function(event, ui) {
                    const $parent = $this.parent();

                    const parentSize = isVerticalSplit ? $parent.innerHeight() : $parent.innerWidth();
                    const currentSize = isVerticalSplit ? $this.outerHeight() : $this.outerWidth();

                    // calculate split percentage value of split position in relation to outer width or height
                    // this is important to keep relation if user resizes the browser window (e.g. switch to fullscreen)
                    let percent = (currentSize / parentSize) * 100;
                    percent = Math.max(10, Math.min(90, percent)); // Safety-Margin

                    // fix position in DOM
                    $this.css(dimension, percent + '%');
                    $this.siblings().css(dimension, (100 - percent) + '%');

                    // keep layout persistent
                    const dockId = $parent.attr('id');
                    if (!self.#nav.session.dockSplits) self.#nav.session.dockSplits = {};
                    self.#nav.session.dockSplits[dockId] = percent;
                },
                stop: function(event, ui) {
                    // avoid updating local storage too often
                    self._refreshLocalStorage(true);
                }
            });
        });
    }

    _applySavedSplits() {
        const splits = this.getConfig('dockSplits');
        if (!splits || !this._isObject(splits)) return;

        for (const [dockId, percent] of Object.entries(splits)) {
            const $dock = $(`#${dockId}`);
            if (!$dock.length) continue;

            const isVerticalDock = dockId.includes('Left') || dockId.includes('Right');
            const firstClass = isVerticalDock ? 'Top' : 'Left';
            const secondClass = isVerticalDock ? 'Bottom' : 'Right';
            const dimension = isVerticalDock ? 'height' : 'width';

            const $first = $dock.find(`.${this.#prefix}-DockInner${firstClass}`);
            const $second = $dock.find(`.${this.#prefix}-DockInner${secondClass}`);

            if ($first.length && $second.length) {
                $first.css(dimension, percent + '%');
                $second.css(dimension, (100 - percent) + '%');
            }
        }
    }

    _ensureContainer(id, parent = 'body', content = '') {
        let $el = $(`#${id}`);
        if (!$el.length) {
            $el = $(`<div id="${id}" class="${id}">${content}</div>`).appendTo(parent);
        }
        return $el;
    }

    _validateOptions(options, defaults) {
        if (!this._isObject(options)) return;

        for (const [key, value] of Object.entries(options)) {
            if (defaults.hasOwnProperty(key)) {
                const defaultType = typeof defaults[key];
                const valueType = typeof value;

                // Allow type match OR allow an object if the default was a string
                // (to support our {on, off} icon/tooltip logic)
                if (defaultType === valueType || (defaultType === 'string' && valueType === 'object')) {

                    if (this._isObject(value) && this._isObject(defaults[key])) {
                        // Deep merge for nested objects (like 'dock' or 'layout')
                        this._validateOptions(options[key], defaults[key]);
                    } else {
                        // Direct assignment for primitives or our special toggle-objects
                        defaults[key] = value;
                        if (this.target === cactiNavigation) {
                            this.#refreshStorage = true;
                        }
                    }
                } else {
                    console.warn(`Navigation: Type mismatch for property '${key}'. Expected ${defaultType}, got ${valueType}.`);
                }
            }
        }
    }

    /* helper & utility methods */

    _readConfigOption(obj, option = '', defVal = false) {
        const session = this.#nav.session;
        if (session.hasOwnProperty(obj)) {
            if (option === '') return session[obj];
            if (session[obj].hasOwnProperty(option)) return session[obj][option];
        }
        return defVal;
    }

    _isFunction(funcName) {
        if (!funcName || funcName === '') return false;

        // check local instance method
        if (typeof this[funcName] === 'function') return true;

        // helper to resolve path in a root object
        const resolvePath = (path, root) => {
            if (!root) return undefined;
            return path.split('.').reduce((prev, curr) => prev ? prev[curr] : undefined, root);
        };

        // check in registry
        if (typeof registry !== 'undefined') {
            if (typeof resolvePath(funcName, registry) === 'function') return true;
        }

        // check in window scope (supporting dot notation)
        return typeof resolvePath(funcName, window) === 'function';
    }

    _runFunction(path, args = null) {
        if (!path || typeof path !== 'string') return '';

        try {
            let func = undefined;
            let context = null;

            // search in current class instance
            if (typeof this[path] === 'function') {
                func = this[path];
                context = this;
            }

            // helper to resolve path and keep track of the parent (context)
            const resolvePath = (root) => {
                let parent = null;
                const target = path.split('.').reduce((obj, key) => {
                    parent = obj;
                    return (obj && typeof obj === 'object') ? obj[key] : undefined;
                }, root);
                return { target, parent };
            };

            // search in registry
            if (typeof func !== 'function' && typeof registry !== 'undefined') {
                const res = resolvePath(registry);
                func = res.target;
                context = res.parent;
            }

            // fallback: window scope
            if (typeof func !== 'function') {
                const res = resolvePath(window);
                func = res.target;
                context = res.parent;
            }

            // execution
            if (typeof func === 'function') {
                // use apply to maintain context and support multiple arguments (like key, opt)
                const finalArgs = Array.isArray(args) ? args : [args];
                return func.apply(context, finalArgs);
            } else {
                console.warn(`Navigation: Path '${path}' not found.`);
            }
        } catch (e) {
            console.error(`Navigation: Error executing function at path '${path}':`, e);
        }
        return '';
    }

    _runKeyEvent(event) {
        // Only trigger on Enter (13) or Space (32)
        if (!(event.keyCode === 13 || event.keyCode === 32)) return;
        event.preventDefault();

        const funcName = event.data.function;
        if (typeof window[funcName] === 'function') {
            window[funcName](event);
        }
    }

    _isObject(obj) {
        return obj !== null && typeof obj === 'object' && !Array.isArray(obj);
    }

    _ucFirst(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }

    _getBox(helper) {
        return $(`[class^="${this.#prefix}-ConsoleNavigationBox"][data-helper="${helper}"]`);
    }

    _getButton(helper) {
        return $(`[class^="compact_nav_icon"][data-helper="${helper}"]`);
    }

    getConfig(path = '') {
        return path.split('.').reduce((obj, key) => obj?.[key], this.#nav.session);
    }

    _updateNavigationBox($box, status = null) {
        if (typeof status === "boolean") {
            $box.toggleClass('visible', status);
        } else {
            $box.toggleClass('visible');
        }
    }

    _updateNavigationButton(helper, status = false) {
        // uses the existing _getButton helper
        this._getButton(helper).toggleClass('selected', status);
    }

    _hideSiblings($navigationBox) {
        const helper = $navigationBox.attr('data-helper');
        // find all other visible boxes in the same container and hide them
        $navigationBox.parent().find(`[class^='${this.#prefix}-ConsoleNavigationBox']:not([data-helper='${helper}'])`)
            .each((_, el) => {
                const $el = $(el);
                $el.removeClass('visible').attr('data-status', 'closed');
                this._updateNavigationButton($el.attr('data-helper'), false);
                // ensure persistence
                window.dispatchEvent(new CustomEvent('box:stateChanged', {
                    detail: {
                        helper: $el.attr('data-helper'),
                        attributes: ['status']
                    }
                }));
            });
    }

    _checkDockInnerSiblings($destination) {
        const $sibling = $destination.siblings().eq(0);

        // combined check: Box must be logically OPEN and visually VISIBLE
        const hasChildVisible = $destination.find('[data-status="open"]').length > 0;
        const hasNieceVisible = $sibling.find('[data-status="open"]').length > 0;

        $sibling.toggleClass('noSibling', !hasChildVisible && hasNieceVisible);
        $destination.toggleClass('noSibling', hasChildVisible && !hasNieceVisible);
    }

    checkConfigurationIntegrity(boxConfigs, btnConfigs) {
        const boxHelpers = boxConfigs.map(b => b.helper);
        const btnHelpers = btnConfigs.map(b => b.helper);

        // CRITICAL: Check for Boxes that have no Button to open them
        boxHelpers.forEach(helper => {
            if (!btnHelpers.includes(helper)) {
                console.error(`Navigation Integrity Error: Box '${helper}' is defined but has no corresponding Button. It will be unreachable in the UI!`);
            }
        });

        // INFO: Buttons without boxes are allowed (standalone actions)
        btnHelpers.forEach(helper => {
            if (!boxHelpers.includes(helper)) {
                console.info(`Navigation Info: Button '${helper}' is a standalone action (no box linked).`);
            }
        });
    }

    toggleBox(helper, action = 'toggle', filter = null) {
        const $box = this._getBox(helper);
        if (!$box.length) return;

        const style = $box.attr('data-style');
        const isExclusive = $box.attr('data-exclusive') !== 'false'; // Default to true
        const currentStatus = $box.attr('data-status');
        const isVisible = currentStatus === 'open';

        // determine if we are about to open the box
        const opening = (action === 'force_open' || (action === 'toggle' && !isVisible));

        if (opening) {
            if (style === 'dock') {
                this._hideSiblings($box);
            } else if (isExclusive) {
                this.closeAllBoxes(helper); // Close other overlays
            }

            $box.addClass('visible');
            $box.attr('data-status', 'open');
            this._updateNavigationButton(helper, true);

            // Handle search filter if provided
            if (filter) {
                const $searchField = $box.find("input[name='navBox-header-search']");
                $box.find('.navBox-header-search').removeClass('hide');
                if (filter !== 'reset') {
                    $searchField.focus().val(filter).trigger('input');
                } else {
                    $searchField.val('').trigger('input').blur();
                }
            }
        } else {
            // closing logic
            $box.removeClass('visible');
            $box.attr('data-status', 'closed');
            this._updateNavigationButton(helper, false);
        }

        // Always check siblings for dock style
        if (style === 'dock') {
            this._checkDockInnerSiblings($box.parent());
        }

        if (style === 'dock' || style === 'window') {
            // synchronize internal cache and storage of a boxes with static style type
            window.dispatchEvent(new CustomEvent('box:stateChanged', {
                detail: {
                    helper: helper,
                    attributes: ['status']
                }
            }));

        }
    }

    closeAllBoxes(excludeHelper = null) {
        // Only close overlays, as docked boxes are usually permanent
        $(`[class*="ConsoleNavigationBox"][data-style="overlay"].visible`).each((_, el) => {
            const helper = $(el).attr('data-helper');
            if (helper !== excludeHelper) {
                this.toggleBox(helper, 'force_close');
            }
        });
    }

    setBoxPresence(helper, visible) {
        const $box = this._getBox(helper);
        if (!$box.length) return;

        const currentStatus = $box.attr('data-status'); // 'open' or 'closed'
        const isUserOpen = (currentStatus === 'open');

        if (visible) {
            // CONTENT IS THERE:
            // Only show if the user actually wants it open (respect storage)
            if (isUserOpen) {
                this._updateNavigationBox($box, true);
            }
        } else {
            // NO CONTENT:
            // Hide visually, but DO NOT change data-status or fire events
            this._updateNavigationBox($box, false);
        }

        // Always update Dock layout if it's a dock style
        if ($box.attr('data-style') === 'dock') {
            this._checkDockInnerSiblings($box.parent());
        }
    }
}

class cactiButton extends cactiNavigation {
    constructor() {
        super();
    }

    add(options = {}) {

        const config = {
            title       : '',
            helper      : '',
            tooltip     : '',
            iconClass   : '',
            destination : '',
            onclick     : 'auto',
            param       : 'on',
            enabled     : true,
        };

        // validate and merge
        this._validateOptions(options, config);

        // early exit if button is disabled (e.g., permissions)
        if (config.enabled === false) return this;

        // handle potential object for toggling icons
        const icon = typeof config.iconClass === 'object' ? config.iconClass.off : config.iconClass;
        const tooltip = typeof config.tooltip === 'object' ? config.tooltip.off : config.tooltip;

        // use 'toggleConsoleNavigationBox' if set to 'auto'
        const clickFunc = config.onclick === 'auto' ? 'toggleConsoleNavigationBox' : config.onclick;

        // build HTML
        const containerHtml = `
            <div class="compact_nav_icon hint--info hint--right hint--rounded" 
                 data-subtitle="${config.title}" 
                 data-helper="${config.helper}"  
                 aria-label="${tooltip}" 
                 role="button" 
                 tabindex="0" 
                 aria-pressed="false">
                <i class="${icon}"></i>
            </div>`;

        const $destination = $(config.destination);
        if (!$destination.length) {
            console.warn(`Button: Destination '${config.destination}' not found for '${config.helper}'.`);
            return this;
        }

        // Avoid Duplicates and Append
        let $button = $destination.find(`[data-helper="${config.helper}"]`);

        if ($button.length === 0) {
            $button = $(containerHtml).appendTo($destination);

            // Bind Events
            if (this._isFunction(clickFunc)) {
                $button.on("click", { param: config.param }, (e) => {
                    // First priority: Method on this instance (like toggleConsoleNavigationBox)
                    if (typeof this[clickFunc] === 'function') {
                        this[clickFunc](e);
                    }
                    // Second priority: Global window function
                    else if (typeof window[clickFunc] === 'function') {
                        window[clickFunc](e);
                    }
                });

                $button.on("keydown", {
                    param: config.param,
                    function: clickFunc
                }, (e) => this._runKeyEvent(e));
            }
        }

        return this;
    }

    show(helper) {
        this._getButton(helper).removeClass('hide');
        return this;
    }

    hide(helper) {
        this._getButton(helper).addClass('hide');
        return this;
    }

    toggleConsoleNavigationBox(event) {
        const $caller = $(event.currentTarget);
        const helper = $caller.attr('data-helper');
        const param = event.data?.param; // 'on', 'force_open', 'force_close'
        const filter = event.data?.filter;

        /* hide any open context menus */
        $('.context-menu-list').trigger('contextmenu:hide');

        // map the param to our new toggleBox actions
        let action = 'toggle';
        if (param === 'force_open') action = 'force_open';
        if (param === 'force_close') action = 'force_close';
        if (param === 'on' && $caller.hasClass('selected')) action = 'force_close';
        if (param === 'on' && !$caller.hasClass('selected')) action = 'force_open';

        // execute central logic
        this.toggleBox(helper, action, filter);

        // re-initialize resizable if box just became visible
        const $box = this._getBox(helper);
        if ($box.hasClass('visible') && $box.attr('data-style') === 'overlay' && $.isFunction($.fn.resizable)) {
            $box.resizable({ handles: 'e' });
        }
    }
}

class cactiBox extends cactiNavigation {

    #searchTimer = null;

    constructor(options = {}) {
        super();

        // dynamic prefix detection
        this.prefix = this.getConfig('container').split('-')[0];

        // merge options over defaults
        const config = {
            className: 'ConsoleNavigationBox',
            ...options
        };

        // same classes for all boxes
        this.baseClass = `${this.prefix}${config.className}`;
        this.className = config.className;

        // unique storage key
        this.storageKey = `navigationBoxStates`;

        // cache local storage
        const saved = localStorage.getItem(this.storageKey);
        this.allSavedStates = saved ? JSON.parse(saved) : {};

        // listen to our parent for synchronization
        window.addEventListener('box:stateChanged', (e) => {
            const { helper, attributes } = e.detail;
            this.#saveBoxState(helper, attributes);
        });
    }

    add(options = {}) {
        let config = {
            title           : 'box_title',
            helper          : 'helper',
            buttons         : { close: true, search: '', menu: true },
            content         : '',
            contentLoader   : '',
            contextMenuItems: '',
            layout          : { style: 'overlay', dock: 'top-left', overlay: 'auto', align: 'left', height: 'full', exclusive: true },
            header          : '',
            control         : '',
            controlLoader   : '',
            status          : 'closed',
            initCallback    : '',
            pluginType      : '',
            isRefreshable   : false,
        };

        // validate and merge
        this._validateOptions(options, config);

        // persistence check
        const saveStates = this.allSavedStates[config.helper];

        if (saveStates) {
            // overwrite layout values with stored ones
            config.status           = saveStates.status     || config.status;
            config.layout.height    = saveStates.height     || config.layout.height;
            config.layout.width     = saveStates.width      || config.layout.width;
            config.layout.align     = saveStates.align      || config.layout.align;
            config.layout.style     = saveStates.style      || config.layout.style;
            config.layout.dock      = saveStates.dock       || config.layout.dock;
            config.layout.overlay   = saveStates.overlay    || config.layout.overlay;
            config.layout.exclusive = saveStates.exclusive  || config.layout.exclusive;

            // check if we have a style specif width value stored
            const styleSpecificWidth = saveStates[`width_${config.layout.style}`];

            if (styleSpecificWidth) {
                config.width = styleSpecificWidth;
            } else {
                config.width = saveStates.width || config.width;
            }
        }

        if (!config.header) config.header = config.title;

        const { buttons, helper, layout, title, header, width, content, contentLoader, control, controlLoader } = config;

        // content & control loading via registry
        const finalContent = content || (contentLoader ? this._runFunction(contentLoader) : '');
        const finalControl = control || (controlLoader ? this._runFunction(controlLoader) : '');

        // generate UI components
        const btnSearch     = buttons.search    ? `<div class="navBox-header-button ti-icon-search" data-action="search" data-helper="${helper}" role="button" tabindex="0"></div>` : '';
        const inputSearch   = buttons.search    ? `<div class="navBox-header-search"><input type="search" name="navBox-header-search" placeholder="Search in ${title}" data-helper="${helper}" tabindex="0"></div>` : '';
        const btnMenu       = buttons.menu      ? `<div class="navBox-header-button context-menu ti-icon-dots-vertical" data-action="menu" data-helper="${helper}" role="button" tabindex="0"></div>` : '';
        const btnClose      = buttons.close     ? `<div class="navBox-header-button ti-icon-minus" data-action="close" data-helper="${helper}" role="button" tabindex="0"></div>` : '';
        const rightButtons  = (btnMenu + btnClose) || '<div class="navBox-header-dropdown invisible"></div>';

        // assemble container
        const containerHtml = `
            <div class="${this.prefix}-${this.className}" data-title="${title}" data-helper="${helper}" 
                 data-plugin="${config.pluginType}"
                 data-refresh="${config.isRefreshable}" 
                 data-status="${config.status}" 
                 data-height="${layout.height}" data-width="${width}" data-align="${layout.align}"
                 data-style="${layout.style}" data-dock="${layout.dock}" data-overlay="${layout.overlay}"
                 data-exclusive="${layout.exclusive}">
                <div class="navBox-header">
                    ${btnSearch}
                    ${inputSearch}
                    <div class="navBox-header-filler"></div>
                    <div class="navBox-header-title"><span>${header}</span></div>
                    ${rightButtons}
                </div>
                <div class="navBox-control">${finalControl}</div>
                <div class="navBox-content">${finalContent}</div>
            </div>`;

        return this.#build(config, containerHtml);
    }

    #build(config, containerHtml) {
        const destinationId = this._readConfigOption('container');
        const $box = $(containerHtml).appendTo(`#${destinationId}`);

        // register close button
        if (config.buttons.close) {
            $box.find('[data-action="close"]').on('click', () => {
                this.toggleBox(config.helper, 'force_close');
            });
        }

        // register menu button
        if (config.buttons.menu) {

            $.contextMenu({
                // ensure memory isolation
                selector: `.${this.prefix}-${this.className}[data-helper="${config.helper}"] .context-menu`,
                trigger: 'left',
                autoHide: false,
                items: this.#prepareContextMenuItems(config)
            });
        }

        // register search button
        if (config.buttons.search) {
            const $input = $box.find('input[name="navBox-header-search"]');
            const $searchBtn = $box.find('[data-action="search"]');

            $searchBtn.on('click', function(e) {
                const isPressed = $(this).attr('aria-pressed') === 'true';
                const $header = $(this).closest('.navBox-header');

                $(this).attr('aria-pressed', !isPressed);
                $header.toggleClass('search-active', !isPressed);

                if (!isPressed) {
                    setTimeout(() => $input.focus(), 100);
                } else {
                    $input.val('').trigger('input').blur();
                }
                e.preventDefault();
            });

            const searchPath = config.buttons.search;

            // debounce logic to keep CPU utilization low
            const runDebouncedSearch = this.#debounce((query) => {
                this._runFunction(searchPath, {
                    query: query,
                    helper: config.helper,
                    $box: $box
                });
            }, 250);

            $input.on("input", (e) => {
                const query = $(e.currentTarget).val();
                // Trigger the debounced function instead of running it immediately
                runDebouncedSearch(query);
            });
        }

        // save box states if states are not in cache ( and not in local storage, too)
        if(!this.allSavedStates[config.helper]) {
            this.#saveBoxState(config.helper);
        }

        // execute initCallback if defined (e.g., to start first navigationBox plugin 'tree')
        if (config.initCallback) {
            this._runFunction(config.initCallback, $box);
        }

        // return the jQuery object
        return $box;
    }

    #prepareContextMenuItems(config) {
        // define basic menu structure
        let menuItems = {
            "search": {
                name: "Search",
                icon: "ti ti-search",
                callback: (key, opt) => this.#setFocusSearch(opt)
            }
        };

        // recursive helper to process nested items
        const processItems = (items) => {
            let processed = {};

            for (let [key, item] of Object.entries(items)) {
                // handle separators
                if (typeof item === 'string') {
                    processed[key] = item;
                    continue;
                }

                // create copy to avoid reference issues
                let newItem = { ...item };
                const safeKey = `plugin_${key}`;

                // process sub-items if they exist
                if (newItem.items && typeof newItem.items === 'object') {
                    newItem.items = processItems(newItem.items);
                }

                // bind string-based callbacks to our safe runner
                if (typeof newItem.callback === 'string') {
                    const funcPath = newItem.callback;
                    newItem.visible = () => this._isFunction(funcPath);
                    newItem.callback = (key, opt) => this._runFunction(funcPath, [key, opt]);
                }

                processed[safeKey] = newItem;
            }
            return processed;
        };

        // process and merge menu items added by plugins
        if (config.contextMenuItems && Object.keys(config.contextMenuItems).length > 0) {
            menuItems["sep_plugin"] = "---------";
            const pluginItems = processItems(config.contextMenuItems);
            menuItems = { ...menuItems, ...pluginItems };
        }

        // add remaining default options
        menuItems["sep_style"] = "---------";
        menuItems["style"] = {
            name: "Display Style",
                items: {
                "dock": {
                    name: "Dock",
                        icon: "ti ti-layout-sidebar",
                        disabled: (key, opt) => this.#isDisabled('style', key, opt),
                        callback: (key, opt) => this.#setDisplayStyle(key, opt),
                        visible: (key, opt) => this._readConfigOption(key, 'enabled')
                },
                "overlay": {
                    name: "Sidebar",
                        icon: "ti ti-layout-sidebar-filled",
                        disabled: (key, opt) => this.#isDisabled('style', key, opt),
                        callback: (key, opt) => this.#setDisplayStyle(key, opt),
                        visible: (key, opt) => this._readConfigOption(key, 'enabled')
                },
                "window": {
                    name: "Window",
                        icon: "ti ti-box-model-2",
                        disabled: (key, opt) => this.#isDisabled('style', key, opt),
                        callback: (key, opt) => this.#setDisplayStyle(key, opt),
                        visible: (key, opt) => this._readConfigOption(key, 'enabled')
                }
            }
        }

        menuItems["sep_dock"] = "---------";
        menuItems["dock"] = {
                name: "Dock",
                items: {
                    "left-top": {       name: "Top Left",
                        icon: "ti ti-box-align-top-left",
                        disabled: (key, opt) => this.#isDisabled('dock',key, opt),
                        callback: (key, opt) => this.#setDockPosition('left-top', opt),
                        visible: (key, opt) => this._readConfigOption('dock', 'top')
                    },
                    "right-top": {      name: "Top Right",
                        icon: "ti ti-box-align-top-right",
                        disabled: (key, opt) => this.#isDisabled('dock',key, opt),
                        callback: (key, opt) => this.#setDockPosition('right-top', opt),
                        visible: (key, opt) => this._readConfigOption('dock', 'top')
                    },
                    "top-left":  {      name: "Left Top",
                        icon: "ti ti-box-align-left",
                        disabled: (key, opt) => this.#isDisabled('dock', key, opt),
                        callback: (key, opt) => this.#setDockPosition('top-left', opt),
                        visible: (key, opt) => this._readConfigOption('dock', 'left')
                    },
                    "bottom-left":  {   name: "Left Bottom",
                        icon: "ti ti-box-align-left",
                        disabled: (key, opt) => this.#isDisabled('dock',key, opt),
                        callback: (key, opt) => this.#setDockPosition('bottom-left', opt),
                        visible: (key, opt) => this._readConfigOption('dock', 'left')
                    },
                    "top-right":    {   name: "Right Top",
                        icon: "ti ti-box-align-right",
                        disabled: (key, opt) => this.#isDisabled('dock',key, opt),
                        callback: (key, opt) => this.#setDockPosition('top-right', opt),
                        visible: (key, opt) => this._readConfigOption('dock', 'right')
                    },
                    "bottom-right": {   name: "Right Bottom ",
                        icon: "ti ti-box-align-right",
                        disabled: (key, opt) => this.#isDisabled('dock',key, opt),
                        callback: (key, opt) => this.#setDockPosition('bottom-right', opt),
                        visible: (key, opt) => this._readConfigOption('dock', 'right')
                    },
                    "left-bottom": {    name: "Bottom Left",
                        icon: "ti ti-box-align-bottom-left",
                        disabled: (key, opt) => this.#isDisabled('dock',key, opt),
                        callback: (key, opt) => this.#setDockPosition('left-bottom', opt),
                        visible: (key, opt) => this._readConfigOption('dock', 'bottom')
                    },
                    "right-bottom": {   name: "Bottom Right",
                        icon: "ti ti-box-align-bottom-right",
                        disabled: (key, opt) => this.#isDisabled('dock',key, opt),
                        callback: (key, opt) => this.#setDockPosition('right-bottom', opt),
                        visible: (key, opt) => this._readConfigOption('dock', 'bottom')
                    },
                },
                visible: (key, opt) => this._readConfigOption(key, 'enabled')
            };

        menuItems["overlay"] = {
                name: "Sidebar",
                disabled: (key, opt) => !this.#isDisabled('style',key, opt),
                items: {
                    "left": {           name: "Left",
                        icon: "ti ti-layout-sidebar-left-collapse",
                        disabled: (key, opt) => this.#isDisabled('align',key, opt),
                        callback: (key, opt) => this.#setDataAttribute('align', key, opt)
                    },
                    "right": {          name: "Right",
                        icon: "ti ti-layout-sidebar-right-collapse",
                        disabled: (key, opt) => this.#isDisabled('align',key, opt),
                        callback: (key, opt) => this.#setDataAttribute('align', key, opt)
                    },
                    "sep": "---------",
                    "manual": {         name: "Manual",
                        icon: "ti ti-arrow-bar-both",
                        disabled: (key, opt) => this.#isDisabled('overlay',key, opt),
                        callback: (key, opt) => this.#setOverlaySize(key, opt)
                    },
                    "sep1": "---------",
                    "auto": {           name: "Auto",
                        icon: "ti ti-arrow-autofit-width",
                        disabled: (key, opt) => this.#isDisabled('overlay',key, opt),
                        callback: (key, opt) => this.#setOverlaySize(key, opt)
                    },
                    "maximum": {
                        name: "Maximum",
                        icon: "ti ti-arrow-bar-to-right-dashed",
                        disabled: (key, opt) => this.#isDisabled('overlay',key, opt),
                        callback: (key, opt) => this.#setOverlaySize(key, opt)
                    },
                    "minimum": {
                        name: "Minimum",
                        icon: "ti ti-arrow-bar-to-left-dashed",
                        disabled: (key, opt) => this.#isDisabled('overlay',key, opt),
                        callback: (key, opt) => this.#setOverlaySize(key, opt)
                    },
                },
                visible: (key, opt) => this._readConfigOption(key, 'enabled')
            };
        menuItems["sep_footer"] = "---------";
        menuItems["hide"] = {
                name: "Close",
                icon: "ti ti-x",
                callback: function() { return true; }
        };

        return menuItems;
    }

    #setDisplayStyle(mode, opt) {
        let box = opt.$trigger.parents(`div.${this.prefix}-${this.className}`)[0];
        if (!box) return;

        box.dataset.style = mode;
        const $box = opt.$trigger.closest('[class*="ConsoleNavigationBox"]');
        //$box.attr("data-style", mode);
        mode === "dock" ? this.#moveToDock($box) : this.#moveToSideBar($box);

        // ensure persistence
        const helper = box.dataset.helper;
        this.#saveBoxState(helper);
    }

    #setDockPosition(mode, opt) {
        let box = opt.$trigger.parents(`div.${this.prefix}-${this.className}`)[0];
        if (!box) return;

        box.dataset.dock = mode;
        this.#setDisplayStyle("dock", opt);
    }

    #moveToDock($box, restoral = null) {
        const [inner, outer] = $box.attr("data-dock").split('-');
        const prefix = $box.attr('class').split('-')[0];
        const destinationSelector = `#${prefix}-Dock${this._ucFirst(outer)} > .${prefix}-DockInner${this._ucFirst(inner)}`;

        const $destination = $(destinationSelector);
        const $oldParent = $box.parent();

        $box.detach().appendTo($destination);

        if (typeof restoral !== "boolean") {
            console.log('here')
            this._updateNavigationButton($box, true);
            this._hideSiblings($box);

            if ($oldParent.is(`[class*="DockInner"]`)) {
                this._checkDockInnerSiblings($oldParent);
            }
        }
        this._checkDockInnerSiblings($destination);
    }

    #moveToSideBar($box) {
        const $oldParent = $box.parent();
        const containerId = this.getConfig('container');

        $box.detach().appendTo(`#${containerId}`);

        if ($oldParent.is(`[class*="DockInner"]`)) this._checkDockInnerSiblings($oldParent);
        this._hideSiblings($box);
    }

    #setFocusSearch(opt) {
        let box = opt.$trigger.parents(`div.${this.prefix}-${this.className}`)[0];
        if (!box) return;
        const searchBtn = box.querySelector('[data-action="search"]');
        if (searchBtn) {
            searchBtn.click();
        }
    }

    #setDataAttribute(attr, value, opt) {
        let box = opt.$trigger.parents(`div.${this.prefix}-${this.className}`)[0];
        if (!box) return;

        box.dataset[attr] = value;

        // ensure persistence
        const helper = box.dataset.helper;
        this.#saveBoxState( helper,[attr] );
    }

    #isDisabled(attr, value, opt) {
        const box = opt.$trigger.closest(`div.${this.prefix}-${this.className}`)[0];
        if (!box) return;
        return value === box.dataset[attr];
    }

    #setOverlaySize(mode, opt) {
        const box = opt.$trigger.closest(`div.${this.prefix}-${this.className}`)[0];
        if (!box) return;

        box.dataset.overlay = mode;

        switch (mode) {
            case "manual":
                box.dataset.width = '0';
                break;
            case "auto":
                box.dataset.width = 'auto'
                box.style.width = 'auto';
                break;
            case "maximum":
                box.dataset.width = '0';
                box.style.width = 'auto';
                break;
            case "minimum":
                box.dataset.width = '0';
                box.style.width = 'min-content';
                break;
            default:
        }

        // ensure persistence
        const helper = box.dataset.helper;
        this.#saveBoxState(helper, ['style', 'overlay', 'width']);
    }

    #saveBoxState(helper, attributes = null) {
        const boxSelector = `[class*="NavigationBox"][data-helper="${helper}"]`;
        const box = document.querySelector(boxSelector);
        if (!box) return;

        let currentData = { ...(this.allSavedStates[helper] || {}) };

        if (attributes && Array.isArray(attributes)) {
            // update only dedicated attributes
            attributes.forEach(attr => {
                if (box.dataset[attr] !== undefined) {
                    currentData[attr] = box.dataset[attr];
                }
            });

        } else {
            // update all attributes (except title)
            const newData = { ...box.dataset };
            delete newData.title;
            // merge new over old data
            currentData = { ...currentData, ...newData };
        }

        // cache style specific width
        const currentStyle = box.dataset.style;
        const currentWidth = box.dataset.width;
        if (currentStyle && currentWidth) {
            currentData[`width_${currentStyle}`] = currentWidth;
        }

        // updated internal cache
        this.allSavedStates[helper] = currentData;

        // finally update local storage
        localStorage.setItem(this.storageKey, JSON.stringify(this.allSavedStates));
    }

    #debounce(callback, delay = 250) {
        return (...args) => {
            clearTimeout(this.#searchTimer);
            this.#searchTimer = setTimeout(() => {
                callback.apply(this, args);
            }, delay);
        };
    }

    searchToHighlight(data) {
        // critical check: ensure external library mark.js is loaded
        if (!$.isFunction($.fn.markRegExp)) {
            console.error("NavigationBox Error: 'mark.js' is not loaded! The search function requires this library.");
            return;
        }

        const query = typeof data === 'object' ? data.query : data;
        const $container = data.$box ? data.$box.find('.navBox-content') : this._getBox(data.helper).find('.navBox-content');

        if (!$container.length) return;

        // prepare search pattern
        const pattern = '.*' + query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '.*';
        const re = new RegExp(pattern, 'gmiu');

        // execute mark.js logic
        $("li.menuitem", $container).removeClass('hide');
        $("a[role='menuitem'], li.menuitem", $container).unmark({
            done: () => {
                if (query) {
                    $("a[role='menuitem'], li.menuitem", $container).markRegExp(re, {
                        "accuracy": "complementary",
                        "separateWordSearch": false,
                        "done": () => {
                            $("li.menuitem", $container).not(":has(mark)").addClass('hide');
                        }
                    });
                }
            }
        });
    }

    restore(helper) {
        // fetch box-specific state from 'navigationBoxStates'
        const savedBox = this.allSavedStates[helper];

        // exit if no state exists
        if (!savedBox) return;

        // determine the current layout style or fallback to overlay
        const currentStyle = savedBox.style || 'overlay';

        // check if the layout style is globally enabled in the navigation config
        const isStyleEnabled = this.getConfig(`${currentStyle}.enabled`);

        if (!isStyleEnabled) {
            console.warn(`Navigation: cannot restore ${helper} as ${currentStyle} mode is globally disabled.`);
            return;
        }

        const $box = this._getBox(helper);
        if (!$box.length) return;

        // apply saved width dimensions for the specific style
        const savedWidth = savedBox[`width_${currentStyle}`] || savedBox.width;
        if (savedWidth && savedWidth !== '0' && savedWidth !== 'auto' && savedWidth !== 'undefined') {
            $box.css('width', savedWidth);
        }

        // prevent dynamic boxes ( overlay / window ) from opening automatically during startup
        if (currentStyle !== 'dock') return;

        // verify if the specific dock position is allowed by the global configuration
        if (currentStyle === 'dock') {
            const [inner, outer] = (savedBox.dock || '').split('-');
            const isDockPosEnabled = this.getConfig(`dock.${outer}`);

            if (!isDockPosEnabled) {
                console.warn(`Navigation: dock position '${outer}' is globally disabled. skipping restore for ${helper}.`);
                return;
            }
        }

        // move the box to its designated dock using restoral mode to keep siblings visible
        if (currentStyle === 'dock') {
            this.#moveToDock($box, true);
        }

        // synchronize the visual state of the box and its corresponding button
        if (savedBox.status === 'open') {
            this._updateNavigationBox($box, true);
            this._updateNavigationButton(helper, true);
        }
    }
}
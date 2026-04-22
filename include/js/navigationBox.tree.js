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

// ensure namespace exists
var midwinter = midwinter || {};
midwinter.navigationBox = midwinter.navigationBox || {};

midwinter.navigationBox.tree = {
    nodes: [],      // storage for nodes to be opened

    /**
     * returns the configuration for the tree box
     * @param {object} overrides - optional configuration overwrites
     */
    getDefaultConfig: function(overrides = {}) {
        // derive the type from the object path
        const type = 'tree';
        const base = 'midwinter.navigationBox';

        const defaults = {
            title: 'Tree',
            helper: 'tree',
            buttons: { search: `${base}.tree.search` },
            contentLoader: `${base}.tree.content`,
            controlLoader: `${base}.tree.control`,
            initCallback: `${base}.tree.init`,
            contextMenuItems: {
                'tree' : {
                    name: "Tree",
                    items: {
                        "reload": {
                            name: "Reload",
                            icon: "ti ti-refresh",
                            callback: `${base}.tree.reload`
                        },
                        "sep_all": "---------",
                        "expand_all": {
                            name: "Expand All",
                            icon: "ti ti-arrows-maximize",
                            callback: `${base}.tree.expandAll`
                        },
                        "collapse_all": {
                            name: "Collapse All",
                            icon: "ti ti-arrows-minimize",
                            callback: `${base}.tree.collapseAll`
                        },
                        "sep_sel": "---------",
                        "expand_sel": {
                            name: "Expand Selected",
                            icon: "ti ti-arrow-maximize",
                            callback: `${base}.tree.expandSelected`
                        },
                        "collapse_sel": {
                            name: "Collapse Selected",
                            icon: "ti ti-arrow-minimize",
                            callback: `${base}.tree.collapseSelected`
                        },
                    },
                },
            },
        };
        return $.extend(true, {}, defaults, overrides);
    },

    /**
     * returns the main navigation button configuration
     * @param {object} overrides - optional button overwrites
     * @returns {object|null} button definition or null
     */
    getNavigationButton: function(overrides = {}) {
        const defaults = {
            id: 'nav-btn-tree',
            icon: 'ti ti-layout-tree',
            label: 'tree navigation',
            action: 'toggle_box',
            target: 'tree' // matches the helper name
        };

        // return merged button config or null if this plugin shouldn't have a button
        return $.extend(true, {}, defaults, overrides);
    },

    /**
     * returns the html container for the tree
     * @param {object} $box - the box jquery object
     * @returns {string} html string
     */
    content: function($box) {
        // use a class instead of an ID to support multiple instances
        return '<div class="mdw-jstree-wrapper"></div>';
    },

    /**
     * returns the html for the control area
     * @param {object} args - can be the $box or an array [key, opt] / [$box]
     * @returns {string} html structure
     */
    control: function(args) {
        // handle both direct $box call and apply-style array calls
        const $box = Array.isArray(args) ? args[0] : args;

        // safety check: if $box is not yet available, use a fallback helper
        const helper = ($box && typeof $box.data === 'function') ? $box.data('helper') : 'tree';

        return `
        <div class="navBox-control-group">
            <button class="navBox-control-btn compact_nav_icon hint--bottom hint--rounded" 
                    data-action="expand_all" data-helper="${helper}" 
                    aria-label="expand all">
                <i class="ti ti-arrows-maximize"></i>
            </button>
            <button class="navBox-control-btn compact_nav_icon hint--bottom hint--rounded" 
                    data-action="collapse_all" data-helper="${helper}" 
                    aria-label="collapse all">
                <i class="ti ti-arrows-minimize"></i>
            </button>
            <button class="navBox-control-btn compact_nav_icon hint--bottom hint--rounded" 
                    data-action="reload" data-helper="${helper}" 
                    aria-label="reload navigation">
                <i class="ti ti-refresh"></i>
            </button>
        </div>`;
    },

    /**
     * check if user was logged out (cacti specific)
     * @param {object} $tree - the jstree jquery object
     */
    checkLogout: function($tree) {
        const html = $tree.html();
        if (html.indexOf('Login to Cacti') >= 0) {
            document.location = 'logout.php';
        }
    },

    /**
     * initializes jstree with optimized settings and event handling
     * @param {object} $box - the box jquery object
     */
    init: function($box) {
        const $treeContainer = $box.find('.mdw-jstree-wrapper');
        if (!$treeContainer.length) return;

        const ns = midwinter.navigationBox.tree;

        $treeContainer
            .on('init.jstree', () => {
                // check nodes via namespace reference
                if (ns.nodes && ns.nodes.length > 0) {
                    $treeContainer.jstree().clear_state();
                }
            })
            .on('before_open.jstree', () => {
                // check logout via namespace reference
                ns.checkLogout($treeContainer);
            })
            .on('after_open.jstree after_close.jstree', () => {
                // global cacti helper for graph resizing
                if (typeof responsiveResizeGraphs === 'function') responsiveResizeGraphs();
            })
            .on('select_node.jstree', (e, data) => {
                if (data.node && data.node.id) {
                    let $nodeEl = $('#' + data.node.id);
                    let href = $nodeEl.find('a:first').attr('href');

                    if (href) {
                        href = href.replace('action=tree', 'action=tree_content') + '&hyper=true';
                        $('.cactiGraphContentArea').hide();
                        // use our global loadUrl helper
                        if (typeof loadUrl === 'function') loadUrl({url: href});
                    }
                }
            })
            .jstree({
                'core': {
                    'data': {
                        'url': window.urlPath + 'graph_view.php?action=get_node&tree_id=0',
                        'data': (node) => ({ 'id': node.id })
                    },
                    'animation': 0,
                    'check_callback': false,
                    'themes': { 'name': 'default', 'responsive': true, 'dots': false }
                },
                'types': {
                    'default': { icon: 'ti ti-folder' },
                    'tree': { icon: 'ti ti-star' },
                    'device': { icon: 'ti ti-device-analytics' },
                    'graph_template': { icon: 'ti ti-photo' },
                    'site': { icon: 'ti ti-building' },
                    'location': { icon: 'ti ti-building' },
                    'host_template': { icon: 'ti ti-devices-2' },
                    'graph_templates': { icon: 'ti ti-photo-star' }
                },
                'state': {
                    'key': 'graph_tree_history_' + $box.data('helper'), // unique key per box instance
                    'filter': (x) => { delete x.core.selected; return x; }
                },
                'search': {
                    'case_sensitive': false,
                    'show_only_matches': true,
                    'ajax': { 'url': window.urlPath + 'graph_view.php?action=ajax_search' }
                },
                'plugins': ['types', 'state', 'wholerow', 'search']
            });

        // bind control buttons
        $box.on('click', '.navBox-control-btn', (e) => {
            const $btn = $(e.currentTarget);
            const action = $btn.data('action');
            const ns = midwinter.navigationBox.tree;

            // map button action to jstree command
            let command;
            switch (action) {
                case 'expand_all':
                    command = 'open_all';
                    break;
                case 'collapse_all':
                    command = 'close_all';
                    break;
                case 'reload':
                    command = 'refresh';
                    break;
                default:
                    // log unknown actions to console
                    console.warn(`navigation.tree: unknown control action '${action}'`);
                    return;
            }

            // we simulate the 'opt' object so our helper works
            ns._executeTreeCommand({ $trigger: $btn }, command);
        });

    },

    /**
     * internal helper to execute jstree commands with robust box detection
     * @param {object} opt - context menu options or data object from search
     * @param {string} command - jstree method name
     * @param {mixed} args - optional arguments for the jstree command
     */
    _executeTreeCommand: function(opt, command, args = null) {
        // identify the helper name (support both context menu and search-header-data)
        const helper = opt.$trigger ? opt.$trigger.attr('data-helper') : (opt.helper || 'tree');

        // robust selector: prefix-independent and class-partial match
        const $box = $(`div[class*="ConsoleNavigationBox"][data-helper="${helper}"]`);

        if (!$box.length) {
            console.error(`tree.${command}: box for helper '${helper}' not found in dom`);
            return;
        }

        const $tree = $box.find('.mdw-jstree-wrapper');

        if ($tree.length && $.isFunction($tree.jstree)) {

            if (command.includes('Selected')) {
                const actualCommand = command.startsWith('expand') ? 'open_all' : 'close_all';
                const selectedNode = $tree.jstree('get_selected');

                if (selectedNode.length > 0) {
                    return $tree.jstree(actualCommand, selectedNode[0]);
                } else {
                    console.warn(`tree.${command}: no node selected`);
                    return;
                }
            }

            // execute command with or without additional arguments
            return args !== null ? $tree.jstree(command, args) : $tree.jstree(command);
        } else {
            console.warn(`tree.${command}: jstree not ready in box '${helper}'`);
        }
    },

    /**
     * expands all nodes
     */
    expandAll: function(key, opt) {
        // use internal helper for expansion
        midwinter.navigationBox.tree._executeTreeCommand(opt, 'open_all');
    },

    /**
     * collapses all nodes
     */
    collapseAll: function(key, opt) {
        // use internal helper for collapsing
        midwinter.navigationBox.tree._executeTreeCommand(opt, 'close_all');
    },

    /**
     * expand selected node
     */
    expandSelected: function(key, opt) {
        midwinter.navigationBox.tree._executeTreeCommand(opt, 'expandSelected');
    },

    /**
     * collapses selected node
     */
    collapseSelected: function(key, opt) {
        midwinter.navigationBox.tree._executeTreeCommand(opt, 'collapseSelected');
    },

    /**
     * search interface for the box header
     */
    search: function(data) {
        const query = typeof data === 'object' ? data.query : data;
        // use the robust helper to trigger jstree search
        midwinter.navigationBox.tree._executeTreeCommand(data, query ? 'search' : 'clear_search', query);
    },

    /**
     * reloads the jstree using the central command executor
     */
    reload: function(key, opt) {
        // ensure a default helper is present if called without context
        const options = opt || { helper: 'tree' };

        // execute the jstree refresh command
        midwinter.navigationBox.tree._executeTreeCommand(options, 'refresh');
    },
};

// register this module automatically
midwinter.navigationBox.registerPlugin('midwinter.navigationBox.tree');
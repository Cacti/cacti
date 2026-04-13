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

midwinter.navigationBox.table = {

    // Internal constants for decoupling
    _selectors: {
        table:      '.cactiTable',
        header:     'tr.tableHeader',
        sortInfo:   'div.sortinfo'
    },

    /**
     * cyrb53 (c) 2018 bryc (github.com/bryc)
     * License: Public domain. Attribution appreciated.
     * A fast and simple 53-bit string hash function with decent collision resistance.
     * Largely inspired by MurmurHash2/3, but with a focus on speed/simplicity.
     */
    _hash: function(str, seed = 0) {
        let h1 = 0xdeadbeef ^ seed, h2 = 0x41c6ce57 ^ seed;
        for(let i = 0, ch; i < str.length; i++) {
            ch = str.charCodeAt(i);
            h1 = Math.imul(h1 ^ ch, 2654435761);
            h2 = Math.imul(h2 ^ ch, 1597334677);
        }
        h1  = Math.imul(h1 ^ (h1 >>> 16), 2246822507);
        h1 ^= Math.imul(h2 ^ (h2 >>> 13), 3266489909);
        h2  = Math.imul(h2 ^ (h2 >>> 16), 2246822507);
        h2 ^= Math.imul(h1 ^ (h1 >>> 13), 3266489909);
        return 4294967296 * (2097151 & h2) + (h1 >>> 0);
    },

    // internal helper to notify the Cacti theme (e.g. that content is empty)
    _notifyState: function($box, hasContent) {
        const event = new CustomEvent('mdw:pluginStateUpdate', {
            detail: {
                helper: $box.data('helper'),
                plugin: 'table',
                hasContent: hasContent
            },
            bubbles: true // ensure we are reaching the document level
        });
        $box[0].dispatchEvent(event);
    },

    _executeReset: function(tableHash) {
        if (!tableHash) return;

        const storage = Storages.localStorage;
        let storageData = storage.get('cactiTable_' + tableHash);
        let settings = (typeof storageData === 'string') ? JSON.parse(storageData) : storageData;

        if (settings && settings[1]) {
            // remove all hide-classes from the actual table
            const $table = $(`table[data-table="${tableHash}"]`);
            $table.removeClass(settings[0]);

            // reset the hidden-classes array
            settings[0] = [];

            // set all columns to visible (1) in the metadata
            settings[1].forEach(col => {
                col[4] = 1;
            });

            // sync back to storage
            storage.set('cactiTable_' + tableHash, JSON.stringify(settings));

            // update the UI Checkboxes in the Box
            const $box = $(`[data-helper="displayOptions"]`);
            $box.find('input[type="checkbox"]').prop('checked', true);
            $box.find('#mdw-columns-reset').addClass('inactive');

            console.log(`[TablePlugin] Reset executed for table ${tableHash}`);
        }
    },


    /**
     * Internal toggle logic
     */
    _executeToggle: function(tableHash, columnIndex, isChecked) {
        const columnClass = `no-col${columnIndex}`;
        const storage = Storages.localStorage;

        let storageData = storage.get('cactiTable_' + tableHash);
        let settings = (typeof storageData === 'string') ? JSON.parse(storageData) : storageData;

        if (!settings) return;

        const $table = $(`table[data-table="${tableHash}"]`);

        // update visibility flag in metadata [index, name, title, hideable, visible]
        settings[1][columnIndex - 1][4] = Number(isChecked);

        if (!isChecked) {
            // hide column: add class to table and storage list
            if (!settings[0].includes(columnClass)) settings[0].push(columnClass);
            $table.addClass(columnClass);
        } else {
            // show column: remove class
            const index = settings[0].indexOf(columnClass);
            if (index !== -1) settings[0].splice(index, 1);
            $table.removeClass(columnClass);
        }

        storage.set('cactiTable_' + tableHash, JSON.stringify(settings));

        // update reset button state within the box
        $(`[data-helper="displayOptions"] #mdw-columns-reset`).toggleClass('inactive', settings[0].length === 0);
    },

    /**
     * returns the configuration for the table layout box
     * @param {object} overrides - optional configuration overwrites
     */
    getDefaultConfig: function(overrides = {}) {
        const base = 'midwinter.navigationBox.table';
        const defaults = {
            title: 'Table Layout',
            helper: 'displayOptions',
            contentLoader: `${base}.content`,
            controlLoader: `${base}.control`,
            initCallback: `${base}.init`,
            contextMenuItems: {
                'table' : {
                    name: "Table Options",
                    items: {
                        "reset": {
                            name: "Reset Columns",
                            icon: "ti ti-rotate",
                            callback: `${base}.reset`
                        }
                    }
                }
            },
            isRefreshable: true,
        };
        return $.extend(true, {}, defaults, overrides);
    },

    /**
     * returns the html for the control area (Reset Button)
     */
    control: function(args) {
        const $box = Array.isArray(args) ? args[0] : args;
        const helper = ($box && typeof $box.data === 'function') ? $box.data('helper') : 'displayOptions';

        return `
        <div class="navBox-control-group">
            <button class="navBox-control-btn compact_nav_icon hint--bottom hint--rounded" 
                    data-action="reset_table" data-helper="${helper}" 
                    aria-label="reset columns">
                <i class="ti ti-rotate"></i>
            </button>
        </div>`;
    },

    /**
     * generates the column checkbox list
     */
    content: function($box) {
        const ns = midwinter.navigationBox.table;
        const $headerRow = $(`${ns._selectors.header}:has(th:nth-of-type(2))`).first();

        if (!$headerRow.length) return '<div class="navBox-empty">No compatible table found</div>';

        const $currentTable = $headerRow.closest(ns._selectors.table);
        const tableID       = $currentTable.attr('id') || 'no-id';

        // build header string for hashing
        let cHeaderStr = '';
        $headerRow.find('th').each(function() {
            const $th = $(this);
            const cName = $th.hasClass('sortable') ? $th.find(ns._selectors.sortInfo).attr('sort-column') : 'n/a';
            cHeaderStr += cName;
        });

        const tableHash = this._hash(window.location.pathname + tableID + cHeaderStr);
        $currentTable.attr('data-table', tableHash);

        // storage handling
        const storageKey = 'cactiTable_' + tableHash; // Updated prefix
        let storageData = Storages.localStorage.get(storageKey);
        let settings = (typeof storageData === 'string') ? JSON.parse(storageData) : storageData;

        // init storage if missing
        if (!settings || !settings[1]) {
            let cArray = [];
            $headerRow.find('th').each(function(index) {
                const $th = $(this);
                let cIndex = index + 1;
                let cName = 'n/a', cTitle = 'n/a', cHideable = 0;

                if ($th.hasClass('sortable')) {
                    cName = $th.find(ns._selectors.sortInfo).attr('sort-column');
                    cTitle = $th.find('i:first').parent().text().trim();
                    cHideable = 1;
                } else if (!$th.hasClass('tableSubHeaderCheckbox')) {
                    cTitle = $th.text().trim();
                    cHideable = 1;
                }
                cArray.push([cIndex, cName, cTitle, cHideable, 1]);
            });

            settings = [[], cArray, (typeof sessionLocale !== 'undefined' ? sessionLocale : 'en-US')];
            Storages.localStorage.set(storageKey, JSON.stringify(settings));
        }

        // 3. Render HTML
        let html = '';
        const columns = settings[1]; // Correct index for column definitions

        columns.forEach((col) => {
            const [index, name, title, hideable, visible] = col;
            if (hideable) {
                const inputID = `cacti_col_${index}`;
                html += `
                    <div class="navBox-item-row">
                        <span>${title}</span>
                        <div class="navBox-item-action">
                            <input type="checkbox" id="${inputID}" class="plugin-table-toggle"
                                   data-table="${tableHash}" data-column="${index}" 
                                   ${visible ? 'checked' : ''} 
                                   ${index === 1 ? 'disabled' : ''}>
                            <label for="${inputID}"></label>
                        </div>
                    </div>`;
            }
        });

        return html || '<div class="navBox-empty">No hideable columns</div>';
    },

    /**
     * initializes events and visibility
     */
    init: function($box) {
        if (!$box || !$box.length) return;
        const helper = $box.data('helper');
        const ns = midwinter.navigationBox.table;

        // bind toggle checkboxes
        $box.off('change', '.plugin-table-toggle').on('change', '.plugin-table-toggle', (e) => {
            const $el = $(e.currentTarget);
            ns._executeToggle($el.data('table'), $el.data('column'), e.target.checked);
        });

        // bind reset control button
        $box.off('click', '[data-action="reset_table"]').on('click', '[data-action="reset_table"]', (e) => {
            const tableHash = $box.find('input[data-table]').first().data('table');
            ns._executeReset(tableHash);
        });

        // handle navigation button visibility via mdw.actions
        const hasContent= $box.find('input[type="checkbox"]').length > 0;
        ns._notifyState($box, hasContent);
    },

    reset: function(key, opt) {
        const ns = midwinter.navigationBox.table;
        const $box = opt.$trigger.closest('div[class*="ConsoleNavigationBox"]')
        const tableHash = $box.find('input[data-table]').first().attr('data-table');
        ns._executeReset(tableHash);
    },
};

// register plugin
midwinter.navigationBox.registerPlugin('midwinter.navigationBox.table');

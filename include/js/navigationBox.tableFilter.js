// ensure namespace exists
var midwinter = midwinter || {};
midwinter.navigationBox = midwinter.navigationBox || {};

midwinter.navigationBox.filter = {

    // Internal constants for decoupling
    _selectors: {
        cactiTable:      '.cactiTable',
        cactiFilterTable: '.filterTable',
        filterTopTarget:  '#filterTableOnTop'
    },

    // internal helper to notify the Cacti theme (e.g. that content is empty)
    _notifyState: function($box, hasContent) {
        const event = new CustomEvent('mdw:pluginStateUpdate', {
            detail: {
                helper: $box.data('helper'),
                plugin: 'filter',
                hasContent: hasContent
            },
            bubbles: true // ensure we are reaching the document level
        });
        $box[0].dispatchEvent(event);
    },

    /**
     * returns the configuration for the filter box
     */
    getDefaultConfig: function(overrides = {}) {
        const base = 'midwinter.navigationBox.filter';
        const defaults = {
            title: 'Filter',
            helper: 'displayFilterOptions',
            contentLoader: `${base}.content`,
            initCallback: `${base}.init`,
            contextMenuItems: {},
            isRefreshable: true,
        };
        return $.extend(true, {}, defaults, overrides);
    },

    /**
     * Extracts the Cacti filter table and returns it
     */
    content: function($box) {
        const ns = midwinter.navigationBox.filter;
        const sel = ns._selectors;
        const $main = $('#main');
        const version = $main.data('cactiContentVersion') || 0;
        const previous = $box.data('filterSource');
        const $filterSource = $(`#main ${sel.cactiFilterTable}`).first();

        if ($filterSource.length) {

            // Find the surrounding cacti table container and detach it
            const $filterContainer = $filterSource.closest(sel.cactiTable).detach();
            $box.data('filterSource', { element: $main[0], version: version });

            /* Legacy support for cacti breaks/titles */
            const $mainFirstDiv = $("#main > div:first");
            if ($mainFirstDiv.find(sel.cactiFilterTable).closest('div').length === 1) {
                const $topTarget = $(sel.filterTopTarget);

                $(".break:first").detach().appendTo($topTarget);
                $topTarget.find('.cactiTableTitle').remove();
                $topTarget.removeClass('hide');
            }

            return $filterContainer;
        }

        // Repeated setup on the same content must keep the relocated controls.
        // An AJAX replacement advances the version even when its URL is unchanged.
        if (previous && previous.element === $main[0] && previous.version === version) return null;
        $box.removeData('filterSource');
        return '';
    },

    /**
     * Handles events and visibility notification
     */
    init: function($box) {
        const ns = midwinter.navigationBox.filter;
        const $content = $box.find('.navBox-content');
        const hasContent = $content.find(ns._selectors.cactiFilterTable).length > 0;

        ns._notifyState($box, hasContent);
    }
};

// register plugin
midwinter.navigationBox.registerPlugin('midwinter.navigationBox.filter');

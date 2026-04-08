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

const base = 'midwinter.navigationBox'; 
const content = `${base}.content`;

const uiConfig = {
    boxes: [
        {
            title: window.cactiDashboards || 'Panels',
            helper: 'dashboards',
            buttons: { search: 'searchToHighlight' },
            contentLoader: `${content}.dashboards`
        },
        {
            title: window.zoom_i18n_settings || 'Settings',
            helper: 'settings',
            buttons: { search: 'searchToHighlight' },
            contentLoader: `${content}.settings`
        },
        {   // New Cacti NavigationBox Tree Plugin
            type: 'tree',
            overrides: { title: 'Tree' }
        },
        {
            title: window.help,
            helper: 'help',
            layout: { height: 'half' },
            header: window.justCacti + ' v' + window.cactiVersion,
            contentLoader: `${content}.help`
        },
        {
            title: window.cactiUser,
            helper: 'user',
            layout: { height: 'half' },
            header: $('.loggedInAs').text(),
            contentLoader: `${content}.user`
        },
        {
            title: 'Table Layout',
            helper: 'displayOptions',
            layout: { align: 'right' },
        },
        {
            title: 'Theme',
            helper: 'theme',
            layout: { align: 'right' },
            contentLoader: `${content}.theme`
        },
        {
            title: 'Filter',
            helper: 'displayFilterOptions',
            layout: { align: 'right' },
            header: 'Display Filter',
        },
    ],
    buttons: [
        {
            title: "Panels",
            helper: "dashboards",
            tooltip: "Panels",
            iconClass: "ti ti-map",
            destination: "#compact_tab_menu"
        },
        {
            title: "Setup",
            helper: "settings",
            tooltip: "Settings",
            iconClass: "ti ti-settings-cog",
            destination: "#compact_tab_menu",
            enabled: window.cactiConsoleAllowed || false
        },
        {
            title: "Tree",
            helper: "tree",
            tooltip: "Tree View",
            iconClass: "ti ti-seedling",
            destination: "#compact_tab_menu",
            enabled: window.cactiGraphsAllowed || false
        },
        {
            title: "Help",
            helper: "help",
            tooltip: "Help",
            iconClass: "ti ti-messages",
            destination: "#compact_user_menu"
        },
        {
            title: "User",
            helper: "user",
            tooltip: "User Settings",
            iconClass: "ti ti-user",
            destination: "#compact_user_menu"
        },
        {
            title: "Exit",
            helper: "logout",
            tooltip: "Sign Out",
            iconClass: "ti ti-logout",
            destination: "#compact_user_menu",
            onclick: "redirect",
            param: window.urlPath + 'logout.php'
        },
        {
            title: "Color",
            helper: "toggleColorMode",
            tooltip: "Toggle light/dark Mode",
            iconClass: "ti ti-contrast-filled",
            destination: "#navControl",
            onclick: "toggleColorMode",
            param: "on"
        },
        {
            title: "Kiosk",
            helper: "kioskMode",
            tooltip: "Enable Kiosk Mode",
            iconClass: "ti ti-device-desktop",
            destination: "#navControl",
            onclick: "kioskMode",
            param: "on"
        },
        {
            title: "Fullscreen",
            helper: "fullscreen",
            tooltip: { "on" : "Exit Full screen", "off" : "Switch to Full screen" },
            iconClass: { "on" : "ti ti-minimize" , "off" : "ti ti-maximize" },
            destination: "#navControl",
            onclick: "fullScreen",
            enabled: document.fullscreenEnabled || false
        },
        {
            title: "Theme Settings",
            helper: "theme",
            tooltip: "Theme Settings",
            iconClass: "ti ti-color-swatch",
            destination: "#mdw-ActionBarBottom",
        },
        {
            title: "Filter",
            helper: "displayFilterOptions",
            tooltip: "Show Display Filter",
            iconClass: "ti ti-filter",
            destination: "#mdw-ActionBarTop",
        },
    ]
};
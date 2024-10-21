/*
  +-------------------------------------------------------------------------+
  | Copyright (C) 2004-2023 The Cacti Group                                 |
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

class navigationBox {
    
    #box;
    #container;
    #container_content = '';
    
    constructor(title, helper, height='full', width='auto', buttons= {close: false, search: false, resize: true}, align='left', header=title,
                content = 'auto',
                destination = '#mdw-SideBarContainer') {
        let storage = Storages.localStorage;

        this.#box = {
            'class':        'mdw-ConsoleNavigationBox',
            'title':        title,
            'helper':       helper,
            'height':       ((height === 'half') ? 'half' : 'full'),
            'width':        (storage.isSet('midWinter_widthNavigationBox_'+helper)? storage.get('midWinter_widthNavigationBox_'+helper) : width),
            'align':        ((align === 'right') ? 'right' : 'left'),
            'header':       header,
            'content':      content,
            'buttons':       {
                close:      (buttons.close !== false),
                search:     (buttons.search !== false) ? buttons.search : false,
                resize:     (buttons.resize !== false),
                dock:       (buttons.dock !== false) ? buttons.dock : false,
            },
            'destination':   destination
        };
        let navigationBoxButtonsRight   = '';
        let navigationBoxButtonsLeft    = '';
        let navigationBoxSearch         = '';
        let navigationBoxFiller         = '<div class="navBox-header-filler"></div>';
        let navigationBoxTitle          = '<div class="navBox-header-title"><span>'+this.#box.header+'</span></div>';

        if(this.#box.buttons.search) {
            navigationBoxButtonsLeft += '<div class="navBox-header-button fa-icon-search" data-action="search" data-helper="'+this.#box.helper+'" role="button" tabindex="0" aria-pressed="false"></div>';
            navigationBoxSearch += '<div class="navBox-header-search"><input type="search" name="navBox-header-search" data-scope="theme" data-helper="'+this.#box.helper+'" placeholder="Search in '+this.#box.title+'" tabindex="0"></div>';
        }
        if(this.#box.buttons.resize) {
            navigationBoxButtonsRight +=
                '<div class="navBox-header-button fa-icon-ellipsis-v" data-action="dropdown" data-helper="'+this.#box.helper+'" role="button" tabindex="0" aria-pressed="false">'
                +		'<div class="navBox-header-dropdown-content">'
                +			'<a class="setNavigationBoxColumns" data-scope="theme" data-func="setNavigationBoxColumns" data-helper="'+this.#box.helper+'" data-value="auto" href="#">Auto</a>'
                +			'<a class="setNavigationBoxColumns" data-scope="theme" data-func="setNavigationBoxColumns" data-helper="'+this.#box.helper+'" data-value="1" href="#">Columns 1</a>'
                +			'<a class="setNavigationBoxColumns" data-scope="theme" data-func="setNavigationBoxColumns" data-helper="'+this.#box.helper+'" data-value="2" href="#">Columns 2</a>'
                +			'<a class="setNavigationBoxColumns" data-scope="theme" data-func="setNavigationBoxColumns" data-helper="'+this.#box.helper+'" data-value="3" href="#">Columns 3</a>'
                +			'<a class="setNavigationBoxColumns" data-scope="theme" data-func="setNavigationBoxColumns" data-helper="'+this.#box.helper+'" data-value="4" href="#">Columns 4</a>'
                +			'<a class="setNavigationBoxColumns" data-scope="theme" data-func="setNavigationBoxColumns" data-helper="'+this.#box.helper+'" data-value="5" href="#">Columns 5</a>'
                +		'</div>'
                +	'</div>'
        }
        if(this.#box.buttons.close) {
            navigationBoxButtonsRight += '<div class="navBox-header-button fa-icon-minus" data-action="close" data-helper="'+this.#box.helper+'" role="button" tabindex="0" aria-pressed="false"></div>';
        }

        if(this.#box.buttons.dock) {
            navigationBoxButtonsRight += '<div class="navBox-header-button" data-action="dock" data-helper="'+this.#box.helper+'" role="button" tabindex="0" aria-pressed="false">' +
                                    '<svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-width="2"><rect width="20" height="18" x="2" y="3" stroke-linecap="round" stroke-linejoin="round" rx="2"/><path d="M9 3v18"/></g></svg>' +
                                    '</div>';
        }

        if(navigationBoxButtonsRight === '') {
            navigationBoxButtonsRight = '<div class="navBox-header-dropdown invisible"></div>';
        }

        if(this.#box.content !== 'auto') {
            this.#container_content = this.#box.content;
        }else {
            let fname = 'get_'+this.#box.helper+'_content';
            if(is_function(fname)) {
                this.#container_content = window[fname]();
            }
        }

        this.#container  = '<div class="'+this.#box.class+'" data-title="'+this.#box.title+'" data-helper="'+this.#box.helper+'" data-height="'+this.#box.height+'" data-width="'+this.#box.width+'" data-align="'+this.#box.align+'">';
        this.#container += '<div class="navBox-header">'
                            + navigationBoxButtonsLeft
                            + navigationBoxSearch
                            + navigationBoxFiller
                            + navigationBoxTitle
                            + navigationBoxButtonsRight
                         + '</div>';
        this.#container += '<div class="navBox-content">' + this.#container_content + '</div>';
    }

    build() {
        /* keep all navigation boxes inside a common DOM container */
        if($('#mdw-SideBarContainer').length === 0) {
            $('<div id="mdw-SideBarContainer" class="mdw-SideBarContainer"></div>').insertAfter('#mdw-GridContainer');
        }
        this.#container += '</div></div>';
        let navigationBox = $(this.#container).appendTo(this.#box.destination);

        /* register button events if required */
        if(this.#box.buttons.close) {
            $('[class^="navBox-header-button"][data-action="close"][data-helper="'+this.#box.helper+'"]', navigationBox).off().on(
                'click', {param: 'force_close'}, toggleCactiNavigationBox).on(
                'keydown', {param: 'force_close', function: 'toggleCactiNavigationBox'}, run_keyEvent
            )
        }

        if(this.#box.buttons.resize) {
            $('[class^="navBox-header-button"][data-action="dropdown"][data-helper="'+this.#box.helper+'"]', navigationBox).off().on(
                'click', {param: this.#box.helper}, toggleDropDownMenu).on(
                'keydown', {param:  this.#box.helper, function: 'toggleDropDownMenu'}, run_keyEvent
            )
        }

        if(this.#box.buttons.dock) {
            $('[class^="navBox-header-button"][data-action="dock"][data-helper="'+this.#box.helper+'"]', navigationBox).off().on(
                'click', {param: this.#box.helper, dock: 'right'}, toggleCactiNavigationBoxPin).on(
                'keydown', {param:  this.#box.helper, dock: 'right', function: 'toggleCactiNavigationBoxPin'}, run_keyEvent
            );
        }

        if(this.#box.buttons.search) {
            let navBox_input_field = $("input[name=navBox-header-search]", navigationBox);
            $('[class^="navBox-header-button"][data-action="search"][data-helper="'+this.#box.helper+'"]', navigationBox).off().on('click', function(e) {
                $(this).attr('aria-pressed', function(_, attr) { return !(attr === 'true') })
                if ($(this).attr('aria-pressed') === 'true') {
                    setTimeout( function() {
                        navBox_input_field.delay().trigger('focus');
                    }, 100 );
                }else {
                    navBox_input_field.val('').trigger('input').blur();
                }
                /* avoid that click event takes focus away */
                e.preventDefault();
            });
            if(is_function(this.#box.buttons.search)) {
                navBox_input_field.off().on("input", window[this.#box.buttons.search]);
            }
        }
    }
}

class navigationButton {
    #icon;
    #container;
    #button;

    constructor(helper, subtitle='', tooltip='', icon_class, destination, onclick='auto', param='on') {
        this.#icon = {
            'helper'      : helper,
            'title'       : subtitle,
            'tooltip'     : tooltip,
            'class'       : icon_class,
            'destination' : destination,
            'param'       : param
        }

        if(onclick === 'auto') {
            this.#icon.onclick = 'toggleCactiNavigationBox';
        }else {
            this.#icon.onclick = onclick;
        }

        this.#container = '<div class="compact_nav_icon hide" data-subtitle="'+this.#icon.title+'" data-helper="'+this.#icon.helper+'" title="'+this.#icon.tooltip+'" role="button" tabindex="0" aria-pressed="false"><i class="'+this.#icon.class+'"></i></div>';

        /* avoid duplicates */
        if( $(this.#icon.destination + ' > div[class^="compact_nav_icon"][data-helper="' + this.#icon.helper + '"]').length === 0 ) {
            $(this.#container).appendTo(this.#icon.destination);
            this.#button = $(this.#icon.destination + ' > div[class^="compact_nav_icon"][data-helper="' + this.#icon.helper + '"]');
            if (is_function(this.#icon.onclick)) {
                /* if an onclick event is supported then implement keydown events to act similar to a button element */
                this.#button.off().on("click", {param: this.#icon.param}, window[this.#icon.onclick]);
                this.#button.on("keydown", {param: this.#icon.param, function: this.#icon.onclick}, run_keyEvent);
            }
        }else {
            this.#button = $(this.#icon.destination + ' > div[class^="compact_nav_icon"][data-helper="' + this.#icon.helper + '"]');
        }
    }

    show() {
        this.#button.removeClass('hide');
        return this;
    }

    hide() {
        this.#button.addClass('hide');
        return this;
    }
}

function run_keyEvent(event) {
    if (!(event.keyCode === 13 || event.keyCode === 32)) {
        return;
    }
    event.preventDefault();
    window[event.data.function](event);
}

function is_function(fname) {
    return (typeof window[fname] === 'function');
}

function get_displayOptions_content() {
    let filters_content;
    filters_content =
        '<div class="displayOptions">'
        +   '<div class="displayOptionsTap">'
        +       '<label class="tab-label" for="tab-columns">Columns <i class="fas fa-chevron-down"></i></label>'
        +       '<input data-scope="theme" id="tab-columns" class="tab-input" type="checkbox" checked/>'
        +       '<div class="tab-columns tab-content"></div>'
        +   '</div>'
        +'</div>';

    return filters_content;
}



function get_dashboards_content(){
        let compact_tab_menu_content = '<ul class="nav">';

        if (cactiConsoleAllowed) {
            compact_tab_menu_content +=
                '<li class="menuitem" id="menu_home">'
                +    '<a class="menu_parent" href="#" inert>'
                +        '<i class="menu_glyph ignore fas fa-home"></i>'
                +        '<span>'+cactiHome+'</span>'
                +    '</a>'
                +    '<ul>'
                +        '<li><a href="'+urlPath+'index.php" class="pic" role="menuitem">'+cactiConsole+'</a></li>'
                +    '</ul>'
                +'</li>';
        }

        //#todo : string handling list, preview
        if (cactiGraphsAllowed) {
            compact_tab_menu_content +=
                '<li class="menuitem" id="menu_tab_dashboard">'
                +    '<a class="menu_parent" href="#" inert>'
                +        '<i class="menu_glyph ignore fas fa-chart-area"></i>'
                +        '<span>Views</span>'
                +    '</a>'
                +    '<ul>'
                +       '<li><a class="pic" role="menuitem" id="tab-graphs-list-view" href="' + urlPath + 'graph_view.php?action=list">List</a></li>'
                +       '<li><a class="pic" role="menuitem" id="tab-graphs-pre-view" href="' + urlPath + 'graph_view.php?action=preview">Preview</a></li>'
                +       '<li><a class="pic" role="menuitem" id="tab-graphs-pre-view" href="' + urlPath + 'graph_view.php?action=tree">Tree</a></li>'
                +    '</ul>'
                +'</li>';
        }

        let showMisc = false;
        $('.maintabs nav ul li a.lefttab').each(function() {
            if ($(this).attr('id') !== 'tab-console' && $(this).attr('id') !== 'tab-graphs') {
                showMisc = true;
                return true;
            }
        });
        if (showMisc) {
            compact_tab_menu_content +=
                '<li class="menuitem" id="menu_tab_miscellaneous">'
                +   '<a class="menu_parent" href="#" inert>'
                +       '<i class="menu_glyph ignore fas fa-puzzle-piece"></i>'
                +       '<span>'+cactiMisc+'</span>'
                +   '</a>'
                +'<ul>';
        }

        $('.maintabs nav ul li a.lefttab').each( function() {
            let id = $(this).attr('id');

            if (id === 'tab-graphs' && $(this).parent().hasClass('maintabs-has-submenu') === false) {
                $(this).parent().addClass('maintabs-has-submenu');

                let submenu_tab_graphs_content =
                    '<ul id="submenu-tab-graphs" class="submenuoptions" style="display:none;">'
                    + '<li><a id="tab-graphs-tree-view" href="' + urlPath + 'graph_view.php?action=tree"><span>' + treeView + '</span></a></li>'
                    + '<li><a id="tab-graphs-list-view" href="' + urlPath + 'graph_view.php?action=list"><span>' + listView + '</span></a></li>'
                    + '<li><a id="tab-graphs-pre-view" href="' + urlPath + 'graph_view.php?action=preview"><span>' + previewView + '</span></a></li>'
                    + '</ul>';

                $('<div class="dropdownMenu">' + submenu_tab_graphs_content + '</div>').appendTo('body');
            } else if ($(this).attr('href') !== urlPath + 'index.php') {
                compact_tab_menu_content += '<li><a class="pic" role="menuitem" href="' + $(this).attr('href') + '">' + $('.text_' + id).text() + '</a></li>';
            }
        });
        compact_tab_menu_content += '</ul></li></ul></div>';
        return compact_tab_menu_content;
    }

function get_help_content() {
    let compact_help_menu_content =
           '<ul class="nav">'
        +   '<li class="menuitem" id="menu_user_help">'
        +       '<a class="menu_parent" href="#" inert>'
        +           '<i class="menu_glyph fas fa-medkit"></i>'
        +           '<span>'+cactiGeneral+'</span>'
        +       '</a>'
        +       '<ul>'
        +           '<li><a class="pic" role="menuitem" href="'+urlPath+'about.php">'+aboutCacti+'</a></li>'
        +           '<li><a href="https://github.com/Cacti/documentation/blob/develop/README.md" target="_blank" rel="noopener noreferrer">'+cactiDocumentation+'</a></li>'
        +           '<li><a href="https://github.com/cacti" target="_blank" rel="noopener noreferrer">'+cactiProjectPage+'</a></li>'
        +           '<li><a href="https://www.cacti.net" target="_blank" rel="noopener noreferrer">'+cactiHome+'</></a></li>'
        +       '</ul>'
        +   '</li>'
        +   '<li class="menuitem" id="menu_user_issues">'
        +       '<a class="menu_parent" href="#" inert>'
        +           '<i class="menu_glyph fas fa-bug"></i>'
        +           '<span>'+reportABug+'</span>'
        +       '</a>'
        +       '<ul>'
        +           '<li><a href="https://github.com/Cacti/cacti/issues/new/choose" target="_blank" rel="noopener noreferrer">'+justCacti+'</></a></li>'
        +           '<li><a href="https://github.com/Cacti/documentation/issues/new/choose" target="_blank" rel="noopener noreferrer">'+cactiDocumentation+'</></a></li>'
        +           '<li><a href="https://github.com/Cacti/spine/issues/new/choose" target="_blank" rel="noopener noreferrer">'+cactiSpine+'</a></li>'
        +           '<li><a href="https://github.com/Cacti/rrdproxy/issues/new/choose" target="_blank" rel="noopener noreferrer">'+cactiRRDProxy+'</a></li>'
        +       '</ul>'
        +   '</li>'
        +   '<li class="menuitem" id="menu_user_shortcuts">'
        +       '<a class="menu_parent" href="#" inert>'
        +           '<i class="menu_glyph far fa-keyboard"></i>'
        +           '<span>'+cactiKeyboard+'</span>'
        +       '</a>'
        +       '<ul>'
        +           '<li><a href="#" class="dialog_client">'+cactiShortcuts+'</a></li>'
        +       '</ul>'
        +   '</li>'
        +   '<li class="menuitem" id="menu_user_help">'
        +       '<a class="menu_parent" href="#" inert>'
        +           '<i class="menu_glyph fas fa-hands-helping"></i>'
        +           '<span>'+cactiContributeTo+'</span>'
        +       '</a>'
        +       '<ul>'
        +           '<li><a href="https://forums.cacti.net/" target="_blank" rel="noopener noreferrer">'+cactiCommunityForum+'</a></li>'
        +           '<li><a href="https://github.com/cacti" target="_blank" rel="noopener noreferrer">'+cactiDevHelp+'</a></li>'
        +           '<li><a href="https://www.cacti.net/development/contribute" target="_blank" rel="noopener noreferrer">'+cactiDonate+'</a></li>'
        +           '<li><a href="https://translate.cacti.net" target="_blank" rel="noopener noreferrer">'+cactiTranslate+'</a></li>'
        +       '</ul>'
        +   '</li>'
        +   '</ul>';
    return compact_help_menu_content;
}

function get_user_content() {
    let storage = Storages.localStorage;
    let midWinter_Color_Mode = storage.get('midWinter_Color_Mode');
    let midWinter_Color_Mode_Auto = storage.get('midWinter_Color_Mode_Auto');
    let midWinter_Font_Size = storage.get('midWinter_Font_Size');
    let midWinter_widthNavigationBox_dashboards = storage.get('midWinter_widthNavigationBox_dashboards');
    let midWinter_Animations = storage.get('midWinter_Animations');
    let midWinter_ShownFontSizeValue = parseFloat(midWinter_Font_Size) + 25;
    let midWinter_Auto_Table_Layout = storage.get('midWinter_Auto_Table_Layout');
    let midWinter_Controls_SubTitle = storage.get('midWinter_Controls_SubTitle');

    let compact_user_menu_content =
           '<ul class="nav">'
        +   '<li class="menuitem" id="menu_user_action">'
        +       '<a class="menu_parent" href="#" inert>'
        +           '<i class="menu_glyph fas fa-user-edit"></i>'
        +           '<span>'+cactiProfile+'</span>'
        +       '</a>'
        +       '<ul>'
        +           '<li><a class="pic" role="menuitem" href="'+urlPath+'auth_profile.php?action=edit&header=false">'+editProfile+'</a></li>'
        +           '<li><a href="'+urlPath+'auth_changepassword.php" style="">'+changePassword+'</a></li>'
        +           '<li><a href="'+urlPath+'logout.php">'+logout+'</a></li>'
        +       '</ul>'
        +   '</li>'
        +   '<li class="menuitem double" id="menu_user_action">'
        +       '<a class="menu_parent" href="#" inert>'
        +           '<i class="menu_glyph fas fa-palette"></i>'
        +           '<span>'+cactiTheme+'</span>'
        +       '</a>'
        +       '<ul>'
        +           '<li>'
        +				'<div>' + usePreferredColorTheme + '</div>'
        +				'<div>'
        +					'<label class="checkboxSwitch">'
        +						'<input data-scope="theme" id="mdw_themeColorModeAuto" data-func="toggleColorModeAuto" class="formCheckbox" type="checkbox" name="mdw_themeColorModeAuto" '+(midWinter_Color_Mode_Auto === 'on' ? 'checked' : '')+'>'
        +						'<span class="checkboxSlider checkboxRound"></span>'
        +					'</label>'
        +					'<label class="checkboxLabel checkboxLabelWanted" for="mdw_themeColorModeAuto"></label>'
        +                   '<output id="mdw_themeColorModeAutoValue">'+ midWinter_Color_Mode_Auto +'</output>'
        +				'</div>'
        +           '</li>'
        +           '<li>'
        +				'<div>' + 'Animations' + '</div>'
        +				'<div>'
        +					'<label class="checkboxSwitch">'
        +						'<input data-scope="theme" id="mdw_themeAnimations" data-func="toggleGuiAnimations" class="formCheckbox" type="checkbox" name="mdw_themeAnimations" '+(midWinter_Animations === 'on' ? 'checked' : '')+'>'
        +						'<span class="checkboxSlider checkboxRound"></span>'
        +					'</label>'
        +					'<label class="checkboxLabel checkboxLabelWanted" for="mdw_themeAnimations"></label>'
        +                   '<output id="mdw_themeAnimationsValue">'+ midWinter_Animations +'</output>'
        +				'</div>'
        +           '</li>'
        +           '<li>'
        +				'<div>' + 'Zoom Level' + '</div>'
        +				'<div>'
        +						'<input data-scope="theme" class="mdw_themeFontSize" id="mdw_themeFontSize" data-func="changeGuiFontSize" type="range" min="50" max="100" step="2.5" value="'+ midWinter_Font_Size +'" defaultValue="75">'
        +                       '<output id="mdw_themeFontSizeValue">'+midWinter_ShownFontSizeValue+'%</output>'
        +				'</div>'
        +           '</li>'
        +           '<li>'
        +				'<div>' + 'Show Control Names' + '</div>'
        +				'<div>'
        +					'<label class="checkboxSwitch">'
        +						'<input data-scope="theme" id="mdw_themeControlsSubTitle" data-func="toggleControlsSubtitle" class="formCheckbox" type="checkbox" name="mdw_themeControlsSubtitle" '+(midWinter_Controls_SubTitle === 'on' ? 'checked' : '')+'>'
        +						'<span class="checkboxSlider checkboxRound"></span>'
        +					'</label>'
        +					'<label class="checkboxLabel checkboxLabelWanted" for="mdw_themeControlsSubTitle"></label>'
        +                   '<output id="mdw_themeControlsSubTitleValue">'+ midWinter_Controls_SubTitle +'</output>'
        +				'</div>'
        +           '</li>'
        +       '</ul>'
        +   '</li>'
        +   '<li class="menuitem double" id="menu_user_action">'
        +       '<a class="menu_parent" href="#" inert>'
        +           '<i class="menu_glyph fas fa-mobile-alt"></i>'
        +           '<span>Mobile Devices</span>'
        +       '</a>'
        +       '<ul>'
        +           '<li>'
        +				'<div>' + 'Auto Table Layout' + '</div>'
        +				'<div>'
        +					'<label class="checkboxSwitch">'
        +						'<input data-scope="theme" id="mdw_themeAutoTableLayout" data-func="toggleAutoTableLayout" class="formCheckbox" type="checkbox" name="mdw_themeAutoTableLayout" '+(midWinter_Auto_Table_Layout === 'on' ? 'checked' : '')+'>'
        +						'<span class="checkboxSlider checkboxRound"></span>'
        +					'</label>'
        +					'<label class="checkboxLabel checkboxLabelWanted" for="mdw_themeAutoTableLayout"></label>'
        +                   '<output id="mdw_themeAutoTableLayoutValue">'+ midWinter_Auto_Table_Layout +'</output>'
        +				'</div>'
        +           '</li>'
        +       '</ul>'
        +   '</li>'
        +'</ul>';
    return compact_user_menu_content;
}

function get_tree_content() {
    let compact_tree_content =
        '<div class="mdw_tree" id="mdw_tree">'
        +   '<div class="mdw-treen_content" id="mdw_tree_content">'
        +   '</div>'
        +'</div>';
    return compact_tree_content;
}
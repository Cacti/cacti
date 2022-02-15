// Host Autocomplete Magic
themeLoader('on');
let themeInitialized = false;
let themeUserMenu

function themeReady() {
    /* load default values */
    initStorageItem('midWinter_GUI_Mode', 'compact');
    initStorageItem('midWinter_Color_Mode', 'dark');
    initStorageItem('midWinter_Color_Mode_Auto', 'on');
    initStorageItem('midWinter_Font_Size', 'regular', 'zoom-level');

    themeInitialized = midwinterInitialized();
    checkConsoleMenu();
    setThemeColor();
    setupTheme();
    setupTree();
    setupDefaultElements();
    setMenuVisibility();
    setHotKeys();
    ajaxAnchors();
    extendAnchorActions();
    updateNavigation();
    themeLoader('off');
}


function checkConsoleMenu() {
    if( $('#cactiContent').length !== 0 && $('#menu').length === 0  ) {
        $('<div id="menu"></div>').appendTo('body');
        $( "#menu" ).load( urlPath + 'about.php' + " #menu",
            function (responseText, textStatus, XMLHttpRequest) {
                if (textStatus == "success") {

                }
                if (textStatus == "error") {

                }
        });
    }
}

function midwinterInitialized() {
    return ($('#compact_tab_menu').length !== 0);
}

function extendAnchorActions() {
    $('a[role="menuitem"]').on('click', function() { midWinterNavigation( $(this) ); });
}

function midWinterNavigation(element) {
    let action   =  element.parent().html();
    let category =  element.closest('.menuitem').children('.menu_parent').first().html();
    let helper   =  element.closest('div[class^="cactiConsoleNavigation"]').data('helper');
    let rubric   =  $('.compact_nav_icon[data-helper="'+helper+'"]').html();

    $('#navTitle .rubric').html( rubric );
    $('#navTitle .category').html( category );
    $('#navTitle .action').html( action );
}

function updateNavigation() {
    if(themeInitialized === false) {
        var menu_element = $('.cactiConsoleNavigationArea a[href$="'+window.location.pathname+window.location.search+'"').first();
        if(menu_element.length !== 0) return midWinterNavigation(menu_element);
        var menu_element = $('.cactiConsoleNavigationArea a[href$="'+window.location.pathname+'"').first();
        if(menu_element.length !== 0) return midWinterNavigation(menu_element);
        var menu_element = $('.cactiConsoleNavigationArea a[href$="'+window.location.pathname+'index.php"').first();
        if(menu_element.length !== 0) return midWinterNavigation(menu_element);
    }
}

function setupTree() {
    let storage = Storages.localStorage;
    let midWinter_GUI_Mode = storage.get('midWinter_GUI_Mode');
    let urlParams = new URLSearchParams(window.location.search);
    let action = urlParams.get('action');

    if(midWinter_GUI_Mode === 'compact' && pageName === 'graph_view.php' && action === 'tree') {
        $('#mdw_tree').removeClass('hide');
    }else {
        $('#mdw_tree').addClass('hide');
    }
}

function setupTheme() {

    let storage = Storages.localStorage;
    let midWinter_Color_Mode = storage.get('midWinter_Color_Mode');
    let midWinter_Color_Mode_Auto = storage.get('midWinter_Color_Mode_Auto');
    let midWinter_Font_Size = storage.get('midWinter_Font_Size');

    // -- standard mode -- add user tabs to CactiPageHeader
    if ($('.usertabs').length === 0) {
        $('.infoBar, .menuHr, #userDocumentation, #userCommunity').remove();
        $('.loggedInAs').show();

        let user_tab_content =
            '<ul>'
            + '<li><a id="menu-user-help" class="usertabs-submenu" href="#"><i class="far fa-comment-alt"></i></a></li>'
            + '<li class="action-icon-user"><a class="pic" href="#"><i class="far fa-user"></i></a></li>'
            + '</ul>';
        $('<div class="maintabs usertabs">' + user_tab_content + '</div>').insertAfter('.maintabs');

        let submenu_user_help_content =
            '<li><a href="https://www.cacti.net" target="_blank" rel="noopener">'+cactiHome+'</></a></li>'
            +'<li><a href="https://github.com/cacti" target="_blank" rel="noopener">'+cactiProjectPage+'</a></li>'
            +'<li><hr class="menu"></li>'
            +'<li><a href="https://forums.cacti.net/" target="_blank" rel="noopener">'+cactiCommunityForum+'</a></li>'
            +'<li><a href="https://github.com/Cacti/documentation/blob/develop/README.md" target="_blank" rel="noopener">'+cactiDocumentation+'</a></li>'
            +'<li><hr class="menu"></li>'
            +'<li><a href="https://github.com/Cacti/cacti/issues/new" target="_blank" rel="noopener">'+reportABug+'</a></li>'
            +'<li><a href="'+urlPath+'about.php">'+aboutCacti+'</a></li>';

        $('<div class="dropdownMenu">'
            +   '<ul id="submenu-user-help" class="submenuoptions right" style="display:none;">'
            +       submenu_user_help_content
            +   '</ul>'
            +'</div>'
        ).appendTo('body');

        let theme_switches =
            '<li><hr class="menu"></li>'
            +'<li><a href="#" class="toggleGuiMode">'+compactGraphicalUserInterface+'</a></li>'
            +'<li><a href="#" class="toggleColorMode">'+(midWinter_Color_Mode === 'light' ? darkColorMode : lightColorMode)+'</a></li>'
            +'<li><a href="#" class="toggleColorModeAuto">'+(midWinter_Color_Mode_Auto === 'on' ? ignorePreferredColorTheme : usePreferredColorTheme)+'</a></li>'
            +'<li><a href="#" class="toggleGuiFontSize">Font Size: '+ midWinter_Font_Size +'</a></li>'
            +'<li><hr class="menu"></li>';
        $('.menuoptions').find('li').eq(2).after(theme_switches);

    }

    // -- standard & compact mode -- redesign navigation tabs
    let compact_tab_menu_content =
        '<div class="cactiConsoleNavigationBox hide" data-helper="dashboards">'
        + '<div class="header compact">Dashboards</div>'
        + '<ul class="nav">'
        +   '<li class="menuitem" id="menu_home">'
        +       '<a class="menu_parent active" href="#">'
        +           '<i class="menu_glyph ignore fas fa-home"></i>'
        +           '<span>Home</span>'
        +       '</a>'
        +       '<ul>'
        +           '<li><a href="'+urlPath+'index.php" class="pic" role="menuitem">Console</a></li>'
        +       '</ul>'
        +   '</li>'
        +   '<li class="menuitem" id="menu_tab_dashboard">'
        +       '<a class="menu_parent active" href="#">'
        +           '<i class="menu_glyph ignore fas fa-chart-area"></i>'
        +           '<span>Charts</span>'
        +       '</a>'
        +       '<ul>'
        +           '<li><a class="pic" role="menuitem" id="tab-graphs-tree-view" href="' + urlPath + 'graph_view.php?action=tree">' + treeView + '</a></li>'
        +           '<li><a class="pic" role="menuitem" id="tab-graphs-list-view" href="' + urlPath + 'graph_view.php?action=list">' + listView + '</a></li>'
        +           '<li><a class="pic" role="menuitem" id="tab-graphs-pre-view" href="' + urlPath + 'graph_view.php?action=preview">' + previewView + '</a></li>'
        +       '</ul>'
        +   '</li>'
        +   '<li class="menuitem" id="menu_tab_miscellaneous">'
        +       '<a class="menu_parent active" href="#">'
        +           '<i class="menu_glyph ignore fas fa-puzzle-piece"></i>'
        +           '<span>Miscellaneous</span>'
        +       '</a>'
        +       '<ul>';


    $('.maintabs nav ul li a.lefttab').each( function() {
        let id = $(this).attr('id');
        let title = id.replace('tab-', '');

        if (id === 'tab-graphs' && $(this).parent().hasClass('maintabs-has-submenu') === false) {
            $(this).parent().addClass('maintabs-has-submenu');

            let submenu_tab_graphs_content =
                '<ul id="submenu-tab-graphs" class="submenuoptions" style="display:none;">'
                + '<li><a id="tab-graphs-tree-view" href="' + urlPath + 'graph_view.php?action=tree"><span>' + treeView + '</span></a></li>'
                + '<li><a id="tab-graphs-list-view" href="' + urlPath + 'graph_view.php?action=list"><span>' + listView + '</span></a></li>'
                + '<li><a id="tab-graphs-pre-view" href="' + urlPath + 'graph_view.php?action=preview"><span>' + previewView + '</span></a></li>'
                + '</ul>';
            $('<div class="dropdownMenu">' + submenu_tab_graphs_content + '</div>').appendTo('body');
        }else {
            if($(this).attr('href') !== urlPath + 'index.php') {
                compact_tab_menu_content += '<li><a class="pic" role="menuitem" href="' + $(this).attr('href') + '">' + $('.text_' + id).text() + '</a></li>';
            }
        }
    });
    compact_tab_menu_content += '</ul></li></ul></div>';

    if($('.cactiConsoleNavigationArea').length === 0 && $('.cactiContent').length !== 0)  {
        $('<div id="navigation" class="cactiConsoleNavigationArea compact"></div>').prependTo('#cactiContent');
    }

    // -- compact mode -- redesign console navigation area
    if($('.cactiConsoleNavigationArea').length !== 0) {

        if($('#compact_tab_menu').length === 0 && $('#compact_user_menu').length === 0) {

            // -- split the navigation area into 3 parts to separate tabs (dashboards), settings and user menus
            let menu = $('#menu').detach();

            $('.cactiConsoleNavigationArea').empty().prepend(
                '<div class="compact" id="compact_tab_menu"></div>'
                +'<div class="compact" id="compact_user_menu"></div>'
            );
            $(menu).insertAfter('#compact_tab_menu');
            $('#menu').addClass('cactiConsoleNavigationBox hide').attr('data-helper', 'settings');
            $('<div class="header compact">Settings</div>').prependTo('#menu');

            // Clean up: kick out Main Console
            $('#menu_main_console').remove();

            // -- duplicate the console tab items and add them to the console navigation area for compact mode
            if ($.trim($('compact_tab_menu').html()) === '') {
                $('<div class="compact_nav_icon" data-helper="dashboards">'+
                        '<i class="fas fa-th-large"></i>'+
                        '<span>'+'Dashboards'+'</span>'+
                    '</div>'+
                    '<div class="compact_nav_icon" data-helper="settings">'+
                        '<i class="fas fa-cogs"></i>'+
                        '<span>'+'Settings'+'</span>'+
                    '</div>').appendTo('#compact_tab_menu');
                $(compact_tab_menu_content).appendTo('#compact_tab_menu');
            }

            // -- compact mode --
            $('<div class="compact_nav_icon" data-helper="help">'+
                    '<i class="far fa-comment-alt"></i>'+
                    '<span>'+'Help'+'</span>'+
                '</div>'+
                '<div class="compact_nav_icon" data-helper="user">'+
                    '<i class="far fa-user"></i>'+
                    '<span>'+'User'+'</span>'+
                '</div>'+
                '<div class="compact_nav_icon mdw_logout"><i class="fas fa-sign-out-alt"></i></div>'
            ).appendTo('#compact_user_menu');


            let compact_user_menu_content =
                '<div class="cactiConsoleNavigationUserBox hide" data-helper="help">'
                +   '<div class="header compact">Cacti &reg; v'+cactiVersion+'</div>'
                +   '<ul class="nav">'
                +   '<li class="menuitem" id="menu_user_help">'
                +       '<a class="menu_parent active" href="#">'
                +           '<i class="menu_glyph fas fa-medkit"></i>'
                +           '<span>General</span>'
                +       '</a>'
                +       '<ul>'
                +           '<li><a class="pic" role="menuitem" href="'+urlPath+'about.php">'+aboutCacti+'</a></li>'
                +           '<li><a href="https://github.com/Cacti/documentation/blob/develop/README.md" target="_blank" rel="noopener">'+cactiDocumentation+'</a></li>'
                +           '<li><a href="https://github.com/cacti" target="_blank" rel="noopener">'+cactiProjectPage+'</a></li>'
                +           '<li><a href="https://www.cacti.net" target="_blank" rel="noopener">'+cactiHome+'</></a></li>'
                +       '</ul>'
                +   '</li>'
                +   '<li class="menuitem" id="menu_user_issues">'
                +       '<a class="menu_parent active" href="#">'
                +           '<i class="menu_glyph fas fa-bug"></i>'
                +           '<span>'+reportABug+'</span>'
                +       '</a>'
                +       '<ul>'
                +           '<li><a href="https://github.com/Cacti/cacti/issues/new/choose" target="_blank" rel="noopener">Cacti</></a></li>'
                +           '<li><a href="https://github.com/Cacti/documentation/issues/new/choose" target="_blank" rel="noopener">Documentation</></a></li>'
                +           '<li><a href="https://github.com/Cacti/spine/issues/new/choose" target="_blank" rel="noopener">Spine</a></li>'
                +           '<li><a href="https://github.com/Cacti/rrdproxy/issues/new/choose" target="_blank" rel="noopener">RRDproxy</a></li>'
                +       '</ul>'
                +   '</li>'
                +   '<li class="menuitem" id="menu_user_shortcuts">'
                +       '<a class="menu_parent active" href="#">'
                +           '<i class="menu_glyph far fa-keyboard"></i>'
                +           '<span>Keyboard</span>'
                +       '</a>'
                +       '<ul>'
                +           '<li><a href="#" class="dialog_client">Shortcuts</a></li>'
                +       '</ul>'
                +   '</li>'
                +   '<li class="menuitem" id="menu_user_help">'
                +       '<a class="menu_parent active" href="#">'
                +           '<i class="menu_glyph fas fa-hands-helping"></i>'
                +           '<span>Contribute to the Cacti Project</span>'
                +       '</a>'
                +       '<ul>'
                +           '<li><a href="https://forums.cacti.net/" target="_blank" rel="noopener">'+cactiCommunityForum+'</a></li>'
                +           '<li><a href="https://github.com/cacti" target="_blank" rel="noopener">Help in Developing</a></li>'
                +           '<li><a href="https://www.cacti.net/development/contribute" target="_blank" rel="noopener">Donation & Sponsoring</a></li>'
                +           '<li><a href="https://translate.cacti.net" target="_blank" rel="noopener">Help in Translating</a></li>'
                +       '</ul>'
                +   '</li>'
                +   '</ul>'
                +   '</div>'
                +   '<div class="cactiConsoleNavigationUserBox hide" data-helper="user">'
                +   '<div class="header compact">'+'<span>'+ $('.loggedInAs').text() +'</span>'+'</div>'
                +   '<ul class="nav">'
                +   '<li class="menuitem" id="menu_user_action">'
                +       '<a class="menu_parent active" href="#">'
                +           '<i class="menu_glyph fas fa-user-edit"></i>'
                +           '<span>Profile</span>'
                +       '</a>'
                +       '<ul>'
                +           '<li><a class="pic" role="menuitem" href="'+urlPath+'auth_profile.php?action=edit">'+editProfile+'</a></li>'
                +           '<li><a href="'+urlPath+'auth_changepassword.php" style="">'+changePassword+'</a></li>'

                +       '</ul>'
                +   '</li>'
                +   '<li class="menuitem" id="menu_user_action">'
                +       '<a class="menu_parent active" href="#">'
                +           '<i class="menu_glyph fas fa-palette"></i>'
                +           '<span>Theme</span>'
                +       '</a>'
                +       '<ul>'
                +           '<li><a href="#" class="toggleGuiMode">'+standardGraphicalUserInterface+'</a></li>'
                +           '<li><a href="#" class="toggleColorMode">'+(midWinter_Color_Mode === 'light' ? darkColorMode : lightColorMode)+'</a></li>'
                +           '<li><a href="#" class="toggleColorModeAuto">'+(midWinter_Color_Mode_Auto === 'on' ? ignorePreferredColorTheme : usePreferredColorTheme)+'</a></li>'
                +           '<li><a href="#" class="toggleGuiFontSize">Font Size: '+ midWinter_Font_Size +'</a></li>'
                +       '</ul>'
                +   '</li>'
                +   '<li class="menuitem" id="menu_user_client">'
                +       '<a class="menu_parent active" href="#">'
                +           '<i class="menu_glyph fas fa-desktop"></i>'
                +           '<span>Client</span>'
                +       '</a>'
                +       '<ul>'
                +           '<li><a href="#" class="dialog_client">Overview</a></li>'
                +       '</ul>'
                +   '</li>'
                +'</ul>'
                +'</div>';
            $(compact_user_menu_content).appendTo('#compact_user_menu');
        }

        if($('.cactiTreeNavigationArea').length === 0) {
            $('<div id="mdw_tree" class="cactiTreeNavigationArea compact hide"></div>').insertAfter("#navigation");
        }
    }

    //                +           '<li>Local Timezone: ' + Intl.DateTimeFormat().resolvedOptions().timeZone + '</li>'

    /* User Menu */
    $('.menuoptions').parent().appendTo('body');

    $('.action-icon-user').unbind().click(function(event) {
        event.preventDefault();
        if ($('.menuoptions').is(':visible') === false) {
            $('.submenuoptions').stop().slideUp(120);
            $('.menuoptions').stop().slideDown(120);
        } else {
            $('.menuoptions').stop().slideUp(120);
        }

        return false;
    });

    $('.submenuoptions, .menuoptions').on('click', function() {
        if ($(window).width() < 640) {
            $(this).stop().delay(100).slideUp(0);
        }else {
            $(this).stop().slideUp(120);
        }
    })

    $('.compact_nav_icon:not(.mdw_logout)').off().on( "click", toggleCactiNavigationBox );
    $('.compact_nav_icon.mdw_logout').off().on('click', {url: urlPath+'logout.php'}, redirect);


    $('.dialog_client').off().on('click', {id: 'dialog_client'}, dialog_client);

    $('.toggleGuiMode').unbind().click(toggleGuiMode);
    $('.toggleColorMode').unbind().click(toggleColorMode);
    $('.toggleColorModeAuto').unbind().click(toggleColorModeAuto);
    $('.toggleGuiFontSize').unbind().click(toggleGuiFontSize);


    $('.cactiConsoleContentArea, .cactiGraphContentArea').on('mouseenter', toggleCactiNavigationBox);
}


function redirect(event) {
    event.preventDefault();
    let url = event.data.url;
    window.location = url;
}

function toggleCactiNavigationBox(event) {
    event.preventDefault();
    let helper = $(this).data('helper');

    $('[class^="cactiConsoleNavigation"][class$="Box"]:not([data-helper="'+helper+'"])').addClass('hide');
    $('[class^="cactiConsoleNavigation"][data-helper="'+helper+'"]').toggleClass('hide');
}

function setupDefaultElements() {
    var pageName = basename($(location).attr('pathname'));
    var hostTimer = false;
    var clickTimeout = false;
    var hostOpen = false;

    // duplicate cactiConsolePageHeadBackdrop for compact mode
    if($('#cactiConsoleBackdrop').length === 0 ) {
        $('<div id="cactiConsoleBackdrop"></div>'+
            '<div id="navTitle">'+
                '<div class="rubric"></div><div class="separator">/</div>'+
                '<div class="category"></div><div class="separator">/</div>'+
                '<div class="action"></div>'+
            '</div>').prependTo("#breadCrumbBar");
        $("#cactiConsoleBackdrop").click( function() {
           loadPage(urlPath+'index.php');
        });
    }

    // migrate breadcrumbs to Ajax
    $('a[id^="nav_"]').each(function(data) {
        $(this).addClass('hyperLink');
    });


    // ensure that filter table and 1st navBar will stay on top
    if($('#filterTableOnTop').length !== 0 ) $('#filterTableOnTop').remove();

    if($(".filterTable").length !== 0) {
        $('<div id="filterTableOnTop">').prependTo('#navigation_right');
        $(".filterTable:first").closest('div').detach().prependTo('#filterTableOnTop');
        $(".break:first").detach().appendTo('#filterTableOnTop');
        $(".navBarNavigation:first").detach().appendTo('#filterTableOnTop');
        $( "#filterTableOnTop").addClass('sticky');
        $('<div class="cactiTableFilter"><span><i class="far fa fa-sliders-h"></i></span></div>').prependTo('#filterTableOnTop .cactiTableTitle');
    }

    // Add nice search filter to filters
    if ($('input[id="filter"]').length > 0 && $('input[id="filter"] > i[class="fa fa-search filter"]').length < 1) {
        $('input[id="filter"]').after("<i class='fa fa-search filter'/>").attr('autocomplete', 'off').attr('placeholder', searchFilter).parent('td').css('white-space', 'nowrap');
    }

    if ($('input[id="filterd"]').length > 0 && $('input[id="filterd"] > i[class="fa fa-search filter"]').length < 1) {
        $('input[id="filterd"]').after("<i class='fa fa-search filter'/>").attr('autocomplete', 'off').attr('placeholder', searchFilter).parent('td').css('white-space', 'nowrap');
    }

    if ($('input[id="rfilter"]').length > 0 && $('input[id="rfilter"] > i[class="fa fa-search filter"]').length < 1) {
        $('input[id="rfilter"]').after("<i class='fa fa-search filter'/>").attr('autocomplete', 'off').attr('placeholder', searchRFilter).parent('td').css('white-space', 'nowrap');
    }

    $('input#filter, input#rfilter').addClass('ui-state-default ui-corner-all');
    $('input[type="text"], input[type="password"], input[type="checkbox"], textarea').not('image').addClass('ui-state-default ui-corner-all');

    /* Highlight sortable table columns */
    $('.tableHeader th').has('i.fa-sort').removeClass('tableHeaderColumnHover tableHeaderColumnSelected');
    $('.tableHeader th').has('i.fa-sort-up').addClass('tableHeaderColumnSelected');
    $('.tableHeader th').has('i.fa-sort-down').addClass('tableHeaderColumnSelected');
    $('.tableHeader th').has('i.fa-sort').hover(
        function() {
            $(this).addClass("tableHeaderColumnHover");
        }, function() {
            $(this).removeClass("tableHeaderColumnHover");
        }
    );

    /* replace default icons */
    $('i.menu_glyph:not(.ignore).fa-home').removeClass('fa fa-home').addClass('fa fa-tools');
    $('i.menu_glyph.fa-folder').removeClass('fa').addClass('far');
    $('i.menu_glyph.fa-clone').removeClass('fa').addClass('far');
    $('i.menu_glyph.fa-database').removeClass('fa fa-database').addClass('far fa-hdd');
    $('i.menu_glyph:not(.ignore).fa-chart-area').removeClass('fa fa-chart-area').addClass('fa fa-plus');
    $('i.menu_glyph.fa-cogs').removeClass('fa fa-cogs').addClass('fa fa-toolbox');
    $('i.menu_glyph.fa-superpowers').removeClass('fab fa-superpowers').addClass('fas fa-network-wired');
    //$('td:nth-child(2), th:nth-child(2)').hide;


    $('input#filter, input#rfilter').addClass('ui-state-default ui-corner-all');

    $('input[type="text"], input[type="password"], input[type="checkbox"], textarea').not('image').addClass('ui-state-default ui-corner-all');

    // really shitty workaround to make custom row checkboxes clickable again. :(
    $('tr[id*="line"]:not(.disabled_row)').each(function(data) {
        $(this).find('.formCheckboxLabel').attr('for', '');
    });



    // Turn file buttons into jQueryUI buttons
    $('.import_label').button();
    $('.import_button').change(function() {
        text=this.value;
        setImportFile(text);
    });

    setImportFile(noFileSelected);

    function setImportFile(fileText) {
        $('.import_text').text(fileText);
    }

    $('select.colordropdown').dropcolor();

    $('select').not('.colordropdown').each(function() {
        if ($(this).prop('multiple') != true) {
            $(this).each(function() {
                id = $(this).attr('id');

                $(this).selectmenu({
		open: function(event, ui) {
			var instance = $(this).selectmenu('instance');
			instance.menuInstance.focus(null, instance._getSelectedItem());
		},
                    change: function(event, ui) {
                        $(this).val(ui.item.value).change();
                    },
                    position: {
                        my: "left top",
                        at: "left bottom",
                        collision: "flip"
                    },
                    width: false
                });

                $('#'+id+'-menu').css('max-height', '250px');
            });
        } else {
            $(this).addClass('ui-state-default ui-corner-all');
        }
    });

    $('#host').unbind().autocomplete({
        source: pageName+'?action=ajax_hosts',
        autoFocus: true,
        minLength: 0,
        select: function(event,ui) {
            $('#host_id').val(ui.item.id);
            callBack = $('#call_back').val();
            if (callBack != 'undefined') {
                if (callBack.indexOf('applyFilter') >= 0) {
                    applyFilter();
                } else if (callBack.indexOf('applyGraphFilter') >= 0) {
                    applyGraphFilter();
                }
            } else if (typeof applyGraphFilter === 'function') {
                applyGraphFilter();
            } else {
                applyFilter();
            }
        }
    }).addClass('ui-state-default ui-selectmenu-text').css('border', 'none').css('background-color', 'transparent');

    $('#host_click').css('z-index', '4');
    $('#host_wrapper').unbind().dblclick(function() {
        hostOpen = false;
        clearTimeout(hostTimer);
        clearTimeout(clickTimeout);
		$('#host').autocomplete('close').select();
    }).click(function() {
        if (hostOpen) {
            $('#host').autocomplete('close');
            clearTimeout(hostTimer);
            hostOpen = false;
        } else {
            clickTimeout = setTimeout(function() {
                $('#host').autocomplete('search', '');
                clearTimeout(hostTimer);
                hostOpen = true;
            }, 200);
        }
		$('#host').select();
    }).on('mouseenter', function() {
        $(this).addClass('ui-state-hover');
        $('input#host').addClass('ui-state-hover');
    }).on('mouseleave', function() {
        $(this).removeClass('ui-state-hover');
        $('#host').removeClass('ui-state-hover');
        hostTimer = setTimeout(function() { $('#host').autocomplete('close'); }, 800);
        hostOpen = false;
    });

    var hostPrefix = '';
    $('#host').autocomplete('widget').each(function() {
        hostPrefix=$(this).attr('id');

        if (hostPrefix != '') {
            $('ul[id="'+hostPrefix+'"]').on('mouseenter', function() {
                clearTimeout(hostTimer);
            }).on('mouseleave', function() {
                hostTimer = setTimeout(function() { $('#host').autocomplete('close'); }, 800);
                $(this).removeClass('ui-state-hover');
                $('input#host').removeClass('ui-state-hover');
            });
        }
    });

    // Hide the graph icons until you hover
    $('.graphDrillDown').hover(
        function() {
            element = $(this);

            // hide the previously shown element
            if (element.attr('id').replace('dd', '') != graphMenuElement && graphMenuElement > 0) {
                $('#dd'+graphMenuElement).find('.iconWrapper:first').hide(300);
            }

            clearTimeout(graphMenuTimer);
            graphMenuTimer = setTimeout(function() { showGraphMenu(element); }, 400);
        },
        function() {
            element = $(this);
            clearTimeout(graphMenuTimer);
            graphMenuTimer = setTimeout(function() { hideGraphMenu(element); }, 400);
        });

    function showGraphMenu(element) {
        element.find('.spikekillMenu').menu('disable');
        element.find('.iconWrapper').show(300, function() {
            graphMenuElement = element.attr('id').replace('dd', '');
            $(this).find('.spikekillMenu').menu('enable');
        });
    }

    function hideGraphMenu(element) {
        element.find('.spikekillMenu').menu('disable');
        element.find('.iconWrapper').hide(300, function() {
            $(this).find('.spikekillMenu').menu('enable');
        });
    }

    setNavigationScroll();
}

function initStorageItem(name, default_value, data_attribute= '') {
    let storage = Storages.localStorage;
    if (storage.isSet(name) === false) {
        storage.set(name, default_value);
    }
    if (data_attribute !=='') {
        setDocumentAttribute(data_attribute, storage.get(name));
    }
    return storage.get(name);
}

function themeLoader(state='off', force = false) {
    if(state === 'on') {
        if(getDocumentAttribute('data-theme-state') !== 'ready' | force === true) {
            setDocumentAttribute('theme-state', 'loading');
        }
    }else {
        setDocumentAttribute('theme-state', 'ready');
    }
}

function setDocumentAttribute(name, value) {
    document.documentElement.setAttribute('data-'+name, value);
}

function getDocumentAttribute(name) {
    return document.documentElement.getAttribute('data-'+name);
}

function setCookieValue(name, value) {
    $.cookie(name, value.toString(), { expires: 365, path: urlPath + ';SameSite=Lax', secure: true });
}

function toggleGuiMode() {
    let storage = Storages.localStorage;
    let midWinter_GUI_Mode = storage.get('midWinter_GUI_Mode');

    midWinter_GUI_Mode = (midWinter_GUI_Mode === 'standard') ? 'compact' : 'standard';
    storage.set('midWinter_GUI_Mode', midWinter_GUI_Mode);

    setDocumentAttribute('theme-mode', midWinter_GUI_Mode);
}

function toggleColorMode() {
    let storage = Storages.localStorage;
    let midWinter_Color_Mode = storage.get('midWinter_Color_Mode');
    let midWinter_Color_Mode_Auto = storage.get('midWinter_Color_Mode_Auto');

    if(midWinter_Color_Mode_Auto !== 'on') {
        midWinter_Color_Mode = (midWinter_Color_Mode === 'dark') ? 'light' : 'dark';
        storage.set('midWinter_Color_Mode', midWinter_Color_Mode);
        $('.toggleColorMode').text(midWinter_Color_Mode === 'dark' ? lightColorMode : darkColorMode);
        setDocumentAttribute('theme-color', midWinter_Color_Mode);
        setCookieValue('CactiColorMode', midWinter_Color_Mode);
        initializeGraphs(true);
    }
}

function toggleColorModeAuto() {
    let storage = Storages.localStorage;
    let midWinter_Color_Mode = storage.get('midWinter_Color_Mode');
    let midWinter_Color_Mode_Auto = storage.get('midWinter_Color_Mode_Auto');

    midWinter_Color_Mode_Auto = (midWinter_Color_Mode_Auto === 'on') ? 'off' : 'on';
    storage.set('midWinter_Color_Mode_Auto', midWinter_Color_Mode_Auto);
    $('.toggleColorModeAuto').text( midWinter_Color_Mode_Auto === 'on' ? ignorePreferredColorTheme : usePreferredColorTheme );

    setThemeColor();
}

function toggleGuiFontSize() {
    let storage = Storages.localStorage;
    let midWinter_Font_Size = storage.get('midWinter_Font_Size');

    if(midWinter_Font_Size == 'regular') {
        midWinter_Font_Size = 'large';
    }else if(midWinter_Font_Size == 'large') {
        midWinter_Font_Size = 'small';
    }else {
        midWinter_Font_Size = 'regular';
    }
    storage.set('midWinter_Font_Size', midWinter_Font_Size);
    $('.toggleGuiFontSize').text('Font Size: '+ midWinter_Font_Size);

    setDocumentAttribute('zoom-level', midWinter_Font_Size);
}

function setThemeColor() {
    let storage = Storages.localStorage;

    if(storage.get('midWinter_Color_Mode_Auto') === 'on') {
        $('.toggleColorMode').hide(0);
        detectSystemColorSetup();
    }else {
        $('.toggleColorMode').show(0);
        setDocumentAttribute('theme-color', storage.get('midWinter_Color_Mode'));
        //setCookieValue('CactiColorMode', storage.get('midWinter_Color_Mode'));
    }
    setDocumentAttribute('theme-mode', storage.get('midWinter_GUI_Mode'));
}

function detectSystemColorSetup() {
    const systemColorMode = window.matchMedia("(prefers-color-scheme: dark)");

    try {
        systemColorMode.addEventListener('change', (e) => {
            checkThemeColorSetup((e.matches) ? 'dark' : 'light')
        });
    } catch (e1) {
        try {
            systemColorMode.addListener((e) => {
                checkThemeColorSetup((e.matches) ? 'dark' : 'light')
            });
        } catch (e2) {
            console.error(e2);
        }
    }
    checkThemeColorSetup(systemColorMode.matches === true ? 'dark' : 'light');
}

function checkThemeColorSetup(color_mode) {
    let document_color_mode = document.documentElement.getAttribute('data-theme-color');

    if (document_color_mode !== color_mode) {
        setDocumentAttribute('theme-color', color_mode)
        setCookieValue('CactiColorMode', color_mode);

        initializeGraphs(true);
    }
}

function setMenuVisibility() {
    storage=Storages.localStorage;

    // Initialize the navigation settings
    // This will setup the initial visibility of the menu
    $('li.menuitem').each(function() {
        var id = $(this).attr('id');

        if (storage.isSet(id)) {
            var active = storage.get(id);
        } else {
            var active = null;
        }

        if (active != null && active === 'active') {
            $(this).find('ul').attr('aria-hidden', 'false').attr('aria-expanded', 'true').show();
            $(this).next('a').show();
        } else {
            $(this).find('ul').attr('aria-hidden', 'true').attr('aria-expanded', 'false').hide();
            $(this).next('a').hide();
        }

        if ($(this).find('a.selected').length == 0) {
            //console.log('hiding1:'+$(this).closest('.menuitem').attr('id'));
            $(this).find('ul').attr('aria-hidden', 'true').attr('aria-expanded', 'false').hide();
            $(this).next('a').hide();
            storage.set($(this).closest('.menuitem').attr('id'), 'collapsed');
        } else {
            $(this).find('ul').attr('aria-hidden', 'false').attr('aria-expanded', 'true').show();
            $(this).next('a').show();
            storage.set($(this).closest('.menuitem').attr('id'), 'active');
        }
    });

    // Functon to give life to the Navigation pane
    $('#nav li:has(ul) a.active').unbind().click(function(event) {
        event.preventDefault();

        id = $(this).closest('.menuitem').attr('id');

        if ($(this).next().is(':visible')) {
            $(this).next('ul').attr('aria-hidden', 'true').attr('aria-expanded', 'false');
            $(this).next().slideUp( { duration: 200, easing: 'swing' } );
            storage.set(id, 'collapsed');
        } else {
            $(this).next('ul').attr('aria-hidden', 'false').attr('aria-expanded', 'true');
            $(this).next().slideToggle( { duration: 200, easing: 'swing' } );
            if ($(this).next().is(':visible')) {
                storage.set($(this).closest('.menuitem').attr('id'), 'active');
            } else {
                storage.set(id, 'collapsed');
            }
        }

        $('li.menuitem').not('#'+id).each(function() {
            text = $(this).attr('id');
            id   = $(this).attr('id');

            $(this).find('ul').attr('aria-hidden', 'true').attr('aria-expanded', 'false');
            $(this).find('ul').slideUp( { duration: 200, easing: 'swing' } );
            storage.set($(this).attr('id'), 'collapsed');
        });
    });
}

function setHotKeys() {
    $.cachedScript('include/themes/midwinter/vendor/hotkeys/hotkeys.js').done(function (script, textStatus) {
        if(textStatus === 'success') {
            hotkeys('SHIFT+c,c+t,c+l,c+p,c+F1,F5,SHIFT+m+d, SHIFT+g, SHIFT+p, ESC, SHIFT+k', function (event, handler) {
                event.preventDefault();
                switch (handler.key) {
                    case 'SHIFT+c':
                        loadPage(urlPath+'index.php');
                        break;
                    case 'c+t':
                        loadPage(urlPath+'graph_view.php?action=tree');
                        break;
                    case 'c+l':
                        loadPage(urlPath+'graph_view.php?action=list');
                        break;
                    case 'c+p':
                        loadPage(urlPath+'graph_view.php?action=preview');
                        break;
                    case 'F5':
                        loadPage(window.location.href);
                        break;
                    case 'SHIFT+m+d':
                        loadPage(urlPath+'host.php');
                        break;
                    case 'SHIFT+g':
                        loadPage(urlPath+'graphs.php');
                        break;
                    case 'SHIFT+p':
                        loadPage(urlPath+'auth_profile.php?action=edit');
                        break;
                    case 'SHIFT+k':
                        kiosk_mode();
                        break;
                    case 'ESC':
                        kiosk_mode('off');
                        break;

                    default: alert(event);
                }
                return false;
            });
        }
    });
}



jQuery.cachedScript = function(url, options) {
    options = $.extend(options||{}, {
        dataType: "script",
        cache: true,
        url: url
    })
    return jQuery.ajax(options);
}

function kiosk_mode(state='toggle') {
    if(state == 'toggle') {
        //hide all navigation elements
        $('.cactiConsoleNavigationArea, .breadCrumbBar').toggleClass('hide');
        $('.cactiContent').toggleClass('fullscreen');
    }else {
        $('.cactiConsoleNavigationArea, .breadCrumbBar').removeClass('hide');
        $('.cactiContent').removeClass('fullscreen');
    }
}


function dialog_client(event) {
    event.preventDefault();
    console.log('dialog_user');
    $.cachedScript('include/themes/midwinter/vendor/ua-parser/ua-parser.js').done(function (script, textStatus) {
        if (textStatus === 'success') {
            let title='Your Client';

            $('#dialog_container').remove();
            $('body').append('<div id="dialog_container" style="display:none"></div>');
            $('#dialog_container').dialog({
                draggable: true,
                resizable: false,
                height: 'auto',
                minWidth: 400,
                maxWidth: 800,
                maxHeight: 600,
                title: title
            });
            console.log('loaded');
       }
    });
}
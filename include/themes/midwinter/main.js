// Host Autocomplete Magic
var pageName = basename($(location).attr('pathname'));

function themeReady() {
    /* load default values */
    initStorageItem('midWinter_GUI_Mode', 'standard');
    initStorageItem('midWinter_Color_Mode', 'light');
    initStorageItem('midWinter_Color_Mode_Auto', 'on');

    setupTheme();
    setupDefaultElements();
    setThemeColor();
    setMenuVisibility();
    ajaxAnchors();
}

function setupTheme() {

    let storage = Storages.localStorage;
    let midWinter_Color_Mode = storage.get('midWinter_Color_Mode');
    let midWinter_Color_Mode_Auto = storage.get('midWinter_Color_Mode_Auto');

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
            +'<li><hr class="menu"></li>';
        $('.menuoptions').find('li').eq(2).after(theme_switches);

    }

    // -- standard & new mode -- redesign navigation tabs
    let compact_tab_menu_content =
        '<ul class="nav">'
        +   '<li class="menuitem" id="menu_tab_dashboard">'
        +       '<a class="menu_parent active" href="#">'
        +           '<i class="menu_glyph fas fa-th"></i>'
        +           '<span>Dashboards</span>'
        +       '</a>'
        +       '<ul>';

    $('.maintabs nav ul li a.lefttab').each( function() {
        let id = $(this).attr('id');
        let title = id.replace('tab-', '');

        if (id === 'tab-graphs' && $(this).parent().hasClass('maintabs-has-submenu') === false) {
            $(this).parent().addClass('maintabs-has-submenu');

            let submenu_tab_graphs_content =
                '<ul id="submenu-tab-graphs" class="submenuoptions" style="display:none;">'
                +   '<li><a id="tab-graphs-tree-view" href="'+urlPath+'graph_view.php?action=tree"><span>'+treeView+'</span></a></li>'
                +   '<li><a id="tab-graphs-list-view" href="'+urlPath+'graph_view.php?action=list"><span>'+listView+'</span></a></li>'
                +   '<li><a id="tab-graphs-pre-view" href="'+urlPath+'graph_view.php?action=preview"><span>'+previewView+'</span></a></li>'
                +'</ul>';
            $('<div class="dropdownMenu">'+ submenu_tab_graphs_content +'</div>').appendTo('body');

            compact_tab_menu_content +=
                '<li><hr class="menu"></li>'
                +'<li><a class="hyperLink" id="tab-graphs-tree-view" href="'+urlPath+'graph_view.php?action=tree">'+treeView+'</a></li>'
                +'<li><a class="hyperLink" id="tab-graphs-list-view" href="'+urlPath+'graph_view.php?action=list">'+listView+'</a></li>'
                +'<li><a class="hyperLink" id="tab-graphs-pre-view" href="'+urlPath+'graph_view.php?action=preview">'+previewView+'</a></li>'
                +'<li><hr class="menu"></li>';

        }else {
            compact_tab_menu_content += '<li><a class="hyperLink" href="'+ $(this).attr('href') +'">'+ $('.text_'+id).text() +'</a></li>';
        }
    });
    compact_tab_menu_content += '</ul></li></ul>';

    // -- compact mode -- redesign console navigation area
    if($('.cactiConsoleNavigationArea').length !== 0) {

        if($('#compact_tab_menu').length === 0 && $('#compact_user_menu').length === 0) {

            // -- split the navigation area into 3 parts to separate tabs, settings and user menus
            let menu = $('#menu').detach();
            $('.cactiConsoleNavigationArea').empty().prepend(
                '<div class="compact" id="compact_tab_menu"></div>'
                +'<div class="compact" id="compact_user_menu"></div>'
            );
            $(menu).insertAfter('#compact_tab_menu');

            // -- duplicate the console tab items and add them to the console navigation area for compact mode
            if ($.trim($('compact_tab_menu').html()) === '') {
                $(compact_tab_menu_content).appendTo('#compact_tab_menu');
            }

            // -- compact mode --
            /* user menus are close to the button, so we have write the items the other way around */
            let compact_user_menu_content =
                '<ul class="nav">'
                +   '<li class="menuitem" id="menu_user_help">'
                +       '<a class="menu_parent active" href="#">'
                +           '<i class="menu_glyph far fa-comment-alt"></i>'
                +           '<span>'+help+'</span>'
                +       '</a>'
                +       '<ul>'
                +           '<li><a class="hyperLink" href="'+urlPath+'about.php">'+aboutCacti+'</a></li>'
                +           '<li><a href="https://github.com/Cacti/cacti/issues/new" target="_blank" rel="noopener">'+reportABug+'</a></li>'
                +           '<li><hr class="menu"></li>'
                +           '<li><a href="https://github.com/Cacti/documentation/blob/develop/README.md" target="_blank" rel="noopener">'+cactiDocumentation+'</a></li>'
                +           '<li><a href="https://forums.cacti.net/" target="_blank" rel="noopener">'+cactiCommunityForum+'</a></li>'
                +           '<li><hr class="menu"></li>'
                +           '<li><a href="https://github.com/cacti" target="_blank" rel="noopener">'+cactiProjectPage+'</a></li>'
                +           '<li><a href="https://www.cacti.net" target="_blank" rel="noopener">'+cactiHome+'</></a></li>'
                +       '</ul>'
                +   '</li>'
                +   '<li class="menuitem" id="menu_user_action">'
                +       '<a class="menu_parent active" href="#">'
                +           '<i class="menu_glyph far fa-user"></i>'
                +           '<span>'+ $('.loggedInAs').text() +'</span>'
                +       '</a>'
                +       '<ul>'
                +           '<li><a href="/cacti/cacti/logout.php">'+logout+'</a></li>'
                +           '<li><hr class="menu"></li>'
                +           '<li><a href="#" class="toggleGuiMode">'+standardGraphicalUserInterface+'</a></li>'
                +           '<li><a href="#" class="toggleColorMode">'+(midWinter_Color_Mode === 'light' ? darkColorMode : lightColorMode)+'</a></li>'
                +           '<li><a href="#" class="toggleColorModeAuto">'+(midWinter_Color_Mode_Auto === 'on' ? ignorePreferredColorTheme : usePreferredColorTheme)+'</a></li>'
                +           '<li><hr class="menu"></li>'
                +           '<li><a href="/cacti/cacti/auth_changepassword.php" style="">'+changePassword+'</a></li>'
                +           '<li><a class="hyperLink" href="/cacti/cacti/auth_profile.php?action=edit">'+editProfile+'</a></li>'
                +       '</ul>'
                +   '</li>'
                +'</ul>';
            $(compact_user_menu_content).appendTo('#compact_user_menu');
        }
    }


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

    $('.toggleGuiMode').unbind().click(toggleGuiMode);
    $('.toggleColorMode').unbind().click(toggleColorMode);
    $('.toggleColorModeAuto').unbind().click(toggleColorModeAuto);
}

function setupDefaultElements() {
    var pageName = basename($(location).attr('pathname'));
    var hostTimer = false;
    var clickTimeout = false;
    var hostOpen = false;

    // ensure that filter table and 1st navBar will stay on top
    if($('#filterTableOnTop').length !== 0 ) $('#filterTableOnTop').remove();

    if($(".filterTable").length !== 0) {
        $('<div id="filterTableOnTop">').prependTo('#navigation_right');
        $(".filterTable:first").closest('div').detach().prependTo('#filterTableOnTop');
        $(".break:first").detach().appendTo('#filterTableOnTop');
        $(".navBarNavigation:first").detach().appendTo('#filterTableOnTop');
        $( "#filterTableOnTop").addClass('sticky');
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

    $('input#filter, input#rfilter').addClass('ui-state-default ui-corner-all');

    $('input[type="text"], input[type="password"], input[type="checkbox"], textarea').not('image').addClass('ui-state-default ui-corner-all');

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

	$('select.colordropdown').each(function() {
		$(this).select2({
			templateResult: formatColorItem,
			templateSelection: formatColorSelection
		});
	});

    $('select').not('.colordropdown').each(function() {
        $(this).select2();
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

function initStorageItem(name, default_value) {
    let storage = Storages.localStorage;
    if (storage.isSet(name) === false) {
        storage.set(name, default_value);
    }
    return storage.get(name);
}

function setDocumentAttribute(name, value) {
    document.documentElement.setAttribute('data-'+name, value);
}

function toggleGuiMode() {
    let storage = Storages.localStorage;
    let midWinter_GUI_Mode = storage.get('midWinter_GUI_Mode');

    midWinter_GUI_Mode = (midWinter_GUI_Mode === 'standard') ? 'compact' : 'standard';
    storage.set('midWinter_GUI_Mode', midWinter_GUI_Mode);

    setDocumentAttribute('theme-mode', midWinter_GUI_Mode);
    $(window).trigger('resize');
}

function toggleColorMode() {
    let storage = Storages.localStorage;
    let midWinter_Color_Mode = storage.get('midWinter_Color_Mode');
    let midWinter_Color_Mode_Auto = storage.get('midWinter_Color_Mode_Auto');

    if(midWinter_Color_Mode_Auto !== 'on') {
        midWinter_Color_Mode = (midWinter_Color_Mode === 'dark') ? 'light' : 'dark';
        storage.set('midWinter_Color_Mode', midWinter_Color_Mode);
        $('.toggleColorMode').text(midWinter_Color_Mode === 'dark' ? lightColorMode : darkColorMode);

        document.documentElement.classList.add('color-theme-in-transition')
        setDocumentAttribute('theme-color', midWinter_Color_Mode)
        window.setTimeout(function () {
            document.documentElement.classList.remove('color-theme-in-transition')
        }, 1000)
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

function setThemeColor() {
    let storage = Storages.localStorage;

    if(storage.get('midWinter_Color_Mode_Auto') === 'on') {
        $('.toggleColorMode').hide(0);
        detectSystemColorSetup();
    }else {
        $('.toggleColorMode').show(0);
        setDocumentAttribute('theme-color', storage.get('midWinter_Color_Mode'));
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

    console.log('document: ' + document_color_mode + ', requested: ' + color_mode);
    if (document_color_mode !== color_mode) {
        document.documentElement.classList.add('color-theme-in-transition')
        setDocumentAttribute('theme-color', color_mode)
        window.setTimeout(function() {
            document.documentElement.classList.remove('color-theme-in-transition')
        }, 1000)
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

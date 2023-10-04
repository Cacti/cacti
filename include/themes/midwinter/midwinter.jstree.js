var nodes = [];
var search_to = false;


function checkTreeForLogout() {
    html = $('#mdw_tree_content').html();
    found = html.indexOf('Login to Cacti');
    if (found >= 0) {
        document.location = 'logout.php';
    }
}

function openNodes() {
    if (nodes.length > 0) {
        var deffereds = $.Deferred(function (def) { def.resolve(); });
        var lastNode  = nodes[nodes.length-1];

        for (var j = 0; j <= nodes.length-1; j++) {
            deffereds = (function(name, deferreds) {
                return deferreds.pipe(function () {
                    return $.Deferred(function(def) {
                        id = $('a[id^='+name+']').first().attr('id');
                        if (lastNode == name) {
                            $('#mdw_tree_content').jstree('select_node', id, function() {
                            def.resolve();
                            });
                        } else {
                            $('#mdw_tree_content').jstree('open_node', id, function() {
                                $('.mdw-ConsoleNavigationArea').css('overflow-y', 'auto');
                                def.resolve();
                            });
                        }
                    });
                });
            })(nodes[j], deffereds);
        }
    }
}

function setupTree() {
    $('#mdw_tree_content').each(function (data) {
        var id = $(this).attr('id');

        $(this)
            .on('init.jstree', function () {
                if (nodes.length > 0) {
                    $('#mdw_tree_content').jstree().clear_state();
                }
            })
            .on('before_open.jstree', function () {
                checkTreeForLogout();
            })
            .on('after_open.jstree', function () {
                responsiveResizeGraphs();
            })
            .on('after_close.jstree', function () {
                responsiveResizeGraphs();
            })
            .on('select_node.jstree', function (e, data) {
                if (data.node.id) {
                    if (data.node.id.search('tree_anchor') >= 0) {
                        href = $('#' + data.node.id).find('a:first').attr('href');
                    } else {
                        href = $('#' + data.node.id).find('a:first').attr('href');
                    }

                    origHref = href;

                    if (typeof href !== 'undefined') {
                        href = href.replace('action=tree', 'action=tree_content');
                        href = href + '&hyper=true';
                        $('.cactiGraphContentArea').hide();
                        loadUrl({url:href});
                    }
                    node = data.node.id;
                }
            })
            .jstree({
                'types': {
                    'tree': {
                        icon: urlPath + 'images/tree.png',
                        max_children: 0
                    },
                    'device': {
                        icon: urlPath + 'images/server.png',
                        max_children: 0
                    },
                    'graph': {
                        icon: urlPath + 'images/server_chart_curve.png',
                        max_children: 0
                    },
                    'graph_template': {
                        icon: urlPath + 'images/server_chart.png',
                        max_children: 0
                    },
                    'data_query': {
                        icon: urlPath + 'images/server_dataquery.png',
                        max_children: 0
                    },
                    'site': {
                        icon: urlPath + 'images/site.png',
                        max_children: 0
                    },
                    'location': {
                        icon: urlPath + 'images/location.png',
                        max_children: 0
                    },
                    'host_template': {
                        icon: urlPath + 'images/server_device_template.png',
                        max_children: 0
                    },
                    'graph_templates': {
                        icon: urlPath + 'images/server_graph_template.png',
                        max_children: 0
                    }
                },
                'core': {
                    'data': {
                        'url': urlPath + 'graph_view.php?action=get_node&tree_id=0',
                        'data': function (node) {
                            return {'id': node.id}
                        }
                    },
                    'animation': 0,
                    'check_callback': false
                },
                'themes': {
                    'name': 'default',
                    'responsive': true,
                    'url': true,
                    'dots': false
                },
                'state': {'key': 'graph_tree_history'},
                'search': {
                    'case_sensitive': false,
                    'show_only_matches': true,
                    'ajax': {'url': urlPath + 'graph_view.php?action=ajax_search'}
                },
                'plugins': ['types', 'state', 'wholerow', 'search']
            });
    });

    $('#mdw_tree_search').keyup(function() {
        if(search_to) { clearTimeout(search_to); }
        search_to = setTimeout(function() {
            var v = $('#mdw_tree_search_input').val();
            if (v.length >= 3) {
                $('#mdw_tree_content').jstree('search', v, false);
            }else {
                $('#mdw_tree_content').jstree('search', '', false);
            }
        }, 500);
    });
};
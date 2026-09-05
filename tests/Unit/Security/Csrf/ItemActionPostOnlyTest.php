<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Item, tree, RRA, and ordering actions change state and were reachable by GET,
 * so a cross-origin request could delete or reorder objects using the victim's
 * authentication state.
 *
 * The guard and the links have to move together: adding the actions to
 * $bad_actions while a page still links them by GET breaks the delete it is
 * meant to protect. These two assertions fail in opposite directions, so
 * neither half can land alone.
 */

/**
 * Every GET reachable action whose handler mutates state. Read-only actions are
 * deliberately absent: item_edit, readme, changelog, latest, avail and the
 * *_confirm dialogs render a page and change nothing.
 *
 * @return array<int, string> Action names.
 */
function guarded_actions() {
    return array(
        'item_remove', 'item_moveup', 'item_movedown',
        'item_remove_gsv', 'item_remove_dssv',
        'item_moveup_gsv', 'item_moveup_dssv',
        'item_movedown_gsv', 'item_movedown_dssv',
        'delete_node', 'gt_remove', 'query_remove', 'remove', 'change_leaf',
        'moveup', 'movedown',
        'tree_up', 'tree_down', 'move_page_up', 'move_page_down',
        'rrd_add', 'rrd_remove',
    );
}

/**
 * A pattern matching any guarded action, longest name first so that
 * item_remove_gsv is not matched as item_remove.
 *
 * @return string A regex alternation.
 */
function guarded_action_pattern() {
    $actions = guarded_actions();

    usort($actions, function ($a, $b) {
        return strlen($b) - strlen($a);
    });

    return 'action=(?:' . implode('|', array_map('preg_quote', $actions)) . ')(?![a-z_])';
}

function item_action_files() {
    $root  = dirname(__DIR__, 4);
    $found = array();

    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));

    foreach ($it as $file) {
        $path = $file->getPathname();

        if (substr($path, -4) !== '.php') {
            continue;
        }

        if (strpos($path, '/include/vendor/') !== false || strpos($path, '/tests/') !== false) {
            continue;
        }

        $src = file_get_contents($path);

        if ($src !== false && preg_match('/' . guarded_action_pattern() . '/', $src)) {
            $found[] = substr($path, strlen($root) + 1);
        }
    }

    sort($found);

    return $found;
}

test('every state changing action is refused without a POST token', function () {
    $src = file_get_contents(dirname(__DIR__, 4) . '/include/global.php');

    expect($src)->not->toBeFalse();

    $start = strpos($src, '$bad_actions = array(');

    expect($start)->not->toBeFalse();

    $list = substr($src, $start, strpos($src, ');', $start) - $start);

    $missing = array();

    foreach (guarded_actions() as $action) {
        if (strpos($list, "'" . $action . "'") === false) {
            $missing[] = $action;
        }
    }

    expect($missing)->toBe(array());
});

test('no page still links a state changing action by GET', function () {
    $files = item_action_files();

    expect($files)->not->toBe(array());

    $unconverted = array();

    foreach ($files as $file) {
        $src = file_get_contents(dirname(__DIR__, 4) . '/' . $file);

        /* Anchors are emitted across two lines in places, so collapse the file
           before pairing an href with the class that posts it. */
        $flat = preg_replace('/\s+/', ' ', $src);

		if (preg_match_all('/<a\b[^>]*?' . guarded_action_pattern() . '[^>]*>/', $flat, $matches)) {
			foreach ($matches[0] as $anchor) {
				$has_crawlable_action = preg_match('/href=["\'][^"\']*' . guarded_action_pattern() . '/i', $anchor) === 1;
				$uses_global_handler = strpos($anchor, 'cactiPostAction') !== false;
				$uses_page_handler   = strpos($anchor, 'remover') !== false
					&& strpos($src, "$('.remover').on('click'") !== false
					&& strpos($src, 'cactiPreparePostRequestFromUrl') !== false;

				if ($has_crawlable_action || (!$uses_global_handler && !$uses_page_handler)) {
					$unconverted[] = $file;
                    break;
                }
            }
        }
    }

    expect($unconverted)->toBe(array());
});

test('the cactiPostAction handler posts the token and refuses another origin', function () {
    $js = file_get_contents(dirname(__DIR__, 4) . '/include/layout.js');

    expect($js)->not->toBeFalse();

    /* The class has to be bound on its own, not only through the ajaxAnchors
       selector, because several tagged anchors carry none of the classes that
       selector names. */
    expect($js)->toContain(".cactiPostAction')")
        ->and($js)->toContain('submitPageUsingPost')
        ->and($js)->toContain('cactiPreparePostRequestFromUrl');

    $start = strpos($js, 'function cactiPreparePostRequest(');

    expect($start)->not->toBeFalse();

    $body = substr($js, $start, 600);

    expect($body)->toContain('csrfMagicToken')
        ->and($body)->toContain('__csrf_magic')
        ->and($body)->toContain('Refusing to send a CSRF token to a different origin');
});

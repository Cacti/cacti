<?php
/* Cacti general-header regression probe. This fixture runs only in the test CLI. */
if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}
chdir(dirname(__DIR__, 3));
$_SERVER['REQUEST_URI'] = '/auth_profile.php';
$_SERVER['PHP_SELF'] = '/auth_profile.php';
$_SERVER['SERVER_NAME'] = 'localhost';
require './include/global.php';
require_once './lib/html.php';
require_once './lib/html_graph.php';
require_once './lib/html_tree.php';
$_SESSION['sess_user_id'] = 1;
$user_menu = $menu;
general_header();
bottom_footer();

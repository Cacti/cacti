<?php

include_once("./include/global.php");

$page = db_fetch_row_prepared('SELECT
	id, title, style, contentfile
	FROM external_links AS el
	WHERE id = ?', array(get_filter_request_var('id')));

if (!sizeof($page)) {
	print 'FATAL: Page is not defined.';
} else {
	global $link_nav;

	if (is_realm_allowed($page['id']+10000)) {
		unset ($refresh);

		if ($page['style'] == 'TAB') {
			$link_nav['link.php:']['title']   = $page['title'];
			$link_nav['link.php:']['mapping'] = '';
			general_header();
		}else{
			$link_nav['link.php:']['title']   = $page['title'];
			$link_nav['link.php:']['mapping'] = 'index.php:';
			top_header();
		}

		if (preg_match('/^((((ht|f)tp(s?))\:\/\/){1}\S+)/i', $page['contentfile'])) {
			print '<iframe id="content" src="' . $page['contentfile'] . '" frameborder="0"></iframe>';
		} else {
			print '<div id="content">';

			$file = $config['base_path'] . "/include/content/" . $page['contentfile'];

			if (file_exists($file)) {
				include_once($file);
			} else {
				print '<h1>The file \'' . $page['contentfile'] . '\' does not exist!!</h1>';
			}

			print '</div>';
		}
	}else{
		print 'ERROR: Page is not authorized.';
	}

	?>
	<script type='text/javascript'>
	$(function() {
		resizeWindow();
		$(window).resize(function() {
			resizeWindow();
		});
	});

	function resizeWindow() {
		height = parseInt($('#navigation_right').height());
		width  = $('#main').width();
		$('#content').css({'height':height+'px', 'width':width, 'margin-top':'-5px'});
	}
	</script>
	<?php

	bottom_footer();
}

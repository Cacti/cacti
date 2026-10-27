<?php
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
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

namespace Cacti\Controller;

use Cacti\View\TemplateRenderer;
use Symfony\Component\HttpFoundation\Response;
use Twig\Markup;

final class AboutController {
	public function __construct(private TemplateRenderer $renderer) {
	}

	public function show(): Response {
		// Rich translations are escaped before fixed links are substituted.
		return new Response($this->renderer->render('pages/about.html.twig', [
			'version'              => CACTI_VERSION_TEXT_FULL,
			'description'          => __('Cacti is designed to be a complete graphing solution based on the RRDtool Time Series Database (TSDB) and Graphing solution. Its goal is to make the Network Administrator\'s job easier by taking care of all the important details necessary to create meaningful Graphs.'),
			'website'              => new Markup(sprintf(html_escape(__('Please see the official %sCacti website%s for information on how to use Cacti, get support, and updates.')), '<a href="http://www.cacti.net/?version=' . html_escape_attr(CACTI_VERSION) . '" target="_blank">', '</a>'), 'UTF-8'),
			'active_title'         => __('Active Developers'),
			'active_description'   => __('Developers working on Cacti, its Architecture, Documentation and Future Releases.'),
			'mentions_title'       => __('Honorable Mentions'),
			'mentions_description' => __('Contributors to Documentation, QA, Packaging, the Forums and our YouTube page.'),
			'emeritus_title'       => __('Emeritus Members'),
			'emeritus_description' => __('Members of the original Cacti Group that have since moved on in their careers.  We continue to wish them the best.'),
			'thanks_title'         => __('Thanks'),
			'thanks_links'         => new Markup(sprintf(html_escape(__('A very special thanks to %sTobi Oetiker%s, the creator of %sRRDtool%s and the very popular %sMRTG%s.')), '<a href="http://tobi.oetiker.ch/" target="_blank"><strong>', '</strong></a>', '<a href="http://www.rrdtool.org/" target="_blank">', '</a>', '<a href="http://www.rrdtool.org" target="_blank">', '</a>'), 'UTF-8'),
			'users_title'          => __('The users of Cacti'),
			'users_description'    => __('Especially anyone who has taken the time to create an issue report, or otherwise help fix a Cacti related problems. Also to anyone who has contributed to supporting Cacti.'),
			'license_title'        => __('License'),
			'license_intro'        => __('Cacti is licensed under the GNU GPL:'),
			'license_terms'        => __('This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.'),
			'license_warranty'     => __('This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.'),
		]));
	}
}

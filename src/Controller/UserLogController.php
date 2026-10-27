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

use Symfony\Component\HttpFoundation\Response;

final class UserLogController extends TableController {
	/** @param list<array<string, mixed>> $rows */
	public function show(array $rows, string $search, array $realms): Response {
		return $this->render('pages/userlog.html.twig', $rows, $search, [
			'removed'  => __('(User Removed)'),
			'na'       => __('N/A'),
			'failed'   => __('Failed'),
			'password' => __('Success - Password'),
			'changed'  => __('Success - Password Change'),
			'token'    => __('Success - Token'),
		], ['realms' => $realms]);
	}
}

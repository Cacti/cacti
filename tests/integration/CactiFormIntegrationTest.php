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
 */

require_once CACTI_PATH_LIBRARY . '/CactiForm.php';

it('hydrates and renders through the real Cacti form helpers', function () {
	$form = new CactiForm([
		'name' => [
			'method'        => 'textbox',
			'friendly_name' => 'Name',
			'description'   => 'Site name',
			'value'         => '|arg1:name|',
			'max_length'    => '100',
		],
	]);

	ob_start();
	$form->withValues(['name' => 'Datacenter'])->withoutFormTag()->render();
	$html = ob_get_clean();

	expect($html)->toContain("name='name'")
		->and($html)->toContain("value='Datacenter'")
		->and($html)->not->toContain("<form class='cactiForm'");
});

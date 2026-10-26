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

namespace Cacti\Vdef;

require_once __DIR__ . '/VdefRepository.php';

final class VdefResolver {
	public function __construct(private readonly VdefRepository $repository) {
	}

	public function resolve(int $vdef_id, bool $display = false) : string {
		$vdef_items = $this->repository->items($vdef_id);

		$i           = 0;
		$vdef_string = '';

		if ($vdef_items !== false && count($vdef_items)) {
			foreach ($vdef_items as $vdef_item) {
				if ($i > 0) {
					$vdef_string .= ($display ? ', ' : ',');
				}

				if ($vdef_item['type'] == 5) {
					$current_vdef_id = $vdef_item['value'];
					$vdef_string .= $this->resolve($current_vdef_id, $display);
				} else {
					$vdef_string .= $this->repository->itemName($vdef_item['id']);
				}

				$i++;
			}
		}

		return $vdef_string;
	}
}

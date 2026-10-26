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

namespace Cacti\Sites;

use Closure;

require_once __DIR__ . '/SiteRepository.php';

final class SiteService {
	/**
	 * @param Closure(): void    $changed
	 * @param Closure(int): void $message
	 */
	public function __construct(private readonly SiteRepository $repository, private readonly Closure $changed, private readonly Closure $message) {
	}

	public function save(array $values) : mixed {
		$id = $this->repository->save($values);

		if ($id) {
			($this->message)(1);

			if (empty($values['id'])) {
				($this->changed)();
			}
		} else {
			($this->message)(2);
		}

		return $id;
	}

	public function enable(array $ids) : void {
		$this->setDisabled($ids, false);
	}

	public function disable(array $ids) : void {
		$this->setDisabled($ids, true);
	}

	private function setDisabled(array $ids, bool $disabled) : void {
		if (count($ids)) {
			foreach ($ids as $id) {
				$this->repository->setDisabled($id, $disabled);
			}

			($this->changed)();
		}
	}

	/** @param Closure(): void $missingTemplate */
	public function duplicate(mixed $template_id, string $name, Closure $missingTemplate) : void {
		if (!is_array($template_id)) {
			$template_id = [$template_id];
		}

		foreach ($template_id as $id) {
			$site = $this->repository->find($id);

			if (is_array($site) && count($site)) {
				$save = ['id' => 0];

				foreach ($site as $column => $value) {
					if ($column == 'id') {
						continue;
					}

					if ($column == 'name') {
						$save['name'] = str_replace('<site>', $value, $name);
					} else {
						$save[$column] = $value;
					}
				}

				$site_id = $this->repository->save($save);
				($this->message)($site_id > 0 ? 1 : 2);
			} else {
				$missingTemplate();
			}
		}

		($this->changed)();
	}

	public function delete(array $ids) : void {
		if (count($ids)) {
			$this->repository->delete($ids);
			($this->changed)();
		}
	}
}

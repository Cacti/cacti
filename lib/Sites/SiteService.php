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

/**
 * Site management actions behind sites.php: save, enable, disable,
 * duplicate and delete, with the legacy messages and change timestamps.
 */
final class SiteService {
	/**
	 * @param SiteRepository     $repository Site storage.
	 * @param Closure(): void    $changed    Records that sites changed (the time_last_change_site settings).
	 * @param Closure(int): void $message    Raises a Cacti message by id.
	 */
	public function __construct(private readonly SiteRepository $repository, private readonly Closure $changed, private readonly Closure $message) {
	}

	/**
	 * Save a site and raise message 1 on success or 2 on failure.
	 *
	 * Only a newly created site records a change.
	 *
	 * @param array<string, mixed> $values Column values; an empty id creates a site.
	 *
	 * @return mixed The saved site id, or a falsy value on failure.
	 */
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

	/**
	 * Enable the selected sites.
	 *
	 * @param array<mixed> $ids The site ids.
	 *
	 * @return void
	 */
	public function enable(array $ids) : void {
		$this->setDisabled($ids, false);
	}

	/**
	 * Disable the selected sites.
	 *
	 * @param array<mixed> $ids The site ids.
	 *
	 * @return void
	 */
	public function disable(array $ids) : void {
		$this->setDisabled($ids, true);
	}

	/**
	 * Apply one disabled state to every selected site and record a single change.
	 *
	 * @param array<mixed> $ids      The site ids; an empty list changes nothing.
	 * @param bool         $disabled True to disable, false to enable.
	 *
	 * @return void
	 */
	private function setDisabled(array $ids, bool $disabled) : void {
		if (count($ids)) {
			foreach ($ids as $id) {
				$this->repository->setDisabled($id, $disabled);
			}

			($this->changed)();
		}
	}

	/**
	 * Copy each template site, substituting <site> in the new name.
	 *
	 * @param mixed           $template_id     One site id or a list of them.
	 * @param string          $name            The new name; <site> is replaced with the template's name.
	 * @param Closure(): void $missingTemplate Called for each template id that does not exist.
	 *
	 * @return void
	 */
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

	/**
	 * Delete the selected sites and record a change.
	 *
	 * @param array<mixed> $ids The site ids; an empty list changes nothing.
	 *
	 * @return void
	 */
	public function delete(array $ids) : void {
		if (count($ids)) {
			$this->repository->delete($ids);
			($this->changed)();
		}
	}
}

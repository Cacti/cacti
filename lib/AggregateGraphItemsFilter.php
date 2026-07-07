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

/**
 * Request state for the aggregate graph items tab.
 */
final class AggregateGraphItemsFilter {
	public function __construct(
		private int $aggregateGraphLocalId,
		private int $rowSelection,
		private int $rowsPerPage,
		private int $page,
		private string $rfilter,
		private string $matching,
		private string $sortColumn,
		private string $sortDirection,
		private string $localGraphIds
	) {
	}

	public static function fromRequest(int $defaultRows) : self {
		$row_selection = (int) grv('rows');

		return new self(
			(int) grv('id'),
			$row_selection,
			$row_selection == -1 ? $defaultRows : $row_selection,
			(int) grv('page'),
			(string) grv('rfilter'),
			(string) grv('matching'),
			(string) grv('sort_column'),
			(string) grv('sort_direction'),
			(string) grv('local_graph_ids')
		);
	}

	public function aggregateGraphLocalId() : int {
		return $this->aggregateGraphLocalId;
	}

	public function rowSelection() : int {
		return $this->rowSelection;
	}

	public function rowsPerPage() : int {
		return $this->rowsPerPage;
	}

	public function page() : int {
		return $this->page;
	}

	public function rfilter() : string {
		return $this->rfilter;
	}

	public function matching() : string {
		return $this->matching;
	}

	public function sortColumn() : string {
		return $this->sortColumn;
	}

	public function sortDirection() : string {
		return $this->sortDirection;
	}

	public function localGraphIds() : string {
		return $this->localGraphIds;
	}

	public function hasRfilter() : bool {
		return $this->rfilter != '';
	}

	public function hasLocalGraphIds() : bool {
		return $this->localGraphIds != '';
	}

	public function hasRegexFilter() : bool {
		return validate_is_regex($this->rfilter) === true;
	}

	public function matchingOnly() : bool {
		return $this->matching != 'false';
	}

	public function matchingChecked() : bool {
		return $this->matching == 'on' || $this->matching == 'true';
	}
}

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

final class PackageListFilter {
	private string $filter;
	private int $rows;
	private int $page;
	private string $sortColumn;
	private string $sortDirection;

	private function __construct(string $filter, int $rows, int $page, string $sortColumn, string $sortDirection) {
		$this->filter        = $filter;
		$this->rows          = $rows;
		$this->page          = max(1, $page);
		$this->sortColumn    = $sortColumn;
		$this->sortDirection = $sortDirection;
	}

	public static function fromRequest(int $defaultRows) : self {
		$rows = (string) grv('rows');

		return new self(
			(string) grv('filter'),
			$rows === '-1' ? $defaultRows : (int) $rows,
			(int) grv('page'),
			(string) grv('sort_column'),
			(string) grv('sort_direction')
		);
	}

	public function filter() : string {
		return $this->filter;
	}

	public function hasFilter() : bool {
		return $this->filter !== '';
	}

	public function rows() : int {
		return $this->rows;
	}

	public function page() : int {
		return $this->page;
	}

	public function sortColumn() : string {
		return $this->sortColumn;
	}

	public function sortDirection() : string {
		return $this->sortDirection;
	}

	public function offset() : int {
		return $this->rows * ($this->page - 1);
	}

	public function paginationUrl(string $path) : string {
		if ($this->filter === '') {
			return $path;
		}

		return $path . '?' . http_build_query(['filter' => $this->filter], '', '&', PHP_QUERY_RFC3986);
	}

	public static function selectedIdsFromPost() : array {
		return self::selectedIdsFromInput($_POST);
	}

	public static function selectedIdsFromInput(array $input) : array {
		$selected = [];

		foreach ($input as $var => $value) {
			if (preg_match('/^chk_([0-9]+)$/', (string) $var, $matches)) {
				$selected[] = (int) $matches[1];
			}
		}

		return $selected;
	}

	public static function encodeSelectedIds(array $ids) : string {
		return json_encode(array_values(array_map('intval', $ids)), JSON_THROW_ON_ERROR);
	}

	public static function decodeSelectedIds(mixed $ids) : array|false {
		if (!is_string($ids) || $ids === '') {
			return false;
		}

		try {
			$decoded = json_decode(stripslashes($ids), true, 512, JSON_THROW_ON_ERROR);
		} catch (JsonException) {
			return false;
		}

		if (!is_array($decoded)) {
			return false;
		}

		$selected = [];

		foreach ($decoded as $id) {
			if (!is_int($id) && !(is_string($id) && preg_match('/^[0-9]+$/', $id))) {
				return false;
			}

			$selected[] = (int) $id;
		}

		return $selected;
	}
}

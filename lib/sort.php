<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2023 The Cacti Group                                 |
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

/* usort_data_query_index - attempts to sort a data query index either numerically
     or alphabetically depending on which seems best. it also tries to strip out
     extra characters before sorting to improve accuracy when sorting things like
     switch ifNames, etc
   @arg $a - the first string to compare
   @arg $b - the second string to compare
   @returns - '1' if $a is greater than $b, '-1' if $a is less than $b, or '0' if
     $b is equal to $b */
function usort_data_query_index($a, $b) {
	/* split strings to be compared into chunks
	 * that shall be compared separately,
	 * e.g. for gi0/1, gi0/2, ... */
	$arr_a = explode('/', $a);
	$arr_b = explode('/', $b);

	for ($i=0; $i<min(cacti_count($arr_a), cacti_count($arr_b)); $i++) {
		if ((is_numeric($arr_a[$i])) && (is_numeric($arr_b[$i]))) {
			if (intval($arr_a[$i]) > intval($arr_b[$i])) {
				return 1;
			} elseif (intval($arr_a[$i]) < intval($arr_b[$i])) {
				return -1;
			}
		} else {
			$cmp = strcmp(strval($arr_a[$i]), strval($arr_b[$i]));

			if (($cmp > 0) || ($cmp < 0)) {
				return $cmp;
			}
		}
	}

	if (cacti_count($arr_a) < cacti_count($arr_b)) {
		return 1;
	} elseif (cacti_count($arr_a) > cacti_count($arr_b)) {
		return -1;
	}

	return 0;
}

/* usort_numeric - sorts two values numerically (ie. 1, 34, 36, 76)
   @arg $a - the first string to compare
   @arg $b - the second string to compare
   @returns - '1' if $a is greater than $b, '-1' if $a is less than $b, or '0' if
     $b is equal to $b */
function usort_numeric($a, $b) {
	if (intval($a) > intval($b)) {
		return 1;
	} elseif (intval($a) < intval($b)) {
		return -1;
	} else {
		return 0;
	}
}

/* usort_alphabetic - sorts two values alphabetically (ie. ab, by, ef, xy)
   @arg $a - the first string to compare
   @arg $b - the second string to compare
   @returns - '1' if $a is greater than $b, '-1' if $a is less than $b, or '0' if
     $b is equal to $b */
function usort_alphabetic($a, $b) {
	return strcmp($a, $b);
}

/* usort_natural - sorts two values naturally (ie. ab1, ab2, ab7, ab10, ab20)
   @arg $a - the first string to compare
   @arg $b - the second string to compare
   @returns - '1' if $a is greater than $b, '-1' if $a is less than $b, or '0' if
     $b is equal to $b */
function usort_natural($a, $b) {
	return strnatcmp($a, $b);
}

/* sort_by_subkey - takes the list of templates and perform a final sort
   @returns - (array) an array of sorted templates */
function sort_by_subkey(&$array, $subkey, $sort = SORT_ASC) {
	$keys = array();

    foreach ($array as $subarray) {
        $keys[] = $subarray[$subkey];
    }

    array_multisort($keys, $sort, $array);
}


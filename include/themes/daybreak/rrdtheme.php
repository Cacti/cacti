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
  | Cacti: The Complete RRDTool-based Graphing Solution                     |
  +-------------------------------------------------------------------------+
  | This code is designed, written, and maintained by the Cacti Group. See  |
  | about.php and/or the AUTHORS file for specific developer information.   |
  +-------------------------------------------------------------------------+
  | http://www.cacti.net/                                                   |
  +-------------------------------------------------------------------------+
*/

// fonts and borders come from the parent theme; only the palette differs
include __DIR__ . '/../midwinter/rrdtheme.php';

$rrdcolors_light = [
	'back'   => 'F7F8FB',
	'canvas' => 'FFFFFF',
	'shadea' => 'D9DEE8',
	'shadeb' => 'E6EAF1',
	'grid'   => 'E3E7EF00',
	'mgrid'  => 'D9DEE8',
	'font'   => '1B2333',
	'axis'   => '4A5468',
	'arrow'  => '4A5468',
	'frame'  => 'D9DEE8',
];

$rrdcolors_dark = [
	'back'   => '0F1115',
	'canvas' => '14171C',
	'shadea' => '08090B',
	'shadeb' => '2E333C',
	'grid'   => '14171C00',
	'mgrid'  => '2A2F37',
	'font'   => 'D7DBE0',
	'axis'   => '4A515C',
	'arrow'  => '4A515C',
	'frame'  => '2E333C',
];

// without a color mode cookie, graphs use the light palette
$rrdcolors = $rrdcolors_light;

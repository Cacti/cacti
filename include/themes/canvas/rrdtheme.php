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
	'back'   => 'F6F8FA',
	'canvas' => 'FFFFFF',
	'shadea' => 'D1D9E0',
	'shadeb' => 'E6EBF1',
	'grid'   => 'E6EBF100',
	'mgrid'  => 'D1D9E0',
	'font'   => '1F2328',
	'axis'   => '59636E',
	'arrow'  => '59636E',
	'frame'  => 'D1D9E0',
];

$rrdcolors_dark = [
	'back'   => '010409',
	'canvas' => '0D1117',
	'shadea' => '010409',
	'shadeb' => '3D444D',
	'grid'   => '0D111700',
	'mgrid'  => '3D444D',
	'font'   => 'F0F6FC',
	'axis'   => '656C76',
	'arrow'  => '656C76',
	'frame'  => '3D444D',
];

// without a color mode cookie, graphs use the light palette
$rrdcolors = $rrdcolors_light;

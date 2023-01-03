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
  | Cacti: The Complete RRDTool-based Graphing Solution                     |
  +-------------------------------------------------------------------------+
  | This code is designed, written, and maintained by the Cacti Group. See  |
  | about.php and/or the AUTHORS file for specific developer information.   |
  +-------------------------------------------------------------------------+
  | http://www.cacti.net/                                                   |
  +-------------------------------------------------------------------------+
*/

$rrdcolors['back']   = 'F3F3F3';
$rrdcolors['canvas'] = 'FDFDFD';
$rrdcolors['shadea'] = 'CBCBCB';
$rrdcolors['shadeb'] = '999999';
$rrdcolors['grid']   = 'C4C4C4';
$rrdcolors['mgrid']  = '1A1C1C';
$rrdcolors['font']   = '000000';
$rrdcolors['axis']   = '2C4D43';
$rrdcolors['arrow']  = '2C4D43';
$rrdcolors['frame']  = '2C4D43';

# RRDtool graph fonts in RRDtool 1.2+
$rrdfonts['title']['font']     = 'Arial';
$rrdfonts['title']['size']     = '11';
$rrdfonts['axis']['font']      = 'Arial';
$rrdfonts['axis']['size']      = '8';
$rrdfonts['legend']['font']    = 'Courier';
$rrdfonts['legend']['size']    = '8';
$rrdfonts['unit']['font']      = 'Arial';
$rrdfonts['unit']['size']      = '8';
$rrdfonts['watermark']['font'] = 'Arial';
$rrdfonts['watermark']['size'] = '6';

# Only supported in RRDtool 1.4+
$rrdborder = 1;

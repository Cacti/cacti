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

// ruleid: cacti-reflected-xss
print '<script>DownloadStart(' . get_request_var('selection') . ');</script>';

// ok: cacti-reflected-xss
print '<script>DownloadStart(' . cacti_js_encode(get_request_var('selection')) . ');</script>';

// ruleid: cacti-reflected-xss
print '<script>DownloadStart(' . gnrv('selection') . ');</script>';

// ok: cacti-reflected-xss
print '<script>DownloadStart(' . cacti_js_encode(gnrv('selection')) . ');</script>';

<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2003 Ian Berry                                            |
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
 | cacti: a php-based graphing solution                                    |
 +-------------------------------------------------------------------------+
 | Most of this code has been designed, written and is maintained by       |
 | Ian Berry. See about.php for specific developer credit. Any questions   |
 | or comments regarding this code should be directed to:                  |
 | - iberry@raxnet.net                                                     |
 +-------------------------------------------------------------------------+
 | - raXnet - http://www.raxnet.net/                                       |
 +-------------------------------------------------------------------------+
*/
?>
			<br>
		</td>
	</tr>
</table>

</body>
</html>

<script type="text/javascript">
<!--
function SelectAll() {
  for (var i = 0; i < document.chk.elements.length; i++) {
    if( document.chk.elements[i].name.substr( 0, 4 ) == 'chk_') {
      document.chk.elements[i].checked =
        !(document.chk.elements[i].checked);
    }
  }
}
//-->

</script>

<?php
/* we use this session var to store field values for when a save fails,
this way we can restore the field's previous values. we reset it here, because
they only need to be stored for a single page */
session_unregister("sess_field_values");
?>

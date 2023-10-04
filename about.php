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

include('./include/auth.php');

top_header();

html_start_box(__('About Cacti'), '100%', '', '3', 'center', '');

?>

<tr class='tableHeader'>
	<td class='tableSubHeaderColumn' colspan='2'>
		<font class='textSubHeaderDark'><?php print CACTI_VERSION_TEXT_FULL; ?></font>
	</td>
</tr>
<tr>
	<td valign='top' class='odd' class='textArea'>
		<div style='float:right;'>
			<a href='http://www.cacti.net/' target='_blank'><img class='right' alt='cactiHome' src='images/cacti_about_logo.gif'></a>
		</div>

		<p><?php print __('Cacti is designed to be a complete graphing solution based on the RRDtool Time Series Database (TSDB) and Graphing solution. Its goal is to make the Network Administrator\'s job easier by taking care of all the important details necessary to create meaningful Graphs.'); ?></p>

		<p><?php print __('Please see the official %sCacti website%s for information on how to use Cacti, get support, and updates.', '<a href="http://www.cacti.net/?version=' . CACTI_VERSION . '" target="_blank">', '</a>'); ?></p>

		<div class='flexContainer'>
		<div class='flexChild' style='min-width:300px;'>
		<p><strong><?php print __('Active Developers'); ?></strong></p>
		<p style='min-height:45px'><?php print __('Developers working on Cacti, its Architecture, Documentation and Future Releases.'); ?></strong></p>
		<ul type='disc'>
			<li>Larry Adams <i>(TheWitness)</i></li>
			<li>Mark Brugnoli-Vinten <i>(netniV)</i></li>
			<li>Jimmy Conner <i>(cigamit)</i></li>
			<li>Petr Macek <i>(xmacan)</i></li>
			<li>Andreas Braun <i>(browniebraun)</i></li>
			<li>Thomas Urban <i>(phalek)</i></li>
			<li>Jing Chen <i>(ddb4github)</i></li>
		</ul>
		</div>
		<div class='flexChild' style='min-width:300px;'>
		<p><strong><?php print __('Honorable Mentions'); ?></strong></p>
		<p style='min-height:45px'><?php print __('Contributors to Docuemntation, QA, Packaging, the Forums and our YouTube page.'); ?></strong></p>
		<ul type='disc'>
			<li>Sean Mancini <i>(bmfmancini)</i></li>
			<li>J.P. Pasnak, CD <i>(Linegod)</i></strong></li>
			<li>Chris Bell (Windows) <i>(BSOD2600)</i></li>
			<li>Paul Gevers (Debian) <i>(paulgevers)</i></strong></li>
			<li>Morten Stevens (Fedora) <i>(mortenstevens)</i></strong></li>
		</ul>
		</div>
		<div class='flexChild' style='min-width:300px;'>
		<p><strong><?php print __('Emeritus Members'); ?></strong></p>
		<p style='min-height:45px'><?php print __('Members of the original Cacti Group that have since moved on in their careers.  We continue to wish them the best.'); ?></strong></p>
		<ul type='disc'>
			<li>Ian Berry <i>(raX)</i></li>
			<li>Reinhard Scheck <i>(gandalf)</i></li>
			<li>Tony Roman <i>(rony)</i></strong></li>
			<li>Andy Blyler <i>(ablyler)</i></strong></li>
		</ul>
		</div>
		</div>

		<p><strong><?php print __('Thanks'); ?></a></strong><br>
		<ul type='disc'>
			<li>
				<?php print __('A very special thanks to %sTobi Oetiker%s, the creator of %sRRDtool%s and the very popular %sMRTG%s.', '<a href="http://tobi.oetiker.ch/" target="_blank"><strong>', '</strong></a>', '<a href="http://www.rrdtool.org/" target="_blank">', '</a>', '<a href="http://www.rrdtool.org" target="_blank">', '</a>'); ?>
			</li>
			<li>
				<strong><?php print __('The users of Cacti'); ?></strong>
				<?php print __('Especially anyone who has taken the time to create an issue report, or otherwise help fix a Cacti related problems. Also to anyone who has contributed to supporting Cacti.'); ?>
			</li>
		</ul>
		</p>

		<strong><?php print __('License'); ?></strong><br>

		<p><?php print __('Cacti is licensed under the GNU GPL:'); ?></p>

		<p><tt><?php print __('This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.');?></tt></p>

		<p><tt><?php print __('This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.'); ?></tt></p>
	</td>
</tr>

<?php
html_end_box();

bottom_footer();

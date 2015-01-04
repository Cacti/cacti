<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2015 The Cacti Group                                 |
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

include('./include/auth.php');

top_header();

html_start_box('<strong>About Cacti</strong>', '100%', '', '3', 'center', '');
?>

<tr class='tableHeader'>
	<td class='tableSubHeaderColumn' colspan="2">
		<font class='textSubHeaderDark'>Version <?php print $config["cacti_version"];?></font>
	</td>
</tr>
<tr>
	<td valign="top" class="odd" class="textArea">
		<a href="http://www.cacti.net/"><img align="right" src="images/cacti_about_logo.gif" border="0" alt="raXnet"></a>

		Cacti is designed to be a complete graphing solution based on the RRDTool's framework. Its goal is to make a
		network administrator's job easier by taking care of all the necessary details necessary to create
		meaningful graphs.

		<p>Please see the offical <a href="http://www.cacti.net/?version=<?php print $config['cacti_version'];?>">Cacti website</a> for information, support, and updates.</p>

		<p><strong>Current Cacti Developers</strong><br>
		<ul type="disc">
			<li><strong>Ian Berry</strong> (raX) was the original creator of Cacti which was first released to the world in 2001. He remained the sole
				developer for over two years, writing code, supporting users, and keeping the project active. Over the years, Ian has moved from
				hist role as a fledgling developer to starting multiple companies focusing on building unique internet based services for customers worldwide.</li>
			<li><strong>Larry Adams</strong> (TheWitness) joined the Cacti Group in June of 2004 right before the major 0.8.6 release. He helped bring the new poller
				architecture to life by providing ideas, writing code, and managing an active group of beta testers. Larry, has since moved on to creating unique Cacti based solutions for HPC customers across the globe.  Over the years, Larry has developed dozens of Cacti add-ons, and now leads a group of Cacti development and support personel where he works today.</li>
			<li><strong>Tony Roman</strong> (rony) joined the Cacti Group in October of 2004 offering years of programming and system administration
				experience to the project.  Tony has gone on to holding key roles with his employers and uses Cacti every day at his current employer.</li>
			<li><strong>J.P. Pasnak, CD</strong> (Linegod) joined the Cacti Group in August of 2005.  He is contributing to releases and maintains the <a href="http://docs.cacti.net/">Documentation System</a>  Jeff is one of our true leaders on the team.  He plays a diminished role, but when he speaks, the rest of the team listen, if for nothing else, but to prep the pallete for another swig of hard cider and shephards pie!
			</li>
			<li><strong>Jimmy Conner</strong> (cigamit) joined the Cacti Group in January of 2006.  He is currently in charge of the Plug-in Architecture, the new events system and maintaining many of the popular plugins.
			</li>
			<li><strong>Reinhard Scheck</strong> (gandalf) joined the Cacti team in June of 2007.  Reinhard is focusing on howto's and graph presentation as well as being the 'European Arm' of the Cacti Group.  He is our RRDtool expert on the team.
			</li>
			<li><strong>Andreas Braun</strong> (browniebraun) joined the Cacti Group in July of 2009. As the second european developer Andreas is focusing on internationalization of Cacti.  Andreas has developed many unique plugins for his current employer, and enjoys interacting with the team.
			</li>
		</ul>
		</p>

		<p><strong>Thanks</a></strong><br>
		<ul type="disc">
			<li>A very special thanks to <a href="http://tobi.oetiker.ch/"><strong>Tobi Oetiker</strong></a>,
				the creator of <a href="http://www.rrdtool.org/">RRDTool</a> and the very popular
				<a href="http://www.rrdtool.org">MRTG</a>.</li>
			<li><strong>The users of Cacti</strong>! Especially anyone who has taken the time to create a bug report, or otherwise
				help fix a Cacti-related problem. Also to anyone who has purchased an item from a developers amazon.com
				wishlist or donated money to the project.</li>
		</ul>
		</p>

		<p><strong>License</strong><br>

		<p>Cacti is licensed under the GNU GPL:</p>

		<p><tt>This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.</tt></p>

		<p><tt>This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.</tt></p>
	</td>
</tr>

<?php
html_end_box();

bottom_footer();



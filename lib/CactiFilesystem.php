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

use Symfony\Component\Filesystem\Filesystem;

final class CactiFilesystem {
	private Filesystem $filesystem;

	public function __construct(?Filesystem $filesystem = null) {
		$this->filesystem = $filesystem ?? new Filesystem();
	}

	/**
	 * Replace a complete local file atomically.
	 *
	 * @throws Symfony\Component\Filesystem\Exception\IOExceptionInterface
	 */
	public function writeFile(string $filename, string $contents) : void {
		$this->filesystem->dumpFile($filename, $contents);
	}
}

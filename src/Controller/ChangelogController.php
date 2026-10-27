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
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

namespace Cacti\Controller;

use Cacti\View\TemplateRenderer;
use Symfony\Component\HttpFoundation\Response;

final class ChangelogController {
	public function __construct(private TemplateRenderer $renderer, private string $changelogPath) {
	}

	public function show(bool $full): Response {
		$lines = file($this->changelogPath, FILE_IGNORE_NEW_LINES);

		if ($lines === false) {
			throw new \RuntimeException('Unable to read Cacti changelog');
		}

		return new Response($this->renderer->render('pages/changelog.html.twig', [
			'versions' => $this->versions($lines),
			'full'     => $full,
		]));
	}

	/** @param list<string> $lines
	 * @return list<array{label: string, groups: array<string, list<array{desc: string, issue: string}>>}>
	 */
	public function versions(array $lines): array {
		$versions = [];
		$version  = '';
		$groups   = [];

		// The first two lines contain the file title and its separator.
		foreach (array_slice($lines, 2) as $line) {
			$line = trim($line);

			if ($line === '') {
				continue;
			}

			if ($line[0] === '-' || $line[0] === '*') {
				if (preg_match('/^-(issue|feature|security)(?:#(\d+))?: (.*)$/i', $line, $match)) {
					$type            = strtolower($match[1]);
					$type            = $type === 'security' ? ' security' : $type;
					$groups[$type][] = ['desc' => $match[3], 'issue' => $match[2]];
				}

				continue;
			}

			if ($version !== '') {
				ksort($groups);
				$versions[$version] = $groups;

				if (count($versions) === 5) {
					break;
				}
			}
			$version = $line;
			$groups  = [];
		}

		if (count($versions) < 5 && $version !== '') {
			ksort($groups);
			$versions[$version] = $groups;
		}
		krsort($versions);
		$result = [];

		foreach ($versions as $version => $groups) {
			$result[] = ['label' => __('Version %s', $version), 'groups' => $groups];
		}

		return $result;
	}
}

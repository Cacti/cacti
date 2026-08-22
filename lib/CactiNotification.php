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
 */

use Symfony\Component\Notifier\Notification\Notification;

/**
 * A notification carrying optional channel-specific Cacti delivery data.
 */
final class CactiNotification extends Notification {
	/** @var array<string, mixed> */
	private array $options;

	/**
	 * @param list<string>         $channels
	 * @param array<string, mixed> $options
	 */
	public function __construct(string $subject, string $content, array $channels = ['email'], array $options = []) {
		parent::__construct($subject, $channels);

		parent::content($content);

		if (isset($options['importance'])) {
			parent::importance((string) $options['importance']);
		}

		$this->options = $options;
	}

	public function subject(string $subject) : static {
		$this->immutable();
	}

	public function content(string $content) : static {
		$this->immutable();
	}

	public function importance(string $importance) : static {
		$this->immutable();
	}

	public function importanceFromLogLevelName(string $level) : static {
		$this->immutable();
	}

	public function emoji(string $emoji) : static {
		$this->immutable();
	}

	public function exception(Throwable $exception) : static {
		$this->immutable();
	}

	/**
	 * @param list<string> $channels
	 */
	public function channels(array $channels) : static {
		$this->immutable();
	}

	/**
	 * @return array<string, mixed>
	 */
	public function getOptions(string $channel) : array {
		$options = $this->options[$channel] ?? [];

		return is_array($options) ? $options : [];
	}

	private function immutable() : never {
		throw new LogicException('Cacti notifications are immutable after construction.');
	}
}

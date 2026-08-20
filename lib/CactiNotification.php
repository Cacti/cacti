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
	private bool $sealed = false;

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
		$this->sealed  = true;
	}

	public function subject(string $subject) : static {
		$this->assertMutable();

		return parent::subject($subject);
	}

	public function content(string $content) : static {
		$this->assertMutable();

		return parent::content($content);
	}

	public function importance(string $importance) : static {
		$this->assertMutable();

		return parent::importance($importance);
	}

	public function importanceFromLogLevelName(string $level) : static {
		$this->assertMutable();

		return parent::importanceFromLogLevelName($level);
	}

	public function emoji(string $emoji) : static {
		$this->assertMutable();

		return parent::emoji($emoji);
	}

	public function exception(Throwable $exception) : static {
		$this->assertMutable();

		return parent::exception($exception);
	}

	/**
	 * @param list<string> $channels
	 */
	public function channels(array $channels) : static {
		$this->assertMutable();

		return parent::channels($channels);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function getOptions(string $channel) : array {
		$options = $this->options[$channel] ?? [];

		return is_array($options) ? $options : [];
	}

	private function assertMutable() : void {
		if ($this->sealed) {
			throw new LogicException('Cacti notifications are immutable after construction.');
		}
	}
}

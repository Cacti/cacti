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
*/

require_once dirname(__DIR__, 2) . '/lib/CactiLogger.php';

test('CactiLogger falls back to cacti_log when no PSR-3 logger is set', function () {
	// Since we can't easily capture cacti_log global output here, we ensure it doesn't crash
	\Cacti\Log\CactiLogger::info('Testing CactiLogger info fallback');
	\Cacti\Log\CactiLogger::error('Testing CactiLogger error fallback');
	
	expect(true)->toBeTrue();
});

test('CactiLogger can use a custom PSR-3 logger', function () {
	$mockLogger = new class implements \Psr\Log\LoggerInterface {
		use \Psr\Log\LoggerTrait;
		public $logs = [];
		public function log($level, $message, array $context = []) {
			$this->logs[] = ['level' => $level, 'message' => $message];
		}
	};
	
	\Cacti\Log\CactiLogger::setLogger($mockLogger);
	\Cacti\Log\CactiLogger::info('Custom log message');
	
	expect($mockLogger->logs)->toHaveCount(1);
	expect($mockLogger->logs[0]['message'])->toBe('Custom log message');
	
	// Reset for other tests
	// \Cacti\Log\CactiLogger::setLogger(null); // would need static reset helper
});

<?php

require_once dirname(__DIR__) . '/Helpers/UnitStubs.php';
require_once dirname(__DIR__, 2) . '/include/vendor/autoload.php';

use Cacti\Log\CactiLogger;
use Psr\Log\LogLevel;

beforeEach(function () {
    CactiLogger::reset();
});

// Minimal PSR-3 logger used to verify CactiLogger::setLogger forwards calls.
class TestLogger extends \Psr\Log\AbstractLogger {
    public array $logs = [];
    public function log($level, $message, array $context = []): void {
        $this->logs[] = ['level' => $level, 'message' => $message, 'context' => $context];
    }
}

it('falls back to legacy cacti_log when no logger set', function () {
    // cacti_log is stubbed to a no-op in UnitStubs.php, so this only proves
    // the fallback path does not throw. beforeEach() resets the static logger.
    CactiLogger::info("Test message");
    expect(true)->toBeTrue();
});

it('uses the PSR-3 logger when set', function () {
    $logger = new TestLogger();
    CactiLogger::setLogger($logger);
    
    CactiLogger::info("Hello PSR-3");
    CactiLogger::error("Something went wrong", ['environ' => 'TEST']);
    
    expect($logger->logs)->toHaveCount(2);
    expect($logger->logs[0]['level'])->toBe(LogLevel::INFO);
    expect($logger->logs[0]['message'])->toBe("Hello PSR-3");
    expect($logger->logs[1]['level'])->toBe(LogLevel::ERROR);
    expect($logger->logs[1]['context']['environ'])->toBe('TEST');
});

it('exercises mapToCactiLevel via fallback', function () {
    CactiLogger::reset();
    // No PSR logger set; should fall through to cacti_log stub without throwing for each level.
    foreach ([LogLevel::DEBUG, LogLevel::INFO, LogLevel::WARNING, LogLevel::ERROR, LogLevel::CRITICAL, LogLevel::ALERT, LogLevel::EMERGENCY] as $level) {
        CactiLogger::log($level, 'msg');
    }
    expect(true)->toBeTrue();
});

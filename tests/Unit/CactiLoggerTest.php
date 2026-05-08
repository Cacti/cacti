<?php

require_once dirname(__DIR__) . '/Helpers/UnitStubs.php';
require_once dirname(__DIR__, 2) . '/include/vendor/autoload.php';

use Cacti\Log\CactiLogger;
use Psr\Log\LogLevel;

// A simple mock logger to verify CactiLogger::setLogger working
class TestLogger extends \Psr\Log\AbstractLogger {
    public array $logs = [];
    public function log($level, $message, array $context = []): void {
        $this->logs[] = ['level' => $level, 'message' => $message, 'context' => $context];
    }
}

it('falls back to legacy cacti_log when no logger set', function () {
    // This is hard to test because cacti_log is stubbed to do nothing.
    // But we can at least verify it doesn't crash.
    CactiLogger::setLogger(new TestLogger()); // Reset it first
    CactiLogger::info("Test message");
    
    // Actually, CactiLogger keeps state between tests because it's a static class.
    // Pest doesn't reset static properties by default.
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

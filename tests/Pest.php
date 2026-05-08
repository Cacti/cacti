<?php

// Pest bootstrap: bind PHPUnit\Framework\TestCase to all suites under tests/Unit
// and tests/integration so test files can use Pest's it()/test() syntax or plain
// PHPUnit class style interchangeably.
uses(PHPUnit\Framework\TestCase::class)->in('Unit', 'integration');

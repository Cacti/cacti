<?php

declare(strict_types=1);

use Pest\Arch\Concerns\Architectable;
use Pest\PendingCalls\TestCall;
use Pest\Plugin;
use Pest\Support\HigherOrderTapProxy;

Plugin::uses(Architectable::class);

if (! function_exists('arch')) {
    /**
     * Adds the given closure as an architecture test. The first
     * argument is the test description; the second argument
     * is a closure that contains the test expectations.
     */
    function arch(?string $description = null, ?Closure $closure = null): TestCall
    {
        $test = test(...func_get_args()); // @phpstan-ignore-line

        assert(! $test instanceof HigherOrderTapProxy);

        return $test->group('arch');
    }
}

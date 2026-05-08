<?php

declare(strict_types=1);

namespace Pest\Arch\Expectations;

use Pest\Arch\Contracts\ArchExpectation;
use Pest\Expectation;

/**
 * @internal
 */
final class ToUseNothing
{
    /**
     * Creates an "ToUseNothing" expectation.
     *
     * @param  Expectation<array<int, string>|string>  $expectation
     */
    public static function make(Expectation $expectation): ArchExpectation
    {
        return ToOnlyUse::make($expectation, []);
    }
}

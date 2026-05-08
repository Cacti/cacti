<?php

declare(strict_types=1);

namespace Pest\Arch\Expectations;

use Pest\Arch\GroupArchExpectation;
use Pest\Arch\SingleArchExpectation;
use Pest\Expectation;

/**
 * @internal
 */
final class ToBeUsedIn
{
    /**
     * Creates an "ToBeUsedIn" expectation.
     *
     * @param  array<int, string>|string  $targets
     * @param  Expectation<array<int, string>|string>  $expectation
     */
    public static function make(Expectation $expectation, array|string $targets): GroupArchExpectation
    {
        // @phpstan-ignore-next-line
        assert(is_string($expectation->value) || is_array($expectation->value));

        $targets = is_string($targets) ? [$targets] : $targets;

        return GroupArchExpectation::fromExpectations(
            $expectation,
            array_map(
                // @phpstan-ignore-next-line
                static fn ($target): SingleArchExpectation => ToUse::make(expect($target), $expectation->value), $targets
            )
        );
    }
}

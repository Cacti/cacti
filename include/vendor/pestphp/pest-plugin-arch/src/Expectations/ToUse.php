<?php

declare(strict_types=1);

namespace Pest\Arch\Expectations;

use Pest\Arch\Blueprint;
use Pest\Arch\Collections\Dependencies;
use Pest\Arch\Options\LayerOptions;
use Pest\Arch\SingleArchExpectation;
use Pest\Arch\ValueObjects\Targets;
use Pest\Expectation;
use PHPUnit\Framework\ExpectationFailedException;

/**
 * @internal
 */
final class ToUse
{
    /**
     * Creates an "ToUse" expectation.
     *
     * @param  Expectation<array<int, string>|string>  $expectation
     * @param  array<int, string>|string  $dependencies
     */
    public static function make(Expectation $expectation, array|string $dependencies): SingleArchExpectation
    {
        // @phpstan-ignore-next-line
        assert(is_string($expectation->value) || is_array($expectation->value));

        $blueprint = Blueprint::make(
            Targets::fromExpectation($expectation),
            Dependencies::fromExpectationInput($dependencies),
        );

        return SingleArchExpectation::fromExpectation(
            $expectation,
            static function (LayerOptions $options) use ($blueprint): void {
                $blueprint->expectToUse(
                    $options,
                    static fn (string $value, string $dependOn) => throw new ExpectationFailedException(
                        "Expecting '{$value}' to use '{$dependOn}'.",
                    ),
                );
            },
        );
    }
}

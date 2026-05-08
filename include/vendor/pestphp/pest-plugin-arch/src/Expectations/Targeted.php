<?php

declare(strict_types=1);

namespace Pest\Arch\Expectations;

use Pest\Arch\Blueprint;
use Pest\Arch\Collections\Dependencies;
use Pest\Arch\Exceptions\ArchExpectationFailedException;
use Pest\Arch\Options\LayerOptions;
use Pest\Arch\SingleArchExpectation;
use Pest\Arch\ValueObjects\Targets;
use Pest\Arch\ValueObjects\Violation;
use Pest\Expectation;

/**
 * @internal
 */
final class Targeted
{
    /**
     * Creates an "ToBe" expectation.
     *
     * @param  Expectation<array<int, string>|string>  $expectation
     */
    public static function make(
        Expectation $expectation,
        callable $callback,
        string $what,
        callable $line,
    ): SingleArchExpectation {
        // @phpstan-ignore-next-line
        assert(is_string($expectation->value) || is_array($expectation->value));
        /** @var Expectation<array<int, string>|string> $expectation */
        $blueprint = Blueprint::make(
            Targets::fromExpectation($expectation),
            Dependencies::fromExpectationInput([]),
        );

        return SingleArchExpectation::fromExpectation(
            $expectation,
            static function (LayerOptions $options) use ($callback, $blueprint, $what, $line): void {
                $blueprint->targeted(
                    $callback,
                    $options,
                    static fn (Violation $violation) => throw new ArchExpectationFailedException(
                        $violation,
                        sprintf(
                            "Expecting '%s' %s.",
                            $violation->path,
                            $what
                        ),
                    ),
                    $line,
                );
            },
        );
    }
}

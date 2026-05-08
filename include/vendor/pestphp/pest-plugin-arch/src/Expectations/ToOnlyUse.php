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
use PHPUnit\Framework\ExpectationFailedException;

/**
 * @internal
 */
final class ToOnlyUse
{
    /**
     * Creates an "ToOnlyUse" expectation.
     *
     * @param  Expectation<array<int, string>|string>  $expectation
     * @param  array<int, string>|string  $dependencies
     */
    public static function make(Expectation $expectation, array|string $dependencies): SingleArchExpectation
    {
        // @phpstan-ignore-next-line
        assert(is_string($expectation->value) || is_array($expectation->value));
        /** @var Expectation<array<int, string>|string> $expectation */
        $blueprint = Blueprint::make(
            Targets::fromExpectation($expectation),
            Dependencies::fromExpectationInput($dependencies),
        );

        return SingleArchExpectation::fromExpectation($expectation, static function (LayerOptions $options) use ($blueprint): void {
            $blueprint->expectToOnlyUse(
                $options, static function (string $value, string $dependOn, string $notAllowedDependOn, ?Violation $violation): void {
                    $message = $dependOn === ''
                        ? "Expecting '{$value}' to use nothing. However, it uses '{$notAllowedDependOn}'."
                        : "Expecting '{$value}' to only use '{$dependOn}'. However, it also uses '{$notAllowedDependOn}'.";

                    if (! $violation instanceof Violation) {
                        throw new ExpectationFailedException($message);
                    }

                    throw new ArchExpectationFailedException($violation, $message);
                });
        });
    }
}

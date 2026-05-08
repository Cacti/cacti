<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\Sets;

use Pest\Mutate\Contracts\Mutator;
use Pest\Mutate\Contracts\MutatorSet;
use Pest\Mutate\Mutators\Concerns\HasName;
use Pest\Mutate\Mutators\Equality\EqualToIdentical;
use Pest\Mutate\Mutators\Equality\EqualToNotEqual;
use Pest\Mutate\Mutators\Equality\GreaterOrEqualToGreater;
use Pest\Mutate\Mutators\Equality\GreaterOrEqualToSmaller;
use Pest\Mutate\Mutators\Equality\GreaterToGreaterOrEqual;
use Pest\Mutate\Mutators\Equality\GreaterToSmallerOrEqual;
use Pest\Mutate\Mutators\Equality\IdenticalToEqual;
use Pest\Mutate\Mutators\Equality\IdenticalToNotIdentical;
use Pest\Mutate\Mutators\Equality\NotEqualToEqual;
use Pest\Mutate\Mutators\Equality\NotEqualToNotIdentical;
use Pest\Mutate\Mutators\Equality\NotIdenticalToIdentical;
use Pest\Mutate\Mutators\Equality\NotIdenticalToNotEqual;
use Pest\Mutate\Mutators\Equality\SmallerOrEqualToGreater;
use Pest\Mutate\Mutators\Equality\SmallerOrEqualToSmaller;
use Pest\Mutate\Mutators\Equality\SmallerToGreaterOrEqual;
use Pest\Mutate\Mutators\Equality\SmallerToSmallerOrEqual;
use Pest\Mutate\Mutators\Equality\SpaceshipSwitchSides;

class EqualitySet implements MutatorSet
{
    use HasName;

    /**
     * {@inheritDoc}
     */
    public static function mutators(): array
    {
        return [
            ...self::defaultMutators(),
            IdenticalToEqual::class,
            NotIdenticalToNotEqual::class,
        ];
    }

    /**
     * @return array<int, class-string<Mutator>>
     */
    public static function defaultMutators(): array
    {
        return [
            EqualToNotEqual::class,
            NotEqualToEqual::class,
            IdenticalToNotIdentical::class,
            NotIdenticalToIdentical::class,
            GreaterToGreaterOrEqual::class,
            GreaterToSmallerOrEqual::class,
            GreaterOrEqualToGreater::class,
            GreaterOrEqualToSmaller::class,
            SmallerToGreaterOrEqual::class,
            SmallerToSmallerOrEqual::class,
            SmallerOrEqualToGreater::class,
            SmallerOrEqualToSmaller::class,
            EqualToIdentical::class,
            NotEqualToNotIdentical::class,
            SpaceshipSwitchSides::class,
        ];
    }
}

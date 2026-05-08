<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\Sets;

use Pest\Mutate\Contracts\Mutator;
use Pest\Mutate\Contracts\MutatorSet;
use Pest\Mutate\Mutators\Concerns\HasName;
use Pest\Mutate\Mutators\Visibility\ConstantProtectedToPrivate;
use Pest\Mutate\Mutators\Visibility\ConstantPublicToProtected;
use Pest\Mutate\Mutators\Visibility\FunctionProtectedToPrivate;
use Pest\Mutate\Mutators\Visibility\FunctionPublicToProtected;
use Pest\Mutate\Mutators\Visibility\PropertyProtectedToPrivate;
use Pest\Mutate\Mutators\Visibility\PropertyPublicToProtected;

class VisibilitySet implements MutatorSet
{
    use HasName;

    /**
     * @return array<int, class-string<Mutator>>
     */
    public static function defaultMutators(): array
    {
        return [
            // ...
        ];
    }

    /**
     * {@inheritDoc}
     */
    public static function mutators(): array
    {
        return [
            ConstantPublicToProtected::class,
            ConstantProtectedToPrivate::class,
            FunctionPublicToProtected::class,
            FunctionProtectedToPrivate::class,
            PropertyPublicToProtected::class,
            PropertyProtectedToPrivate::class,
        ];
    }
}

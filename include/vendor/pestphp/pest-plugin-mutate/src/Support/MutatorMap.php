<?php

declare(strict_types=1);

namespace Pest\Mutate\Support;

use Pest\Mutate\Contracts\Mutator;
use Pest\Mutate\Mutators\Sets\ArithmeticSet;
use Pest\Mutate\Mutators\Sets\ArraySet;
use Pest\Mutate\Mutators\Sets\AssignmentSet;
use Pest\Mutate\Mutators\Sets\CastingSet;
use Pest\Mutate\Mutators\Sets\ControlStructuresSet;
use Pest\Mutate\Mutators\Sets\EqualitySet;
use Pest\Mutate\Mutators\Sets\LaravelSet;
use Pest\Mutate\Mutators\Sets\LogicalSet;
use Pest\Mutate\Mutators\Sets\MathSet;
use Pest\Mutate\Mutators\Sets\NumberSet;
use Pest\Mutate\Mutators\Sets\RemovalSet;
use Pest\Mutate\Mutators\Sets\ReturnSet;
use Pest\Mutate\Mutators\Sets\StringSet;
use Pest\Mutate\Mutators\Sets\VisibilitySet;

class MutatorMap
{
    /**
     * @var ?array<string, array<class-string<Mutator>>>
     */
    public static ?array $map = null;

    /**
     * @return array<string, array<class-string<Mutator>>>
     */
    public static function get(): array
    {
        if (self::$map !== null) {
            return self::$map;
        }

        $mutators = [
            ...ArithmeticSet::mutators(),
            ...ArraySet::mutators(),
            ...AssignmentSet::mutators(),
            ...CastingSet::mutators(),
            ...ControlStructuresSet::mutators(),
            ...EqualitySet::mutators(),
            ...LogicalSet::mutators(),
            ...LaravelSet::mutators(),
            ...MathSet::mutators(),
            ...NumberSet::mutators(),
            ...RemovalSet::mutators(),
            ...ReturnSet::mutators(),
            ...StringSet::mutators(),
            ...VisibilitySet::mutators(),
        ];

        $map = [];
        foreach ($mutators as $mutator) {
            foreach ($mutator::nodesToHandle() as $node) {
                $map[$node][] = $mutator;
            }
        }

        return self::$map = $map;
    }
}

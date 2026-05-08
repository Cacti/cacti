<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\Sets;

use Pest\Mutate\Contracts\MutatorSet;
use Pest\Mutate\Mutators\Array\ArrayKeyFirstToArrayKeyLast;
use Pest\Mutate\Mutators\Array\ArrayKeyLastToArrayKeyFirst;
use Pest\Mutate\Mutators\Array\ArrayPopToArrayShift;
use Pest\Mutate\Mutators\Array\ArrayShiftToArrayPop;
use Pest\Mutate\Mutators\Array\UnwrapArrayChangeKeyCase;
use Pest\Mutate\Mutators\Array\UnwrapArrayChunk;
use Pest\Mutate\Mutators\Array\UnwrapArrayColumn;
use Pest\Mutate\Mutators\Array\UnwrapArrayCombine;
use Pest\Mutate\Mutators\Array\UnwrapArrayCountValues;
use Pest\Mutate\Mutators\Array\UnwrapArrayDiff;
use Pest\Mutate\Mutators\Array\UnwrapArrayDiffAssoc;
use Pest\Mutate\Mutators\Array\UnwrapArrayDiffKey;
use Pest\Mutate\Mutators\Array\UnwrapArrayDiffUassoc;
use Pest\Mutate\Mutators\Array\UnwrapArrayDiffUkey;
use Pest\Mutate\Mutators\Array\UnwrapArrayFilter;
use Pest\Mutate\Mutators\Array\UnwrapArrayFlip;
use Pest\Mutate\Mutators\Array\UnwrapArrayIntersect;
use Pest\Mutate\Mutators\Array\UnwrapArrayIntersectAssoc;
use Pest\Mutate\Mutators\Array\UnwrapArrayIntersectKey;
use Pest\Mutate\Mutators\Array\UnwrapArrayIntersectUassoc;
use Pest\Mutate\Mutators\Array\UnwrapArrayIntersectUkey;
use Pest\Mutate\Mutators\Array\UnwrapArrayKeys;
use Pest\Mutate\Mutators\Array\UnwrapArrayMap;
use Pest\Mutate\Mutators\Array\UnwrapArrayMerge;
use Pest\Mutate\Mutators\Array\UnwrapArrayMergeRecursive;
use Pest\Mutate\Mutators\Array\UnwrapArrayPad;
use Pest\Mutate\Mutators\Array\UnwrapArrayReduce;
use Pest\Mutate\Mutators\Array\UnwrapArrayReplace;
use Pest\Mutate\Mutators\Array\UnwrapArrayReplaceRecursive;
use Pest\Mutate\Mutators\Array\UnwrapArrayReverse;
use Pest\Mutate\Mutators\Array\UnwrapArraySlice;
use Pest\Mutate\Mutators\Array\UnwrapArraySplice;
use Pest\Mutate\Mutators\Array\UnwrapArrayUdiff;
use Pest\Mutate\Mutators\Array\UnwrapArrayUdiffAssoc;
use Pest\Mutate\Mutators\Array\UnwrapArrayUdiffUassoc;
use Pest\Mutate\Mutators\Array\UnwrapArrayUintersect;
use Pest\Mutate\Mutators\Array\UnwrapArrayUintersectAssoc;
use Pest\Mutate\Mutators\Array\UnwrapArrayUintersectUassoc;
use Pest\Mutate\Mutators\Array\UnwrapArrayUnique;
use Pest\Mutate\Mutators\Array\UnwrapArrayValues;
use Pest\Mutate\Mutators\Concerns\HasName;

class ArraySet implements MutatorSet
{
    use HasName;

    /**
     * {@inheritDoc}
     */
    public static function mutators(): array
    {
        return [
            ArrayKeyFirstToArrayKeyLast::class,
            ArrayKeyLastToArrayKeyFirst::class,
            ArrayPopToArrayShift::class,
            ArrayShiftToArrayPop::class,
            UnwrapArrayChangeKeyCase::class,
            UnwrapArrayChunk::class,
            UnwrapArrayColumn::class,
            UnwrapArrayCombine::class,
            UnwrapArrayCountValues::class,
            UnwrapArrayDiffAssoc::class,
            UnwrapArrayDiffKey::class,
            UnwrapArrayDiffUassoc::class,
            UnwrapArrayDiffUkey::class,
            UnwrapArrayDiff::class,
            UnwrapArrayFilter::class,
            UnwrapArrayFlip::class,
            UnwrapArrayIntersectAssoc::class,
            UnwrapArrayIntersectKey::class,
            UnwrapArrayIntersectUassoc::class,
            UnwrapArrayIntersectUkey::class,
            UnwrapArrayIntersect::class,
            UnwrapArrayKeys::class,
            UnwrapArrayMap::class,
            UnwrapArrayMergeRecursive::class,
            UnwrapArrayMerge::class,
            UnwrapArrayPad::class,
            UnwrapArrayReduce::class,
            UnwrapArrayReplaceRecursive::class,
            UnwrapArrayReplace::class,
            UnwrapArrayReverse::class,
            UnwrapArraySlice::class,
            UnwrapArraySplice::class,
            UnwrapArrayUdiffAssoc::class,
            UnwrapArrayUdiffUassoc::class,
            UnwrapArrayUdiff::class,
            UnwrapArrayUintersectAssoc::class,
            UnwrapArrayUintersectUassoc::class,
            UnwrapArrayUintersect::class,
            UnwrapArrayUnique::class,
            UnwrapArrayValues::class,
        ];
    }
}

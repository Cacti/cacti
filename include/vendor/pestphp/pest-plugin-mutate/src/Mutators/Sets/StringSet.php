<?php

declare(strict_types=1);

namespace Pest\Mutate\Mutators\Sets;

use Pest\Mutate\Contracts\MutatorSet;
use Pest\Mutate\Mutators\Concerns\HasName;
use Pest\Mutate\Mutators\String\ConcatRemoveLeft;
use Pest\Mutate\Mutators\String\ConcatRemoveRight;
use Pest\Mutate\Mutators\String\ConcatSwitchSides;
use Pest\Mutate\Mutators\String\EmptyStringToNotEmpty;
use Pest\Mutate\Mutators\String\NotEmptyStringToEmpty;
use Pest\Mutate\Mutators\String\StrEndsWithToStrStartsWith;
use Pest\Mutate\Mutators\String\StrStartsWithToStrEndsWith;
use Pest\Mutate\Mutators\String\UnwrapChop;
use Pest\Mutate\Mutators\String\UnwrapChunkSplit;
use Pest\Mutate\Mutators\String\UnwrapHtmlentities;
use Pest\Mutate\Mutators\String\UnwrapHtmlEntityDecode;
use Pest\Mutate\Mutators\String\UnwrapHtmlspecialchars;
use Pest\Mutate\Mutators\String\UnwrapHtmlspecialcharsDecode;
use Pest\Mutate\Mutators\String\UnwrapLcfirst;
use Pest\Mutate\Mutators\String\UnwrapLtrim;
use Pest\Mutate\Mutators\String\UnwrapMd5;
use Pest\Mutate\Mutators\String\UnwrapNl2br;
use Pest\Mutate\Mutators\String\UnwrapRtrim;
use Pest\Mutate\Mutators\String\UnwrapStripTags;
use Pest\Mutate\Mutators\String\UnwrapStrIreplace;
use Pest\Mutate\Mutators\String\UnwrapStrPad;
use Pest\Mutate\Mutators\String\UnwrapStrRepeat;
use Pest\Mutate\Mutators\String\UnwrapStrReplace;
use Pest\Mutate\Mutators\String\UnwrapStrrev;
use Pest\Mutate\Mutators\String\UnwrapStrShuffle;
use Pest\Mutate\Mutators\String\UnwrapStrtolower;
use Pest\Mutate\Mutators\String\UnwrapStrtoupper;
use Pest\Mutate\Mutators\String\UnwrapSubstr;
use Pest\Mutate\Mutators\String\UnwrapTrim;
use Pest\Mutate\Mutators\String\UnwrapUcfirst;
use Pest\Mutate\Mutators\String\UnwrapUcwords;
use Pest\Mutate\Mutators\String\UnwrapWordwrap;

class StringSet implements MutatorSet
{
    use HasName;

    /**
     * {@inheritDoc}
     */
    public static function mutators(): array
    {
        return [
            ConcatRemoveLeft::class,
            ConcatRemoveRight::class,
            ConcatSwitchSides::class,
            EmptyStringToNotEmpty::class,
            // NotEmptyStringToEmpty::class,
            StrStartsWithToStrEndsWith::class,
            StrEndsWithToStrStartsWith::class,
            UnwrapChop::class,
            UnwrapChunkSplit::class,
            UnwrapHtmlentities::class,
            UnwrapHtmlEntityDecode::class,
            UnwrapHtmlspecialchars::class,
            UnwrapHtmlspecialcharsDecode::class,
            UnwrapLcfirst::class,
            UnwrapLtrim::class,
            UnwrapMd5::class,
            UnwrapNl2br::class,
            UnwrapRtrim::class,
            UnwrapStripTags::class,
            UnwrapStrIreplace::class,
            UnwrapStrPad::class,
            UnwrapStrRepeat::class,
            UnwrapStrReplace::class,
            UnwrapStrrev::class,
            UnwrapStrShuffle::class,
            UnwrapStrtolower::class,
            UnwrapStrtoupper::class,
            UnwrapSubstr::class,
            UnwrapTrim::class,
            UnwrapUcfirst::class,
            UnwrapUcwords::class,
            UnwrapWordwrap::class,
        ];
    }
}

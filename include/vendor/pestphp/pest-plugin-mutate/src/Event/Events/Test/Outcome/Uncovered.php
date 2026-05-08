<?php

declare(strict_types=1);

namespace Pest\Mutate\Event\Events\Test\Outcome;

use Pest\Mutate\Contracts\Event;
use Pest\Mutate\MutationTest;

class Uncovered implements Event
{
    public function __construct(
        public readonly MutationTest $test,
    ) {}
}

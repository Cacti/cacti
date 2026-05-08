<?php

declare(strict_types=1);

namespace Pest\Mutate\Event\Events\TestSuite;

use Pest\Mutate\Contracts\Event;
use Pest\Mutate\MutationSuite;

class StartMutationSuite implements Event
{
    public function __construct(
        public readonly MutationSuite $mutationSuite,
    ) {}
}

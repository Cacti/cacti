<?php

declare(strict_types=1);

namespace Pest\Mutate\Subscribers;

use Pest\Mutate\Event\Events\TestSuite\FinishMutationSuite;
use Pest\Mutate\Event\Events\TestSuite\FinishMutationSuiteSubscriber;

/**
 * @internal
 */
final class TrackMutationSuiteFinish implements FinishMutationSuiteSubscriber
{
    public function notify(FinishMutationSuite $event): void
    {
        $event->mutationSuite->trackFinish();
    }
}

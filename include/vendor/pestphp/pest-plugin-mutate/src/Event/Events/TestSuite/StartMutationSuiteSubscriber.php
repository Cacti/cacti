<?php

declare(strict_types=1);

namespace Pest\Mutate\Event\Events\TestSuite;

use Pest\Mutate\Contracts\Subscriber;

interface StartMutationSuiteSubscriber extends Subscriber
{
    public function notify(StartMutationSuite $event): void;
}

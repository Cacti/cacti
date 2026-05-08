<?php

declare(strict_types=1);

namespace Pest\Mutate\Event\Events\TestSuite;

use Pest\Mutate\Contracts\Subscriber;

interface StartMutationGenerationSubscriber extends Subscriber
{
    public function notify(StartMutationGeneration $event): void;
}

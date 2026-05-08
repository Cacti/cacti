<?php

declare(strict_types=1);

namespace Pest\Mutate\Event\Events\Test\HookMethod;

use Pest\Mutate\Contracts\Subscriber;

interface BeforeFirstTestExecutedSubscriber extends Subscriber
{
    public function notify(BeforeFirstTestExecuted $event): void;
}

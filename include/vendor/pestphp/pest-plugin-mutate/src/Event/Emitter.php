<?php

declare(strict_types=1);

namespace Pest\Mutate\Event;

use Pest\Mutate\Event\Events\Test\HookMethod\BeforeFirstTestExecuted;
use Pest\Mutate\Event\Events\Test\HookMethod\BeforeFirstTestExecutedSubscriber;
use Pest\Mutate\Event\Events\Test\Outcome\Tested;
use Pest\Mutate\Event\Events\Test\Outcome\TestedSubscriber;
use Pest\Mutate\Event\Events\Test\Outcome\Timeout;
use Pest\Mutate\Event\Events\Test\Outcome\TimeoutSubscriber;
use Pest\Mutate\Event\Events\Test\Outcome\Uncovered;
use Pest\Mutate\Event\Events\Test\Outcome\UncoveredSubscriber;
use Pest\Mutate\Event\Events\Test\Outcome\Untested;
use Pest\Mutate\Event\Events\Test\Outcome\UntestedSubscriber;
use Pest\Mutate\Event\Events\TestSuite\FinishMutationGeneration;
use Pest\Mutate\Event\Events\TestSuite\FinishMutationGenerationSubscriber;
use Pest\Mutate\Event\Events\TestSuite\FinishMutationSuite;
use Pest\Mutate\Event\Events\TestSuite\FinishMutationSuiteSubscriber;
use Pest\Mutate\Event\Events\TestSuite\StartMutationGeneration;
use Pest\Mutate\Event\Events\TestSuite\StartMutationGenerationSubscriber;
use Pest\Mutate\Event\Events\TestSuite\StartMutationSuite;
use Pest\Mutate\Event\Events\TestSuite\StartMutationSuiteSubscriber;
use Pest\Mutate\MutationSuite;
use Pest\Mutate\MutationTest;
use Pest\Mutate\MutationTestCollection;

class Emitter
{
    private static ?self $instance = null;

    public static function instance(): self
    {
        if (! isset(self::$instance)) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    public function mutationTested(MutationTest $test): void
    {
        $event = new Tested($test);

        foreach (Facade::instance()->subscribers()[TestedSubscriber::class] ?? [] as $subscriber) {
            /** @var TestedSubscriber $subscriber */
            $subscriber->notify($event);
        }
    }

    public function mutationEscaped(MutationTest $test): void
    {
        $event = new Untested($test);

        foreach (Facade::instance()->subscribers()[UntestedSubscriber::class] ?? [] as $subscriber) {
            /** @var UntestedSubscriber $subscriber */
            $subscriber->notify($event);
        }
    }

    public function mutationTimedOut(MutationTest $test): void
    {
        $event = new Timeout($test);

        foreach (Facade::instance()->subscribers()[TimeoutSubscriber::class] ?? [] as $subscriber) {
            /** @var TimeoutSubscriber $subscriber */
            $subscriber->notify($event);
        }
    }

    public function mutationUncovered(MutationTest $test): void
    {
        $event = new Uncovered($test);

        foreach (Facade::instance()->subscribers()[UncoveredSubscriber::class] ?? [] as $subscriber) {
            /** @var UncoveredSubscriber $subscriber */
            $subscriber->notify($event);
        }
    }

    public function startTestCollection(MutationTestCollection $testCollection): void
    {
        $event = new BeforeFirstTestExecuted($testCollection);

        foreach (Facade::instance()->subscribers()[BeforeFirstTestExecutedSubscriber::class] ?? [] as $subscriber) {
            /** @var BeforeFirstTestExecutedSubscriber $subscriber */
            $subscriber->notify($event);
        }
    }

    public function startMutationGeneration(MutationSuite $mutationSuite): void
    {
        $event = new StartMutationGeneration($mutationSuite);

        foreach (Facade::instance()->subscribers()[StartMutationGenerationSubscriber::class] ?? [] as $subscriber) {
            /** @var StartMutationGenerationSubscriber $subscriber */
            $subscriber->notify($event);
        }
    }

    public function finishMutationGeneration(MutationSuite $mutationSuite): void
    {
        $event = new FinishMutationGeneration($mutationSuite);

        foreach (Facade::instance()->subscribers()[FinishMutationGenerationSubscriber::class] ?? [] as $subscriber) {
            /** @var FinishMutationGenerationSubscriber $subscriber */
            $subscriber->notify($event);
        }
    }

    public function startMutationSuite(MutationSuite $mutationSuite): void
    {
        $event = new StartMutationSuite($mutationSuite);

        foreach (Facade::instance()->subscribers()[StartMutationSuiteSubscriber::class] ?? [] as $subscriber) {
            /** @var StartMutationSuiteSubscriber $subscriber */
            $subscriber->notify($event);
        }
    }

    public function finishMutationSuite(MutationSuite $mutationSuite): void
    {
        $event = new FinishMutationSuite($mutationSuite);

        foreach (Facade::instance()->subscribers()[FinishMutationSuiteSubscriber::class] ?? [] as $subscriber) {
            /** @var FinishMutationSuiteSubscriber $subscriber */
            $subscriber->notify($event);
        }
    }
}

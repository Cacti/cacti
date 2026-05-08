<?php

declare(strict_types=1);

namespace Pest\Mutate\Plugins;

use NunoMaduro\Collision\Highlighter;
use Pest\Contracts\Bootstrapper;
use Pest\Contracts\Plugins\AddsOutput;
use Pest\Contracts\Plugins\Bootable;
use Pest\Contracts\Plugins\HandlesArguments;
use Pest\Exceptions\InvalidOption;
use Pest\Mutate\Boostrappers\BootPhpUnitSubscribers;
use Pest\Mutate\Boostrappers\BootSubscribers;
use Pest\Mutate\Cache\FileStore;
use Pest\Mutate\Contracts\MutationTestRunner;
use Pest\Mutate\Contracts\Printer;
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
use Pest\Mutate\Event\Facade;
use Pest\Mutate\Repositories\ConfigurationRepository;
use Pest\Mutate\Subscribers\PrinterSubscriber;
use Pest\Mutate\Support\Printers\DefaultPrinter;
use Pest\Mutate\Support\StreamWrapper;
use Pest\Plugins\Concerns\HandleArguments;
use Pest\Plugins\Parallel;
use Pest\Support\Container;
use Pest\Support\Coverage;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 *
 * @final
 */
class Mutate implements AddsOutput, Bootable, HandlesArguments
{
    use HandleArguments;

    final public const ENV_MUTATION_TESTING = 'PEST_MUTATION_TESTING';

    final public const ENV_MUTATION_FILE = 'PEST_MUTATION_FILE';

    /**
     * The Kernel bootstrappers.
     *
     * @var array<int, class-string>
     */
    private const BOOTSTRAPPERS = [
        BootPhpUnitSubscribers::class,
        BootSubscribers::class,
    ];

    /**
     * Creates a new Plugin instance.
     */
    public function __construct(
        private readonly Container $container,
        private readonly OutputInterface $output,
    ) {
        //
    }

    public function boot(): void
    {
        if (getenv(self::ENV_MUTATION_TESTING) !== false) {
            StreamWrapper::start(getenv(self::ENV_MUTATION_TESTING), (string) getenv(self::ENV_MUTATION_FILE));
        }

        $this->container->add(MutationTestRunner::class, $runner = new \Pest\Mutate\Tester\MutationTestRunner);
        $this->container->add(Printer::class, $printer = new DefaultPrinter($this->output));

        if ($_SERVER['COLLISION_PRINTER_COMPACT'] ?? false) {
            $printer->compact();
        }

        foreach (self::BOOTSTRAPPERS as $bootstrapper) {
            $bootstrapper = Container::getInstance()->get($bootstrapper);
            assert($bootstrapper instanceof Bootstrapper);

            $bootstrapper->boot();
        }

        $this->container->add(CacheInterface::class, new FileStore(dirname(__DIR__, 2).'/.temp/pest-mutate-cache'));
    }

    /**
     * {@inheritdoc}
     */
    public function handleArguments(array $arguments): array
    {
        /** @var \Pest\Mutate\Tester\MutationTestRunner $mutationTestRunner */
        $mutationTestRunner = Container::getInstance()->get(MutationTestRunner::class);

        if (! $this->hasArgument('--mutate', $arguments)) {
            if (! $mutationTestRunner->isEnabled()) {
                return $arguments;
            }
        } else {
            $arguments = $this->popArgument('--mutate', $arguments);
        }

        if (! Coverage::isAvailable() && ! isset($_SERVER['PEST_PLUGIN_INTERNAL_TEST_SUITE'])) {
            throw new InvalidOption('Mutation testing requires code coverage to be enabled. You can find more about code coverage in the Pest documentation.');
        }

        $mutationTestRunner->enable();
        $this->ensurePrinterIsRegistered();

        $coverageRequired = array_filter($arguments, fn (string $argument): bool => str_starts_with($argument, '--coverage')) !== [];
        if ($coverageRequired) {
            $mutationTestRunner->doNotDisableCodeCoverage();
        } else {
            $arguments[] = '--coverage-php='.Coverage::getPath();
        }

        $arguments = Container::getInstance()->get(ConfigurationRepository::class) // @phpstan-ignore-line
            ->cliConfiguration->fromArguments($arguments);

        $mutationTestRunner->setOriginalArguments($arguments);
        $mutationTestRunner->setStartTime(microtime(true));

        return $arguments;
    }

    public function addOutput(int $exitCode): int
    {
        /** @var MutationTestRunner $mutationTestRunner */
        $mutationTestRunner = Container::getInstance()->get(MutationTestRunner::class);

        if (Parallel::isWorker() || $exitCode !== 0 || ! $mutationTestRunner->isEnabled()) {
            return $exitCode;
        }

        if (isset($_SERVER['PEST_PLUGIN_INTERNAL_TEST_SUITE']) && $_SERVER['PEST_PLUGIN_INTERNAL_TEST_SUITE'] === 1) {
            return $exitCode;
        }

        /** @var ConfigurationRepository $configurationRepository */
        $configurationRepository = Container::getInstance()->get(ConfigurationRepository::class);
        $configuration = $configurationRepository->mergedConfiguration();

        $paths = $configurationRepository->cliConfiguration->toArray()['paths'] ?? false;

        if (! is_array($paths) && $configuration->classes === [] && ! $configuration->everything) {
            $this->output->writeln(['  <bg=red> ERROR </> Mutation testing requires the usage of the `covers()` function or `mutates()` function. Here is an example:', '']);

            $highlighter = new Highlighter;
            $content = $highlighter->highlight(<<<'PHP'
                covers(TodoController::class); // mutations will be generated only for this class
                // or mutates(TodoController::class);

                it('list todos', function () {
                    // your test here...
                });
            PHP, 1);

            $this->output->writeln($content);

            $this->output->writeln(['', '  <bg=cyan> INFO </> Optionally, you can use mutation testing with our filters:', '']);

            $this->output->writeln([
                '  <fg=gray>pest --mutate --parallel --path=app/Models</>',
                '  <fg=gray>pest --mutate --parallel --class=App\\Models</>',
                '  <fg=gray>pest --mutate --parallel --everything --covered-only</>',
            ]);

            $this->output->writeln(['', '  However, we recommend using the `covers()` function or the `mutates()` function for better performance, and keep tracking of your mutation testing score over time.']);

            return 1;
        }

        return $mutationTestRunner->run();
    }

    private function ensurePrinterIsRegistered(): void
    {
        /** @var Printer $printer */
        $printer = Container::getInstance()->get(Printer::class);

        $subscribers = [
            // Test > Hook Methods
            new class($printer) extends PrinterSubscriber implements BeforeFirstTestExecutedSubscriber
            {
                public function notify(BeforeFirstTestExecuted $event): void
                {
                    $this->printer()->printFilename($event->testCollection);
                }
            },

            // Test > Outcome
            new class($printer) extends PrinterSubscriber implements TestedSubscriber
            {
                public function notify(Tested $event): void
                {
                    $this->printer()->reportTestedMutation($event->test);
                }
            },

            new class($printer) extends PrinterSubscriber implements UntestedSubscriber
            {
                public function notify(Untested $event): void
                {
                    $this->printer()->reportUntestedMutation($event->test);
                }
            },

            new class($printer) extends PrinterSubscriber implements TimeoutSubscriber
            {
                public function notify(Timeout $event): void
                {
                    $this->printer()->reportTimedOutMutation($event->test);
                }
            },

            new class($printer) extends PrinterSubscriber implements UncoveredSubscriber
            {
                public function notify(Uncovered $event): void
                {
                    $this->printer()->reportUncoveredMutation($event->test);
                }
            },

            // MutationSuite
            new class($printer) extends PrinterSubscriber implements StartMutationGenerationSubscriber
            {
                public function notify(StartMutationGeneration $event): void
                {
                    $this->printer()->reportMutationGenerationStarted($event->mutationSuite);
                }
            },

            new class($printer) extends PrinterSubscriber implements FinishMutationGenerationSubscriber
            {
                public function notify(FinishMutationGeneration $event): void
                {
                    $this->printer()->reportMutationGenerationFinished($event->mutationSuite);
                }
            },

            new class($printer) extends PrinterSubscriber implements StartMutationSuiteSubscriber
            {
                public function notify(StartMutationSuite $event): void
                {
                    $this->printer()->reportMutationSuiteStarted($event->mutationSuite);
                }
            },

            new class($printer) extends PrinterSubscriber implements FinishMutationSuiteSubscriber
            {
                public function notify(FinishMutationSuite $event): void
                {
                    $this->printer()->reportMutationSuiteFinished($event->mutationSuite);
                }
            },
        ];

        Facade::instance()->registerSubscribers(...$subscribers);
    }
}

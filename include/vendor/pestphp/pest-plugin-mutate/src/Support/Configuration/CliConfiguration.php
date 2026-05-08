<?php

declare(strict_types=1);

namespace Pest\Mutate\Support\Configuration;

use Pest\Mutate\Cache\NullStore;
use Pest\Mutate\Options\BailOption;
use Pest\Mutate\Options\ClassOption;
use Pest\Mutate\Options\ClearCacheOption;
use Pest\Mutate\Options\CoveredOnlyOption;
use Pest\Mutate\Options\EverythingOption;
use Pest\Mutate\Options\ExceptOption;
use Pest\Mutate\Options\IgnoreMinScoreOnZeroMutationsOption;
use Pest\Mutate\Options\IgnoreOption;
use Pest\Mutate\Options\MinScoreOption;
use Pest\Mutate\Options\MutateOption;
use Pest\Mutate\Options\MutationIdOption;
use Pest\Mutate\Options\MutatorsOption;
use Pest\Mutate\Options\NoCacheOption;
use Pest\Mutate\Options\ParallelOption;
use Pest\Mutate\Options\PathOption;
use Pest\Mutate\Options\ProcessesOption;
use Pest\Mutate\Options\ProfileOption;
use Pest\Mutate\Options\RetryOption;
use Pest\Mutate\Options\StopOnUncoveredOption;
use Pest\Mutate\Options\StopOnUntestedOption;
use Pest\Support\Container;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputDefinition;

class CliConfiguration extends AbstractConfiguration
{
    private const OPTIONS = [
        MutateOption::class,
        ClassOption::class,
        MinScoreOption::class,
        IgnoreMinScoreOnZeroMutationsOption::class,
        MutatorsOption::class,
        ExceptOption::class,
        PathOption::class,
        IgnoreOption::class,
        ParallelOption::class,
        ProcessesOption::class,
        ProfileOption::class,
        StopOnUntestedOption::class,
        StopOnUncoveredOption::class,
        BailOption::class,
        RetryOption::class,
        MutationIdOption::class,
        NoCacheOption::class,
        ClearCacheOption::class,
        EverythingOption::class,
        CoveredOnlyOption::class,
    ];

    /**
     * @param  array<int, string>  $arguments
     * @return array<int, string>
     */
    public function fromArguments(array $arguments): array
    {
        $filteredArguments = ['vendor/bin/pest'];
        $inputOptions = [];
        foreach ($arguments as $key => $argument) {
            foreach (self::OPTIONS as $option) {
                if ($option::match($argument)) {
                    $filteredArguments[] = $argument;
                    $inputOptions[] = $option::inputOption();

                    if ($option::remove()) {
                        unset($arguments[$key]);
                    }
                }
            }
        }

        $arguments = array_values($arguments);

        $input = new ArgvInput($filteredArguments, new InputDefinition($inputOptions));

        if ($input->hasOption(PathOption::ARGUMENT)) {
            $this->path(explode(',', (string) $input->getOption(PathOption::ARGUMENT))); // @phpstan-ignore-line
        }

        if ($input->hasOption(IgnoreOption::ARGUMENT)) {
            $this->ignore(explode(',', (string) $input->getOption(IgnoreOption::ARGUMENT))); // @phpstan-ignore-line
        }

        if ($input->hasOption(MutatorsOption::ARGUMENT)) {
            $this->mutator(explode(',', (string) $input->getOption(MutatorsOption::ARGUMENT))); // @phpstan-ignore-line
        }

        if ($input->hasOption(ExceptOption::ARGUMENT)) {
            $this->except(explode(',', (string) $input->getOption(ExceptOption::ARGUMENT))); // @phpstan-ignore-line
        }

        if ($input->hasOption(CoveredOnlyOption::ARGUMENT)) {
            $this->coveredOnly($input->getOption(CoveredOnlyOption::ARGUMENT) !== 'false');
        }

        if ($input->hasOption(MinScoreOption::ARGUMENT)) {
            $this->min((float) $input->getOption(MinScoreOption::ARGUMENT)); // @phpstan-ignore-line
        }

        if ($input->hasOption(IgnoreMinScoreOnZeroMutationsOption::ARGUMENT)) {
            $this->ignoreMinScoreOnZeroMutations($input->getOption(IgnoreMinScoreOnZeroMutationsOption::ARGUMENT) !== 'false');
        }

        $this->parallel($input->hasOption(ParallelOption::ARGUMENT));

        if ($input->hasOption(ProcessesOption::ARGUMENT)) {
            $this->processes($input->getOption(ProcessesOption::ARGUMENT) !== null ? (int) $input->getOption(ProcessesOption::ARGUMENT) : null); // @phpstan-ignore-line
        }

        if ($input->hasOption(ProfileOption::ARGUMENT)) {
            $this->profile($input->getOption(ProfileOption::ARGUMENT) !== 'false');
        }

        if ($_SERVER['COLLISION_PRINTER_PROFILE'] ?? false) {
            $this->profile(true);
            unset($_SERVER['COLLISION_PRINTER_PROFILE']);
        }

        if ($input->hasOption(ClassOption::ARGUMENT)) {
            $this->class(explode(',', (string) $input->getOption(ClassOption::ARGUMENT))); // @phpstan-ignore-line
        }

        if ($input->hasOption(StopOnUntestedOption::ARGUMENT)) {
            $this->stopOnUntested($input->getOption(StopOnUntestedOption::ARGUMENT) !== 'false');
        }

        if ($input->hasOption(StopOnUncoveredOption::ARGUMENT)) {
            $this->stopOnUncovered($input->getOption(StopOnUncoveredOption::ARGUMENT) !== 'false');
        }

        if ($input->hasOption(BailOption::ARGUMENT)) {
            $this->stopOnUntested();
            $this->stopOnUncovered();
        }

        if ($input->hasOption(RetryOption::ARGUMENT)) {
            $this->retry($input->getOption(RetryOption::ARGUMENT) !== 'false');
        }

        if ($input->hasOption(MutationIdOption::ARGUMENT)) {
            $this->mutationId((string) $input->getOption(MutationIdOption::ARGUMENT)); // @phpstan-ignore-line
        }

        if ($input->hasOption(NoCacheOption::ARGUMENT)) {
            Container::getInstance()->add(CacheInterface::class, new NullStore);
        }

        if ($input->hasOption(ClearCacheOption::ARGUMENT)) {
            Container::getInstance()->get(CacheInterface::class)->clear(); // @phpstan-ignore-line
        }

        if ($input->hasOption(EverythingOption::ARGUMENT)) {
            $this->everything();
        }

        return $arguments;
    }
}

<?php

declare(strict_types=1);

/*
 * This file is part of the overtrue/phplint package
 *
 * (c) overtrue
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Overtrue\PHPLint\Configuration;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;

use function array_key_exists;
use function ini_get;
use function sprintf;

/**
 * @author Laurent Laville
 * @since Release 9.0.0
 */
abstract class AbstractOptionsResolver implements Resolver
{
    protected array $defaults;
    protected array $options;

    public function __construct(InputInterface $input, array $configuration = [])
    {
        $arguments = $input->getArguments();
        $options = $input->getOptions();

        if (null !== $options[OptionDefinition::CACHE]) {
            // "cache" option is deprecated since 9.6.2, use instead "cache-dir" automagically
            $options[OptionDefinition::CACHE_DIR] = $options[OptionDefinition::CACHE];
        }

        $optionDefaults = [
            OptionDefinition::PATH => OptionDefinition::DEFAULT_PATH,
            OptionDefinition::CONFIGURATION => OptionDefinition::DEFAULT_CONFIG_FILE,
            OptionDefinition::NO_CONFIGURATION => false,
            OptionDefinition::EXCLUDE => OptionDefinition::DEFAULT_EXCLUDES,
            OptionDefinition::EXTENSIONS => OptionDefinition::DEFAULT_EXTENSIONS,
            OptionDefinition::JOBS => OptionDefinition::DEFAULT_JOBS,
            OptionDefinition::CACHE => OptionDefinition::DEFAULT_CACHE_DIR,
            OptionDefinition::CACHE_DIR => OptionDefinition::DEFAULT_CACHE_DIR,
            OptionDefinition::NO_CACHE => false,
            OptionDefinition::CACHE_TTL => OptionDefinition::DEFAULT_CACHE_TTL,
            OptionDefinition::PROGRESS => OptionDefinition::DEFAULT_PROGRESS_WIDGET,
            OptionDefinition::NO_PROGRESS => false,
            OptionDefinition::OUTPUT_FILE => null,
            OptionDefinition::OUTPUT_FORMAT => OptionDefinition::DEFAULT_FORMATS,
            OptionDefinition::WARNING => false,
            OptionDefinition::OPTION_MEMORY_LIMIT => ini_get('memory_limit'),
            OptionDefinition::IGNORE_EXIT_CODE => false,
            OptionDefinition::BOOTSTRAP => OptionDefinition::DEFAULT_BOOTSTRAP,
        ];

        $defaults = [];

        if (empty($arguments['path'])) {
            $defaults[OptionDefinition::PATH] = $configuration[OptionDefinition::PATH] ?? $optionDefaults[OptionDefinition::PATH];
        } else {
            $defaults[OptionDefinition::PATH] = $arguments['path'];
        }

        if (empty($options['format'])) {
            unset($options['format']);
        }
        if (empty($options['exclude'])) {
            unset($options['exclude']);
        }
        if (empty($options['extensions'])) {
            unset($options['extensions']);
        }
        if (empty($options['no-cache'])) {
            unset($options['no-cache']);
        }
        if (empty($options['no-progress'])) {
            unset($options['no-progress']);
        }
        if (empty($options['warning'])) {
            unset($options['warning']);
        }

        // options that cannot be overridden by YAML config file values
        $names = [
            OptionDefinition::CONFIGURATION,
            OptionDefinition::NO_CONFIGURATION
        ];
        foreach ($names as $name) {
            $defaults[$name] = $options[$name] ?? $optionDefaults[$name];
        }

        // all options that may be overridden by YAML config file values
        $names = [
            OptionDefinition::EXCLUDE,
            OptionDefinition::EXTENSIONS,
            OptionDefinition::JOBS,
            OptionDefinition::NO_CACHE,
            OptionDefinition::CACHE,
            OptionDefinition::CACHE_DIR,
            OptionDefinition::CACHE_TTL,
            OptionDefinition::NO_PROGRESS,
            OptionDefinition::PROGRESS,
            OptionDefinition::OUTPUT_FILE,
            OptionDefinition::OUTPUT_FORMAT,
            OptionDefinition::WARNING,
            OptionDefinition::OPTION_MEMORY_LIMIT,
            OptionDefinition::IGNORE_EXIT_CODE,
            OptionDefinition::BOOTSTRAP,
        ];
        foreach ($names as $name) {
            $defaults[$name] = $options[$name] ?? $configuration[$name] ?? $optionDefaults[$name];
        }

        $this->defaults = $defaults;
    }

    abstract public function factory(): Options;

    public function getOptions(): array
    {
        $options = $this->factory();
        return $this->options = $options->resolve();
    }

    public function getOption(string $name): mixed
    {
        if (!isset($this->options)) {
            $this->getOptions();
        }

        if (array_key_exists($name, $this->options)) {
            return $this->options[$name];
        }

        throw new InvalidOptionsException(sprintf('The "%s" option does not exist.', $name));
    }
}

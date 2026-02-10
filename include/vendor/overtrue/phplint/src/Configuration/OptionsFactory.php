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

use Symfony\Component\OptionsResolver\Options as SymfonyOptions;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function array_keys;
use function intval;

/**
 * @author Laurent Laville
 * @since Release 9.0.0
 */
class OptionsFactory implements Options
{
    private array $defaults;

    public function __construct(array $defaults)
    {
        $this->defaults = $defaults;
    }

    public function resolve(): array
    {
        $resolver = new OptionsResolver();
        $this->configureOptions($resolver);
        $resolver->setDefaults($this->defaults);
        $resolver->setDeprecated(
            OptionDefinition::CACHE,
            'overtrue/phplint',
            '9.6.2',
            'The option "%name%" is deprecated and will be removed in the future, use "cache-dir" instead'
        );
        return $resolver->resolve();
    }

    protected function configureOptions(OptionsResolver $resolver): void
    {
        $definitions = [
            OptionDefinition::PATH => ['null', 'string', 'string[]'],
            OptionDefinition::EXCLUDE => ['string[]'],
            OptionDefinition::EXTENSIONS => ['string[]'],
            OptionDefinition::JOBS => ['int', 'string'],
            OptionDefinition::CONFIGURATION => 'string',
            OptionDefinition::NO_CONFIGURATION => 'bool',
            OptionDefinition::CACHE => ['null', 'string'],
            OptionDefinition::CACHE_DIR => ['null', 'string'],
            OptionDefinition::CACHE_TTL => ['int', 'string'],
            OptionDefinition::NO_CACHE => 'bool',
            OptionDefinition::PROGRESS => ['null', 'string'],
            OptionDefinition::NO_PROGRESS => 'bool',
            OptionDefinition::OUTPUT_FILE => ['null', 'string'],
            OptionDefinition::OUTPUT_FORMAT => ['string', 'string[]'],
            OptionDefinition::WARNING => 'bool',
            OptionDefinition::OPTION_MEMORY_LIMIT => ['int', 'string'],
            OptionDefinition::IGNORE_EXIT_CODE => 'bool',
            OptionDefinition::BOOTSTRAP => ['null', 'string'],

            'ansi' => ['null', 'bool'],
            'help' => ['null', 'bool'],
            'no-interaction' => 'bool',
            'quiet' => ['null', 'bool'],
            'verbose' => ['null', 'bool'],
            'version' => ['null', 'bool'],
            'command' => ['null', 'string'],
        ];

        $resolver->setDefined(array_keys($definitions));

        foreach ($definitions as $option => $allowedTypes) {
            $resolver->setAllowedTypes($option, $allowedTypes);
        }

        $resolver->setNormalizer(OptionDefinition::PATH, function (SymfonyOptions $options, $value) {
            return (array) $value;
        });

        $resolver->setNormalizer(OptionDefinition::JOBS, function (SymfonyOptions $options, $value) {
            return (int) $value;
        });

        $resolver->setNormalizer(OptionDefinition::OUTPUT_FORMAT, function (SymfonyOptions $options, $value) {
            return (array) $value;
        });

        $resolver->setNormalizer(OptionDefinition::CACHE_TTL, function (SymfonyOptions $options, $value) {
            return (int) $value;
        });

        $resolver->setAllowedValues(OptionDefinition::CACHE_TTL, function (string $value) {
            return (intval($value) > 0);
        });
    }
}

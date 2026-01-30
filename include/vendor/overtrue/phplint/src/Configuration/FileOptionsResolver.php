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
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

use function is_array;
use function sprintf;

/**
 * @author Laurent Laville
 * @since Release 9.0.0
 */
final class FileOptionsResolver extends AbstractOptionsResolver
{
    public function __construct(InputInterface $input)
    {
        $configFile = $input->getOption(OptionDefinition::CONFIGURATION);

        try {
            $configuration = Yaml::parseFile($configFile);
        } catch (ParseException) {
            // If the file could not be read or the YAML is not valid
            $configuration = [];
        }

        if (null === $configuration) {
            // YAML file is empty (but may contain comments)
            $configuration = [];
        }

        if (!is_array($configuration)) {
            throw new InvalidOptionsException(sprintf('Invalid content type in "%s".', $configFile));
        }

        foreach ($configuration as $name => $value) {
            if (null === $value) {
                throw new InvalidOptionsException(sprintf('Invalid content type in "%s" for option "%s".', $configFile, $name));
            }
        }

        parent::__construct($input, $configuration);
    }

    public function factory(): Options
    {
        return new OptionsFactory($this->defaults);
    }
}

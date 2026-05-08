<?php

/*
 * This file is part of the overtrue/phplint package
 *
 * (c) overtrue
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Overtrue\PHPLint;

use Symfony\Component\Console\Application;

/**
 * @author Laurent Laville
 * @since Release 9.4.0
 * @deprecated since Release 9.6.0; Will be removed in major version 10.0
 */
class Client
{
    public function __construct(private readonly Application $application)
    {
    }

    public function getApplication(): Application
    {
        return $this->application;
    }
}

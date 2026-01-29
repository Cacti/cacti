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

namespace Overtrue\PHPLint\Event;

use Symfony\Component\EventDispatcher\EventDispatcher as SymfonyEventDispatcher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @author Laurent Laville
 * @since Release 9.0.0
 */
final class EventDispatcher extends SymfonyEventDispatcher
{
    public function __construct(iterable $extensions)
    {
        parent::__construct();

        foreach ($extensions as $extension) {
            if ($extension instanceof EventSubscriberInterface) {
                $this->addSubscriber($extension);
            }
        }
    }
}

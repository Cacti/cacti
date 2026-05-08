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

/*
 * @author Laurent Laville
 * @since Release 9.4.0
 */

require_once dirname(__DIR__, 2) . '/autoload.php';
require_once '/shared/backups/bartlett/sarif-php-converters/' . 'vendor/autoload.php';
require_once __DIR__ . '/MyPhpLintConverter.php';

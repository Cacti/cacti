<?php

declare(strict_types=1);

/**
 * This file is part of phpDocumentor.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @link      http://phpdoc.org
 */

namespace phpDocumentor\Reflection\DocBlock\Tags;

use phpDocumentor\Reflection\DocBlock\Description;
use phpDocumentor\Reflection\Type;

/**
 * Reflection class for a {@}return tag in a Docblock.
 */
final class Return_ extends TagWithType
{
    public function __construct(Type $type, ?Description $description = null)
    {
        $this->name        = 'return';
        $this->type        = $type;
        $this->description = $description;
    }
}

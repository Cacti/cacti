<?php

declare(strict_types=1);

/**
 * This file is part of phpDocumentor.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @link http://phpdoc.org
 */

namespace phpDocumentor\Reflection\DocBlock\Tags;

use phpDocumentor\Reflection\DocBlock\Description;
use phpDocumentor\Reflection\Type;

/**
 * Reflection class for a {@}template-implements tag in a Docblock.
 */
final class TemplateImplements extends Implements_
{
    public function __construct(Type $type, ?Description $description = null)
    {
        parent::__construct($type, $description);
        $this->name = 'template-implements';
    }
}

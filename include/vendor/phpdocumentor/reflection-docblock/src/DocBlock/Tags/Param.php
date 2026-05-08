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
 * Reflection class for the {@}param tag in a Docblock.
 */
final class Param extends TagWithType
{
    private ?string $variableName = null;

    /** @var bool determines whether this is a variadic argument */
    private bool $isVariadic;

    /** @var bool determines whether this is passed by reference */
    private bool $isReference;

    public function __construct(
        ?string $variableName,
        ?Type $type = null,
        bool $isVariadic = false,
        ?Description $description = null,
        bool $isReference = false
    ) {
        $this->name         = 'param';
        $this->variableName = $variableName;
        $this->type         = $type;
        $this->isVariadic   = $isVariadic;
        $this->description  = $description;
        $this->isReference  = $isReference;
    }

    /**
     * Returns the variable's name.
     */
    public function getVariableName(): ?string
    {
        return $this->variableName;
    }

    /**
     * Returns whether this tag is variadic.
     */
    public function isVariadic(): bool
    {
        return $this->isVariadic;
    }

    /**
     * Returns whether this tag is passed by reference.
     */
    public function isReference(): bool
    {
        return $this->isReference;
    }

    /**
     * Returns a string representation for this tag.
     */
    public function __toString(): string
    {
        if ($this->description) {
            $description = $this->description->render();
        } else {
            $description = '';
        }

        $variableName = '';
        if ($this->variableName !== null && $this->variableName !== '') {
            $variableName .= ($this->isReference ? '&' : '') . ($this->isVariadic ? '...' : '');
            $variableName .= '$' . $this->variableName;
        }

        $type = (string) $this->type;

        return $type
            . ($variableName !== '' ? ($type !== '' ? ' ' : '') . $variableName : '')
            . ($description !== '' ? ($type !== '' || $variableName !== '' ? ' ' : '') . $description : '');
    }
}

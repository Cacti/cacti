<?php
/**
 * This file is part of phpDocumentor.
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 *
 *  @link      http://phpdoc.org
 */

declare(strict_types=1);

namespace phpDocumentor\Reflection\DocBlock\Tags;

use phpDocumentor\Reflection\DocBlock\Tags\Factory\MethodParameterFactory;
use phpDocumentor\Reflection\Type;

final class MethodParameter
{
    private Type $type;

    private bool $isReference;

    private bool $isVariadic;

    private string $name;

    /** @var mixed */
    private $defaultValue;

    public const NO_DEFAULT_VALUE = '__NO_VALUE__';

    /**
     * @param mixed $defaultValue
     */
    public function __construct(
        string $name,
        Type $type,
        bool $isReference = false,
        bool $isVariadic = false,
        $defaultValue = self::NO_DEFAULT_VALUE
    ) {
        $this->type = $type;
        $this->isReference = $isReference;
        $this->isVariadic = $isVariadic;
        $this->name = $name;
        $this->defaultValue = $defaultValue;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): Type
    {
        return $this->type;
    }

    public function isReference(): bool
    {
        return $this->isReference;
    }

    public function isVariadic(): bool
    {
        return $this->isVariadic;
    }

    public function getDefaultValue(): ?string
    {
        if ($this->defaultValue === self::NO_DEFAULT_VALUE) {
            return null;
        }

        return (new MethodParameterFactory())->format($this->defaultValue);
    }

    public function __toString(): string
    {
        return $this->getType() . ' ' .
            ($this->isReference() ? '&' : '') .
            ($this->isVariadic() ? '...' : '') .
            '$' . $this->getName() .
            (
                $this->defaultValue !== self::NO_DEFAULT_VALUE ?
                    ' = ' . (new MethodParameterFactory())->format($this->defaultValue) :
                ''
            );
    }
}

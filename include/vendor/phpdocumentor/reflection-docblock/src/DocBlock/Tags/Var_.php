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
use Webmozart\Assert\Assert;

/**
 * Reflection class for a {@}var tag in a Docblock.
 */
final class Var_ extends TagWithType
{
    protected ?string $variableName = '';

    public function __construct(?string $variableName, ?Type $type = null, ?Description $description = null)
    {
        Assert::string($variableName);

        $this->name         = 'var';
        $this->variableName = $variableName;
        $this->type         = $type;
        $this->description  = $description;
    }

    /**
     * Returns the variable's name.
     */
    public function getVariableName(): ?string
    {
        return $this->variableName;
    }

    /**
     * Returns a string representation for this tag.
     */
    public function __toString(): string
    {
        if ($this->description !== null) {
            $description = $this->description->render();
        } else {
            $description = '';
        }

        if ($this->variableName !== null && $this->variableName !== '') {
            $variableName = '$' . $this->variableName;
        } else {
            $variableName = '';
        }

        $type = (string) $this->type;

        return $type
            . ($variableName !== '' ? ($type !== '' ? ' ' : '') . $variableName : '')
            . ($description !== '' ? ($type !== '' || $variableName !== '' ? ' ' : '') . $description : '');
    }
}

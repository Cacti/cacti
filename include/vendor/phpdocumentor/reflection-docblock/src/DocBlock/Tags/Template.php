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
use phpDocumentor\Reflection\DocBlock\Tag;
use phpDocumentor\Reflection\Exception\CannotCreateTag;
use phpDocumentor\Reflection\Type;

/**
 * Reflection class for a {@}template tag in a Docblock.
 */
final class Template extends BaseTag
{
    /** @var non-empty-string */
    private string $templateName;

    /** @var ?Type The real type */
    private ?Type $bound;

    private ?Type $default;

    /** @param non-empty-string $templateName */
    public function __construct(
        string $templateName,
        ?Type $bound = null,
        ?Type $default = null,
        ?Description $description = null
    ) {
        $this->name = 'template';
        $this->templateName = $templateName;
        $this->bound = $bound;
        $this->default = $default;
        $this->description = $description;
    }

    /**
     * @deprecated Create using static factory is deprecated,
     *  this method should not be called directly by library consumers
     */
    public static function create(string $body): ?Tag
    {
        throw new CannotCreateTag('Template tag cannot be created');
    }

    public function getTemplateName(): string
    {
        return $this->templateName;
    }

    public function getBound(): ?Type
    {
        return $this->bound;
    }

    public function getDefault(): ?Type
    {
        return $this->default;
    }

    public function __toString(): string
    {
        $bound = $this->bound !== null ? ' of ' . $this->bound : '';
        $default = $this->default !== null ? ' = ' . $this->default : '';

        if ($this->description) {
            $description = $this->description->render();
        } else {
            $description = '';
        }

        return $this->templateName . $bound . $default . ($description !== '' ? ' ' . $description : '');
    }
}

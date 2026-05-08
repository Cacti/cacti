<?php

declare(strict_types=1);

namespace PHPUnit\Architecture\Enums;

enum ObjectType: string
{
    case _CLASS     = 'class';
    case _ENUM      = 'enum';
    case _TRAIT     = 'trait';
    case _INTERFACE = 'interface';
}

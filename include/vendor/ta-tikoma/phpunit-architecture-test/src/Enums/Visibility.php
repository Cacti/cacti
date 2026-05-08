<?php

declare(strict_types=1);

namespace PHPUnit\Architecture\Enums;

enum Visibility: string
{
    case PRIVATE   = 'private';
    case PUBLIC    = 'public';
    case PROTECTED = 'protected';
}

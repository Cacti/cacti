<?php

declare(strict_types=1);

namespace Pest\Arch\Support;

use Pest\Exceptions\ShouldNotHappen;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Clone_;
use PhpParser\Node\Expr\Empty_;
use PhpParser\Node\Expr\Eval_;
use PhpParser\Node\Expr\Exit_;
use PhpParser\Node\Expr\Include_;
use PhpParser\Node\Expr\Isset_;
use PhpParser\Node\Expr\Print_;
use PhpParser\Node\Expr\ShellExec;

/**
 * @internal
 */
final class PhpCoreExpressions
{
    /**
     * @var array<int, class-string<Expr>>
     */
    public static array $ENABLED = [
        Clone_::class,
        Empty_::class,
        Eval_::class,
        Exit_::class,
        Isset_::class,
        Print_::class,
    ];

    public static function getName(Expr $expression): string
    {
        return match ($expression::class) {
            Clone_::class => 'clone',
            Empty_::class => 'empty',
            Eval_::class => 'eval',
            Exit_::class => match ($expression->getAttribute('kind')) {
                Exit_::KIND_EXIT => 'exit',
                Exit_::KIND_DIE => 'die',
                // @phpstan-ignore-next-line
                default => throw ShouldNotHappen::fromMessage('Unhandled Exit expression kind '.$expression->getAttribute('kind')),
            },
            Isset_::class => 'isset',
            Print_::class => 'print',
            default => throw ShouldNotHappen::fromMessage('Unsupported Core Expression'),
        };
    }

    public static function getClass(string $name): ?string
    {
        return match ($name) {
            'clone' => Clone_::class,
            'empty' => Empty_::class,
            'eval' => Eval_::class,
            'die', 'exit' => Exit_::class,
            'include' => Include_::class,
            'isset' => Isset_::class,
            'print' => Print_::class,
            'shell_exec' => ShellExec::class,
            default => null,
        };
    }
}

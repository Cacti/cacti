<?php

declare(strict_types=1);

namespace Pest\Arch;

use Closure;
use Pest\Arch\Options\LayerOptions;
use Pest\Arch\Support\UserDefinedFunctions;
use Pest\Expectation;
use Pest\TestSuite;
use PHPUnit\Architecture\Elements\ObjectDescription;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @mixin Expectation<array<int, string>|string>
 */
final class SingleArchExpectation implements Contracts\ArchExpectation
{
    /**
     * The "opposite" callback.
     */
    private ?Closure $opposite = null;

    /**
     * Whether the expectation has been verified.
     */
    private bool $lazyExpectationVerified = false;

    /**
     * The ignored list of layers.
     *
     * @var array<int, string>
     */
    public array $ignoring = [];

    /**
     * The ignored list of layers.
     *
     * @var array<int, callable(ObjectDescription): bool>
     */
    private array $excludeCallbacks = [];

    /**
     * Creates a new Arch Expectation instance.
     *
     * @param  Expectation<array<int, string>|string>  $expectation
     */
    private function __construct(private readonly Expectation $expectation, private readonly Closure $lazyExpectation)
    {
        // ...
    }

    /**
     * {@inheritDoc}
     */
    public function ignoring(array|string $targetsOrDependencies): self
    {
        $targetsOrDependencies = is_array($targetsOrDependencies) ? $targetsOrDependencies : [$targetsOrDependencies];

        $this->ignoring = array_unique([...$this->ignoring, ...$targetsOrDependencies]);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function ignoringGlobalFunctions(): self
    {
        return $this->ignoring(UserDefinedFunctions::get());
    }

    /**
     * Sets the "opposite" callback.
     */
    public function opposite(Closure $callback): self
    {
        $this->opposite = $callback;

        return $this;
    }

    /**
     * Creates a new Arch Expectation instance from the given expectation.
     *
     * @param  Expectation<array<int, string>|string>  $expectation
     */
    public static function fromExpectation(Expectation $expectation, Closure $lazyExpectation): self
    {
        return new self($expectation, $lazyExpectation);
    }

    /**
     * Proxies the call to the expectation.
     *
     * @param  array<array-key, mixed>  $arguments
     * @return Expectation<string>
     */
    public function __call(string $name, array $arguments): mixed
    {
        $this->ensureLazyExpectationIsVerified();

        return $this->expectation->$name(...$arguments); // @phpstan-ignore-line
    }

    /**
     * Proxies the call to the expectation.
     *
     * @return Expectation<string>
     */
    public function __get(string $name): mixed
    {
        $this->ensureLazyExpectationIsVerified();

        return $this->expectation->$name; // @phpstan-ignore-line
    }

    /**
     * {@inheritDoc}
     */
    public function mergeExcludeCallbacks(array $callbacks): void
    {
        $this->excludeCallbacks = [...$this->excludeCallbacks, ...$callbacks];
    }

    /**
     * {@inheritDoc}
     *
     * @return array<int, callable(ObjectDescription): bool>
     */
    public function excludeCallbacks(): array
    {
        return $this->excludeCallbacks;
    }

    /**
     * Ensures the lazy expectation is verified when the object is destructed.
     */
    public function __destruct()
    {
        $this->ensureLazyExpectationIsVerified();
    }

    /**
     * Ensures the lazy expectation is verified.
     */
    public function ensureLazyExpectationIsVerified(): void
    {
        if (TestSuite::getInstance()->test instanceof TestCase && ! $this->lazyExpectationVerified) {
            $this->lazyExpectationVerified = true;

            $e = null;

            $options = LayerOptions::fromExpectation($this);

            try {
                ($this->lazyExpectation)($options);
            } catch (ExpectationFailedException|AssertionFailedError $e) {
                if (! $this->opposite instanceof \Closure) {
                    throw $e;
                }
            }

            if (! $this->opposite instanceof Closure) {
                return;
            }
            if (! is_null($e)) {
                return;
            }

            ($this->opposite)(); // @phpstan-ignore-line
        }
    }
}

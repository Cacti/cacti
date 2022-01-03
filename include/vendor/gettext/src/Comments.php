<?php
declare(strict_types = 1);

namespace Gettext;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use ReturnTypeWillChange;

/**
 * Class to manage the comments of a translation.
 */
class Comments implements JsonSerializable, Countable, IteratorAggregate
{
    protected $comments = [];

    public static function __set_state(array $state): Comments
    {
        return new static(...$state['comments']);
    }

    public function __construct(string ...$comments)
    {
        if (!empty($comments)) {
            $this->add(...$comments);
        }
    }

    public function __debugInfo()
    {
        return $this->toArray();
    }

    public function add(string ...$comments): self
    {
        foreach ($comments as $comment) {
            if (!in_array($comment, $this->comments)) {
                $this->comments[] = $comment;
            }
        }

        return $this;
    }

    public function delete(string ...$comments): self
    {
        foreach ($comments as $comment) {
            $key = array_search($comment, $this->comments);

            if (is_int($key)) {
                array_splice($this->comments, $key, 1);
            }
        }

        return $this;
    }

    #[ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return $this->toArray();
    }

    #[ReturnTypeWillChange]
    public function getIterator()
    {
        return new ArrayIterator($this->comments);
    }

    public function count(): int
    {
        return count($this->comments);
    }

    public function toArray(): array
    {
        return $this->comments;
    }

    public function mergeWith(Comments $comments): Comments
    {
        $merged = clone $this;
        $merged->add(...$comments->comments);

        return $merged;
    }
}

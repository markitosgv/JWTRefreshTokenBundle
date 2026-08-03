<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Unit\Doctrine;

use Countable;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenInterface;
use Iterator;

/**
 * Test double for the result the ODM repositories return.
 *
 * `Doctrine\ODM\MongoDB\Iterator\CachingIterator` implements `Countable` and `Iterator`, so it can be
 * counted and traversed, but it is not an array. This double is used to cover the ODM code path
 * without requiring the ODM (and a running MongoDB) in the unit test suite.
 *
 * @implements Iterator<int, RefreshTokenInterface>
 */
final class CachingIteratorDouble implements Countable, Iterator
{
    private int $position = 0;

    /**
     * @param list<RefreshTokenInterface> $items
     */
    public function __construct(private readonly array $items = [])
    {
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function current(): RefreshTokenInterface
    {
        return $this->items[$this->position];
    }

    public function key(): int
    {
        return $this->position;
    }

    public function next(): void
    {
        ++$this->position;
    }

    public function rewind(): void
    {
        $this->position = 0;
    }

    public function valid(): bool
    {
        return isset($this->items[$this->position]);
    }
}

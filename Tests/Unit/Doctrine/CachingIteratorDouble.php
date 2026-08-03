<?php

namespace Gesdinet\JWTRefreshTokenBundle\Tests\Unit\Doctrine;

use Countable;
use Iterator;

/**
 * Test double for the result the ODM repositories return.
 *
 * Doctrine\ODM\MongoDB\Iterator\CachingIterator implements Countable and Iterator, so it can be
 * counted and traversed, but it is not an array. This double is used to cover the ODM code path
 * without requiring the ODM (and a running MongoDB) in the test suite.
 */
final class CachingIteratorDouble implements Countable, Iterator
{
    /**
     * @var array
     */
    private $items;

    /**
     * @var int
     */
    private $position = 0;

    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    public function count(): int
    {
        return count($this->items);
    }

    #[\ReturnTypeWillChange]
    public function current()
    {
        return $this->items[$this->position];
    }

    #[\ReturnTypeWillChange]
    public function key()
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

<?php
/**
 * @link https://github.com/ixocreate
 * @copyright IXOCREATE GmbH
 * @license MIT License
 */

declare(strict_types=1);

namespace Ixocreate\Collection;

use Ixocreate\Contract\Collection\CollectionCollectionInterface;
use Ixocreate\Contract\Collection\CollectionInterface;

class CollectionCollection extends AbstractCollection implements CollectionCollectionInterface
{
    /**
     * @var CollectionInterface[]
     */
    private $items = [];

    /**
     * @param array $items
     */
    public function __construct(array $items = [])
    {
        $this->items = (function (CollectionInterface ...$collection) {
            return $collection;
        })(...$items);
    }

    /**
     * @return array
     */
    public function getCollections(): array
    {
        return $this->items;
    }

    /**
     * @return \Iterator
     */
    public function getCollectionIterator(): \Iterator
    {
        return new \ArrayIterator($this->items);
    }

    /**
     * @return int
     */
    public function getCollectionCount(): int
    {
        return \count($this->items);
    }

    /**
     * Returns all collection items
     *
     * @return array
     */
    public function all(): array
    {
        $array = [];
        /** @var CollectionInterface $item */
        foreach ($this->items as $item) {
            $array = \array_merge($array, $item->all());
        }

        return $array;
    }

    /**
     * Returns all keys of the collection items
     *
     * @return array
     */
    public function keys(): array
    {
        return \array_keys($this->items);
    }

    /**
     * Executes a callable over each collection item
     *
     * @param callable $callable
     */
    public function each(callable $callable): void
    {
        foreach ($this->items as $key => $item) {
            $result = $callable($item, $key);
            if ($result === false) {
                break;
            }
        }
    }

    /**
     * Checks if the current collection is empty
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return $this->count() === 0;
    }

    /**
     * @return \Iterator
     */
    public function getIterator(): \Iterator
    {
        $iterator = new \MultipleIterator();
        /** @var CollectionInterface $item */
        foreach ($this->items as $item) {
            $iterator->attachIterator($item->getIterator());
        }

        return $iterator;
    }

    /**
     * @return int
     */
    public function count(): int
    {
        return \array_sum(\array_map(function (CollectionInterface $collection) {
            return $collection->count();
        }, $this->items));
    }
}

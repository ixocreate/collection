<?php
/**
 * @link https://github.com/ixocreate
 * @copyright IXOCREATE GmbH
 * @license MIT License
 */

declare(strict_types=1);

namespace Ixocreate\Collection;

use Ixocreate\Collection\Exception\EmptyException;
use Ixocreate\Collection\Exception\InvalidCollectionException;
use Ixocreate\Collection\Exception\InvalidTypeException;
use Ixocreate\Collection\Exception\KeysNotMatchException;
use Ixocreate\Contract\Collection\CollectionCollectionInterface;
use Ixocreate\Contract\Collection\CollectionInterface;

abstract class AbstractCollection implements CollectionInterface
{
    /**
     * @var array
     */
    private $items = [];

    /**
     * @var callable|string|int|null
     */
    private $indexByKey;

    /**
     * @param array $items
     * @param callable|string|int|null $indexBy
     */
    public function __construct(array $items = [], $indexBy = null)
    {
        $this->items = \array_values($items);

        $this->indexByKey = $indexBy;
        $this->regenerateKeys();
    }

    /**
     * Returns all collection items
     *
     * @return array
     */
    final public function all(): array
    {
        return $this->items;
    }

    /**
     * @param callable|string|int $key
     * @return CollectionInterface
     */
    final public function indexBy($key): CollectionInterface {
        return new static($this->items, $key);
    }

    /**
     *
     */
    private function regenerateKeys(): void
    {
        if ($this->indexByKey === null) {
            return;
        }

        $callbackKeys = $this->getScalarSelector($this->indexByKey);
        $keys = \array_unique($this->callSelectorWithAllResults($callbackKeys), SORT_REGULAR);
        if (\count($keys) !== \count($this->items)) {
            throw new KeysNotMatchException("The amount of keys doesn't match the amount of items");
        }

        $this->items = \array_combine($keys, \array_values($this->items));
    }

    /**
     * @param callable $selector
     * @return array
     */
    private function callSelectorWithAllResults(callable $selector): array
    {
        $result = [];
        foreach ($this->items as $key => $item) {
            $result[$key] = $selector($item, $key);
        }

        return $result;
    }

    /**
     * @param callable $selector
     * @return mixed
     */
    private function callSelectorWithFirstResult(callable $selector)
    {
        foreach ($this->items as $key => $item) {
            $result = $selector($item, $key);

            if (!empty($result)) {
                return $result;
            }
        }
    }

    /**
     * @param callable|string|int $selector
     * @return callable
     */
    private function getScalarSelector($selector): callable
    {
        if (\is_callable($selector)) {
            return $selector;
        }

        return function ($item) use ($selector) {
            if (($item instanceof \ArrayAccess && $item->offsetExists($selector)) || \array_key_exists($selector, $item)) {
                return $item[$selector];
            }
        };
    }

    /**
     * Returns the average of a given selector
     *
     * @param callable|string|int $selector
     * @throws EmptyException
     * @return float
     */
    final public function avg($selector): float
    {
        if ($this->count() === 0) {
            throw new EmptyException("Can't calculate average on empty collections");
        }

        return (float) ($this->sum($selector) / $this->count());
    }

    /**
     * Returns the sum of a given selector
     *
     * @param callable|string|int $selector
     * @return float
     */
    final public function sum($selector): float
    {
        $selector = $this->getScalarSelector($selector);

        return (float) \array_sum(\array_map(function ($value) {
            return (float) $value;
        }, $this->callSelectorWithAllResults($selector)));
    }

    /**
     * Returns the minimum value of a given selector
     *
     * @param callable|string|int $selector
     * @throws EmptyException
     * @return CollectionInterface
     */
    final public function min($selector): CollectionInterface
    {
        if ($this->count() === 0) {
            throw new EmptyException("Can't calculate average on empty collections");
        }

        $selector = $this->getScalarSelector($selector);

        $result = \array_filter($this->callSelectorWithAllResults($selector));
        $result = \array_keys($result, \min($result));

        $items = [];
        foreach ($result as $key) {
            $items[] = $this->items[$key];
        }

        return new static($items, $this->indexByKey);
    }

    /**
     * Returns the maximum value of a given selector
     *
     * @param callable|string|int $selector
     * @throws EmptyException
     * @return CollectionInterface
     */
    final public function max($selector): CollectionInterface
    {
        if ($this->count() === 0) {
            throw new EmptyException("Can't calculate average on empty collections");
        }

        $selector = $this->getScalarSelector($selector);

        $result = \array_filter($this->callSelectorWithAllResults($selector));
        $result = \array_keys($result, \max($result));

        $items = [];
        foreach ($result as $key) {
            $items[] = $this->items[$key];
        }

        return new static($items, $this->indexByKey);
    }

    /**
     * Returns all keys of the collection items
     *
     * @return array
     */
    final public function keys(): array
    {
        return \array_keys($this->items);
    }

    /**
     * Returns an array of all values of a given selector
     *
     * @param callable|string|int $selector
     * @return array
     */
    final public function parts($selector): array
    {
        $selector = $this->getScalarSelector($selector);

        return \array_values($this->callSelectorWithAllResults($selector));
    }

    /**
     * Returns one collection item based on a given selector
     *
     * @param string|int $key
     * @param mixed $default
     * @return mixed
     */
    final public function get($key, $default = null)
    {
        if (!$this->has($key)) {
            return $default;
        }

        return $this->items[$key];
    }

    /**
     * Checks if an collection item exists based on a given selector
     *
     * @param string|int $key
     * @return bool
     */
    final public function has($key): bool
    {
        return \array_key_exists($key, $this->items);
    }

    /**
     * Returns one random collection item
     *
     * @return mixed
     */
    final public function random()
    {
        \mt_srand();
        $randomKey = \mt_rand(0, $this->count() - 1);

        return $this->get($this->keys()[$randomKey]);
    }

    /**
     * Executes a callable over each collection item
     *
     * @param callable $callable
     */
    final public function each(callable $callable): void
    {
        foreach ($this->items as $key => $item) {
            $result = $callable($item, $key);
            if ($result === false) {
                break;
            }
        }
    }

    /**
     * Filters the current collection items based on a given callable
     *
     * @param callable $callable
     * @return CollectionInterface
     */
    final public function filter(callable $callable): CollectionInterface
    {
        return new static(\array_filter($this->items, $callable), $this->indexByKey);
    }

    /**
     * Sorts the current collection based on a given callable
     *
     * @param callable $callable
     * @return CollectionInterface
     */
    final public function sort(callable $callable): CollectionInterface
    {
        $items = $this->items;
        \usort($items, $callable);
        return new static($items, $this->indexByKey);
    }

    /**
     * Merge another collection into the current collection
     *
     * @param CollectionInterface $collection
     * @return CollectionInterface
     */
    final public function merge(CollectionInterface $collection): CollectionInterface
    {
        if (!$collection instanceof $this) {
            throw new InvalidCollectionException(
                \sprintf(
                    "'collection' must be a '%s', '%s' given",
                    \get_class($this),
                    \get_class($collection)
                )
            );
        }
        return new static(\array_merge($this->items, $collection->all()), $this->indexByKey);
    }

    /**
     * Chunk the collection items and return them as a collection
     *
     * @param int $size
     * @return CollectionCollectionInterface
     */
    final public function chunk(int $size): CollectionCollectionInterface
    {
        if ($this->count() === 0) {
            return new CollectionCollection([]);
        }

        $chunks = [];
        foreach (\array_chunk($this->items, $size) as $chunk) {
            $chunks[] = new static($chunk, $this->indexByKey);
        }

        return new CollectionCollection($chunks);
    }

    /**
     * Split the collection into groups and return them as a collection
     *
     * @param int $groups
     * @return CollectionCollectionInterface
     */
    final public function split(int $groups): CollectionCollectionInterface
    {
        return $this->chunk((int)\ceil($this->count() / $groups));
    }

    /**
     * Return a new collection of every n-th element
     *
     * @param int $step
     * @param int $offset
     * @return CollectionInterface
     */
    final public function nth(int $step, $offset = 0): CollectionInterface
    {
        $items = [];

        $position = 0;
        foreach ($this->items as $item) {
            if ($position % $step === $offset) {
                $items[] = $item;
            }
            $position++;
        }
        return new static($items, $this->indexByKey);
    }

    /**
     * Return a collection of items which are different between the given collection and the current collection
     *
     * @param CollectionInterface $collection
     * @return CollectionInterface
     */
    final public function diff(CollectionInterface $collection): CollectionInterface
    {
        if (!$collection instanceof $this) {
            throw new InvalidCollectionException(
                \sprintf(
                    "'collection' must be a '%s', '%s' given",
                    \get_class($this),
                    \get_class($collection)
                )
            );
        }
        $array = \array_udiff($this->items, $collection->all(), function ($item1, $item2) {
            if ($item1 === $item2) {
                return 0;
            }

            return -1;
        });

        return new static($array, $this->indexByKey);
    }

    /**
     * Return a collection of intersecting collection items
     *
     * @param CollectionInterface $collection
     * @return CollectionInterface
     */
    final public function intersect(CollectionInterface $collection): CollectionInterface
    {
        if (!$collection instanceof $this) {
            throw new InvalidCollectionException(
                \sprintf(
                    "'collection' must be a '%s', '%s' given",
                    \get_class($this),
                    \get_class($collection)
                )
            );
        }

        $array = \array_uintersect($this->items, $collection->all(), function ($item1, $item2) {
            if ($item1 === $item2) {
                return 0;
            }

            return -1;
        });

        return new static($array, $this->indexByKey);
    }

    /**
     * Removes and returns the last collection item
     *
     * @return mixed
     */
    final public function pop()
    {
        $pop = \array_pop($this->items);
        $this->regenerateKeys();
        return $pop;
    }

    /**
     * Removes and returns the first collection item
     *
     * @return mixed
     */
    final public function shift()
    {
        $shift = \array_shift($this->items);
        $this->regenerateKeys();
        return $shift;
    }

    /**
     * Removes and returns all items filtered by a given callable
     *
     * @param callable $callable
     * @return CollectionInterface
     */
    final public function pull(callable $callable): CollectionInterface
    {
        $filteredItems = [];
        $items = [];

        foreach ($this->items as $key => $item) {
            if ($callable($item, $key) === true) {
                $filteredItems[] = $item;
                continue;
            }

            $items[] = $item;
        }

        $this->items = $items;
        $this->regenerateKeys();

        return new static($filteredItems, $this->indexByKey);
    }

    /**
     * Iteratively reduce the array to a single value using a callback function
     *
     * @param callable $callable
     * @param mixed $initial
     * @return mixed
     */
    final public function reduce(callable $callable, $initial = null)
    {
        return \array_reduce($this->items, $callable);
    }

    /**
     * Adds an item at the beginning of the current collection
     *
     * @param mixed $item
     */
    final public function prepend($item): void
    {
        if (!\is_array($item)) {
            throw new InvalidTypeException("'item' must be an item array");
        }

        \array_unshift($this->items, $item);
        $this->regenerateKeys();
    }

    /**
     * Adds an item at the end of the current collection
     *
     * @param array $item
     */
    final public function push($item): void
    {
        if (!\is_array($item)) {
            throw new InvalidTypeException("'item' must be an item array");
        }

        \array_push($this->items, $item);
        $this->regenerateKeys();
    }

    /**
     * Returns the first collection item or the first collection item matched by a given callable
     *
     * @param callable|null $callable
     * @return mixed
     */
    final public function first(callable $callable = null)
    {
        if ($callable === null) {
            foreach ($this->items as $item) {
                return $item;
            }
        }

        return $this->callSelectorWithFirstResult($callable);
    }

    /**
     * Returns the last collection item or the last collection item matched by a given callable
     *
     * @param callable|null $callable
     * @return mixed
     */
    final public function last(callable $callable = null)
    {
        return $this->reverse()->first($callable);
    }

    /**
     * Shuffles the current collection items
     *
     * @return CollectionInterface
     */
    final public function shuffle(): CollectionInterface
    {
        $items = $this->items;
        \mt_srand();
        \usort($items, function () {
            return \mt_rand(-1, 1);
        });

        return new static($items, $this->indexByKey);
    }

    /**
     * Returns a sequence of collection items as collection interface specified by offset and length
     *
     * @param int $offset
     * @param int|null $length
     * @return CollectionInterface
     */
    final public function slice(int $offset, int $length = null): CollectionInterface
    {
        return new static(\array_slice($this->items, $offset, $length), $this->indexByKey);
    }

    /**
     * Checks if the current collection is empty
     *
     * @return bool
     */
    final public function isEmpty(): bool
    {
        return $this->count() === 0;
    }

    /**
     * @return \ArrayIterator
     */
    final public function getIterator()
    {
        return new \ArrayIterator($this->items);
    }

    /**
     * @return int
     */
    final public function count(): int
    {
        return \count($this->items);
    }

    /**
     * Returns a collection in reverse order
     *
     * @return CollectionInterface
     */
    final public function reverse(): CollectionInterface
    {
        $items = \array_reverse($this->items);
        return new static($items, $this->indexByKey);
    }
}

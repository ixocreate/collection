<?php
/**
 * @link https://github.com/ixocreate
 * @copyright IXOCREATE GmbH
 * @license MIT License
 */

declare(strict_types=1);

namespace Ixocreate\Collection;

use ArrayIterator;
use Iterator;
use Ixocreate\Collection\Exception\DuplicateKey;
use Ixocreate\Collection\Exception\EmptyCollection;
use Ixocreate\Collection\Exception\InvalidArgument;
use Ixocreate\Collection\Exception\InvalidReturnValue;
use Ixocreate\Contract\Collection\CollectionInterface;
use Traversable;

/**
 * Class AbstractCollection
 *
 * Inspired by collection pipeline library https://github.com/DusanKasan/Knapsack and https://laravel.com/docs/master/collections
 *
 * @package Ixocreate\Collection
 */
abstract class AbstractCollection implements CollectionInterface
{
    /**
     * @var int
     */
    private $count;

    /**
     * @var array|callable|Traversable
     */
    private $inputFactory;

    /**
     * @var Iterator
     */
    private $input;

    /**
     * @var bool
     */
    private $strictUniqueKeys = true;

    /**
     * @var array
     */
    private $usedKeys;

    /**
     * @param callable|array|Traversable $items
     */
    public function __construct($items = [])
    {
        /**
         * The constructor is not final to allow for overrides in specialized collections (for setting defaults etc).
         * For this reason the input is passed to a dedicated setter which can be called after cloning.
         *
         * ```
         * return (clone $this)->input($generatorFactory);
         * ```
         */
        return $this->input($items);
    }

    /**
     * @param callable|array|Traversable $input
     * @return CollectionInterface
     */
    private function input($input = []): CollectionInterface
    {
        $this->count = null;
        $this->input = null;
        $this->inputFactory = null;
        $this->usedKeys = [];

        /**
         * explicitly check that it's not a string callable which would accept global php functions as input
         */
        if (\is_callable($input) && !\is_string($input)) {
            $this->inputFactory = $input;
            $input = $input();
        }

        if (\is_array($input)) {
            $this->input = new ArrayIterator($input);
        } elseif ($input instanceof \Generator) {
            $this->input = $input;
        } elseif ($input instanceof \Iterator) {
            /**
             * If another Collection is passed as $input and assigned directly this would result in DuplicateKey errors.
             * TODO: find out why exactly
             * Wrap it into an IteratorIterator to prevent this.
             */
            $this->input = new \IteratorIterator($input);
        } elseif ($input instanceof \Traversable) {
            $this->input = new \IteratorIterator($input);
        } else {
            throw $this->inputFactory ? new InvalidReturnValue() : new InvalidArgument();
        }

        return $this;
    }

    /**
     * @return CollectionInterface
     */
    private function items(): CollectionInterface
    {
        return $this;
    }

    /**
     * @param callable|string|int $selector
     * @return callable
     */
    private function selector($selector = null): callable
    {
        /**
         * explicitly check that it's not a string callable which would accept global php functions as input
         */
        if (\is_callable($selector) && !\is_string($selector)) {
            return $selector;
        }

        /**
         * TODO: re-implement; used for extract()
         * A dot separated key path works as well. Supports the * wildcard. If a key contains \ or it must be escaped using \ character.
         */
        //$keyPath = $selector;
        //
        //preg_match_all('/(.*[^\\\])(?:\.|$)/U', $keyPath, $matches);
        //$pathParts = $matches[1];
        //
        //$extractor = function ($coll) use ($pathParts) {
        //    foreach ($pathParts as $pathPart) {
        //        $coll = flatten(filter($coll, 'isCollection'), 1);
        //
        //        if ($pathPart != '*') {
        //            $pathPart = str_replace(['\.', '\*'], ['.', '*'], $pathPart);
        //            $coll = values(only($coll, [$pathPart]));
        //        }
        //    }
        //
        //    return $coll;
        //};
        //
        //$generatorFactory = function () use ($collection, $extractor) {
        //    foreach ($collection as $value) {
        //        foreach ($extractor([$value]) as $extracted) {
        //            yield $extracted;
        //        }
        //    }
        //};

        if ($selector === null) {
            return function ($value) {
                if (!\is_scalar($value)) {
                    throw new InvalidReturnValue();
                }
                return $value;
            };
        }

        return function ($value) use ($selector) {
            if ($value instanceof \ArrayAccess && $value->offsetExists($selector)) {
                return $value[$selector];
            }
            if (\is_array($value) && \array_key_exists($selector, $value)) {
                return $value[$selector];
            }
            if (\is_object($value)) {
                try {
                    return $value->{$selector};
                } catch (\Throwable $exception) {
                    //
                }
            }
            return null;
        };
    }

    /**
     * Transforms [[$key, $value], [$key2, $value2]] into [$key => $value, $key2 => $value2].
     * Used as a helper whenever keys and values were mapped to the same level for transformation.
     *
     * @param $collection
     * @return CollectionInterface
     */
    private function dereferenceKeyValue($collection)
    {
        $generatorFactory = function () use ($collection) {
            foreach ($collection as $value) {
                yield $value[0] => $value[1];
            }
        };

        return (clone $this)->input($generatorFactory);
    }

    /**
     * By expecting unique keys realizing the Collection by calling any method that causes iteration will
     * throw a DuplicateKey Exception if items share the same key.
     *
     * Disabling strict behaviour will cause toArray() to only contain each last found value of a given key.
     * To get the expected amount of items you should call values() before.
     *
     * @param bool $strictUniqueKeys
     * @return CollectionInterface
     */
    final public function strictUniqueKeys(bool $strictUniqueKeys = true): CollectionInterface
    {
        $this->strictUniqueKeys = $strictUniqueKeys;

        return $this;
    }

    final public function current()
    {
        return $this->input->current();
    }

    final public function next()
    {
        $this->input->next();
    }

    final public function key()
    {
        /**
         * Strict indexBy requires keys to be unique - make sure this is the case while keys are being iterated
         * Note that this check will not be run when iterating over the collection with foreach ($collection as $value)
         * as key() would not be called.
         * Moving this check anywhere else would require all internal functions to call values() beforehand and
         * values() itself would have to ignore strict duplicate checks.
         */
        if ($this->strictUniqueKeys) {
            $key = $this->input->key();
            if (\in_array($key, $this->usedKeys)) {
                throw new DuplicateKey();
            }
            $this->usedKeys[] = $key;
        }

        return $this->input->key();
    }

    final public function valid()
    {
        return $this->input->valid();
    }

    final public function rewind()
    {
        /**
         * reset used keys so a strict unique keys check can be run again
         */
        $this->usedKeys = [];

        /**
         * generators cannot be rewound after current() is after the first yield
         * if valid() returns false the generator was closed and thus has to be recreated
         * otherwise it may as well just run through for the first time
         */
        if ($this->inputFactory && $this->input instanceof \Generator) {
            $input = $this->inputFactory;
            $this->input = $input();
        }

        $this->input->rewind();
    }

    final public function jsonSerialize()
    {
        return $this->toArray();
    }

    /**
     * @deprecated
     * @see AbstractCollection::toArray()
     * @return array
     */
    final public function all(): array
    {
        return $this->toArray();
    }

    final public function avg($selector = null)
    {
        $selector = $this->selector($selector);
        $collection = $this->items();

        if ($collection->isEmpty()) {
            throw new EmptyCollection("Cannot calculate average on empty collection");
        }

        return $collection->sum($selector) / $collection->count();
    }

    final public function chunk(int $size, bool $preserveKeys = true): CollectionInterface
    {
        if ($this->count() === 0) {
            return new CollectionCollection([]);
        }

        $collections = \array_chunk($this->toArray(), $size, $preserveKeys);

        $chunks = [];
        foreach ($collections as $chunk) {
            $chunks [] = (clone $this)->input($chunk);
        }

        return (clone $this)->input($chunks);
    }

    ///**
    // * Combines the values of this collection as keys, with values of $collection as values.
    // * The resulting collection has length equal to the size of smaller collection.
    // *
    // * @param array|\Traversable $collection
    // * @return CollectionInterface
    // * @throws ItemNotFound
    // */
    //final public function combine($collection): CollectionInterface
    //{
    //    $generatorFactory = function () use ($keys, $values) {
    //        $keyCollection = new Collection($keys);
    //        $valueIt = new IteratorIterator(new Collection($values));
    //        $valueIt->rewind();
    //
    //        foreach ($keyCollection as $key) {
    //            if (!$valueIt->valid()) {
    //                break;
    //            }
    //
    //            yield $key => $valueIt->current();
    //            $valueIt->next();
    //        }
    //    };
    //
    //    return (clone $this)->input($generatorFactory);
    //
    //    return combine($this->items(), $collection);
    //}

    final public function concat(...$collections): CollectionInterface
    {
        $collection = $this->items();

        $generatorFactory = function () use ($collection, $collections) {
            foreach ($collection as $k => $v) {
                yield $k => $v;
            }
            foreach ($collections as $collection) {
                foreach ($collection as $key => $value) {
                    yield $key => $value;
                }
            }
        };

        return (clone $this)->input($generatorFactory);
    }

    final public function contains($needle): bool
    {
        $collection = $this->items();

        foreach ($collection as $key => $value) {
            if ($value === $needle) {
                return true;
            }
        }

        return false;
    }

    final public function count(): int
    {
        if ($this->count !== null) {
            return $this->count;
        }
        $collection = $this->items();
        $count = 0;

        /**
         * note that omitting $key in the loop will not trigger a DuplicateKeys exception when $strictUniqueKeys is set ...
         *
         * foreach ($collection as $value) {
         *     $count++;
         * }
         *
         * ... the following will, as it will call the \Iterator::key() method where the actual uniqueness check happens
         */
        foreach ($collection as $key => $value) {
            $count++;
        }

        $this->count = $count;

        return $count;
    }

    final public function countBy($selector): CollectionInterface
    {
        return $this->items()
            ->groupBy($selector)
            ->map(function ($value) {
                return $value->count();
            });
    }

    final public function diff(...$collections): CollectionInterface
    {
        $collection = $this->items();

        $valuesToCompare = (clone $this)->input([])
            ->concat(...$collections)
            ->values()
            ->toArray();

        $generatorFactory = function () use ($collection, $valuesToCompare) {
            foreach ($collection as $key => $value) {
                if (!\in_array($value, $valuesToCompare)) {
                    yield $key => $value;
                }
            }
        };

        return (clone $this)->input($generatorFactory);
    }

    final public function distinct(): CollectionInterface
    {
        $collection = $this->items();

        $generatorFactory = function () use ($collection) {
            $distinctValues = [];

            foreach ($collection as $key => $value) {
                if (!\in_array($value, $distinctValues, true)) {
                    $distinctValues[] = $value;
                    yield $key => $value;
                }
            }
        };

        return (clone $this)->input($generatorFactory);
    }

    ///**
    // * A form of slice that returns all but first $numberOfItems items.
    // *
    // * @param int $numberOfItems
    // * @return CollectionInterface
    // */
    //final public function drop($numberOfItems)
    //{
    //    $collection = $this->items();
    //
    //    return $collection->slice($numberOfItems);
    //}

    ///**
    // * Returns a lazy collection with last $numberOfItems items skipped. These are still iterated over, just skipped.
    // *
    // * @param int $numberOfItems
    // * @return CollectionInterface
    // */
    //final public function dropLast($numberOfItems = 1)
    //{
    //    $collection = $this->items();
    //
    //    $generatorFactory = function () use ($collection, $numberOfItems) {
    //        $buffer = [];
    //
    //        foreach ($collection as $key => $value) {
    //            $buffer[] = [$key, $value];
    //
    //            if (\count($buffer) > $numberOfItems) {
    //                $val = \array_shift($buffer);
    //                yield $val[0] => $val[1];
    //            }
    //        }
    //    };
    //
    //    return (clone $this)->input($generatorFactory);
    //}

    ///**
    // * Returns a lazy collection by removing items from this collection until first item for which $callable returns
    // * false.
    // *
    // * @param callable $callable
    // * @return CollectionInterface
    // */
    //final public function dropWhile(callable $callable)
    //{
    //    $collection = $this->items();
    //
    //    $generatorFactory = function () use ($collection, $callable) {
    //        $shouldDrop = true;
    //        foreach ($collection as $key => $value) {
    //            if ($shouldDrop) {
    //                $shouldDrop = $callable($value, $key);
    //            }
    //
    //            if (!$shouldDrop) {
    //                yield $key => $value;
    //            }
    //        }
    //    };
    //
    //    return (clone $this)->input($generatorFactory);
    //}

    final public function each(callable $callable): CollectionInterface
    {
        $collection = $this->items();

        $generatorFactory = function () use ($collection, $callable) {
            foreach ($collection as $key => $value) {
                $callable($value, $key);

                yield $key => $value;
            }
        };

        return (clone $this)->input($generatorFactory);
    }

    final public function every(callable $callable)
    {
        $collection = $this->items();

        foreach ($collection as $key => $value) {
            if (!$callable($value, $key)) {
                return false;
            }
        }

        return true;
    }

    final public function except($keys): CollectionInterface
    {
        $keys = (new Collection($keys))->values()->toArray();
        $collection = $this->items();

        return $collection->reject(function ($value, $key) use ($keys) {
            return \in_array($key, $keys);
        });
    }

    final public function extract($selector): CollectionInterface
    {
        $collection = $this->items();
        $selector = $this->selector($selector);

        $generatorFactory = function () use ($collection, $selector) {
            foreach ($collection as $value) {
                yield $selector($value);
            }
        };

        return (clone $this)->input($generatorFactory);
    }

    final public function filter(callable $callable): CollectionInterface
    {
        if ($callable === null) {
            $callable = function ($value, $key) {
                return (bool)$value;
            };
        }

        $collection = $this->items();

        $generatorFactory = function () use ($collection, $callable) {
            foreach ($collection as $key => $value) {
                if ($callable($value, $key)) {
                    yield $key => $value;
                }
            }
        };

        return (clone $this)->input($generatorFactory);
    }

    final public function find(callable $callable, $default = null)
    {
        $collection = $this->items();

        foreach ($collection as $key => $value) {
            if ($callable($value, $key)) {
                return $value;
            }
        }

        return $default;
    }

    final public function first()
    {
        $collection = $this->items();

        return $collection->values()->get(0);
    }

    final public function flatten(int $depth = -1): CollectionInterface
    {
        $collection = $this->items();

        $generatorFactory = function () use ($collection, $depth) {
            $flattenNextLevel = $depth < 0 || $depth > 0;
            $childLevelsToFlatten = $depth > 0 ? $depth - 1 : $depth;

            foreach ($collection as $key => $value) {
                if ($flattenNextLevel && (\is_array($value) || $value instanceof Traversable)) {
                    $value = (clone $this)->input($value);
                    foreach ($value->flatten($childLevelsToFlatten) as $childKey => $childValue) {
                        yield $childKey => $childValue;
                    }
                } else {
                    yield $key => $value;
                }
            }
        };

        return (clone $this)->input($generatorFactory);
    }

    final public function flip(): CollectionInterface
    {
        $collection = $this->items();

        $generatorFactory = function () use ($collection) {
            foreach ($collection as $key => $value) {
                yield $value => $key;
            }
        };

        return (clone $this)->input($generatorFactory);
    }

    final public function frequencies(): CollectionInterface
    {
        $collection = $this->items();

        return $collection->countBy(function ($value) {
            return $value;
        });
    }

    final public function get($key, $default = null)
    {
        $collection = $this->items();

        foreach ($collection as $valueKey => $value) {
            if ($valueKey === $key) {
                return $value;
            }
        }

        return $default;
    }

    final public function groupBy($selector): CollectionInterface
    {
        $collection = $this->items();
        $selector = $this->selector($selector);

        $result = [];

        foreach ($collection as $key => $value) {
            $newKey = $selector($value);

            $result[$newKey][] = $value;
        }

        return (clone $this)->input($result)->map(function ($entry) {
            return new Collection($entry);
        });
    }

    final public function has($key): bool
    {
        $collection = $this->items();

        return $collection->keys()->contains($key);
    }

    final public function implode($glue = ', ', $selector = null)
    {
        //TODO: return implode();
    }

    final public function indexBy($selector): CollectionInterface
    {
        $selector = $this->selector($selector);
        $collection = $this->items();

        $generatorFactory = function () use ($collection, $selector) {
            foreach ($collection as $key => $value) {
                yield $selector($value, $key) ?? $key => $value;
            }
        };

        return (clone $this)->input($generatorFactory);
    }

    ///**
    // * Returns a lazy collection of first item from first collection, first item from second, second from first and
    // * so on. Accepts any number of collections.
    // *
    // * @param array|\Traversable ...$collections
    // * @return CollectionInterface
    // */
    //final public function interleave(...$collections): CollectionInterface
    //{
    //    $generatorFactory = function () use ($collections) {
    //        /* @var Iterator[] $iterators */
    //        $iterators = array_map(
    //            function ($collection) {
    //                $it = new IteratorIterator(new Collection($collection));
    //                $it->rewind();
    //                return $it;
    //            },
    //            $collections
    //        );
    //
    //        do {
    //            $valid = false;
    //            foreach ($iterators as $it) {
    //                if ($it->valid()) {
    //                    yield $it->key() => $it->current();
    //                    $it->next();
    //                    $valid = true;
    //                }
    //            }
    //        } while ($valid);
    //    };
    //
    //    return (clone $this)->input($generatorFactory);
    //
    //    return interleave($this->items(), ...$collections);
    //}

    ///**
    // * Returns a lazy collection of items of this collection separated by $separator
    // *
    // * @param mixed $separator
    // * @return CollectionInterface
    // */
    //final public function interpose($separator): CollectionInterface
    //{
    //    $generatorFactory = function () use ($collection, $separator) {
    //        foreach (take($collection, 1) as $key => $value) {
    //            yield $key => $value;
    //        }
    //
    //        foreach (drop($collection, 1) as $key => $value) {
    //            yield $separator;
    //            yield $key => $value;
    //        }
    //    };
    //
    //    return (clone $this)->input($generatorFactory);
    //
    //    return interpose($this->items(), $separator);
    //}

    final public function intersect(...$collections): CollectionInterface
    {
        $valuesToCompare = toArray(values(concat(...$collections)));
        $generatorFactory = function () use ($collection, $valuesToCompare) {
            foreach ($collection as $key => $value) {
                if (\in_array($value, $valuesToCompare)) {
                    yield $key => $value;
                }
            }
        };

        return (clone $this)->input($generatorFactory);

        return intersect($this->items(), ...$collections);
    }

    ///**
    // * Return a collection of intersecting collection items
    // *
    // * @param CollectionInterface $collection
    // * @return CollectionInterface
    // */
    //final public function intersect(CollectionInterface $collection): CollectionInterface
    //{
    //    if (!$collection instanceof $this) {
    //        throw new InvalidCollectionException(
    //            \sprintf(
    //                "'collection' must be a '%s', '%s' given",
    //                \get_class($this),
    //                \get_class($collection)
    //            )
    //        );
    //    }
    //
    //    $array = \array_uintersect($this->items, $collection->all(), function ($value1, $value2) {
    //        if ($value1 === $value2) {
    //            return 0;
    //        }
    //
    //        return -1;
    //    });
    //
    //    return new static($array, $this->indexByKey);
    //}

    final public function isEmpty(): bool
    {
        $collection = $this->items();

        foreach ($collection as $value) {
            return false;
        }

        return true;
    }

    final public function isNotEmpty(): bool
    {
        $collection = $this->items();

        return !$collection->isEmpty();
    }

    final public function keys(): CollectionInterface
    {
        $collection = $this->items();

        $generatorFactory = function () use ($collection) {
            foreach ($collection as $key => $value) {
                yield $key;
            }
        };

        return (clone $this)->input($generatorFactory);
    }

    final public function last()
    {
        $collection = $this->items();

        return $collection->reverse()->first();
    }

    final public function map(callable $callable): CollectionInterface
    {
        $collection = $this->items();

        $generatorFactory = function () use ($collection, $callable) {
            foreach ($collection as $key => $value) {
                yield $key => $callable($value, $key);
            }
        };

        return (clone $this)->input($generatorFactory);
    }

    ///**
    // * Returns a lazy collection which is a result of calling map($callable) and then flatten(1)
    // *
    // * @param callable $callable
    // * @return CollectionInterface
    // */
    //final public function mapcat(callable $callable)
    //{
    //    return flatten(map($collection, $callable), 1);
    //
    //    return mapcat($this->items(), $callable);
    //}

    /**
     * Returns maximal value from this collection.
     *
     * @param null $selector
     * @return mixed
     */
    final public function max($selector = null): CollectionInterface
    {
        $result = null;

        foreach ($collection as $value) {
            $result = $value > $result ? $value : $result;
        }

        return $result;

        return \max($this->items());
    }

    ///**
    // * Returns the maximum value by a given selector
    // *
    // * @param callable|string|int $selector
    // * @throws EmptyCollection
    // * @return CollectionInterface
    // */
    //final public function max($selector = null): CollectionInterface
    //{
    //    if ($this->count() === 0) {
    //        throw new EmptyCollection("Can't get the maximum value of an empty collection");
    //    }
    //
    //    $selector = $this->getScalarSelector($selector);
    //
    //    $result = \array_filter($this->callSelectorWithAllResults($selector));
    //    $result = \array_keys($result, \max($result));
    //
    //    $items = [];
    //    foreach ($result as $key) {
    //        $items[] = $this->items[$key];
    //    }
    //
    //    return new static($items, $this->indexByKey);
    //}

    /**
     * Merge another collection into the current collection
     *
     * @param CollectionInterface $collection
     * @return CollectionInterface
     */
    final public function merge(CollectionInterface $collection): CollectionInterface
    {
        //if (!$collection instanceof $this) {
        //    throw new InvalidCollectionException(
        //        \sprintf(
        //            "'collection' must be a '%s', '%s' given",
        //            \get_class($this),
        //            \get_class($collection)
        //        )
        //    );
        //}
        return new static(\array_merge($this->items, $collection->all()), $this->indexByKey);
    }

    /**
     * Returns minimal value from this collection.
     *
     * @param callable|string|int $selector
     * @return mixed
     */
    final public function min($selector): CollectionInterface
    {
        $result = null;
        $hasItem = false;

        foreach ($collection as $value) {
            if (!$hasItem) {
                $hasItem = true;
                $result = $value;
            }

            $result = $value < $result ? $value : $result;
        }

        return $result;

        return \min($this->items());
    }

    ///**
    // * Returns the minimum value of a given selector
    // *
    // * @param callable|string|int $selector
    // * @throws EmptyCollection
    // * @return CollectionInterface
    // */
    //final public function min($selector): CollectionInterface
    //{
    //    if ($this->count() === 0) {
    //        throw new EmptyCollection("Can't get the minimum value of an empty collection");
    //    }
    //
    //    $selector = $this->getScalarSelector($selector);
    //
    //    $result = \array_filter($this->callSelectorWithAllResults($selector));
    //    $result = \array_keys($result, \min($result));
    //
    //    $items = [];
    //    foreach ($result as $key) {
    //        $items[] = $this->items[$key];
    //    }
    //
    //    return new static($items, $this->indexByKey);
    //}

    /**
     * Returns a lazy collection of items associated to any of the keys from $keys.
     *
     * @param array|\Traversable $keys
     * @return CollectionInterface
     */
    final public function only($keys)
    {
        $keys = toArray(values($keys));

        return filter(
            $collection,
            function ($value, $key) use ($keys) {
                return \in_array($key, $keys, true);
            }
        );

        return only($this->items(), $keys);
    }

    ///**
    // * Returns a lazy collection of collections of $numberOfItems items each, at $step step
    // * apart. If $step is not supplied, defaults to $numberOfItems, i.e. the partitions
    // * do not overlap. If a $padding collection is supplied, use its elements as
    // * necessary to complete last partition up to $numberOfItems items. In case there are
    // * not enough padding elements, return a partition with less than $numberOfItems items.
    // *
    // * @param int $numberOfItems
    // * @param int $step
    // * @param array|\Traversable $padding
    // * @return CollectionInterface
    // */
    //final public function partition($numberOfItems, $step = 0, $padding = [])
    //{
    //    $generatorFactory = function () use ($collection, $numberOfItems, $step, $padding) {
    //        $buffer = [];
    //        $itemsToSkip = 0;
    //        $tmpStep = $step ?: $numberOfItems;
    //
    //        foreach ($collection as $key => $value) {
    //            if (\count($buffer) == $numberOfItems) {
    //                yield dereferenceKeyValue($buffer);
    //
    //                $buffer = \array_slice($buffer, $tmpStep);
    //                $itemsToSkip = $tmpStep - $numberOfItems;
    //            }
    //
    //            if ($itemsToSkip <= 0) {
    //                $buffer[] = [$key, $value];
    //            } else {
    //                $itemsToSkip--;
    //            }
    //        }
    //
    //        yield take(
    //            concat(dereferenceKeyValue($buffer), $padding),
    //            $numberOfItems
    //        );
    //    };
    //
    //    return (clone $this)->input($generatorFactory);
    //
    //    return partition($this->items(), $numberOfItems, $step, $padding);
    //}

    ///**
    // * Creates a lazy collection of collections created by partitioning this collection every time $callable will
    // * return different result.
    // *
    // * @param callable $callable
    // * @return CollectionInterface
    // */
    //final public function partitionBy(callable $callable)
    //{
    //    $generatorFactory = function () use ($collection, $callable) {
    //        $result = null;
    //        $buffer = [];
    //
    //        foreach ($collection as $key => $value) {
    //            $newResult = $callable($value, $key);
    //
    //            if (!empty($buffer) && $result != $newResult) {
    //                yield dereferenceKeyValue($buffer);
    //                $buffer = [];
    //            }
    //
    //            $result = $newResult;
    //            $buffer[] = [$key, $value];
    //        }
    //
    //        if (!empty($buffer)) {
    //            yield dereferenceKeyValue($buffer);
    //        }
    //    };
    //
    //    return (clone $this)->input($generatorFactory);
    //
    //    return partitionBy($this->items(), $callable);
    //}

    /**
     * @deprecated
     * @see AbstractCollection::extract()
     * @param callable|string|int $selector
     * @return CollectionInterface
     */
    final public function parts($selector): CollectionInterface
    {
        return $this->extract($selector);
    }

    /**
     * Removes and returns the last collection item
     *
     * @return mixed
     */
    final public function pop()
    {
        $pop = \array_pop($this->items);
        return $pop;
    }

    /**
     * @deprecated
     * @see AbstractCollection::unshift()
     * @param $value
     * @return CollectionInterface
     */
    final public function prepend($value)
    {
        return $this->unshift($value);
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

        foreach ($this->items as $key => $value) {
            if ($callable($value, $key) === true) {
                $filteredItems[] = $value;
                continue;
            }

            $items[] = $value;
        }

        $this->items = $items;

        return new static($filteredItems, $this->indexByKey);
    }

    final public function push($value, $key = null): CollectionInterface
    {
        $collection = $this->items();

        $generatorFactory = function () use ($collection, $value, $key) {
            foreach ($collection as $k => $v) {
                yield $k => $v;
            }

            if ($key === null) {
                yield $value;
            } else {
                yield $key => $value;
            }
        };

        return (clone $this)->input($generatorFactory);
    }

    /**
     * Returns one or more random collection items
     *
     * @param int|null $number Specifies how many random keys to return
     * @return CollectionInterface
     */
    final public function random(int $number = 1)
    {
        \mt_srand();
        $randomKey = \mt_rand(0, $this->count() - 1);

        return $this->get($this->keys()->get($randomKey));
    }

    ///**
    // * Returns one random collection item
    // *
    // * @return Collection|mixed
    // */
    //final public function randomOne()
    //{
    //    return $this->random(1)->first();
    //}

    final public function reduce(callable $callable, $initial = null)
    {
        $collection = $this->items();

        //return \array_reduce($this->items, $callable);

        $carry = (clone $this)->input($initial);

        foreach ($collection as $key => $value) {
            $carry = $callable($carry, $value, $key);
        }

        return (clone $this)->input($carry);

        $result = reduce($this->items(), $callable, $initial);

        return ($convertToCollection && isCollection($result)) ? new Collection($result) : $result;
    }

    ///**
    // * Reduce the collection to single value. Walks from right to left.
    // *
    // * @param callable $callable must take 2 arguments, intermediate value and item from the iterator
    // * @param mixed $startValue
    // * @return mixed
    // */
    //final public function reduceRight(callable $callable, $startValue)
    //{
    //    return reduce(reverse($collection), $callable, $startValue);
    //
    //    $result = reduceRight($this->items(), $callable, $startValue);
    //
    //    return ($convertToCollection && isCollection($result)) ? new Collection($result) : $result;
    //}

    ///**
    // * Returns a lazy collection of reduction steps.
    // *
    // * @param callable $callable
    // * @param mixed $startValue
    // * @return CollectionInterface
    // */
    //final public function reductions(callable $callable, $startValue)
    //{
    //    $generatorFactory = function () use ($collection, $callable, $startValue) {
    //        $tmp = duplicate($startValue);
    //
    //        yield $tmp;
    //        foreach ($collection as $key => $value) {
    //            $tmp = $callable($tmp, $value, $key);
    //            yield $tmp;
    //        }
    //    };
    //
    //    return (clone $this)->input($generatorFactory);
    //
    //    return reductions($this->items(), $callable, $startValue);
    //}

    final public function reject(callable $callable): CollectionInterface
    {
        $collection = $this->items();

        return $collection->filter(function ($value, $key) use ($callable) {
            return !$callable($value, $key);
        });
    }

    ///**
    // * Returns a lazy collection with items from this collection but values that are found in keys of $replacementMap
    // * are replaced by their values.
    // *
    // * @param array|\Traversable $replacementMap
    // * @return CollectionInterface
    // */
    //final public function replace($replacementMap)
    //{
    //    $generatorFactory = function () use ($collection, $replacementMap) {
    //        foreach ($collection as $key => $value) {
    //            $newValue = getOrDefault($replacementMap, $value, $value);
    //            yield $key => $newValue;
    //        }
    //    };
    //
    //    return (clone $this)->input($generatorFactory);
    //
    //    return replace($this->items(), $replacementMap);
    //}

    ///**
    // * Returns a lazy collection with items from $collection, but items with keys that are found in keys of
    // * $replacementMap are replaced by their values.
    // *
    // * @param array|\Traversable $replacementMap
    // * @return CollectionInterface
    // */
    //final public function replaceByKeys($replacementMap)
    //{
    //    $generatorFactory = function () use ($collection, $replacementMap) {
    //        foreach ($collection as $key => $value) {
    //            $newValue = getOrDefault($replacementMap, $key, $value);
    //            yield $key => $newValue;
    //        }
    //    };
    //
    //    return (clone $this)->input($generatorFactory);
    //
    //    return replaceByKeys($this->items(), $replacementMap);
    //}

    /**
     * Returns collection of items in this collection in reverse order.
     *
     * @return CollectionInterface
     */
    final public function reverse(): CollectionInterface
    {
        $generatorFactory = function () use ($collection) {
            $array = [];
            foreach ($collection as $key => $value) {
                $array[] = [$key, $value];
            }

            return map(
                indexBy(
                    \array_reverse($array),
                    function ($item) {
                        return $item[0];
                    }
                ),
                function ($item) {
                    return $item[1];
                }
            );
        };

        return (clone $this)->input($generatorFactory);

        return reverse($this->items());
    }

    ///**
    // * Returns a collection in reverse order
    // *
    // * @return CollectionInterface
    // */
    //final public function reverse(): CollectionInterface
    //{
    //    $items = \array_reverse($this->items);
    //    return new static($items, $this->indexByKey);
    //}

    ///**
    // * Returns the second item in this collection or throws ItemNotFound if the collection is empty or has 1 item.
    // *
    // * @throws ItemNotFound
    // * @return mixed
    // */
    //final public function second()
    //{
    //    return get(values($collection), 1);
    //
    //    $result = second($this->items());
    //
    //    return ($convertToCollection && isCollection($result)) ? new Collection($result) : $result;
    //}

    /**
     * Removes and returns the first collection item
     *
     * @return mixed
     */
    final public function shift()
    {
        $shift = \array_shift($this->items);
        return $shift;
    }

    /**
     * Returns a non-collection of shuffled items from this collection
     *
     * @return CollectionInterface
     */
    final public function shuffle(): CollectionInterface
    {
        $buffer = [];
        foreach ($collection as $key => $value) {
            $buffer[] = [$key, $value];
        }

        \shuffle($buffer);

        return dereferenceKeyValue($buffer);

        return \shuffle($this->items());
    }

    ///**
    // * Shuffles the current collection items
    // *
    // * @return CollectionInterface
    // */
    //final public function shuffle(): CollectionInterface
    //{
    //    $items = $this->items;
    //    \mt_srand();
    //    \usort($items, function () {
    //        return \mt_rand(-1, 1);
    //    });
    //
    //    return new static($items, $this->indexByKey);
    //}

    /**
     * Returns lazy collection items of which are part of the original collection from item number $from to item
     * number $to. The items before $from are also iterated over, just not returned.
     *
     * @param int $from
     * @param int $length If omitted, will slice until end
     * @return CollectionInterface
     */
    final public function slice(int $from, int $length = null): CollectionInterface
    {
        // return new static(\array_slice($this->items, $offset, $length), $this->indexByKey);

        $collection = $this->items();

        $generatorFactory = function () use ($collection, $from, $to) {
            //    $index = 0;
            //    foreach ($collection as $key => $value) {
            //        if ($index >= $from && ($index < $to || $to == -1)) {
            //            yield $key => $value;
            //        } elseif ($index >= $to && $to >= 0) {
            //            break;
            //        }
            //
            //        $index++;
            //    }
        };

        return (clone $this)->input($generatorFactory);
    }

    final public function some(callable $callable): bool
    {
        $collection = $this->items();

        foreach ($collection as $key => $value) {
            if ($callable($value, $key)) {
                return true;
            }
        }

        return false;
    }

    final public function sort($callable = null): CollectionInterface
    {
        $collection = $this->items();

        $items = $collection
            ->map(function ($value, $key) {
                return [$key, $value];
            })
            ->values()
            ->toArray();

        if ($callable === null) {
            $callable = function ($value1, $value2) {
                return $value1 > $value2;
            };
        }

        \uasort(
            $items,
            function ($a, $b) use ($callable) {
                return $callable($a[1], $b[1], $a[0], $b[0]);
            }
        );

        return $this->dereferenceKeyValue($items);
    }

    final public function sortBy($selector): CollectionInterface
    {
        $collection = $this->items();

        return $collection->sort(function ($value1, $value2, $key1, $key2) {
            return $key1 > $key2;
        });
    }

    final public function sortByKeys(): CollectionInterface
    {
        $collection = $this->items();

        return $collection->sort(function ($value1, $value2, $key1, $key2) {
            return $key1 > $key2;
        });
    }

    final public function split(int $groups, bool $preserveKeys = true): CollectionInterface
    {
        return $this->chunk((int)\ceil($this->count() / $groups), $preserveKeys);
    }

    ///**
    // * Returns a collection of [take($position), drop($position)]
    // *
    // * @param int $position
    // * @return CollectionInterface
    // */
    //final public function splitAt($position)
    //{
    //    $generatorFactory = function () use ($collection, $position) {
    //        yield take($collection, $position);
    //        yield drop($collection, $position);
    //    };
    //
    //    return (clone $this)->input($generatorFactory);
    //
    //    return splitAt($this->items(), $position);
    //}

    ///**
    // * Returns a collection of [takeWhile($predicament), dropWhile($predicament]
    // *
    // * @param callable $callable
    // * @return CollectionInterface
    // */
    //final public function splitWith(callable $callable)
    //{
    //    $generatorFactory = function () use ($collection, $callable) {
    //        yield takeWhile($collection, $callable);
    //        yield dropWhile($collection, $callable);
    //    };
    //
    //    return (clone $this)->input($generatorFactory);
    //
    //    return splitWith($this->items(), $callable);
    //}

    final public function sum($selector = null)
    {
        $selector = $this->selector($selector);
        $collection = $this->items();

        $sum = 0;

        foreach ($collection as $value) {
            $sum += $selector($value);
        }

        return $sum;
    }

    final public function take($numberOfItems): CollectionInterface
    {
        $collection = $this->items();

        return $collection->slice( 0, $numberOfItems);
    }

    final public function takeNth($step, $offset = 0): CollectionInterface
    {
        //$items = [];
        //
        //$position = 0;
        //foreach ($this->items as $value) {
        //    if ($position % $step === $offset) {
        //        $items[] = $value;
        //    }
        //    $position++;
        //}
        //return new static($items, $this->indexByKey);

        $collection = $this->items();

        $generatorFactory = function () use ($collection, $step, $offset) {
            $index = 0;
            foreach ($collection as $key => $value) {
                if ($index % $step == 0) {
                    yield $key => $value;
                }

                $index++;
            }
        };

        return (clone $this)->input($generatorFactory);
    }

    /**
     * @deprecated
     * @see AbstractCollection::takeNth()
     * @param int $step
     * @param int $offset
     * @return CollectionInterface
     */
    final public function nth(int $step, $offset = 0): CollectionInterface
    {
        return $this->takeNth($step, $offset);
    }

    ///**
    // * Returns a lazy collection of items from the start of the collection until the first item for which $callable
    // * returns false.
    // *
    // * @param callable $callable
    // * @return CollectionInterface
    // */
    //final public function takeWhile(callable $callable)
    //{
    //    $collection = $this->items();
    //
    //    $generatorFactory = function () use ($collection, $callable) {
    //        $shouldTake = true;
    //        foreach ($collection as $key => $value) {
    //            if ($shouldTake) {
    //                $shouldTake = $callable($value, $key);
    //            }
    //
    //            if ($shouldTake) {
    //                yield $key => $value;
    //            }
    //        }
    //    };
    //
    //    return (clone $this)->input($generatorFactory);
    //}

    final public function toArray(): array
    {
        return \iterator_to_array($this->items());
    }

    final public function transform(callable $transformer): CollectionInterface
    {
        $items = $this->items();

        $transformed = $transformer($items instanceof CollectionInterface ? $items : (clone $this)->input($items));

        if (!($transformed instanceof CollectionInterface)) {
            throw new InvalidReturnValue();
        }

        return $transformed;
    }

    final public function transpose(): CollectionInterface
    {
        $collection = $this->items();

        if ($collection->some(function ($value) {
            return !($value instanceof CollectionInterface);
        })) {
            throw new InvalidArgument('Can only transpose collections of collections');
        }

        return (clone $this)->input();

        return Collection::from(
            \array_map(
                function (...$items) {
                    return new Collection($items);
                },
                ...toArray(
                    map(
                        $collection,
                        'toArray'
                    )
                )
            )
        );
    }

    final public function unshift($value, $key = null): CollectionInterface
    {
        $collection = $this->items();

        $generatorFactory = function () use ($collection, $value, $key) {
            if ($key === null) {
                yield $value;
            } else {
                yield $key => $value;
            }

            foreach ($collection as $key => $value) {
                yield $key => $value;
            }
        };

        return (clone $this)->input($generatorFactory);
    }

    final public function values(): CollectionInterface
    {
        $collection = $this->items();

        $generatorFactory = function () use ($collection) {
            foreach ($collection as $value) {
                yield $value;
            }
        };

        return (clone $this)->input($generatorFactory);
    }

    final public function zip(...$collections): CollectionInterface
    {
        /* @var Iterator[] $iterators */
        $iterators = \array_map(
            function ($collection) {
                $it = new IteratorIterator(new Collection($collection));
                $it->rewind();
                return $it;
            },
            $collections
        );

        $generatorFactory = function () use ($iterators) {
            while (true) {
                $isMissingItems = false;
                $zippedItem = new Collection([]);

                foreach ($iterators as $it) {
                    if (!$it->valid()) {
                        $isMissingItems = true;
                        break;
                    }

                    $zippedItem = append($zippedItem, $it->current(), $it->key());
                    $it->next();
                }

                if (!$isMissingItems) {
                    yield $zippedItem;
                } else {
                    break;
                }
            }
        };

        return (clone $this)->input($generatorFactory);

        \array_unshift($collections, $this->items());

        return zip(...$collections);
    }
}

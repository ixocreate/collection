<?php
/**
 * @link https://github.com/ixocreate
 * @copyright IXOCREATE GmbH
 * @license MIT License
 */

declare(strict_types=1);

namespace Ixocreate\Collection;

use Ixocreate\Collection\Exception\DuplicateKey;
use Ixocreate\Collection\Exception\EmptyCollection;
use Ixocreate\Collection\Exception\InvalidArgument;
use Ixocreate\Collection\Exception\InvalidReturnValue;

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
     * @var array|callable|\Traversable
     */
    private $inputFactory;

    /**
     * @var \Iterator
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
     * @param callable|array|\Traversable $items
     */
    public function __construct($items = [])
    {
        /**
         * The constructor is not final to allow for overrides in specialized collections (for setting defaults etc).
         * For this reason the input is passed to a dedicated setter which can be called after cloning.
         *
         * ```
         * return (clone $this)->input($generator);
         * ```
         */
        return $this->input($items);
    }

    /**
     * @param callable|array|\Traversable $input
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
            $this->input = new \ArrayIterator($input);
        } elseif ($input instanceof \Generator) {
            $this->input = $input;
        } elseif ($input instanceof \Traversable) {
            /**
             * If another Collection is passed as $input and assigned directly this would result in DuplicateKey errors.
             * Wrap it into an IteratorIterator to prevent this.
             */
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
                    throw new InvalidReturnValue('Cannot key object by "' . $selector . '": ' . $exception->getMessage());
                }
            }
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
        $generator = function () use ($collection) {
            foreach ($collection as $value) {
                yield $value[0] => $value[1];
            }
        };

        return (clone $this)->input($generator);
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
        $key = $this->input->key();

        if (!\is_scalar($key)) {
            $key = (string) $key;
        }

        /**
         * Strict indexBy requires keys to be unique - make sure this is the case while keys are being iterated
         * Note that this check will not be run when iterating over the collection with foreach ($collection as $value)
         * as key() would not be called.
         * Moving this check anywhere else would require all internal functions to call values() beforehand and
         * values() itself would have to ignore strict duplicate checks.
         */
        if ($this->strictUniqueKeys) {
            if (\in_array($key, $this->usedKeys, true)) {
                throw new DuplicateKey('Key "' . $key . '" already in use. Either call values() or strictUniqueKeys(false) before you act on the collection.');
            }
            $this->usedKeys[] = $key;
        }

        return $key;
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

    final public function concat(iterable $pushItems): CollectionInterface
    {
        $collection = $this->items();

        $generator = function () use ($collection, $pushItems) {
            foreach ($collection->values() as $value) {
                yield $value;
            }
            foreach ($pushItems as $value) {
                yield $value;
            }
        };

        return (clone $this)->input($generator);
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
         * note that omitting $key in the loop will not trigger a DuplicateKey exception when $strictUniqueKeys is set ...
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

    final public function diff(iterable $compare): CollectionInterface
    {
        $collection = $this->items();

        $valuesToCompare = (new Collection($compare))->values()->toArray();

        $generator = function () use ($collection, $valuesToCompare) {
            foreach ($collection as $key => $value) {
                if (!\in_array($value, $valuesToCompare, true)) {
                    yield $key => $value;
                }
            }
        };

        return (clone $this)->input($generator);
    }

    final public function distinct(): CollectionInterface
    {
        $collection = $this->items();

        $generator = function () use ($collection) {
            $distinctValues = [];

            foreach ($collection as $key => $value) {
                if (!\in_array($value, $distinctValues, true)) {
                    $distinctValues[] = $value;
                    yield $key => $value;
                }
            }
        };

        return (clone $this)->input($generator);
    }

    final public function each(callable $callable): CollectionInterface
    {
        $collection = $this->items();

        $generator = function () use ($collection, $callable) {
            foreach ($collection as $key => $value) {
                $callable($value, $key);

                yield $key => $value;
            }
        };

        return (clone $this)->input($generator);
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

    final public function except(iterable $keys): CollectionInterface
    {
        $keys = (new Collection($keys))->values()->toArray();
        $collection = $this->items();

        return $collection->reject(function ($value, $key) use ($keys) {
            return \in_array($key, $keys, true);
        });
    }

    final public function extract($selector): CollectionInterface
    {
        $collection = $this->items();
        $selector = $this->selector($selector);

        $generator = function () use ($collection, $selector) {
            foreach ($collection as $value) {
                yield $selector($value);
            }
        };

        return (clone $this)->input($generator);
    }

    final public function filter(callable $callable = null): CollectionInterface
    {
        if ($callable === null) {
            $callable = function ($value, $key) {
                return (bool)$value;
            };
        }

        $collection = $this->items();

        $generator = function () use ($collection, $callable) {
            foreach ($collection as $key => $value) {
                if ($callable($value, $key)) {
                    yield $key => $value;
                }
            }
        };

        return (clone $this)->input($generator);
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

        $generator = function () use ($collection, $depth) {
            $flattenNextLevel = $depth < 0 || $depth > 0;
            $childLevelsToFlatten = $depth > 0 ? $depth - 1 : $depth;

            foreach ($collection as $key => $value) {
                if ($flattenNextLevel && (\is_array($value) || $value instanceof \Traversable)) {
                    $value = (clone $this)->input($value);
                    foreach ($value->flatten($childLevelsToFlatten) as $childKey => $childValue) {
                        yield $childKey => $childValue;
                    }
                } else {
                    yield $key => $value;
                }
            }
        };

        return (clone $this)->input($generator);
    }

    final public function flip(): CollectionInterface
    {
        $collection = $this->items();

        $generator = function () use ($collection) {
            foreach ($collection as $key => $value) {
                yield $value => $key;
            }
        };

        return (clone $this)->input($generator);
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
        $collection = $this->items();

        return \implode($glue, $collection->extract($selector)->toArray());
    }

    final public function indexBy($selector): CollectionInterface
    {
        $selector = $this->selector($selector);
        $collection = $this->items();

        $generator = function () use ($collection, $selector) {
            /**
             * Iterate over values to not trigger a DuplicateKey exception while re-indexing.
             */
            foreach ($collection->values() as $key => $value) {
                yield $selector($value, $key) ?? $key => $value;
            }
        };

        return (clone $this)->input($generator);
    }

    final public function intersect(iterable $compare): CollectionInterface
    {
        $collection = $this->items();

        $valuesToCompare = (new Collection($compare))->values()->toArray();

        $generator = function () use ($collection, $valuesToCompare) {
            foreach ($collection as $key => $value) {
                if (\in_array($value, $valuesToCompare, true)) {
                    yield $key => $value;
                }
            }
        };

        return (clone $this)->input($generator);
    }

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

        $generator = function () use ($collection) {
            foreach ($collection as $key => $value) {
                yield $key;
            }
        };

        return (clone $this)->input($generator);
    }

    final public function last()
    {
        $collection = $this->items();

        return $collection->reverse()->first();
    }

    final public function map(callable $callable): CollectionInterface
    {
        $collection = $this->items();

        $generator = function () use ($collection, $callable) {
            foreach ($collection as $key => $value) {
                yield $key => $callable($value, $key);
            }
        };

        return (clone $this)->input($generator);
    }

    final public function max($selector = null)
    {
        $selector = $this->selector($selector);
        $collection = $this->items();

        $result = null;

        foreach ($collection as $value) {
            $value = $selector($value);
            $result = $value > $result ? $value : $result;
        }

        return $result;
    }

    final public function median($selector = null)
    {
        $collection = $this->items();

        $values = $collection->extract($selector)
            ->filter(function ($item) {
                return $item !== null;
            })->sort()->values();

        $count = $values->count();
        if ($count == 0) {
            return null;
        }

        $middle = (int)($count / 2);
        if ($count % 2) {
            return $values->get($middle);
        }

        return (clone $this)->input([$values->get($middle - 1), $values->get($middle)])->avg();
    }

    final public function merge(iterable $items): CollectionInterface
    {
        $collection = $this->items();

        foreach ($items as $key => $value) {
            /**
             * Safely override items by using put() in combination with string keys, new items will be pushed
             */
            $collection = $collection->put($value, \is_string($key) ? $key : null);
        }

        return (clone $this)->input($collection);
    }

    final public function min($selector = null)
    {
        $selector = $this->selector($selector);
        $collection = $this->items();

        $result = null;
        $hasItem = false;

        foreach ($collection as $value) {
            $value = $selector($value);

            if (!$hasItem) {
                $hasItem = true;
                $result = $value;
            }

            $result = $value < $result ? $value : $result;
        }

        return $result;
    }

    final public function only(iterable $keys): CollectionInterface
    {
        $collection = $this->items();
        $keys = (new Collection($keys))->values()->toArray();
        return $collection->filter(function ($value, $key) use ($keys) {
            return \in_array($key, $keys, true);
        });
    }

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
     * @deprecated
     * @see AbstractCollection::unshift()
     * @param $value
     * @return CollectionInterface
     */
    final public function prepend($value)
    {
        return $this->unshift($value);
    }

    final public function push($value, $key = null): CollectionInterface
    {
        $collection = $this->items();

        $generator = function () use ($collection, $value, $key) {
            foreach ($collection as $k => $v) {
                yield $k => $v;
            }

            if ($key !== null) {
                yield $key => $value;
            } else {
                yield $value;
            }
        };

        return (clone $this)->input($generator);
    }

    final public function put($value, $key): CollectionInterface
    {
        $collection = $this->items();

        $generator = function () use ($collection, $value, $key) {
            foreach ($collection as $k => $v) {
                if ($key !== null && $k === $key) {
                    yield $key => $value;
                } else {
                    yield $k => $v;
                }
            }

            if (!$collection->has($key)) {
                if ($key !== null) {
                    yield $key => $value;
                } else {
                    yield $value;
                }
            }
        };

        return (clone $this)->input($generator);
    }

    final public function random(int $number = 1): CollectionInterface
    {
        $randomKeys = \array_rand($this->keys()->flip()->toArray(), $number);

        if ($number <= 1) {
            $randomKeys = [$randomKeys];
        }

        return $this->items()->only($randomKeys);
    }

    final public function reduce(callable $callable, $initial)
    {
        $carry = $initial;

        $collection = $this->items();
        foreach ($collection as $key => $value) {
            $carry = $callable($carry, $value, $key);
        }

        return \is_iterable($carry) ? (clone $this)->input($carry) : $carry;
    }

    final public function reject(callable $callable): CollectionInterface
    {
        $collection = $this->items();

        return $collection->filter(function ($value, $key) use ($callable) {
            return !$callable($value, $key);
        });
    }

    final public function reverse(): CollectionInterface
    {
        $collection = $this->items();

        $generator = function () use ($collection) {
            $array = [];
            foreach ($collection as $key => $value) {
                $array[] = [$key, $value];
            }

            return (new Collection(\array_reverse($array)))
                ->indexBy(function ($item) {
                    return $item[0];
                })->map(function ($item) {
                    return $item[1];
                });
        };

        return (clone $this)->input($generator);
    }

    final public function shuffle(): CollectionInterface
    {
        $collection = $this->items();
        $buffer = [];
        foreach ($collection as $key => $value) {
            $buffer[] = [$key, $value];
        }

        \shuffle($buffer);

        return $this->dereferenceKeyValue($buffer);
    }

    final public function slice(int $offset, int $length = null): CollectionInterface
    {
        $collection = $this->items();

        $generator = function () use ($collection, $offset, $length) {
            $index = 0;
            $from = $offset >= 0 ? $offset : $collection->count() + $offset;
            $to = $length > 0 ? $offset + $length : $collection->count() + $length;
            foreach ($collection as $key => $value) {
                if ($index >= $from && $index < $to) {
                    yield $key => $value;
                } elseif ($index >= $to && $to >= 0) {
                    break;
                }

                $index++;
            }
        };

        return (clone $this)->input($generator);
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
        $selector = $this->selector($selector);

        return $collection->sort(function ($value1, $value2) use ($selector) {
            return $selector($value1) > $selector($value2);
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

        return $collection->slice(0, $numberOfItems);
    }

    final public function takeNth($step, $offset = 0): CollectionInterface
    {
        $collection = $this->items();

        $generator = function () use ($collection, $step, $offset) {
            $index = 0;
            foreach ($collection as $key => $value) {
                if ($index % $step == $offset) {
                    yield $key => $value;
                }

                $index++;
            }
        };

        return (clone $this)->input($generator);
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

    final public function toArray(): array
    {
        return \iterator_to_array($this->items());
    }

    final public function transform(callable $transformer): CollectionInterface
    {
        $collection = $this->items();

        return $transformer($collection);
    }

    final public function transpose(): CollectionInterface
    {
        $collection = $this->items();

        if ($collection->count() < 2 || $collection->some(function ($value) {
            return !($value instanceof CollectionInterface);
        })) {
            throw new InvalidArgument('Can only transpose collections of at least two CollectionInterface items');
        }

        $collections = ($collection->map(function (CollectionInterface $collection) {
            return $collection->toArray();
        })->toArray());

        $transposed = \array_map(
            function (...$items) {
                return new Collection($items);
            },
            ...$collections
        );

        return (clone $this)->input($transposed);
    }

    final public function unshift($value, $key = null): CollectionInterface
    {
        $collection = $this->items();

        $generator = function () use ($collection, $value, $key) {
            if ($key === null) {
                yield $value;
            } else {
                yield $key => $value;
            }

            foreach ($collection as $k => $v) {
                yield $k => $v;
            }
        };

        return (clone $this)->input($generator);
    }

    final public function values(): CollectionInterface
    {
        $collection = $this->items();

        $generator = function () use ($collection) {
            foreach ($collection as $value) {
                yield $value;
            }
        };

        return (clone $this)->input($generator);
    }
}

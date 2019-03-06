<?php
/**
 * @link https://github.com/ixocreate
 * @copyright IXOCREATE GmbH
 * @license MIT License
 */

declare(strict_types=1);

namespace IxocreateTest\Collection;

use Ixocreate\Collection\Collection;
use Ixocreate\Collection\CollectionCollection;
use Ixocreate\Collection\Exception\DuplicateKey;
use Ixocreate\Collection\Exception\InvalidCollection;
use Ixocreate\Collection\Exception\InvalidReturnValue;
use Ixocreate\Collection\Exception\InvalidType;
use Ixocreate\Contract\Collection\CollectionInterface;
use PHPUnit\Framework\TestCase;

class CollectionMethodsTest extends TestCase
{
    private function data()
    {
        return require '../misc/data.php';
    }

    public function setUp()
    {
    }

    public function testAvg()
    {
        $collection = new Collection($this->data());

        $sum = 0;
        foreach ($this->data() as $array) {
            $sum += $array['age'];
        }
        $expected = (float)($sum / \count($this->data()));

        /**
         * string key selector
         */
        $this->assertSame($expected, $collection->avg('age'));

        /**
         * callable key selector
         */
        $this->assertSame($expected, $collection->avg(function ($item) {
            return $item['age'];
        }));

        /**
         * no selector scalar values
         */
        $this->assertSame(3, (new Collection([2, 3, 4]))->avg());
        $this->assertSame(14.1, (new Collection([8, 21, 13.3]))->avg());

        /**
         * pushing another item changes average
         */
        $this->assertSame(2, (new Collection([1, 2, 2, 3]))->avg());
        $this->assertSame(2.2, (new Collection([1, 2, 2, 3]))->push(3)->avg());

        /**
         * no selector non-scalar values
         */
        $this->expectException(InvalidReturnValue::class);
        $this->assertSame(3, $collection->avg());
    }

    public function testChunk()
    {
        $collection = new Collection($this->data());

        /**
         * counts
         */
        $this->assertSame(4, $collection->chunk(4)->count());
        $this->assertSame(2, $collection->chunk(8)->count());
        $this->assertSame(3, $collection->chunk(6)->count());
        $this->assertSame(8, $collection->chunk(2)->count());
        $this->assertSame(1, $collection->chunk(1000)->count());

        /**
         * flatten first level (chunk adds one level) and check that the values amount still matches
         */
        $this->assertSame(\count($this->data()), $collection->chunk(4)->flatten(1)->values()->count());

        /**
         * contents
         */
        $this->assertSame(null, $collection->chunk(4)->get(4));
        $this->assertSame(\array_slice($this->data(), 0, 4), $collection->chunk(4)->first()->toArray());

        /**
         * Not preserving keys
         * Subsequent collections' keys should be reset when not preserving keys
         */
        $chunkedCollection = $collection->chunk(4, false);
        $this->assertSame(\array_slice($this->data(), 4, 4), $chunkedCollection->get(1)->toArray());

        $chunks = [];
        foreach (\array_chunk($this->data(), 4) as $chunk) {
            $chunks[] = $chunk;
        }
        $i = 0;
        foreach ($chunkedCollection as $collection) {
            $this->assertSame($chunks[$i], $collection->toArray());
            $i++;
        }

        /**
         * chunk a modified collection for fun
         */
        $collection = (new Collection($this->data()))
            ->map(function ($item) {
                $item['awesome'] = true;
                return $item;
            })
            ->indexBy('id')
            ->filter(function ($item, $key) {
                return $item['age'] <= 8 || $key === 4;
            });
        $this->assertSame(3, $collection->chunk(1)->count());

        /**
         * When chunked without preserving keys, flattening again should result in duplicate keys
         */
        $this->expectException(DuplicateKey::class);
        $collection->split(4, false)
            ->flatten(1)
            ->toArray();
    }

    public function testConcat()
    {
        $collection = new Collection([1, 3, 3, 2]);

        $collection = $collection
            ->strictUniqueKeys(false)
            ->concat([4, 5]);

        $this->assertSame(6, $collection->values()->count());
        $this->assertSame([4, 5, 3, 2], $collection->toArray());
    }

    public function testContains()
    {
        $collection = new Collection($this->data());
        $this->assertTrue($collection->contains($this->data()[4]));

        $collection = new Collection([1, 3, 3, 2]);
        $this->assertTrue($collection->contains(3));
        $this->assertFalse($collection->contains(true));

    }

    public function testCount()
    {
        $expected = \count($this->data());

        $collection = new Collection($this->data());
        $this->assertSame($expected, $collection->count());

        /**
         * call count() again after adding something to the pipeline to make sure the internal variable is still the same
         */
        $collection = $collection->each(function ($item) {
            // do nothing
        });
        $this->assertSame($expected, $collection->count());

        $collection = new Collection([]);
        $this->assertSame(0, $collection->count());
    }

    public function testCountBy()
    {
        $data = $this->data();
        $expected = [];
        foreach ($data as $datum) {
            if (!isset($expected[$datum['age']])) {
                $expected[$datum['age']] = 0;
            }
            $expected[$datum['age']]++;
        }
        \ksort($expected);

        $collection = new Collection($this->data());

        $this->assertSame($expected, $collection->countBy('age')->sortByKeys()->toArray());
    }

    public function testDiff()
    {
        $collection = new Collection($this->data());
        $data = $this->data();
        $last = \array_pop($data);
        $collection2 = new Collection($data);
        $diff = $collection->diff($collection2);

        $this->assertInstanceOf(Collection::class, $diff);
        $this->assertSame([$last], $diff->values()->toArray());

        $collection = $collection->indexBy('name');
        $data = $this->data();
        $last = \array_pop($data);
        $collection2 = new Collection($data);
        $diff = $collection->diff($collection2);
        $this->assertSame([$last['name'] => $last], $diff->toArray());
    }

    public function testDistinct()
    {
        $collection = new Collection([1, 1, 1, 1, 2, 2, 3, 4, 5, 6]);
        $collection = $collection->distinct();
        $this->assertSame([1, 2, 3, 4, 5, 6], $collection->values()->toArray());

        $data = [];
        foreach ($this->data() as $item) {
            $data [] = $item;
            $data [] = $item;
        }
        $collection = new Collection($data);
        $collection = $collection->distinct();

        $this->assertSame($this->data(), $collection->values()->toArray());
    }

    public function testEach()
    {
        $collection = new Collection($this->data());

        $i = 0;
        $result = [];
        $collection->each(function ($item) use (&$result, &$i) {
            if ($i > 0) {
                return;
            }
            $result[] = $item;
            $i++;
        })->toArray();

        $this->assertSame([$this->data()[0]], $result);
    }

    public function testEvery()
    {
        $collection = new Collection($this->data());

        $allHaveAnId = $collection->every(function ($item) {
            return isset($item['id']);
        });
        $this->assertTrue($allHaveAnId);

        $notAllAreCalledTheSame = $collection->every(function ($item) {
            return $item['name'] === 'Davos Seaworth';
        });
        $this->assertFalse($notAllAreCalledTheSame);
    }

    public function testExcept()
    {
        $reject = [0, 4, 5];
        $expected = [];
        foreach ($this->data() as $key => $item) {
            if (!\in_array($key, $reject)) {
                $expected[] = $item;
            }
        }

        $collection = new Collection($this->data());
        $this->assertSame($expected, $collection->except($reject)->values()->toArray());
    }

    public function testExtract()
    {
        $collection = (new Collection($this->data()))->indexBy('id');
        $data = [];
        foreach ($this->data() as $array) {
            $data[] = $array['name'];
        }

        $this->assertSame($data, $collection->extract("name")->toArray());

        $this->assertSame($data, $collection->extract(function ($item) {
            return $item['name'];
        })->toArray());
    }

    public function testFilter()
    {
        $collection = new Collection($this->data());
        $collection = $collection->filter(function ($item) {
            return $item['age'] < 8;
        });
        $this->assertInstanceOf(Collection::class, $collection);

        $this->assertSame(
            [
                [
                    'id' => 6,
                    'name' => 'Brandon Stark',
                    'age' => 7,
                ],
                [
                    'id' => 15,
                    'name' => 'Brandon Stark Twin',
                    'age' => 7,
                ],
            ],
            $collection->values()->toArray()
        );
    }

    public function testFind()
    {
        $collection = new Collection($this->data());

        $item = $collection->find(function ($item) {
            return $item['name'] === 'Brandon Stark';
        });
        $this->assertSame(
            [
                'id' => 6,
                'name' => 'Brandon Stark',
                'age' => 7,
            ],
            $item
        );

        $item = $collection->find(function ($item) {
            return $item['name'] === 'John Doe';
        });
        $this->assertNull($item);

        $expected = ['id' => 99, 'name' => 'John Doe'];
        $item = $collection->find(function ($item) {
            return $item['name'] === 'John Doe';
        }, $expected);
        $this->assertSame($expected, $item);
    }

    public function testFirst()
    {
        $data = $this->data();
        $first = \array_shift($data);
        $collection = new Collection($this->data());
        $this->assertSame($first, $collection->first());

        $this->assertSame(
            [
                'id' => 5,
                'name' => 'Jon Snow',
                'age' => 14,
            ],
            $collection->filter(function ($item) {
                if ($item['id'] === 5) {
                    return $item;
                }
                return null;
            })->first()
        );

        $this->assertNull($collection->filter(function ($item) {
            if ($item['id'] === 21380) {
                return $item;
            }
            return null;
        })->first());
    }

    public function testFlatten()
    {
        $data = $this->data();

        $collection = new Collection([$data]);
        $this->assertSame($this->data(), $collection->flatten(1)->toArray());

        $collection = new Collection([[$data]]);
        $this->assertSame($this->data(), $collection->flatten(2)->toArray());

        $this->assertSame([$this->data()], $collection->flatten(1)->toArray());
    }

    public function testFlip()
    {
        $data = [
            1 => 'One',
            2 => 'Two',
            3 => 'Three',
            4 => 'Four',
            5 => 'Five',
        ];
        $expected = \array_flip($data);

        $collection = new Collection($data);
        $this->assertSame($expected, $collection->flip()->toArray());
    }

    public function testFrequencies()
    {
        $collection = new Collection([1, 3, 3, 2,]);
        $this->assertSame([1 => 1, 3 => 2, 2 => 1], $collection->frequencies()->toArray());
    }

    public function testGet()
    {
        $collection = (new Collection($this->data()))->indexBy('id');

        $this->assertSame(
            [
                'id' => 12,
                'name' => 'Samwell Tarly',
                'age' => 14,
            ],
            $collection->get(12)
        );

        $this->assertFalse($collection->get("doesntExists", false));
    }

    public function testGroupBy()
    {
        $data = $this->data();
        $expected = [];
        foreach ($data as $datum) {
            $expected[$datum['age']] = $datum;
        }
        $expected = \array_keys($expected);
        \sort($expected);
        $expected = \array_values($expected);

        $collection = new Collection($this->data());
        $this->assertSame($expected, $collection->groupBy('age')->keys()->sort()->values()->toArray());
    }

    public function testHas()
    {
        $collection = (new Collection($this->data()))->indexBy('id');

        $this->assertTrue($collection->has(12));
        $this->assertFalse($collection->has("doesntExists"));
    }

    public function testImplode()
    {
        $collection = new Collection([1, 2, 3]);
        $this->assertSame('1, 2, 3', $collection->implode());
        $this->assertSame('1.2.3', $collection->implode('.'));

        $collection = new Collection($this->data());
        $collection = $collection->filter(function($item){
            return $item['age'] <= 8;
        });
        $this->assertSame('Brandon Stark, Brandon Stark Twin', $collection->implode(', ', 'name'));
    }

    public function testIndexBy()
    {
        /**
         * string key selector
         */
        $collection = (new Collection($this->data()))
            ->indexBy('id');
        $data = [];
        foreach ($this->data() as $array) {
            $data[$array['id']] = $array;
        }
        $this->assertEquals($data, $collection->toArray());

        /**
         * string key selector that would also work as a callable (e.g. global functions)
         */
        $collection = (new Collection($this->data()))
            ->map(function ($item) {
                /**
                 * key is also a callable within the application
                 */
                $item['key'] = $item['id'];
                return $item;
            })
            ->indexBy('key');
        $data = [];
        foreach ($this->data() as $array) {
            $array['key'] = $array['id'];
            $data[$array['key']] = $array;
        }
        $this->assertEquals($data, $collection->toArray());

        /**
         * callable key selector
         */
        $collection = (new Collection($this->data()))
            ->indexBy(function ($item) {
                return $item['id'];
            });
        $data = [];
        foreach ($this->data() as $array) {
            $data[$array['id']] = $array;
        }
        $this->assertEquals($data, $collection->toArray());
    }

    public function testIndexByConstructor()
    {
        $collection = new Collection($this->data(), 'name');
        $collection2 = (new Collection($this->data()))->indexBy('name');

        $this->assertSame($collection->toArray(), $collection2->toArray());
    }

    public function testIndexByFilter()
    {
        $collection = (new Collection($this->data()))
            ->indexBy('id')
            ->filter(function ($item) {
                return $item['age'] < 8;
            });

        $this->assertSame(2, $collection->count());

        $this->assertSame(
            [
                6 => [
                    'id' => 6,
                    'name' => 'Brandon Stark',
                    'age' => 7,
                ],
                15 => [
                    'id' => 15,
                    'name' => 'Brandon Stark Twin',
                    'age' => 7,
                ],
            ],
            $collection->toArray()
        );
    }

    public function testIndexByArrayAccessOffset()
    {
        $data = [];
        foreach ($this->data() as $entry) {
            $data[] = new class($entry) implements \ArrayAccess
            {
                private $data;

                public function __construct(array $data)
                {
                    $this->data = $data;
                }

                public function offsetExists($offset)
                {
                    return isset($this->data[$offset]);
                }

                public function offsetGet($offset)
                {
                    return $this->data[$offset];
                }

                public function offsetSet($offset, $value)
                {
                    $this->data[$offset] = $value;
                }

                public function offsetUnset($offset)
                {
                    unset($this->data[$offset]);
                }
            };
        }

        $expected = [];
        foreach ($data as $datum) {
            $expected[$datum['name']] = $datum;
        }

        $collection = (new Collection($data))
            ->indexBy('name');

        $this->assertSame(
            $expected,
            $collection->toArray()
        );
    }

    public function testIndexByGetterMagicMethod()
    {
        $data = [];
        foreach ($this->data() as $entry) {
            $data[] = new class($entry)
            {
                private $data;

                public function __construct(array $data)
                {
                    $this->data = $data;
                }

                public function __get($name)
                {
                    return $this->data[$name];
                }
            };
        }

        $expected = [];
        foreach ($data as $datum) {
            $expected[$datum->name] = $datum;
        }

        $collection = (new Collection($data))
            ->indexBy('name');

        $this->assertSame($expected, $collection->toArray());
    }

    public function testIndexByPublicObjectProperty()
    {
        $data = [];
        foreach ($this->data() as $entry) {
            $data[] = new class($entry)
            {
                public $id;

                public $name;

                public function __construct(array $data)
                {
                    $this->id = $data['id'];
                    $this->name = $data['name'];
                }
            };
        }

        $expected = [];
        foreach ($data as $datum) {
            $expected[$datum->name] = $datum;
        }

        $collection = (new Collection($data))
            ->indexBy('name');

        $this->assertSame($expected, $collection->toArray());
    }

    public function testIndexByWithDuplicateKeys()
    {
        $collection = (new Collection([['id' => 1], ['id' => 1]]))
            ->strictUniqueKeys(false)
            ->indexBy('id');

        /**
         * toArray() realizes the collection and thus the amount of items gets reduces due to the duplicate key
         */
        $this->assertSame(1, \count($collection->toArray()));

        /**
         * count() does not realize the collection and thus the amount of items stays the same
         */
        $this->assertSame(2, $collection->count());

        /**
         * indexBy with $strict = true (which is default) results in a DuplicateKeys Exception
         */
        $this->expectException(DuplicateKey::class);
        (new Collection([['id' => 1], ['id' => 1]]))
            ->strictUniqueKeys(true)
            ->indexBy('id')
            ->toArray();
    }

    public function testIntersect()
    {
        $collection1 = new Collection($this->data());
        $data = $this->data();
        $last = \array_pop($data);
        $collection2 = new Collection([$last]);
        $collection = $collection1->intersect($collection2);

        $this->assertInstanceOf(Collection::class, $collection);
        $this->assertSame([$last], $collection->toArray());

        $collection1 = (new Collection($this->data()))->indexBy('id');
        $data = $this->data();
        $last = \array_pop($data);
        $collection2 = new Collection([$last]);
        $collection = $collection1->intersect($collection2);
        $this->assertSame([$last['id'] => $last], $collection->toArray());

        $this->expectException(InvalidCollection::class);
        $collection1->intersect(new CollectionCollection([]));
    }

    public function testIsEmpty()
    {
        $collection = new Collection($this->data());
        $this->assertFalse($collection->isEmpty());

        $collection = new Collection([]);
        $this->assertTrue($collection->isEmpty());
    }

    public function testKeys()
    {
        $collection = (new Collection($this->data()))
            ->indexBy('name');
        $data = [];
        foreach ($this->data() as $array) {
            $data[] = $array['name'];
        }

        $this->assertSame($data, $collection->keys()->toArray());
    }

    public function testLast()
    {
        $data = $this->data();
        $last = \array_pop($data);
        $collection = new Collection($this->data());
        $this->assertSame($last, $collection->last());

        $this->assertSame(
            [
                'id' => 5,
                'name' => 'Jon Snow',
                'age' => 14,
            ],
            $collection->last(function ($item) {
                if ($item['id'] === 5) {
                    return $item;
                }
                return null;
            })
        );

        $this->assertNull($collection->last(function ($item) {
            if ($item['id'] === 21380) {
                return $item;
            }
            return null;
        }));
    }

    public function testMax()
    {
        $collection = (new Collection($this->data()))->indexBy('id');
        $oldGuys = [
            [
                'id' => 10,
                'name' => 'Davos Seaworth',
                'age' => 37,
            ],
            [
                'id' => 16,
                'name' => 'Davos Seaworth Twin',
                'age' => 37,
            ],
        ];
        $this->assertEquals(
            (new Collection($oldGuys, 'id'))->toArray(),
            $collection->max('age')->toArray()
        );

        $this->assertInstanceOf(Collection::class, $collection->max('age'));


        $collection = new Collection($this->data());
        $oldGuys = [
            [
                'id' => 10,
                'name' => 'Davos Seaworth',
                'age' => 37,
            ],
            [
                'id' => 16,
                'name' => 'Davos Seaworth Twin',
                'age' => 37,
            ],
        ];
        $this->assertEquals(
            (new Collection($oldGuys))->toArray(),
            $collection->max('age')->toArray()
        );

        $this->expectException(EmptyCollection::class);
        $collection = new Collection([]);
        $collection->max('id');
    }

    /**
     * TODO: add median()
     */
    //public function testMedian()
    //{
    //    $collection = new Collection($this->data());
    //
    //    $median = 0;
    //    foreach ($this->data() as $array) {
    //        $median += $array['age'];
    //    }
    //    $median = (float)($median / \count($this->data()));
    //    $this->assertSame($median, $collection->median('age'));
    //
    //    $this->assertSame($median, $collection->avg(function ($item) {
    //        return $item['age'];
    //    }));
    //
    //    //$collection = new Collection([]);
    //    //$collection->avg('id');
    //}

    public function testMerge()
    {
        $newData = [
            [
                'id' => 256,
                'name' => 'Someone else',
                'age' => 33,
            ],
        ];
        $collection1 = new Collection($this->data());
        $collection2 = new Collection($newData);

        $collection = $collection1->merge($collection2);
        $this->assertInstanceOf(Collection::class, $collection);

        $this->assertSame(
            \array_merge($this->data(), $newData),
            $collection->toArray()
        );

        $newData = [
            [
                'id' => 256,
                'name' => 'Someone else',
                'age' => 33,
            ],
        ];
        $collection1 = (new Collection($this->data()))->indexBy('id');
        $collection2 = new Collection($newData);

        $collection = $collection1->merge($collection2);
        $tmp = \array_merge($this->data(), $newData);
        $newData = [];
        foreach ($tmp as $item) {
            $newData[$item['id']] = $item;
        }

        $this->assertSame(
            $newData,
            $collection->toArray()
        );

        $this->expectException(InvalidCollection::class);
        $collection1->merge(new CollectionCollection([]));
    }

    public function testMin()
    {
        $collection = (new Collection($this->data()))->indexBy('id');

        $youngster = [
            [
                'id' => 6,
                'name' => 'Brandon Stark',
                'age' => 7,
            ],
            [
                'id' => 15,
                'name' => 'Brandon Stark Twin',
                'age' => 7,
            ],
        ];
        $this->assertEquals(
            (new Collection($youngster, 'id'))->toArray(),
            $collection->min('age')->toArray()
        );

        $this->assertInstanceOf(Collection::class, $collection->min('age'));

        $collection = new Collection($this->data());

        $youngster = [
            [
                'id' => 6,
                'name' => 'Brandon Stark',
                'age' => 7,
            ],
            [
                'id' => 15,
                'name' => 'Brandon Stark Twin',
                'age' => 7,
            ],
        ];
        $this->assertEquals(
            (new Collection($youngster))->toArray(),
            $collection->min('age')->toArray()
        );

        $this->expectException(EmptyCollection::class);
        $collection = new Collection([]);
        $collection->min('id');
    }

    public function testNth()
    {
        $collection = new Collection($this->data());
        $collection = $collection->nth(2);

        $this->assertInstanceOf(Collection::class, $collection);

        $items = [];
        for ($i = 0; $i < \count($this->data()); $i++) {
            if ($i % 2 !== 0) {
                continue;
            }
            $items[] = $this->data()[$i];
        }

        $this->assertSame($items, $collection->toArray());

        $collection = new Collection($this->data());
        $collection = $collection->nth(3, 1);

        $items = [];
        for ($i = 0; $i < \count($this->data()); $i++) {
            if ($i % 3 !== 1) {
                continue;
            }
            $items[] = $this->data()[$i];
        }

        $this->assertSame($items, $collection->toArray());

        $collection = (new Collection($this->data()))->indexBy('id');
        $collection = $collection->nth(4, 1);

        $items = [];
        for ($i = 0; $i < \count($this->data()); $i++) {
            if ($i % 4 !== 1) {
                continue;
            }
            $items[$this->data()[$i]['id']] = $this->data()[$i];
        }

        $this->assertSame($items, $collection->toArray());
    }

    public function testPop()
    {
        $collection = new Collection($this->data());

        $data = $this->data();
        $last = \array_pop($data);

        $this->assertSame($last, $collection->pop());
        $this->assertSame($data, $collection->toArray());

        $collection = (new Collection($this->data()))->indexBy('id');

        $data = $this->data();
        $last = \array_pop($data);
        $newData = [];
        foreach ($data as $item) {
            $newData[$item['id']] = $item;
        }

        $this->assertSame($last, $collection->pop());
        $this->assertSame($newData, $collection->toArray());
    }

    public function testUnshift()
    {
        $add = [
            'id' => 256,
            'name' => 'Someone else',
            'age' => 33,
        ];
        $collection = new Collection($this->data());
        $collection->unshift($add);
        $data = $this->data();
        \array_unshift($data, $add);
        $this->assertSame($data, $collection->toArray());

        $add = [
            'id' => 256,
            'name' => 'Someone else',
            'age' => 33,
        ];
        $collection = new Collection($this->data(), "id");
        $collection->unshift($add);
        $data = $this->data();
        \array_unshift($data, $add);
        $newData = [];
        foreach ($data as $item) {
            $newData[$item['id']] = $item;
        }
        $this->assertSame($newData, $collection->toArray());

        $this->expectException(InvalidType::class);
        $collection = new Collection($this->data(), "id");
        $collection->unshift(false);
    }

    public function testPull()
    {
        $collection = new Collection($this->data());
        $pulledCollection = $collection->pull(function ($item) {
            return $item['id'] == 1;
        });

        $this->assertInstanceOf(Collection::class, $pulledCollection);

        $data = $this->data();
        $first = \array_shift($data);

        $this->assertSame($data, $collection->toArray());
        $this->assertSame([$first], $pulledCollection->toArray());


        $collection = (new Collection($this->data()))->indexBy('id');
        $pulledCollection = $collection->pull(function ($item) {
            return $item['id'] == 1;
        });

        $data = $this->data();
        $first = \array_shift($data);
        $newData = [];
        foreach ($data as $item) {
            $newData[$item['id']] = $item;
        }

        $this->assertSame($newData, $collection->toArray());
        $this->assertSame([1 => $first], $pulledCollection->toArray());
    }

    public function testPush()
    {
        $add = [
            'id' => 256,
            'name' => 'Melisandre',
            'age' => 33,
        ];
        $collection = new Collection($this->data());
        $collection = $collection->push($add);
        $data = $this->data();
        \array_push($data, $add);
        $this->assertSame($data, $collection->toArray());

        $add = [
            'id' => 256,
            'name' => 'Melisandre',
            'age' => 33,
        ];
        $collection = (new Collection($this->data()))
            ->push($add)
            ->indexBy('id');
        $data = $this->data();
        \array_push($data, $add);
        $newData = [];
        foreach ($data as $item) {
            $newData[$item['id']] = $item;
        }
        $this->assertSame($newData, $collection->toArray());

        //$this->expectException(InvalidType::class);
        //$collection = new Collection($this->data(), "id");
        //$collection->push(false);
    }

    public function testRandom()
    {
        $collection = (new Collection($this->data()))->indexBy('id');
        $this->assertSame(['id', 'name', 'age'], \array_keys($collection->random()));

        $oneItem = [
            [
                'id' => 12,
                'name' => 'Samwell Tarly',
                'age' => 14,
            ],
        ];
        $collection = new Collection($oneItem, 'id');
        $this->assertSame(\current($oneItem), $collection->random());
    }

    public function testReduce()
    {
        $collection = new Collection($this->data());

        $sumAge = $collection->reduce(function ($carry, $item) {
            return $carry + $item['age'];
        }, 0);

        $this->assertSame((float)$sumAge, $collection->sum('age'));
    }

    public function testReverse()
    {
        $collection = new Collection($this->data());
        $this->assertSame(\array_reverse($this->data()), $collection->reverse()->toArray());

        $this->assertInstanceOf(Collection::class, $collection->reverse());

        $data = [];
        foreach ($this->data() as $array) {
            $data[$array['id']] = $array;
        }
        $collection = (new Collection($this->data()))->indexBy('id');
        $this->assertSame(\array_reverse($data, true), $collection->reverse()->toArray());
    }

    public function testShift()
    {
        $collection = new Collection($this->data());

        $data = $this->data();
        $first = \array_shift($data);

        $this->assertSame($first, $collection->shift());
        $this->assertSame($data, $collection->toArray());

        $collection = (new Collection($this->data()))->indexBy('id');

        $data = $this->data();
        $first = \array_shift($data);
        $newData = [];
        foreach ($data as $item) {
            $newData[$item['id']] = $item;
        }

        $this->assertSame($first, $collection->shift());
        $this->assertSame($newData, $collection->toArray());
    }

    public function testShuffle()
    {
        $collection = new Collection($this->data());
        $collectionShuffle = $collection->shuffle();
        $this->assertSame($collection->count(), $collectionShuffle->count());

        $this->assertInstanceOf(Collection::class, $collectionShuffle);

        $collection1 = $collection->sort(function ($item1, $item2) {
            return $item1['id'] - $item2['id'];
        });

        $collection2 = $collectionShuffle->sort(function ($item1, $item2) {
            return $item1['id'] - $item2['id'];
        });
        $this->assertSame($collection1->toArray(), $collection2->toArray());


        $collection = (new Collection($this->data()))->indexBy('id');
        $collectionShuffle = $collection->shuffle();
        $this->assertSame($collection->count(), $collectionShuffle->count());

        $collection1 = $collection->sort(function ($item1, $item2) {
            return $item1['id'] - $item2['id'];
        });

        $collection2 = $collectionShuffle->sort(function ($item1, $item2) {
            return $item1['id'] - $item2['id'];
        });
        $this->assertSame($collection1->toArray(), $collection2->toArray());
    }

    public function testSlice()
    {
        $collection = new Collection($this->data());
        $this->assertSame(
            [
                [
                    'id' => 16,
                    'name' => 'Davos Seaworth Twin',
                    'age' => 37,
                ],
            ],
            $collection->slice(15)->toArray()
        );

        $this->assertInstanceOf(Collection::class, $collection->slice(15));

        $collection = (new Collection($this->data()))->indexBy('id');
        $this->assertSame(
            [
                16 => [
                    'id' => 16,
                    'name' => 'Davos Seaworth Twin',
                    'age' => 37,
                ],
            ],
            $collection->slice(15)->toArray()
        );


        $collection = new Collection($this->data());
        $this->assertSame(
            [
                [
                    'id' => 16,
                    'name' => 'Davos Seaworth Twin',
                    'age' => 37,
                ],
            ],
            $collection->slice(-1)->toArray()
        );

        $collection = (new Collection($this->data()))->indexBy('id');
        $this->assertSame(
            [
                16 => [
                    'id' => 16,
                    'name' => 'Davos Seaworth Twin',
                    'age' => 37,
                ],
            ],
            $collection->slice(-1)->toArray()
        );

        $collection = new Collection($this->data());
        $this->assertSame(
            [
                [
                    'id' => 2,
                    'name' => 'Catelyn Stark',
                    'age' => 33,
                ],
            ],
            $collection->slice(1, 1)->toArray()
        );

        $collection = (new Collection($this->data()))->indexBy('id');
        $this->assertSame(
            [
                2 => [
                    'id' => 2,
                    'name' => 'Catelyn Stark',
                    'age' => 33,
                ],
            ],
            $collection->slice(1, 1)->toArray()
        );

        $collection = new Collection($this->data());
        $this->assertSame(
            [
                [
                    'id' => 15,
                    'name' => 'Brandon Stark Twin',
                    'age' => 7,
                ],
            ],
            $collection->slice(14, -1)->toArray()
        );

        $collection = (new Collection($this->data()))->indexBy('id');
        $this->assertSame(
            [
                15 => [
                    'id' => 15,
                    'name' => 'Brandon Stark Twin',
                    'age' => 7,
                ],
            ],
            $collection->slice(14, -1)->toArray()
        );

        $collection = new Collection($this->data());
        $this->assertSame(
            [
                [
                    'id' => 15,
                    'name' => 'Brandon Stark Twin',
                    'age' => 7,
                ],
            ],
            $collection->slice(-2, -1)->toArray()
        );

        $collection = (new Collection($this->data()))->indexBy('id');
        $this->assertSame(
            [
                15 => [
                    'id' => 15,
                    'name' => 'Brandon Stark Twin',
                    'age' => 7,
                ],
            ],
            $collection->slice(-2, -1)->toArray()
        );
    }

    public function testSort()
    {
        $collection = new Collection($this->data());
        $collection = $collection->sort(function ($item1, $item2) {
            return $item1['age'] - $item2['age'];
        });

        $this->assertInstanceOf(Collection::class, $collection);

        $data = $this->data();
        \usort($data, function ($item1, $item2) {
            return $item1['age'] - $item2['age'];
        });

        $this->assertSame($data, $collection->toArray());


        $collection = (new Collection($this->data()))->indexBy('id');
        $collection = $collection->sort(function ($item1, $item2) {
            return $item1['age'] - $item2['age'];
        });

        $data = $this->data();
        \usort($data, function ($item1, $item2) {
            return $item1['age'] - $item2['age'];
        });
        $newData = [];
        foreach ($data as $item) {
            $newData[$item['id']] = $item;
        }

        $this->assertSame($newData, $collection->toArray());
    }

    public function testSplit()
    {
        $chunkedCollection = (new Collection($this->data()))
            ->split(4, true);

        $this->assertInstanceOf(Collection::class, $chunkedCollection);
        $this->assertSame(4, $chunkedCollection->count());
        $this->assertSame(\count($this->data()), $chunkedCollection->flatten(1)->values()->count());

        /**
         * Contents are still the same
         */
        $chunks = [];
        foreach (\array_chunk($this->data(), 4, true) as $chunk) {
            $chunks[] = $chunk;
        }
        $i = 0;
        /** @var CollectionInterface $collection */
        foreach ($chunkedCollection as $collection) {
            $this->assertSame($chunks[$i], $collection->toArray());
            $i++;
        }

        /**
         * Contents and keys are still the same when indexed
         */
        $chunkedCollection = (new Collection($this->data()))
            ->indexBy('name')
            ->split(4, true);

        $this->assertSame(4, $chunkedCollection->count());
        $this->assertSame(\count($this->data()), $chunkedCollection->flatten(1)->values()->count());

        $data = [];
        foreach ($this->data() as $key => $array) {
            $data[$array['name']] = $array;
        }

        $chunks = [];
        foreach (\array_chunk($data, 4, true) as $chunk) {
            $chunks[] = $chunk;
        }
        $i = 0;
        /** @var CollectionInterface $collection */
        foreach ($chunkedCollection as $collection) {
            $this->assertSame($chunks[$i], $collection->toArray());
            $i++;
        }

        $collection = (new Collection([]))->indexBy('id');
        $chunkedCollection = $collection->split(4);
        $this->assertSame(0, $chunkedCollection->count());

        /**
         * When split without preserving keys, flattening it again should result in duplicate keys
         */
        $this->expectException(DuplicateKey::class);
        (new Collection($this->data()))
            ->split(4, false)
            ->flatten(1)
            ->toArray();
    }

    public function testSum()
    {
        $collection = new Collection($this->data());

        $sum = 0;
        foreach ($this->data() as $array) {
            $sum += $array['age'];
        }
        $this->assertSame($sum, $collection->sum('age'));

        /**
         * Indexed by duplicate key still sums it all up
         */
        $collection = (new Collection([
            [
                'id' => 1,
                'age' => 20,
            ],
            [
                'id' => 1,
                'age' => 20,
            ],
        ]))->indexBy('id');

        $this->assertSame(40, $collection->sum('age'));

        /**
         * No selector required for scalar values
         */
        $this->assertSame(9, (new Collection([2, 3, 4]))->sum());

        /**
         * Selector has to be present for non-scalar values
         */
        $this->expectException(InvalidReturnValue::class);
        $this->assertSame(3, $collection->sum());

        /**
         * Empty collection is valid
         */
        $collection = new Collection([]);
        $this->assertSame(0, $collection->sum());

        /**
         * int/float casting remains dynamic
         */
        $collection = new Collection([0.1, 8.3]);
        $this->assertSame(8.4, $collection->sum());
    }

    public function testValues()
    {
        $collection = (new Collection($this->data()))
            ->indexBy(function ($item) {
                return $item['name'];
            });
        $data = [];
        foreach ($this->data() as $array) {
            $data[$array['name']] = $array;
        }
        $this->assertEquals(\array_values($data), $collection->values()->toArray());

        $collection = (new Collection($this->data()))
            ->indexBy(function ($item) {
                return $item['id'];
            });
        $data = [];
        foreach ($this->data() as $array) {
            $data[$array['id']] = $array;
        }
        $this->assertEquals(\array_values($data), $collection->values()->toArray());

        $collection = new Collection($this->data());
        $this->assertEquals($this->data(), $collection->toArray());
        $this->assertEquals($this->data(), $collection->values()->toArray());
        $this->assertEquals($collection->toArray(), $collection->values()->toArray());
    }
}

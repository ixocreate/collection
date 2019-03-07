<?php
/**
 * @link https://github.com/ixocreate
 * @copyright IXOCREATE GmbH
 * @license MIT License
 */

declare(strict_types=1);

namespace IxocreateTest\Collection;

use Ixocreate\Collection\Collection;
use Ixocreate\Collection\Exception\DuplicateKey;
use Ixocreate\Collection\Exception\EmptyCollection;
use Ixocreate\Collection\Exception\InvalidArgument;
use Ixocreate\Collection\Exception\InvalidReturnValue;
use Ixocreate\Contract\Collection\CollectionInterface;
use PHPUnit\Framework\TestCase;

class CollectionMethodsTest extends TestCase
{
    private function data()
    {
        return require __DIR__ . '/../misc/data.php';
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
    }

    public function testAvgWithEmptyCollection() {
        /**
         * no selector non-scalar values
         */
        $this->expectException(EmptyCollection::class);
        (new Collection())->avg();
    }

    public function testAvgWithNonScalarValuesAndNoSelector() {
        /**
         * no selector non-scalar values
         */
        $this->expectException(InvalidReturnValue::class);
        (new Collection($this->data()))->avg();
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
        $collection = $collection->concat([4, 5]);

        $this->assertSame(6, $collection->count());
        $this->assertSame([1, 3, 3, 2, 4, 5], $collection->toArray());

        $collection = new Collection(['John Doe']);
        $collection = $collection->concat(['Jane Doe'])->concat(['name' => 'Johnny Doe']);
        $this->assertSame(['John Doe', 'Jane Doe', 'Johnny Doe'], $collection->toArray());

        $collection = new Collection(['name' => 'John Doe']);
        $collection = $collection->concat(['Jane Doe'])->concat(['name' => 'Johnny Doe']);
        $this->assertSame(['John Doe', 'Jane Doe', 'Johnny Doe'], $collection->toArray());
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

        $collection = new Collection([null, true, false, 1, 2, 0, 99, '1', '0', '-1', 'false']);
        $this->assertSame([true, 1, 2, 99, '1', '-1', 'false'], $collection->filter()->values()->toArray());
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
        $collection = $collection->filter(function ($item) {
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
            $data[] = new class($entry) implements \ArrayAccess {
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
            $data[] = new class($entry) {
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
            $data[] = new class($entry) {
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

    public function testIndexByWithDuplicateKey()
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
         * indexBy with $strict = true (which is default) results in a DuplicateKey exception
         */
        $this->expectException(DuplicateKey::class);
        (new Collection([['id' => 1], ['id' => 1]]))
            ->strictUniqueKeys(true)
            ->indexBy('id')
            ->toArray();
    }

    public function testIntersect()
    {
        $data = $this->data();
        $collection1 = new Collection($data);
        $last = \array_pop($data);
        $collection2 = new Collection([$last]);
        $collection = $collection1->intersect($collection2);
        $this->assertSame([$last], $collection->values()->toArray());

        $data = $this->data();
        $collection1 = new Collection($data, 'id');
        $collection2 = new Collection([$last]);
        $collection = $collection1->intersect($collection2);
        $this->assertSame([$last['id'] => $last], $collection->toArray());
    }

    public function testIsEmpty()
    {
        $collection = new Collection($this->data());
        $this->assertFalse($collection->isEmpty());

        $collection = new Collection([]);
        $this->assertTrue($collection->isEmpty());
    }

    public function testIsNotEmpty()
    {
        $collection = new Collection($this->data());
        $this->assertTrue($collection->isNotEmpty());

        $collection = new Collection([]);
        $this->assertFalse($collection->isNotEmpty());
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
        $collection = new Collection($this->data());
        $last = \array_pop($data);
        $this->assertSame($last, $collection->last());
    }

    public function testMap()
    {
        $collection = (new Collection([1, 3, 3, 2,]))->map(function ($value) {
            return $value + 1;
        });

        $this->assertSame([2, 4, 4, 3], $collection->toArray());
    }

    public function testMax()
    {
        $collection = (new Collection($this->data()));
        $this->assertEquals(37, $collection->max('age'));

        $collection = (new Collection([42, 1337, 52]));
        $this->assertEquals(1337, $collection->max());

        $collection = (new Collection());
        $this->assertNull($collection->max());
    }

    public function testMedian()
    {
        $collection = new Collection($this->data());
        $this->assertSame(17.5, $collection->median('age'));

        $collection = new Collection([42, 1337, 52]);
        $this->assertSame(52, $collection->median());

        $collection = new Collection([5, 10, 5, 10]);
        $this->assertSame(7.5, $collection->median());

        $collection = (new Collection());
        $this->assertNull($collection->median());
    }

    public function testMerge()
    {
        /**
         * integer keys get appended
         */
        $new = [
            [
                'name' => 'Someone else',
            ],
        ];
        $expected = \array_merge($this->data(), $new);

        /**
         * merge with Collection
         */
        $collection = (new Collection($this->data()))->merge(new Collection($new));
        $this->assertSame($expected, $collection->toArray());

        /**
         * merge with array
         */
        $collection = (new Collection($this->data()))->merge($new);
        $this->assertSame($expected, $collection->toArray());

        /**
         * string keys overwrite if exist
         */
        $new = [
            'Davos Seaworth' => [
                'id' => 99,
                'name' => 'Infant Davos Seaworth',
                'age' => 3,
            ],
        ];
        $data = [];
        foreach ($this->data() as $datum) {
            $data[$datum['name']] = $datum;
        }
        $expected = \array_merge($data, $new);

        $collection = (new Collection($this->data(), 'name'))->merge(new Collection($new));
        $this->assertSame($expected, $collection->toArray());

        $collection = (new Collection($this->data(), 'name'))->merge($new);
        $this->assertSame($expected, $collection->toArray());

        /**
         * string keys append if not exist
         */
        $newData = [
            'Sandor Clegane' => [
                'name' => 'Sandor Clegane',
            ],
        ];
        $indexedData = [];
        foreach ($this->data() as $datum) {
            $indexedData[$datum['name']] = $datum;
        }
        $expected = \array_merge($indexedData, $newData);

        $collection = (new Collection($this->data(), 'name'))->merge(new Collection($newData));
        $this->assertSame($expected, $collection->toArray());

        $collection = (new Collection($this->data(), 'name'))->merge($newData);
        $this->assertSame($expected, $collection->toArray());
    }

    public function testMin()
    {
        $collection = (new Collection($this->data()));
        $this->assertEquals(7, $collection->min('age'));

        $collection = (new Collection([42, 1337, 52]));
        $this->assertEquals(42, $collection->min());

        $collection = (new Collection());
        $this->assertNull($collection->min());
    }

    public function testOnly()
    {
        $data = $this->data();
        $expected = [0 => $data[0], 3 => $data[3], 5 => $data[5]];
        $collection = (new Collection($data));
        $this->assertEquals($expected, $collection->only([0, 3, 5])->toArray());
    }

    //public function testPop()
    //{
    //    $collection = new Collection($this->data());
    //
    //    $data = $this->data();
    //    $last = \array_pop($data);
    //
    //    $this->assertSame($last, $collection->pop());
    //    $this->assertSame($data, $collection->toArray());
    //
    //    $collection = (new Collection($this->data()))->indexBy('id');
    //
    //    $data = $this->data();
    //    $last = \array_pop($data);
    //    $newData = [];
    //    foreach ($data as $item) {
    //        $newData[$item['id']] = $item;
    //    }
    //
    //    $this->assertSame($last, $collection->pop());
    //    $this->assertSame($newData, $collection->toArray());
    //}

    //public function testPull()
    //{
    //    $collection = new Collection($this->data());
    //    $pulledCollection = $collection->pull(function ($item) {
    //        return $item['id'] == 1;
    //    });
    //
    //    $data = $this->data();
    //    $first = \array_shift($data);
    //
    //    $this->assertSame($data, $collection->toArray());
    //    $this->assertSame([$first], $pulledCollection->toArray());
    //
    //
    //    $collection = (new Collection($this->data()))->indexBy('id');
    //    $pulledCollection = $collection->pull(function ($item) {
    //        return $item['id'] == 1;
    //    });
    //
    //    $data = $this->data();
    //    $first = \array_shift($data);
    //    $newData = [];
    //    foreach ($data as $item) {
    //        $newData[$item['id']] = $item;
    //    }
    //
    //    $this->assertSame($newData, $collection->toArray());
    //    $this->assertSame([1 => $first], $pulledCollection->toArray());
    //}

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

        /**
         * push to not yet existing key
         */
        $add = [
            'id' => 256,
            'name' => 'Melisandre',
            'age' => 33,
        ];
        $data = $this->data();
        $data[99] = $add;
        $collection = (new Collection($this->data()))->push($add, 99);
        $this->assertSame($data, $collection->toArray());
    }

    public function testPut()
    {
        /**
         * put to existing key replaces value
         */
        $add = [
            'id' => 256,
            'name' => 'Melisandre',
            'age' => 33,
        ];
        $collection = (new Collection($this->data()))
            ->put($add, 0);
        $data = $this->data();
        $data[0] = $add;
        $this->assertSame($data, $collection->toArray());

        /**
         * put to not yet existing key adds it
         */
        $add = [
            'id' => 256,
            'name' => 'Melisandre',
            'age' => 33,
        ];
        $data = $this->data();
        $data[99] = $add;
        $collection = (new Collection($this->data()))->put($add, 99);
        $this->assertSame($data, $collection->toArray());

        /**
         * providing null key works the same as push
         */
        $add = [
            'id' => 256,
            'name' => 'Melisandre',
            'age' => 33,
        ];
        $data = $this->data();
        \array_push($data, $add);
        $collection = (new Collection($this->data()))->put($add, null);
        $this->assertSame($data, $collection->toArray());
    }

    public function testRandom()
    {
        $collection = (new Collection($this->data()))->indexBy('id');
        $randomItem = $collection->random()->first();
        $this->assertSame(['id', 'name', 'age'], \array_keys($randomItem));

        $oneItem = [
            [
                'id' => 12,
                'name' => 'Samwell Tarly',
                'age' => 14,
            ],
        ];
        $collection = new Collection($oneItem, 'id');
        $this->assertSame(\current($oneItem), $collection->random()->first());
    }

    public function testReduce()
    {
        $collection = new Collection($this->data(), 'id');

        $expected = $collection->sum('age') + $collection->sum('id');

        /**
         * reduce to sum of all ages and sum of numeric index value
         */
        $reduced = $collection->reduce(function ($carry, $item, $key) {
            return $carry + $item['age'] + $key;
        }, 0);

        $this->assertSame($expected, $reduced);
    }

    public function testReverse()
    {
        $data = $this->data();
        $expected = \array_reverse($data, true);

        $collection = new Collection($data);
        $this->assertSame($expected, $collection->reverse()->toArray());

        $data = [];
        foreach ($this->data() as $array) {
            $data[$array['id']] = $array;
        }
        $collection = (new Collection($this->data()))->indexBy('id');
        $this->assertSame(\array_reverse($data, true), $collection->reverse()->toArray());
    }

    //public function testShift()
    //{
    //    $collection = new Collection($this->data());
    //
    //    $data = $this->data();
    //    $first = \array_shift($data);
    //
    //    $this->assertSame($first, $collection->shift());
    //    $this->assertSame($data, $collection->toArray());
    //
    //    $collection = (new Collection($this->data()))->indexBy('id');
    //
    //    $data = $this->data();
    //    $first = \array_shift($data);
    //    $newData = [];
    //    foreach ($data as $item) {
    //        $newData[$item['id']] = $item;
    //    }
    //
    //    $this->assertSame($first, $collection->shift());
    //    $this->assertSame($newData, $collection->toArray());
    //}

    public function testShuffle()
    {
        $collection = new Collection($this->data());
        $collectionShuffle = $collection->shuffle();
        $this->assertSame($collection->count(), $collectionShuffle->count());

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
            $collection->slice(15)->values()->toArray()
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
            $collection->slice(-1)->values()->toArray()
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
            $collection->slice(1, 1)->values()->toArray()
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
            $collection->slice(14, -1)->values()->toArray()
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
            $collection->slice(-2, -1)->values()->toArray()
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

        /**
         * preserve index (uasort)
         */
        $data = $this->data();
        \uasort($data, function ($item1, $item2) {
            return $item1['age'] - $item2['age'];
        });
        $this->assertSame($data, $collection->toArray());

        /**
         * do not preserve index (usort)
         */
        $data = $this->data();
        \usort($data, function ($item1, $item2) {
            return $item1['age'] - $item2['age'];
        });
        $this->assertSame($data, $collection->values()->toArray());

        /**
         * indexed
         */
        $collection = (new Collection($this->data()))->indexBy('id');
        $collection = $collection->sort(function ($item1, $item2) {
            return $item1['age'] - $item2['age'];
        });
        $data = $this->data();
        \uasort($data, function ($item1, $item2) {
            return $item1['age'] - $item2['age'];
        });
        $newData = [];
        foreach ($data as $item) {
            $newData[$item['id']] = $item;
        }
        $this->assertSame($newData, $collection->toArray());
    }

    public function testSortBy()
    {
        $collection = new Collection($this->data());
        $sortBySelector = $collection->sortBy('age');
        $sortByCallable = $collection->sort(function ($item1, $item2) {
            return $item1['age'] - $item2['age'];
        });
        /**
         * preserving keys
         */
        $this->assertSame($sortBySelector->toArray(), $sortByCallable->toArray());
        /**
         * reindex
         */
        $this->assertSame($sortBySelector->values()->toArray(), $sortByCallable->values()->toArray());
    }

    public function testSplit()
    {
        $chunkedCollection = (new Collection($this->data()))
            ->split(4, true);

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

    public function testTake()
    {
        $data = $this->data();
        $expected = [];
        for ($i = 0; $i < 2; $i++) {
            $expected[] = $data[$i];
        }

        $collection = new Collection($this->data());
        $collection = $collection->take(2);

        $this->assertSame($expected, $collection->toArray());
    }

    public function testTakeNth()
    {
        $collection = new Collection($this->data());
        $collection = $collection->takeNth(2);

        $expected = [];
        for ($i = 0; $i < \count($this->data()); $i++) {
            if ($i % 2 !== 0) {
                continue;
            }
            $expected[] = $this->data()[$i];
        }

        $this->assertSame($expected, $collection->values()->toArray());

        $collection = new Collection($this->data());
        $collection = $collection->takeNth(3, 1);

        $expected = [];
        for ($i = 0; $i < \count($this->data()); $i++) {
            if ($i % 3 !== 1) {
                continue;
            }
            $expected[] = $this->data()[$i];
        }

        $this->assertSame($expected, $collection->values()->toArray());

        $collection = (new Collection($this->data()))->indexBy('id');
        $collection = $collection->takeNth(4, 1);

        $expected = [];
        for ($i = 0; $i < \count($this->data()); $i++) {
            if ($i % 4 !== 1) {
                continue;
            }
            $expected[$this->data()[$i]['id']] = $this->data()[$i];
        }

        $this->assertSame($expected, $collection->toArray());
    }

    /**
     * Example of implementing a transpose function and how to apply it over a collection.
     *
     * For more on how this can be useful: http://adamwathan.me/2016/04/06/cleaning-up-form-input-with-transpose/
     */
    public function testTransform()
    {
        $formData = [
            'names' => [
                'Jane',
                'Bob',
                'Mary',
            ],
            'emails' => [
                'jane@example.com',
                'bob@example.com',
                'mary@example.com',
            ],
            'occupations' => [
                'Doctor',
                'Plumber',
                'Dentist',
            ],
        ];

        $transpose = function (Collection $collections) {
            $transposed = \array_map(
                function (...$items) {
                    return $items;
                },
                ...$collections->values()->toArray()
            );

            return new Collection($transposed);
        };

        $result = (new Collection($formData))
            ->transform($transpose)
            ->toArray();

        $expected = [
            [
                'Jane',
                'jane@example.com',
                'Doctor',
            ],
            [
                'Bob',
                'bob@example.com',
                'Plumber',
            ],
            [
                'Mary',
                'mary@example.com',
                'Dentist',
            ],
        ];

        $this->assertEquals($expected, $result);
    }

    /**
     * @see testTransform() but with implicit transpose() call
     */
    public function testTranspose()
    {
        $data = [
            new Collection([1, 2, 3]),
            new Collection([4, 5, new Collection(['foo', 'bar'])]),
            new Collection([7, 8, 9]),
        ];

        $result = (new Collection($data))
            ->transpose()
            ->toArray();

        $expected = [
            new Collection([1, 4, 7]),
            new Collection([2, 5, 8]),
            new Collection([3, new Collection(['foo', 'bar']), 9]),
        ];

        $this->assertEquals($expected, $result);
    }

    public function testTransposeWithEmptyCollection()
    {
        $this->expectException(InvalidArgument::class);
        (new Collection())
            ->transpose()
            ->toArray();
    }

    public function testTransposeWithNotEnoughItems()
    {
        $this->expectException(InvalidArgument::class);
        (new Collection([new Collection()]))
            ->transpose()
            ->toArray();
    }

    public function testTransposeWithNonCollection()
    {
        $this->expectException(InvalidArgument::class);
        (new Collection([new Collection(), []]))
            ->transpose()
            ->toArray();
    }

    public function testUnshift()
    {
        $add = [
            'id' => 256,
            'name' => 'Someone else',
            'age' => 33,
        ];
        $data = $this->data();
        \array_unshift($data, $add);
        $collection = (new Collection($this->data()))->unshift($add)->values();
        $this->assertSame($data, $collection->toArray());

        $add = [
            'id' => 256,
            'name' => 'Someone else',
            'age' => 33,
        ];
        $data = $this->data();
        \array_unshift($data, $add);
        $collection = (new Collection($this->data()))->unshift($add, 256)->values();
        $this->assertSame($data, $collection->toArray());

        $add = [
            'id' => 256,
            'name' => 'Someone else',
            'age' => 33,
        ];
        $data = $this->data();
        \array_unshift($data, $add);
        foreach ($data as $item) {
            $newData[$item['id']] = $item;
        }
        $collection = (new Collection($this->data(), 'name'))->unshift($add)->indexBy('id');
        $this->assertSame($newData, $collection->toArray());
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

<?php
/**
 * @link https://github.com/ixocreate
 * @copyright IXOCREATE GmbH
 * @license MIT License
 */

declare(strict_types=1);

namespace IxocreateTest\Entity\Collection;

use Ixocreate\Collection\Collection;
use Ixocreate\Collection\CollectionCollection;
use Ixocreate\Collection\Exception\EmptyException;
use Ixocreate\Collection\Exception\InvalidCollectionException;
use Ixocreate\Collection\Exception\InvalidTypeException;
use Ixocreate\Collection\Exception\KeysNotMatchException;
use Ixocreate\Contract\Collection\CollectionInterface;
use PHPUnit\Framework\TestCase;

class AbstractCollectionTest extends TestCase
{
    protected $data = [
        [
            'id' => 1,
            'name' => 'Eddard Stark',
            'age' => 34,
        ],
        [
            'id' => 2,
            'name' => 'Catelyn Stark',
            'age' => 33,
        ],
        [
            'id' => 3,
            'name' => 'Daenerys Targaryen',
            'age' => 13,
        ],
        [
            'id' => 4,
            'name' => 'Tyrion Lannister',
            'age' => 24,
        ],
        [
            'id' => 5,
            'name' => 'Jon Snow',
            'age' => 14,
        ],
        [
            'id' => 6,
            'name' => 'Brandon Stark',
            'age' => 7,
        ],
        [
            'id' => 7,
            'name' => 'Sansa Stark',
            'age' => 11,
        ],
        [
            'id' => 8,
            'name' => 'Arya Stark',
            'age' => 9,
        ],
        [
            'id' => 9,
            'name' => 'Theon Greyjoy',
            'age' => 18,
        ],
        [
            'id' => 10,
            'name' => 'Davos Seaworth',
            'age' => 37,
        ],
        [
            'id' => 11,
            'name' => 'Jaime Lannister',
            'age' => 31,
        ],
        [
            'id' => 12,
            'name' => 'Samwell Tarly',
            'age' => 14,
        ],
        [
            'id' => 13,
            'name' => 'Cersei Lannister',
            'age' => 31,
        ],
        [
            'id' => 14,
            'name' => 'Brienne of Tarth',
            'age' => 17,
        ],
        [
            'id' => 15,
            'name' => 'Brandon Stark Twin',
            'age' => 7,
        ],
        [
            'id' => 16,
            'name' => 'Davos Seaworth Twin',
            'age' => 37,
        ],
    ];

    public function testDataIntegrity()
    {
        $collection = new Collection($this->data);
        $this->assertSame($this->data, $collection->all());

        $collection = new Collection($this->data, "id");
        $data = [];
        foreach ($this->data as $array) {
            $data[$array['id']] = $array;
        }
        $this->assertSame($data, $collection->all());

        $collection = new Collection($this->data, function ($item) {
            return $item['name'];
        });
        $data = [];
        foreach ($this->data as $array) {
            $data[$array['name']] = $array;
        }
        $this->assertSame($data, $collection->all());
    }

    public function testDataIntegrityMatchKeyException()
    {
        $this->expectException(KeysNotMatchException::class);
        new Collection([['id' => 1], ['id' => 1]], 'id');
    }

    public function testAvg()
    {
        $collection = new Collection($this->data, 'id');

        $avg = 0;
        foreach ($this->data as $array) {
            $avg += $array['age'];
        }
        $avg = (float) ($avg / \count($this->data));
        $this->assertSame($avg, $collection->avg('age'));

        $this->assertSame($avg, $collection->avg(function ($item) {
            return $item['age'];
        }));

        $this->expectException(EmptyException::class);
        $collection = new Collection([]);
        $collection->avg('id');
    }

    public function testSum()
    {
        $collection = new Collection($this->data, 'id');

        $sum = 0;
        foreach ($this->data as $array) {
            $sum += $array['age'];
        }
        $this->assertSame((float) $sum, $collection->sum('age'));

        $this->expectException(EmptyException::class);
        $collection = new Collection([]);
        $collection->avg('id');
    }

    public function testMin()
    {
        $collection = new Collection($this->data, 'id');

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
        $this->assertSame(
            (new Collection($youngster, 'id'))->all(),
            $collection->min('age')->all()
        );

        $this->assertInstanceOf(Collection::class, $collection->min('age'));

        $collection = new Collection($this->data);

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
        $this->assertSame(
            (new Collection($youngster))->all(),
            $collection->min('age')->all()
        );

        $this->expectException(EmptyException::class);
        $collection = new Collection([]);
        $collection->min('id');
    }

    public function testMax()
    {
        $collection = new Collection($this->data, 'id');
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
        $this->assertSame(
            (new Collection($oldGuys, 'id'))->all(),
            $collection->max('age')->all()
        );

        $this->assertInstanceOf(Collection::class, $collection->max('age'));


        $collection = new Collection($this->data);
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
        $this->assertSame(
            (new Collection($oldGuys))->all(),
            $collection->max('age')->all()
        );

        $this->expectException(EmptyException::class);
        $collection = new Collection([]);
        $collection->max('id');
    }

    public function testKeys()
    {
        $collection = new Collection($this->data, 'id');
        $data = [];
        foreach ($this->data as $array) {
            $data[] = $array['id'];
        }

        $this->assertSame($data, $collection->keys());
    }

    public function testParts()
    {
        $collection = new Collection($this->data, 'id');
        $data = [];
        foreach ($this->data as $array) {
            $data[] = $array['name'];
        }

        $this->assertSame($data, $collection->parts("name"));

        $this->assertSame($data, $collection->parts(function ($item) {
            return $item['name'];
        }));
    }

    public function testGet()
    {
        $collection = new Collection($this->data, 'id');

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

    public function testHas()
    {
        $collection = new Collection($this->data, 'id');

        $this->assertTrue($collection->has(12));
        $this->assertFalse($collection->has("doesntExists"));
    }

    public function testRandom()
    {
        $collection = new Collection($this->data, 'id');
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

    public function testEach()
    {
        $collection = new Collection($this->data);

        $i = 0;
        $result = [];
        $collection->each(function ($item) use (&$result, &$i) {
            if ($i > 0) {
                return false;
            }
            $result[] = $item;
            $i++;
        });

        $this->assertSame([$this->data[0]], $result);
    }

    public function testFilter()
    {
        $collection = new Collection($this->data);
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
            $collection->all()
        );

        $collection = new Collection($this->data, 'id');
        $collection = $collection->filter(function ($item) {
            return $item['age'] < 8;
        });

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
            $collection->all()
        );
    }

    public function testSort()
    {
        $collection = new Collection($this->data);
        $collection = $collection->sort(function ($item1, $item2) {
            return $item1['age'] - $item2['age'];
        });

        $this->assertInstanceOf(Collection::class, $collection);

        $data = $this->data;
        \usort($data, function ($item1, $item2) {
            return $item1['age'] - $item2['age'];
        });

        $this->assertSame($data, $collection->all());


        $collection = new Collection($this->data, 'id');
        $collection = $collection->sort(function ($item1, $item2) {
            return $item1['age'] - $item2['age'];
        });

        $data = $this->data;
        \usort($data, function ($item1, $item2) {
            return $item1['age'] - $item2['age'];
        });
        $newData = [];
        foreach ($data as $item) {
            $newData[$item['id']] = $item;
        }

        $this->assertSame($newData, $collection->all());
    }

    public function testMerge()
    {
        $newData = [
            [
                'id' => 256,
                'name' => 'Someone else',
                'age' => 33,
            ],
        ];
        $collection1 = new Collection($this->data);
        $collection2 = new Collection($newData);

        $collection = $collection1->merge($collection2);
        $this->assertInstanceOf(Collection::class, $collection);

        $this->assertSame(
            \array_merge($this->data, $newData),
            $collection->all()
        );

        $newData = [
            [
                'id' => 256,
                'name' => 'Someone else',
                'age' => 33,
            ],
        ];
        $collection1 = new Collection($this->data, 'id');
        $collection2 = new Collection($newData);

        $collection = $collection1->merge($collection2);
        $tmp = \array_merge($this->data, $newData);
        $newData = [];
        foreach ($tmp as $item) {
            $newData[$item['id']] = $item;
        }

        $this->assertSame(
            $newData,
            $collection->all()
        );

        $this->expectException(InvalidCollectionException::class);
        $collection1->merge(new CollectionCollection([]));
    }

    public function testChunk()
    {
        $collection = new Collection($this->data);
        $collectionCollection = $collection->chunk(4);

        $this->assertSame(4, $collectionCollection->getCollectionCount());
        $this->assertSame(\count($this->data), $collectionCollection->count());
        $this->assertInstanceOf(CollectionCollection::class, $collectionCollection);

        $chunks = [];
        foreach (\array_chunk($this->data, 4) as $chunk) {
            $chunks[] = $chunk;
        }
        $i = 0;
        /** @var CollectionInterface $collection */
        foreach ($collectionCollection->getCollections() as $collection) {
            $this->assertSame($chunks[$i], $collection->all());
            $i++;
        }

        $collection = new Collection($this->data, 'id');
        $collectionCollection = $collection->chunk(4);

        $this->assertSame(4, $collectionCollection->getCollectionCount());
        $this->assertSame(\count($this->data), $collectionCollection->count());

        $data = [];
        foreach ($this->data as $key => $array) {
            $data[$array['id']] = $array;
        }

        $chunks = [];
        foreach (\array_chunk($data, 4, true) as $chunk) {
            $chunks[] = $chunk;
        }
        $i = 0;
        /** @var CollectionInterface $collection */
        foreach ($collectionCollection->getCollections() as $collection) {
            $this->assertSame($chunks[$i], $collection->all());
            $i++;
        }

        $collection = new Collection([], 'id');
        $collectionCollection = $collection->chunk(4);
        $this->assertSame(0, $collectionCollection->getCollectionCount());
    }

    public function testSplit()
    {
        $collection = new Collection($this->data);
        $collectionCollection = $collection->split(4);

        $this->assertInstanceOf(CollectionCollection::class, $collectionCollection);
        $this->assertSame(4, $collectionCollection->getCollectionCount());
        $this->assertSame(\count($this->data), $collectionCollection->count());

        $chunks = [];
        foreach (\array_chunk($this->data, 4) as $chunk) {
            $chunks[] = $chunk;
        }
        $i = 0;
        /** @var CollectionInterface $collection */
        foreach ($collectionCollection->getCollections() as $collection) {
            $this->assertSame($chunks[$i], $collection->all());
            $i++;
        }

        $collection = new Collection($this->data, 'id');
        $collectionCollection = $collection->split(4);

        $this->assertSame(4, $collectionCollection->getCollectionCount());
        $this->assertSame(\count($this->data), $collectionCollection->count());

        $data = [];
        foreach ($this->data as $key => $array) {
            $data[$array['id']] = $array;
        }

        $chunks = [];
        foreach (\array_chunk($data, 4, true) as $chunk) {
            $chunks[] = $chunk;
        }
        $i = 0;
        /** @var CollectionInterface $collection */
        foreach ($collectionCollection->getCollections() as $collection) {
            $this->assertSame($chunks[$i], $collection->all());
            $i++;
        }

        $collection = new Collection([], 'id');
        $collectionCollection = $collection->split(4);
        $this->assertSame(0, $collectionCollection->getCollectionCount());
    }

    public function testNth()
    {
        $collection = new Collection($this->data);
        $collection = $collection->nth(2);

        $this->assertInstanceOf(Collection::class, $collection);

        $items = [];
        for ($i = 0; $i < \count($this->data); $i++) {
            if ($i % 2 !== 0) {
                continue;
            }
            $items[] = $this->data[$i];
        }

        $this->assertSame($items, $collection->all());

        $collection = new Collection($this->data);
        $collection = $collection->nth(3, 1);

        $items = [];
        for ($i = 0; $i < \count($this->data); $i++) {
            if ($i % 3 !== 1) {
                continue;
            }
            $items[] = $this->data[$i];
        }

        $this->assertSame($items, $collection->all());

        $collection = new Collection($this->data, 'id');
        $collection = $collection->nth(4, 1);

        $items = [];
        for ($i = 0; $i < \count($this->data); $i++) {
            if ($i % 4 !== 1) {
                continue;
            }
            $items[$this->data[$i]['id']] = $this->data[$i];
        }

        $this->assertSame($items, $collection->all());
    }

    public function testDiff()
    {
        $collection1 = new Collection($this->data);
        $data = $this->data;
        $last = \array_pop($data);
        $collection2 = new Collection($data);
        $collection = $collection1->diff($collection2);

        $this->assertInstanceOf(Collection::class, $collection);
        $this->assertSame([$last], $collection->all());

        $collection1 = new Collection($this->data, 'id');
        $data = $this->data;
        $last = \array_pop($data);
        $collection2 = new Collection($data);
        $collection = $collection1->diff($collection2);
        $this->assertSame([$last['id'] => $last], $collection->all());

        $this->expectException(InvalidCollectionException::class);
        $collection1->diff(new CollectionCollection([]));
    }

    public function testIntersect()
    {
        $collection1 = new Collection($this->data);
        $data = $this->data;
        $last = \array_pop($data);
        $collection2 = new Collection([$last]);
        $collection = $collection1->intersect($collection2);

        $this->assertInstanceOf(Collection::class, $collection);
        $this->assertSame([$last], $collection->all());

        $collection1 = new Collection($this->data, 'id');
        $data = $this->data;
        $last = \array_pop($data);
        $collection2 = new Collection([$last]);
        $collection = $collection1->intersect($collection2);
        $this->assertSame([$last['id'] => $last], $collection->all());

        $this->expectException(InvalidCollectionException::class);
        $collection1->intersect(new CollectionCollection([]));
    }

    public function testPop()
    {
        $collection = new Collection($this->data);

        $data = $this->data;
        $last = \array_pop($data);

        $this->assertSame($last, $collection->pop());
        $this->assertSame($data, $collection->all());

        $collection = new Collection($this->data, 'id');

        $data = $this->data;
        $last = \array_pop($data);
        $newData = [];
        foreach ($data as $item) {
            $newData[$item['id']] = $item;
        }

        $this->assertSame($last, $collection->pop());
        $this->assertSame($newData, $collection->all());
    }

    public function testShift()
    {
        $collection = new Collection($this->data);

        $data = $this->data;
        $first = \array_shift($data);

        $this->assertSame($first, $collection->shift());
        $this->assertSame($data, $collection->all());

        $collection = new Collection($this->data, 'id');

        $data = $this->data;
        $first = \array_shift($data);
        $newData = [];
        foreach ($data as $item) {
            $newData[$item['id']] = $item;
        }

        $this->assertSame($first, $collection->shift());
        $this->assertSame($newData, $collection->all());
    }

    public function testPull()
    {
        $collection = new Collection($this->data);
        $pulledCollection = $collection->pull(function ($item) {
            return $item['id'] == 1;
        });

        $this->assertInstanceOf(Collection::class, $pulledCollection);

        $data = $this->data;
        $first = \array_shift($data);

        $this->assertSame($data, $collection->all());
        $this->assertSame([$first], $pulledCollection->all());


        $collection = new Collection($this->data, 'id');
        $pulledCollection = $collection->pull(function ($item) {
            return $item['id'] == 1;
        });

        $data = $this->data;
        $first = \array_shift($data);
        $newData = [];
        foreach ($data as $item) {
            $newData[$item['id']] = $item;
        }

        $this->assertSame($newData, $collection->all());
        $this->assertSame([1 => $first], $pulledCollection->all());
    }

    public function testReduce()
    {
        $collection = new Collection($this->data);

        $sumAge = $collection->reduce(function ($carry, $item) {
            return $carry + $item['age'];
        }, 0);

        $this->assertSame((float) $sumAge, $collection->sum('age'));
    }

    public function testPrepend()
    {
        $add = [
            'id' => 256,
            'name' => 'Someone else',
            'age' => 33,
        ];
        $collection = new Collection($this->data);
        $collection->prepend($add);
        $data = $this->data;
        \array_unshift($data, $add);
        $this->assertSame($data, $collection->all());

        $add = [
            'id' => 256,
            'name' => 'Someone else',
            'age' => 33,
        ];
        $collection = new Collection($this->data, "id");
        $collection->prepend($add);
        $data = $this->data;
        \array_unshift($data, $add);
        $newData = [];
        foreach ($data as $item) {
            $newData[$item['id']] = $item;
        }
        $this->assertSame($newData, $collection->all());

        $this->expectException(InvalidTypeException::class);
        $collection = new Collection($this->data, "id");
        $collection->prepend(false);
    }

    public function testPush()
    {
        $add = [
            'id' => 256,
            'name' => 'Someone else',
            'age' => 33,
        ];
        $collection = new Collection($this->data);
        $collection->push($add);
        $data = $this->data;
        \array_push($data, $add);
        $this->assertSame($data, $collection->all());

        $add = [
            'id' => 256,
            'name' => 'Someone else',
            'age' => 33,
        ];
        $collection = new Collection($this->data, "id");
        $collection->push($add);
        $data = $this->data;
        \array_push($data, $add);
        $newData = [];
        foreach ($data as $item) {
            $newData[$item['id']] = $item;
        }
        $this->assertSame($newData, $collection->all());

        $this->expectException(InvalidTypeException::class);
        $collection = new Collection($this->data, "id");
        $collection->push(false);
    }

    public function testFirst()
    {
        $data = $this->data;
        $first = \array_shift($data);
        $collection = new Collection($this->data);
        $this->assertSame($first, $collection->first());

        $this->assertSame(
            [
                'id' => 5,
                'name' => 'Jon Snow',
                'age' => 14,
            ],
            $collection->first(function ($item) {
                if ($item['id'] === 5) {
                    return $item;
                }
            })
        );

        $this->assertNull($collection->first(function ($item) {
            if ($item['id'] === 21380) {
                return $item;
            }
        }));
    }

    public function testLast()
    {
        $data = $this->data;
        $last = \array_pop($data);
        $collection = new Collection($this->data);
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
            })
        );

        $this->assertNull($collection->last(function ($item) {
            if ($item['id'] === 21380) {
                return $item;
            }
        }));
    }

    public function testShuffle()
    {
        $collection = new Collection($this->data);
        $collectionShuffle = $collection->shuffle();
        $this->assertSame($collection->count(), $collectionShuffle->count());

        $this->assertInstanceOf(Collection::class, $collectionShuffle);

        $collection1 = $collection->sort(function ($item1, $item2) {
            return $item1['id'] - $item2['id'];
        });

        $collection2 = $collectionShuffle->sort(function ($item1, $item2) {
            return $item1['id'] - $item2['id'];
        });
        $this->assertSame($collection1->all(), $collection2->all());


        $collection = new Collection($this->data, 'id');
        $collectionShuffle = $collection->shuffle();
        $this->assertSame($collection->count(), $collectionShuffle->count());

        $collection1 = $collection->sort(function ($item1, $item2) {
            return $item1['id'] - $item2['id'];
        });

        $collection2 = $collectionShuffle->sort(function ($item1, $item2) {
            return $item1['id'] - $item2['id'];
        });
        $this->assertSame($collection1->all(), $collection2->all());
    }

    public function testSlice()
    {
        $collection = new Collection($this->data);
        $this->assertSame(
            [
                [
                    'id' => 16,
                    'name' => 'Davos Seaworth Twin',
                    'age' => 37,
                ],
            ],
            $collection->slice(15)->all()
        );

        $this->assertInstanceOf(Collection::class, $collection->slice(15));

        $collection = new Collection($this->data, 'id');
        $this->assertSame(
            [
                16 => [
                    'id' => 16,
                    'name' => 'Davos Seaworth Twin',
                    'age' => 37,
                ],
            ],
            $collection->slice(15)->all()
        );


        $collection = new Collection($this->data);
        $this->assertSame(
            [
                [
                    'id' => 16,
                    'name' => 'Davos Seaworth Twin',
                    'age' => 37,
                ],
            ],
            $collection->slice(-1)->all()
        );

        $collection = new Collection($this->data, 'id');
        $this->assertSame(
            [
                16 => [
                    'id' => 16,
                    'name' => 'Davos Seaworth Twin',
                    'age' => 37,
                ],
            ],
            $collection->slice(-1)->all()
        );

        $collection = new Collection($this->data);
        $this->assertSame(
            [
                [
                    'id' => 2,
                    'name' => 'Catelyn Stark',
                    'age' => 33,
                ],
            ],
            $collection->slice(1, 1)->all()
        );

        $collection = new Collection($this->data, 'id');
        $this->assertSame(
            [
                2 => [
                    'id' => 2,
                    'name' => 'Catelyn Stark',
                    'age' => 33,
                ],
            ],
            $collection->slice(1, 1)->all()
        );

        $collection = new Collection($this->data);
        $this->assertSame(
            [
                [
                    'id' => 15,
                    'name' => 'Brandon Stark Twin',
                    'age' => 7,
                ],
            ],
            $collection->slice(14, -1)->all()
        );

        $collection = new Collection($this->data, 'id');
        $this->assertSame(
            [
                15 => [
                    'id' => 15,
                    'name' => 'Brandon Stark Twin',
                    'age' => 7,
                ],
            ],
            $collection->slice(14, -1)->all()
        );

        $collection = new Collection($this->data);
        $this->assertSame(
            [
                [
                    'id' => 15,
                    'name' => 'Brandon Stark Twin',
                    'age' => 7,
                ],
            ],
            $collection->slice(-2, -1)->all()
        );

        $collection = new Collection($this->data, 'id');
        $this->assertSame(
            [
                15 => [
                    'id' => 15,
                    'name' => 'Brandon Stark Twin',
                    'age' => 7,
                ],
            ],
            $collection->slice(-2, -1)->all()
        );
    }

    public function testIsEmpty()
    {
        $collection = new Collection($this->data);
        $this->assertFalse($collection->isEmpty());

        $collection = new Collection([]);
        $this->assertTrue($collection->isEmpty());
    }

    public function testCount()
    {
        $collection = new Collection($this->data);
        $this->assertSame(\count($this->data), $collection->count());

        $collection = new Collection([]);
        $this->assertSame(0, $collection->count());
    }

    public function testReverse()
    {
        $collection = new Collection($this->data);
        $this->assertSame(\array_reverse($this->data), $collection->reverse()->all());

        $this->assertInstanceOf(Collection::class, $collection->reverse());

        $data = [];
        foreach ($this->data as $array) {
            $data[$array['id']] = $array;
        }
        $collection = new Collection($this->data, 'id');
        $this->assertSame(\array_reverse($data, true), $collection->reverse()->all());
    }

    public function testGetIterator()
    {
        $collection = new Collection($this->data);
        $this->assertInstanceOf(\ArrayIterator::class, $collection->getIterator());

        $this->assertSame(\count($this->data), $collection->getIterator()->count());
    }
}

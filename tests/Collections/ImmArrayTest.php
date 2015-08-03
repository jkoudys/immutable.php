<?php
/**
 * Test cases for verifying each ImmArray method
 */

use Qaribou\Collection\ImmArray;
use Qaribou\Collection\CallbackHeap;

class ImmArrayTest extends PHPUnit_Framework_TestCase
{
    public function testMap()
    {
        $base = [1, 2, 3, 4];
        $doubled = [2, 4, 6, 8];

        $numberSet = ImmArray::fromArray($base);
        $mapped = $numberSet->map(function($num) { return $num * 2; });

        foreach ($mapped as $i => $v) {
            $this->assertEquals($v, $doubled[$i]);
        }
    }

    public function testJoin()
    {
        $imarr = ImmArray::fromArray(['foo', 'bar', 'baz']);

        $this->assertEquals('foo,bar,baz', $imarr->join(), 'Default join failed.');
        $this->assertEquals('fooXXXbarXXXbaz', $imarr->join('XXX'), 'Token join failed.');
        $this->assertEquals('<li>foo</li><li>bar</li><li>baz</li>', $imarr->join('<li>', '</li>'), 'Two token join failed.');
    }

    public function testFilter()
    {
        $oddArr = [1, 3, 5, 7, 9];
        $immArr = ImmArray::fromArray([1, 2, 3, 4, 5, 6, 7, 8, 9]);

        $odds = $immArr->filter(function($num) { return $num % 2; });

        foreach ($odds as $i => $v) {
            $this->assertEquals($v, $oddArr[$i]);
        }
    }

    public function testSort()
    {
        // Sort
        $unsorted = ImmArray::fromArray(['f', 'c', 'a', 'b', 'e', 'd']);
        $sorted = $unsorted->sort(function($a, $b) { return strcmp($a, $b); });

        $this->assertSame($sorted->toArray(), ['a', 'b', 'c', 'd', 'e', 'f'], 'Callback sort failed.');

        // Heap sort
        $heapSorted = $unsorted->sortHeap(new BasicHeap());
        $this->assertSame($sorted->toArray(), ['a', 'b', 'c', 'd', 'e', 'f'], 'Heap sort failed.');
    }

    public function testSlice()
    {
        $immArr = ImmArray::fromArray([1, 2, 3, 4, 5, 6, 7, 8, 9]);

        $firstThree = $immArr->slice(0, 3);

        $this->assertCount(3, $firstThree);
        $this->assertSame([1, 2, 3], $firstThree->toArray());
    }

    public function testReduce()
    {
        $arIt = new ArrayIterator([1, 2, 3, 4, 5]);
        $numberSet = ImmArray::fromItems($arIt);

        // Reduce with sum
        $sum = $numberSet->reduce(function($last, $cur) { return $last + $cur; }, 0);
        $this->assertEquals(15, $sum);

        // Reduce with string concat
        $concatted = $numberSet->reduce(function($last, $cur, $i) {
            return $last . '{"'. $i . '":"' . $cur . '"},';
        }, '');
        $this->assertEquals('{"0":"1"},{"1":"2"},{"2":"3"},{"3":"4"},{"4":"5"},', $concatted);
    }

    public function testConcat()
    {
        $setA = ImmArray::fromArray([1, 2, 3]);

        $setB = ImmArray::fromItems(new ArrayIterator([4, 5, 6]));

        $concatted = $setA->concat($setB);

        $this->assertSame([1, 2, 3, 4, 5, 6], $concatted->toArray());
    }

    public function testLoadBigSet()
    {
        $startMem = ini_get('memory_limit');
        ini_set('memory_limit', '50M');
        // Big
        $bigSet = ImmArray::fromArray(array_map(function($el) { return md5($el); }, range(0, 200000)));

        $this->assertCount(200001, $bigSet);
        ini_set('memory_limit', $startMem);
    }
}

// A heap for testing sorting
class BasicHeap extends \SplHeap
{
    public function compare($a, $b)
    {
        return strcmp($a, $b);
    }
}

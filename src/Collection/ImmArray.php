<?php
/**
 * The Immutable Array
 *
 * This provides special methods for quickly creating an immutable array,
 * either from any Traversable, or using a C-optimized fromArray() to directly
 * instantiate from. Also includes methods fundamental to functional
 * programming, e.g. map, filter, join, and sort.
 *
 * @package immutable.php
 * @author Joshua Koudys <josh@qaribou.com>
 */

namespace Qaribou\Collection;

use Qaribou\Collection\CallbackHeap;
use SplFixedArray;
use SplHeap;
use Iterator;
use ArrayAccess;
use Countable;
use CallbackFilterIterator;
use JsonSerializable;
use RuntimeException;
use Traversable;

class ImmArray implements Iterator, ArrayAccess, Countable, JsonSerializable
{
    // The fixed array
    private $sfa = null;

    /**
     * Create an immutable array
     */
    public function __construct()
    {
    }

    /**
     * Map elements to a new ImmArray via a callback
     *
     * @param Callable $cb Function to map new data
     * @return ImmArray
     */
    public function map(Callable $cb)
    {
        $ret = new static();
        $count = count($this);
        $sfa = new SplFixedArray($count);
        for ($i = 0; $i < $count; $i++) {
            $sfa[$i] = $cb($this->sfa[$i]);
        }
        $ret->setSplFixedArray($sfa);
        return $ret;
    }

    /**
     * Filter out elements
     *
     * @param Callable $cb Function to filter out on false
     * @return ImmArray
     */
    public function filter(Callable $cb)
    {
        $ret = new static();
        $count = count($this->sfa);
        $sfa = new SplFixedArray($count);
        $newCount = 0;

        foreach ($this->sfa as $el) {
            if ($cb($el)) {
                $sfa[$newCount++] = $el;
            }
        }

        $sfa->setSize($newCount);
        $ret->setSplFixedArray($sfa);
        return $ret;
    }

    /**
     * Join a set of strings together.
     *
     * @param string $token Main token to put between elements
     * @param string $secondToken If set, $token on left $secondToken on right
     * @return string
     */
    public function join($token = ',', $secondToken = null)
    {
        $ret = '';
        if ($secondToken) {
            foreach ($this as $el) {
                $ret .= $token . $el . $secondToken;
            }
        } else {
            $this->rewind();
            while ($this->valid()) {
                $ret .= (string) $this->current();
                $this->next();
                if ($this->valid()) {
                    $ret .= $token;
                }
            }
        }
        return $ret;
    }

    /**
     * Take a slice of the array
     *
     * @param int $begin Start index of slice
     * @param int $end End index of slice
     * @return ImmArray
     */
    public function slice($begin = 0, $end = null)
    {
        $count = count($this);

        // If no end set, assume whole array
        if ($end === null) {
            $end = $count;
        } else if ($end < 0) {
            // Ends counting back from start
            $end = $count + $end;
        }

        // Negative begin means start from the end
        if ($begin < 0) {
            $begin = $count + $begin;
        }

        if ($begin === 0) {
            // If 0-indexed, we can do a quick clone + resize to slice
            $sfa = clone $this->sfa;
            if ($end) {
                // Don't allow slices beyond the end
                $sfa->setSize(min($end, $count));
            }
        } else {
            // We're taking a slice starting inside the array
            $sfa = new SplFixedArray($end - $begin + 1);

            for ($i = $begin; $i < $end; $i++) {
                $sfa[$i - $begin] = $this->sfa[$i];
            }
        }
        $ret = new static();
        $ret->setSplFixedArray($sfa);
        return $ret;
    }

    /**
     * Return a new sorted ImmArray
     *
     * @param Callable $cb The sort callback
     * @return ImmArray
     */
    public function sort(Callable $cb = null)
    {
        if ($cb) {
            return $this->heapSort($cb);
        } else {
            return $this->arraySort();
        }
    }

    /**
     * Sort a new ImmArray by filtering through a heap.
     * Tends to run much faster than array or merge sorts, since you're only
     * sorting the pointers, and the sort function is running in a highly
     * optimized space.
     *
     * @param SplHeap $heap The heap to run for sorting
     * @return ImmArray
     */
    public function sortHeap(SplHeap $heap)
    {
        foreach ($this as $item) {
            $heap->insert($item);
        }
        return static::fromItems($heap);
    }

    /**
     * Typically internal method to directly set the SplFixedArray
     *
     * @param SplFixedArray $sfa The dataset to set
     * @return null
     */
    public function setSplFixedArray(SplFixedArray $sfa)
    {
        $this->sfa = $sfa;
    }

    /**
     * Factory for building ImmArrays from any traversable
     *
     * @return ImmArray
     */
    public static function fromItems(Traversable $arr)
    {
        $ret = new static();
        $sfa = new SplFixedArray(count($arr));
        foreach ($arr as $i => $el) {
            $sfa[$i] = $el;
        }
        $ret->setSplFixedArray($sfa);

        return $ret;
    }

    /**
     * Build from an array
     *
     * @return ImmArray
     */
    public static function fromArray(array $arr)
    {
        $ret = new static();
        $ret->setSplFixedArray(SplFixedArray::fromArray($arr));

        return $ret;
    }

    public function toArray()
    {
        return $this->sfa->toArray();
    }

    /**
     * Countable
     */
    public function count()
    {
        return count($this->sfa);
    }

    /**
     * Iterator
     */
    public function current()
    {
        return $this->sfa->current();
    }

    public function key()
    {
        return $this->sfa->key();
    }

    public function next()
    {
        return $this->sfa->next();
    }

    public function rewind()
    {
        return $this->sfa->rewind();
    }

    public function valid()
    {
        return $this->sfa->valid();
    }

    /**
     * ArrayAccess
     */
    public function offsetExists($offset)
    {
        return $this->sfa->offsetExists($offset);
    }

    public function offsetGet($offset)
    {
        return $this->sfa->offsetGet($offset);
    }

    public function offsetSet($offset, $value)
    {
        throw new RuntimeException('Attempt to mutate immutable ' . __CLASS__ . ' object.');
    }

    public function offsetUnset($offset)
    {
        throw new RuntimeException('Attempt to mutate immutable ' . __CLASS__ . ' object.');
    }

    /**
     * JsonSerializable
     */
    public function jsonSerialize()
    {
        return $this->sfa->toArray();
    }

    /**
     * Perform a bottom-up, non-recursive, in-place mergesort.
     * Efficient for very-large objects, and written without recursion
     * since PHP isn't well optimized for large recursion stacks.
     *
     * @param Callable $cb The callback for comparison
     * @return ImmArray
     */
    protected function mergeSort(Callable $cb)
    {
        $count = count($this);
        $sfa = $this->sfa;
        $result = new SplFixedArray($count);
        for ($k = 1; $k < $count; $k = $k << 1) {
            for ($left = 0; ($left + $k) < $count; $left += $k << 1) {
                $right = $left + $k;
                $rend = min($right + $k, $count);
                $m = $left;
                $i = $left;
                $j = $right;
                while ($i < $right && $j < $rend) {
                    if ($cb($sfa[$i], $sfa[$j]) <= 0) {
                        $result[$m] = $sfa[$i];
                        $i++;
                    } else {
                        $result[$m] = $sfa[$j];
                        $j++;
                    }
                    $m++;
                }
                while ($i < $right) {
                    $result[$m] = $sfa[$i];
                    $i++;
                    $m++;
                }
                while ($j < $rend) {
                    $result[$m] = $sfa[$j];
                    $j++;
                    $m++;
                }
                for ($m = $left; $m < $rend; $m++) {
                    $sfa[$m] = $result[$m];
                }
            }
        }

        $imm = new static();
        $imm->setSplFixedArray($result);
        return $imm;
    }

    /**
     * Sort by applying a CallbackHeap and building a new heap
     * Can be efficient for sorting large stored objects.
     *
     * @param Callable $cb The comparison callback
     * @return ImmArray
     */
    protected function heapSort(Callable $cb)
    {
        $h = new CallbackHeap($cb);
        foreach ($this as $el) {
            $h->insert($el);
        }

        return static::fromItems($h);
    }

    /**
     * Fallback behaviour to use the builtin array sort functions
     *
     * @param Callable $cb The callback for comparison
     * @return ImmArray
     */
    protected function arraySort(Callable $cb = null)
    {
        $ar = $this->toArray();
        if ($cb) {
            usort($ar, $cb);
        } else {
            sort($ar);
        }
        return static::fromArray($ar);
    }
}

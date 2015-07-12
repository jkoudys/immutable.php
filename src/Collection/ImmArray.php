<?php
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
use Closure;
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

    public function map(Callable $cb)
    {
        $ret = new static();
        $sfa = clone $this->sfa;
        $sfa->rewind();
        iterator_apply($sfa, function($iterator) use ($cb) {
            $iterator->offsetSet($iterator->key(), $cb($iterator->current()));
            return true;
        }, [$sfa]);

        $ret->setSplFixedArray($sfa);
        return $ret;
    }

    public function filter(Callable $cb)
    {
        $ret = new static();
        $sfa = clone $this->sfa;
        $count = 0;

        foreach ($sfa as $el) {
            if ($cb($el)) {
                $sfa[$count++] = $el;
            }
        }

        $sfa->setSize($count);
        $ret->setSplFixedArray($sfa);
        return $ret;
    }

    /**
     * Join a set of strings together.
     * 
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
     * Return a new sorted ImmArray
     *
     * @param Closure $cb The sort callback
     * @return ImmArray
     */
    public function sort(Closure $cb = null)
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
     * @param Closure $cb The callback for comparison
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
     * @param Closure $cb The comparison callback
     * @return ImmArray
     */
    protected function heapSort(Closure $cb)
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
     * @param Closure $cb The callback for comparison
     * @return ImmArray
     */
    protected function arraySort(Closure $cb = null)
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

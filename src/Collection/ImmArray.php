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
use Qaribou\Iterator\SliceIterator;
use Qaribou\Iterator\ConcatIterator;
use SplFixedArray;
use SplHeap;
use SplStack;
use LimitIterator;
use Iterator;
use ArrayAccess;
use Countable;
use CallbackFilterIterator;
use JsonSerializable;
use RuntimeException;
use Traversable;
use ReflectionClass;

class ImmArray implements Iterator, ArrayAccess, Countable, JsonSerializable
{
    use Sort {
        Sort::quickSort as sortAlgo;
    }

    // The fixed array
    private $sfa = null;

    /**
     * Create an immutable array
     *
     * @param Traversable $immute Data guaranteed to be immutable
     */
    private function __construct(Traversable $immute)
    {
        $this->sfa = $immute;
    }

    /**
     * Map elements to a new ImmArray via a callback
     *
     * @param callable $cb Function to map new data
     * @return ImmArray
     */
    public function map(callable $cb): self
    {
        $count = count($this);
        $sfa = new SplFixedArray($count);
        for ($i = 0; $i < $count; $i++) {
            $sfa[$i] = $cb($this->sfa[$i], $i, $this);
        }
        return new static($sfa);
    }

    /**
     * forEach, or "walk" the data
     * Exists primarily to provide a consistent interface, though it's seldom
     * any better than a simple php foreach. Mainly useful for chaining.
     * Named walk for historic reasons - forEach is reserved in PHP
     *
     * @param callable $cb Function to call on each element
     * @return ImmArray
     */
    public function walk(callable $cb): self
    {
        foreach ($this as $i => $el) {
            $cb($el, $i, $this);
        }
        return $this;
    }

    /**
     * Filter out elements
     *
     * @param callable $cb Function to filter out on false
     * @return ImmArray
     */
    public function filter(callable $cb): self
    {
        $count = count($this->sfa);
        $sfa = new SplFixedArray($count);
        $newCount = 0;

        foreach ($this->sfa as $el) {
            if ($cb($el)) {
                $sfa[$newCount++] = $el;
            }
        }

        $sfa->setSize($newCount);
        return new static($sfa);
    }

    /**
     * Reduce to a single value
     *
     * @param callable $cb Callback(
     *     mixed $previous, mixed $current[, mixed $index, mixed $immArray]
     * ):mixed Callback to run reducing function
     * @param mixed $accumulator Initial value for first argument
     */
    public function reduce(callable $cb, $accumulator = null)
    {
        foreach ($this->sfa as $i => $el) {
            $accumulator = $cb($accumulator, $el, $i, $this);
        }
        return $accumulator;
    }

    /**
     * Join a set of strings together.
     *
     * @param string $token Main token to put between elements
     * @param string $secondToken If set, $token on left $secondToken on right
     * @return string
     */
    public function join(string $token = ',', string $secondToken = null): string
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
    public function slice(int $begin = 0, int $end = null): self
    {
        $it = new SliceIterator($this->sfa, $begin, $end);
        return new static($it);
    }

    /**
     * Concat to the end of this array
     *
     * @param Traversable,...
     * @return ImmArray
     */
    public function concat(...$args): self
    {
        $concatIt = new ConcatIterator(...$args);
        return new static($concatIt);
    }

    /**
     * Find a single element
     *
     * @param callable $cb The test to run on each element
     * @return mixed The element we found
     */
    public function find(callable $cb)
    {
        foreach ($this->sfa as $i => $el) {
            if ($cb($el, $i, $this)) return $el;
        }
    }

    /**
     * Return a new sorted ImmArray
     *
     * @param callable $cb The sort callback
     * @return ImmArray
     */
    public function sort(callable $cb = null)
    {
        if ($cb) return $this->sortAlgo($cb);
        return $this->arraySort();
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
    public function sortHeap(SplHeap $heap): self
    {
        foreach ($this as $item) {
            $heap->insert($item);
        }
        return static::fromItems($heap);
    }

    /**
     * Factory for building ImmArrays from any traversable
     *
     * @return ImmArray
     */
    public static function fromItems(Traversable $arr): self
    {
        // We can only do it this way if we can count it
        if ($arr instanceof Countable) {
            $sfa = new SplFixedArray(count($arr));
            foreach ($arr as $i => $el) {
                $sfa[$i] = $el;
            }

            return new static($sfa);
        }

        // If we can't count it, it's simplest to iterate into an array first
        return static::fromArray(iterator_to_array($arr));
    }

    /**
     * Build from an array
     *
     * @return ImmArray
     */
    public static function fromArray(array $arr): self
    {
        return new static(SplFixedArray::fromArray($arr));
    }

    public function toArray(): array
    {
        return $this->sfa->toArray();
    }

    /**
     * Countable
     */
    public function count(): int
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

    public function key(): int
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
    public function offsetExists($offset): bool
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
    public function jsonSerialize(): array
    {
        return $this->sfa->toArray();
    }
}

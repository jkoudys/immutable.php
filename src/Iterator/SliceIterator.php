<?php
/**
 * Iterator to allow a slice to be used like an array
 */

namespace Qaribou\Iterator;

use ArrayAccess;
use LimitIterator;
use JsonSerializable;
use Countable;
use Iterator;
use InvalidArgumentException;
use RuntimeException;

class SliceIterator extends LimitIterator implements ArrayAccess, Countable, JsonSerializable
{
    protected $count = 0;
    protected $begin = 0;

    const INVALID_INDEX = 'Index invalid or out of range';

    /**
     * Build an iterator over a slice of an ArrayAccess object
     * Unlike a LimitIterator, the $end defines the last index, not the count
     *
     * @param Iterator $iterator An ArrayAccess iterator, e.g. SplFixedArray
     * @param int $begin The starting offset of the slice
     * @param int $end The last index of the slice
     */
    public function __construct(Iterator $iterator, $begin = 0, $end = null)
    {
        if ($iterator instanceof ArrayAccess && $iterator instanceof Countable) {
            $count = count($iterator);

            // Negative begin means start from the end
            if ($begin < 0) {
                $begin = max(0, $count + $begin);
            }

            // If no end set, assume whole array
            if ($end === null) {
                $end = $count;
            } else if ($end < 0) {
                // Ends counting back from start
                $end = max($begin, $count + $end);
            }

            // Set the size of iterable object, for quick-lookup
            $this->count = max(0, $end - $begin);

            // Need to store the starting offset to adjust by
            $this->begin = $begin;

            // Init as LimitIterator
            parent::__construct($iterator, $this->begin, $this->count);
        } else {
            throw new InvalidArgumentException('Iterator must be a Countable ArrayAccess');
        }
    }

    /**
     * Rewind, extended for clean results on empty sets
     */
    public function rewind()
    {
        // no need to rewind on empty sets
        if ($this->count > 0) {
            parent::rewind();
        }
    }

    /**
     * Countable
     */
    public function count()
    {
        return $this->count;
    }

    /**
     * ArrayAccess
     */
    public function offsetExists($offset)
    {
        return $offset >= 0 && $offset < $this->count;
    }

    public function offsetGet($offset)
    {
        if ($this->offsetExists($offset)) {
            return $this->getInnerIterator()->offsetGet($offset + $this->begin);
        } else {
            throw new RuntimeException(self::INVALID_INDEX);
        }
    }

    public function offsetSet($offset, $value)
    {
        return $this->getInnerIterator()->offsetSet($offset + $this->begin, $value);
    }

    public function offsetUnset($offset)
    {
        return $this->getInnerIterator()->offsetUnset($offset + $this->begin);
    }

    /**
     * JsonSerializable
     */
    public function jsonSerialize()
    {
        return $this->toArray();
    }

    public function toArray()
    {
        return iterator_to_array($this, false);
    }
}

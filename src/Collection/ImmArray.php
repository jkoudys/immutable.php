<?php
namespace Qaribou\Functional;
use Countable, Iterator, ArrayAccess, Exception, InvalidArgumentException;

class ImmArray implements Countable, Iterator, ArrayAccess
{
    private $refImmArray = null;
    private $delta = [];
    private $position = 0;

    public function __construct($vals, ImmArray $ref = null)
    {
        if (is_array($vals) || $vals instanceof ArrayAccess) {
            $this->delta = $vals;
            $this->refImmArray = $ref;
        } else {
            throw new InvalidArgumentException('Must be initialized with array or implementation of ArrayAccess');
        }
    }

    public function add($val)
    {
        return new ImmArray([$val], $this);
    }

    public function addAll($vals)
    {
        if (is_array($vals) || $vals instanceof ArrayAccess) {
            return new ImmArray($vals, $this);
        } else {
            throw new InvalidArgumentException('Requires array or implementation of ArrayAccess');
        }
    }

    /* Countable */
    public function count()
    {
        return count($this->refImmArray) + count($this->delta);
    }

    /* Iterator methods */
    public function rewind()
    {
        $this->position = 0;
    }

    public function current()
    {
        // See if we're checking in the ref
        if ($this->position < count($this->refImmArray)) {
            return $this->refImmArray[$this->position];
        } else {
            return $this->delta[$this->position - count($this->refImmArray)];
        }
    }

    function key()
    {
        return $this->position;
    }

    function next()
    {
        ++$this->position;
    }

    function valid()
    {
        return $this->offsetExists($this->position);
    }

    /* Array methods */
    public function offsetSet($offset, $value)
    {
        throw new Exception('Attempted to mutate immutable array');
    }

    public function offsetExists($offset)
    {
        // Need different behaviour for int indexes vs hashmap
        if (is_int($offset)) {
            $refCount = count($this->refImmArray);
            if ($offset < $refCount) {
                return $this->refImmArray->offsetExists($offset);
            } else {
                return isset($this->delta[$offset - $refCount]);
            }
        } else {
            // If it's a hash, lookup in this array and the chain of references
            return isset($this->delta[$offset]) || $this->refImmArray->offsetExists($offset);
        }
    }

    public function offsetUnset($offset)
    {
        throw new Exception('Attempted to mutate immutable array');
    }

    public function offsetGet($offset)
    {
        if (is_int($offset)) {
            $refCount = count($this->refImmArray);
            if ($offset < $refCount) {
                return $this->refImmArray->offsetGet($offset);
            } else {
                return isset($this->delta[$offset - $refCount]) ? $this->delta[$offset - $refCount] : null;
            }
        } else {
            // If it's a hash, lookup in this array and the chain of references
            return isset($this->delta[$offset]) || $this->refImmArray->offsetExists($offset);
        }
    }
}


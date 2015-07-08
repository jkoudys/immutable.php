<?php
namespace Qaribou\Collection;

use SplFixedArray;
use Iterator;
use ArrayAccess;
use Countable;
use JsonSerializable;
use RuntimeException;

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
        $ret = new self();
        $sfa = clone $this->sfa;

        foreach ($sfa as $i => $el) {
            $sfa[$i] = $cb($el, $i);
        }

        $ret->setSplFixedArray($sfa);
        return $ret;
    }

    public function filter(Callable $cb)
    {
        $ret = new self();
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
            foreach ($this->sfa as $el) {
                $ret .= $token . $el . $secondToken;
            }
        } else {
            while ($this->sfa->valid()) {
                $ret .= $el;
                $this->sfa->next();
                if ($this->sfa->valid()) {
                    $ret .= $token;
                }
            }
            $this->sfa->rewind();
        }
        return $ret;
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
}

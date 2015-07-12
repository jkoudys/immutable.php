<?php
/**
 * The CallbackHeap
 *
 * A simple class for defining a callback to use as the comparison function,
 * when building a heap. Note that this will always incur extra overheaad on
 * each comparison, so if you need to define a simple heap always running the
 * same comparison, it makes more sense to define it in its own class extending
 * SplHeap. This class is appropriate when you have a set of comparisons to
 * choose from.
 */

namespace Qaribou\Collection;

use SplHeap;
use Closure;

class CallbackHeap extends SplHeap
{
    public $cb;

    public function __construct(Closure $cb)
    {
        $this->cb = $cb;
    }

    public function compare($a, $b)
    {
        return call_user_func($this->cb, $a, $b);
    }
}



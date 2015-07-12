<?php
/**
 * Simple tests for now, to verify basic functionality.
 * This should soon be PHPUnit.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Qaribou\Collection\ImmArray;
use Qaribou\Collection\CallbackHeap;

$numberSet = ImmArray::fromArray([1, 2, 3, 4, 5]);

// Use a standard function as the callable
function double($num) { return $num * 2; }

echo '<h1>List of items</h1><ul>' . $numberSet->join('<li>', '</li>') . '</ul>', PHP_EOL;
echo 'Doubled: ' . $numberSet->map('double')->join(), PHP_EOL;
echo 'Odds: ' . $numberSet->filter(function($num) { return (bool) $num % 2; })->join(), PHP_EOL;

$unsorted = ImmArray::fromArray(['f', 'c', 'a', 'b', 'e', 'd']);
$sorted = $unsorted->sort(function($a, $b) { return strcmp($a, $b); });
echo 'Sorted: ' . $sorted->join(', '), PHP_EOL;

// Big
$bigSet = ImmArray::fromArray(array_map(function($el) { return md5($el); }, range(0, 100000)));

// Time the filter function
$t = microtime(true);
$filter = $bigSet->filter(function($el) { return strpos($el, 'a8') > -1; });
echo 'filter: ' . (microtime(true) - $t) . 's', PHP_EOL;

// Time the map function
$t = microtime(true);
$mapped = $bigSet->map(function($el) { return '{' . $el . '}'; });
echo 'map: ' . (microtime(true) - $t) . 's', PHP_EOL;

// Time the sort function
$t = microtime(true);
$bigSet->sort(function($a, $b) { return strcmp($a, $b); });
echo 'mergeSort: ' . (microtime(true) - $t) . 's', PHP_EOL;

// Time the sort function without a callback
$t = microtime(true);
$bigSet->sort();
echo 'arraySort: ' . (microtime(true) - $t) . 's', PHP_EOL;

// Build a heap and sort from that
class BasicHeap extends \SplHeap
{
    public function compare($a, $b)
    {
        return strcmp($a, $b);
    }
}
$t = microtime(true);
$sorted = $bigSet->sortHeap(new BasicHeap());
echo 'sortHeap: ' . (microtime(true) - $t) . 's', PHP_EOL;

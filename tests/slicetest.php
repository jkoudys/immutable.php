
<?php
/**
 * Simple tests for now, to verify basic functionality.
 * This should soon be PHPUnit.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Qaribou\Collection\ImmArray;
use Qaribou\Iterator\SliceIterator;

$ia = ImmArray::fromArray(['a', 'b', 'c', 'd', 'e']);
$slice = new SliceIterator($ia, 1, -1);

foreach ($slice as $i => $el) {
    echo $i . ': ' . $el, PHP_EOL;
}

echo 'Count: ' . count($slice), PHP_EOL;
echo '1st index: ' . $slice[2], PHP_EOL;

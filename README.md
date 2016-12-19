# immutable.php

Immutable collections, well-suited for functional programming and memory-intensive applications. Runs especially fast in PHP7.

## Basic Usage

###Quickly load from a simple array
```php
use Qaribou\Collection\ImmArray;
$polite = ImmArray::fromArray(['set', 'once', 'don\'t', 'mutate']);
echo $polite->join(' ');
// => "set once don't mutate"
```
###Map with a callback
```php
$yelling = $polite->map(function($word) { return strtoupper($word); });

echo <<<EOT
<article>
  <h3>A Wonderful List</h3>
  <ul>
    {$yelling->join('<li>', '</li>')}
  </ul>
</article>
EOT;

// => <article>
// =>   <h3>A Wonderful List</h3>
// =>   <ul>
// =>     <li>SET</li><li>ONCE</li><li>DON'T</li><li>MUTATE</li>
// =>   </ul>
// => </article>
```

###Sort with a callback
```php
echo 'Os in front: ' .
    $yelling
        ->sort(function($word) { return (strpos('O', $word) === false) ? 1 : -1; })
        ->join(' ');
// => "Os in front: ONCE DON'T MUTATE SET"
```

###Slice
```php
echo 'First 2 words only: ' . $polite->slice(0, 2)->join(' ');
// => "set once"
```

###Load big objects
```php
// Big memory footprint: $fruits is 30MB on PHP5.6
$fruits = array_merge(array_fill(0, 1000000, 'peach'), array_fill(0, 1000000, 'banana'));

// Small memory footprint: only 12MB
$fruitsImm = ImmArray::fromArray($fruits);

// Especially big savings for slices -- array_slice() gives a 31MB object
$range = range(0, 50000);
$sliceArray = array_slice($range, 0, 30000);

// But this is a 192 _byte_ iterator!
$immSlice = ImmArray::fromArray($range)->slice(0, 30000);
```

###Filter
```php
// Yes, we have no bananas
$noBananas = $fruitsImm->filter(function($fruit) { return $fruit !== 'banana'; });
```

###Concat (aka merge)
```php
$ia = ImmArray::fromArray([1,2,3,4]);
$ib = ImmArray::fromArray([5,6,7,8]);

// Like slice(), it's just a little iterator in-memory
$ic = $ia->concat($ib);
// => [1,2,3,4,5,6,7,8]
```

###Reduce
```php
$fruits = ImmArray::fromArray(['peach', 'plum', 'orange']);

$fruits->reduce(function($last, $cur, $i) {
  return $last . '{"' . $i . '":' . $cur . '"},';
}, '"My Fruits: ');

// => My Fruits: {"0":"peach"},{"1":"plum"},{"2":"orange"},
```

###Find
```php
$fruits = ImmArray::fromArray(['peach', 'plum', 'banana', 'orange']);

$fruitILike = $fruits->find(function ($fruit) {
  return $fruit === 'plum' || $fruit === 'orange';
});

// => 'plum'
```

###Array accessible
```php
echo $fruits[1];
// => "plum"
```

###Countable
```php
count($fruits);
// => 3
```

###Iterable
```php
foreach ($fruits as $fruit) {
    $fruitCart->sell($fruit);
}
```

###Load from any `Traversable` object
```php
$vegetables = ImmArray::fromItems($vegetableIterator);
```

###Even serialize back as json!
```php
echo json_encode(
    ['name' => 'The Peach Pit', 'type' => 'fruit stand', 'fruits' => $noBananas]
);
// => {"name": "The Peach Pit", "type": "fruit stand", "fruits": ["peach", "peach", .....
```

## Install
immutable.php is available on composer via packagist.
```sh
composer require qaribou/immutable.php
```

## Why
This project was born out of my love for 3 other projects: Hack (http://hacklang.org), immutable.js (https://facebook.github.io/immutable-js/), and the Standard PHP Library (SPL) datastructures (http://php.net/manual/en/spl.datastructures.php).

* Both Hack and immutable.js show that it's both possible, and practical to work with immutable data structures, even in a very loosely-typed language
* The Hack language introduced many collections of its own, along with special syntax, which are unavailable in PHP.
* SPL has some technically excellent, optimized datastructures, which are often impractical in real world applications.

## Why didn't I just use SplFixedArray directly?
The SplFixedArray is very nicely implemented at the low-level, but is often somewhat painful to actually use. Its memory savings vs standard arrays (which are really just variable-sized hashmaps -- the most mutable datastructure I can think of) can be enormous, though perhaps not quite as big a savings as it will be once PHP7 gets here. By composing an object with the SplFixedArray, we can have a class which solves the usability issues, while maintaining excellent performance.

### Static-Factory Methods
The SPL datastructures are all very focused on an inheritance-approach, but I found the compositional approach taken in hacklang collections to be far nicer to work with. Indeed, the collections classes in hack are all `final`, implying that you must build your own datastructures composed of them, so I took the same approach with SPL. The big thing you miss out on with inheritance is the `fromArray` method, which is implemented in C and quite fast, however:

```php
class FooFixed extends SplFixedArray {}
$foo = FooFixed::fromArray([1, 2, 3]);
echo get_class($foo);
// => "SplFixedArray"
```

So you can see that while the static class method `fromArray()` was called from a FooFixed class, our `$foo` is not a `FooFixed` at all, but an `SplFixedArray`.

ImmArray, however, uses a compositional approach so we can statically bind the factory methods:

```php
class FooFixed extends ImmArray {}
$foo = FooFixed::fromArray([1, 2, 3]);
echo get_class($foo);
// => "FooFixed"
```

Now that dependency injection, and type-hinting in general, are all the rage, it's more important than ever that our datastructures can be built as objects for the class we want. It's doubly important, because implementing a similar `fromArray()` in PHP is many times slower than the C-optimized `fromArray()` we use here.

### De-facto standard array functions
The good ol' PHP library has a pile of often useful, generally well-performing, but crufty array functions with inconsistent interfaces (e.g. `array_map($callback, $array)` vs `array_walk($array, $callback)`). Dealing with these can be considered one of PHP's quirky little charms. The real problem is, these functions all have one thing in common: your object _must_ be an array. Not arraylike, not ArrayAccessible, not Iterable, not Traversable, etc., but an array. By building in functions so common in JavaScript and elsewhere, e.g. `map()`, `filter()`, and `join()`, one can easily build new immutable arrays by passing a callback to the old one.

```php
$foo = ImmArray::fromArray([1, 2, 3, 4, 5]);
echo $foo->map(function($el) { return $el * 2; })->join(', ');
// => "2, 4, 6, 8, 10"
```

### Serialize as JSON
More and more, PHP is being used less for bloated, view-logic heavy applications, and more as a thin data layer that exists to provide business logic against a datasource, and be consumed by a client side or remote application. I've found most of what I write nowadays simply renders to JSON, which I'll load in a React.js or ember application in the browser. In the interest of being nice to JavaScript developers, it's important to send arrays as arrays, not "arraylike" objects which need to have a bunch of `Object.keys` magic used on them.e.g.

```php
$foo = SplFixedArray::fromArray([1, 2, 3]);
echo json_encode($foo);
// => {"0":1,"1":2,"2":3}
```

The internal logic makese sense to a PHP dev here -- you're encoding properties, after all, but this format is undesirable when working in JS. Objects in js are unordered, so you need to loop through a separate counter, and lookup each string property-name by casting the counter back to string, doing a property lookup, and ending the loop once you've reached the length of the object keys. It's a silly PitA we often have to endure, when we'd much rather get back an array in the first place. e.g.

```php
$foo = ImmArray::fromArray([1, 2, 3]);
echo json_encode($foo);
// => [1,2,3]
```

### Immutability
A special interface gives us an appropriate layer to enforce immutability. While the immutable.php datastructures implement `ArrayAccess`, attempts to push or set to them will fail.

```php
$foo = new ImmArray();
$foo[1] = 'bar';
// => PHP Warning:  Uncaught exception 'RuntimeException' with message 'Attempt
to mutate immutable Qaribou\Collection\ImmArray object.' in
/project/src/Collection/ImmArray.php:169
```

### Alternative Iterators


## PHP7
It's well-known that callbacks are incredibly slow pre-PHPNG days, but once PHP7 becomes the standard the callback-heavy approach to functional programming needed by immutable.php will become far faster. For example, compare this basic test:

```php
// Make 100,000 random strings
$bigSet = ImmArray::fromArray(array_map(function($el) { return md5($el); }, range(0, 100000)));

// Time the map function
$t = microtime(true);
$mapped = $bigSet->map(function($el) { return '{' . $el . '}'; });
echo 'map: ' . (microtime(true) - $t) . 's', PHP_EOL;

// Time the sort function
$t = microtime(true);
$bigSet->sort(function($a, $b) { return strcmp($a, $b); });
echo 'mergeSort: ' . (microtime(true) - $t) . 's', PHP_EOL;
```
On 5.6:
```php
map: 0.30895709991455s
mergeSort: 6.610347032547s
```
On 7.0alpha2:
```php
map: 0.01442813873291s
mergeSort: 0.58948588371277s
```
Holy moly! Running on my laptop, running the map function (which executes a callback) is 21x faster on PHP7. Running the stable mergesort algorithm is 11x faster on PHP7. Big maps and sorts will always be expensive, but PHP7 drops what may be a prohibitively expensive 300ms map, to a much more manageable 14ms.

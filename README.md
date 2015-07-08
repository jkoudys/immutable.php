# immutable.php
Immutable, highly-performant collections, well-suited for functional programming and memory-intensive applications.

```
$polite = ImmArray::fromArray(['set', 'once', 'don\'t', 'mutate']);
echo $polite->join(' ');
// => "set once don't mutate"

$yelling = $polite->map(function($word) { return strtoupper($word); });
echo '<ul>', $yelling->join('<li>', '</li>'), '</ul>';
// => "<ul><li>SET</li><li>ONCE</li><li>DON'T</li><li>MUTATE</li></ul>"

// Big memory footprint: $fruits is 20MB on PHP5.6
$fruits = array_merge(array_fill(0, 1000000, 'peach'), array_fill(0, 1000000, 'banana'));

// Small memory footprint: only 1.6MB
$fruitsImm = ImmArray::fromArray($fruits);

// Yes, we have no bananas
$noBananas = $fruitsImm->filter(function($fruit) { return $fruit !== 'banana'; });

// Array accessible
echo $noBananas[5];
// => "peach"

// Countable
count($noBananas);
// => 1000000

// Iterable
foreach($noBananas as $fruit) {
    FruitCart->sell($fruit);
}

// Even serialize back as json!
echo json_encode(['name' => 'The Peach Pit', 'type' => 'fruit stand', 'fruits' => $noBananas]);
// => {"name": "The Peach Pit", "type": "fruit stand", "fruits": ["peach", "peach", .....
```

## Why
This project was born out of my love for 3 other projects: Hack (http://hacklang.org), immutable.js (https://facebook.github.io/immutable-js/), and the Standard PHP Library (SPL) datastructures (http://php.net/manual/en/spl.datastructures.php).

* Both Hack and immutable.js show that it's both possible, and practical to work with immutable data structures, even in a very loosely-typed language
* The Hack language introduced many collections of its own, along with special syntax, which are unavailable in PHP.
* SPL has some technically excellent, optimized datastructures, which are often impractical in real world applications.

## Why didn't I just use SplFixedArray directly?
The SplFixedArray is very nicely implemented at the low-level, but is often somewhat painful to actually use. Its memory savings vs standard arrays (which are really just variable-sized hashmaps -- the most mutable datastructure I can think of) can be enormous, though perhaps not quite as big a savings as it will be once PHP7 gets here.

### Static-Factory Methods
The SPL datastructures are all very focused on an inheritance-approach, but I found the compositional approach taken in hacklang collections to be far nicer to work with. Indeed, the collections classes in hack are all `final`, implying that you must build your own datastructures composed of them, so I took the same approach with SPL. The big thing you miss out on with inheritance is the `fromArray` method, which is implemented in C and quite fast, however:

```
class FooFixed extends SplFixedArray {}
$foo = FooFixed::fromArray([1, 2, 3]);
echo get_class($foo);
// => "SplFixedArray"
```

So you can see that while the static class method `fromArray()` was called from a FooFixed class, our `$foo` is not a `FooFixed` at all, but an `SplFixedArray`.

ImmArray, however, uses a compositional approach so we can statically bind the factory methods:

```
class FooFixed extends ImmArray {}
$foo = FooFixed::fromArray([1, 2, 3]);
echo get_class($foo);
// => "FooFixed"
```

Now that dependency injection, and type-hinting in general, are all the rage, it's more important than ever that our datastructures can be built as objects for the class we want. It's doubly important, because implementing a similar `fromArray()` in PHP is many times slower than the C-optimized `fromArray()` we use here.

### De-facto standard array functions
The good ol' PHP library has a pile of often useful, generally well-performing, but crufty array functions with inconsistent interfaces (e.g. `array_map($callback, $array)` vs `array_walk($array, $callback)`). Dealing with these can be considered one of PHP's quirky little charms. The real problem is, these functions all have one thing in common: your object _must_ be an array. Not arraylike, not ArrayAccessible, not Iterable, not Traversable, etc., but an array. By building in functions so common in JavaScript and elsewhere, e.g. `map()`, `filter()`, and `join()`, one can easily build new immutable arrays by passing a callback to the old one.

```
$foo = ImmArray::fromArray([1, 2, 3, 4, 5]);
echo $foo->map(function($el) { return $el * 2; })->join(', ');
// => "2, 4, 6, 8, 10"
```

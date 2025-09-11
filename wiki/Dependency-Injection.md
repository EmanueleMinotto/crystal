Some days ago [Faryshta Mextly](https://plus.google.com/u/0/101549844949845796518/posts/dSE5pU3E4Y5) told me that this isn't a microframework, it's only a routing system, and he was right, so 
I started thinking which services should a framework have and final solution was a 
[dependency injection](http://en.wikipedia.org/wiki/Dependency_injection) system that could be used for every other service.

It works like the routing system, except for the first parameter that must match the [regular expression](http://www.php.net/manual/en/language.variables.basics.php) `[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*` and every parameter is stored internally.
The second parameter should be an [`anonymous function`](http://it2.php.net/manual/en/functions.anonymous.php), but can be a [`callable`](http://php.net/manual/en/language.types.callable.php).

```php
class Bar {
    public $foo = 5;
}

$mf('bar', function () {
    return new Bar;
});

$mf('/', function () use ($mf) {
    var_dump($mf('bar')->foo);
});

var_dump(
    $bar,
    $bar->foo
);
// object(Closure)[2]
// object(Bar)[5]
//   public 'foo' => int 5
// int 5
// int 5
```

## Container 

See https://www.php-fig.org/psr/psr-11/

If PHP 7 or a newer version is used, than a `$mf('container')` utility becomes available too.<br/>
The container will have the same values of those defined in the above section.

Using the PHP 7 class, additional arguments can be passed to the service constructor.

```php
class Bar {
    public $foo = 5;

    public function __construct($value) { $this->foo = $value ?: 5; }
}

$mf('bar', function ($value = 5) {
    return new Bar($value);
});

$mf('/', function () use ($mf) {
    var_dump(
        $mf('container')->has('bar'),
        $mf('container')->has('test'),
        $mf('container')->get('bar'),
        $mf('container')->get('bar', 7)
    );
});

// bool true
// bool false
// object(Bar)
//   public 'foo' => int 5
// object(Bar)
//   public 'foo' => int 7
```

### Autowiring

Autowiring is the ability of the container to automatically create and inject dependencies.<br />
This can be done in crystal using the `make` method of the container, this will understand when we are trying to create an object from an existing class and will try to guess the dependencies required for the class (so necessary for the `__construct` method).

In the following example, should not be possible to create the class `Bar` without passing to it an instance of the dependency `Foo`, so the container inspects all the parameters required in the `Bar::__construct` method and if a dependency is not available will try to create it and inject it into the container.

```php
namespace TestContainerAutowiring;

class Foo {
    private $lorem = 'ipsum';
}

class Bar {
    private $foo;

    public function __construct(Foo $foo) {
        $this->foo = $foo;
    }
}

$mf(function () use ($mf) {
    $value = $mf('container')->make(Bar::class);

    var_dump($value);
});
```

At the end, we will have 2 container elements: `TestContainerAutowiring\Bar` and `TestContainerAutowiring\Foo`.<br/>
While `$value` will contain just the `TestContainerAutowiring\Bar` instance.

#### Autowiring Aliases

Sometimes classes require interfaces, you can set a default class for them through aliases.

```php

interface FooInterface {}

class Foo implements FooInterface {
    private $lorem = 'ipsum';
}

class Bar {
    private $foo;

    public function __construct(FooInterface $foo) {
        $this->foo = $foo;
    }
}

$mf(function () use ($mf) {
    $mf('container')->alias('FooInterface', 'Foo');

    $value = $mf('container')->make('Bar');

    var_dump($value);
});
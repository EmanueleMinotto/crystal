--TEST--
Container Autowiring
--INI--
error_reporting=E_ALL & ~E_DEPRECATED
--FILE--
<?php

namespace Test;

$mf = require_once(__DIR__.'/../crystal.php');

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

class Baz {
    public $args;

    // untyped and optional parameters before the resolved ones (implicitly nullable
    // parameters are deprecated in PHP 8.4, "?Foo" isn't supported by PHP 7.0)
    public function __construct($limit = 10, int $size = 5, Foo $foo = null) {
        $this->args = [$limit, $size, get_class($foo)];
    }
}

$mf(function () use ($mf) {
    $mf('container')->alias('Test\\FooInterface', 'Test\\Foo');

    $value = $mf('container')->make('Test\\Bar');

    var_dump($value);
    var_dump($mf('container')->make('Test\\Baz')->args);
});

?>
--EXPECTF--
object(Test\Bar)#%d (1) {
  ["foo":"Test\Bar":private]=>
  object(Test\Foo)#%d (1) {
    ["lorem":"Test\Foo":private]=>
    string(5) "ipsum"
  }
}
array(3) {
  [0]=>
  int(10)
  [1]=>
  int(5)
  [2]=>
  string(8) "Test\Foo"
}

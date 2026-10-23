--TEST--
Container Autowiring
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

$mf(function () use ($mf) {
    $mf('container')->alias('Test\\FooInterface', 'Test\\Foo');

    $value = $mf('container')->make('Test\\Bar');

    var_dump($value);
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
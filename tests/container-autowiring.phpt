--TEST--
Container Autowiring
--SKIPIF--
<?php

if (PHP_MAJOR_VERSION < 7) {
    die('Skip: PHP 7+ is required');
}

?>
--FILE--
<?php

namespace Test;

$mf = require_once(__DIR__.'/../crystal.php');

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
    $value = $mf('container')->make('Test\\Bar');

    var_dump($value);
});

?>
--EXPECT--
object(Test\Bar)#25 (1) {
  ["foo":"Test\Bar":private]=>
  object(Test\Foo)#26 (1) {
    ["lorem":"Test\Foo":private]=>
    string(5) "ipsum"
  }
}
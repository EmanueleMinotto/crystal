--TEST--
Dependency Injection
--FILE--
<?php

$mf = require_once(__DIR__.'/../crystal.php');

class Bar
{
    private static $foo = 0;

    public function __construct()
    {
        ++static::$foo;
    }

    public function getFoo()
    {
        return static::$foo;
    }
}

$mf('bar', function () {
    return new Bar;
});

$bar = $mf('bar');

echo get_class($bar).': '.($bar->getFoo()).PHP_EOL;
echo $mf('bar')->getFoo();

?>
--EXPECT--
Bar: 1
2
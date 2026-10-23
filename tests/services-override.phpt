--TEST--
Services can be overridden
--FILE--
<?php

$mf = require_once(__DIR__.'/../crystal.php');

$listener = new stdClass();
$mf('listener', $listener);

$mf(function () use ($mf, $listener) {
    var_dump($mf('listener') === $listener);
    var_dump($mf('container')->get('listener') === $listener);
});

?>
--EXPECT--
bool(true)
bool(true)

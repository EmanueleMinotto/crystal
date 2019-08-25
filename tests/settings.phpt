--TEST--
Settings
--FILE--
<?php

$mf = require_once(__DIR__.'/../crystal.php');

$mf('pi', M_PI);

$mf(function () use ($mf) {
    echo round($mf('pi'), 5);
});

?>
--EXPECT--
3.14159
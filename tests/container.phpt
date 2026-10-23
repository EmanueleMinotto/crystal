--TEST--
PSR container when PHP 7.0+
--FILE--
<?php

$mf = require_once(__DIR__.'/../crystal.php');

$mf('pi', M_PI);
$mf('test-service-constant', function ($value) {
    return function () use ($value) {
        return $value * 2;
    };
});

$mf(function () use (&$mf) {
    $value = $mf('container')->get('pi');
    echo round($value, 5)."\n";

    $newValueService = $mf('container')->get('test-service-constant', 9);
    echo $newValueService();
});

?>
--EXPECT--
3.14159
18
--TEST--
PSR container when PHP 7.0+
--SKIPIF--
<?php

if (PHP_MAJOR_VERSION < 7) {
    die('Skip: PHP 7+ is required');
}

?>
--FILE--
<?php

$mf = require_once(__DIR__.'/../crystal.php');

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SCRIPT_NAME'] = '/';

$mf('pi', M_PI);
$mf('test-service-constant', function ($value) {
    return function () use ($value) {
        return $value * 2;
    };
});

$mf('/', function () use (&$mf) {
    $value = $mf('container')->get('pi');
    echo round($value, 5)."\n";

    $newValueService = $mf('container')->get('test-service-constant', 9);
    echo $newValueService();
});

?>
--EXPECT--
3.14159
18
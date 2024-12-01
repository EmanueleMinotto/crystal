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

$mf('/', function () use (&$mf) {
    $value = $mf('container')->get('pi');
    echo round($value, 5);
});

?>
--EXPECT--
3.14159
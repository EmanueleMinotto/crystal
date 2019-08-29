--TEST--
PSR container when PHP 7.0+
--SKIPIF--
<?php

$message = 'Skip: psr/container and PHP 7.0+ required';

if (PHP_MAJOR_VERSION < 7) {
    die($message);
}

if (!file_exists(__DIR__.'/../vendor/autoload.php')) {
    die($message);
}

include_once __DIR__.'/../vendor/autoload.php';

if (!interface_exists('Psr\Container\ContainerInterface')) {
    die($message);
}

?>
--FILE--
<?php

require_once __DIR__.'/../vendor/autoload.php';

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
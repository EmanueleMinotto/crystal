--TEST--
Shared services created on first use
--FILE--
<?php

$mf = require_once(__DIR__.'/../crystal.php');

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SCRIPT_NAME'] = '/';

$created = 0;

$mf('container')->share('clock', function () use (&$created) {
    ++$created;

    return new ArrayObject(['now' => 'noon']);
});

var_dump($created);
var_dump($mf('clock') === $mf('clock'), $mf('clock') === $mf('container')->get('clock'));

// injected in the route callbacks too
$mf->get('/', function ($clock) use ($mf, &$created) {
    var_dump($clock === $mf('clock'), $created);
});

?>
--EXPECT--
int(0)
bool(true)
bool(true)
bool(true)
int(1)

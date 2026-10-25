--TEST--
Hits counted in fixed windows
--FILE--
<?php

$mf = require_once(__DIR__.'/../../crystal.php');

$mf('cache')::setDriver('array');
$limiter = $mf('limiter');

var_dump($limiter->hits('user:1'), $limiter->remaining('user:1', 3), $limiter->availableIn('user:1'));

var_dump($limiter->attempt('user:1', 3, 1));
var_dump($limiter->attempt('user:1', 3, 1));
var_dump($limiter->attempt('user:1', 3, 1));
var_dump($limiter->attempt('user:1', 3, 1));
var_dump($limiter->hits('user:1'), $limiter->remaining('user:1', 3), $limiter->tooManyAttempts('user:1', 3));
var_dump(1 === $limiter->availableIn('user:1'));

// counters are separated by key
var_dump($limiter->hits('user:2'), $limiter->hit('user:2', 60), $limiter->hit('user:2', 60));

// a new window starts when the previous one ends
sleep(1);
var_dump($limiter->hits('user:1'), $limiter->availableIn('user:1'), $limiter->attempt('user:1', 3, 1));

$limiter->clear('user:2');
var_dump($limiter->hits('user:2'));

?>
--EXPECT--
int(0)
int(3)
int(0)
bool(true)
bool(true)
bool(true)
bool(false)
int(3)
int(0)
bool(true)
bool(true)
int(0)
int(1)
int(2)
int(0)
int(0)
bool(true)
int(0)

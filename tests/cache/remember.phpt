--TEST--
Values computed once and cached with remember()
--FILE--
<?php

$mf = require_once(__DIR__.'/../../crystal.php');

$cache = $mf('cache');
$cache::setDriver('array');

$calls = 0;
$compute = function () use (&$calls) {
    ++$calls;

    return $calls > 1 ? 'computed again' : 'computed';
};

var_dump($cache->remember('stats', 60, $compute));
var_dump($cache->remember('stats', 60, $compute));

// null values are cached too
$cache->remember('nothing', null, function () use (&$calls) {
    ++$calls;
});
$cache->remember('nothing', null, function () use (&$calls) {
    ++$calls;
});

// namespaces keep their keys
var_dump($cache->withNamespace('users')->remember('stats', 60, $compute));

var_dump($calls);

?>
--EXPECT--
string(8) "computed"
string(8) "computed"
string(14) "computed again"
int(3)

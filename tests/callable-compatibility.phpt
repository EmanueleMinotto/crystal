--TEST--
Framework instance is usable as a callable
--FILE--
<?php

$mf = require_once(__DIR__.'/../crystal.php');

var_dump(is_callable($mf));

call_user_func($mf, 'foo', 'bar');
echo call_user_func_array($mf, ['foo'])."\n";
echo $mf->__invoke('foo')."\n";

$cb = function (callable $mf) {
    return $mf('foo');
};
echo $cb($mf)."\n";

?>
--EXPECT--
bool(true)
bar
bar
bar

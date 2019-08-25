--TEST--
CLI usage with arguments
--FILE--
<?php

$GLOBALS['argv'][] = 'foo';
$GLOBALS['argv'][] = 'bar';

$mf = require_once(__DIR__.'/../crystal.php');

$mf(function ($a, $b) {
    echo $b . ' ' . $a;
});

?>
--EXPECT--
bar foo
--TEST--
CLI usage with arguments
--ARGS--
foo bar
--FILE--
<?php

$mf = require_once(__DIR__.'/../crystal.php');

$mf(function ($a, $b) {
    echo $b . ' ' . $a;
});

?>
--EXPECT--
bar foo
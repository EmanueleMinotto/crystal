--TEST--
Basic CLI usage
--FILE--
<?php

$mf = require_once(__DIR__.'/../crystal.php');

$mf(function () {
    echo 'Hello World!';
});

?>
--EXPECT--
Hello World!
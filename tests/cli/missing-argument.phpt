--TEST--
CLI callbacks report the required parameters without a value
--SKIPIF--
<?php if (!class_exists('ArgumentCountError')) die('skip ArgumentCountError is PHP 7.1+'); ?>
--FILE--
<?php

$GLOBALS['argv'] = ['script.php'];

$mf = require_once(__DIR__.'/../../crystal.php');

$mf('cli')->enable();

try {
    $mf('cli')->invoke(function ($file) {});
} catch (ArgumentCountError $exception) {
    echo $exception->getMessage();
}

?>
--EXPECT--
Too few arguments to the command line callback: $file is neither an argument of the script nor a service.

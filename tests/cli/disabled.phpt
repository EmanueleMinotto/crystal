--TEST--
CLI arguments are passed as they are when the cli service isn't enabled
--FILE--
<?php

$GLOBALS['argv'] = ['script.php', 'users.csv', '--dry-run'];

$mf = require_once(__DIR__.'/../../crystal.php');

var_dump($mf('cli')->isEnabled());

$mf(function ($file, $option = null, $logger = null) {
    var_dump($file, $option, $logger);
});

?>
--EXPECT--
bool(false)
string(9) "users.csv"
string(9) "--dry-run"
NULL

--TEST--
CLI options are split from the arguments when the cli service is enabled
--FILE--
<?php

$GLOBALS['argv'] = ['script.php', 'users.csv', '--dry-run', '--limit=10', '-5', '--limit=20', '--name=a=b', '--', '--literal'];

$mf = require_once(__DIR__.'/../../crystal.php');

$mf('cli')->enable();

$mf(function ($file, $number, $literal, $cli) {
    var_dump($file, $number, $literal);
    var_dump($cli->option('dry-run'), $cli->option('limit'), $cli->option('name'), $cli->option('missing', 'default'));
    var_dump(count(func_get_args()));
});

?>
--EXPECT--
string(9) "users.csv"
string(2) "-5"
string(9) "--literal"
bool(true)
string(2) "20"
string(3) "a=b"
string(7) "default"
int(4)

--TEST--
CGI with multiple actions
--FILE--
<?php

$mf = require_once(__DIR__.'/../crystal.php');

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/hello/world';
$_SERVER['SCRIPT_NAME'] = '/';

$mf('/hello/world', function () {
    echo 'Hello ';
});
$mf('/hello(.*)', function () {
    echo 'World!';
});

?>
--EXPECT--
Hello World!

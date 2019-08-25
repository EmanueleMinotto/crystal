--TEST--
Basic CGI usage
--FILE--
<?php

$mf = require_once(__DIR__.'/../crystal.php');

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SCRIPT_NAME'] = '/';

$mf('/', function () {
    echo 'Hello World!';
});

?>
--EXPECT--
Hello World!
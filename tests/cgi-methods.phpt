--TEST--
CGI methods allowed
--FILE--
<?php

$mf = require_once(__DIR__.'/../crystal.php');

$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SCRIPT_NAME'] = '/';
$_SERVER['REQUEST_METHOD'] = 'POST';

$mf('/', function () {
    echo 'Hello ' . $_SERVER['REQUEST_METHOD'] . '!';
}, 'GET|POST');

?>
--EXPECT--
Hello POST!